<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../database/dbConfig.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }

    // Fetch latest 20 notifications with tournament_id
    $stmt = $conn->prepare("SELECT * FROM admin_notifications WHERE admin_id=? ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Count unread from DB
    $unreadCount = array_sum(array_map(fn($n) => $n['is_read'] == 0 ? 1 : 0, $notifications));

    $adminName = $_SESSION['admin_name'] ?? 'Admin User';
    $adminEmail = $_SESSION['admin_email'] ?? 'admin@gmail.com';
    $adminImg = $_SESSION['admin_img'] ?? 'default_profile.png';

    $imageSource = '../images/upload_photos/' . $adminImg;
    if (!file_exists(__DIR__ . '/' . $imageSource) && $adminImg !== 'default_profile.png') {
        $imageSource = '../images/default_profile.png';
    } elseif ($adminImg === 'default.jpg') {
        $imageSource = '../images/default.jpg';
    }
    ?>

    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fa fa-bars"></i>
    </button>

    <div class="wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>TournaX</h2>
            </div>

            <div class="sidebar-menu">
                <a href="adminDashboard.php"><i class="fa fa-chart-line"></i> Dashboard</a>
                <a href="players.php"><i class="fa fa-users"></i> Players</a>
                <a href="tournaments.php"><i class="fa fa-trophy"></i> Tournaments</a>
                <a href="#post.php"><i class="fa fa-pen-to-square"></i> Post</a>
                <a href="organizers.php"><i class="fa fa-user-check"></i> Organizers</a>
                <a href="#message.php"><i class="fa fa-envelope"></i> Message</a>
            </div>

            <div class="profile-popup" id="profilePopup">
                <div class="popup-header">
                    <div class="popup-avatar-large">
                        <img src="<?php echo $imageSource; ?>" alt="<?php echo htmlspecialchars($adminName); ?>"
                            onerror="this.src='../images/default_profile.png'">
                    </div>
                    <div class="popup-info">
                        <h4>Hi, <?= $adminName ?>!</h4>
                        <h4><?= $adminEmail ?></h4>
                        <a href="customizeProfile.php" class="manage-btn" style="text-decoration: none;">Customize Profile</a>
                    </div>
                </div>
                <div class="popup-body">
                    <div class="popup-item"><a href="changePassword.php" style="text-decoration: none; color: #d1d1e6;"><i class="fa fa-lock"></i><span>Change Password</span></a></div>
                    <hr style="border-color: #45455e; margin: 5px 0;">
                    <a href="signOut.php" class="popup-item logout-link">
                        <i class="fa fa-sign-out-alt"></i><span>Sign out</span>
                    </a>
                </div>
            </div>

            <div class="admin_profile" onclick="togglePopup(event)">
                <div class="profile_content">
                    account
                </div>
            </div>
        </nav>

        <div class="main">
            <header class="header">
                <h3>Tournaments</h3>
                <div class="header-actions">

                    <button id="theme-toggle" class="theme-toggle">
                        <i class="fa-solid fa-sun"></i>
                    </button>
                    <div class="notification-dropdown">
                        <?php
                        $unread_count = 0;
                        if (!empty($notifications)) {
                            foreach ($notifications as $n) {
                                if (!$n['is_read']) {
                                    $unread_count++;
                                }
                            }
                        }
                        ?>

                        <button class="noti-btn" id="notiBtn">
                            <i class="fa-regular fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="noti-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="noti-content" id="notiContent">
                            <div class="noti-header">
                                Notifications
                                <?php if ($unread_count > 0): ?>
                                    <span style="font-size: 11px; color: #64748b; font-weight: normal;">(<?php echo $unread_count; ?> unread)</span>
                                <?php endif; ?>
                            </div>

                            <div class="noti-body" style="max-height: 300px; overflow-y: auto;">
                                <?php if (empty($notifications)): ?>
                                    <div class="noti-item" style="cursor: default;">
                                        <div class="noti-text" style="width: 100%; text-align: center; padding: 10px;">
                                            <p style="color: #94a3b8;">No new notifications</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $n):

                                        $tournament_id = 0;
                                        if (preg_match('/tournament ID #(\d+)/i', $n['message'], $matches)) {
                                            $tournament_id = $matches[1];
                                        }
                                    ?>
                                        <div class="noti-item <?php echo $n['is_read'] ? '' : 'unread'; ?>"
                                            data-id="<?php echo $n['notification_id']; ?>"
                                            data-tournament-id="<?php echo $tournament_id; ?>">
                                            <div class="noti-icon">
                                                <i class="fa-solid fa-info"></i>
                                            </div>
                                            <div class="noti-text">
                                                <p>
                                                    <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                                                    <br>
                                                    <?php echo htmlspecialchars($n['message']); ?>
                                                </p>
                                                <small>
                                                    <i class="fa-regular fa-clock" style="margin-right: 3px;"></i>
                                                    <?php echo date('M d, h:i A', strtotime($n['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="main-content">



                <script>
                    const ws = new WebSocket("ws://localhost:5000");

                    // --- Real-time notifications ---
                    ws.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        const notiBody = document.querySelector('.noti-body');

                        // Extract tournament ID from message
                        let tournamentId = 0;
                        const tournamentMatch = data.message.match(/tournament ID #(\d+)/i);
                        if (tournamentMatch && tournamentMatch[1]) {
                            tournamentId = tournamentMatch[1];
                        }

                        // Create new notification div
                        const div = document.createElement("div");
                        div.className = "noti-item unread";
                        div.dataset.id = data.id;
                        div.dataset.tournamentId = tournamentId;
                        div.innerHTML = `
                <div class="noti-icon">
                    <i class="fa-solid fa-info"></i>
                </div>
                <div class="noti-text">
                    <p>
                        <strong>${data.title}</strong>
                        <br>
                        ${data.message}
                    </p>
                    <small>
                        <i class="fa-regular fa-clock" style="margin-right: 3px;"></i>
                        ${data.created_at}
                    </small>
                </div>
            `;

                        // Add to top of notifications
                        notiBody.prepend(div);

                        // Remove "No new notifications" message if present
                        const noNotiMsg = notiBody.querySelector('.noti-item p[style*="color: #94a3b8"]');
                        if (noNotiMsg && noNotiMsg.textContent.includes("No new notifications")) {
                            noNotiMsg.closest('.noti-item').remove();
                        }

                        // Update unread count
                        const badge = document.querySelector('.noti-badge');
                        if (badge) {
                            badge.textContent = parseInt(badge.textContent) + 1;
                        } else {
                            // Create badge if it doesn't exist
                            const notiBtn = document.querySelector('.noti-btn');
                            const newBadge = document.createElement('span');
                            newBadge.className = 'noti-badge';
                            newBadge.textContent = '1';
                            notiBtn.appendChild(newBadge);
                        }

                        // Update unread text in header
                        const unreadSpan = document.querySelector('.noti-header span');
                        if (unreadSpan) {
                            const current = parseInt(unreadSpan.textContent.match(/\d+/)[0]) || 0;
                            unreadSpan.textContent = `(${current + 1} unread)`;
                        } else {
                            // Create unread span if it doesn't exist
                            const notiHeader = document.querySelector('.noti-header');
                            const newSpan = document.createElement('span');
                            newSpan.style.fontSize = '11px';
                            newSpan.style.color = '#64748b';
                            newSpan.style.fontWeight = 'normal';
                            newSpan.textContent = `(1 unread)`;
                            notiHeader.appendChild(newSpan);
                        }
                    };

                    // --- Sidebar & Profile Popup ---
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

                    document.addEventListener('DOMContentLoaded', function() {
                        const notiBtn = document.getElementById('notiBtn');
                        const notiContent = document.getElementById('notiContent');

                        // Toggle dropdown
                        notiBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            notiContent.classList.toggle('show');
                        });

                        // Close when clicking outside
                        document.addEventListener('click', function(e) {
                            if (!notiContent.contains(e.target) && !notiBtn.contains(e.target)) {
                                notiContent.classList.remove('show');
                            }
                        });

                        // --- Handle notification click (mark as read & redirect) ---
                        const notiBody = document.querySelector('.noti-body');
                        notiBody.addEventListener('click', async (e) => {
                            const notiItem = e.target.closest('.noti-item');
                            if (!notiItem) return;


                            if (notiItem.querySelector('p[style*="color: #94a3b8"]')) {
                                return;
                            }

                            const id = notiItem.dataset.id;
                            const tournamentId = notiItem.dataset.tournamentId;
                            const isUnread = notiItem.classList.contains('unread');

                            // Mark as read if unread
                            if (isUnread) {
                                try {
                                    const response = await fetch(`mark-read.php?id=${id}`);
                                    if (response.ok) {
                                        notiItem.classList.remove('unread');

                                        // Update unread count
                                        const badge = document.querySelector('.noti-badge');
                                        if (badge) {
                                            const current = parseInt(badge.textContent);
                                            if (current > 1) {
                                                badge.textContent = current - 1;
                                            } else {
                                                badge.remove();
                                            }
                                        }

                                        // Update unread text
                                        const unreadSpan = document.querySelector('.noti-header span');
                                        if (unreadSpan) {
                                            const match = unreadSpan.textContent.match(/\d+/);
                                            if (match) {
                                                const current = parseInt(match[0]);
                                                if (current > 1) {
                                                    unreadSpan.textContent = `(${current - 1} unread)`;
                                                } else {
                                                    unreadSpan.textContent = '';
                                                    unreadSpan.remove();
                                                }
                                            }
                                        }
                                    }
                                } catch (error) {
                                    console.error('Error marking notification as read:', error);
                                }
                            }

                            // Redirect to tournament detail if tournament ID exists
                            if (tournamentId && tournamentId > 0) {
                                window.location.href = `tournamentsDetail.php?id=${tournamentId}`;
                            } else {
                                // If no tournament ID found, try to extract from notification text
                                const notificationText = notiItem.querySelector('.noti-text p').textContent;
                                const tournamentMatch = notificationText.match(/tournament ID #(\d+)/i);

                                if (tournamentMatch && tournamentMatch[1]) {
                                    window.location.href = `tournamentDetail.php?id=${tournamentMatch[1]}`;
                                } else {
                                    // If still no tournament ID, show alert or stay on page
                                    console.log('No tournament ID found in notification');

                                }
                            }
                        });
                    });

                    // Dark mode and light mode toggle
                    document.addEventListener('DOMContentLoaded', () => {
                        const themeToggle = document.getElementById('theme-toggle');
                        const themeIcon = themeToggle.querySelector('i');

                        // Get saved theme or default to 'light'
                        const savedTheme = localStorage.getItem('theme');
                        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                        // Determine initial theme
                        let currentTheme = savedTheme || (prefersDark ? 'dark' : 'light');

                        // Apply theme on load
                        applyTheme(currentTheme);

                        themeToggle.addEventListener('click', () => {
                            // Toggle between light and dark
                            const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                            applyTheme(newTheme);
                            localStorage.setItem('theme', newTheme);
                        });

                        function applyTheme(theme) {
                            document.documentElement.setAttribute('data-theme', theme);

                            // Update icon
                            if (theme === 'dark') {
                                themeIcon.classList.remove('fa-moon');
                                themeIcon.classList.add('fa-sun');

                            } else {
                                themeIcon.classList.remove('fa-sun');
                                themeIcon.classList.add('fa-moon');
                                themeIcon.style.color = '#64748b'; // Gray for moon
                            }
                        }

                        // Listen for system theme changes
                        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                            if (!localStorage.getItem('theme')) { // Only if user hasn't set a preference
                                applyTheme(e.matches ? 'dark' : 'light');
                            }
                        });
                    });


                    //sidebar active link highlighting
                    document.addEventListener('DOMContentLoaded', function() {

                        const currentPage = window.location.pathname.split("/").pop();


                        const menuLinks = document.querySelectorAll('.sidebar-menu a');

                        menuLinks.forEach(link => {
                            // If the link's href matches the current page, add the 'active' class
                            if (link.getAttribute('href') === currentPage) {
                                link.classList.add('active');
                            }
                        });
                    });
                </script>