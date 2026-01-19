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

$organizer_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Organizer';

/* ======================
   FETCH ORGANIZER TOURNAMENTS
====================== */
$stmt = $conn->prepare("
    SELECT tournament_id, title, game_name, status, created_at
    FROM tournaments
    WHERE organizer_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $organizer_id);
$stmt->execute();
$result = $stmt->get_result();
?>



    <!-- Background -->
    <div class="grid-background"></div>
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
          Edit Tournament
        </a>
      </div>
    <?php endwhile; ?>

  </section>

<?php
include('footer.php');
?>
