<?php
session_start();

/* Fake login check (replace with real auth later) */
$isLoggedIn = true;
$username = "Organizer";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TournaX | Home</title>
    <link rel="stylesheet" href="../css/organizer.css">
</head>
<body>

<!-- Animated Grid Background -->
  <div class="grid-background"></div>
  
  <!-- Floating Cubes -->
  <div class="cube cube-1"></div>
  <div class="cube cube-2"></div>
  <div class="cube cube-3"></div>
  <div class="cube cube-4"></div>
  <div class="cube cube-5"></div>

  <!-- Glowing Orbs -->
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <!-- Particles -->
  <script>
    for(let i = 0; i < 50; i++) {
      const particle = document.createElement('div');
      particle.className = 'particle';
      particle.style.left = Math.random() * 100 + '%';
      particle.style.top = Math.random() * 100 + '%';
      particle.style.animationDelay = Math.random() * 5 + 's';
      particle.style.animationDuration = (3 + Math.random() * 4) + 's';
      document.body.appendChild(particle);
    }
  </script>

  <!-- Neon Lines -->
  <div class="neon-line line-1"></div>
  <div class="neon-line line-2"></div>
  <div class="neon-line line-3"></div>

<header class="legacy-header">
    <div class="legacy-logo">Tourna<span>X</span></div>

    <nav class="legacy-headnav">
        <a href="#">Tournament</a>
        <a href="#">Post</a>
        <a href="#">Report</a>
    </nav>

    <nav class="legacy-signnav">
        <?php if($isLoggedIn): ?>
            <a href="#">Profile</a>
            <a href="#">Logout</a>
        <?php else: ?>
            <a href="#">Login</a>
            <a href="#">Register</a>
        <?php endif; ?>
    </nav>
</header>

<section class="hero">
    <div class="hero-content">
        <h1>Welcome, <?php echo $username; ?> 🎮</h1>
        <p>Compete. Manage. Dominate the Tournament Arena.</p>

        <div class="hero-buttons">
            <a href="#" class="btn primary">Create Tournament</a>
            <a href="#" class="btn secondary">Join Tournament</a>
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