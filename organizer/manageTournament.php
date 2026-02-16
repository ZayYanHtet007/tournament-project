<?php
include('header.php');
?>
<?php
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
        'tournament_id' => $row['tournament_id'],
        'name' => $row['title'],
        'details' => 'Status: ' . $row['status'],
        'status' => ucfirst($row['status']),
        'game_id' => $row['game_id']
    ];
}

// Get the ID from the URL (defaulting to 5 as seen in your screenshot)
$id = isset($_GET['tournament_id']) ? $_GET['tournament_id'] : 0;
if($id === 0){
  echo"Tournament ID not found !";
  return;
}
$stmt = $conn->prepare("SELECT g.genre FROM games g JOIN tournaments t ON t.game_id = g.game_id    WHERE t.tournament_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$tournament_data = $result->fetch_assoc();
$genre = $tournament_data ? $tournament_data['genre'] : 'MOBA';

$stmtFortype = $conn->prepare("SELECT type FROM tournaments WHERE tournament_id = ?");
$stmtFortype->bind_param("i",$id);
$stmtFortype->execute();
$typeResult = $stmtFortype->get_result();

$typeData = $typeResult->fetch_assoc();
$type = $typeData ? $typeData['type'] : 'standard';

if ($type === 'singleelimination'){
    $scorePage = "singleEliminationScore.php";
    $schedulePage = "SingleEliminationSchedule.php";
}
  else if ($genre === 'BATTLE_ROYALE' && $type='standarad') {
    $scorePage = "brScoreManagement.php";
    $schedulePage = "brScheduleManagement.php";
} else if($genre !== 'BATTLE_ROYALE' && $type='standard'){
    $scorePage = "resultManagement.php";
    $schedulePage = "scheduleManagement.php";
}
  
?>

<style>
    :root {
        --riot-blue: #00eeff;
        --deep-black: #010a13;
        --obsidian: #051923;
        --hex-gold: #c8aa6e;
    }

    body {
        background-color: var(--deep-black);
        background-image: radial-gradient(circle at 50% 0%, rgba(0, 238, 255, 0.08) 0%, transparent 50%);
    }

    .container {
        max-width: 1200px;
        margin: 50px auto;
        padding: 0 20px;
        height: calc(100vh - 100px);
        text-align: center;
    }

    /* TOP ACTION BAR */

    h1 {
        font-family: 'Inter', sans-serif;
        font-size: 2.8rem;
        color: #fff;
        letter-spacing: 4px;
        font-weight: 200;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .subtitle {
        color: var(--riot-blue);
        margin-bottom: 50px;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 3px;
        opacity: 0.6;
    }

    /* ======== SMOOTH CARD GRID ======== */
    .managecard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 30px;
        margin-top: 20px;
    }

    .mgmt-card {
        background: rgba(5, 25, 35, 0.8);
        border: 1px solid rgba(0, 238, 255, 0.15);
        padding: 50px 20px;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        position: relative;
        backdrop-filter: blur(10px);
    }

    .mgmt-card:hover {
        transform: translateY(-10px);
        border-color: var(--riot-blue);
        background: rgba(11, 198, 227, 0.05);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
    }

    /* Animated Border Accent */
    .mgmt-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--riot-blue), transparent);
        transform: scaleX(0);
        transition: 0.5s;
    }

    .mgmt-card:hover::before {
        transform: scaleX(1);
    }

    /* ICON BOX */
    .icon-box {
        width: 65px;
        height: 65px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 25px;
        font-size: 1.8rem;
        color: var(--riot-blue);
        border: 1px solid rgba(0, 238, 255, 0.2);
        background: rgba(0, 0, 0, 0.3);
        transition: 0.5s;
    }

    .mgmt-card:hover .icon-box {
        color: #000;
        background: var(--riot-blue);
        box-shadow: 0 0 20px var(--riot-blue);
        border-color: var(--riot-blue);
    }

    /* TEXT STYLES */
    .mgmt-card h3 {
        color: #fff;
        font-family: 'Inter', sans-serif;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 12px;
        font-weight: 800;
    }

    .mgmt-card p {
        color: #7a7a7a;
        font-size: 0.85rem;
        line-height: 1.5;
        transition: 0.3s;
    }

    .mgmt-card:hover p {
        color: #f0f5f5;
    }

</style>

<div class="container">

    <h1>Tournament Management</h1>
    <p class="subtitle">Select a category to manage tournament <p>

    <div class="managecard">
        <a href="editTournament.php?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box"><i class="fa-solid fa-trophy"></i></div>
            <h3>Tournaments</h3>
            <p>Edit Tournament Details</p>
        </a>

        <a href="participants.php?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box"><i class="fa-solid fa-users"></i></div>
            <h3>Participants</h3>
            <p>Manage player profiles and stats</p>
        </a>

        <a href="<?php echo $schedulePage; ?>?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box"><i class="fa-solid fa-calendar-days"></i></div>
            <h3>Schedule</h3>
            <p>Manage Schedule</p>
        </a>

        <a href="<?php echo $scorePage; ?>?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box"><i class="fa-solid fa-code-branch"></i></div>
            <h3>Matches</h3>
            <p>Manage Score</p>
        </a>
    </div>
</div>