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
  <title>My Tournaments</title>
  <link rel="stylesheet" href="../css/organizer/tournaments.css">
</head>

<body>

  <header class="legacy-header">
    <div class="legacy-logo">Tourna<span>X</span></div>
    <nav class="legacy-signnav">
      <a href="organizer-home.php">Dashboard</a>
      <a href="../logout.php">Logout</a>
    </nav>
  </header>

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

        <a href="editTournament.php?id=<?= $row['tournament_id'] ?>"
          class="btn secondary">
          Edit Tournament
        </a>
      </div>
    <?php endwhile; ?>

  </section>

  <footer class="footer">
    © 2025 TournaX. All rights reserved.
  </footer>

</body>

</html>