<?php

require '../database/dbConfig.php';

$tournament_id = $_GET['tournament_id'] ?? $_POST['tournament_id'] ?? 0;
$errors = [];

/* ================= POINT CALC ================= */
function calcPoints($rank, $kills) {
    $rankPoints = [
        1 => 20, 2 => 16, 3 => 14, 4 => 12,
        5 => 10, 6 => 8, 7 => 6, 8 => 4
    ];
    return ($rankPoints[$rank] ?? 1) + $kills;
}

/* ================= SAVE MATCH ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_id'])) {

    $ranks = [];

    foreach ($_POST['rank'] as $pid => $rank) {
        if (!$rank || $rank < 1) {
            $errors[$pid]['rank'] = "Rank required";
        } elseif (in_array($rank, $ranks)) {
            $errors[$pid]['rank'] = "Duplicate rank";
        }
        $ranks[] = $rank;

        if ($_POST['kills'][$pid] === '' || $_POST['kills'][$pid] < 0) {
            $errors[$pid]['kills'] = "Invalid kills";
        }
    }

    if (empty($errors)) {

        foreach ($_POST['rank'] as $pid => $rank) {
            $kills = $_POST['kills'][$pid];
            $points = calcPoints($rank, $kills);
            $isWinner = ($rank == 1) ? 1 : 0;


            $pdo->prepare(" 
                           UPDATE battleroyal_participants 
                           SET rank_position=?, kill_count=?, score_points=?, is_winner=?
                           WHERE participation_id=?"
            )->execute([
                (int)$rank,
                (int)$kills,
                (int)$points,
                (int)$isWinner,
                (int)$pid]);

        }

        // Complete match
        $pdo->prepare("
            UPDATE matches SET status='completed'
            WHERE match_id=?
        ")->execute([$_POST['match_id']]);

        // Auto create next match
        $stageMap = ['FIRST'=>'SECOND','SECOND'=>'THIRD'];
        $stage = $pdo->query("
            SELECT round FROM matches WHERE match_id={$_POST['match_id']}
        ")->fetchColumn();

        if (isset($stageMap[$stage])) {
            $pdo->prepare("
                INSERT INTO matches (tournament_id, round, status)
                VALUES (?, ?, 'pending')
            ")->execute([$tournament_id, $stageMap[$stage]]);

            $newMatch = $pdo->lastInsertId();

            $teams = $pdo->prepare("
                SELECT id FROM tournament_teams WHERE tournament_id=?
            ");
            $teams->execute([$tournament_id]);

            foreach ($teams as $t) {
                $pdo->prepare("
                    INSERT INTO battleroyal_participants (match_id, tt_id)
                    VALUES (?, ?)
                ")->execute([$newMatch, $t['id']]);
            }
        }

        header("Location: brScoreManagement.php?tournament_id=$tournament_id");
        exit;
    }
}

/* ================= TOURNAMENT ================= */
$tournament = $pdo->prepare("SELECT * FROM tournaments WHERE tournament_id=?");
$tournament->execute([$tournament_id]);
$tournament = $tournament->fetch();
if (!$tournament) die("Tournament not found");

