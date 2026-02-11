<?php

include("header.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard</title>
    <style>
        body {
            background: #1a1a1a;
            font-family: Arial, sans-serif;
            color: #fff;
            margin: 0;
            padding: 20px;
        }

        .stats-filter {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-mode {
            display: flex;
            background: #111;
            border-radius: 8px;
            overflow: hidden;
        }

        .mode-btn {
            padding: 8px 14px;
            border: none;
            background: transparent;
            color: #aaa;
            cursor: pointer;
            font-size: 13px;
        }

        .mode-btn.active {
            background: #00f2ff;
            color: #000;
            font-weight: 600;
        }

        .date-control {
            display: flex;
            align-items: center;
            background: #111;
            border-radius: 8px;
            padding: 6px 10px;
            gap: 10px;
        }

        .date-label {
            min-width: 80px;
            text-align: center;
            color: #fff;
            font-weight: 500;
        }

        .nav-btn {
            background: none;
            border: none;
            color: #00f2ff;
            font-size: 20px;
            cursor: pointer;
        }

        .date-control.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        .apply-btn {
            background: #00f2ff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .charts-wrapper {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.03);
            padding: 20px;
            border-radius: 12px;
            min-width: 300px;
            flex: 1;
            text-align: center;
            color: #fff;
            position: relative;
        }

        .chart-no-data {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #999;
            font-size: 16px;
            pointer-events: none;
            display: none;
        }
    </style>
</head>

<body>
    <br><br>

    <h2>Organizer Statistics</h2>

    <div class="stats-filter">
        <div class="filter-mode">
            <button class="mode-btn active" data-mode="date">Date Range</button>
            <button class="mode-btn" data-mode="lifetime">Lifetime</button>
        </div>
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
        <button class="apply-btn" id="applyFilter">Apply</button>
    </div>

    <div class="charts-wrapper">
        <div class="chart-card">
            <h3>Participants by Game</h3>
            <canvas id="participantsChart" width="400" height="250"></canvas>
            <div class="chart-no-data" id="participantsNoData">No data available</div>
        </div>
        <div class="chart-card">
            <h3>Revenue</h3>
            <canvas id="revenueChart" width="400" height="250"></canvas>
            <div class="chart-no-data" id="revenueNoData">No data available</div>
        </div>
    </div>

    <div class="charts-wrapper">
        <div class="chart-card">
            <h3>Total Teams</h3>
            <span id="totalTeams" style="font-size:24px;">0</span>
        </div>
        <div class="chart-card">
            <h3>Total Players</h3>
            <span id="totalPlayers" style="font-size:24px;">0</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        let currentDate = new Date();
        let state = {
            mode: 'date',
            month: currentDate.getMonth(),
            year: currentDate.getFullYear()
        };

        const monthLabel = document.getElementById('monthLabel');
        const yearLabel = document.getElementById('yearLabel');
        const monthControl = document.getElementById('monthControl');
        const yearControl = document.getElementById('yearControl');

        function renderDate() {
            monthLabel.textContent = months[state.month];
            yearLabel.textContent = state.year;
        }
        renderDate();

        document.getElementById('prevMonth').onclick = () => {
            state.month--;
            if (state.month < 0) {
                state.month = 11;
                state.year--;
            }
            renderDate();
        };
        document.getElementById('nextMonth').onclick = () => {
            state.month++;
            if (state.month > 11) {
                state.month = 0;
                state.year++;
            }
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

        const participantsChart = new Chart(document.getElementById('participantsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Participants',
                    data: [],
                    backgroundColor: 'rgba(0,242,255,0.45)'
                }]
            },
            options: commonOptions
        });

        const revenueChart = new Chart(document.getElementById('revenueChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Revenue',
                    data: [],
                    borderColor: '#1e90ff',
                    backgroundColor: 'rgba(30,144,255,0.15)',
                    tension: 0.3
                }]
            },
            options: commonOptions
        });

        function fetchStatistics() {
            const formData = new FormData();
            formData.append('mode', state.mode);
            formData.append('month', state.mode === 'date' ? state.month + 1 : '');
            formData.append('year', state.mode === 'date' ? state.year : '');

            fetch('../api/organizerStatistic.php', {
                    method: 'POST',
                    body: formData
                })

                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        console.error(data.error);
                        return;
                    }

                    // Bar chart
                    participantsChart.data.labels = data.barChart.labels || [];
                    participantsChart.data.datasets[0].data = data.barChart.data || [];
                    participantsChart.update();
                    document.getElementById('participantsNoData').style.display = participantsChart.data.datasets[0].data.length ? 'none' : 'block';

                    // Line chart
                    revenueChart.data.datasets[0].data = data.lineChart.data || Array(12).fill(0);
                    revenueChart.update();
                    const revenueSum = revenueChart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    document.getElementById('revenueNoData').style.display = revenueSum > 0 ? 'none' : 'block';

                    // Totals
                    document.getElementById('totalTeams').textContent = data.totals.teams || 0;
                    document.getElementById('totalPlayers').textContent = data.totals.players || 0;
                })
                .catch(err => console.error('Fetch error:', err));
        }

        document.getElementById('applyFilter').onclick = fetchStatistics;
        fetchStatistics();
    </script>
</body>

</html>