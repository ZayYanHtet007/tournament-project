<?php
include('sidebar.php');
include('../database/dbConfig.php');

/**
 * Enhanced Stats Function
 * @param string $table Table name
 * @param string $column Column to count/sum
 * @param string $condition SQL WHERE clause
 * @param mysqli $conn Connection object
 * @param bool $isSum Whether to SUM or COUNT
 * @param bool $onlyCurrentMonth Force current month filter
 */
function getDashboardStats($table, $column, $condition, $conn, $isSum = false, $onlyCurrentMonth = false)
{
    $type = $isSum ? "SUM($column)" : "COUNT(*)";
    $dateCol = "created_at";

    // Overall Total
    $base_cond = $onlyCurrentMonth ? "$condition AND MONTH($dateCol) = MONTH(CURRENT_DATE()) AND YEAR($dateCol) = YEAR(CURRENT_DATE())" : $condition;
    $overall_sql = "SELECT COALESCE($type, 0) as total FROM $table WHERE $base_cond";
    $overall_res = $conn->query($overall_sql);
    $overall_val = $overall_res->fetch_assoc()['total'] ?? 0;

    // Current Month 
    $curr_sql = "SELECT COALESCE($type, 0) as total FROM $table 
                 WHERE $condition 
                 AND MONTH($dateCol) = MONTH(CURRENT_DATE()) 
                 AND YEAR($dateCol) = YEAR(CURRENT_DATE())";
    $curr_res = $conn->query($curr_sql);
    $curr_val = $curr_res->fetch_assoc()['total'] ?? 0;

    // Last Month
    $last_sql = "SELECT COALESCE($type, 0) as total FROM $table 
                 WHERE $condition 
                 AND MONTH($dateCol) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
                 AND YEAR($dateCol) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
    $last_res = $conn->query($last_sql);
    $last_val = $last_res->fetch_assoc()['total'] ?? 0;

    if ($last_val > 0) {
        $diff = (($curr_val - $last_val) / $last_val) * 100;
    } else {
        $diff = ($curr_val > 0) ? 100 : 0;
    }

    return [
        'display_value' => $isSum ? number_format($overall_val, 2) : number_format($overall_val),
        'percent'       => number_format(abs($diff), 1),
        'is_up'         => $diff >= 0,
    ];
}

// Data fetching logic (Keep your existing SQL queries)
$sql6 = "SELECT games.name, COUNT(tournaments.tournament_id) AS num_of_games
         FROM games
         LEFT JOIN tournaments ON games.game_id = tournaments.game_id
         GROUP BY games.name ORDER BY num_of_games DESC LIMIT 6";
$gamename = mysqli_query($conn, $sql6);
$labels = [];
$counts = [];
while ($row = mysqli_fetch_assoc($gamename)) {
    $labels[] = $row['name'];
    $counts[] = $row['num_of_games'];
}

$sql_pie = "SELECT genre, COUNT(*) AS game_type FROM games GROUP BY genre";
$res_pie = mysqli_query($conn, $sql_pie);
$pie_labels = [];
$pie_values = [];
while ($row = mysqli_fetch_assoc($res_pie)) {
    $pie_labels[] = $row['genre'] ?: 'Unknown';
    $pie_values[] = $row['game_type'];
}

$sql_income = "SELECT MONTHNAME(payment_date) AS month_name, SUM(amount) AS total_income 
               FROM tournament_payments
               WHERE YEAR(payment_date) = YEAR(CURDATE()) 
               GROUP BY MONTH(payment_date), MONTHNAME(payment_date) 
               ORDER BY MONTH(payment_date) ASC";
$res_income = mysqli_query($conn, $sql_income);
$income_labels = [];
$income_amounts = [];
while ($row = mysqli_fetch_assoc($res_income)) {
    $income_labels[] = $row['month_name'];
    $income_amounts[] = $row['total_income'];
}

