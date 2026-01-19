<?php
require_once __DIR__ . "/init.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/user/style.css">
    <link rel="stylesheet" href="css/user/login.css">
    <link rel="stylesheet" href="css/user/footer.css">
    <link rel="stylesheet" href="css/user/header.css">
    <link rel="stylesheet" href="css/user/FAQs.css">
    <link rel="stylesheet" href="css/user/aboutUs.css">
    <link rel="stylesheet" href="css/user/tournament.css">
    <link rel="stylesheet" href="css/user/strike.css">
    <link rel="stylesheet" href="css/user/loading.css">
    <link rel="stylesheet" href="css/user/responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/examples/js/postprocessing/EffectComposer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/examples/js/postprocessing/RenderPass.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/examples/js/postprocessing/UnrealBloomPass.js"></script>

    <title>Game Tournament</title>
</head>

<body>

    <div class="progress-container">
        <div class="progress-bar" id="bar"></div>
    </div>

    <header class="legacy-header">
        <img src="images/TX.png" alt="" class="legacy-mainlogo">
        <nav class="legacy-headnav">
            <a href="index.php">Home</a>
            <a href="tournament.php">Tournaments</a>
            <div class="help-container">
                <a>Help & Info <i class="fas fa-caret-down"></i></a>
                <div class="HelpInfo">
                    <a href="aboutUs.php">About Us</a>
                    <a href="FAQS.php">FAQs</a>
                </div>
            </div>
            <a href="#">Contact</a>
        </nav>
        <?php
        if (isset($_SESSION['user_id'])) :
            $uid = $_SESSION['user_id'];

            $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? AND is_organizer = 0");
            mysqli_stmt_bind_param($stmt, "i", $uid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
        ?>
            <nav class="legacy-signnav">
                <a>
                    <img src="images/<?= htmlspecialchars($user['image'] ?: 'default.png') ?>" alt="" class="profilegif" onclick="toggleDropdown()" />
                </a>
                <a href="logout.php" class="btn-primary">LogOut</a>
            </nav>
        <?php  else : ?>
            <nav class="legacy-signnav">
                <a href="login.php">Login</a>
                <a href="#" class="btn-primary">Join Now</a>
            </nav>
        <?php endif; ?>
    </header>
    <!-- Mobile header panel (toggles when TX logo clicked on small screens) -->
    <div id="mobileHeaderPanel" class="mobile-header-panel" aria-hidden="true">
        <nav class="mobile-header-nav">
            <a href="index.php">Home</a>
            <a href="tournament.php">Tournaments</a>
            <a href="aboutUs.php">About Us</a>
            <a href="FAQS.php">FAQs</a>
            <a href="#">Contact</a>
            <!-- Login/Join remain in the top-right `legacy-signnav`; not duplicated here -->
        </nav>
    </div>
    <div id="mobileHeaderOverlay" class="mobile-header-overlay" tabindex="-1"></div>
    <?php if(isset($_SESSION["user_id"])): ?>
    <div class="profile-container">

        <div id="profileDropdown" class="dropdown-menu"
            onmouseenter="disableScroll()"
            onmouseleave="enableScroll()">
            <div class="profile-header">
                <img src="images/<?= $user['image'] ?>" alt="" class="large-avatar">
                <div class="user-info">
                    <span class="name"><?= $user['username'] ?></span>
                    <span class="email"><?= $user['email'] ?></span>
                </div>
            </div>

            <ul class="menu-list">
                <li><span class="icon">🔑</span> <a href="">Change Password</a></li>
                <li><span class="icon">⚙️</span> <a href="">Change Email</a></li>
                <li><span class="icon">G</span> <a href="">My Team</a></li>
                <li><span class="icon">✏️</span> <a href="userprofile.php">Customize profile</a></li>
                <li><span class="icon">🔄</span> <a href="">Inbox</a></li>
            </ul>

            <hr class="divider">

            <ul class="menu-list">
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <script>
        console.log("Dropdown script loaded");
        function toggleDropdown() {
            document.getElementById("profileDropdown").classList.toggle("show");
        }

        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
            if (!event.target.closest('.profile-container')) {
                var dropdowns = document.getElementsByClassName("dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>

    <script>
        function disableScroll() {
            // This stops the main page from scrolling
            document.body.style.overflow = 'hidden';
        }

        function enableScroll() {
            // This allows the main page to scroll again
            document.body.style.overflow = 'auto';
        }
    </script>

    <script>
        window.addEventListener("scroll", () => {
            const header = document.querySelector(".legacy-header");

            if (window.scrollY > 50) {
                header.classList.add("scrolled");
            } else {
                header.classList.remove("scrolled");
            }
        });
    </script>

    <script>
        // Mobile header toggle: clicking the TX logo shows/hides header links on small screens
        (function(){
            const logo = document.querySelector('.legacy-mainlogo');
            const panel = document.getElementById('mobileHeaderPanel');
            const overlay = document.getElementById('mobileHeaderOverlay');

            function open(){
                document.body.classList.add('mobile-header-open');
                panel && panel.setAttribute('aria-hidden','false');
            }
            function close(){
                document.body.classList.remove('mobile-header-open');
                panel && panel.setAttribute('aria-hidden','true');
            }

            if(logo){
                logo.addEventListener('click', function(e){
                    // only toggle on small screens
                    if(window.innerWidth <= 768){
                        if(document.body.classList.contains('mobile-header-open')) close(); else open();
                    }
                });
            }

            overlay && overlay.addEventListener('click', close);
            document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
        })();
    </script>