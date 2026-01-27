<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/init.php";

$isLoggedIn = isset($_SESSION['user_id']);
$uid = $_SESSION['user_id'] ?? null;

if ($isLoggedIn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
}

// ✅ Detect current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TournaX — Elite Esports</title>

<script src="https://cdn.lordicon.com/lordicon.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">

<style>
:root {
    --riot: #ff4655;
    --bg: #06080f;
    --surface: #11141d;
    --sidebar-w: 85px;
    --transition: all 0.25s ease;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: #fff;
    overflow-x: hidden;
}

canvas#bg {
    position: absolute;
    inset: 0;
    z-index: -3;
}

/* ================= SIDEBAR ================= */

.tx-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: var(--sidebar-w);
    background: black;
    border-right: 1px solid rgba(255,255,255,0.08);
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
    perspective: 800px;
}

.logo-container:hover .side-logo {
    transform: rotateY(360deg) rotateX(15deg) scale(1.1);
    filter: drop-shadow(0 0 18px rgba(255, 70, 85, 0.8));
}

.side-logo {
    width: 100%;
    transform-style: preserve-3d;
    transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.nav-stack {
    display: flex;
    flex-direction: column;
    gap: 28px;
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

.nav-item:hover, .nav-item.active { 
    opacity: 1; 
}

/* 🔥 ACTIVE INDICATOR */
.nav-item.active::before {
    content: '';
    position: absolute;
    left: -25px;
    width: 4px;
    height: 24px;
    background: var(--riot);
    box-shadow: 0 0 15px var(--riot);
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
    border-left: 2px solid var(--riot);
}

.nav-item:hover span, .nav-item.active:hover span {
    opacity: 1;
    transform: translateX(0);
    color: #fff;
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
    background-color: transparent;
    z-index: 1000;
}

.auth-wrapper { position: relative; }

.auth-trigger {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.1);
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: 0.3s;
}

.auth-trigger:hover {
    border-color: var(--riot);
    transform: scale(1.1);
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
    border: 1px solid rgba(255,255,255,0.1);
    display: none;
    flex-direction: column;
    animation: menuIn 0.2s ease-out;
}

.riot-dropdown.show { display: flex; }

@keyframes menuIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.drop-info {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    text-align: center;
}

.drop-links a {
    padding: 15px 20px;
    display: block;
    text-decoration: none;
    color: #bbb;
    font-size: 13px;
    transition: 0.2s;
}

.drop-links a:hover {
    background: rgba(255,70,85,0.1);
    color: #fff;
}

/* ================= MOBILE ================= */

@media (max-width: 768px) {
    :root { --sidebar-w: 0px; }

    .tx-sidebar {
        flex-direction: row;
        height: 70px;
        width: 100%;
        bottom: 0;
        top: auto;
        border-right: none;
        border-top: 1px solid rgba(255,255,255,0.08);
        padding: 0;
        justify-content: space-around;
    }

    .logo-container { display: none; }

    .nav-stack {
        flex-direction: row;
        width: 100%;
        justify-content: space-around;
    }

    .nav-item.active::before {
        left: 50%;
        bottom: -2px;
        top: auto;
        width: 20px;
        height: 3px;
        transform: translateX(-50%);
    }

    .nav-item span {
        left: 50%;
        bottom: 45px;
        transform: translateX(-50%) translateY(5px);
    }

    .nav-item:hover span {
        transform: translateX(-50%) translateY(0);
    }

    .tx-header { left: 0; }
}
</style>
</head>

<body>

<aside class="tx-sidebar">
    <a href="index.php" class="logo-container" id="logoAnimContainer">
        <img src="images/TX red.png" class="side-logo" alt="TX">
    </a>

    <nav class="nav-stack">
        <a href="index.php" class="nav-item <?= ($current_page == 'index.php' || $current_page == '') ? 'active' : '' ?>">
            <lord-icon src="https://cdn.lordicon.com/wmwqvixz.json" trigger="hover" 
                colors="primary:<?= ($current_page == 'index.php' || $current_page == '') ? '#ff4655' : '#ffffff' ?>" style="width:32px;height:32px">
            </lord-icon>
            <span>HOME</span>
        </a>

        <a href="tournament.php" class="nav-item <?= ($current_page == 'tournament.php') ? 'active' : '' ?>">
            <lord-icon src="https://cdn.lordicon.com/dxjqoygy.json" trigger="hover" 
                colors="primary:<?= ($current_page == 'tournament.php') ? '#ff4655' : '#ffffff' ?>" style="width:32px;height:32px">
            </lord-icon>
            <span>TOURNAMENTS</span>
        </a>

        <a href="teams.php" class="nav-item <?= ($current_page == 'teams.php') ? 'active' : '' ?>">
            <lord-icon src="https://cdn.lordicon.com/mrdiiocb.json" trigger="hover" 
                colors="primary:<?= ($current_page == 'teams.php') ? '#ff4655' : '#ffffff' ?>" style="width:32px;height:32px">
            </lord-icon>
            <span>TEAMS</span>
        </a>

        <a href="leaderboard.php" class="nav-item <?= ($current_page == 'leaderboard.php') ? 'active' : '' ?>">
            <lord-icon src="https://cdn.lordicon.com/hjeefwhm.json" trigger="hover" 
                colors="primary:<?= ($current_page == 'leaderboard.php') ? '#ff4655' : '#ffffff' ?>" style="width:32px;height:32px">
            </lord-icon>
            <span>LEADERBOARD</span>
        </a>

        <a href="contact.php" class="nav-item <?= ($current_page == 'contact.php') ? 'active' : '' ?>">
            <lord-icon src="https://cdn.lordicon.com/hpivxauj.json" trigger="hover" 
                colors="primary:<?= ($current_page == 'contact.php') ? '#ff4655' : '#ffffff' ?>" style="width:32px;height:32px">
            </lord-icon>
            <span>CONTACT US</span>
        </a>
    </nav>

    <div class="nav-item" style="margin-top: auto; margin-bottom: 20px;">
        <lord-icon src="https://cdn.lordicon.com/lecfrjdy.json" trigger="hover" colors="primary:#ffffff" style="width:32px;height:32px"></lord-icon>
    </div>
</aside>

<header class="tx-header">
    <div class="auth-wrapper">
        <div class="auth-trigger" onclick="toggleUserMenu()">
            <?php if ($isLoggedIn): ?>
                <img src="images/<?= htmlspecialchars($user['image'] ?: 'default.png') ?>" class="user-img">
            <?php else: ?>
                <lord-icon src="https://cdn.lordicon.com/kthelypq.json" trigger="hover" colors="primary:#ffffff" style="width:35px;height:35px"></lord-icon>
            <?php endif; ?>
        </div>

        <div id="userDropdown" class="riot-dropdown">
            <?php if ($isLoggedIn): ?>
                <div class="drop-info">
                    <h4 style="color: var(--riot);"><?= htmlspecialchars($user['username']) ?></h4>
                    <p style="font-size: 10px; opacity: 0.5;"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <div class="drop-links">
                    <a href="userprofile.php">CUSTOMIZE PROFILE</a>
                    <a href="#">MY TEAM</a>
                    <a href="changePassword.php">CHANGE PASSWORD</a>
                    <a href="logout.php" style="color: var(--riot)">LOGOUT</a>
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
        if(dropdown) dropdown.classList.remove('show');
    }
}
</script>

</body>
</html>