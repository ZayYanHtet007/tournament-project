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

function isValidDashboardMonth(?string $value): bool
{
    return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1;
}

function isValidDashboardDate(?string $value): bool
{
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
}

$monthRaw = $_GET['month'] ?? '';
$startFromRaw = $_GET['start_from'] ?? '';
$startToRaw = $_GET['start_to'] ?? '';

$selectedMonth = isValidDashboardMonth($monthRaw) ? $monthRaw : '';
$startDateFrom = isValidDashboardDate($startFromRaw) ? $startFromRaw : '';
$startDateTo = isValidDashboardDate($startToRaw) ? $startToRaw : '';

if ($startDateFrom && $startDateTo && $startDateFrom > $startDateTo) {
    [$startDateFrom, $startDateTo] = [$startDateTo, $startDateFrom];
}
$dashboardDateRangeValue = ($startDateFrom && $startDateTo) ? ($startDateFrom . ' - ' . $startDateTo) : '';

$tournamentDateCondition = '';
$paymentDateCondition = '';
$chartRangeLabel = 'Current Year (' . date('Y') . ')';

$lineChartTitle = 'Revenue Overview (Monthly)';
$lineDatasetLabel = 'Revenue by Month ($)';
$incomePeriodSelect = "DATE_FORMAT(tp.payment_date, '%b') AS period_label";
$incomeGroupBy = "MONTH(tp.payment_date), DATE_FORMAT(tp.payment_date, '%b')";
$incomeOrderBy = "MONTH(tp.payment_date)";

if ($selectedMonth !== '') {
    $selectedYear = (int) substr($selectedMonth, 0, 4);
    $selectedMonthNumber = (int) substr($selectedMonth, 5, 2);
    $selectedMonthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonthNumber);

    $tournamentDateCondition = " AND YEAR(t.start_date) = $selectedYear AND MONTH(t.start_date) = $selectedMonthNumber";
    $paymentDateCondition = " AND YEAR(tp.payment_date) = $selectedYear AND MONTH(tp.payment_date) = $selectedMonthNumber";
    $chartRangeLabel = date('F Y', strtotime($selectedMonthStart));

    $lineChartTitle = 'Revenue Overview (Daily)';
    $lineDatasetLabel = 'Revenue by Day ($)';
    $incomePeriodSelect = "DATE_FORMAT(tp.payment_date, '%d %b') AS period_label";
    $incomeGroupBy = "DATE(tp.payment_date), DATE_FORMAT(tp.payment_date, '%d %b')";
    $incomeOrderBy = "DATE(tp.payment_date)";
} elseif ($startDateFrom || $startDateTo) {
    if ($startDateFrom && $startDateTo) {
        $tournamentDateCondition = " AND DATE(t.start_date) BETWEEN '$startDateFrom' AND '$startDateTo'";
        $paymentDateCondition = " AND DATE(tp.payment_date) BETWEEN '$startDateFrom' AND '$startDateTo'";
        $chartRangeLabel = date('d M Y', strtotime($startDateFrom)) . ' to ' . date('d M Y', strtotime($startDateTo));
    } elseif ($startDateFrom) {
        $tournamentDateCondition = " AND DATE(t.start_date) >= '$startDateFrom'";
        $paymentDateCondition = " AND DATE(tp.payment_date) >= '$startDateFrom'";
        $chartRangeLabel = 'From ' . date('d M Y', strtotime($startDateFrom));
    } else {
        $tournamentDateCondition = " AND DATE(t.start_date) <= '$startDateTo'";
        $paymentDateCondition = " AND DATE(tp.payment_date) <= '$startDateTo'";
        $chartRangeLabel = 'Until ' . date('d M Y', strtotime($startDateTo));
    }

    $lineChartTitle = 'Revenue Overview (Daily)';
    $lineDatasetLabel = 'Revenue by Day ($)';
    $incomePeriodSelect = "DATE_FORMAT(tp.payment_date, '%d %b') AS period_label";
    $incomeGroupBy = "DATE(tp.payment_date), DATE_FORMAT(tp.payment_date, '%d %b')";
    $incomeOrderBy = "DATE(tp.payment_date)";
} else {
    $currentYear = (int) date('Y');
    $tournamentDateCondition = " AND YEAR(t.start_date) = $currentYear";
    $paymentDateCondition = " AND YEAR(tp.payment_date) = $currentYear";
}

$sql6 = "SELECT g.name, COUNT(t.tournament_id) AS num_of_games
         FROM games g
         LEFT JOIN tournaments t
                ON g.game_id = t.game_id
               AND 1=1 $tournamentDateCondition
         GROUP BY g.game_id, g.name
         HAVING num_of_games > 0
         ORDER BY num_of_games DESC
         LIMIT 6";
$gamename = mysqli_query($conn, $sql6);
$labels = [];
$counts = [];
while ($row = mysqli_fetch_assoc($gamename)) {
    $labels[] = $row['name'];
    $counts[] = (int) $row['num_of_games'];
}

$sql_pie = "SELECT COALESCE(g.genre, 'Unknown') AS genre, COUNT(t.tournament_id) AS game_type
            FROM tournaments t
            INNER JOIN games g ON t.game_id = g.game_id
            WHERE 1=1 $tournamentDateCondition
            GROUP BY COALESCE(g.genre, 'Unknown')
            ORDER BY game_type DESC";
