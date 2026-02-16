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
    <!-- Font Awesome for icons (monochrome) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ================= SQUARE DESIGN, BRIGHTER BLUE ACCENTS ================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #0b0d12;
            color: #e1e5ec;
            font-family: 'Inter', sans-serif;
            padding: 2rem 1rem;
            min-height: 100vh;
        }

        /* Main container – dark, square */
        .max-w-6xl {
            max-width: 1280px;
            margin: 0 auto;
            background-color: #10131c;
            border: 1px solid #2f3a4a;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
            padding: 2rem;
        }

        /* Header */
        .flex.justify-between.items-center {
            margin-bottom: 2rem;
            border-bottom: 1px solid #2f3a4a;
            padding-bottom: 1rem;
        }

        h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #b8d0f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .btn-primary {
            background-color: #1e3142;
            border: 1px solid #2d7ff9;
            color: white;
            font-weight: 500;
            padding: 0.7rem 1.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.1s;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border-radius: 0;
        }
        .btn-primary:hover {
            background-color: #264763;
            border-color: #5a9eff;
        }

        /* Flash message */
        .flash-message {
            background-color: #1b2535;
            border: 1px solid #2d7ff9;
            color: #c0dcff;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 0;
        }

        /* Champion card */
        .champion-card {
            background-color: #1b2535;
            border: 1px solid #2d7ff9;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
            border-radius: 0;
        }
        .champion-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #b8d0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        /* Round header */
        .round-header {
            font-size: 1.6rem;
            font-weight: 600;
            color: #b8d0f0;
            border-left: 5px solid #2d7ff9;
            padding-left: 1rem;
            margin: 2rem 0 1rem 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Match row */
        .match-row {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            border-radius: 0;
        }
        .match-row .match-teams {
            width: 250px;
            font-weight: 500;
            color: #d6e2f0;
        }
        .match-row input[type="number"] {
            background-color: #0f141e;
            border: 1px solid #2f3a4a;
            color: #e0e6f0;
            padding: 0.5rem;
            font-size: 0.95rem;
            border-radius: 0;
            width: 60px;
            text-align: center;
        }
        .match-row input[type="number"]:focus {
            outline: none;
            border-color: #2d7ff9;
            box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
        }
        .match-row input:disabled {
            background-color: #1c222d;
            color: #7c8a9c;
            border-color: #353e4e;
        }
        .winner-badge {
            background-color: #1d2c3a;
            border: 1px solid #2d7ff9;
            color: #b0d0ee;
            padding: 0.25rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 0;
        }

        /* Score separator */
        .score-separator {
            font-weight: 700;
            color: #9aaec9;
            margin: 0 0.25rem;
        }

        /* Save button */
        .btn-save {
            background-color: #1e3142;
            border: 1px solid #2d7ff9;
            color: white;
            font-weight: 600;
            padding: 0.9rem 2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: 0.1s;
            border-radius: 0;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-save:hover:not(:disabled) {
            background-color: #264763;
            border-color: #5a9eff;
        }
        .btn-save:disabled {
            background-color: #1a1f2a;
            border-color: #353e4e;
            color: #6b7a8f;
            cursor: not-allowed;
        }

        /* Icons */
        i {
            color: inherit;
        }

        /* Utility classes */
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .items-center { align-items: center; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-4 { gap: 1rem; }
        .gap-2 { gap: 0.5rem; }
        .mt-6 { margin-top: 1.5rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .text-sm { font-size: 0.875rem; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .ml-4 { margin-left: 1rem; }
    </style>
</head>
<body>
    <div class="max-w-6xl">
        <!-- Header with back button on left, title on left, no button on right -->
        <div class="flex items-center gap-4 mb-6" style="border-bottom: 1px solid #2f3a4a; padding-bottom: 1rem;">
            <a href="SingleEliminationSchedule.php?tournament_id=<?= $tournament_id ?>" class="btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Schedule
            </a>
            <h1 class="ml-4"><i class="fas fa-trophy"></i> Score Entry – <?= htmlspecialchars($tournament['title'] ?? '') ?></h1>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message">
                <i class="fas fa-info-circle"></i>
                <?= $flash ?>
            </div>
        <?php endif; ?>

        <?php if ($tournamentStatus === 'completed' && $champion): ?>
            <div class="champion-card">
                <h2><i class="fas fa-crown" style="color: #ffd966;"></i> Champion: <?= htmlspecialchars($champion['team_name'] ?? '') ?> <i class="fas fa-crown" style="color: #ffd966;"></i></h2>
            </div>
        <?php endif; ?>

        <?php if ($matchesResult->num_rows === 0): ?>
            <div class="flash-message" style="border-color: #f59e0b;">
                <i class="fas fa-exclamation-triangle"></i>
                No matches ready for score entry. Make sure they are scheduled and both teams are known.
            </div>
        <?php else: ?>
            <form method="post">
                <?php
                $currentRound = '';
                while ($m = $matchesResult->fetch_assoc()):
                    if ($currentRound !== $m['round']):
                        $currentRound = $m['round'];
                        echo "<h2 class='round-header'><i class='fas fa-bracket-curly'></i> " . strtoupper($currentRound) . "</h2>";
                    endif;

                    $maxWins = ($m['round'] === 'final') ? 3 : 2;
                    $isCompleted = ($m['status'] === 'completed');
                    $disabled = ($tournamentStatus === 'completed' || $isCompleted) ? 'disabled' : '';
                ?>
                    <div class="match-row">
                        <div class="match-teams">
                            <?= htmlspecialchars($m['team1_name'] ?? 'TBD') ?> vs <?= htmlspecialchars($m['team2_name'] ?? 'TBD') ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" name="score1[<?= $m['match_id'] ?>]" min="0" max="<?= $maxWins ?>"
                                   value="<?= $m['team1_score'] ?>"
                                   <?= $disabled ?>
                                   <?= (!$isCompleted && $tournamentStatus !== 'completed') ? 'required' : '' ?>>
                            <span class="score-separator">:</span>
                            <input type="number" name="score2[<?= $m['match_id'] ?>]" min="0" max="<?= $maxWins ?>"
                                   value="<?= $m['team2_score'] ?>"
                                   <?= $disabled ?>
                                   <?= (!$isCompleted && $tournamentStatus !== 'completed') ? 'required' : '' ?>>
                        </div>
                        <?php if ($isCompleted): ?>
                            <span class="winner-badge">
                                <i class="fas fa-check-circle"></i> Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>

                <!-- Bottom action buttons: Back on left, Save on right -->
                <div class="mt-6 flex justify-between items-center">
                    <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="btn-primary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button type="submit"
                            class="btn-save"
                            <?= ($tournamentStatus === 'completed' || !$hasPendingMatches) ? 'disabled' : '' ?>>
                        <i class="fas fa-save"></i> Save Results
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>