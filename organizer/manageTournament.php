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
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$tournament_data = $result->fetch_assoc();
$genre = $tournament_data ? $tournament_data['genre'] : 'MOBA';

if ($genre === 'BATTLE_ROYALE') {
    $scorePage = "brScoreManagement.php";
    $schedulePage = "brScheduleManagement.php";
} else {
    $scorePage = "resultManagement.php";
    $schedulePage = "scheduleManagement.php";
}
?>

<style>
    :root {
        --riot-blue: #0bc6e3;
        --deep-black: #010a13;
        --obsidian: #051923;
        --hex-gold: #c8aa6e;
    }

    .container {
        max-width: 1200px;
        margin: 50px auto;
        padding: 0 20px;
        text-align: center;
    }

    h1 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 3rem;
        color: var(--riot-blue);
        letter-spacing: 4px;
        text-shadow: 0 0 20px rgba(11, 198, 227, 0.4);
        margin-bottom: 5px;
    }

    .subtitle {
        color: #a0a0a0;
        margin-bottom: 40px;
        text-transform: uppercase;
        font-size: 0.9rem;
        letter-spacing: 2px;
    }

    /* ======== NEW MANAGEMENT CARD GRID ======== */
    .managecard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }

    .mgmt-card {
        background: linear-gradient(145deg, var(--deep-black) 0%, var(--obsidian) 100%);
        border: 1px solid rgba(11, 198, 227, 0.2);
        padding: 40px 20px;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        /* Clipped tactical corners */
        clip-path: polygon(10% 0, 100% 0, 100% 90%, 90% 100%, 0 100%, 0 10%);
    }

    .mgmt-card:hover {
        transform: translateY(-10px);
        border-color: var(--riot-blue);
        box-shadow: 0 0 30px rgba(11, 198, 227, 0.2);
    }

    /* Card Accent Glow */
    .mgmt-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--riot-blue), transparent);
        opacity: 0;
        transition: 0.3s;
    }

    .mgmt-card:hover::before {
        opacity: 1;
    }

    /* ICON BOX */
    .icon-box {
        width: 70px;
        height: 70px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 20px;
        font-size: 2rem;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(11, 198, 227, 0.3);
        color: var(--riot-blue);
        transition: 0.3s;
    }

    .mgmt-card:hover .icon-box {
        color: #fff;
        background: var(--riot-blue);
        box-shadow: 0 0 20px var(--riot-blue);
    }

    /* TEXT STYLES */
    .mgmt-card h3 {
        color: #fff;
        font-family: 'Inter', sans-serif;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 10px;
        font-weight: 800;
    }

    .mgmt-card p {
        color: #7a7a7a;
        font-size: 0.85rem;
        line-height: 1.5;
        transition: 0.3s;
    }

    .mgmt-card:hover p {
        color: #ccc;
    }

    /* SPECIFIC ACCENTS - All Blue for your request */
    .blue,
    .purple,
    .red,
    .green {
        color: var(--riot-blue) !important;
    }
</style>

<div class="container">
    <h1>Tournament Management</h1>
    <p class="subtitle">Select a category to manage your gaming tournament</p>

    <div class="managecard">
        <a href="editTournament.php?id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box"><i class="fa-solid fa-download"></i></div>
            <h3>Tournaments</h3>
            <p>Edit Tournament</p>
        </a>

        <a href="participants.php?tournament_id=<?php echo $id; ?>" class="mgmt-card">
            <div class="icon-box"><i class="fa-solid fa-users"></i></div>
            <h3>Participants</h3>
            <p>Manage player profiles and stats</p>
        </a>

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