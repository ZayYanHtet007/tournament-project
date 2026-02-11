<?php
session_start();
require_once "../database/dbConfig.php";

/* =============================
   AUTH
============================= */
if (!isset($_SESSION['user_id'])) {
    die("Login required");
}

$organizer_id = $_SESSION['user_id'];
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if (!$tournament_id) {
    die("Invalid tournament");
}

/* =============================
   VERIFY TOURNAMENT
============================= */
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

/* =============================
   ROUND ORDER
============================= */
$roundOrder = [
    'R256','R128','R64','R32','R16',
    'quarterfinal','semifinal','final'
];

/* =============================
   FIND CURRENT ROUND
============================= */
$currentRound = null;

foreach ($roundOrder as $round) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM matches
        WHERE tournament_id = ?
          AND round = ?
          AND status = 'pending'
          AND scheduled_time IS NOT NULL
    ");
    $stmt->bind_param("is", $tournament_id, $round);
    $stmt->execute();

    if ((int)$stmt->get_result()->fetch_assoc()['total'] > 0) {
        $currentRound = $round;
        break;
    }
}

if (!$currentRound) {
    die("No matches available for scoring.");
}

/* =============================
   FETCH MATCHES
============================= */
$stmt = $conn->prepare("
    SELECT 
        m.match_id,
        t1.team_name AS team_a,
        t2.team_name AS team_b
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    WHERE m.tournament_id = ?
      AND m.round = ?
      AND m.status = 'pending'
      AND m.scheduled_time IS NOT NULL
    ORDER BY m.match_id ASC
");
$stmt->bind_param("is", $tournament_id, $currentRound);
$stmt->execute();
$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =============================
   SAVE RESULTS
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $saved = 0;
    $remaining = 0;

    mysqli_begin_transaction($conn);
    try {
        foreach ($_POST['scores'] as $match_id => $score) {

    if ($score['a'] === '' || $score['b'] === '') {
        $remaining++;
        continue;
    }

    $winsA = (int)$score['a'];
    $winsB = (int)$score['b'];

    // Validation
    if (
        $winsA < 0 || $winsA > 2 ||
        $winsB < 0 || $winsB > 2 ||
        ($winsA + $winsB < 2) ||
        ($winsA + $winsB > 3) ||
        ($winsA !== 2 && $winsB !== 2)
    ) {
        $remaining++;
        continue;
    }

    $winnerColumn = $winsA === 2 ? "team1_id" : "team2_id";

    $stmt = $conn->prepare("
        UPDATE matches
        SET winner_team_id = $winnerColumn,
            status = 'completed'
        WHERE match_id = ?
          AND tournament_id = ?
          AND round = ?
          AND status = 'pending'
    ");
    $stmt->bind_param("iis", $match_id, $tournament_id, $currentRound);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $saved++;
    }
}


        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("Save failed");
    }

    echo json_encode([
        "saved" => $saved,
        "remaining" => $remaining
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Match Results</title>

<style>
body{
    background:#020617;
    color:#fff;
    font-family:Arial,sans-serif;
    padding:30px;
}
.container{
    max-width:1000px;
    margin:auto;
}
.match{
    background:#0f172a;
    padding:16px;
    border-radius:8px;
    margin-bottom:16px;
}
.sets{
    display:flex;
    gap:10px;
    margin-top:10px;
}
.sets input{
    width:60px;
    padding:6px;
}
.save-btn{
    margin-top:20px;
    padding:14px 22px;
    font-size:16px;
    border:none;
    cursor:pointer;
    background:#22c55e;
}
.notice{
    margin-top:15px;
}
.bo3{
    display:flex;
    gap:12px;
    margin-top:10px;
}
.bo3 input{
    width:120px;
    padding:8px;
    font-size:14px;
}

</style>
</head>

<body>
<div class="container">

<h2>Results – <?= strtoupper($currentRound) ?></h2>

<form id="scoreForm">
<?php foreach ($matches as $m): ?>
<div class="match">
    <strong><?= htmlspecialchars($m['team_a']) ?> vs <?= htmlspecialchars($m['team_b']) ?></strong>

    <div class="bo3">
        <input type="number" min="0" max="2"
               name="scores[<?= $m['match_id'] ?>][a]"
               placeholder="<?= htmlspecialchars($m['team_a']) ?> wins">

        <input type="number" min="0" max="2"
               name="scores[<?= $m['match_id'] ?>][b]"
               placeholder="<?= htmlspecialchars($m['team_b']) ?> wins">
    </div>
</div>

<?php endforeach; ?>

<button class="save-btn" type="submit">Save Results</button>
<div class="notice" id="notice"></div>
</form>

</div>

<script>
document.getElementById("scoreForm").addEventListener("submit", e=>{
    e.preventDefault();
    fetch("",{
        method:"POST",
        body:new FormData(e.target)
    })
    .then(r=>r.json())
    .then(d=>{
        document.getElementById("notice").innerText =
            `Saved: ${d.saved} | Remaining: ${d.remaining}`;
    });
});
</script>

</body>
</html>
