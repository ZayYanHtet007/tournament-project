<?php
// Start session and check organizer authentication
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_organizer']) || $_SESSION['is_organizer'] != 1) {
    header("Location: ../index.php");
    exit;
}
if (isset($_SESSION['organizer_status']) && $_SESSION['organizer_status'] !== 'approved') {
    header("Location: ../login.php");
    exit;
}

include("header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Statistics | TournaX</title>
    <!-- Font Awesome (optional but used for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #0a0f1e;
            font-family: 'Segoe UI', Roboto, sans-serif;
            color: #fff;
            line-height: 1.5;
        }

        .stat-page {
            padding: 100px 24px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        h2 {
            font-size: 2rem;
            font-weight: 400;
            margin-bottom: 20px;
            border-left: 4px solid #00f2ff;
            padding-left: 16px;
        }

        /* Filter bar – responsive */
        .stats-filter {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            background: rgba(255,255,255,0.02);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            border: 1px solid rgba(0,242,255,0.1);
            backdrop-filter: blur(4px);
        }

        .filter-mode {
            display: flex;
            background: #111;
            border-radius: 40px;
            overflow: hidden;
        }

        .mode-btn {
            padding: 10px 22px;
            border: none;
            background: transparent;
            color: #aaa;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .mode-btn.active {
            background: #00f2ff;
            color: #000;
        }

        .date-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .date-control {
            display: flex;
            align-items: center;
            background: #111;
            border-radius: 40px;
            padding: 4px 10px;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .date-label {
            min-width: 90px;
            text-align: center;
            color: #fff;
            font-weight: 500;
            font-size: 14px;
        }

        .nav-btn {
            background: none;
            border: none;
            color: #00f2ff;
            font-size: 20px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: 0.2s;
        }

        .nav-btn:hover {
            background: rgba(0,242,255,0.2);
        }

        .date-control.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        .apply-btn {
            background: #00f2ff;
            border: none;
            padding: 10px 28px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            color: #000;
            font-size: 14px;
            transition: 0.2s;
            box-shadow: 0 0 10px rgba(0,242,255,0.3);
            margin-left: auto;
        }

        .apply-btn:hover {
            background: #00d8e6;
            box-shadow: 0 0 20px #00f2ff;
        }

        /* Charts grid */
        .charts-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin: 30px 0;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.02);
            padding: 20px;
            border-radius: 20px;
            border: 1px solid rgba(0,242,255,0.1);
            backdrop-filter: blur(4px);
            position: relative;
            min-height: 300px;
            display: flex;
            flex-direction: column;
        }

        .chart-card h3 {
            font-size: 1.3rem;
            font-weight: 400;
            margin-bottom: 20px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 12px;
        }

        .chart-card h3 i {
            color: #00f2ff;
        }

        .chart-container {
            flex: 1;
            position: relative;
            min-height: 250px;
        }

        .chart-no-data {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #999;
            font-size: 16px;
            text-align: center;
            display: none;
        }

        .stat-numbers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.02);
            border-radius: 16px;
            padding: 24px 16px;
            text-align: center;
            border: 1px solid rgba(0,242,255,0.1);
        }

        .stat-card h4 {
            color: rgba(255,255,255,0.7);
            font-size: 1rem;
            font-weight: 400;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 2.4rem;
            font-weight: 700;
            color: #00f2ff;
        }

        .stat-card i {
            font-size: 2rem;
            color: #00f2ff;
            margin-bottom: 10px;
        }

        /* Loading overlay */
        .loading {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(3px);
        }

        .loading.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(0,242,255,0.2);
            border-top-color: #00f2ff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Mobile fine-tuning */
        @media (max-width: 600px) {
            .stat-page { padding: 80px 16px 20px; }
            h2 { font-size: 1.6rem; }
            .stats-filter { flex-direction: column; align-items: stretch; }
            .date-controls { justify-content: space-between; }
            .apply-btn { margin-left: 0; width: 100%; }
            .filter-mode { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="stat-page">
        <h2><i class="fas fa-chart-pie" style="color:#00f2ff; margin-right:10px;"></i> Organizer Statistics</h2>

        <!-- Filter bar -->
        <div class="stats-filter">
            <div class="filter-mode">
                <button class="mode-btn active" data-mode="date">Date Range</button>
                <button class="mode-btn" data-mode="lifetime">Lifetime</button>
            </div>

            <div class="date-controls">
                <div class="date-control" id="monthControl">
                    <button class="nav-btn" id="prevMonth">‹</button>
                    <span class="date-label" id="monthLabel">March</span>
                    <button class="nav-btn" id="nextMonth">›</button>
                </div>
                <div class="date-control" id="yearControl">
                    <button class="nav-btn" id="prevYear">‹</button>
                    <span class="date-label" id="yearLabel">2026</span>
                    <button class="nav-btn" id="nextYear">›</button>
                </div>
            </div>

            <button class="apply-btn" id="applyFilter"><i class="fas fa-sync-alt"></i> Apply</button>
        </div>

        <!-- Charts -->
        <div class="charts-wrapper">
            <div class="chart-card">
                <h3><i class="fas fa-gamepad"></i> Participants by Game</h3>
                <div class="chart-container">
                    <canvas id="participantsChart"></canvas>
                    <div class="chart-no-data" id="participantsNoData">No data available</div>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-dollar-sign"></i> Monthly Revenue</h3>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                    <div class="chart-no-data" id="revenueNoData">No revenue yet</div>
                </div>
            </div>
        </div>

        <!-- Stat numbers -->
        <div class="stat-numbers">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h4>Total Teams</h4>
                <span class="stat-value" id="totalTeams">0</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-user"></i>
                <h4>Unique Players</h4>
                <span class="stat-value" id="totalPlayers">0</span>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Month names
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        // State
        let currentDate = new Date();
        let state = {
            mode: 'date',
            month: currentDate.getMonth(),
            year: currentDate.getFullYear()
        };

        // DOM elements
        const monthLabel = document.getElementById('monthLabel');
        const yearLabel = document.getElementById('yearLabel');
        const monthControl = document.getElementById('monthControl');
        const yearControl = document.getElementById('yearControl');
        const loading = document.getElementById('loading');

        function renderDate() {
            monthLabel.textContent = months[state.month];
            yearLabel.textContent = state.year;
        }
        renderDate();

        // Navigation
        document.getElementById('prevMonth').onclick = () => {
            state.month--;
            if (state.month < 0) { state.month = 11; state.year--; }
            renderDate();
        };
        document.getElementById('nextMonth').onclick = () => {
            state.month++;
            if (state.month > 11) { state.month = 0; state.year++; }
            renderDate();
        };
        document.getElementById('prevYear').onclick = () => {
            state.year--;
            renderDate();
        };
        document.getElementById('nextYear').onclick = () => {
            state.year++;
            renderDate();
        };

        // Mode toggle
        document.querySelectorAll('.mode-btn').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.mode = btn.dataset.mode;
                if (state.mode === 'lifetime') {
                    monthControl.classList.add('disabled');
                    yearControl.classList.add('disabled');
                } else {
                    monthControl.classList.remove('disabled');
                    yearControl.classList.remove('disabled');
                }
            };
        });

        // Chart instances
        const participantsChart = new Chart(document.getElementById('participantsChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Teams Joined', data: [], backgroundColor: 'rgba(0,242,255,0.45)' }] },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { labels: { color: '#fff' } } },
                scales: { y: { ticks: { color: '#fff' }, beginAtZero: true }, x: { ticks: { color: '#fff' } } }
            }
        });

        const revenueChart = new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: { labels: months, datasets: [{ label: 'Revenue (USD)', data: [], borderColor: '#1e90ff', backgroundColor: 'rgba(30,144,255,0.15)', tension: 0.3 }] },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { labels: { color: '#fff' } } },
                scales: { y: { ticks: { color: '#fff' }, beginAtZero: true }, x: { ticks: { color: '#fff' } } }
            }
        });

        // Fetch data from API
        async function fetchStatistics() {
            loading.classList.add('active');

            const formData = new FormData();
            formData.append('mode', state.mode);
            if (state.mode === 'date') {
                formData.append('month', state.month + 1); // API expects 1-12
                formData.append('year', state.year);
            }

            try {
                const response = await fetch('../api/organizerStatistic.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.error) {
                    console.error(data.error);
                    alert('Error loading statistics: ' + data.error);
                    return;
                }

                // Participants chart
                participantsChart.data.labels = data.barChart.labels || [];
                participantsChart.data.datasets[0].data = data.barChart.data || [];
                participantsChart.update();
                document.getElementById('participantsNoData').style.display = 
                    (data.barChart.data && data.barChart.data.length) ? 'none' : 'block';

                // Revenue chart (12 months)
                revenueChart.data.datasets[0].data = data.lineChart.data || Array(12).fill(0);
                revenueChart.update();
                const totalRevenue = revenueChart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                document.getElementById('revenueNoData').style.display = totalRevenue > 0 ? 'none' : 'block';

                // Totals
                document.getElementById('totalTeams').textContent = data.totals.teams || 0;
                document.getElementById('totalPlayers').textContent = data.totals.players || 0;
            } catch (err) {
                console.error('Fetch error:', err);
                alert('Failed to load data. Please try again.');
            } finally {
                loading.classList.remove('active');
            }
        }

        // Apply button
        document.getElementById('applyFilter').addEventListener('click', fetchStatistics);

        // Initial load
        fetchStatistics();
    </script>

    <?php include("footer.php"); ?>
</body>
</html>