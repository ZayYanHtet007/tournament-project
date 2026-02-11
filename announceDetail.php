
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
  session_start();   // ✅ REQUIRED
}

require 'database/dbConfig.php';

$tournament_id = $_GET['tournament_id'] ?? 0;
if (!$tournament_id) {
  die('Invalid tournament');
}

/* ================= FETCH TOURNAMENT ================= */
$sqlTournament = "
SELECT 
  t.tournament_id,
  t.title AS tournament_title,
  t.status,
  t.fee,
  t.prize_pool,
  t.max_participants,
  t.registration_deadline,
  t.registration_start_date,
  t.created_at,
  g.name AS game_name,
  g.genre,
  g.image AS game_image
FROM tournaments t
JOIN games g ON t.game_id = g.game_id
WHERE t.tournament_id = ?
";
$stmt = $pdo->prepare($sqlTournament);
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
  die('Tournament not found');
}

/* ================= FETCH ANNOUNCEMENT ================= */
$sqlAnnounce = "
SELECT rules, system_info
FROM tournament_announcements
WHERE tournament_id = ?
";
$stmt = $pdo->prepare($sqlAnnounce);
$stmt->execute([$tournament_id]);
$announce = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$announce) {
  die('Announcement not found for this tournament');
}

/* ================= JOINED TEAMS ================= */
$sqlJoined = "
SELECT COUNT(*) 
FROM tournament_teams
WHERE tournament_id = ?
";
$stmt = $pdo->prepare($sqlJoined);
$stmt->execute([$tournament_id]);
$joinedTeams = (int)$stmt->fetchColumn();

/* ================= DATA FROM DB ONLY ================= */
$rulesText  = $announce['rules'];
$systemText = $announce['system_info'];

