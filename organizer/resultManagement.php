<?php
session_start();
// If the request is POST and the script name is not 'manageBracket.php', abort


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

/* ---------- FETCH TOURNAMENT RESULTS (WINNER, RUNNERS-UP) ---------- */
function fetchTournamentResults($conn, $tournament_id) {
    $results = [
        'winner' => null,
        'first_runner_up' => null,
        'second_runner_up' => null,
        'winner_name' => null,
        'first_runner_up_name' => null,
        'second_runner_up_name' => null
    ];

    // Get final match
    $final = $conn->prepare("
        SELECT m.team1_id, m.team2_id, m.winner_team_id,
               t1.team_name AS team1_name, t2.team_name AS team2_name
        FROM matches m
        LEFT JOIN teams t1 ON m.team1_id = t1.team_id
        LEFT JOIN teams t2 ON m.team2_id = t2.team_id
        WHERE m.tournament_id = ? AND m.round = 'final' AND m.status = 'completed'
        LIMIT 1
    ");
    $final->bind_param("i", $tournament_id);
    $final->execute();
    $finalData = $final->get_result()->fetch_assoc();

    if ($finalData) {
        $results['winner'] = $finalData['winner_team_id'];
        // Loser is the other team
        $results['first_runner_up'] = ($finalData['winner_team_id'] == $finalData['team1_id']) 
                                      ? $finalData['team2_id'] 
                                      : $finalData['team1_id'];
        $results['winner_name'] = ($finalData['winner_team_id'] == $finalData['team1_id'])
                                  ? $finalData['team1_name']
                                  : $finalData['team2_name'];
        $results['first_runner_up_name'] = ($results['first_runner_up'] == $finalData['team1_id'])
                                          ? $finalData['team1_name']
                                          : $finalData['team2_name'];
    }

    // Get third place match winner
    $third = $conn->prepare("
        SELECT m.winner_team_id, t.team_name
        FROM matches m
        LEFT JOIN teams t ON m.winner_team_id = t.team_id
        WHERE m.tournament_id = ? AND m.round = 'third_place' AND m.status = 'completed'
        LIMIT 1
    ");
    $third->bind_param("i", $tournament_id);
    $third->execute();
    $thirdData = $third->get_result()->fetch_assoc();

    if ($thirdData) {
        $results['second_runner_up'] = $thirdData['winner_team_id'];
        $results['second_runner_up_name'] = $thirdData['team_name'];
    }

    return $results;
}

/* ---------- BULK SCORE SUBMISSION ---------- */
$flashMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    // Get match IDs from the score1 array keys
    $matchIds = array_keys($_POST['score1'] ?? []);
    $savedCount = 0;

    $conn->begin_transaction();
    try {
        // Prepare statements once
        $deleteScores = $conn->prepare("DELETE FROM match_scores WHERE match_id = ?");
        $insertScore = $conn->prepare("
            INSERT INTO match_scores (match_id, team1_score, team2_score, set_number)
            VALUES (?, ?, ?, ?)
        ");
        // UPDATE matches – NO score1/score2 columns, only winner, status, duration
        $updateMatch = $conn->prepare("
            UPDATE matches
            SET winner_team_id = ?,
                status = 'completed',
                duration_seconds = ?
            WHERE match_id = ? AND tournament_id = ? AND status = 'pending'
        ");

        foreach ($matchIds as $match_id) {
            $match_id = (int)$match_id;

            $score1 = (int)($_POST['score1'][$match_id] ?? 0);
            $score2 = (int)($_POST['score2'][$match_id] ?? 0);
            $duration = 0;
            if (isset($_POST['duration'][$match_id]) && $_POST['duration'][$match_id] !== '') {
                $duration_parts = explode(':', $_POST['duration'][$match_id]);
                $duration = (int)($duration_parts[0] ?? 0) * 60 + (int)($duration_parts[1] ?? 0);
            }

            // Fetch match details to validate and get round info
            $q = $conn->prepare("
                SELECT * FROM matches
                WHERE match_id = ? AND tournament_id = ? AND status = 'pending'
            ");
            $q->bind_param("ii", $match_id, $tournament_id);
            $q->execute();
            $match = $q->get_result()->fetch_assoc();
            if (!$match) continue; // not pending or wrong tournament

            // --- VALIDATION ---
            // 1. Scheduled time must be set
            if (empty($match['scheduled_time'])) {
                throw new Exception("Match #$match_id: Schedule not set, cannot enter score.");
            }
            // 2. No draws
            if ($score1 === $score2) {
                throw new Exception("Match #$match_id: Draws are not allowed.");
            }
            // 3. Both teams must be assigned (should be true for pending matches, but check)
            if (!$match['team1_id'] || !$match['team2_id']) {
                throw new Exception("Match #$match_id: Teams not assigned.");
            }

            // Determine winner
            $winner = $score1 > $score2 ? $match['team1_id'] : $match['team2_id'];

            // Delete old set scores (if any)
            $deleteScores->bind_param("i", $match_id);
            $deleteScores->execute();

            // Insert new set scores – store as one set (total scores)
            $insertScore->bind_param("iiii", $match_id, $score1, $score2, $setNumber);
            $setNumber = 1;
            $insertScore->execute();

            // Update matches table – only winner, duration, status
            $updateMatch->bind_param("iiii", $winner, $duration, $match_id, $tournament_id);
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

        // 3. If both final AND third_place are complete, set tournament status to 'completed'
        if (isRoundComplete($conn, $tournament_id, 'final') && 
            isRoundComplete($conn, $tournament_id, 'third_place')) {
            $updStatus = $conn->prepare("UPDATE tournaments SET status = 'completed' WHERE tournament_id = ?");
            $updStatus->bind_param("i", $tournament_id);
            $updStatus->execute();
            
            
            // Insert winner into tournament_results
            $winnerData = fetchTournamentResults($conn, $tournament_id);
            if ($winnerData['winner']) {
                $insResult = $conn->prepare("INSERT INTO tournament_results (tournament_id, winner_team_id) VALUES (?, ?)");
                $insResult->bind_param("ii", $tournament_id, $winnerData['winner']);
                $insResult->execute();
            }
        }

        $conn->commit();
        $_SESSION['flash'] = "✅ $savedCount match result(s) saved successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash'] = "❌ Error: " . $e->getMessage();
    }
    // Redirect back to the same page
    header("Location: resultManagement.php?tournament_id=$tournament_id");
    exit;
}

/* ---------- FETCH ALL MATCHES WITH AGGREGATED SCORES ---------- */
// First, get all matches
$matchesResult = $conn->query("
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

// Fetch total scores for each match from match_scores
$scoresMap = [];
$scoreQuery = $conn->prepare("
    SELECT match_id, SUM(team1_score) as total1, SUM(team2_score) as total2
    FROM match_scores
    WHERE match_id IN (
        SELECT match_id FROM matches WHERE tournament_id = ?
    )
    GROUP BY match_id
");
$scoreQuery->bind_param("i", $tournament_id);
$scoreQuery->execute();
$scoreRes = $scoreQuery->get_result();
while ($row = $scoreRes->fetch_assoc()) {
    $scoresMap[$row['match_id']] = [
        'total1' => $row['total1'],
        'total2' => $row['total2']
    ];
}

// Merge scores into matches array
$matchesList = [];
while ($row = $matchesResult->fetch_assoc()) {
    $row['score1'] = $scoresMap[$row['match_id']]['total1'] ?? 0;
    $row['score2'] = $scoresMap[$row['match_id']]['total2'] ?? 0;
    $matchesList[] = $row;
}

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

// Fetch tournament results if status is 'completed'
$tournamentResults = null;
if ($tournament['status'] === 'completed') {
    $tournamentResults = fetchTournamentResults($conn, $tournament_id);
}

// Group matches for display (group stage by group)
$groupedMatches = [];
foreach ($matchesList as $m) {
    $round = $m['round'];
    if ($round === 'group') {
        $group = $m['group_name'] ?? '?';
        $groupedMatches['group'][$group][] = $m;
    } else {
        $groupedMatches[$round][] = $m;
    }
}
$roundOrder = ['group', 'quarterfinal', 'semifinal', 'final', 'third_place'];
$roundLabels = [
    'group' => 'Group Stage',
    'quarterfinal' => 'Quarterfinals',
    'semifinal' => 'Semifinals',
    'final' => 'Final',
    'third_place' => 'Third Place Match'
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bracket Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Riot style blue‑black theme with enhanced gaming touches */
        body {
            background-color: #0a0c10;
            color: #e8e9ea;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
        }
        .riot-card {
            border-top: 4px solid #0b4a8f;
            transition: all 0.3s ease;
            background-color: #1a1c22;
            border-color: #2f3136;
            box-shadow: 0 0 10px rgba(11, 74, 143, 0.3);
            position: relative;
            overflow: hidden;
        }
        .riot-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(11, 74, 143, 0.2) 50%,
                transparent 70%
            );
            transform: rotate(25deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        .riot-card:hover::after {
            opacity: 1;
            animation: shine 1.5s infinite;
        }
        .riot-card:hover {
            box-shadow: 0 0 25px rgba(11, 74, 143, 0.9);
            transform: translateY(-2px);
        }
        @keyframes shine {
            0% { transform: rotate(25deg) translateX(-100%); }
            100% { transform: rotate(25deg) translateX(100%); }
        }
        .group-badge {
            background: linear-gradient(135deg, #0b4a8f 0%, #0a1a2b 100%);
            text-shadow: 0 0 8px rgba(255,255,255,0.5);
        }
        input[type="number"], input[type="text"] {
            background-color: #2d2f36;
            border-color: #3f424a;
            color: #ffffff;
            border-radius: 0.5rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        input[type="number"]:focus, input[type="text"]:focus {
            ring-color: #0b4a8f;
            border-color: #0b4a8f;
            outline: none;
            box-shadow: 0 0 15px #0b4a8f;
        }
        input:disabled {
            background-color: #1e1f25;
            color: #9ca3af;
            cursor: default;
            border-color: #3f424a;
            box-shadow: none;
        }
        .round-header {
            border-bottom-color: #0b4a8f;
            text-shadow: 0 0 10px #0b4a8f;
        }
        .completed-badge {
            background-color: #065f46;
            color: #d1fae5;
            border: 1px solid #10b981;
            box-shadow: 0 0 8px #10b981;
        }
        .btn-gaming {
            background: linear-gradient(145deg, #0b4a8f, #062c4f);
            border: 1px solid #2f6bb0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-gaming::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255,255,255,0.3) 50%,
                transparent 70%
            );
            transform: rotate(25deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        .btn-gaming:hover::after {
            opacity: 1;
            animation: shine 1.5s infinite;
        }
        .btn-gaming:hover {
            background: linear-gradient(145deg, #0f5bb0, #083a66);
            box-shadow: 0 0 25px #0b4a8f;
            transform: scale(1.05);
        }
        table {
            color: #e8e9ea;
        }
        th {
            color: #9ca3af;
        }
        .bg-green-50 {
            background-color: rgba(5, 150, 105, 0.2) !important;
        }
        .bg-white {
            background-color: #1a1c22 !important;
            border-color: #2f3136;
        }
        .bg-gray-100 {
            background-color: #2d2f36 !important;
        }
        .text-gray-800, .text-gray-700 {
            color: #e8e9ea !important;
        }
        .text-yellow-800 {
            color: #fbbf24 !important;
        }
        .border-gray-200, .border-gray-300 {
            border-color: #3f424a !important;
        }
        .bg-gradient-to-r {
            background: linear-gradient(135deg, #0b4a8f 0%, #0a1a2b 100%) !important;
        }
        .border-yellow-400 {
            border-color: #fbbf24 !important;
        }
        .back-btn {
            background: linear-gradient(145deg, #2d2f36, #1a1c22);
            border: 1px solid #0b4a8f;
        }
        .back-btn:hover {
            background: linear-gradient(145deg, #3a3d47, #23262e);
            box-shadow: 0 0 20px #0b4a8f;
        }
    </style>
</head>
<body class="p-6 font-sans antialiased">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-blue-400 flex items-center gap-2" style="text-shadow: 0 0 12px #3b82f6;">
                <span>🏆</span> <?= htmlspecialchars($tournament['title']) ?> – Bracket
            </h1>
            <a href="scheduleManagement.php?tournament_id=<?= $tournament_id ?>"
               class="btn-gaming text-white px-5 py-2.5 rounded-lg transition shadow-md flex items-center gap-2 border border-blue-500">
                📅 Back to Schedule
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="bg-blue-900 border-l-4 border-blue-400 text-blue-100 px-4 py-3 rounded-lg shadow-lg mb-4 flex items-center">
                <span class="text-xl mr-2">ℹ️</span> <?= $flash ?>
            </div>
        <?php endif; ?>

        <!-- TOURNAMENT RESULTS CARD (only when completed) -->
        <?php if ($tournamentResults && $tournamentResults['winner_name']): ?>
            <div class="bg-gradient-to-r from-blue-900 to-blue-950 border border-blue-500 rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold text-center mb-4 text-yellow-400">🏆 Tournament Completed 🏆</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                    
                    <!-- 1st Runner-up -->
                    <div class="riot-card rounded-lg p-4 shadow border border-gray-700">
                        <div class="text-4xl mb-2">🥈</div>
                        <div class="text-sm uppercase tracking-wide text-gray-400">1st Runner-up</div>
                        <div class="text-xl font-bold text-gray-200">
                            <?= htmlspecialchars($tournamentResults['first_runner_up_name'] ?? '—') ?>
                        </div>
                    </div>
                    <!-- Winner -->
                    <div class="riot-card rounded-lg p-4 shadow border border-yellow-600">
                        <div class="text-4xl mb-2">🏆</div>
                        <div class="text-sm uppercase tracking-wide text-yellow-400">Winner</div>
                        <div class="text-xl font-bold text-yellow-300">
                            <?= htmlspecialchars($tournamentResults['winner_name'] ?? '—') ?>
                        </div>
                    </div>
                    
                    <!-- 2nd Runner-up -->
                    <div class="riot-card rounded-lg p-4 shadow border border-gray-700">
                        <div class="text-4xl mb-2">🥉</div>
                        <div class="text-sm uppercase tracking-wide text-gray-400">2nd Runner-up</div>
                        <div class="text-xl font-bold text-gray-200">
                            <?= htmlspecialchars($tournamentResults['second_runner_up_name'] ?? '—') ?>
                        </div>
                    </div>
                    
                </div>
            </div>
        <?php endif; ?>

        <!-- GROUP STANDINGS -->
        <?php if (!empty($groupStandings)): ?>
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-200 border-b-2 border-blue-800 pb-1 mb-4 flex items-center round-header">
                    <span class="bg-blue-700 text-white text-sm px-3 py-1 rounded-full mr-3 shadow-lg">📊</span>
                    Group Stage Standings
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach (['A','B','C','D'] as $group): ?>
                        <?php if (isset($groupStandings[$group]) && count($groupStandings[$group]) > 0): ?>
                            <div class="riot-card rounded-xl shadow-md overflow-hidden border border-gray-700">
                                <div class="group-badge px-4 py-3 text-white font-bold text-lg flex items-center">
                                    <span class="bg-white text-blue-800 rounded-full w-7 h-7 flex items-center justify-center mr-2 text-sm">⚔️</span>
                                    Group <?= $group ?>
                                </div>
                                <div class="p-4">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-700">
                                                <th class="text-left py-2 text-gray-400">Team</th>
                                                <th class="text-center text-gray-400">P</th>
                                                <th class="text-center text-gray-400">W</th>
                                                <th class="text-center text-gray-400">L</th>
                                                <th class="text-center text-gray-400">PTS</th>
                                                <th class="text-center text-gray-400">NET</th>
                                                <th class="text-center text-gray-400">DUR</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $rank = 1; ?>
                                            <?php foreach ($groupStandings[$group] as $team): ?>
                                                <tr class="<?= $rank <= 2 ? 'bg-green-900 bg-opacity-20' : '' ?> border-b border-gray-700 last:border-0">
                                                    <td class="py-2 text-gray-200"><?= htmlspecialchars($team['team_name']) ?></td>
                                                    <td class="text-center text-gray-300"><?= $team['played'] ?></td>
                                                    <td class="text-center text-gray-300"><?= $team['wins'] ?></td>
                                                    <td class="text-center text-gray-300"><?= $team['losses'] ?></td>
                                                    <td class="text-center font-bold text-blue-400"><?= $team['points'] ?></td>
                                                    <td class="text-center text-gray-300"><?= $team['net_game'] ?></td>
                                                    <td class="text-center text-gray-300"><?= gmdate('i:s', $team['duration']) ?></td>
                                                </tr>
                                                <?php $rank++; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- MATCHES FORM -->
        <?php if (empty($matchesList)): ?>
            <div class="bg-gray-800 border-l-4 border-yellow-600 text-yellow-200 px-4 py-3 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <span class="text-xl mr-2">⏳</span>
                    <div>
                        No matches have been generated yet. Go to <a href="scheduleManagement.php?tournament_id=<?= $tournament_id ?>" class="underline text-blue-400">Schedule page</a> to generate them.
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form method="post" class="space-y-6">
                <input type="hidden" name="bulk_save" value="1">
                
                <?php foreach ($roundOrder as $round): ?>
                    <?php if (!isset($groupedMatches[$round])) continue; ?>
                    
                    <!-- Round heading -->
                    <h2 class="text-2xl font-bold text-gray-200 border-b-2 border-blue-800 pb-1 mt-8 first:mt-0 flex items-center round-header">
                        <span class="bg-blue-700 text-white text-sm px-3 py-1 rounded-full mr-3 shadow-lg">
                            <?= $round === 'group' ? '👥' : '🏆' ?>
                        </span>
                        <?= $roundLabels[$round] ?? ucfirst($round) ?>
                    </h2>
                    
                    <?php if ($round === 'group'): ?>
                        <!-- Group Stage: each group in its own card -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
                            <?php 
                            $groupNames = ['A', 'B', 'C', 'D'];
                            foreach ($groupNames as $group): 
                                if (!isset($groupedMatches['group'][$group])) continue;
                                $groupMatches = $groupedMatches['group'][$group];
                            ?>
                                <div class="riot-card rounded-xl shadow-md overflow-hidden border border-gray-700">
                                    <div class="group-badge px-4 py-3 text-white font-bold text-lg flex items-center">
                                        <span class="bg-white text-blue-800 rounded-full w-7 h-7 flex items-center justify-center mr-2 text-sm">⚔️</span>
                                        Group <?= $group ?>
                                        <span class="ml-auto text-sm bg-blue-900 px-3 py-1 rounded-full border border-blue-400"><?= count($groupMatches) ?> matches</span>
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <?php foreach ($groupMatches as $m): 
                                            $isEditable = ($m['status'] === 'pending' && !empty($m['scheduled_time']) && $m['team1_id'] && $m['team2_id']);
                                            $maxScore = 2; // group stage best of 3 -> max 2
                                        ?>
                                            <div class="flex flex-wrap items-center gap-3 border-b border-gray-700 pb-2 last:border-0">
                                                <div class="w-8 text-sm font-medium text-gray-400">#<?= $m['match_order'] ?></div>
                                                <div class="flex-1 font-medium text-gray-200">
                                                    <?= htmlspecialchars($m['team1'] ?? 'TBD') ?> 
                                                    <span class="text-gray-500 mx-1">vs</span> 
                                                    <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <?php if ($isEditable): ?>
                                                        <input type="number" name="score1[<?= $m['match_id'] ?>]" min="0" max="<?= $maxScore ?>"
                                                               class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                               value="<?= $m['score1'] ?>">
                                                    <?php else: ?>
                                                        <input type="number" disabled
                                                               class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                               value="<?= $m['score1'] ?>">
                                                    <?php endif; ?>
                                                    <span class="font-bold text-gray-400">:</span>
                                                    <?php if ($isEditable): ?>
                                                        <input type="number" name="score2[<?= $m['match_id'] ?>]" min="0" max="<?= $maxScore ?>"
                                                               class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                               value="<?= $m['score2'] ?>">
                                                    <?php else: ?>
                                                        <input type="number" disabled
                                                               class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                               value="<?= $m['score2'] ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <!-- Duration for group stage -->
                                                <div class="w-32">
                                                    <?php if ($isEditable): ?>
                                                        <input type="text" name="duration[<?= $m['match_id'] ?>]"
                                                               placeholder="mm:ss"
                                                               class="w-full border rounded-lg px-3 py-1.5 text-sm"
                                                               value="<?= $m['duration_seconds'] ? gmdate('i:s', $m['duration_seconds']) : '' ?>">
                                                    <?php else: ?>
                                                        <input type="text" disabled
                                                               placeholder="mm:ss"
                                                               class="w-full border rounded-lg px-3 py-1.5 text-sm"
                                                               value="<?= $m['duration_seconds'] ? gmdate('i:s', $m['duration_seconds']) : '' ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <!-- Status -->
                                                <div class="text-right">
                                                    <?php if ($m['status'] === 'completed'): ?>
                                                        <span class="completed-badge text-xs font-semibold px-2.5 py-1 rounded-full">✓ Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?></span>
                                                    <?php elseif (!$m['team1_id'] || !$m['team2_id']): ?>
                                                        <span class="text-gray-500 text-sm">Waiting for assignment</span>
                                                    <?php elseif (empty($m['scheduled_time'])): ?>
                                                        <span class="text-red-400 text-sm">⚠️ Schedule not set</span>
                                                    <?php else: ?>
                                                        <span class="text-yellow-400 text-sm">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Knockout Rounds: each round in a single card -->
                        <div class="riot-card rounded-xl shadow-md overflow-hidden border border-gray-700 mt-4">
                            <div class="bg-gray-800 px-4 py-3 border-b border-gray-700 flex items-center">
                                <span class="text-blue-400 font-semibold">🏅 <?= $roundLabels[$round] ?? ucfirst($round) ?></span>
                                <span class="ml-auto text-sm text-gray-400"><?= count($groupedMatches[$round]) ?> matches</span>
                            </div>
                            <div class="p-4 space-y-3">
                                <?php foreach ($groupedMatches[$round] as $m): 
                                    $isEditable = ($m['status'] === 'pending' && !empty($m['scheduled_time']) && $m['team1_id'] && $m['team2_id']);
                                    $isGroup = false; // not group stage
                                    $maxScore = ($m['round'] === 'final') ? 3 : 2;
                                ?>
                                    <div class="flex flex-wrap items-center gap-3 border-b border-gray-700 pb-2 last:border-0">
                                        <div class="w-8 text-sm font-medium text-gray-400">#<?= $m['match_order'] ?></div>
                                        <div class="flex-1 font-medium text-gray-200">
                                            <?= htmlspecialchars($m['team1'] ?? 'TBD') ?> 
                                            <span class="text-gray-500 mx-1">vs</span> 
                                            <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if ($isEditable): ?>
                                                <input type="number" name="score1[<?= $m['match_id'] ?>]" min="0" max="<?= $maxScore ?>"
                                                       class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                       value="<?= $m['score1'] ?>">
                                            <?php else: ?>
                                                <input type="number" disabled
                                                       class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                       value="<?= $m['score1'] ?>">
                                            <?php endif; ?>
                                            <span class="font-bold text-gray-400">:</span>
                                            <?php if ($isEditable): ?>
                                                <input type="number" name="score2[<?= $m['match_id'] ?>]" min="0" max="<?= $maxScore ?>"
                                                       class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                       value="<?= $m['score2'] ?>">
                                            <?php else: ?>
                                                <input type="number" disabled
                                                       class="w-16 border rounded-lg px-2 py-1.5 text-center"
                                                       value="<?= $m['score2'] ?>">
                                            <?php endif; ?>
                                        </div>
                                        <!-- No duration input for knockout rounds -->
                                        <div class="w-40 text-right">
                                            <?php if ($m['status'] === 'completed'): ?>
                                                <span class="completed-badge text-xs font-semibold px-2.5 py-1 rounded-full">✓ Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?></span>
                                            <?php elseif (!$m['team1_id'] || !$m['team2_id']): ?>
                                                <span class="text-gray-500 text-sm">Waiting for assignment</span>
                                            <?php elseif (empty($m['scheduled_time'])): ?>
                                                <span class="text-red-400 text-sm">⚠️ Schedule not set</span>
                                            <?php else: ?>
                                                <span class="text-yellow-400 text-sm">Pending</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="mt-8 flex justify-between items-center">
                    <!-- Back button at bottom left -->
                    <a href="tournaments.php" 
                       class="back-btn text-white px-6 py-3 rounded-lg shadow-lg transition transform hover:scale-105 flex items-center gap-2 border border-blue-500">
                        <span>←</span> Back to Dashboard
                    </a>
                    <!-- Save button at bottom right -->
                    <button type="submit"
                            class="btn-gaming text-white font-bold py-3 px-8 rounded-lg shadow-lg transition transform hover:scale-105 flex items-center gap-2 border border-blue-500">
                        <span>💾</span> Save Results
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>