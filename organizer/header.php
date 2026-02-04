<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once  "../partial/init.php";

$isLoggedIn = isset($_SESSION['user_id']);
$uid = $_SESSION['user_id'] ?? null;

if ($isLoggedIn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TournaX — Elite Esports</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: #00d4ff;
            --bg: #06080f;
            --surface: #11141d;
            --sidebar-w: 85px;
            --transition: all 0.25s ease;
        }

        ::-webkit-scrollbar {
            display: none;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: #fff;
            overflow-x: hidden;
        }

        /* ================= SIDEBAR ================= */
        .tx-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-w);
            background: black;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 25px 0;
            z-index: 2000;
        }

        .logo-container {
            width: 60px;
            height: 60px;
            margin-bottom: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .side-logo {
            width: 100%;
            transition: 0.3s;
        }

        .logo-container:hover .side-logo {
            filter: drop-shadow(0 0 10px var(--accent));
        }

        .nav-stack {
            display: flex;
            flex-direction: column;
            gap: 35px;
            flex-grow: 1;
        }

        .nav-item {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            opacity: 0.6;
            transition: var(--transition);
        }

        .nav-item i {
            font-size: 22px;
            transition: var(--transition);
        }

        .nav-item:hover,
        .nav-item.active {
            opacity: 1;
        }

        .nav-item.active i {
            color: var(--accent) !important;
        }

        /* ACTIVE INDICATOR */
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: -25px;
            width: 4px;
            height: 24px;
            background: var(--accent);
            box-shadow: 0 0 15px var(--accent);
        }

        .nav-item span {
            position: absolute;
            left: 45px;
            background: var(--surface);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            white-space: nowrap;
            opacity: 0;
            transform: translateX(-10px);
            pointer-events: none;
            transition: var(--transition);
            border-left: 2px solid var(--accent);
        }

        .nav-item:hover span {
            opacity: 1;
            transform: translateX(0);
        }

        /* ================= HEADER ================= */
        .tx-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: 75px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 0 40px;
            z-index: 1000;
        }

        .auth-trigger {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.1);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: 0.3s;
        }

        .auth-trigger:hover {
            border-color: var(--accent);
        }

        .user-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .riot-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            width: 260px;
            background: var(--surface);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: none;
            flex-direction: column;
        }

        .riot-dropdown.show {
            display: flex;
        }

        .drop-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
        }

        .drop-links a {
            padding: 15px 20px;
            display: block;
            text-decoration: none;
            color: #bbb;
            font-size: 13px;
        }

        .drop-links a:hover {
            background: rgba(0, 212, 255, 0.1);
            color: #fff;
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-w: 0px;
            }

            .tx-sidebar {
                flex-direction: row;
                height: 70px;
                width: 100%;
                bottom: 0;
                top: auto;
                border-right: none;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                justify-content: space-around;
                padding: 0;
            }

            .logo-container {
                display: none;
            }

            .nav-stack {
                flex-direction: row;
                width: 100%;
                justify-content: space-around;
                margin: 0;
            }

            .nav-item.active::before {
                left: 50%;
                bottom: -2px;
                top: auto;
                width: 20px;
                height: 3px;
                transform: translateX(-50%);
            }

            .tx-header {
                left: 0;
            }
        }
    </style>
</head>

<body>

    <aside class="tx-sidebar">
        <a href="organizerDashboard.php" class="logo-container">
            <img src="../images/TX blue.png" class="side-logo" alt="TX">
        </a>

        <nav class="nav-stack">
            <a href="organizerDashboard.php" class="nav-item <?= ($current_page == 'organizerDashboard.php' || $current_page == '') ? 'active' : '' ?>">
                <i class="fa-solid fa-house" style="color: #fff;"></i>
                <span>HOME</span>
            </a>

            <a href="manageTournament.php" class="nav-item <?= ($current_page == 'manageTournament.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-trophy" style="color: #fff;"></i>
                <span>TOURNAMENTS</span>
            </a>

            <a href="teams.php" class="nav-item <?= ($current_page == 'teams.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-users" style="color: #fff;"></i>
                <span>TEAMS</span>
            </a>

            <a href="leaderboard.php" class="nav-item <?= ($current_page == 'leaderboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-ranking-star" style="color: #fff;"></i>
                <span>LEADERBOARD</span>
            </a>

            <a href="contact.php" class="nav-item <?= ($current_page == 'contact.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-envelope" style="color: #fff;"></i>
                <span>CONTACT US</span>
            </a>
        </nav>

        <div class="nav-item" style="margin-top: auto; margin-bottom: 20px;">
            <i class="fa-solid fa-gear" style="color: #fff;"></i>
        </div>
    </aside>

    <header class="tx-header">
        <div class="auth-wrapper" style="position: relative;">
            <div class="auth-trigger" onclick="toggleUserMenu()">
                <?php if ($isLoggedIn): ?>
                    <img src="../images/<?= htmlspecialchars($user['image'] ?: 'default.png') ?>" class="user-img">
                <?php else: ?>
                    <i class="fa-solid fa-circle-user" style="font-size: 30px; color: #fff;"></i>
                <?php endif; ?>
            </div>

            <div id="userDropdown" class="riot-dropdown">
                <?php if ($isLoggedIn): ?>
                    <div class="drop-info">
                        <h4 style="color: var(--accent);"><?= htmlspecialchars($user['username']) ?></h4>
                        <p style="font-size: 10px; opacity: 0.5;"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                    <div class="drop-links">
                        <a href="userprofile.php">CUSTOMIZE PROFILE</a>
                        <a href="changePassword.php">CHANGE PASSWORD</a>
                        <a href="logout.php" style="color: var(--accent)">LOGOUT</a>
                    </div>
                <?php else: ?>
                    <div class="drop-links">
                        <a href="login.php">LOGIN</a>
                        <a href="signUp.php">CREATE ACCOUNT</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script>
        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
        }
        window.onclick = function(e) {
            if (!e.target.closest('.auth-wrapper')) {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) dropdown.classList.remove('show');
            }
        }
    </script>

</body>

</html>