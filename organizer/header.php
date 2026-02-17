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

$notifications = [];
if ($isLoggedIn) {
    $notiStmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $notiStmt->bind_param("i", $uid);
    $notiStmt->execute();
    $notifications = $notiStmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
            color: rgba(255, 255, 255, 0.5);
            transition: var(--transition);
        }

        .nav-item i {
            font-size: 22px;
            transition: var(--transition);
        }

        .nav-item:hover,
        .nav-item.active {
            color: #fff;
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

        /*=============== Noti =====================*/
        .notification-dropdown {
            position: relative;
            margin-right: 15px;
        }

        .noti-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            position: relative;
            padding: 10px;
        }

        .noti-badge {
            position: absolute;
            top: 0px;
            right: 0px;
            background: var(--accent);
            color: #000;
            font-size: 10px;
            font-weight: 900;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            border: 2px solid var(--bg);
        }

        .noti-content {
            position: absolute;
            top: 50px;
            right: 0;
            width: 320px;
            background: var(--surface);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: none;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border-radius: 4px;
        }

        .noti-content.show {
            display: flex;
        }

        .noti-header {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13px;
            font-weight: 800;
            color: var(--accent);
        }

        .noti-item {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            display: flex;
            gap: 12px;
            cursor: pointer;
            transition: 0.2s;
        }

        .noti-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .noti-item.unread {
            background: rgba(0, 212, 255, 0.03);
            border-left: 2px solid var(--accent);
        }

        .noti-icon {
            color: var(--accent);
            font-size: 14px;
            margin-top: 3px;
        }

        .noti-text p {
            font-size: 12px;
            color: #ccc;
            line-height: 1.4;
        }

        .noti-text small {
            font-size: 10px;
            color: #64748b;
            margin-top: 5px;
            display: block;
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
                <i class="fa-solid fa-house"></i>
                <span>HOME</span>
            </a>

            <a href="tournaments.php" class="nav-item <?= ($current_page == 'tournaments.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-trophy"></i>
                <span>TOURNAMENTS</span>
            </a>

            <a href="teams.php" class="nav-item <?= ($current_page == 'teams.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>TEAMS</span>
            </a>

            <a href="organizerStatistic.php" class="nav-item <?= ($current_page == 'organizerStatistics.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-bar"></i>
                <span>STATISTICS</span>
            </a>

            <a href="contact.php" class="nav-item <?= ($current_page == 'contact.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-envelope"></i>
                <span>CONTACT US</span>
            </a>
        </nav>

    </aside>

    <header class="tx-header">
        <div class="notification-dropdown">
            <?php
            $unread_count = 0;
            if (!empty($notifications)) {
                foreach ($notifications as $n) {
                    if (!$n['is_read']) $unread_count++;
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
                    NOTIFICATIONS
                    <?php if ($unread_count > 0): ?>
                        <span style="font-size: 11px; color: #64748b; font-weight: normal; margin-left: 5px;">(<?php echo $unread_count; ?> unread)</span>
                    <?php endif; ?>
                </div>

                <div class="noti-body" style="max-height: 350px; overflow-y: auto;">
                    <?php if (empty($notifications)): ?>
                        <div class="noti-item" style="cursor: default; justify-content: center;">
                            <p style="color: #94a3b8; font-size: 12px; padding: 20px;">No new notifications</p>
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
                                data-tournament-id="<?php echo $tournament_id; ?>"
                                data-type="<?php echo htmlspecialchars($n['type'] ?? ''); ?>">
                                <div class="noti-icon"><i class="fa-solid fa-info-circle"></i></div>
                                <div class="noti-text">
                                    <p><strong><?php echo htmlspecialchars($n['title']); ?></strong><br>
                                        <?php echo htmlspecialchars($n['message']); ?></p>
                                    <small><i class="fa-regular fa-clock"></i> <?php echo date('M d, h:i A', strtotime($n['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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
                        <a href="organizerProfile.php">CUSTOMIZE PROFILE</a>
                        <a href="changePassword.php">CHANGE PASSWORD</a>
                        <a href="../logout.php" style="color: var(--accent)">LOGOUT</a>
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

        
        // --- Notification Dropdown Toggle ---
        const notiBtn = document.getElementById('notiBtn');
        const notiContent = document.getElementById('notiContent');

        if (notiBtn) {
            notiBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notiContent.classList.toggle('show');
                document.getElementById('userDropdown').classList.remove('show');
            });
        }

        // Close dropdowns on outside click
        window.onclick = function(e) {
            
            if (!e.target.closest('.notification-dropdown')) {
                const notiDrop = document.getElementById('notiContent');
                if (notiDrop) notiDrop.classList.remove('show');
            }
        }

        // --- Handle Clicking Notification Items ---
        document.addEventListener('click', function(e) {
            const item = e.target.closest('.noti-item');
            if (!item || !item.dataset.id) return;

            const notiId = item.dataset.id;
            const tournamentId = item.dataset.tournamentId;
            const notiType = item.dataset.type;

            // Mark as read via AJAX
            fetch('mark-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `id=${notiId}`
            }).then(() => {
                item.classList.remove('unread');
                // Redirect based on notification context
                const notiText = item.querySelector('.noti-text')?.innerText || '';
                const isRejectedText = /rejected/i.test(notiText);
                const isTournamentRejection = (notiType === 'tournament_rejected') || (isRejectedText && /tournament/i.test(notiText));

                if (tournamentId && tournamentId != 0) {
                    window.location.href = `editTournament.php?tournament_id=${tournamentId}`;
                    return;
                }

                if (isTournamentRejection) {
                    window.location.href = 'organizerDashboard.php';
                    return;
                }
            });
        });

        const notiBody = document.querySelector('.noti-body');
        let lastNotiId = (() => {
            const first = notiBody?.querySelector('.noti-item[data-id]');
            return first ? parseInt(first.dataset.id) : 0;
        })();

        const updateUnreadBadge = async () => {
            try {
                const res = await fetch(`get-unread-count.php?_=${Date.now()}`, {
                    cache: 'no-store'
                });
                if (!res.ok) return;
                const countText = await res.text();
                const count = parseInt(countText, 10) || 0;

                const badge = document.querySelector('.noti-badge');
                if (count > 0) {
                    if (badge) {
                        badge.textContent = count;
                    } else {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'noti-badge';
                        newBadge.textContent = String(count);
                        document.getElementById('notiBtn').appendChild(newBadge);
                    }
                } else if (badge) {
                    badge.remove();
                }
            } catch (_) {
                // ignore polling errors
            }
        };

        const renderNotification = (n) => {
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[ch]));

            let tournamentId = 0;
            const rawMessage = String(n.message || '');
            const match = rawMessage.match(/tournament ID #(\d+)/i);
            if (match) tournamentId = match[1];

            const div = document.createElement("div");
            div.className = `noti-item ${n.is_read == 1 ? '' : 'unread'}`;
            div.dataset.id = n.notification_id;
            div.dataset.tournamentId = tournamentId;
            div.dataset.type = n.type || '';
            div.innerHTML = `
                <div class="noti-icon"><i class="fa-solid fa-info-circle"></i></div>
                <div class="noti-text">
                    <p><strong>${esc(n.title)}</strong><br>${esc(rawMessage)}</p>
                    <small><i class="fa-regular fa-clock"></i> ${new Date(n.created_at).toLocaleString()}</small>
                </div>`;
            return div;
        };

        let isPollingNoti = false;
        const pollNotifications = async () => {
            if (isPollingNoti) return;
            isPollingNoti = true;
            try {
                const res = await fetch(`fetch-notifications.php?since=${lastNotiId}&_=${Date.now()}`, {
                    cache: 'no-store'
                });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success || !Array.isArray(data.notifications) || data.notifications.length === 0) {
                    await updateUnreadBadge();
                    return;
                }

                if (notiBody) {
                    data.notifications.slice().reverse().forEach((n) => {
                        const node = renderNotification(n);
                        notiBody.prepend(node);
                        lastNotiId = Math.max(lastNotiId, parseInt(n.notification_id));
                    });

                    const emptyMsg = notiBody.querySelector('.noti-item[style*="justify-content: center"]');
                    if (emptyMsg) emptyMsg.remove();
                } else {
                    data.notifications.forEach((n) => {
                        lastNotiId = Math.max(lastNotiId, parseInt(n.notification_id));
                    });
                }

                await updateUnreadBadge();
            } catch (_) {
                // ignore polling errors
            } finally {
                isPollingNoti = false;
            }
        };

        // Poll every 2s for new notifications (no full page refresh needed)
        setInterval(pollNotifications, 2000);
        setInterval(updateUnreadBadge, 2000);
        pollNotifications();
        updateUnreadBadge();
    </script>

</body>

</html>
