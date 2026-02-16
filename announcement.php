<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'database/dbConfig.php'; // PDO connection
include 'partial/header.php'; // Include header for consistent styling and session management

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
    /* Gaming Style Theme: Red & Black */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Rajdhani', 'Segoe UI', Roboto, sans-serif;
      /* Using a more 'gamer' font feel */
    }

    body {
      background-color: #0a0a0a;
      /* Deep Black */
      background-image: radial-gradient(circle at center, #1a0505 0%, #0a0a0a 100%);
      padding: 40px 20px;
      color: #eeeeee;
    }

    .cards-container {
      display: flex;
      flex-wrap: wrap;
      gap: 25px;
      max-width: 1200px;
      margin: 0 auto;
    }

    .card {
      width: 48%;
      background-color: #151515;
      border: 1px solid #333;
      border-left: 4px solid #ff0000;
      /* Red Accent Stripe */
      border-radius: 4px;
      /* Sharper corners for gaming look */
      overflow: hidden;
      display: flex;
      position: relative;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    .card:hover {
      transform: translateY(-5px);
      border-color: #ff0000;
      box-shadow: 0 0 20px rgba(255, 0, 0, 0.2);
    }

    .card-img {
      width: 40%;
      background-size: cover;
      background-position: center;
      filter: grayscale(30%);
      transition: 0.3s;
      border-right: 1px solid #222;
    }

    .card:hover .card-img {
      filter: grayscale(0%);
    }

    .card-content {
      padding: 20px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background: linear-gradient(135deg, #151515 0%, #0f0f0f 100%);
    }

    .tournament-name {
      font-size: 22px;
      font-weight: 800;
      color: #ffffff;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 2px;
    }

    .game-name {
      font-size: 13px;
      color: #ff0000;
      /* Red accent for game name */
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 15px;
      letter-spacing: 2px;
    }

    .dates {
      display: flex;
      gap: 10px;
      margin-bottom: 15px;
    }

    .date-item {
      flex: 1;
      background: #222;
      border: 1px solid #333;
      border-radius: 4px;
      padding: 8px 4px;
      font-size: 10px;
      text-align: center;
      color: #bbb;
    }

    .date-label {
      font-weight: bold;
      display: block;
      text-transform: uppercase;
      color: #888;
      margin-bottom: 2px;
    }

    /* Specific item accents */
    .reg-start {
      border-bottom: 2px solid greenyellow;
    }

    .reg-end {
      border-bottom: 2px solid #ff0000;
      color: #fff;
    }

    /* Highlight deadline */
    .tour-start {
      border-bottom: 2px solid orange;
      color: #fff;
    }

    .teams-joined,
    .prize-pool {
      font-size: 14px;
      color: #ddd;
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
      border-bottom: 1px solid #222;
      padding-bottom: 4px;
    }

    .prize-pool {
      color: #ff0000;
      font-weight: bold;
      border: none;
    }

    .buttons {
      margin-top: 15px;
    }

    .btn {
      display: block;
      width: 100%;
      padding: 12px 0;
      text-align: center;
      text-decoration: none;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      background: #ff0000;
      color: #fff;
      clip-path: polygon(10% 0, 100% 0, 90% 100%, 0% 100%);
      /* Angled Gaming Button */
      transition: 0.3s;
    }

    .btn:hover {
      background: #cc0000;
      box-shadow: 0 0 15px rgba(255, 0, 0, 0.4);
      letter-spacing: 2px;
    }

    @media (max-width: 992px) {
      .card {
        width: 100%;
      }
    }

    @media (max-width: 768px) {
      .card {
        flex-direction: column;
      }

      .card-img {
        width: 100%;
        height: 200px;
        border-right: none;
        border-bottom: 1px solid #333;
      }

      .btn {
        clip-path: none;
        border-radius: 4px;
      }
    }
  </style>
</head>

<body>

  <div class="cards-container">

    <?php if (empty($tournaments)): ?>
      <p style="text-align:center; width:100%; font-size: 1.2rem; color: #666;">No tournaments available right now.</p>
    <?php endif; ?>

    <?php foreach ($tournaments as $t): ?>
      <?php
      $image = $t['game_image'] ?: 'images/games/defaultTournament.jpg';
      ?>
      <div class="card">
        <div class="card-img" style="background-image:url('images/games/<?= htmlspecialchars($image) ?>')"></div>

        <div class="card-content">
          <div>
            <div class="tournament-name"><?= htmlspecialchars($t['tournament_title']) ?></div>
            <div class="game-name"><?= htmlspecialchars($t['game_name']) ?></div>

            <div class="dates">
              <div class="date-item reg-start">
                <span class="date-label">Reg Start</span>
                <div><?= date('M d, Y', strtotime($t['created_at'] ?? $today)) ?></div>
              </div>
              <div class="date-item reg-end">
                <span class="date-label">Reg End</span>
                <div><?= date('M d, Y', strtotime($t['registration_deadline'])) ?></div>
              </div>
              <div class="date-item tour-start">
                <span class="date-label">Start</span>
                <div><?= date('M d, Y', strtotime($t['start_date'])) ?></div>
              </div>
            </div>

            <div class="teams-joined">
              <span>Teams Joined:</span>
              <span><?= (int)$t['joined_teams'] ?> / <?= (int)$t['max_participants'] ?></span>
            </div>

            <div class="prize-pool">
              <span>PRIZE POOL:</span>
              <span>$<?= number_format($t['prize_pool'], 2) ?></span>
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