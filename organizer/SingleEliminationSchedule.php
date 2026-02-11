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
    SELECT tournament_id, max_participants, type
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

$maxParticipants = (int)$tournament['max_participants'];

/* =============================
   CHECK JOINED TEAMS
============================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tournament_teams
    WHERE tournament_id = ?
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$joined = (int)$stmt->get_result()->fetch_assoc()['total'];

if ($joined < $maxParticipants) {
    $needed = $maxParticipants - $joined;
    die("Tournament needs $needed more teams to start scheduling.");
}

/* =============================
   ROUND ORDER (LOCKED)
============================= */
$roundOrder = [
    'R256','R128','R64','R32','R16',
    'quarterfinal','semifinal','final'
];

/* =============================
   CREATE FIRST ROUND IF NONE
============================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM matches
    WHERE tournament_id = ?
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$existingMatches = (int)$stmt->get_result()->fetch_assoc()['total'];

if ($existingMatches === 0) {

    $firstRoundMap = [
        256 => 'R256',
        128 => 'R128',
        64  => 'R64',
        32  => 'R32',
        16  => 'R16',
        8   => 'quarterfinal'
    ];

    if (!isset($firstRoundMap[$maxParticipants])) {
        die("Unsupported participant count");
    }

    $firstRound = $firstRoundMap[$maxParticipants];

    $stmt = $conn->prepare("
        SELECT team_id
        FROM tournament_teams
        WHERE tournament_id = ?
    ");
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (count($teams) !== $maxParticipants || count($teams) % 2 !== 0) {
        die("Invalid team count for match generation");
    }

    shuffle($teams);

    mysqli_begin_transaction($conn);
    try {
        for ($i = 0; $i < count($teams); $i += 2) {
            $stmt = $conn->prepare("
                INSERT INTO matches
                (tournament_id, round, team1_id, team2_id, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param(
                "isii",
                $tournament_id,
                $firstRound,
                $teams[$i]['team_id'],
                $teams[$i + 1]['team_id']
            );
            $stmt->execute();
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("Match generation failed");
    }
}

/* =============================
   FIND CURRENT SCHEDULABLE ROUND
============================= */
$currentRound = null;

foreach ($roundOrder as $round) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM matches
        WHERE tournament_id = ?
          AND round = ?
          AND scheduled_time IS NULL
          AND status = 'pending'
    ");
    $stmt->bind_param("is", $tournament_id, $round);
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_assoc()['total'] > 0) {
        $currentRound = $round;
        break;
    }
}

if (!$currentRound) {
    die("All matches are scheduled or completed.");
}

/* =============================
   FETCH MATCHES
============================= */
$stmt = $conn->prepare("
    SELECT m.match_id, m.scheduled_time,
           t1.team_name AS team_a,
           t2.team_name AS team_b
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    WHERE m.tournament_id = ?
      AND m.round = ?
    ORDER BY m.match_id ASC
");
$stmt->bind_param("is", $tournament_id, $currentRound);
$stmt->execute();
$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =============================
   SAVE SCHEDULES
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $saved = 0;
    $remaining = 0;
    $nowTs = time();

    mysqli_begin_transaction($conn);
    try {
        foreach ($_POST['schedule'] as $match_id => $datetime) {

            if (!$datetime) {
                $remaining++;
                continue;
            }

            $ts = strtotime($datetime);
            if ($ts === false || $ts <= $nowTs) {
                $remaining++;
                continue;
            }

            $stmt = $conn->prepare("
                UPDATE matches
                SET scheduled_time = ?
                WHERE match_id = ?
                  AND tournament_id = ?
                  AND round = ?
                  AND status = 'pending'
            ");
            $stmt->bind_param(
                "siis",
                $datetime,
                $match_id,
                $tournament_id,
                $currentRound
            );
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
<title>Schedule Matches</title>

<style>
body{
    background:#0f172a;
    color:#fff;
    font-family:Arial,sans-serif;
    padding:30px;
}
.container{
    max-width:1000px;
    margin:auto;
}
.round-title{
    font-size:24px;
    margin-bottom:20px;
}
.toggle{
    margin-bottom:20px;
}
.toggle button{
    padding:10px 16px;
    border:none;
    cursor:pointer;
    margin-right:10px;
    background:#020617;
    color:#fff;
}
.toggle .active{
    background:#38bdf8;
    color:#000;
}
.match{
    background:#020617;
    padding:15px;
    border-radius:8px;
    margin-bottom:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.save-btn{
    margin-top:20px;
    padding:14px 22px;
    font-size:16px;
    border:none;
    cursor:pointer;
    background:#22c55e;
    color:#000;
}
.notice{
    margin-top:15px;
    font-size:14px;
}
</style>
</head>

<body>
<div class="container">

<h2 class="round-title">
Scheduling: <?= strtoupper($currentRound) ?>
</h2>

<div class="toggle">
    <button type="button" id="matchBtn" class="active">Match Based</button>
    <button type="button" id="roundBtn">Round Based</button>
</div>

<form id="scheduleForm">
<?php foreach ($matches as $m): ?>
<div class="match">
    <div><?= htmlspecialchars($m['team_a']) ?> vs <?= htmlspecialchars($m['team_b']) ?></div>
    <input type="datetime-local"
           name="schedule[<?= $m['match_id'] ?>]"
           min="<?= date('Y-m-d\TH:i') ?>">
</div>
<?php endforeach; ?>

<button class="save-btn" type="submit">Save Schedule</button>
<div class="notice" id="notice"></div>
</form>

</div>

<script>
const form = document.getElementById("scheduleForm");
const notice = document.getElementById("notice");

form.addEventListener("submit", e=>{
    e.preventDefault();
    fetch("",{
        method:"POST",
        body:new FormData(form)
    })
    .then(r=>r.json())
    .then(d=>{
        notice.innerText =
            `Saved: ${d.saved} | Remaining: ${d.remaining}`;
    });
});
</script>

</body>
</html>
