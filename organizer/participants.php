<?php
session_start(); // Add session for admin ID
require '../database/dbConfig.php';

// Check if admin is logged in
$organizer_id = $_SESSION['user_id'] ?? 0; // Assuming admin_id is stored in session
if (!$organizer_id) {
    die("Organizer not logged in");
}

$tournamentId = $_GET['tournament_id'] ?? 0;
if (!$tournamentId) {
    die("Tournament ID missing");
}

/* ================= HANDLE BAN REQUEST ================= */
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_team'])) {
    $team_id = $_POST['team_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    
    if ($team_id && $reason) {
        // Get team name for notification title
        $stmt = $pdo->prepare("SELECT team_name FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        $title = "Ban Request: " . ($team['team_name'] ?? "Team #" . $team_id);
        
        // Insert into admin_notifications
        $stmt = $pdo->prepare("
        INSERT INTO admin_notifications (admin_id, title, message, created_at)
        SELECT admin_id, ?, ?, NOW() FROM admins
    ");
        $stmt->execute([ $title, $reason]);
        $successMessage = "Ban request submitted successfully!";
    } else {
        $errorMessage = "Something missing! Please provide all required information.";
    }
}

/* ================= TOURNAMENT ================= */
$stmt = $pdo->prepare("
    SELECT tournament_id, title, status 
    FROM tournaments 
    WHERE tournament_id = ?
");
$stmt->execute([$tournamentId]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    die("Tournament not found");
}

/* ================= TEAMS ================= */
$stmt = $pdo->prepare("
    SELECT t.team_id, t.team_name, t.logo
    FROM teams t
    JOIN tournament_teams tt ON tt.team_id = t.team_id
    WHERE tt.tournament_id = ?
    ORDER BY t.team_name
");
$stmt->execute([$tournamentId]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= MEMBERS ================= */
$teamIds = array_column($teams, 'team_id');
if ($teamIds) {
    $in  = str_repeat('?,', count($teamIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT tm.team_id, u.username, tm.role
        FROM team_members tm
        JOIN users u ON u.user_id = tm.user_id
        WHERE tm.team_id IN ($in)
    ");
    $stmt->execute($teamIds);
    $membersRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $membersRaw = [];
}

/* Organize members by team */
$membersByTeam = [];
foreach ($membersRaw as $m) {
    $membersByTeam[$m['team_id']][] = $m;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teams & Players</title>
<!-- Font Awesome for icons (monochrome) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ================= SQUARE DESIGN, BRIGHTER BLUE ACCENTS, MINIMAL ICONS ================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background-color: #0b0d12;
    color: #e1e5ec;
    font-family: 'Inter', sans-serif;
    padding: 2rem 1rem;
    min-height: 100vh;
}

/* Main container – dark, square */
.br-container {
    max-width: 1280px;
    margin: 0 auto;
    background-color: #10131c;
    border: 1px solid #2f3a4a;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
    padding: 2rem;
}

/* Title area */
.br-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}
.br-title h1 {
    font-size: 2rem;
    font-weight: 600;
    color: #b8d0f0;
    border-bottom: 2px solid #2d7ff9; /* brighter blue */
    padding-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.br-title p {
    color: #9aaec9;
    margin-top: 0.5rem;
}

/* Back button */
.br-back-button {
    background-color: #1e3142;
    border: 1px solid #2d7ff9;
    color: white;
    padding: 8px 16px;
    font-weight: 600;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: 0.1s;
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.br-back-button:hover {
    background-color: #264763;
    border-color: #5a9eff;
}

/* Search input – square, dark */
.br-search {
    margin: 20px auto;
    max-width: 360px;
}
.br-search input {
    width: 100%;
    padding: 12px 16px;
    background-color: #0f141e;
    border: 1px solid #2f3a4a;
    color: #e0e6f0;
    font-size: 0.95rem;
    border-radius: 0; /* square */
    transition: 0.1s;
}
.br-search input:focus {
    outline: none;
    border-color: #2d7ff9;
    box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
}

/* Participants layout */
.br-participants {
    display: flex;
    gap: 20px;
    margin-top: 30px;
    overflow: hidden;
}

/* Team grid – square cards */
.br-team-grid {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    transition: transform 0.5s ease;
}
.br-team-card {
    background-color: #161b26;
    border: 1px solid #2f3a4a;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6);
    transition: all 0.15s ease;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    border-radius: 0; /* square */
    max-height: 170px;
}
.br-team-card:hover {
    border-color: #2d7ff9;
    box-shadow: 0 0 15px #2d7ff9;
    transform: translateY(-2px);
}
.br-team-card.active {
    border: 2px solid #2d7ff9;
    box-shadow: 0 0 25px #2d7ff9;
}
.br-team-card img {
    width: 80px;
    height: 80px;
    border-radius: 0; /* square */
    object-fit: cover;
    margin-bottom: 20px;
    border: 1px solid #2f3a4a;
}
.br-team-card h3 {
    color: #b8d0f0;
    font-size: 1.05rem;
    font-weight: 500;
}

/* Team panel – square, sliding */
.br-team-panel {
    width: 0;
    opacity: 0;
    overflow: hidden;
    transition: all 0.5s ease;
    background-color: #161b26;
    border: 1px solid #2f3a4a;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6);
    border-radius: 0; /* square */
    position: relative;
    perspective: 1000px;
    flex-shrink: 0;
    min-height: 390px;
    max-height: 390px;
}
.br-team-panel.active {
    width: 360px;
    opacity: 1;
    padding: 20px;
}

.br-panel-inner {
    transition: transform 0.6s;
    transform-style: preserve-3d;
    position: relative;
}
.br-panel-inner.flip {
    transform: rotateY(180deg);
}

.br-panel-front, .br-panel-back {
    backface-visibility: hidden;
    position: absolute;
    width: 100%;
    top: 0;
    left: 0;
}
.br-panel-back {
    transform: rotateY(180deg);
}

.br-panel-title {
    font-size: 1.4rem;
    text-align: center;
    color: #b8d0f0;
    margin-bottom: 18px;
    border-bottom: 1px solid #2f3a4a;
    padding-bottom: 0.5rem;
}

/* Role blocks */
.br-role-block {
    background-color: #1a202b;
    border: 1px solid #2b3442;
    border-radius: 0; /* square */
    padding: 12px;
    margin-bottom: 14px;
}
.br-role-block h4 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #9ca3af;
    margin-bottom: 6px;
}
.br-member {
    font-size: 0.95rem;
    padding: 3px 0;
    color: #d6e2f0;
}
.br-empty {
    font-size: 0.85rem;
    color: #7f8fa0;
    font-style: italic;
}

/* Ban button */
.br-ban-btn {
    background-color: #1e3142;
    border: 1px solid #2d7ff9;
    color: white;
    width: 100%;
    padding: 10px;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 14px;
    border-radius: 0; /* square */
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: 0.1s;
}
.br-ban-btn:hover {
    background-color: #264763;
    border-color: #5a9eff;
}

/* Back side form elements */
.br-back-textarea {
    width: 100%;
    min-height: 100px;
    padding: 10px;
    background-color: #0f141e;
    border: 1px solid #2f3a4a;
    color: #e0e6f0;
    border-radius: 0;
    resize: none;
    margin-bottom: 14px;
    font-family: 'Inter', sans-serif;
}
.br-back-textarea:focus {
    outline: none;
    border-color: #2d7ff9;
    box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
}
.br-back-header {
    font-size: 1rem;
    margin-bottom: 10px;
    color: #b8d0f0;
}
.br-commit-btn, .br-back-btn {
    width: 100%;
    padding: 10px;
    background-color: #1e3142;
    border: 1px solid #2d7ff9;
    color: white;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 10px;
    border-radius: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: 0.1s;
}
.br-commit-btn:hover, .br-back-btn:hover {
    background-color: #264763;
    border-color: #5a9eff;
}

/* Notification messages – square, bright blue border */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    background-color: #1b2535;
    border: 1px solid #2d7ff9;
    color: #c0dcff;
    z-index: 1000;
    animation: slideIn 0.3s ease;
    border-radius: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.5);
}
.notification.success {
    border-color: #2d7ff9; /* same bright blue, no extra colors */
}
.notification.error {
    border-color: #2d7ff9; /* consistent */
}
@keyframes slideIn {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

/* Icons – monochrome */
i, .fa, .fas, .far {
    color: inherit;
}

/* Responsive */
@media(max-width:900px){
    .br-participants {
        flex-direction: column;
    }
    .br-team-panel.active {
        width: 100%;
        margin-top: 20px;
    }
}
</style>
</head>

<body class="br-body">
<div class="br-container">

<!-- Notification Messages -->
<?php if ($successMessage): ?>
<div class="notification success">
    <i class="fas fa-check-circle"></i>
    <?= htmlspecialchars($successMessage) ?>
</div>
<?php endif; ?>
<?php if ($errorMessage): ?>
<div class="notification error">
    <i class="fas fa-exclamation-circle"></i>
    <?= htmlspecialchars($errorMessage) ?>
</div>
<?php endif; ?>

<div class="br-title">
    <h1><i class="fas fa-users"></i> Teams & Players</h1>
    <button class="br-back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button>
</div>

<div class="br-search">
    <input type="text" id="teamSearch" placeholder="Search teams...">
</div>

<div class="br-participants">

<div class="br-team-grid" id="teamGrid">
<?php foreach ($teams as $t): ?>
<div class="br-team-card"
     data-name="<?= strtolower($t['team_name']) ?>"
     data-team-id="<?= $t['team_id'] ?>"
     data-team='<?= json_encode($membersByTeam[$t["team_id"]] ?? []) ?>'>
    <img src="<?= $t['logo'] ?: '../images/games/project-icon.jpg' ?>">
    <h3><?= htmlspecialchars($t['team_name']) ?></h3>
</div>
<?php endforeach; ?>
</div>

<div class="br-team-panel" id="teamPanel">
    <div class="br-panel-inner" id="panelInner">

        <div class="br-panel-front">
            <div class="br-panel-title" id="panelTitle"></div>

            <!-- Removed coach block -->
            <div class="br-role-block">
                <h4><i class="fas fa-crown"></i> Leader</h4>
                <div id="leaderBlock"></div>
            </div>
            <div class="br-role-block">
                <h4><i class="fas fa-gamepad"></i> Members</h4>
                <div id="memberBlock"></div>
            </div>
            <!-- Removed substitutes block -->

            <button class="br-ban-btn" id="banBtn"><i class="fas fa-ban"></i> Ban Team</button>
        </div>

        <div class="br-panel-back">
            <div class="br-back-header"><i class="fas fa-exclamation-triangle"></i> Write your reason why should admin ban this team and players from this website</div>
            <form id="banForm" method="POST">
                <input type="hidden" name="team_id" id="formTeamId">
                <input type="hidden" name="ban_team" value="1">
                <textarea class="br-back-textarea" id="banReason" name="reason" required placeholder="Enter ban reason..."></textarea>
                <button type="submit" class="br-commit-btn" id="commitBanBtn"><i class="fas fa-paper-plane"></i> Submit</button>
                <button type="button" class="br-back-btn" id="backBtn"><i class="fas fa-arrow-left"></i> Back</button>
            </form>
        </div>

    </div>
</div>

</div>
</div>

<script>
const cards   = document.querySelectorAll('.br-team-card');
const panel = document.getElementById('teamPanel');
const grid = document.getElementById('teamGrid');
const title = document.getElementById('panelTitle');
const leaderBlock = document.getElementById('leaderBlock');
const memberBlock = document.getElementById('memberBlock');
const search = document.getElementById('teamSearch');

const panelInner = document.getElementById('panelInner');
const banBtn = document.getElementById('banBtn');
const backBtn = document.getElementById('backBtn');
const banReason = document.getElementById('banReason');
const formTeamId = document.getElementById('formTeamId');
const banForm = document.getElementById('banForm');

let currentTeamId = null;

cards.forEach(card => {
    card.addEventListener('click', () => {
        cards.forEach(c => c.classList.remove('active'));
        card.classList.add('active');

        panel.classList.add('active');
        panelInner.classList.remove('flip');

        // Store current team ID
        currentTeamId = card.dataset.teamId;
        formTeamId.value = currentTeamId;
        
        title.textContent = card.querySelector('h3').textContent;

        leaderBlock.innerHTML = '';
        memberBlock.innerHTML = '';

        const members = JSON.parse(card.dataset.team);
        let hasLeader = false;
        let hasMember = false;

        members.forEach(m => {
            if (m.role === 'leader') {
                hasLeader = true;
                leaderBlock.innerHTML += `<div class="br-member">👑 ${m.username}</div>`;
            } else if (m.role === 'member') {
                hasMember = true;
                memberBlock.innerHTML += `<div class="br-member">🎮 ${m.username}</div>`;
            }
            // coach and sub roles are ignored (not displayed)
        });

        if (!hasLeader) leaderBlock.innerHTML = `<div class="br-empty">No leader</div>`;
        if (!hasMember) memberBlock.innerHTML = `<div class="br-empty">No members</div>`;
    });
});

search.addEventListener('keyup', () => {
    const q = search.value.toLowerCase();
    cards.forEach(card => {
        card.style.display = card.dataset.name.includes(q) ? 'block' : 'none';
    });
});

banBtn.addEventListener('click', () => {
    if (!currentTeamId) {
        alert("Please select a team first.");
        return;
    }
    panelInner.classList.add('flip');
    banReason.value = '';
});

backBtn.addEventListener('click', () => panelInner.classList.remove('flip'));

// Handle form submission
banForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const reason = banReason.value.trim();
    if (!reason) { 
        alert("Please write a reason for banning."); 
        return; 
    }
    
    // Submit the form
    this.submit();
});

// Auto-hide notifications after 5 seconds
setTimeout(() => {
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s ease';
        setTimeout(() => notification.remove(), 500);
    });
}, 5000);
</script>
</body>
</html>