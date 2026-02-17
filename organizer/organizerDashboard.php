<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_organizer']) || $_SESSION['is_organizer'] != 1) {
        header("Location: ../index.php");
        exit;
    }

    if (isset($_SESSION['organizer_status']) && $_SESSION['organizer_status'] !== 'approved') {
        header("Location: ../login.php");
        exit;
    }

require_once "../database/dbConfig.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['is_organizer']) ||
    $_SESSION['is_organizer'] != 1
) {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Organizer';
$tournament_id = null;

$stmt = $conn->prepare("SELECT tournament_id FROM tournaments WHERE organizer_id = ? ORDER BY created_at DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $tournament_id = $row['tournament_id'];
    }
} else {
    error_log('DB prepare failed: ' . $conn->error);
}

// ---------- Participants chart data (lifetime, unchanged) ----------
$gameLabels = [];
$participantCounts = [];

$sql = "
SELECT 
    g.name AS game_name,
    COUNT(tt.id) AS teams_joined
FROM tournaments t
JOIN games g ON g.game_id = t.game_id
JOIN tournament_teams tt ON tt.tournament_id = t.tournament_id
WHERE 
    t.organizer_id = ?
    AND t.admin_status = 'approved'
GROUP BY g.game_id, g.name
ORDER BY teams_joined DESC
LIMIT 4
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $gameLabels[] = $row['game_name'];
    $participantCounts[] = (int)$row['teams_joined'];
}

// ---------- Revenue chart data (aggregated Jan–Apr across all years) ----------
$revenueLabels = ['Jan', 'Feb', 'Mar', 'Apr'];
$revenueData = array_fill(0, 4, 0.0); // initialise zeros

