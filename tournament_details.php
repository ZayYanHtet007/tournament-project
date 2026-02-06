<?php
include('partial/header.php');
require_once "database/dbConfig.php";

// Get tournament_id from URL
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
if (!$tournament_id) {
    die("<div style='color:white; text-align:center; margin-top:100px;'><h1>Invalid selection.</h1><a href='index.php'>Return Home</a></div>");
}

// Fetch tournament info
$sql = "
    SELECT 
        t.*, 
        u.username AS organizer_name, 
        g.name AS game_name, 
        g.image AS game_image
    FROM tournaments t
    INNER JOIN users u ON t.organizer_id = u.user_id
    INNER JOIN games g ON t.game_id = g.game_id
    WHERE t.tournament_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$tournament = $result->fetch_assoc();
$stmt->close();

if (!$tournament) {
    die("<div style='color:white; text-align:center; margin-top:100px;'><h1>Tournament not found.</h1><a href='index.php'>Return Home</a></div>");
}

// Fetch registered teams and players
$sqlTeams = "
    SELECT te.team_id, te.team_name, u.username AS leader_name, COUNT(tm.user_id) AS player_count
    FROM tournament_teams tt
    INNER JOIN teams te ON tt.team_id = te.team_id
    INNER JOIN users u ON te.leader_id = u.user_id
    LEFT JOIN team_members tm ON tm.team_id = te.team_id
    WHERE tt.tournament_id = ?
    GROUP BY te.team_id
    ORDER BY te.team_name ASC
";
$stmt = $conn->prepare($sqlTeams);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$resultTeams = $stmt->get_result();
$teams = [];
while ($row = $resultTeams->fetch_assoc()) {
    $teams[] = $row;
}
$stmt->close();

// Determine gradient for UI
$gradients = [
    'League of Legends' => 'red-pink',
    'Dota 2'            => 'purple-indigo',
    'Counter-Strike'    => 'orange-yellow',
    'Valorant'          => 'rose-orange',
    'PUBG'              => 'cyan-indigo',
    'MLBB'              => 'red-pink',
    'FIFA 24'           => 'green-teal'
];
$gradient = $gradients[$tournament['game_name']] ?? 'blue-cyan';
$image = !empty($tournament['game_image']) ? $tournament['game_image'] : 'default.png';

// Check if registration is open
$today = date('Y-m-d');
$registration_open = ($today >= $tournament['registration_start_date'] && $today <= $tournament['registration_deadline']);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
:root {
    --riot-red: #ff4655;
    --riot-dark: #0f1923;
    --glass-bg: rgba(20, 20, 20, 0.95);
}

body {
    background: #0a0a0a;
    color: #ece8e1;
    font-family: 'Segoe UI', sans-serif;
    margin: 0;
}

.container {
    max-width: 1100px;
    margin: 60px auto;
    padding: 0 20px;
}

.section-header {
    border-left: 6px solid var(--riot-red);
    padding-left: 20px;
    margin-bottom: 40px;
}

.tournament-title {
    font-size: 3.5rem;
    font-weight: 900;
    margin: 0;
    text-transform: uppercase;
}

/* =========================
   INFO CARDS (From Filter Style)
========================= */
.info-wrapper {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.info-card {
    flex: 1;
    min-width: 180px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #1a1a1a;
    border: 1px solid #333;
    color: #fff;
    transition: all 0.3s ease;
}

.info-card i {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--riot-red);
}

.info-card span.label {
    font-weight: bold;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 1px;
    color: #888;
    margin-bottom: 5px;
}

.info-card span.value {
    font-weight: 900;
    font-size: 1.1rem;
    text-transform: uppercase;
}

/* =========================
   TABLE DESIGN
========================= */
.table-card {
    background: var(--glass-bg);
    border: 1px solid #333;
}

