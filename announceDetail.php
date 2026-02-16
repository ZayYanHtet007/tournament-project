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
      // 2. NEW: Check if team is already registered in any ongoing/upcoming tournament
      $checkActiveTournamentsSql = "
        SELECT COUNT(*) as active_count, 
                GROUP_CONCAT(DISTINCT t.title SEPARATOR ', ') as tournament_names
        FROM tournament_teams tt
        JOIN tournaments t ON tt.tournament_id = t.tournament_id
        WHERE tt.team_id = ? 
        AND t.status IN ('upcoming', 'ongoing')
        AND t.tournament_id != ?"; // Exclude current tournament

      $checkStmt = $pdo->prepare($checkActiveTournamentsSql);
      $checkStmt->execute([$userTeam['team_id'], $t_id]);
      $activeTournaments = $checkStmt->fetch(PDO::FETCH_ASSOC);

      if ($activeTournaments['active_count'] > 0) {
        $tournamentNames = $activeTournaments['tournament_names'];
        echo "<script>alert('Registration Failed: Your team is already registered in active tournament(s): $tournamentNames. A team can only participate in one tournament at a time.');</script>";
      } else {
        // 3. Check current number of members in that team
        $countSql = "SELECT COUNT(*) FROM team_members WHERE team_id = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$userTeam['team_id']]);
        $currentMemberCount = (int)$countStmt->fetchColumn();

        $requiredSize = (int)$userTeam['required_size'];

        if ($currentMemberCount < $requiredSize) {
          echo "<script>alert('Registration Failed: Your team only has $currentMemberCount members. This tournament requires $requiredSize members.');</script>";
        } else {
          // 4. Check if already registered in this tournament
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
    }
  } catch (Exception $e) {
    echo "<script>alert('An error occurred: " . $e->getMessage() . "');</script>";
  }
}

// Add this function to check if team can register for tournaments
function canTeamRegisterForTournament($pdo, $team_id, $current_tournament_id = null)
{
  $sql = "
        SELECT COUNT(*) as active_count
        FROM tournament_teams tt
        JOIN tournaments t ON tt.tournament_id = t.tournament_id
        WHERE tt.team_id = ? 
        AND t.status IN ('upcoming', 'ongoing')
    ";

  if ($current_tournament_id) {
    $sql .= " AND t.tournament_id != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$team_id, $current_tournament_id]);
  } else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$team_id]);
  }

  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  return $result['active_count'] == 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($tournament['tournament_title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <style>
    /* Match Previous Gaming Theme */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Rajdhani', 'Segoe UI', Roboto, sans-serif;
    }

    body {
      background-color: #0a0a0a;
      background-image: radial-gradient(circle at center, #1a0505 0%, #0a0a0a 100%);
      color: #fff;
      padding: 40px 20px;
    }

    .container {
      max-width: 1000px;
      margin: auto;
      background: #151515;
      border: 1px solid #333;
      border-left: 4px solid #ff0000;
      border-radius: 4px;
      overflow: hidden;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
    }

    /* Header with Back Button on Right */
    .header {
      padding: 30px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }

    .header-text {
      flex: 1;
    }

    .btn-back {
      display: inline-block;
      color: #fff;
      text-decoration: none;
      font-weight: bold;
      text-transform: uppercase;
      font-size: 12px;
      padding: 8px 16px;
      border: 1px solid #ff0000;
      background: rgba(255, 0, 0, 0.1);
      transition: 0.3s;
      white-space: nowrap;
      margin-left: 20px;
    }

    .btn-back:hover {
      background: #ff0000;
      box-shadow: 0 0 15px rgba(255, 0, 0, 0.5);
    }

    .title {
      font-size: 32px;
      color: #ffffff;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .game {
      color: #ff0000;
      font-weight: bold;
      letter-spacing: 2px;
      text-transform: uppercase;
      font-size: 14px;
      margin-top: 5px;
    }

    .image {
      height: 350px;
      background-size: cover;
      background-position: center;
      border-top: 1px solid #222;
      border-bottom: 1px solid #222;
      position: relative;
    }

    .image::after {
      content: "";
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 100px;
      background: linear-gradient(transparent, #151515);
    }

    /* ===== DATE CARDS ===== */
    .dates {
      display: flex;
      gap: 15px;
      padding: 25px;
    }

    .date-card {
      flex: 1;
      background: #222;
      border: 1px solid #333;
      border-radius: 4px;
      padding: 15px;
      text-align: center;
      transition: 0.3s;
    }

    .date-card:hover {
      border-color: #ff0000;
    }

    .date-card1 {
      flex: 1;
      background: #222;
      border: 1px solid #333;
      border-radius: 4px;
      padding: 15px;
      text-align: center;
      transition: 0.3s;
    }

    .date-card1:hover {
      border-color: #fff;
    }

    .reg-end {
      border-bottom: 3px solid #ff0000;
    }

    .white-end{
      border-bottom: 3px solid #fff;
    }

    .date-label {
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
      color: #888;
      display: block;
      margin-bottom: 5px;
    }

    .date-value {
      font-size: 15px;
      font-weight: bold;
      color: #fff;
    }

    /* ===== PRIZE ===== */
    .prize {
      padding: 0 30px 20px;
      font-size: 24px;
      color: #ff0000;
      font-weight: 900;
      text-transform: uppercase;
    }

    /* ===== CONTENT ===== */
    .content {
      padding: 30px;
      background: rgba(0, 0, 0, 0.2);
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
    }

    .section-title {
      font-size: 18px;
      margin-bottom: 15px;
      color: #fff;
      text-transform: uppercase;
      border-left: 3px solid #ff0000;
      padding-left: 10px;
      font-weight: bold;
    }

    /* ===== SCROLL BOX ===== */
    .scroll-box {
      max-height: 260px;
      overflow-y: auto;
      background: #0d0d0d;
      border: 1px solid #222;
      border-radius: 4px;
      padding: 20px;
      line-height: 1.7;
      color: #ccc;
      font-size: 14px;
      white-space: pre-line;
    }

    .scroll-box::-webkit-scrollbar {
      width: 5px;
    }

    .scroll-box::-webkit-scrollbar-thumb {
      background: #ff0000;
    }

    .scroll-box::-webkit-scrollbar-track {
      background: #111;
    }

    /* ===== ACTIONS ===== */
    .fee-joined-row {
      display: flex;
      justify-content: space-between;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #222;
    }

    .fee {
      font-size: 18px;
      color: #fff;
      font-weight: bold;
    }

    .fee span {
      color: #ff0000;
    }

    .joined {
      color: #aaa;
      font-weight: bold;
    }

    .actions {
      margin-top: 25px;
    }

    .checkbox {
      display: flex;
      gap: 12px;
      align-items: center;
      margin-bottom: 15px;
      color: #bbb;
      font-size: 14px;
    }

    .checkbox input {
      accent-color: #ff0000;
      width: 18px;
      height: 18px;
      cursor: pointer;
    }

    .warning {
      display: none;
      margin-bottom: 15px;
      background: rgba(255, 0, 0, 0.2);
      border: 1px solid #ff0000;
      color: #fff;
      padding: 10px;
      border-radius: 4px;
      font-size: 13px;
      text-align: center;
    }

    button#registerBtn {
      width: 100%;
      padding: 15px;
      border: none;
      font-weight: 800;
      font-size: 16px;
      text-transform: uppercase;
      letter-spacing: 2px;
      cursor: pointer;
      background: #ff0000;
      color: #fff;
      clip-path: polygon(5% 0, 100% 0, 95% 100%, 0% 100%);
      transition: 0.3s;
    }

    button#registerBtn:hover:not(:disabled) {
      background: #cc0000;
      box-shadow: 0 0 20px rgba(255, 0, 0, 0.4);
    }

    button#registerBtn:disabled {
      background: #333;
      color: #666;
      cursor: not-allowed;
      clip-path: none;
    }

    .readonly {
      margin-top: 20px;
      background: #222;
      color: #ff0000;
      padding: 15px;
      text-align: center;
      font-weight: bold;
      text-transform: uppercase;
      border: 1px dashed #444;
    }

    .reg-start {
      border-bottom: 2px solid greenyellow;
    }

    /* Highlight deadline */
    .tour-start {
      border-bottom: 2px solid orange;
      color: #fff;
    }

    @media(max-width:768px) {
      .header {
        flex-direction: column-reverse;
        gap: 15px;
      }

      .btn-back {
        margin-left: 0;
        align-self: flex-start;
      }

      .grid {
        grid-template-columns: 1fr;
      }

      .dates {
        flex-direction: column;
      }

      .image {
        height: 200px;
      }

      button#registerBtn {
        clip-path: none;
      }
    }
  </style>