// Fetching Statistics
$stat_active   = getDashboardStats('tournaments', '*', "status='ongoing'", $conn);
$stat_players  = getDashboardStats('users', '*', "is_organizer=0", $conn);
$stat_upcoming = getDashboardStats('tournaments', '*', "status='upcoming'", $conn);
$stat_prize    = getDashboardStats('tournaments', 'prize_pool', "1=1", $conn, true);
$stat_fees     = getDashboardStats('tournaments', 'fee', "1=1", $conn, true);
// Logic fix: Only show sum for the current month
$stat_month_income = getDashboardStats('tournaments', 'fee', "1=1", $conn, true, true);
?>

<div class="main-content">

    <div class="main-content-container">
        <div class="dashboard-header">
            <h1>Analytics Overview</h1>
            <p style="color: var(--text-muted)">Real-time performance metrics for your tournament platform.</p>
        </div>
        <!-- Stat Cards -->
        <div class="stats-grid">
            <?php
            $cards = [
                ['title' => 'Active Tournaments', 'val' => $stat_active, 'icon' => 'fa-trophy', 'class' => 'icon1', 'prefix' => ''],
                ['title' => 'Total Players', 'val' => $stat_players, 'icon' => 'fa-users', 'class' => 'icon2', 'prefix' => ''],
                ['title' => 'Upcoming Events', 'val' => $stat_upcoming, 'icon' => 'fa-calendar-alt', 'class' => 'icon3', 'prefix' => ''],
                ['title' => 'Total Prize Pool', 'val' => $stat_prize, 'icon' => 'fa-dollar-sign', 'class' => 'icon4', 'prefix' => '$'],
                ['title' => 'Total Revenue', 'val' => $stat_fees, 'icon' => 'fa-hand-holding-usd', 'class' => 'icon5', 'prefix' => '$'],
                ['title' => 'Monthly Income', 'val' => $stat_month_income, 'icon' => 'fa-chart-line', 'class' => 'icon6', 'prefix' => '$'],
            ];

            foreach ($cards as $card) : ?>
                <div class="dashboard_card">
                    <h4><?= $card['title'] ?></h4>
                    <h2><?= $card['prefix'] . $card['val']['display_value'] ?></h2>
                    <p class="stat">
                        <span class="<?= $card['val']['is_up'] ? 'span-up' : 'span-down' ?>">
                            <?= ($card['val']['is_up'] ? '↑' : '↓') . $card['val']['percent'] ?>%
                        </span>
                        <span style="color: var(--text-muted)">vs last month</span>
                    </p>
                    <div class="card-icon <?= $card['class'] ?>">
                        <i class="fas <?= $card['icon'] ?>"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h4>Tournaments by Game</h4>
                <div style="height: 300px;">
                    <canvas id="barchart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h4>Distribution by Genre</h4>
                <div style="height: 300px;">
                    <canvas id="piechart"></canvas>
                </div>
            </div>

            <div class="chart-card full-width">
                <h4>Revenue Overview (Current Year)</h4>
                <div style="height: 350px;">
                    <canvas id="linechart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>




<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Global Chart Defaults
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';

    // Bar Chart
    const barCtx = document.getElementById('barchart').getContext('2d');
    const purpleGrad = barCtx.createLinearGradient(0, 0, 0, 400);
    purpleGrad.addColorStop(0, '#8b5cf6');
    purpleGrad.addColorStop(1, '#3b82f6');

    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Tournaments',
                data: <?= json_encode($counts) ?>,
                backgroundColor: purpleGrad,
                borderRadius: 8,
                barThickness: 25
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [5, 5]
                    }
                }
            }
        }
    });

    // Pie Chart
    new Chart(document.getElementById('piechart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pie_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_values) ?>,
                backgroundColor: ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });

    // Line Chart
    const lineCtx = document.getElementById('linechart').getContext('2d');
    const lineGrad = lineCtx.createLinearGradient(0, 0, 0, 400);
    lineGrad.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
    lineGrad.addColorStop(1, 'rgba(59, 130, 246, 0)');

    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($income_labels) ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: <?= json_encode($income_amounts) ?>,
                borderColor: '#3b82f6',
                backgroundcolor: lineGrad,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: '#3b82f6'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    ticks: {
                        callback: value => '$' + value
                    },
                    grid: {
                        borderDash: [5, 5]
                    }
                }
            },
            plugins: {
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#fff',
                    padding: 12,
                    displayColors: false
                }
            }
        }
    });
</script>