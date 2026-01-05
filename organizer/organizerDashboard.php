<?php
session_start();

/* Fake login check (replace with real auth later) */
$isLoggedIn = true;
$username = "Organizer";
?>

<?php
include 'header.php';
?>

<!-- Animated Grid Background -->
  <div class="grid-background"></div>

  <!-- Background Effects -->
  <div class="grid-background"></div>s
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>

  <!-- Particles -->
  <script>
    for(let i = 0; i < 20; i++) {
      const particle = document.createElement('div');
      particle.className = 'particle';
      particle.style.left = Math.random() * 100 + '%';
      particle.style.animationDelay = Math.random() * 8 + 's';
      particle.style.animationDuration = (6 + Math.random() * 4) + 's';
      document.body.appendChild(particle);
    }
  </script>

  <!-- Neon Lines -->
  <div class="neon-line line-1"></div>
  <div class="neon-line line-2"></div>

<section class="hero">
    <div class="hero-content">
        <h1>Welcome, <?php echo $username; ?> 🎮</h1>
        <p>Compete. Manage. Dominate the Tournament Arena.</p>

        <div class="hero-buttons">
            <a href="createTournament.php" class="btn primary">Create Tournament</a>
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

<?php
include 'footer.php';
?>