</head>

<body>

  <div class="container">

    <div class="header">
      <div class="header-text">
        <div class="title"><?= htmlspecialchars($tournament['tournament_title']) ?></div>
        <div class="game"><?= htmlspecialchars($tournament['game_name']) ?></div>
      </div>
      <a href="javascript:history.back()" class="btn-back">← Back to List</a>
    </div>

    <div class="image" style="background-image:url('images/games/<?= htmlspecialchars($tournament['game_image'] ?: 'defaultTournament.jpg') ?>')"></div>

    <div class="dates">
      <div class="date-card reg-start">
        <span class="date-label">Registration Start</span>
        <div class="date-value"><?= date('M d, Y', strtotime($tournament['created_at'])) ?></div>
      </div>
      <div class="date-card1 reg-end">
        <span class="date-label">Registration Deadline</span>
        <div class="date-value"><?= date('M d, Y', strtotime($tournament['registration_deadline'])) ?></div>
      </div>
      <div class="date-card tour-start">
        <span class="date-label">Tournament Start</span>
        <div class="date-value"><?= date('M d, Y', strtotime($tournament['registration_start_date'])) ?></div>
      </div>
    </div>

    <div class="prize">Prize Pool: $<?= number_format($tournament['prize_pool'], 2) ?></div>

    <div class="content">

      <div class="grid">
        <div>
          <div class="section-title">Rules & Regulations</div>
          <div class="scroll-box"><?= nl2br(htmlspecialchars($rulesText)) ?></div>
        </div>

        <div>
          <div class="section-title">Tournament System</div>
          <div class="scroll-box"><?= nl2br(htmlspecialchars($systemText)) ?></div>
        </div>
      </div>

      <div class="fee-joined-row">
        <div class="fee">
          Entry Fee: <span>$<?= number_format($tournament['fee'], 2) ?></span>
        </div>
        <div class="joined">
          SLOTS: <?= $joinedTeams ?> / <?= $tournament['max_participants'] ?>
        </div>
      </div>

      <?php if ($isCompleted): ?>
        <div class="readonly">Tournament completed. Registration closed.</div>
      <?php else: ?>
        <div class="actions">
          <form method="POST">
            <div class="checkbox">
              <input type="checkbox" id="agree">
              <label for="agree">I have read and agree to the rules & regulations</label>
            </div>
            <div id="warn" class="warning">You must agree to the rules to proceed.</div>
            <button type="submit" id="registerBtn" disabled name="btnRegister">Register Team</button>
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