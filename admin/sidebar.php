<?php
require_once __DIR__ . '/../database/dbConfig.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: AdminLogin.php");
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
$adminImg = $_SESSION['admin_img'] ?? null;
$adminRole = $_SESSION['admin_role'] ?? 'admin';


$imageSource = null;
if (!empty($adminImg) && $adminImg !== 'default_profile.png') {

    $imageSource = '../images/upload_photos/' . $adminImg;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') ||
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

</head>



<body>
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
                <a href="organizers.php"><i class="fa fa-user-check"></i> Organizers</a>
                <a href="message.php"><i class="fa fa-envelope"></i> Message</a>
                <a href="games.php"><i class="fa-solid fa-gamepad"></i> Games</a>
            </div>

            <div class="profile-popup" id="profilePopup">

                <div class="popup-body">
                    <div class="popup-item">
                        <a href="changePassword.php" style="text-decoration: none; color: #d1d1e6;">
                            <i class="fa fa-lock"></i><span>Change Password</span>
                        </a>
                    </div>
                    <hr style="border-color: #45455e; margin: 5px 0;">

                    <?php if ($adminRole === 'main_admin'): ?>
                        <div class="popup-item">
                            <a href="adminCreate.php" style="text-decoration: none; color: #d1d1e6;">
                                <i class="fa fa-user-shield"></i><span>Create Admin</span>
                            </a>
                        </div>
                        <hr style="border-color: #45455e; margin: 5px 0;">
                        <div class="popup-item">
                            <a href="manageAdmin.php" style="text-decoration: none; color: #d1d1e6;">
                                <i class="fa fa-users-cog"></i><span>Manage Admin</span>
                            </a>
                        </div>
                        <hr style="border-color: #45455e; margin: 5px 0;">
                    <?php endif; ?>

                    <a href="signOut.php" class="popup-item logout-link" style="text-decoration: none;">
                        <i class="fa fa-sign-out-alt"></i><span>Sign out</span>
                    </a>
                </div>
            </div>

            <div class="admin_profile" onclick="togglePopup(event)">
                <div class="profile_content">
                    <i class="ph ph-gear-six"></i> SETTINGS
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
                                    <span class="noti-unread-meta">(<?php echo $unread_count; ?> unread)</span>
                                <?php endif; ?>
                            </div>

                            <div class="noti-body">
                                <?php if (empty($notifications)): ?>
                                    <div class="noti-item empty">
                                        <div class="noti-text noti-empty-text">
                                            <p>No new notifications</p>
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
                                                <i class="fa-solid fa-circle-info"></i>
                                            </div>
                                            <div class="noti-text">
                                                <p>
                                                    <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                                                    <br>
                                                    <?php echo htmlspecialchars($n['message']); ?>
                                                </p>
                                                <small>
                                                    <i class="fa-regular fa-clock noti-time-icon"></i>
                                                    <?php echo date('M d, h:i A', strtotime($n['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="header-profile-container" onclick="toggleHeaderPopup(event)">
                        <div class="profile-trigger">
                            <?php if ($imageSource): ?>
                                <img src="<?php echo htmlspecialchars($imageSource); ?>"
                                    alt="<?php echo htmlspecialchars($adminName); ?>">
                            <?php else: ?>
                                <img src="../images/default_profile.png" alt="Default">
                            <?php endif; ?>
                        </div>

                        <div class="header-dropdown" id="headerDropdown">
                            <div class="popup-header-content">
                                <h4>Hi, <?= htmlspecialchars($adminName) ?>!</h4>
                                <p style="font-size: 11px; color: #a1a1b5; margin: 0;"><?= htmlspecialchars($adminEmail) ?></p>
                            </div>

                            <hr style="border-color: #45455e; margin: 10px 0;">

                            <div class="popup-item">
                                <a href="customizeProfile.php" style="text-decoration: none; color: #d1d1e6; display: flex; align-items: center; gap: 10px;">
                                    <i class="fa fa-user-pen"></i><span>Customize Profile</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="main-content">


            </div>

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
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div class="noti-text">
                    <p>
                        <strong>${data.title}</strong>
                        <br>
                        ${data.message}
                    </p>
                    <small>
                        <i class="fa-regular fa-clock noti-time-icon"></i>
                        ${data.created_at}
                    </small>
                </div>
            `;

                    // Add to top of notifications
                    notiBody.prepend(div);

                    // Remove "No new notifications" message if present
                    const noNotiItem = notiBody.querySelector('.noti-item.empty');
                    if (noNotiItem) {
                        noNotiItem.remove();
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
                        newSpan.className = 'noti-unread-meta';
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


                        if (notiItem.classList.contains('empty')) {
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
                                window.location.href = `tournamentsDetail.php?id=${tournamentMatch[1]}`;
                            } else {
                                // If still no tournament ID, show alert or stay on page
                                console.log('No tournament ID found in notification');

                            }
                        }
                    });
                });

                // Dark mode and light mode toggle
                document.addEventListener('DOMContentLoaded', function() {
                    const themeToggle = document.getElementById('theme-toggle');
                    const themeIcon = themeToggle.querySelector('i');

                    function applyTheme(theme) {
                        document.documentElement.setAttribute('data-theme', theme);
                        localStorage.setItem('theme', theme);

                        // Update Icon appearance
                        if (theme === 'dark') {
                            themeIcon.classList.remove('fa-sun');
                            themeIcon.classList.add('fa-moon');
                            themeIcon.style.color = '#f8fafc'; // Light color for moon
                        } else {
                            themeIcon.classList.remove('fa-moon');
                            themeIcon.classList.add('fa-sun');
                            themeIcon.style.color = '#eab308'; // Yellow for sun
                        }
                    }

                    // Toggle logic
                    themeToggle.addEventListener('click', () => {
                        const currentTheme = document.documentElement.getAttribute('data-theme');
                        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                        applyTheme(newTheme);
                    });

                    // Initial check to set correct icon on load
                    const savedTheme = localStorage.getItem('theme') ||
                        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                    applyTheme(savedTheme);

                    // Listen for system theme changes
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                        if (!localStorage.getItem('theme')) {
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

                // --- Header Top Right Profile Popup ---
                function toggleHeaderPopup(event) {
                    event.stopPropagation(); // Stop the click from bubbling to window
                    const dropdown = document.getElementById('headerDropdown');
                    dropdown.classList.toggle('show');
                }

                // --- Window Click (Closes popups when clicking outside) ---
                window.addEventListener('click', function(event) {
                    // 1. Close Sidebar Bottom Popup
                    if (!event.target.closest('.admin_profile') && !event.target.closest('#profilePopup')) {
                        const sidebarPopup = document.getElementById('profilePopup');
                        if (sidebarPopup) sidebarPopup.classList.remove('show');
                    }

                    // 2. Close Header Top Right Popup
                    if (!event.target.closest('.header-profile-container')) {
                        const headerDropdown = document.getElementById('headerDropdown');
                        if (headerDropdown && headerDropdown.classList.contains('show')) {
                            headerDropdown.classList.remove('show');
                        }
                    }

                    // 3. Close Mobile Sidebar (if clicking outside sidebar)
                    if (window.innerWidth <= 768 &&
                        !document.getElementById('sidebar').contains(event.target) &&
                        !event.target.closest('.mobile-toggle')) {
                        document.getElementById('sidebar').classList.remove('active');
                    }

                    // 4. Close Notification Dropdown
                    const notiContent = document.getElementById('notiContent');
                    const notiBtn = document.getElementById('notiBtn');
                    if (notiContent && notiBtn && !notiContent.contains(event.target) && !notiBtn.contains(event.target)) {
                        notiContent.classList.remove('show');
                    }
                });
            </script>
