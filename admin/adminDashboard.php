<?php
include('sidebar.php');
include('../database/dbConfig.php');

// $sql1=mysqli_query($conn,"select count(*) as activetournaments from tournaments where status='ongoing'");
// $activetournaments= mysqli_fetch_assoc($sql1)['activetournaments'];

// $sql2=mysqli_query($conn,"select count(*) as totalplayers from users where is_organizer=0 and organizer_status='pending' ");
// $totalplayers= mysqli_fetch_assoc($sql2)['totalplayers'];

// $sql3=mysqli_query($conn,"select count(*) as upcomingtournaments from tournaments where status='upcoming'");
// $upcomingtournaments= mysqli_fetch_assoc($sql3)['upcomingtournaments'];

// $sql4=mysqli_query($conn,"select sum(prize_pool) as totalprize_pool from tournaments");
// $totalprize_pool= mysqli_fetch_assoc($sql4)['totalprize_pool'];

// $sql5=mysqli_query($conn,"select sum(fee) as totalfees from tournaments");
// $totalfees= mysqli_fetch_assoc($sql5)['totalfees'];




function getDashboardStats($table, $column, $condition, $conn, $isSum = false)
{
    $type = $isSum ? "SUM($column)" : "COUNT(*)";
    $dateCol = "created_at";


    $overall_sql = "SELECT COALESCE($type, 0) as total FROM $table WHERE $condition";
    $overall_res = $conn->query($overall_sql);
    $overall_val = $overall_res->fetch_assoc()['total'] ?? 0;


    $curr_sql = "SELECT COALESCE($type, 0) as total FROM $table 
                 WHERE $condition 
                 AND MONTH($dateCol) = MONTH(CURRENT_DATE()) 
                 AND YEAR($dateCol) = YEAR(CURRENT_DATE())";
    $curr_res = $conn->query($curr_sql);
    $curr_val = $curr_res->fetch_assoc()['total'] ?? 0;

    $last_sql = "SELECT COALESCE($type, 0) as total FROM $table 
                 WHERE $condition 
                 AND MONTH($dateCol) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
                 AND YEAR($dateCol) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
    $last_res = $conn->query($last_sql);
    $last_val = $last_res->fetch_assoc()['total'] ?? 0;

    if ($last_val > 0) {
        $diff = (($curr_val - $last_val) / $last_val) * 100;
    } else {
        $diff = ($curr_val > 0) ? 100 : 0;
    }


    return [
        'display_value' => $isSum ? number_format($overall_val, 2) : $overall_val,
        'percent'       => number_format(abs($diff), 1),
        'is_up'         => $diff >= 0,
        'raw_diff'      => $diff
    ];
}

$stat_active   = getDashboardStats('tournaments', '*', "status='ongoing'", $conn);
$stat_players  = getDashboardStats('users', '*', "is_organizer=0", $conn);
$stat_upcoming = getDashboardStats('tournaments', '*', "status='upcoming'", $conn);
$stat_prize    = getDashboardStats('tournaments', 'prize_pool', "1=1", $conn, true);
$stat_fees     = getDashboardStats('tournaments', 'fee', "1=1", $conn, true);
$stat_month_income = getDashboardStats('tournaments', 'fee', "MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())", $conn, true);
?>

