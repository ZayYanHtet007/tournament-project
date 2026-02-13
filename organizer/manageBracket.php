<?php
session_start();
// If the request is POST and the script name is not 'manageBracket.php', abort
if ($_SERVER['SCRIPT_NAME'] !== '/manageBracket.php') {
    die('Wrong script! Please use manageBracket.php directly.');
}
require_once "../database/dbConfig.php";


/* ---------- ACCESS CONTROL ---------- */
if (
    !isset($_SESSION['user_id']) ||
    !$_SESSION['is_organizer'] ||
    $_SESSION['organizer_status'] !== 'approved'
) {
    header("Location: ../login.php");
    exit;
}

/* ---------- TOURNAMENT VALIDATION ---------- */
$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if (!$tournament_id) die("Invalid tournament");

// Verify ownership and fetch tournament info
$stmt = $conn->prepare("
    SELECT tournament_id, title, status
    FROM tournaments
    WHERE tournament_id = ? AND organizer_id = ?
");
$stmt->bind_param("ii", $tournament_id, $_SESSION['user_id']);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
if (!$tournament) die("Tournament not found or access denied");

/* ---------- HELPER FUNCTIONS ---------- */

function roundLabel($round) {
    return match ($round) {
        'group'        => 'Group Stage',
        'quarterfinal' => 'Quarterfinal',
        'semifinal'    => 'Semifinal',
        'final'        => 'Final',
        'third_place'  => 'Third Place',
        default        => ucfirst($round)
    };
}

// Check if all matches in a given round are completed
function isRoundComplete($conn, $tid, $round) {
    $q = $conn->prepare("
        SELECT COUNT(*) FROM matches
        WHERE tournament_id = ? AND round = ? AND status = 'pending'
    ");
    $q->bind_param("is", $tid, $round);
    $q->execute();
    return $q->get_result()->fetch_row()[0] == 0;
}

// Recalculate standings for a specific group (A, B, C, D)
function recalcGroupStandings($conn, $tournament_id, $group_name) {
    // 1. Get all teams in this group from group_standings
    $teams = [];
    $teamQuery = $conn->prepare("
        SELECT team_id FROM group_standings
        WHERE tournament_id = ? AND group_name = ?
    ");
    $teamQuery->bind_param("is", $tournament_id, $group_name);
    $teamQuery->execute();
    $teamRes = $teamQuery->get_result();
    while ($row = $teamRes->fetch_assoc()) {
        $teams[$row['team_id']] = [
            'played' => 0,
            'wins'   => 0,
            'losses' => 0,
            'points' => 0,
            'net_game' => 0,
            'duration' => 0
        ];
    }

    // 2. Fetch all completed group matches for this group
    $matchQuery = $conn->prepare("
        SELECT m.*, ms.team1_score, ms.team2_score, ms.set_number
        FROM matches m
        LEFT JOIN match_scores ms ON m.match_id = ms.match_id
        WHERE m.tournament_id = ?
          AND m.round = 'group'
          AND m.group_name = ?
          AND m.status = 'completed'
        ORDER BY m.match_id, ms.set_number
    ");
    $matchQuery->bind_param("is", $tournament_id, $group_name);
    $matchQuery->execute();
    $matchRes = $matchQuery->get_result();

    // Aggregate per match (since match_scores stores sets)
    $matchesData = [];
    while ($row = $matchRes->fetch_assoc()) {
        $mid = $row['match_id'];
        if (!isset($matchesData[$mid])) {
            $matchesData[$mid] = [
                'team1_id' => $row['team1_id'],
                'team2_id' => $row['team2_id'],
                'score1' => 0,
                'score2' => 0,
                'winner' => $row['winner_team_id'],
                'duration' => $row['duration_seconds'] ?? 0
            ];
        }
        $matchesData[$mid]['score1'] += $row['team1_score'];
        $matchesData[$mid]['score2'] += $row['team2_score'];
    }

    // 3. Process each match to update stats
    foreach ($matchesData as $match) {
        $t1 = $match['team1_id'];
        $t2 = $match['team2_id'];
        $s1 = $match['score1'];
        $s2 = $match['score2'];
        $winner = $match['winner'];
        $dur = $match['duration'];

        if (!isset($teams[$t1]) || !isset($teams[$t2])) continue; // safety

        // Update match counts
        $teams[$t1]['played']++;
        $teams[$t2]['played']++;

        // Update wins/losses and points
        if ($winner == $t1) {
            $teams[$t1]['wins']++;
            $teams[$t2]['losses']++;
            if ($s1 == 2 && $s2 == 0) {
                $teams[$t1]['points'] += 3;
            } elseif ($s1 == 2 && $s2 == 1) {
                $teams[$t1]['points'] += 2;
                $teams[$t2]['points'] += 1;
            }
        } else {
            $teams[$t2]['wins']++;
            $teams[$t1]['losses']++;
            if ($s2 == 2 && $s1 == 0) {
                $teams[$t2]['points'] += 3;
            } elseif ($s2 == 2 && $s1 == 1) {
                $teams[$t2]['points'] += 2;
                $teams[$t1]['points'] += 1;
            }
        }

        // Net game = wins - losses (match differential)
        // (we'll compute after all matches)
        // Duration
        $teams[$t1]['duration'] += $dur;
        $teams[$t2]['duration'] += $dur;
    }

    // 4. Compute net_game and update group_standings
    foreach ($teams as $team_id => $stats) {
        $net = $stats['wins'] - $stats['losses'];
        $update = $conn->prepare("
            UPDATE group_standings
            SET played = ?, wins = ?, losses = ?, points = ?,
                net_game = ?, duration = ?
            WHERE tournament_id = ? AND team_id = ?
        ");
        $update->bind_param(
            "iiiiiiii",
            $stats['played'],
            $stats['wins'],
            $stats['losses'],
            $stats['points'],
            $net,
            $stats['duration'],
            $tournament_id,
            $team_id
        );
        $update->execute();
    }
}

// Assign top 2 from each group to quarterfinal placeholders
function assignKnockoutTeams($conn, $tournament_id) {
    // Fetch all groups and their standings
    $groups = ['A','B','C','D'];
    $qualifiers = [];

    foreach ($groups as $group) {
        $q = $conn->prepare("
            SELECT team_id, points, net_game, duration
            FROM group_standings
            WHERE tournament_id = ? AND group_name = ?
            ORDER BY points DESC, net_game DESC, duration ASC
        ");
        $q->bind_param("is", $tournament_id, $group);
        $q->execute();
        $res = $q->get_result();
        $teams = [];
        while ($row = $res->fetch_assoc()) {
            $teams[] = $row['team_id'];
        }
        // Take top 2, if tie beyond 2nd place we already sorted with coin-flip? Actually we need deterministic.
        // For ties that are exactly 2nd/3rd, we apply coin flip now.
        if (count($teams) >= 2) {
            // Check if 2nd and 3rd have identical points, net, duration
            if (isset($teams[1], $teams[2])) {
                $s2 = $teams[1];
                $s3 = $teams[2];
                // Compare stats (we need to fetch again or we stored in $row? Simpler: re-fetch comparison)
                $comp = $conn->prepare("
                    SELECT points, net_game, duration FROM group_standings
                    WHERE tournament_id = ? AND team_id = ?
                ");
                $comp->bind_param("ii", $tournament_id, $s2);
                $comp->execute();
                $r2 = $comp->get_result()->fetch_assoc();
                $comp->bind_param("ii", $tournament_id, $s3);
                $comp->execute();
                $r3 = $comp->get_result()->fetch_assoc();
                if ($r2['points'] == $r3['points'] &&
                    $r2['net_game'] == $r3['net_game'] &&
                    $r2['duration'] == $r3['duration']) {
                    // Coin flip: randomly choose one to be #2, other #3
                    if (rand(0,1) == 0) {
                        // swap
                        $teams[1] = $s3;
                        $teams[2] = $s2;
                    }
                }
            }
            $qualifiers[] = $teams[0];
            $qualifiers[] = $teams[1];
        }
    }

    // Shuffle qualifiers
    shuffle($qualifiers);

    // Fetch quarterfinal matches that are still placeholders (team1_id IS NULL)
    $qfMatches = [];
    $q = $conn->prepare("
        SELECT match_id FROM matches
        WHERE tournament_id = ? AND round = 'quarterfinal'
          AND team1_id IS NULL AND team2_id IS NULL
        ORDER BY match_order
    ");
    $q->bind_param("i", $tournament_id);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $qfMatches[] = $row['match_id'];
    }

    // Assign qualifiers in pairs
    for ($i = 0; $i < count($qfMatches); $i++) {
        if (!isset($qualifiers[$i*2], $qualifiers[$i*2+1])) break;
        $upd = $conn->prepare("
            UPDATE matches
            SET team1_id = ?, team2_id = ?
            WHERE match_id = ?
        ");
        $upd->bind_param("iii", $qualifiers[$i*2], $qualifiers[$i*2+1], $qfMatches[$i]);
        $upd->execute();
    }
}

// Advance winners to next knockout round
function advanceKnockoutRound($conn, $tournament_id, $currentRound) {
    // Map current round to next round
    $nextRoundMap = [
        'quarterfinal' => 'semifinal',
        'semifinal'    => 'final'
    ];
    if (!isset($nextRoundMap[$currentRound])) return; // final or third_place handled separately

    $nextRound = $nextRoundMap[$currentRound];

    // Fetch winners in order of match_order
    $winners = [];
    $q = $conn->prepare("
        SELECT winner_team_id FROM matches
        WHERE tournament_id = ? AND round = ? AND status = 'completed'
        ORDER BY match_order
    ");
    $q->bind_param("is", $tournament_id, $currentRound);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $winners[] = $row['winner_team_id'];
    }

    // Fetch placeholder matches for next round
    $nextMatches = [];
    $q2 = $conn->prepare("
        SELECT match_id FROM matches
        WHERE tournament_id = ? AND round = ? AND team1_id IS NULL AND team2_id IS NULL
        ORDER BY match_order
    ");
    $q2->bind_param("is", $tournament_id, $nextRound);
    $q2->execute();
    $res2 = $q2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $nextMatches[] = $row['match_id'];
    }

    // Pair winners
    for ($i = 0; $i < count($nextMatches); $i++) {
        if (!isset($winners[$i*2], $winners[$i*2+1])) break;
        $upd = $conn->prepare("
            UPDATE matches
            SET team1_id = ?, team2_id = ?
            WHERE match_id = ?
        ");
        $upd->bind_param("iii", $winners[$i*2], $winners[$i*2+1], $nextMatches[$i]);
        $upd->execute();
    }

    // Special: third place match uses losers of semifinals
    if ($currentRound === 'semifinal') {
        // Get losers (the other team) from each semifinal
        $losers = [];
        $q3 = $conn->prepare("
            SELECT 
                CASE WHEN winner_team_id = team1_id THEN team2_id ELSE team1_id END as loser_id
            FROM matches
            WHERE tournament_id = ? AND round = 'semifinal' AND status = 'completed'
            ORDER BY match_order
        ");
        $q3->bind_param("i", $tournament_id);
        $q3->execute();
        $res3 = $q3->get_result();
        while ($row = $res3->fetch_assoc()) {
            $losers[] = $row['loser_id'];
        }
        // There should be exactly 2 losers
        if (count($losers) >= 2) {
            $thirdMatch = $conn->prepare("
                SELECT match_id FROM matches
                WHERE tournament_id = ? AND round = 'third_place'
                LIMIT 1
            ");
            $thirdMatch->bind_param("i", $tournament_id);
            $thirdMatch->execute();
            $thirdRow = $thirdMatch->get_result()->fetch_assoc();
            if ($thirdRow) {
                $updThird = $conn->prepare("
                    UPDATE matches
                    SET team1_id = ?, team2_id = ?
                    WHERE match_id = ?
                ");
                $updThird->bind_param("iii", $losers[0], $losers[1], $thirdRow['match_id']);
                $updThird->execute();
            }
        }
    }
}

/* ---------- SAVE SCORE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_id'])) {

  $match_id = (int)$_POST['match_id'];
  $score1   = (int)$_POST['score1'];
  $score2   = (int)$_POST['score2'];

  if ($score1 === $score2) {
    die("Draws not allowed");
  }

  $q = $conn->prepare("
        SELECT * FROM matches 
        WHERE match_id=? AND tournament_id=? AND status='pending'
    ");
  $q->bind_param("ii", $match_id, $tournament_id);
  $q->execute();
  $match = $q->get_result()->fetch_assoc();

  if (!$match) die("Invalid match");

  $winner = $score1 > $score2 ? $match['team1_id'] : $match['team2_id'];

            // Delete old set scores (if any)
            $deleteScores->bind_param("i", $match_id);
            $deleteScores->execute();

            // Insert new set scores
            // We assume scores are totals; but we need to store per-set.
            // Since we don't have per-set inputs, we need to decide: either we ask for per-set scores, or we store a single "set" as the whole match.
            // According to your schema, match_scores stores set_number. We'll treat each submitted score as the final totals and store one set.
            $insertScore->bind_param("iiii", $match_id, $score1, $score2, $setNumber);
            $setNumber = 1;
            $insertScore->execute();

            // Update matches table
            $updateMatch->bind_param("iiiiii", $score1, $score2, $winner, $duration, $match_id, $tournament_id);
            $updateMatch->execute();

            $savedCount++;

            // --- POST-UPDATE ACTIONS ---
            if ($match['round'] === 'group') {
                // Recalculate standings for this group
                recalcGroupStandings($conn, $tournament_id, $match['group_name']);
            }
        }

        // After processing all matches, check for global phase transitions
        // 1. If all group matches are completed, assign knockout teams
        if (isRoundComplete($conn, $tournament_id, 'group')) {
            assignKnockoutTeams($conn, $tournament_id);
        }

        // 2. For each knockout round, if that round is complete, advance
        $knockoutRounds = ['quarterfinal', 'semifinal'];
        foreach ($knockoutRounds as $round) {
            if (isRoundComplete($conn, $tournament_id, $round)) {
                advanceKnockoutRound($conn, $tournament_id, $round);
            }
        }

        // 3. If final is complete, maybe set tournament status to 'completed'
        if (isRoundComplete($conn, $tournament_id, 'final')) {
            $updStatus = $conn->prepare("UPDATE tournaments SET status = 'completed' WHERE tournament_id = ?");
            $updStatus->bind_param("i", $tournament_id);
            $updStatus->execute();
        }

        $conn->commit();
        $_SESSION['flash'] = "✅ $savedCount match result(s) saved successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash'] = "❌ Error: " . $e->getMessage();
    }
    header("Location: manageBracket.php?tournament_id=$tournament_id");
    exit;
}

/* ---------- FETCH ALL MATCHES FOR DISPLAY ---------- */
$matches = $conn->query("
    SELECT m.*,
           t1.team_name AS team1,
           t2.team_name AS team2,
           w.team_name AS winner_name
    FROM matches m
    LEFT JOIN teams t1 ON m.team1_id = t1.team_id
    LEFT JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN teams w ON m.winner_team_id = w.team_id
    WHERE m.tournament_id = $tournament_id
    ORDER BY
        FIELD(m.round, 'group', 'quarterfinal', 'semifinal', 'final', 'third_place'),
        m.group_name,
        m.match_order
");

// Fetch flash message
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Fetch group standings for display (if any group matches exist)
$groupStandings = [];
$hasGroupMatches = $conn->query("
    SELECT 1 FROM matches WHERE tournament_id = $tournament_id AND round = 'group' LIMIT 1
")->num_rows > 0;
if ($hasGroupMatches) {
    $groups = ['A','B','C','D'];
    foreach ($groups as $group) {
        $q = $conn->prepare("
            SELECT gs.*, t.team_name
            FROM group_standings gs
            JOIN teams t ON gs.team_id = t.team_id
            WHERE gs.tournament_id = ? AND gs.group_name = ?
            ORDER BY gs.points DESC, gs.net_game DESC, gs.duration ASC
        ");
        $q->bind_param("is", $tournament_id, $group);
        $q->execute();
        $res = $q->get_result();
        while ($row = $res->fetch_assoc()) {
            $groupStandings[$group][] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bracket Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">🏆 <?= htmlspecialchars($tournament['title']) ?> – Bracket</h1>
            <a href="manage-schedule.php?tournament_id=<?= $tournament_id ?>"
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                📅 Back to Schedule
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $flash ?>
            </div>
        <?php endif; ?>

        <!-- GROUP STANDINGS -->
        <?php if (!empty($groupStandings)): ?>
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4">📊 Group Stage Standings</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach (['A','B','C','D'] as $group): ?>
                        <?php if (isset($groupStandings[$group]) && count($groupStandings[$group]) > 0): ?>
                            <div class="bg-white rounded shadow p-4">
                                <h3 class="font-semibold text-lg mb-2">Group <?= $group ?></h3>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left py-2">Team</th>
                                            <th class="text-center">P</th>
                                            <th class="text-center">W</th>
                                            <th class="text-center">L</th>
                                            <th class="text-center">PTS</th>
                                            <th class="text-center">NET</th>
                                            <th class="text-center">DUR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rank = 1; ?>
                                        <?php foreach ($groupStandings[$group] as $team): ?>
                                            <tr class="<?= $rank <= 2 ? 'bg-green-50' : '' ?>">
                                                <td class="py-1"><?= htmlspecialchars($team['team_name']) ?></td>
                                                <td class="text-center"><?= $team['played'] ?></td>
                                                <td class="text-center"><?= $team['wins'] ?></td>
                                                <td class="text-center"><?= $team['losses'] ?></td>
                                                <td class="text-center font-bold"><?= $team['points'] ?></td>
                                                <td class="text-center"><?= $team['net_game'] ?></td>
                                                <td class="text-center"><?= gmdate('i:s', $team['duration']) ?></td>
                                            </tr>
                                            <?php $rank++; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- MATCHES FORM -->
        <?php if ($matches->num_rows === 0): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                No matches have been generated yet. Go to <a href="manage-schedule.php?tournament_id=<?= $tournament_id ?>" class="underline">Schedule page</a> to generate them.
            </div>
        <?php else: ?>
            <form method="post" class="space-y-6">
                <input type="hidden" name="bulk_save" value="1">
                <?php
                $currentRound = '';
                while ($m = $matches->fetch_assoc()):
                    if ($currentRound !== $m['round']):
                        $currentRound = $m['round'];
                        echo "<h2 class='text-xl font-semibold mt-8 mb-4 capitalize'>" . roundLabel($currentRound) . "</h2>";
                    endif;

                    // Determine if this match is editable
                    $isEditable = ($m['status'] === 'pending' && !empty($m['scheduled_time']) && $m['team1_id'] && $m['team2_id']);
                    $isGroup = ($m['round'] === 'group');
                ?>
                    <div class="bg-white p-4 rounded shadow <?= $m['status'] === 'completed' ? 'border-l-4 border-green-500' : '' ?>">
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Match Info -->
                            <div class="w-64">
                                <?php if ($m['group_name']): ?>
                                    <span class="text-xs font-medium bg-gray-200 px-2 py-1 rounded">Group <?= $m['group_name'] ?></span>
                                <?php endif; ?>
                                <div class="font-medium">
                                    <?= htmlspecialchars($m['team1'] ?? 'TBD') ?> vs <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                                </div>
                                <?php if (!$m['team1_id'] || !$m['team2_id']): ?>
                                    <span class="text-xs text-gray-500">Placeholder – teams not assigned yet</span>
                                <?php endif; ?>
                            </div>

                            <!-- Score Inputs -->
                            <div class="flex-1 flex items-center gap-3">
                                <input type="number" name="score1[<?= $m['match_id'] ?>]" min="0" max="5"
                                       class="w-16 border rounded px-2 py-1 text-center"
                                       value="<?= $m['score1'] ?>"
                                       <?= !$isEditable ? 'readonly' : '' ?>
                                       <?= !$isEditable ? 'tabindex="-1"' : '' ?>>
                                <span class="font-bold">:</span>
                                <input type="number" name="score2[<?= $m['match_id'] ?>]" min="0" max="5"
                                       class="w-16 border rounded px-2 py-1 text-center"
                                       value="<?= $m['score2'] ?>"
                                       <?= !$isEditable ? 'readonly' : '' ?>
                                       <?= !$isEditable ? 'tabindex="-1"' : '' ?>>
                            </div>

                            <!-- Duration (only for group stage) -->
                            <?php if ($isGroup): ?>
                                <div class="w-32">
                                    <input type="text" name="duration[<?= $m['match_id'] ?>]"
                                           placeholder="mm:ss"
                                           class="w-full border rounded px-2 py-1"
                                           value="<?= $m['duration_seconds'] ? gmdate('i:s', $m['duration_seconds']) : '' ?>"
                                           <?= !$isEditable ? 'readonly' : '' ?>
                                           <?= !$isEditable ? 'tabindex="-1"' : '' ?>>
                                </div>
                            <?php endif; ?>

                            <!-- Status / Winner -->
                            <div class="w-40 text-right">
                                <?php if ($m['status'] === 'completed'): ?>
                                    <span class="text-green-600 font-semibold text-sm">✓ Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?></span>
                                <?php elseif (!$m['team1_id'] || !$m['team2_id']): ?>
                                    <span class="text-gray-500 text-sm">Waiting for assignment</span>
                                <?php elseif (empty($m['scheduled_time'])): ?>
                                    <span class="text-red-500 text-sm">⚠️ Schedule not set</span>
                                <?php else: ?>
                                    <span class="text-yellow-600 text-sm">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>

                <div class="mt-8 flex justify-end">
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded shadow text-lg">
                        💾 Save All Results
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>