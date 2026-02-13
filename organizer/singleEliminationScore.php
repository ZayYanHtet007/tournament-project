<?php
session_start();
require_once "../database/dbConfig.php";

/* =====================================
   1. AUTH
===================================== */
if (!isset($_SESSION['user_id'])) {
    die("Login required");
}

$organizer_id = $_SESSION['user_id'];
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if (!$tournament_id) {
    die("Invalid tournament");
}

/* =====================================
   2. VERIFY TOURNAMENT
===================================== */
$stmt = $conn->prepare("
    SELECT tournament_id, type
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

/* =====================================
   HELPER: ASSIGN WINNERS TO NEXT ROUND PLACEHOLDERS
===================================== */
function assignWinnersToNextRound($conn, $tournament_id, $completedRound) {
    $roundOrder = [
        'R256','R128','R64','R32','R16',
        'quarterfinal','semifinal','final'
    ];

    $currentIndex = array_search($completedRound, $roundOrder);
    if ($currentIndex === false || $roundOrder[$currentIndex] === 'final') {
        return; // no next round
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
        return; // round not fully completed yet
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

/* =====================================
   3. SAVE RESULTS
===================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);
    try {
        $savedMatches = 0;
        $roundProcessed = null;

        foreach ($_POST['score'] as $match_id => $data) {
            $teamA_score = isset($data['a']) ? (int)$data['a'] : 0;
            $teamB_score = isset($data['b']) ? (int)$data['b'] : 0;

            // Valid BO3 only
            if (
                ($teamA_score === 2 && $teamB_score < 2) ||
                ($teamB_score === 2 && $teamA_score < 2)
            ) {
                $matchStmt = $conn->prepare("
                    SELECT team1_id, team2_id, round
                    FROM matches
                    WHERE match_id = ?
                      AND tournament_id = ?
                      AND status = 'pending'
                      AND team1_id IS NOT NULL
                      AND team2_id IS NOT NULL
                ");
                $matchStmt->bind_param("ii", $match_id, $tournament_id);
                $matchStmt->execute();
                $match = $matchStmt->get_result()->fetch_assoc();

                if (!$match) continue;

                $winner_id = ($teamA_score === 2)
                    ? $match['team1_id']
                    : $match['team2_id'];

                $roundProcessed = $match['round'];

                $update = $conn->prepare("
                    UPDATE matches
                    SET team1_score = ?,
                        team2_score = ?,
                        winner_team_id = ?,
                        status = 'completed'
                    WHERE match_id = ?
                      AND tournament_id = ?
                      AND status = 'pending'
                ");
                $update->bind_param(
                    "iiiii",
                    $teamA_score,
                    $teamB_score,
                    $winner_id,
                    $match_id,
                    $tournament_id
                );
                $update->execute();

                if ($update->affected_rows > 0) {
                    $savedMatches++;
                }
            }
        }

        // After saving, check if the round is now fully completed
        if ($roundProcessed) {
            assignWinnersToNextRound($conn, $tournament_id, $roundProcessed);
        }

        mysqli_commit($conn);
        echo json_encode([
            "status" => "success",
            "saved" => $savedMatches
        ]);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode([
            "status" => "error",
            "message" => "Save failed"
        ]);
        exit;
    }
}

/* =====================================
   4. FETCH SCORABLE MATCHES
   (only those with both teams assigned and scheduled)
===================================== */
$stmt = $conn->prepare("
    SELECT m.match_id,
           m.round,
           m.team1_id,
           m.team2_id,
           ta.team_name AS team_a,
           tb.team_name AS team_b
    FROM matches m
    JOIN teams ta ON ta.team_id = m.team1_id
    JOIN teams tb ON tb.team_id = m.team2_id
    WHERE m.tournament_id = ?
      AND m.scheduled_time IS NOT NULL
      AND m.status = 'pending'
      AND m.team1_id IS NOT NULL
      AND m.team2_id IS NOT NULL
    ORDER BY FIELD(m.round,
        'R256','R128','R64','R32','R16',
        'quarterfinal','semifinal','final'
    ), m.match_id ASC
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>


<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Manage Match Scores</title>

<style>
body{
    background:#0f172a;
    color:#fff;
    font-family:Arial;
    padding:30px;
}
.container{
    max-width:1000px;
    margin:auto;
}
.round-title{
    font-size:22px;
    margin:25px 0 10px;
}
.match{
    background:#020617;
    padding:15px;
    border-radius:8px;
    margin-bottom:12px;
}
.score-box{
    margin-top:10px;
}
input{
    width:60px;
    padding:6px;
    margin-right:10px;
}
button{
    margin-top:20px;
    padding:12px 20px;
    background:#22c55e;
    border:none;
    cursor:pointer;
}
.notice{
    margin-top:15px;
}
</style>
</head>

<body>
<div class="container">

<h2>Score Management (BO3)</h2>

<form id="scoreForm">

<?php 
$currentRound = "";
foreach($matches as $m): 
    if ($currentRound !== $m['round']):
        if ($currentRound !== "") echo "</div>";
        $currentRound = $m['round'];
        echo "<div class='round-title'>".strtoupper($currentRound)."</div>";
        echo "<div>";
    endif;
?>

<div class="match">
    <strong><?= htmlspecialchars($m['team_a']) ?> vs <?= htmlspecialchars($m['team_b']) ?></strong>
    <div class="score-box">
        <?= htmlspecialchars($m['team_a']) ?> Wins:
        <input type="number" min="0" max="2"
            name="score[<?= $m['match_id'] ?>][a]">
        <?= htmlspecialchars($m['team_b']) ?> Wins:
        <input type="number" min="0" max="2"
            name="score[<?= $m['match_id'] ?>][b]">
    </div>
</div>

<?php endforeach; ?>

<button type="submit">Save Results</button>
<div class="notice" id="notice"></div>

</form>

</div>

<script>
document.getElementById("scoreForm")
.addEventListener("submit", function(e){
    e.preventDefault();

    fetch("", {
        method: "POST",
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById("notice").innerText =
            "Saved: " + data.saved;
        location.reload(); // refresh to show next round
    })
    .catch(() => {
        document.getElementById("notice").innerText = "Error saving";
    });
});
</script>

</body>
</html>
