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

include("header.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TournaX | Home</title>

    <style>
        :root {
            --primary-blue: #00f2ff;
        }

        .content-wrap {
            padding-top: 80px;
        }

        .hero {
            text-align: center;
            padding: 40px 20px;
        }

        .hero h1 {
            color: #fff;
            font-size: 2.4rem
        }

        .hero p {
            color: rgba(255, 255, 255, 0.8)
        }

        .hero-buttons {
            margin: 18px 0
        }

        .btn.primary {
            background: var(--primary-blue);
            color: #000;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
        }

        .btn.secondary {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            padding: 8px 16px;
            border-radius: 6px;
            margin-left: 10px;
            text-decoration: none;
        }

        .charts-wrapper {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.03);
            padding: 20px;
            border-radius: 12px;
            min-width: 320px;
            flex: 1
        }

        @media(max-width:768px) {
            .chart-card {
                min-width: 100%
            }
        }
    </style>

<body class="tx-body">

    <div class="content-wrap">
        <section class="hero">
            <div class="hero-content">
                <h1>Welcome, <span style="color:var(--primary-blue)"><?= htmlspecialchars($username) ?></span> 🎮</h1>
                <p>Compete. Manage. Dominate the Tournament Arena.</p>

                <div class="hero-buttons">
                    <a href="createTournament.php" class="btn primary">Create Tournament</a>
                    <?php if ($tournament_id): ?>
                        <a href="tournaments.php" class="btn secondary">Manage Tournaments</a>
                    <?php else: ?>
                        <a href="createTournament.php" class="btn secondary">No Tournament Yet</a>
                    <?php endif; ?>
                </div>

                <div class="charts-wrapper">
                    <div class="chart-card"><canvas id="participantsChart"></canvas></div>
                    <div class="chart-card"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
        </section>
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
                    font: {
                        size: 12
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#fff'
                }
            },
            x: {
                ticks: {
                    color: '#fff'
                }
            }
        }
    };

    /* =========================
       PARTICIPANTS BAR CHART
       (LIFETIME DATA)
    ========================== */
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

    /* =========================
       REVENUE LINE CHART
       (AGGREGATED JAN–APR ACROSS ALL YEARS)
    ========================== */
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

    <?php include("footer.php"); ?>