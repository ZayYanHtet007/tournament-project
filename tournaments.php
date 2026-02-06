<?php
include('partial/header.php');
require_once "database/dbConfig.php";

/* =========================
   1. GET GAME & FILTER SELECTION
========================= */
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
// Default to 'upcoming' so it shows only upcoming by default
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'upcoming';

if (!$game_id) {
    die("<div style='color:white; text-align:center; margin-top:100px;'><h1>Invalid selection.</h1><a href='index.php'>Return Home</a></div>");
}

/* =========================
   2. FETCH GAME INFO
========================= */
$stmt = $conn->prepare("SELECT name FROM games WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$game) {
    die("<div style='color:white; text-align:center; margin-top:100px;'><h1>Game not found.</h1><a href='index.php'>Return Home</a></div>");
}

/* =========================
   3. DYNAMIC SQL FILTER LOGIC
========================= */
$current_time = date('Y-m-d H:i:s');

// Base query
$sql = "SELECT t.*, u.username AS organizer_name, COUNT(DISTINCT tt.team_id) AS teams_joined 
        FROM tournaments t
        INNER JOIN users u ON t.organizer_id = u.user_id
        LEFT JOIN tournament_teams tt ON tt.tournament_id = t.tournament_id
        WHERE t.game_id = ? AND t.admin_status = 'approved'";

// Logic to filter based on clicking the icons/tabs
if ($status_filter === 'upcoming') {
    $sql .= " AND t.start_date > '$current_time'";
} elseif ($status_filter === 'ongoing') {
    $sql .= " AND t.start_date <= '$current_time' AND t.registration_deadline >= '$current_time'";
} elseif ($status_filter === 'completed') {
    $sql .= " AND t.registration_deadline < '$current_time'";
}

$sql .= " GROUP BY t.tournament_id ORDER BY t.start_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
:root {
    --riot-red: #ff4655;
    --riot-dark: #0f1923;
    --glass-bg: rgba(20, 20, 20, 0.95);
}

body {
    background: #0a0a0a; /* Solid dark background since video is removed */
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

.game-title {
    font-size: 3.5rem;
    font-weight: 900;
    margin: 0;
    text-transform: uppercase;
}

/* =========================
   ICON TABS LOGIC
========================= */
.filter-wrapper {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
}

.filter-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #1a1a1a;
    border: 1px solid #333;
    text-decoration: none;
    color: #888;
    transition: all 0.3s ease;
    cursor: pointer;
}

.filter-card i {
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.filter-card span {
    font-weight: bold;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 1px;
}

.filter-card:hover {
    background: #252525;
    border-color: #555;
    color: #fff;
}

/* Active State Logic */
.filter-card.active {
    background: var(--riot-red);
    color: #fff;
    border-color: var(--riot-red);
    box-shadow: 0 5px 20px rgba(255, 70, 85, 0.3);
}

/* =========================
   TABLE 
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

.btn-details {
    background: var(--riot-red);
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.8rem;
}

@media (max-width: 768px) {
    .filter-wrapper { flex-direction: column; }
    thead { display: none; }
    td { display: block; text-align: right; padding-left: 50%; position: relative; }
    td::before { content: attr(data-label); position: absolute; left: 20px; color: var(--riot-red); }
}
</style>

<div class="container">
    <div class="section-header">
        <h1 class="game-title"><?= htmlspecialchars($game['name']) ?></h1>
        <p style="opacity:0.6;">Mission Control: Filter by Status</p>
    </div>

    <div class="filter-wrapper">
        <a href="?game_id=<?= $game_id ?>&status=upcoming" class="filter-card <?= $status_filter == 'upcoming' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Upcoming</span>
        </a>
        <a href="?game_id=<?= $game_id ?>&status=ongoing" class="filter-card <?= $status_filter == 'ongoing' ? 'active' : '' ?>">
            <i class="fa-solid fa-circle-play"></i>
            <span>Ongoing</span>
        </a>
        <a href="?game_id=<?= $game_id ?>&status=completed" class="filter-card <?= $status_filter == 'completed' ? 'active' : '' ?>">
            <i class="fa-solid fa-trophy"></i>
            <span>Completed</span>
        </a>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Tournament</th>
                    <th>Organizer</th>
                    <th>Date</th>
                    <th>Teams</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td data-label="Tournament"><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                    <td data-label="Organizer"><?= htmlspecialchars($row['organizer_name']) ?></td>
                    <td data-label="Date"><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                    <td data-label="Teams"><?= $row['teams_joined'] ?> / <?= $row['max_participants'] ?></td>
                    <td data-label="Action">
                        <a class="btn-details" href="tournament_details.php?tournament_id=<?= $row['tournament_id'] ?>">VIEW</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:60px; color:#666;">
                        No <?= htmlspecialchars($status_filter) ?> tournaments found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('partial/footer.php'); ?>