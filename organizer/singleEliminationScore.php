<?php
session_start();
require_once "../database/dbConfig.php";

/* ---------- AUTH ---------- */
if (!isset($_SESSION['user_id'])) {
    die("Login required");
}

$organizer_id = $_SESSION['user_id'];
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if (!$tournament_id) {
    die("Invalid tournament");
}

/* ---------- VERIFY TOURNAMENT ---------- */
$stmt = $conn->prepare("
    SELECT tournament_id, type, status, title
    FROM tournaments
    WHERE tournament_id = ? AND organizer_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $tournament_id, $organizer_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

if (!$tournament || $tournament['type'] !== 'singleelimination') {
    die("Unauthorized access");
}

$tournamentStatus = $tournament['status'];

/* ---------- HELPER: ASSIGN WINNERS TO NEXT ROUND ---------- */
function assignWinnersToNextRound($conn, $tournament_id, $completedRound) {
    $roundOrder = [
        'R256','R128','R64','R32','R16',
        'quarterfinal','semifinal','final'
    ];

    $currentIndex = array_search($completedRound, $roundOrder);
    if ($currentIndex === false || $roundOrder[$currentIndex] === 'final') {
        return;
    }
    $nextRound = $roundOrder[$currentIndex + 1];

    // Ensure all matches of the completed round are indeed completed
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS remaining
        FROM matches
        WHERE tournament_id = ? AND round = ? AND status != 'completed'
    ");
    $stmt->bind_param("is", $tournament_id, $completedRound);
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_assoc()['remaining'] > 0) {
        return;
    }

    // Fetch winners in order
    $stmt = $conn->prepare("
        SELECT winner_team_id
        FROM matches
        WHERE tournament_id = ? AND round = ? AND status = 'completed'
        ORDER BY match_id ASC
    ");
    $stmt->bind_param("is", $tournament_id, $completedRound);
    $stmt->execute();
    $winners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (count($winners) < 2) return;

    // Fetch placeholder matches of the next round (team1_id IS NULL)
    $stmt = $conn->prepare("
        SELECT match_id
        FROM matches
        WHERE tournament_id = ? AND round = ? AND team1_id IS NULL
        ORDER BY match_id ASC
    ");
    $stmt->bind_param("is", $tournament_id, $nextRound);
    $stmt->execute();
    $placeholders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Pair winners and update placeholders
    for ($i = 0; $i < count($placeholders); $i++) {
        if (!isset($winners[$i*2], $winners[$i*2+1])) break;
        $teamA = $winners[$i*2]['winner_team_id'];
        $teamB = $winners[$i*2+1]['winner_team_id'];
        $mid = $placeholders[$i]['match_id'];

        $update = $conn->prepare("
            UPDATE matches
            SET team1_id = ?, team2_id = ?
            WHERE match_id = ? AND tournament_id = ?
        ");
        $update->bind_param("iiii", $teamA, $teamB, $mid, $tournament_id);
        $update->execute();
    }
}

/* ---------- HANDLE POST (SAVE SCORES) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['score1'])) {
    $conn->begin_transaction();
    try {
        $savedMatches = 0;
        $roundsCompleted = [];

        $deleteScores = $conn->prepare("DELETE FROM match_scores WHERE match_id = ?");
        $insertScore = $conn->prepare("
            INSERT INTO match_scores (match_id, team1_score, team2_score, set_number)
            VALUES (?, ?, ?, 1)
        ");
        $updateMatch = $conn->prepare("
            UPDATE matches
            SET winner_team_id = ?, status = 'completed'
            WHERE match_id = ? AND tournament_id = ? AND status = 'pending'
        ");

        foreach ($_POST['score1'] as $match_id => $score1) {
            $match_id = (int)$match_id;
            $score1 = (int)$score1;
            $score2 = (int)($_POST['score2'][$match_id] ?? 0);

            $q = $conn->prepare("
                SELECT round, team1_id, team2_id, status
                FROM matches
                WHERE match_id = ? AND tournament_id = ? AND status = 'pending'
            ");
            $q->bind_param("ii", $match_id, $tournament_id);
            $q->execute();
            $match = $q->get_result()->fetch_assoc();
            if (!$match) continue;

            if ($score1 === $score2) {
                throw new Exception("Match #$match_id: Draw not allowed.");
            }

            $maxWins = ($match['round'] === 'final') ? 3 : 2;
            $winningScore = max($score1, $score2);
            $losingScore = min($score1, $score2);

            if ($winningScore !== $maxWins || $losingScore >= $maxWins) {
                throw new Exception("Match #$match_id: Invalid score. Must be BO" . ($maxWins*2-1));
            }

            $winner = ($score1 > $score2) ? $match['team1_id'] : $match['team2_id'];

            $deleteScores->bind_param("i", $match_id);
            $deleteScores->execute();

            $insertScore->bind_param("iii", $match_id, $score1, $score2);
            $insertScore->execute();

            $updateMatch->bind_param("iii", $winner, $match_id, $tournament_id);
            $updateMatch->execute();

            if ($updateMatch->affected_rows > 0) {
                $savedMatches++;
                $roundsCompleted[$match['round']] = true;
            }
        }

        foreach (array_keys($roundsCompleted) as $round) {
            assignWinnersToNextRound($conn, $tournament_id, $round);
        }

        $finalCheck = $conn->prepare("
            SELECT COUNT(*) FROM matches
            WHERE tournament_id = ? AND round = 'final' AND status != 'completed'
        ");
        $finalCheck->bind_param("i", $tournament_id);
        $finalCheck->execute();
        if ($finalCheck->get_result()->fetch_row()[0] == 0) {
            $conn->query("UPDATE tournaments SET status = 'completed' WHERE tournament_id = $tournament_id");

            $winnerQuery = $conn->prepare("
                SELECT winner_team_id FROM matches
                WHERE tournament_id = ? AND round = 'final' AND status = 'completed'
                LIMIT 1
            ");
            $winnerQuery->bind_param("i", $tournament_id);
            $winnerQuery->execute();
            $winner = $winnerQuery->get_result()->fetch_assoc();
            if ($winner) {
                $insertResult = $conn->prepare("
                    INSERT INTO tournament_results (tournament_id, winner_team_id, created_at)
                    VALUES (?, ?, NOW())
                ");
                $insertResult->bind_param("ii", $tournament_id, $winner['winner_team_id']);
                $insertResult->execute();
            }
        }

        $conn->commit();
        $_SESSION['flash'] = "✅ $savedMatches result(s) saved.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash'] = "❌ Error: " . $e->getMessage();
    }
    header("Location: singleEliminationScore.php?tournament_id=$tournament_id");
    exit;
}

/* ---------- FETCH ALL MATCHES (PENDING + COMPLETED) WITH AGGREGATED SCORES ---------- */
$matches = $conn->prepare("
    SELECT m.*,
           t1.team_name AS team1_name,
           t2.team_name AS team2_name,
           w.team_name AS winner_name,
           COALESCE(ms.total1, 0) AS team1_score,
           COALESCE(ms.total2, 0) AS team2_score
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN teams w ON m.winner_team_id = w.team_id
    LEFT JOIN (
        SELECT match_id, SUM(team1_score) AS total1, SUM(team2_score) AS total2
        FROM match_scores
        GROUP BY match_id
    ) ms ON m.match_id = ms.match_id
    WHERE m.tournament_id = ?
      AND m.scheduled_time IS NOT NULL
      AND m.team1_id IS NOT NULL
      AND m.team2_id IS NOT NULL
    ORDER BY FIELD(m.round, 'R256','R128','R64','R32','R16','quarterfinal','semifinal','final'),
             m.match_id ASC
");
$matches->bind_param("i", $tournament_id);
$matches->execute();
$matchesResult = $matches->get_result();

/* ---------- FETCH CHAMPION IF COMPLETED ---------- */
$champion = null;
if ($tournamentStatus === 'completed') {
    $result = $conn->prepare("
        SELECT t.team_name
        FROM matches m
        JOIN teams t ON m.winner_team_id = t.team_id
        WHERE m.tournament_id = ? AND m.round = 'final' AND m.status = 'completed'
        LIMIT 1
    ");
    $result->bind_param("i", $tournament_id);
    $result->execute();
    $champion = $result->get_result()->fetch_assoc();
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Determine if any pending matches exist (to enable/disable save button)
$hasPendingMatches = $matchesResult->num_rows > 0; // all fetched matches are pending because we filtered status in query? Actually we removed status filter to show completed too, so we need to check.
$matchesResult->data_seek(0);
$hasPendingMatches = false;
while ($m = $matchesResult->fetch_assoc()) {
    if ($m['status'] === 'pending') {
        $hasPendingMatches = true;
        break;
    }
}
$matchesResult->data_seek(0);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Single Elimination Score Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">🏆 Score Entry – <?= htmlspecialchars($tournament['title'] ?? '') ?></h1>
            <a href="SingleEliminationSchedule.php?tournament_id=<?= $tournament_id ?>"
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                📅 Back to Schedule
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $flash ?>
            </div>
        <?php endif; ?>

        <?php if ($tournamentStatus === 'completed' && $champion): ?>
            <div class="bg-gradient-to-r from-yellow-100 to-yellow-200 border border-yellow-400 rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold text-center text-yellow-800">🏆 Champion: <?= htmlspecialchars($champion['team_name'] ?? '') ?> 🏆</h2>
            </div>
        <?php endif; ?>

        <?php if ($matchesResult->num_rows === 0): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                No matches ready for score entry. Make sure they are scheduled and both teams are known.
            </div>
        <?php else: ?>
            <form method="post">
                <?php
                $currentRound = '';
                while ($m = $matchesResult->fetch_assoc()):
                    if ($currentRound !== $m['round']):
                        $currentRound = $m['round'];
                        echo "<h2 class='text-xl font-semibold mt-6 mb-2'>" . strtoupper($currentRound) . "</h2>";
                    endif;

                    $maxWins = ($m['round'] === 'final') ? 3 : 2;
                    $isCompleted = ($m['status'] === 'completed');
                    $disabled = ($tournamentStatus === 'completed' || $isCompleted) ? 'disabled' : '';
                ?>
                    <div class="bg-white p-4 rounded shadow mb-2 flex flex-wrap items-center gap-4">
                        <div class="w-64 font-medium">
                            <?= htmlspecialchars($m['team1_name'] ?? 'TBD') ?> vs <?= htmlspecialchars($m['team2_name'] ?? 'TBD') ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" name="score1[<?= $m['match_id'] ?>]" min="0" max="<?= $maxWins ?>"
                                   class="w-16 border rounded px-2 py-1 text-center"
                                   value="<?= $m['team1_score'] ?>"
                                   <?= $disabled ?>
                                   <?= (!$isCompleted && $tournamentStatus !== 'completed') ? 'required' : '' ?>>
                            <span class="font-bold">:</span>
                            <input type="number" name="score2[<?= $m['match_id'] ?>]" min="0" max="<?= $maxWins ?>"
                                   class="w-16 border rounded px-2 py-1 text-center"
                                   value="<?= $m['team2_score'] ?>"
                                   <?= $disabled ?>
                                   <?= (!$isCompleted && $tournamentStatus !== 'completed') ? 'required' : '' ?>>
                        </div>
                        <?php if ($isCompleted): ?>
                            <span class="text-green-600 font-semibold text-sm bg-green-50 px-3 py-1 rounded">
                                ✓ Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>

                <!-- Save button – always visible, disabled when tournament completed or no pending matches -->
                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded shadow disabled:opacity-50 disabled:cursor-not-allowed"
                            <?= ($tournamentStatus === 'completed' || !$hasPendingMatches) ? 'disabled' : '' ?>>
                        💾 Save Results
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>