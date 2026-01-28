<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection (assuming it's included via your config)
require_once "../database/dbConfig.php";

$isLoggedIn = isset($_SESSION['user_id']);
$uid = $_SESSION['user_id'] ?? null;

if ($isLoggedIn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
}

// Get current page filename for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TournaX | Home</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.lordicon.com/lordicon.js"></script>

    <style>
        /* ======== ROOT VARIABLES ======== */
        :root {
            --accent-blue: #3d8bff;
            --bg-dark: #06090f;
            --sidebar-bg: rgba(10, 15, 25, 0.95);
            --text-light: #ffffff;
            --text-dim: rgba(255, 255, 255, 0.5);
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --surface: rgba(15, 20, 35, 0.95);
            --riot-red: #ff4655;
        }

        /* ======== RESET & BASE ======== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            background-image: radial-gradient(circle at 50% 50%, #0d1a30 0%, #06090f 100%);
            color: var(--text-light);
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* ======== SIDEBAR (Riot Style) ======== */
        .sidebar {
            width: 80px;
            background: var(--sidebar-bg);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(61, 139, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }

        .logo-container {
            width: 50px;
            height: 50px;
            margin-bottom: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 800px;
        }

        .side-logo {
            width: 100%;
            transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            filter: drop-shadow(0 0 5px var(--accent-blue));
        }

        .logo-container:hover .side-logo {
            transform: rotateY(360deg) scale(1.1);
            filter: drop-shadow(0 0 15px var(--accent-blue));
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 0px;
            width: 100%;
            align-items: center;
        }

        .sidebar nav a {
            color: var(--text-dim);
            font-size: 18px;
            transition: var(--transition);
            position: relative;
            width: 100%;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
        }

        .sidebar nav a:hover {
            color: var(--text-light);
            background: rgba(255, 255, 255, 0.05);
        }

        /* Active State */
        .sidebar nav a.active {
            color: var(--accent-blue);
            background: rgba(61, 139, 255, 0.1);
        }

        /* Active indicator line */
        .sidebar nav a.active::after {
            content: '';
            position: absolute;
            left: 0;
            top: 15%;
            width: 3px;
            height: 70%;
            background: var(--accent-blue);
            box-shadow: 0 0 10px var(--accent-blue);
        }

        /* ======== HEADER & DROPDOWN ======== */
        .header {
            position: fixed;
            top: 0;
            left: 80px;
            right: 0;
            height: 75px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 0 40px;
            z-index: 999;
        }

        .auth-wrapper { position: relative; }

        .auth-trigger {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .auth-trigger:hover {
            border-color: var(--accent-blue);
            box-shadow: 0 0 15px rgba(61, 139, 255, 0.3);
        }

        .user-img { width: 100%; height: 100%; object-fit: cover; }

        .riot-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            width: 240px;
            background: #0a1428;
            border: 1px solid #1e2328;
            display: none;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            animation: menuIn 0.2s ease-out;
        }

        .riot-dropdown.show { display: flex; }

        @keyframes menuIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .drop-info {
            padding: 20px;
            border-bottom: 1px solid #1e2328;
            text-align: center;
        }

        .drop-links a {
            padding: 15px 20px;
            display: block;
            text-decoration: none;
            color: #cdbe91; /* Hextech Gold color */
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
            transition: 0.2s;
            text-transform: uppercase;
        }

        .drop-links a:hover {
            background: rgba(61, 139, 255, 0.1);
            color: #fff;
        }

        /* Hero Content Just for visualization */
        .main-content {
            margin-left: 80px;
            padding-top: 100px;
            text-align: center;
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="logo-container">
            <img src="../images/TX blue.png" class="side-logo" alt="TX">
        </div>
        <nav>
            <a href="organizerDashboard.php" class="<?= ($current_page == 'organizerDashboard.php' || $current_page == '') ? 'active' : '' ?>" title="Home">
                <i class="fas fa-home"></i>
            </a>
            
            <a href="tournaments.php" class="<?= ($current_page == 'tournaments.php') ? 'active' : '' ?>" title="Tournaments">
                <i class="fas fa-trophy"></i>
            </a>
            
            <a href="teams.php" class="<?= ($current_page == 'teams.php') ? 'active' : '' ?>" title="Teams">
                <i class="fas fa-users"></i>
            </a>
            
            <a href="stats.php" class="<?= ($current_page == 'stats.php') ? 'active' : '' ?>" title="Stats">
                <i class="fas fa-chart-bar"></i>
            </a>
            
            <a href="settings.php" class="<?= ($current_page == 'settings.php') ? 'active' : '' ?>" title="Settings">
                <i class="fas fa-cog"></i>
            </a>
        </nav>
    </aside>

    <header class="header">
        <div class="auth-wrapper">
            <div class="auth-trigger" onclick="toggleUserMenu()">
                <?php if ($isLoggedIn): ?>
                    <img src="../images/<?= htmlspecialchars($user['image'] ?: 'default.png') ?>" class="user-img">
                <?php else: ?>
                    <lord-icon src="https://cdn.lordicon.com/kthelypq.json" trigger="hover" colors="primary:#ffffff" style="width:30px;height:30px"></lord-icon>
                <?php endif; ?>
            </div>

            <div id="userDropdown" class="riot-dropdown">
                <?php if ($isLoggedIn): ?>
                    <div class="drop-info">
                        <h4 style="color: var(--accent-blue);"><?= htmlspecialchars($user['username']) ?></h4>
                        <p style="font-size: 10px; opacity: 0.5;"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                    <div class="drop-links">
                        <a href="userprofile.php">Customize Profile</a>
                        <a href="../logout.php" style="color: var(--riot-red)">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="drop-links">
                        <a href="login.php">Login</a>
                        <a href="signUp.php">Create Account</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="main-content">
        </main>

    <script>
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const trigger = document.querySelector('.auth-trigger');
            if (dropdown && trigger && !trigger.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>