table { width: 100%; border-collapse: collapse; }
th {
    padding: 20px;
    text-align: left;
    color: var(--riot-red);
    border-bottom: 1px solid #333;
    font-size: 0.8rem;
    text-transform: uppercase;
}
td { padding: 20px; border-bottom: 1px solid #222; }

/* Buttons & Gradients */
.gradient-red-pink { background: linear-gradient(135deg, #ff4655, #ff858d); }
.gradient-purple-indigo { background: linear-gradient(135deg, #7b2ff7, #3f51b5); }
.gradient-orange-yellow { background: linear-gradient(135deg, #ff9800, #ffeb3b); }
.gradient-rose-orange { background: linear-gradient(135deg, #f43f5e, #fb923c); }
.gradient-cyan-indigo { background: linear-gradient(135deg, #06b6d4, #6366f1); }
.gradient-green-teal { background: linear-gradient(135deg, #10b981, #14b8a6); }
.gradient-blue-cyan { background: linear-gradient(135deg, #3b82f6, #06b6d4); }
.gradient-gray { background: #333; }

.btn-details {
    display: inline-block;
    color: white;
    padding: 12px 24px;
    text-decoration: none;
    font-weight: 900;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    border: none;
    cursor: pointer;
    margin-bottom: 40px;
}

@media (max-width: 768px) {
    .info-wrapper { flex-direction: column; }
    thead { display: none; }
    td { display: block; text-align: right; padding-left: 50%; position: relative; }
    td::before { content: attr(data-label); position: absolute; left: 20px; color: var(--riot-red); }
}
</style>

<div class="container">
    <div class="section-header">
        <h1 class="tournament-title"><?= htmlspecialchars($tournament['title']) ?></h1>
        <p style="opacity:0.6; text-transform: uppercase; letter-spacing: 1px;">
            <?= htmlspecialchars($tournament['game_name']) ?> Tournament by <?= htmlspecialchars($tournament['organizer_name']) ?>
        </p>
    </div>

    <div class="info-wrapper">
        <div class="info-card">
            <i class="fa-solid fa-trophy"></i>
            <span class="label">Prize Pool</span>
            <span class="value" style="color:var(--riot-red); font-size: 1.4rem;">$<?= number_format($tournament['prize_pool'], 2) ?></span>
        </div>
        <div class="info-card">
            <i class="fa-solid fa-users"></i>
            <span class="label">Team Size</span>
            <span class="value"><?= (int)$tournament['team_size'] ?> Players</span>
        </div>
        <div class="info-card">
            <i class="fa-solid fa-calendar"></i>
            <span class="label">Start Date</span>
            <span class="value"><?= date('d M Y', strtotime($tournament['start_date'])) ?></span>
        </div>
        <div class="info-card">
            <i class="fa-solid fa-chart-pie"></i>
            <span class="label">Slots</span>
            <span class="value"><?= count($teams) ?> / <?= $tournament['max_participants'] ?></span>
        </div>
    </div>

    <div class="action-bar">
        <?php if ($registration_open): ?>
            <a href="announceDetail.php?tournament_id=<?= $tournament_id ?>" class="btn-details gradient-<?= $gradient ?>">Register Now</a>
        <?php else: ?>
            <span class="btn-details gradient-gray">Registration Closed</span>
        <?php endif; ?>
    </div>

    <div class="section-header" style="margin-top: 20px;">
        <h2 style="font-size: 1.5rem; text-transform: uppercase; margin: 0;">Registered Teams</h2>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Leader</th>
                    <th>Roster</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($teams)): ?>
                <?php foreach ($teams as $team): ?>
                <tr>
                    <td data-label="Team Name"><strong><?= htmlspecialchars($team['team_name']) ?></strong></td>
                    <td data-label="Leader"><?= htmlspecialchars($team['leader_name']) ?></td>
                    <td data-label="Roster"><?= $team['player_count'] ?> Players</td>
                    <td data-label="Status">
                        <span style="color: #00ff88; font-size: 0.75rem; font-weight: 900; text-transform: uppercase;">Confirmed</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding:60px; color:#666;">
                        No teams have joined this tournament yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('partial/footer.php'); ?>