$res_pie = mysqli_query($conn, $sql_pie);
$pie_labels = [];
$pie_values = [];
while ($row = mysqli_fetch_assoc($res_pie)) {
    $pie_labels[] = $row['genre'];
    $pie_values[] = (int) $row['game_type'];
}

$sql_income = "SELECT $incomePeriodSelect, SUM(tp.amount) AS total_income
               FROM tournament_payments tp
               WHERE 1=1 $paymentDateCondition
               GROUP BY $incomeGroupBy
               ORDER BY $incomeOrderBy";
$res_income = mysqli_query($conn, $sql_income);
$income_labels = [];
$income_amounts = [];
while ($row = mysqli_fetch_assoc($res_income)) {
    $income_labels[] = $row['period_label'];
    $income_amounts[] = (float) $row['total_income'];
}

// Fetching Statistics
$stat_active   = getDashboardStats('tournaments', '*', "status='ongoing'", $conn);
$stat_players  = getDashboardStats('users', '*', "is_organizer=0", $conn);
$stat_upcoming = getDashboardStats('tournaments', '*', "status='upcoming'", $conn);
$stat_prize    = getDashboardStats('tournaments', 'prize_pool', '1=1', $conn, true);
$stat_fees     = getDashboardStats('tournaments', 'fee', '1=1', $conn, true);
$stat_month_income = getDashboardStats('tournaments', 'fee', '1=1', $conn, true, true);
?>

<div class="main-content">

    <div class="main-content-container">
        <div class="dashboard-toolbar">
            <div class="dashboard-header">
                <h1>Analytics Overview</h1>
                <p style="color: var(--text-muted)">Real-time performance metrics for your tournament platform.</p>
            </div>

            <form method="GET" class="dashboard-date-filter" id="dashboardFilterForm">
                <input type="hidden" name="start_from" id="start_from" value="<?= htmlspecialchars($startDateFrom) ?>">
                <input type="hidden" name="start_to" id="start_to" value="<?= htmlspecialchars($startDateTo) ?>">
                <div class="date-filter-wrapper dashboard-date-range">
                    <input type="text"
                        id="dateRange"
                        class="date-range-input-clean"
                        readonly
                        placeholder="Select Date Range"
                        value="<?= htmlspecialchars($dashboardDateRangeValue) ?>">
                    <i class="ph ph-calendar calendar-icon"></i>
                </div>
            </form>
        </div>

        <p class="dashboard-range-note">Chart filter: <?= htmlspecialchars($chartRangeLabel) ?></p>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <?php
            $cards = [
                ['title' => 'Ongoing Tournaments', 'val' => $stat_active, 'icon' => 'fa-trophy', 'class' => 'icon1', 'prefix' => ''],
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
                            <?= ($card['val']['is_up'] ? '&uarr;' : '&darr;') . $card['val']['percent'] ?>%
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
                <h4><?= htmlspecialchars($lineChartTitle) ?></h4>
                <div style="height: 350px;">
                    <canvas id="linechart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const filterForm = document.getElementById('dashboardFilterForm');
    const dateRangeInput = document.getElementById('dateRange');
    const startFromInput = document.getElementById('start_from');
    const startToInput = document.getElementById('start_to');

    if (startFromInput && startToInput && dateRangeInput && filterForm) {
        const updateHiddenFields = (selectedDates, instance) => {
            if (selectedDates.length === 2) {
                startFromInput.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                startToInput.value = instance.formatDate(selectedDates[1], 'Y-m-d');
            }
        };

        const flatpickrInstance = flatpickr(dateRangeInput, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            closeOnSelect: false,
            defaultDate: [
                startFromInput.value || null,
                startToInput.value || null
            ].filter(Boolean),
            onReady: function(selectedDates, dateStr, instance) {
                const footer = document.createElement('div');
                footer.className = 'flatpickr-footer';

                const restartBtn = document.createElement('button');
                restartBtn.type = 'button';
                restartBtn.className = 'fp-btn fp-btn-restart';
                restartBtn.textContent = 'Restart';

                const applyBtn = document.createElement('button');
                applyBtn.type = 'button';
                applyBtn.className = 'fp-btn fp-btn-apply';
                applyBtn.textContent = 'Apply';

                restartBtn.addEventListener('click', function() {
                    instance.clear();
                    startFromInput.value = '';
                    startToInput.value = '';
                    dateRangeInput.value = '';
                    instance.close();
                    filterForm.submit();
                });

                applyBtn.addEventListener('click', function() {
                    if (instance.selectedDates.length === 2) {
                        updateHiddenFields(instance.selectedDates, instance);
                        instance.close();
                        filterForm.submit();
                    } else if (instance.selectedDates.length === 0) {
                        instance.close();
                    } else {
                        alert('Please select an end date.');
                    }
                });

                footer.appendChild(restartBtn);
                footer.appendChild(applyBtn);
                instance.calendarContainer.appendChild(footer);
            }
        });

        const dateFilterWrapper = document.querySelector('.date-filter-wrapper');
        if (dateFilterWrapper) {
            dateFilterWrapper.addEventListener('click', function() {
                flatpickrInstance.open();
            });
        }
    }

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
                label: <?= json_encode($lineDatasetLabel) ?>,
                data: <?= json_encode($income_amounts) ?>,
                borderColor: '#3b82f6',
                backgroundColor: lineGrad,
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
