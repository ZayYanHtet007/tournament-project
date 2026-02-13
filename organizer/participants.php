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
<link rel="stylesheet" href="../css/organizer/brscore.css">
<style>
/* ================= PARTICIPANTS ================= */
.br-search { 
margin: 20px auto; 
max-width: 360px; 
}
.br-search input {
    width: 100%; 
    padding: 12px 16px; 
    border-radius: 14px;
    background: rgba(0,0,0,0.6); 
    border: 1px solid rgba(0,247,255,0.4); 
    color: #fff;
}
.br-participants { 
    display: flex; 
    gap: 20px; 
    margin-top: 30px; 
    overflow: hidden; 
}
.br-team-grid { 
    flex: 1; 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); 
    gap: 20px; transition: transform 0.5s ease; 
}
.br-team-card { 
    background: rgba(255,255,255,0.06); 
    border-radius: 16px; 
    padding: 20px; 
    text-align: center; 
    cursor: pointer; 
    transition: 0.35s ease; 
    box-shadow: 0 0 20px rgba(0,247,255,0.12); 
}
.br-team-card:hover { 
    transform: translateY(-6px) scale(1.04); 
}
.br-team-card.active { 
    border: 2px solid #00f7ff; 
    box-shadow: 0 0 35px rgba(0,247,255,0.6); 
}
.br-team-card img { 
    width: 80px; 
    height: 80px; 
    border-radius: 50%; 
    object-fit: cover; 
    margin-bottom: 10px; 
}
.br-team-card h3 { 
    color: #00f7ff; 
    font-size: 1.05rem; 
}

/* panel */
.br-team-panel { 
    width: 0; 
    opacity: 0; 
    overflow: hidden; 
    transition: all 0.5s ease;
    background: rgba(0,0,0,0.55); 
    border-radius: 18px; 
    position: relative; 
    perspective: 1000px; 
    flex-shrink: 0; 
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
    width: 100%; top: 0; 
    left: 0; 
}
.br-panel-back { 
    transform: rotateY(180deg); 
}

.br-panel-title { 
    font-size: 1.4rem; 
    text-align: center; 
    color: #00f7ff; 
    margin-bottom: 18px; 
}
.br-role-block { 
    background: rgba(255,255,255,0.06); 
    border-radius: 12px; 
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
}
.br-empty { 
    font-size: 0.85rem; 
    color: #9ca3af; 
    font-style: italic; 
}

.br-ban-btn { 
    background:#dc2626; 
    border:none; 
    color:white; 
    width:100%; 
    padding:10px; 
    border-radius:10px; 
    cursor:pointer; 
    margin-bottom:14px; 
    font-weight:600; 
}
.br-ban-btn:hover { 
    background:#b91c1c; 
}

.br-back-textarea { 
    width: 100%; 
    min-height: 100px; 
    padding: 10px; 
    border-radius: 10px; 
    border: 1px solid #00f7ff; 
    resize: none; 
    margin-bottom: 14px; 
    background: rgba(255,255,255,0.05); 
    color: #fff; 
}
.br-back-header { 
    font-size: 1rem; 
    margin-bottom: 10px; 
    color: #00f7ff; 
}
.br-commit-btn, .br-back-btn { 
    width: 100%; 
    padding: 10px; 
    border-radius: 10px; 
    background: #00f7ff; 
    border: none; 
    color: #000; 
    font-weight: 600; 
    cursor: pointer; 
    margin-bottom: 10px; 
}
.br-commit-btn:hover, .br-back-btn:hover { 
    background: #00cfff; 
}

/* Notification Messages */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    z-index: 1000;
    animation: slideIn 0.3s ease;
}

.success {
    background-color: #10b981;
    color: white;
}

.error {
    background-color: #ef4444;
    color: white;
}

@keyframes slideIn {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

/* responsive */
@media(max-width:900px){
    .br-participants { flex-direction: column; }
    .br-team-panel.active { width: 100%; margin-top: 20px; }
}
</style>
</head>

<body class="br-body">
<div class="br-container">

<!-- Notification Messages -->
<?php if ($successMessage): ?>
<div class="notification success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
<div class="notification error"><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>

<div class="br-title">
    <h1>👥 Teams & Players</h1>
    <p><?= htmlspecialchars($tournament['title']) ?></p>
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

            <div class="br-role-block">
                <h4>Coach</h4>
                <div id="coachBlock"></div>
            </div>
            <div class="br-role-block">
                <h4>Leader</h4>
                <div id="leaderBlock"></div>
            </div>
            <div class="br-role-block">
                <h4>Members</h4>
                <div id="memberBlock"></div>
            </div>
            <div class="br-role-block">
                <h4>Substitutes</h4>
                <div id="subBlock"></div>
            </div>

            <button class="br-ban-btn" id="banBtn">Ban Team</button>
        </div>

        <div class="br-panel-back">
            <div class="br-back-header">Write your reason why should admin ban this team and players from this website</div>
            <form id="banForm" method="POST">
                <input type="hidden" name="team_id" id="formTeamId">
                <input type="hidden" name="ban_team" value="1">
                <textarea class="br-back-textarea" id="banReason" name="reason" required placeholder="Enter ban reason..."></textarea>
                <button type="submit" class="br-commit-btn" id="commitBanBtn">Submit</button>
                <button type="button" class="br-back-btn" id="backBtn">Back</button>
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
const coachBlock = document.getElementById('coachBlock');
const leaderBlock = document.getElementById('leaderBlock');
const memberBlock = document.getElementById('memberBlock');
const subBlock = document.getElementById('subBlock');
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

        coachBlock.innerHTML = '';
        leaderBlock.innerHTML = '';
        memberBlock.innerHTML = '';
        subBlock.innerHTML = '';

        const members = JSON.parse(card.dataset.team);
        let hasCoach = false;
        let hasLeader = false;
        let hasMember = false;
        let hasSub = false;

        members.forEach(m => {
            switch(m.role) {
                case 'coach': hasCoach = true; coachBlock.innerHTML += `<div class="br-member">🧑‍🏫 ${m.username}</div>`; break;
                case 'leader': hasLeader = true; leaderBlock.innerHTML += `<div class="br-member">👑 ${m.username}</div>`; break;
                case 'member': hasMember = true; memberBlock.innerHTML += `<div class="br-member">🎮 ${m.username}</div>`; break;
                case 'sub': hasSub = true; subBlock.innerHTML += `<div class="br-member">🔄 ${m.username}</div>`; break;
            }
        });

        if (!hasCoach) coachBlock.innerHTML = `<div class="br-empty">No coach</div>`;
        if (!hasLeader) leaderBlock.innerHTML = `<div class="br-empty">No leader</div>`;
        if (!hasMember) memberBlock.innerHTML = `<div class="br-empty">No members</div>`;
        if (!hasSub) subBlock.innerHTML = `<div class="br-empty">No substitutes</div>`;
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