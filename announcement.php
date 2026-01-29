<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'database/dbConfig.php'; // PDO connection

$today = date('Y-m-d');

$sql = "
SELECT 
  t.tournament_id,
  t.title AS tournament_title,
  t.registration_deadline,
  t.start_date,
  t.max_participants,
  t.prize_pool,
  g.name AS game_name,
  g.image AS game_image,
  COUNT(tr.team_id) AS joined_teams
FROM tournaments t
JOIN games g 
  ON t.game_id = g.game_id
LEFT JOIN tournament_teams tr
  ON tr.tournament_id = t.tournament_id
WHERE 
  t.status = 'upcoming'
  AND t.admin_status = 'approved'
  AND t.registration_deadline >= ?
GROUP BY t.tournament_id
HAVING joined_teams < t.max_participants
ORDER BY t.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$today]);
$tournaments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gaming Tournaments</title>

<style>
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
body {
  background-color: #121212;
  padding: 20px;
  color: #fff;
}
.cards-container {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}
.card {
  width: 48%;
  background-color: #1e1e1e;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  box-shadow: 0 4px 14px rgba(0,0,0,0.6);
  transition: all 0.25s ease;
}
.card:hover {
  transform: translateY(-4px) scale(1.02);
  box-shadow: 0 10px 25px rgba(0,0,0,0.8);
}
.card-img {
  width: 40%;
  background-size: cover;
  background-position: center;
  
}
.card-content {
  padding: 16px 20px;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.tournament-name {
  font-size: 20px;
  font-weight: bold;
  color: #ffcc00;
}
.game-name {
  font-size: 14px;
  color: #ccc;
  margin-bottom: 10px;
}
.dates {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
}
.date-item {
  flex: 1;
  background: #333;
  border-radius: 8px;
  padding: 6px;
  font-size: 11px;
  text-align: center;
}
.reg-start { background: #00c6ff; color:#000; }
.reg-end { background: #ff416c; }
.tour-start { background: #ffcc00; color:#000; }

.date-label {
  font-weight: bold;
}
.teams-joined, .prize-pool {
  font-size: 13px;
  color: #ddd;
  margin-bottom: 6px;
}
.buttons {
  margin-top: 10px;
}
.btn {
  display: block;
  width: 100%;
  padding: 10px 0;
  text-align: center;
  border-radius: 8px;
  text-decoration: none;
  font-weight: bold;
  background: linear-gradient(45deg, #00c6ff, #0072ff);
  color: #fff;
  transition: 0.3s;
}
.btn:hover {
  background: linear-gradient(45deg, #0072ff, #00c6ff);
}

@media (max-width: 768px) {
  .card {
    width: 100%;
    flex-direction: column;
  }
  .card-img {
    width: 100%;
    height: 180px;
  }
}
</style>
</head>

<body>

<div class="cards-container">

<?php if (empty($tournaments)): ?>
  <p>No tournaments available right now.</p>
<?php endif; ?>

<?php foreach ($tournaments as $t): ?>
  <?php
    $image = $t['game_image'] ?: 'images\games\defaultTournament.jpg';
  ?>
  <div class="card">
    <div class="card-img" style="background-image:url('images/games/<?= htmlspecialchars($image) ?>')"></div>

    <div class="card-content">
      <div>
        <div class="tournament-name"><?= htmlspecialchars($t['tournament_title']) ?></div>
        <div class="game-name"><?= htmlspecialchars($t['game_name']) ?></div>

        <div class="dates">
          <div class="date-item reg-start">
            <div class="date-label">Reg Start</div>
            <div><?= date('M d, Y', strtotime($t['created_at'] ?? $today)) ?></div>
          </div>
          <div class="date-item reg-end">
            <div class="date-label">Reg End</div>
            <div><?= date('M d, Y', strtotime($t['registration_deadline'])) ?></div>
          </div>
          <div class="date-item tour-start">
            <div class="date-label">Start</div>
            <div><?= date('M d, Y', strtotime($t['start_date'])) ?></div>
          </div>
        </div>

        <div class="teams-joined">
          Teams Joined: <?= (int)$t['joined_teams'] ?> / <?= (int)$t['max_participants'] ?>
        </div>

        <div class="prize-pool">
          Prize Pool: $<?= number_format($t['prize_pool'], 2) ?>
        </div>
      </div>

      <div class="buttons">
        <a href="announceDetail.php?tournament_id=<?= (int)$t['tournament_id'] ?>" class="btn">
          Detail & Register
        </a>
      </div>
    </div>
  </div>
<?php endforeach; ?>

</div>

</body>
</html>
