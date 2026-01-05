    <?php
    session_start();
    require_once "../database/dbConfig.php"; // include if you need DB later

    // ===== ACCESS CONTROL =====
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_organizer'])) {
        // user is not logged in
        header("Location: ../login.php");
        exit;
    }

    if ($_SESSION['is_organizer']!= 1){
        header("Location: ../login.php");
        exit;
    }
    
    // Fetch organizer username from session
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Organizer';

    $isLoggedIn = true;
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

        <!-- Background Effects -->
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

        <!-- Neon Lines -->
        <div class="neon-line line-1"></div>
        <div class="neon-line line-2"></div>

        <header class="legacy-header">
            <div class="legacy-logo">Tourna<span>X</span></div>

            <nav class="legacy-headnav">
                <a href="#">Tournament</a>
                <a href="#">Post</a>
                <a href="#">Report</a>
            </nav>

            <nav class="legacy-signnav">
                <?php if ($isLoggedIn): ?>
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

        <footer class="footer">
            © 2025 TournaX. All rights reserved.
        </footer>

    </body>

    </html>
