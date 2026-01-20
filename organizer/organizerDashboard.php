<?php
include("header.php");
?>

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

/* ======================
   USER INFO
====================== */
$username = $_SESSION['username'] ?? 'Organizer';
$isLoggedIn = true;

/* ======================
   FETCH LATEST TOURNAMENT
====================== */
$tournament_id = null;

$stmt = $conn->prepare("
    SELECT tournament_id
    FROM tournaments
    WHERE organizer_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
  $tournament_id = $row['tournament_id'];
}
?>



<!-- Background -->

<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<!-- Particles -->
<script>
  for (let i = 0; i < 20; i++) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    particle.style.left = Math.random() * 100 + '%';
    particle.style.animationDelay = Math.random() * 8 + 's';
    particle.style.animationDuration = (6 + Math.random() * 4) + 's';
    document.body.appendChild(particle);
  }
</script>

<div class="neon-line line-1"></div>
<div class="neon-line line-2"></div>

<section class="hero">
  <div class="hero-content">
    <h1>Welcome, <?= htmlspecialchars($username) ?> 🎮</h1>
    <p>Compete. Manage. Dominate the Tournament Arena.</p>

    <div class="hero-buttons">
      <a href="createTournament.php" class="btn primary">
        Create Tournament
      </a>

      <?php if ($tournament_id): ?>
        <a href="tournaments.php" class="btn secondary">
          Manage Tournaments
        </a>

        </a>
      <?php else: ?>
        <a href="createTournament.php" class="btn secondary">
          No Tournament Yet
        </a>
      <?php endif; ?>
    </div>
  </div>  
</section>

<section class="dashboard">
  <div class="card">🏆 Tournament Detail</div>
  <div class="card">📊 Bracket Management</div>
  <div class="card">✅ Result Management</div>
  <div class="card">⏱ Deadline Management</div>
  <div class="card">💬 Chat</div>
  <div class="card">🧾 History</div>
</section>

<footer class="footer">
  © 2025 TournaX. All rights reserved.
</footer>

</body>

</html>