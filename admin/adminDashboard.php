<?php
include('sidebar.php');
include('../database/dbConfig.php');
?>
<div class="content">

            <div class="container_card">
                <div class="dashboard_card">
                    <h4>Active Tournaments</h4>
                    <h2>12</h2>
                    <p class="stat"><span class="span">↑18.2%</span>vs last month</p>
                    <div class="icon1"> <i class="fa fa-trophy"></i></div>
                   
                </div>

                <div class="dashboard_card">
                    <h4>Total Players</h4>
                    <h2>240</h2>
                    <p class="stat"><span class="span">↑12.5%</span>vs last month</p>
                    <div class="icon2"> <i class="fa fa-users"></i></div>
                </div>

                <div class="dashboard_card">
                    <h4>Upcoming Matches</h4>
                    <h2>30</h2>
                    <p class="stat"><span class="span">↓3.1% </span>vs last month</p>
                    <div class="icon3"> <i class="fa fa-calendar-alt"></i></div>
                </div>

                <div class="dashboard_card">
                    <h4>Total Prize Pool</h4>
                    <h2>$2.1M</h2>
                    <p class="stat"><span class="span">↑25.3%</span>vs last month</p>
                    <span class="span"> vs last month</span>
                    <div class="icon4"> <i class="fa fa-dollar-sign"></i></div>
                </div>
            </div> 
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="chart">
    <!-- for barchart -->
    <canvas id="userchart"></canvas>
</div>
<?php
$sql4="select created_at, count(*) as usertotal from users group by created_at";
$users=mysqli_query($conn,$sql4);
$data=[];
$labels=[];
while($row=mysqli_fetch_assoc($users)){
    $data[]=$row;
    $labels[]=$row['created_at'];
}
?>

<?php
include('footer.php');
?>