/* ================= TEAM COUNT ================= */
$totalTeams = $pdo->query("
    SELECT COUNT(*) FROM tournament_teams WHERE tournament_id=$tournament_id
")->fetchColumn();

$canStart = in_array($totalTeams, [16,25]);

/* ================= AUTO CREATE MATCH 1 ================= */
if ($canStart) {
    $count = $pdo->query("
        SELECT COUNT(*) FROM matches WHERE tournament_id=$tournament_id
    ")->fetchColumn();

    if ($count == 0) {
        $pdo->prepare("
            INSERT INTO matches (tournament_id, round, status)
            VALUES (?,'FIRST', 'pending')
        ")->execute([$tournament_id]);

        $mid = $pdo->lastInsertId();

        $teams = $pdo->query("
            SELECT id FROM tournament_teams WHERE tournament_id=$tournament_id
        ");

        foreach ($teams as $t) {
            $pdo->prepare("
                INSERT INTO battleroyal_participants (match_id, tt_id)
                VALUES (?, ?)
            ")->execute([$mid, $t['id']]);
        }
    }
}

/* ================= STANDINGS ================= */
$standings = $pdo->query("
    SELECT t.team_name,
           SUM(mp.score_points) points,
           SUM(mp.kill_count) kills
    FROM tournament_teams tt
    JOIN teams t ON tt.team_id=t.team_id
    LEFT JOIN battleroyal_participants mp ON mp.tt_id=tt.id
    WHERE tt.tournament_id=$tournament_id
    GROUP BY tt.id
    ORDER BY points DESC, kills DESC
")->fetchAll();

/* ================= CURRENT MATCH ================= */
$currentMatch = $pdo->query("
    SELECT * FROM matches
    WHERE tournament_id=$tournament_id
      AND status!='completed'
    ORDER BY FIELD(round,'FIRST','SECOND','THIRD')
    LIMIT 1
")->fetch();

/* ================= PARTICIPANTS ================= */
$participants = [];
if ($currentMatch) {
    $participants = $pdo->query("
        SELECT mp.participation_id, t.team_name
        FROM battleroyal_participants mp
        JOIN tournament_teams tt ON mp.tt_id=tt.id
        JOIN teams t ON tt.team_id=t.team_id
        WHERE mp.match_id={$currentMatch['match_id']}
    ")->fetchAll();
}

/* ================= FINISH ================= */
$isFinished = $pdo->query("
    SELECT COUNT(*) FROM matches
    WHERE tournament_id=$tournament_id AND status='completed'
")->fetchColumn() == 3;

$top3 = [];
if ($isFinished) {
    $top3 = $pdo->query("
        SELECT t.team_name
        FROM tournament_teams tt
        JOIN teams t ON tt.team_id=t.team_id
        JOIN battleroyal_participants mp ON mp.tt_id=tt.id
        WHERE tt.tournament_id=$tournament_id
        GROUP BY tt.id
        ORDER BY SUM(mp.score_points) DESC, SUM(mp.kill_count) DESC
        LIMIT 3
    ")->fetchAll();

    $pdo->prepare("
        UPDATE tournaments SET status='completed'
        WHERE tournament_id=?
    ")->execute([$tournament_id]);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Battle Royale Score</title>
    <link rel="stylesheet" href="../css/organizer/brscore.css">
</head>

<body class="br-body">
<div class="br-container">

<div class="br-title">
    <h1>🔥 Battle Royale</h1>
    <p><?= htmlspecialchars($tournament['title']) ?></p>
</div>

<?php if (!$canStart): ?>
<div class="br-table-wrapper">
    <h3 style="color:#ff4d4d;text-align:center">
        Tournament needs
        <?= $totalTeams < 16 ? (16 - $totalTeams) : (25 - $totalTeams) ?>
        more teams to start
    </h3>
</div>
</div></body></html>
<?php exit; endif; ?>

<?php if ($isFinished): ?>
<div class="br-table-wrapper" style="display:flex;gap:20px;justify-content:center;margin-bottom:30px">
    <div style="flex:1;text-align:center;color:#c0c0c0">🥈 <?= $top3[1]['team_name'] ?? '' ?></div>
    <div style="flex:1;text-align:center;color:#ffd700;font-size:1.3rem">🥇 <?= $top3[0]  ['team_name'] ?? '' ?></div>
    <div style="flex:1;text-align:center;color:#cd7f32">🥉 <?= $top3[2]['team_name'] ?? '' ?></div>
</div>
<?php endif; ?>

<div class="br-table-wrapper">
<table class="br-table">
<thead>
<tr>
    <th>#</th>
    <th>Team</th>
    <th>Points</th>
    <th>Kills</th>
</tr>
</thead>
<tbody>
<?php $r=1; foreach ($standings as $s): ?>
<tr>
    <td><?= $r++ ?></td>
    <td><?= htmlspecialchars($s['team_name']) ?></td>
    <td><?= $s['points'] ?? 0 ?></td>
    <td><?= $s['kills'] ?? 0 ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php if ($currentMatch && !$isFinished): ?>
<div class="br-title" style="margin-top:40px">
    <h1>Match <?= $currentMatch['round'] ?></h1>
</div>

<form method="POST" onsubmit="return validateMatch();">
<input type="hidden" name="match_id" value="<?= $currentMatch['match_id'] ?>">
<input type="hidden" name="tournament_id" value="<?= $tournament_id ?>">

<div class="br-table-wrapper">
<table class="br-table">
<thead>
<tr><th>Team</th><th>Rank</th><th>Kills</th></tr>
</thead>
<tbody>
<?php foreach ($participants as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['team_name']) ?></td>
    <td><input type="number" name="rank[<?= $p['participation_id'] ?>]" min="1"></td>
    <td><input type="number" name="kills[<?= $p['participation_id'] ?>]" min="0"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div style="text-align:center;margin-top:20px">
    <button type="submit" class="br-btn">Save Match</button>
</div>
</form>
<?php endif; ?>

</div>

<script>
function validateMatch() {
    let ranks = [];
    document.querySelectorAll('input[name^="rank"]').forEach(r => {
        if (!r.value || ranks.includes(r.value)) {
            alert("Invalid or duplicate rank!");
            throw false;
        }
        else if(r.value == null || r.value<0){
         alert("Rank must be greater than 0 and can't be empty or null!");
         throw false;
        }
        ranks.push(r.value);
    });
    let kills =[];
    document.querySelectorAll('input[name^="kills"]').forEach(k =>{
        if(k.value==null || k.value < 0){
           alert("Kills can't be empty or negative value");
           throw false;
        }

    });
    return true;
}
</script>

</body>
</html>
