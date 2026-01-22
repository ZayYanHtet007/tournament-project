<?php
session_start();
require_once "../database/dbConfig.php";

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
  <title>My Tournaments</title>
  <link rel="stylesheet" href="../css/organizer/tournaments.css">
  <link rel="stylesheet" href="../css/user/responsive.css">
</head>

<body>

  <header class="legacy-header">
    <div class="legacy-logo">Tourna<span>X</span></div>
    <button id="mobileMenuBtn" class="mobile-menu-button" aria-label="Open menu" aria-expanded="false">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </button>
    <nav class="legacy-signnav">
      <a href="organizer-home.php">Dashboard</a>
      <a href="../logout.php">Logout</a>
    </nav>
  </header>

  <!-- Mobile off-canvas sidebar -->
  <aside id="mobileSidebar" class="mobile-sidebar" aria-hidden="true">
    <nav class="mobile-nav">
      <a href="organizer-home.php">Dashboard</a>
      <a href="createTournament.php">Create Tournament</a>
      <a href="manageTournament.php">Manage Tournaments</a>
      <a href="../logout.php">Logout</a>
    </nav>
  </aside>
  <div id="mobileOverlay" class="mobile-overlay" tabindex="-1"></div>

  <section class="hero">
    <div class="hero-content">
      <h1>My Tournaments</h1>
      <p>Select a tournament to manage</p>
    </div>
  </section>

  <section class="dashboard">

    <?php if ($result->num_rows === 0): ?>
      <div class="card">
        <p>No tournaments created yet.</p>
        <a href="createTournament.php" class="btn primary">Create Tournament</a>
      </div>
    <?php endif; ?>

    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="card">
        <h3><?= htmlspecialchars($row['title']) ?></h3>
        <p>🎮 <?= htmlspecialchars($row['game_name']) ?></p>
        <p>Status: <strong><?= $row['status'] ?></strong></p>
        <p>Created: <?= date('d M Y', strtotime($row['created_at'])) ?></p>

        <a href="manageTournament.php?id=<?= $row['tournament_id'] ?>"
          class="btn secondary">
          Manage Tournament
        </a>
        
      </div>
    <?php endwhile; ?>

  </section>

  <footer class="footer">
    © 2025 TournaX. All rights reserved.
  </footer>

  <script>
    (function(){
      const btn = document.getElementById('mobileMenuBtn');
      const sidebar = document.getElementById('mobileSidebar');
      const overlay = document.getElementById('mobileOverlay');

      function openMenu(){
        document.body.classList.add('mobile-menu-open');
        btn.setAttribute('aria-expanded','true');
        sidebar.setAttribute('aria-hidden','false');
      }
      function closeMenu(){
        document.body.classList.remove('mobile-menu-open');
        btn.setAttribute('aria-expanded','false');
        sidebar.setAttribute('aria-hidden','true');
      }

      btn && btn.addEventListener('click', function(e){
        if(document.body.classList.contains('mobile-menu-open')) closeMenu(); else openMenu();
      });
      overlay && overlay.addEventListener('click', closeMenu);
      document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeMenu(); });
    })();
  </script>
</body>

</html>