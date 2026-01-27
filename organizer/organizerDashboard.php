<?php
session_start();
require_once "../database/dbConfig.php";

/* ======================
     ACCESS CONTROL
====================== */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['is_organizer']) ||
    $_SESSION['is_organizer'] != 1
) {
    header("Location: ../login.php");
    exit;
}

/* ======================
     USER INFO
====================== */
$username = $_SESSION['username'] ?? 'Organizer';
$isLoggedIn = true;

/* ======================
     FETCH LATEST TOURNAMENT
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

        const ctx1 = document.getElementById('participantsChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Valorant', 'CS:GO', 'League', 'Dota'],
                datasets: [{
                    label: 'Participants',
                    data: [120, 190, 150, 250],
                    backgroundColor: 'rgba(0,242,255,0.45)'
                }]
            },
            options: commonOptions
        });

        const ctx2 = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr'],
                datasets: [{
                    label: 'Revenue',
                    data: [5000, 8500, 6000, 11000],
                    borderColor: '#1e90ff',
                    backgroundColor: 'rgba(30,144,255,0.15)',
                    tension: 0.3
                }]
            },
            options: commonOptions
        });
    </script>

    <?php include("footer.php"); ?>
