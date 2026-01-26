<?php
include('header.php');
?>
<?php
session_start();
require_once "../database/dbConfig.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['is_organizer']) ||
    $_SESSION['is_organizer'] != 1
) {
    header("Location: ../login.php");
    exit;
}

$organizer_id = $_SESSION['user_id'];


/* FETCH ORGANIZER TOURNAMENTS */
$stmt = $conn->prepare("
    SELECT tournament_id, title, status , game_id
    FROM tournaments
    WHERE organizer_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $organizer_id);
$stmt->execute();
$res = $stmt->get_result();

$tournaments = [];
while ($row = $res->fetch_assoc()) {
    $tournaments[] = [
        'id' => $row['tournament_id'],
        'name' => $row['title'],
        'details' => 'Status: ' . $row['status'],
        'status' => ucfirst($row['status']),
        'game_id' => $row['game_id']
    ];
}



// Get the ID from the URL (defaulting to 5 as seen in your screenshot)
$id = isset($_GET['id']) ? $_GET['id'] : 5;
$stmt = $conn->prepare("SELECT g.genre FROM games g JOIN tournaments t ON t.game_id = g.game_id    WHERE t.tournament_id = ?");
$stmt->bind_param("i",$id);
$stmt->execute();
$result = $stmt->get_result();

$tournament_data = $result->fetch_assoc();
$genre = $tournament_data ? $tournament_data['genre'] : 'MOBA';

if($genre === 'BATTLE_ROYALE') {
    $scorePage = "brScoreManagement.php";
    $schedulePage = "brScheduleManagement.php";
}
else {
    $scorePage = "resultManagement.php";
    $schedulePage = "scheduleManagement.php";
}


?>

<div class="container">
    <h1>Tournament Management</h1>

    <p class="subtitle">Select a category to manage your gaming tournament</p>

    <div class="managecard">
        <a href="editTournament.php?id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box red"><i class="fa-solid fa-trophy"></i></div>
            <h3>Tournament</h3>
            <p>Edit Tournament</p>
        </a>

        <a href="participants.php?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box blue"><i class="fa-solid fa-download"></i></div>
            <h3>Teams</h3>
            <p>View Teams and Players / Ban</p>
        </a>
        
        <!-- <a href="participants.php?tournam=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box purple"><i class="fa-solid fa-users"></i></div>
            <h3>Players</h3>
            <p>Manage player profiles and stats</p>
        </a>
            -->
        <a href="<?php echo $scorePage; ?>?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box red"><i class="fa-solid fa-code-branch"></i></div>
            <h3>Matches</h3>
            <p>Manage Score</p>
        </a>

        <a href="<?php echo $schedulePage; ?>?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box green"><i class="fa-solid fa-calendar-days"></i></div>
            <h3>Schedule</h3>
            <p>Manage Schedule</p>
        </a>
    </div>
</div>

<?php
include('footer.php');
?>