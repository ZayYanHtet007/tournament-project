<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../css/admin.css">
</head>

<body>
    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    /*if(!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }*/

    $adminName = $_SESSION['admin_name'] ?? 'Admin User';
    $adminEmail = $_SESSION['admin_email'] ?? 'admin@gmail.com';
    $adminInitial = substr($adminName, 0, 1);
    ?>

    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fa fa-bars"></i>
    </button>

    <div class="wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Tournament Admin</h2>
            </div>

            <div class="sidebar-menu">
   <a href="adminDashboard.php" class="<?= ($current_page == 'adminDashboard.php') ? 'active' : '' ?>">
        <i class="fa fa-chart-line"></i> Dashboard
    </a>
    <a href="players.php" class="<?= ($current_page == 'players.php') ? 'active' : '' ?>">
        <i class="fa fa-users"></i> Players
    </a>
    <a href="tournaments.php" class="<?= ($current_page == 'tournaments.php') ? 'active' : '' ?>">
        <i class="fa fa-trophy"></i> Tournaments
    </a>
    <a href="post.php" class="<?= ($current_page == 'post.php') ? 'active' : '' ?>">
        <i class="fa fa-pen-to-square"></i> Post
    </a>
    <a href="organizers.php" class="<?= ($current_page == 'organizers.php') ? 'active' : '' ?>">
        <i class="fa fa-user-check"></i> Organizers
    </a>
    <a href="message.php" class="<?= ($current_page == 'message.php') ? 'active' : '' ?>">
        <i class="fa fa-envelope"></i> Message
    </a>
            </div>

            <div class="profile-popup" id="profilePopup">
                <div class="popup-header">
                    <div class="popup-avatar-large"><?= $adminInitial ?></div>
                    <div class="popup-info">
                        <h4>Hi, <?= $adminName ?>!</h4>
                        <a href="customizeProfile.php" class="manage-btn" style="text-decoration: none;">Customize Profile</a>
                    </div>
                </div>
                <div class="popup-body">
                    <div class="popup-item"><a href="changePassword.php" style="text-decoration: none; color: #d1d1e6;"><i class="fa fa-lock"></i><span>Change Password</span></a></div>
                    <hr style="border-color: #45455e; margin: 5px 0;">
                    <a href="logout.php" class="popup-item logout-link">
                        <i class="fa fa-sign-out-alt"></i><span>Sign out</span>
                    </a>
                </div>
            </div>

            <div class="admin_profile" onclick="togglePopup(event)">
                <div class="admin_avatar"><?= $adminInitial ?></div>
                <div class="profile_content">
                    <div class="name"><?= $adminName ?></div>
                    <div class="email"><?= $adminEmail ?></div>
                </div>
                <i class="fa fa-ellipsis-v" style="margin-left: auto; color: #94a3b8; font-size: 12px;"></i>
            </div>
        </nav>

        <div class="main">
            <header class="header">
                <h3>Tournaments</h3>
                <div class="admin-name"><?= $adminName  ?></div>
            </header>

            <div class="main-content">

                <script>
                    function toggleSidebar() {
                        document.getElementById('sidebar').classList.toggle('active');
                    }

                    function togglePopup(event) {
                        event.stopPropagation();
                        document.getElementById('profilePopup').classList.toggle('show');
                    }

                    window.onclick = function(e) {
                        if (!document.querySelector('.sidebar').contains(e.target)) {
                            document.getElementById('profilePopup').classList.remove('show');
                        }
                        
                        if (window.innerWidth <= 768 && !document.getElementById('sidebar').contains(e.target) && !e.target.closest('.mobile-toggle')) {
                            document.getElementById('sidebar').classList.remove('active');
                        }
                    };
                </script>
