<?php
session_start();
require_once "../database/dbConfig.php";
include("header.php");

/* ======================
   ACCESS CONTROL
====================== */
if (
  !isset($_SESSION['user_id']) ||
  !isset($_SESSION['is_organizer']) ||
  $_SESSION['is_organizer'] != 1
) {
  header("Location: ../login.php");
  exit;
}

$organizer_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Organizer';

/* ======================
   FETCH ORGANIZER TOURNAMENTS
====================== */
$stmt = $conn->prepare("
    SELECT 
        t.tournament_id,
        t.title,
        g.name AS game_name,
        t.status,
        t.admin_status,
        t.created_at
    FROM tournaments t
    JOIN games g ON t.game_id = g.game_id
    WHERE t.organizer_id = ?
    ORDER BY t.created_at DESC
");
$stmt->bind_param("i", $organizer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Tournaments | Organizer</title>
  <style>
    :root {
      --riot-blue: #0bc6e3;
      --riot-dark-blue: #00151e;
      --riot-navy: #0a1428;
      --riot-gold: #c8aa6e; /* Hextech Gold accent */
      --riot-gray: #a0a0a0;
      --card-bg: #010a13;
      --card-accent: #051923;
    }

    body {
      background-color: var(--riot-dark-blue);
      color: #f0f5f5;
      font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      margin: 0;
    }

    /* Hero Section */
    .hero-content h1 {
      font-size: 2.5rem;
      margin-top: 40px;
      margin-bottom: 10px;
      color: var(--riot-blue);
      text-shadow: 0 0 15px rgba(11, 198, 227, 0.5);
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 3px;
    }

    /* Dashboard Grid */
    .dashboard {
      max-width: 1200px;
      margin: 40px auto;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 30px;
      padding: 0 20px;
    }

    /* NEW CARD STYLE - BLUE & BLACK HEXTECH */
    .riot-card {
      background: linear-gradient(135deg, #010a13 0%, #051923 100%);
      border-left: 4px solid var(--riot-blue);
      border-right: 1px solid rgba(11, 198, 227, 0.2);
      border-top: 1px solid rgba(11, 198, 227, 0.2);
      border-bottom: 1px solid rgba(11, 198, 227, 0.2);
      position: relative;
      padding: 30px;
      transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      /* Clipped corner effect */
      clip-path: polygon(0 0, 100% 0, 100% 85%, 90% 100%, 0 100%);
    }

    .riot-card:hover {
      transform: scale(1.02);
      border-color: var(--riot-blue);
      background: linear-gradient(135deg, #02121e 0%, #0a2a3a 100%);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(11, 198, 227, 0.1);
    }

    .riot-card::after {
        content: "TX-INTEL";
        position: absolute;
        bottom: 5px;
        right: 40px;
        font-size: 10px;
        color: rgba(11, 198, 227, 0.3);
        font-weight: 900;
        letter-spacing: 2px;
    }

    .riot-card h3 {
      font-size: 1.5rem;
      color: #fff;
      margin: 10px 0;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 800;
    }

    .game-badge {
      background: rgba(11, 198, 227, 0.1);
      color: var(--riot-blue);
      padding: 4px 10px;
      font-size: 0.75rem;
      font-weight: 900;
      display: inline-block;
      border: 1px solid var(--riot-blue);
      text-transform: uppercase;
      margin-bottom: 10px;
    }

    .stats-row {
      border-top: 1px solid rgba(11, 198, 227, 0.1);
      padding-top: 20px;
      margin-top: 20px;
    }

    .status-text {
      color: var(--riot-gold);
      text-transform: uppercase;
      font-weight: bold;
      font-size: 0.75rem;
      letter-spacing: 1px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .status-text::before {
        content: "";
        width: 8px;
        height: 8px;
        background: var(--riot-gold);
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 8px var(--riot-gold);
    }

    /* Riot Buttons */
    .btn-riot {
      display: block;
      text-align: center;
      padding: 14px;
      text-transform: uppercase;
      font-weight: 900;
      font-size: 0.8rem;
      letter-spacing: 2px;
      cursor: pointer;
      transition: 0.3s;
      text-decoration: none;
      margin-top: 20px;
      background: transparent;
      border: 1px solid var(--riot-blue);
      color: var(--riot-blue);
      position: relative;
      overflow: hidden;
    }

    .btn-riot:hover {
      background: var(--riot-blue);
      color: #000;
      box-shadow: 0 0 25px rgba(11, 198, 227, 0.4);
    }

    .empty-state {
      grid-column: 1 / -1;
      text-align: center;
      padding: 60px;
      background: rgba(0,0,0,0.3);
      border: 1px dashed var(--riot-blue);
    }
  </style>
</head>

<body>

  <div id="mobileOverlay" class="mobile-overlay" tabindex="-1"></div>

    <div class="hero-content">
      <h1>My Tournaments</h1>
      <p style="color: var(--riot-gray); text-align:center; letter-spacing: 1px;">Accessing Organizer Database...</p>
    </div>

  <section class="dashboard">

    <?php if ($result->num_rows === 0): ?>
      <div class="empty-state">
        <p style="font-size: 1.2rem; margin-bottom: 20px;">No active deployments found.</p>
        <a href="createTournament.php" class="btn-riot" style="display:inline-block; padding: 14px 40px;">Initiate Tournament</a>
      </div>
    <?php endif; ?>

    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="riot-card">
        <div>
          <span class="game-badge"><?= htmlspecialchars($row['game_name']) ?></span>
          <h3><?= htmlspecialchars($row['title']) ?></h3>
          <p class="status-text"><?= htmlspecialchars($row['status']) ?></p>
        </div>

        <div class="stats-row">
          <p style="color: var(--riot-gray); margin: 0; font-size: 0.8rem; text-transform: uppercase;">
            Date Logged: <span style="color: #fff;"><?= date('d M Y', strtotime($row['created_at'])) ?></span>
          </p>
          <a href="manageTournament.php?id=<?= $row['tournament_id'] ?>" class="btn-riot">
            Modify Intel
          </a>
        </div>
      </div>
    <?php endwhile; ?>

  </section>

  <script>
    (function(){
      const btn = document.getElementById('mobileMenuBtn');
      const sidebar = document.getElementById('mobileSidebar');
      const overlay = document.getElementById('mobileOverlay');

      function openMenu(){
        document.body.classList.add('mobile-menu-open');
        if(btn) btn.setAttribute('aria-expanded','true');
        sidebar.setAttribute('aria-hidden','false');
      }
      function closeMenu(){
        document.body.classList.remove('mobile-menu-open');
        if(btn) btn.setAttribute('aria-expanded','false');
        sidebar.setAttribute('aria-hidden','true');
      }

      btn && btn.addEventListener('click', function(e){
        if(document.body.classList.contains('mobile-menu-open')) closeMenu(); else openMenu();
      });
      overlay && overlay.addEventListener('click', closeMenu);
      document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeMenu(); });
    })();
  </script>

<?php include("footer.php"); ?>
</body>
</html>