$revStmt = $conn->prepare("
    SELECT MONTH(tp.payment_date) AS month_num, SUM(tp.amount) AS total
    FROM tournament_payments tp
    JOIN tournaments t ON tp.tournament_id = t.tournament_id
    WHERE t.organizer_id = ?
      AND tp.status = 'completed'
      AND MONTH(tp.payment_date) IN (1,2,3,4)
    GROUP BY MONTH(tp.payment_date)
");
$revStmt->bind_param("i", $_SESSION['user_id']);
$revStmt->execute();
$revResult = $revStmt->get_result();

while ($row = $revResult->fetch_assoc()) {
    $index = $row['month_num'] - 1; // convert month (1-4) to array index (0-3)
    $revenueData[$index] = (float)$row['total'];
}

// ---------- Additional stats for organizer dashboard ----------
// Total tournaments (all, regardless of admin_status)
$totalTournaments = 0;
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM tournaments WHERE organizer_id = ?");
if ($stmtTotal) {
    $stmtTotal->bind_param("i", $_SESSION['user_id']);
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result();
    $totalTournaments = $resultTotal->fetch_assoc()['total'] ?? 0;
}

// Total teams (across all tournaments of this organizer)
$totalTeams = 0;
$stmtTeams = $conn->prepare("
    SELECT COUNT(tt.id) AS total
    FROM tournament_teams tt
    JOIN tournaments t ON tt.tournament_id = t.tournament_id
    WHERE t.organizer_id = ?
");
if ($stmtTeams) {
    $stmtTeams->bind_param("i", $_SESSION['user_id']);
    $stmtTeams->execute();
    $resultTeams = $stmtTeams->get_result();
    $totalTeams = $resultTeams->fetch_assoc()['total'] ?? 0;
}

// Total revenue (all completed payments for organizer's tournaments)
$totalRevenue = 0.0;
$stmtRevenue = $conn->prepare("
    SELECT SUM(tp.amount) AS total
    FROM tournament_payments tp
    JOIN tournaments t ON tp.tournament_id = t.tournament_id
    WHERE t.organizer_id = ? AND tp.status = 'completed'
");
if ($stmtRevenue) {
    $stmtRevenue->bind_param("i", $_SESSION['user_id']);
    $stmtRevenue->execute();
    $resultRevenue = $stmtRevenue->get_result();
    $totalRevenue = (float)($resultRevenue->fetch_assoc()['total'] ?? 0);
}

// Pending tournaments (admin approval pending)
$pendingTournaments = 0;
$stmtPending = $conn->prepare("SELECT COUNT(*) AS total FROM tournaments WHERE organizer_id = ? AND status = 'upcoming'");
if ($stmtPending) {
    $stmtPending->bind_param("i", $_SESSION['user_id']);
    $stmtPending->execute();
    $resultPending = $stmtPending->get_result();
    $pendingTournaments = $resultPending->fetch_assoc()['total'] ?? 0;
}

// ---------- Recent tournaments (for display) ----------
$recentTournaments = [];
$stmtRecent = $conn->prepare("
    SELECT 
        t.tournament_id,
        t.title AS name,
        t.start_date,
        t.admin_status,
        g.name AS game_name,
        (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = t.tournament_id) AS teams_count
    FROM tournaments t
    JOIN games g ON t.game_id = g.game_id
    WHERE t.organizer_id = ?
    ORDER BY t.created_at DESC
    LIMIT 5
");
if ($stmtRecent) {
    $stmtRecent->bind_param("i", $_SESSION['user_id']);
    $stmtRecent->execute();
    $resultRecent = $stmtRecent->get_result();
    while ($row = $resultRecent->fetch_assoc()) {
        $recentTournaments[] = $row;
    }
}

// ---------- Pending actions (example: tournaments waiting for admin approval) ----------
// We already have $pendingTournaments count, but we might want to list them if any.
$pendingList = [];
if ($pendingTournaments > 0) {
    $stmtPendingList = $conn->prepare("
        SELECT tournament_id, title AS name, created_at
        FROM tournaments
        WHERE organizer_id = ? AND status = 'upcoming'
        ORDER BY created_at DESC
        LIMIT 6
    ");
    if ($stmtPendingList) {
        $stmtPendingList->bind_param("i", $_SESSION['user_id']);
        $stmtPendingList->execute();
        $resultPendingList = $stmtPendingList->get_result();
        while ($row = $resultPendingList->fetch_assoc()) {
            $pendingList[] = $row;
        }
    }
}

include("header.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TournaX | Organizer Dashboard</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Three.js and GSAP for 3D floating letters -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>

    <style>
        :root {
            --primary-blue: #00f2ff;
            --dark-bg: #0a0f1e;
            --card-bg: rgba(255, 255, 255, 0.03);
            --border-glow: rgba(0, 242, 255, 0.2);
        }

        body {
            background-color: var(--dark-bg);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        /* Canvas for 3D background */
        #bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .content-wrap {
            padding-top: 80px;
            max-width: 1400px;
            margin: 0 auto;
            padding-left: 20px;
            padding-right: 20px;
            position: relative;
            z-index: 1;
        }

        .hero {
            text-align: center;
            padding: 20px 0 30px;
            align-items: center;
            margin-bottom: 300px;
            margin-top: 180px;
        }

        .hero h1 {
            color: #fff;
            font-size: 2.4rem;
            margin-bottom: 10px;
        }

        .hero p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
        }

        .hero-buttons {
            margin: 25px 0 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            font-size: 1rem;
        }

        .btn.primary {
            background: var(--primary-blue);
            color: #000;
            box-shadow: 0 0 15px var(--primary-blue);
        }

        .btn.primary:hover {
            box-shadow: 0 0 25px var(--primary-blue);
            transform: scale(1.02);
        }

        .btn.secondary {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            margin-left: 15px;
        }

        .btn.secondary:hover {
            background: rgba(0, 242, 255, 0.1);
            border-color: #fff;
            color: #fff;
        }

        .btn.small {
            padding: 6px 12px;
            font-size: 0.9rem;
            border-radius: 20px;
        }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px 15px;
            text-align: center;
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-glow);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-blue);
        }

        .stat-card i {
            font-size: 2.2rem;
            color: var(--primary-blue);
            margin-bottom: 15px;
        }

        .stat-card h3 {
            color: #fff;
            font-size: 1rem;
            margin-bottom: 8px;
            opacity: 0.8;
            letter-spacing: 1px;
        }

        .stat-card .stat-value {
            color: var(--primary-blue);
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .stat-card .stat-unit {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
        }

        /* Charts row */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .chart-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 16px;
            border: 1px solid var(--border-glow);
            backdrop-filter: blur(8px);
        }

        .chart-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #fff;
            font-size: 1.3rem;
            font-weight: 400;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }

        .chart-card h3 i {
            color: var(--primary-blue);
            margin-right: 8px;
        }

        /* Bottom sections grid */
        .bottom-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin: 30px 0;
            margin-bottom: 100px;
        }

        .info-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-glow);
            backdrop-filter: blur(8px);
        }

        .info-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #fff;
            font-size: 1.3rem;
            font-weight: 400;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }

        .info-card h3 i {
            color: var(--primary-blue);
            margin-right: 8px;
        }

        /* Recent tournaments table */
        .tournament-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tournament-table th {
            text-align: left;
            padding: 12px 8px;
            color: rgba(255,255,255,0.6);
            font-weight: 400;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tournament-table td {
            padding: 12px 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .tournament-table tr:hover {
            background: rgba(255,255,255,0.02);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-approved {
            background: rgba(0, 255, 0, 0.15);
            color: #a3ffa3;
            border: 1px solid rgba(0, 255, 0, 0.3);
        }

        .status-pending {
            background: rgba(255, 165, 0, 0.15);
            color: #ffb347;
            border: 1px solid rgba(255, 165, 0, 0.3);
        }

        .status-rejected {
            background: rgba(255, 0, 0, 0.15);
            color: #ff8a8a;
            border: 1px solid rgba(255, 0, 0, 0.3);
        }

        .view-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        .quick-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .quick-link-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            text-decoration: none;
            color: #fff;
            transition: background 0.2s, transform 0.2s;
            border: 1px solid transparent;
        }

        .quick-link-item:hover {
            background: rgba(0,242,255,0.1);
            border-color: var(--primary-blue);
            transform: translateX(5px);
        }

        .quick-link-item i {
            font-size: 1.3rem;
            color: var(--primary-blue);
            width: 30px;
            text-align: center;
            margin-right: 15px;
        }

        .quick-link-item span {
            flex: 1;
        }

        .quick-link-item .fa-chevron-right {
            font-size: 0.9rem;
            opacity: 0.5;
            width: auto;
        }

        .pending-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .pending-item:last-child {
            border-bottom: none;
        }

        .pending-item .tourney-name {
            font-weight: 500;
        }

        .pending-item .tourney-date {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.5);
        }

        /* ===== FULLSCREEN LOADER ===== */
        #loader {
            position: fixed;
            inset: 0;
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }

        /* ===== HIDE ===== */
        #loader.hide {
            opacity: 0;
            visibility: hidden;
        }

        /* ===== LOGO ===== */
        #txLogo {
            width: 100px;
            filter: brightness(0) invert(1);
            animation: subtlePulse 2s ease-in-out infinite;
        }

        @keyframes subtlePulse {
            0% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 0.6; transform: scale(1); }
        }

        /* ===== FADE-UP ANIMATION ===== */
        .fade-up {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Scroll up button */
        .scroll-up-btn {
            display: inline-block;
            margin-top: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary-blue);
            color: #000;
            border-radius: 50%;
            line-height: 50px;
            text-align: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 0 15px var(--primary-blue);
            transition: all 0.3s ease;
            border: none;
        }

        .scroll-up-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 25px var(--primary-blue);
        }

        @media (max-width: 900px) {
            .bottom-grid {
                grid-template-columns: 1fr;
            }
            .btn.secondary{
                margin-top: 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Loader -->
    <div id="loader">
        <img src="../images/TX.png" id="txLogo">
    </div>

    <!-- 3D Canvas for floating letters -->
    <canvas id="bg"></canvas>

    <div class="content-wrap">
        <!-- Hero section with fade-up -->
        <section class="hero fade-up">
            <div class="hero-content">
                <h1>Welcome, <span style="color:var(--primary-blue)"><?= htmlspecialchars($username) ?></span> 🎮</h1>
                <p>Compete. Manage. Dominate the Tournament Arena.</p>

                <div class="hero-buttons">
                    <a href="createTournament.php" class="btn primary"><i class="fas fa-plus-circle"></i> Create Tournament</a>
                    <?php if ($tournament_id): ?>
                        <a href="tournaments.php" class="btn secondary"><i class="fas fa-trophy"></i> Manage Tournaments</a>
                    <?php else: ?>
                        <a href="createTournament.php" class="btn secondary"><i class="fas fa-info-circle"></i> No Tournament Yet</a>
                    <?php endif; ?>
                </div>
                    </div>
        </section>

        <!-- Stats cards (with fade-up) -->
        <div class="stats-grid fade-up">
            <div class="stat-card">
                <i class="fas fa-calendar-alt"></i>
                <h3>Total Tournaments</h3>
                <span class="stat-value"><?= $totalTournaments ?></span>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Total Teams</h3>
                <span class="stat-value"><?= $totalTeams ?></span>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>Total Revenue</h3>
                <span class="stat-value">$<?= number_format($totalRevenue, 2) ?></span>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3>Upcoming Tournaments</h3>
                <span class="stat-value"><?= $pendingTournaments ?></span>
            </div>
        </div>

        <!-- Charts row (with fade-up) -->
        <div class="charts-row fade-up">
            <div class="chart-card">
                <h3><i class="fas fa-chart-bar"></i> Top Games by Participation</h3>
                <canvas id="participantsChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Revenue (Jan-Apr)</h3>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Bottom sections: Recent Tournaments + Pending Actions (with fade-up) -->
        <div class="bottom-grid fade-up">
            <!-- Recent Tournaments -->
            <div class="info-card">
                <h3><i class="fas fa-list"></i> Recent Tournaments</h3>
                <?php if (count($recentTournaments) > 0): ?>
                    <table class="tournament-table">
                        <thead>
                            <tr>
                                <th>Tournament</th>
                                <th>Game</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Teams</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTournaments as $t): 
                                $statusClass = '';
                                if ($t['admin_status'] == 'approved') $statusClass = 'status-approved';
                                elseif ($t['admin_status'] == 'pending') $statusClass = 'status-pending';
                                elseif ($t['admin_status'] == 'rejected') $statusClass = 'status-rejected';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($t['name']) ?></td>
                                <td><?= htmlspecialchars($t['game_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($t['start_date'])) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($t['admin_status']) ?></span></td>
                                <td><?= $t['teams_count'] ?></td>
                                <td><a href="tournamentDetails.php?id=<?= $t['tournament_id'] ?>" class="view-link"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 15px;">
                        <a href="tournaments.php" class="btn small secondary">View All Tournaments <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php else: ?>
                    <p style="color: rgba(255,255,255,0.5); text-align: center;">No tournaments created yet.</p>
                <?php endif; ?>
            </div>

            <!-- Pending Actions (if any) -->
            <?php if (!empty($pendingList)): ?>
            <div class="info-card">
                <h3><i class="fas fa-hourglass-half"></i> Upcoming Tournaments List </h3>
                <div>
                    <?php foreach ($pendingList as $p): ?>
                    <div class="pending-item">
                        <div>
                            <div class="tourney-name"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="tourney-date">Created <?= date('M d', strtotime($p['created_at'])) ?></div>
                        </div>
                        <a href="manageTournament.php?tournament_id=<?= $p['tournament_id'] ?>" class="view-link">View</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chart.js + init -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#fff',
                        font: { size: 12 }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#fff' }
                },
                x: {
                    ticks: { color: '#fff' }
                }
            }
        };

        // Participants Bar Chart
        const gameLabels = <?= json_encode($gameLabels) ?>;
        const participantData = <?= json_encode($participantCounts) ?>;
        const ctx1 = document.getElementById('participantsChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: gameLabels,
                datasets: [{
                    label: 'Teams Joined',
                    data: participantData,
                    backgroundColor: 'rgba(0,242,255,0.45)'
                }]
            },
            options: commonOptions
        });

        // Revenue Line Chart
        const revenueLabels = <?= json_encode($revenueLabels) ?>;
        const revenueData   = <?= json_encode($revenueData) ?>;
        const ctx2 = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Revenue (USD)',
                    data: revenueData,
                    borderColor: '#1e90ff',
                    backgroundColor: 'rgba(30,144,255,0.15)',
                    tension: 0.3
                }]
            },
            options: commonOptions
        });
    </script>

    <script>
        // ================= LOADER LOGIC =================
        const startTime = Date.now();

        window.addEventListener("load", () => {
            const loader = document.getElementById("loader");

            const elapsed = Date.now() - startTime;
            const minDuration = 1500; // 1.5 seconds

            const remaining = Math.max(0, minDuration - elapsed);

            setTimeout(() => {
                loader.classList.add("hide");
            }, remaining);
        });

        // ================= AUTO UPPERCASE TAG =================
        const shortNameInput = document.querySelector('input[name="shortName"]');
        if (shortNameInput) {
            shortNameInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase();
            });
        }

        // ================= FADE-UP ON SCROLL =================
        const fadeElements = document.querySelectorAll('.fade-up');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                } else {
                    entry.target.classList.remove('visible');
                }
            });
        }, { threshold: 0.2 });

        fadeElements.forEach(el => observer.observe(el));

        // ================= SCROLL TO TOP FUNCTION =================
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>

    <!-- 3D Floating Letters Animation (TournaX) -->
    <script>
        // --- THREE.JS SCENE SETUP ---
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({
            canvas: document.querySelector('#bg'),
            antialias: true,
            alpha: true
        });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setClearColor(0x000000, 0);
        renderer.setPixelRatio(window.devicePixelRatio);

        // Core 3D object (icosahedron) – for visual interest
        const geometry = new THREE.IcosahedronGeometry(8, 1);
        const material = new THREE.MeshStandardMaterial({
            color: 0x00f2ff,
            wireframe: true,
            emissive: 0x00f2ff,
            emissiveIntensity: 0.3
        });
        const core = new THREE.Mesh(geometry, material);
        scene.add(core);

        // --- FLOATING LETTERS: T O U R N A X ---
        const lettersGroup = new THREE.Group();
        const fontLoader = new THREE.FontLoader();
        const letters = ['T', 'O', 'U', 'R', 'N', 'A', 'X'];

        fontLoader.load('https://threejs.org/examples/fonts/helvetiker_bold.typeface.json', function(font) {
            // Create glowing materials
            const redMat = new THREE.MeshStandardMaterial({
                color: 0xff4655,
                emissive: 0xff4655,
                emissiveIntensity: 1.5,
                transparent: true,
                opacity: 0.9
            });
            const cyanMat = new THREE.MeshStandardMaterial({
                color: 0x00f2ff,
                emissive: 0x00f2ff,
                emissiveIntensity: 1.5,
                transparent: true,
                opacity: 0.9
            });

            // Create 400 letters
            for (let i = 0; i < 400; i++) {
                const char = letters[Math.floor(Math.random() * letters.length)];
                const textGeo = new THREE.TextGeometry(char, {
                    font: font,
                    size: 1.2,
                    height: 0.2
                });

                // Randomly choose material
                const material = Math.random() > 0.5 ? redMat : cyanMat;
                const mesh = new THREE.Mesh(textGeo, material);

                // Random position within a sphere of radius 30-50
                const r = 30 + Math.random() * 20;
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);
                mesh.position.x = r * Math.sin(phi) * Math.cos(theta);
                mesh.position.y = r * Math.sin(phi) * Math.sin(theta);
                mesh.position.z = r * Math.cos(phi);

                // Random rotation
                mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);

                lettersGroup.add(mesh);
            }
        });
        scene.add(lettersGroup);

        // Lighting
        const light1 = new THREE.PointLight(0xffffff, 1, 100);
        light1.position.set(20, 20, 20);
        scene.add(light1);
        const light2 = new THREE.PointLight(0x00f2ff, 0.5, 100);
        light2.position.set(-20, -10, 20);
        scene.add(light2);
        scene.add(new THREE.AmbientLight(0x404040));

        camera.position.z = 30;

        // Mouse interaction
        let mouseX = 0, mouseY = 0;
        document.addEventListener('mousemove', (e) => {
            mouseX = (e.clientX / window.innerWidth) - 0.5;
            mouseY = (e.clientY / window.innerHeight) - 0.5;
        });

        function animate() {
            requestAnimationFrame(animate);
            core.rotation.x += 0.001;
            core.rotation.y += 0.002;
            lettersGroup.rotation.y += 0.0005;
            lettersGroup.rotation.x += 0.0002;
            camera.position.x += (mouseX * 20 - camera.position.x) * 0.05;
            camera.position.y += (-mouseY * 20 - camera.position.y) * 0.05;
            camera.lookAt(scene.position);
            renderer.render(scene, camera);
        }
        animate();

        // Resize handler
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });
    </script>

    <?php include("footer.php"); ?>