<div class="content">
    <div class="container_card">
        <div class="dashboard_card">
            <h4>Active Tournaments</h4>
            <h2><?php echo $stat_active['display_value']; ?></h2>
            <p class="stat">
                <span class="span" style="color: <?php echo $stat_active['is_up'] ? '#4ade80' : '#f87171'; ?>;">
                    <?php echo ($stat_active['is_up'] ? '↑' : '↓') . $stat_active['percent']; ?>%
                </span> vs last month
            </p>
            <div class="icon1"> <i class="fa fa-trophy"></i></div>
        </div>

        <div class="dashboard_card">
            <h4>Total Players</h4>
            <h2><?php echo $stat_players['display_value']; ?></h2>
            <p class="stat">
                <span class="span" style="color: <?php echo $stat_players['is_up'] ? '#4ade80' : '#f87171'; ?>;">
                    <?php echo ($stat_players['is_up'] ? '↑' : '↓') . $stat_players['percent']; ?>%
                </span> vs last month
            </p>
            <div class="icon2"> <i class="fa fa-users"></i></div>
        </div>
        <div class="dashboard_card">
            <h4>Upcoming Tournaments</h4>
            <h2><?php echo $stat_upcoming['display_value']; ?></h2>
            <p class="stat">
                <span class="span" style="color: <?php echo $stat_upcoming['is_up'] ? '#4ade80' : '#f87171'; ?>;">
                    <?php echo ($stat_upcoming['is_up'] ? '↑' : '↓') . $stat_upcoming['percent']; ?>%
                </span> vs last month
            </p>
            <div class="icon3"> <i class="fa fa-calendar-alt"></i></div>
        </div>

        <div class="dashboard_card">
            <h4>Total Prize Pool</h4>
            <h2>$<?php echo $stat_prize['display_value']; ?></h2>
            <p class="stat">
                <span class="span" style="color: <?php echo $stat_prize['is_up'] ? '#4ade80' : '#f87171'; ?>;">
                    <?php echo ($stat_prize['is_up'] ? '↑' : '↓') . $stat_prize['percent']; ?>%
                </span> vs last month
            </p>
            <div class="icon4"> <i class="fa fa-dollar-sign"></i></div>
        </div>

        <div class="dashboard_card">
            <h4>Total Fees</h4>
            <h2>$<?php echo $stat_fees['display_value']; ?></h2>
            <p class="stat">
                <span class="span" style="color: <?php echo $stat_fees['is_up'] ? '#4ade80' : '#f87171'; ?>;">
                    <?php echo ($stat_fees['is_up'] ? '↑' : '↓') . $stat_fees['percent']; ?>%
                </span> vs last month
            </p>
            <div class="icon5"> <i class="fa fa-hand-holding-usd"></i></div>
        </div>

        <div class="dashboard_card">
            <h4>Current Month Income</h4>
            <h2>$<?php echo $stat_month_income['display_value']; ?></h2>
            <p class="stat">
                <span class="span" style="color: <?php echo $stat_prize['is_up'] ? '#4ade80' : '#f87171'; ?>;">
                    <?php echo ($stat_prize['is_up'] ? '↑' : '↓') . $stat_prize['percent']; ?>%
                </span> vs last month
            </p>
            <div class="icon6"> <i class="fa fa-dollar-sign"></i></div>
        </div>

    </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="charts-container">
    <div class="chart-card chart-l">
        <h4>Tournaments by Game</h4>
        <div class="canvas-container">
            <canvas id="barchart"></canvas>
        </div>
    </div>

    <div class="chart-card chart-s">
        <h4>Tournaments by Game Type</h4>
        <div class="canvas-container piecon">
            <canvas id="piechart"></canvas>
        </div>
    </div>

    <div class="chart-card chart-income">
        <h4>Income</h4>
        <div class="canvas-container piecon ">
            <canvas id="linechart"></canvas>
        </div>
    </div>
</div>
<?php
$sql6 = "SELECT 
    games.name, 
    COUNT(games.game_id) AS num_of_games
FROM games
LEFT JOIN tournaments ON games.game_id = tournaments.game_id
GROUP BY games.name
ORDER BY num_of_games DESC
LIMIT 5;";
$gamename = mysqli_query($conn, $sql6);
$data = [];
$labels = [];
while ($row = mysqli_fetch_assoc($gamename)) {
    $data[] = $row;
    $labels[] = $row['name'];
}

$sql_pie = "SELECT genre, COUNT(*) AS game_type 
            FROM games 
            GROUP BY genre";
$res_pie = mysqli_query($conn, $sql_pie);
$pie_labels = [];
$pie_values = [];
while ($row = mysqli_fetch_assoc($res_pie)) {
    $pie_labels[] = $row['genre'];
    $pie_values[] = $row['game_type'];
}


$sql_income = "SELECT 
                MONTHNAME(payment_date) AS month_name, 
                SUM(amount) AS total_income 
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
?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const barCtx = document.getElementById('barchart').getContext('2d');
    const purpleGradient = barCtx.createLinearGradient(0, 0, 0, 400);
    purpleGradient.addColorStop(0, 'rgb(242, 12, 150)');
    purpleGradient.addColorStop(1, 'rgb(70, 72, 206)');

    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Number of Tournaments',
                data: <?= json_encode(array_column($data, 'num_of_games')) ?>,
                backgroundColor: purpleGradient,
                borderColor: '#af4792',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });


    new Chart(document.getElementById('piechart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($pie_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_values) ?>,
                backgroundColor: ['#Ecc440', '#007cbe', '#e57a44', '#db5375', '#a882dd', '#beff83', '#b0db43']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    align: 'center',
                    labels: {

                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
    new Chart(document.getElementById('linechart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($income_labels) ?>,
            datasets: [{
                label: 'Monthly Income ($)',
                data: <?= json_encode($income_amounts) ?>,
                borderColor: '#4648ce',
                backgroundColor: 'linear-gradient(135deg, #120c4d, #849dd3)',
                fill: true,
                tension: 0.3,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount ($)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                }
            }
        }
    });






    <?php
    include('footer.php');
    ?>
</script>