$isCompleted = ($tournament['status'] === 'completed');
if (isset($_POST['btnRegister'])) {
  if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please login to register.'); window.location.href='login.php';</script>";
    exit;
  }

  $user_id = (int)$_SESSION['user_id'];
  $t_id = (int)$tournament_id;

  try {
    // 1. Fetch the user's team and the required team size for this tournament
    $teamSql = "
        SELECT t.team_id, t.status, tourn.team_size as required_size
        FROM teams t
        JOIN team_members tm ON t.team_id = tm.team_id
        CROSS JOIN tournaments tourn
        WHERE tm.user_id = ? 
        AND tm.role = 'leader' 
        AND tourn.tournament_id = ?
        LIMIT 1";

    $teamStmt = $pdo->prepare($teamSql);
    $teamStmt->execute([$user_id, $t_id]);
    $userTeam = $teamStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userTeam) {
      echo "<script>alert('Error: You must be a team leader to register.');</script>";
    } elseif (strtolower($userTeam['status']) === 'disban' || strtolower($userTeam['status']) === 'disbanded') {
      echo "<script>alert('Registration Failed: This team is inactive.');</script>";
    } else {
      // 2. NEW: Check the current number of members in that team
      $countSql = "SELECT COUNT(*) FROM team_members WHERE team_id = ?";
      $countStmt = $pdo->prepare($countSql);
      $countStmt->execute([$userTeam['team_id']]);
      $currentMemberCount = (int)$countStmt->fetchColumn();

      $requiredSize = (int)$userTeam['required_size'];

      if ($currentMemberCount < $requiredSize) {
        echo "<script>alert('Registration Failed: Your team only has $currentMemberCount members. This tournament requires $requiredSize members.');</script>";
      } else {
        // 3. Check if already registered
        $checkReg = $pdo->prepare("SELECT id FROM tournament_teams WHERE tournament_id = ? AND team_id = ?");
        $checkReg->execute([$t_id, $userTeam['team_id']]);

        if ($checkReg->rowCount() > 0) {
          echo "<script>alert('Your team is already registered.');</script>";
        } else {
          header("Location: ./player/player-stripe-payment.php?tournament_id=" . $tournament_id);
          exit;
        }
      }
    }
  } catch (Exception $e) {
    echo "<script>alert('An error occurred: " . $e->getMessage() . "');</script>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($tournament['tournament_title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <style>
    body {
      background: #121212;
      color: #fff;
      font-family: 'Segoe UI', sans-serif;
      padding: 20px;
    }

    .container {
      max-width: 1000px;
      margin: auto;
      background: #1e1e1e;
      border-radius: 14px;
      overflow: hidden;
    }

    .header {
      padding: 20px;
    }

    .title {
      font-size: 26px;
      color: #ffcc00;
      font-weight: bold;
    }

    .game {
      color: #ccc;
    }

    .image {
      height: 260px;
      background-size: cover;
      background-position: center;
    }

    /* ===== DATE CARDS ===== */
    .dates {
      display: flex;
      gap: 12px;
      padding: 20px;
    }

    .date-card {
      flex: 1;
      background: #2a2a2a;
      border-radius: 10px;
      padding: 12px;
      text-align: center;
    }

    .date-card.reg-start {
      background: #00c6ff;
      color: #000;
    }

    .date-card.reg-end {
      background: #ff416c;
    }

    .date-card.tour-start {
      background: #ffcc00;
      color: #000;
    }

    .date-label {
      font-size: 12px;
      font-weight: bold;
    }

    .date-value {
      margin-top: 4px;
      font-size: 13px;
    }

    /* ===== PRIZE ===== */
    .prize {
      padding: 0 20px 20px;
      font-size: 18px;
      color: #00ffcc;
      font-weight: bold;
    }

    /* ===== CONTENT ===== */
    .content {
      padding: 20px;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .section-title {
      font-size: 17px;
      margin-bottom: 8px;
      color: #00c6ff;
    }

    /* ===== SCROLL BOX ===== */
    .scroll-box {
      max-height: 260px;
      overflow-y: auto;
      background: #151515;
      border-radius: 10px;
      padding: 15px;
      line-height: 1.6;
      white-space: pre-line;
    }

    .scroll-box::-webkit-scrollbar {
      width: 6px;
    }

    .scroll-box::-webkit-scrollbar-thumb {
      background: #00c6ff;
      border-radius: 10px;
    }

    .scroll-box::-webkit-scrollbar-track {
      background: #111;
    }

    /* ===== ACTIONS ===== */
    .fee {
      margin-top: 20px;
      font-size: 15px;
      color: #ffcc00;
    }

    .joined {
      margin-top: 5px;
      color: #ccc;
    }

    .actions {
      margin-top: 20px;
    }

    .checkbox {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .checkbox input {
      width: 18px;
      height: 18px;
    }

    .warning {
      display: none;
      margin-top: 10px;
      background: #ff416c;
      padding: 8px;
      border-radius: 6px;
    }

    button {
      margin-top: 15px;
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      background: linear-gradient(45deg, #00c6ff, #0072ff);
      color: #fff;
    }

    button:disabled {
      background: #555;
      cursor: not-allowed;
    }

    .readonly {
      margin-top: 15px;
      background: #333;
      padding: 12px;
      text-align: center;
      border-radius: 8px;
    }

    @media(max-width:768px) {
      .grid {
        grid-template-columns: 1fr;
      }

      .dates {
        flex-direction: column;
      }
    }
  </style>
</head>

<body>

  <div class="container">

    <div class="header">
      <div class="title"><?= htmlspecialchars($tournament['tournament_title']) ?></div>
      <div class="game"><?= htmlspecialchars($tournament['game_name']) ?></div>
    </div>

    <div class="image" style="background-image:url('<?= htmlspecialchars($tournament['game_image'] ?: 'images/games/defaultTournament.jpg') ?>')"></div>

    <!-- DATE CARDS -->
    <div class="dates">
      <div class="date-card reg-start">
        <div class="date-label">Reg Start</div>
        <div class="date-value"><?= date('M d, Y', strtotime($tournament['created_at'])) ?></div>
      </div>
      <div class="date-card reg-end">
        <div class="date-label">Reg End</div>
        <div class="date-value"><?= date('M d, Y', strtotime($tournament['registration_deadline'])) ?></div>
      </div>
      <div class="date-card tour-start">
        <div class="date-label">Tournament Start</div>
        <div class="date-value"><?= date('M d, Y', strtotime($tournament['registration_start_date'])) ?></div>
      </div>
    </div>

    <div class="prize">💰 Prize Pool: $<?= number_format($tournament['prize_pool'], 2) ?></div>

    <div class="content">

      <div class="grid">
        <div>
          <div class="section-title">📜 Rules & Regulations</div>
          <div class="scroll-box"><?= nl2br(htmlspecialchars($rulesText)) ?></div>
        </div>

        <div>
          <div class="section-title">⚙ Tournament System</div>
          <div class="scroll-box"><?= nl2br(htmlspecialchars($systemText)) ?></div>
        </div>
      </div>

      <div class="fee">
        Registration Fee: $<?= number_format($tournament['fee'], 2) ?>
      </div>
      <div class="joined">
        Joined Teams: <?= $joinedTeams ?> / <?= $tournament['max_participants'] ?>
      </div>

      <?php if ($isCompleted): ?>
        <div class="readonly">Tournament completed. Registration closed.</div>
      <?php else: ?>
        <div class="actions">
          <form method="POST">
            <div class="checkbox">
              <input type="checkbox" id="agree">
              <label for="agree">I agree to the rules & regulations</label>
            </div>
            <div id="warn" class="warning">Must agree rules and regulations to register</div>
            <button type="submit" id="registerBtn" disabled name="btnRegister">Register Now</button>
          </form>
        </div>
      <?php endif; ?>

    </div>
  </div>


  <script>
    const agree = document.getElementById('agree');
    const btn = document.getElementById('registerBtn');
    const warn = document.getElementById('warn');

    if (agree) {
      agree.addEventListener('change', () => {
        btn.disabled = !agree.checked;
        warn.style.display = 'none';
      });
      btn.addEventListener('click', e => {
        if (!agree.checked) {
          e.preventDefault();
          warn.style.display = 'block';
        }
      });
    }
  </script>

</body>

</html>