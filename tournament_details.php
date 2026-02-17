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
        g.image AS game_image,
        g.genre AS game_genre
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

// Determine if this is a battle royale tournament (by checking if there are any battleroyal_participants)
$isBattleRoyale = false;
$checkBR = $conn->prepare("SELECT 1 FROM battleroyal_participants WHERE match_id IN (SELECT match_id FROM matches WHERE tournament_id = ?) LIMIT 1");
$checkBR->bind_param("i", $tournament_id);
$checkBR->execute();
$checkBR->store_result();
if ($checkBR->num_rows > 0) {
    $isBattleRoyale = true;
}
$checkBR->close();

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

// Determine gradient for UI (now all forced to red/black in CSS, but keep for fallback)
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

// ---------- ADDITIONAL DATA FETCH BASED ON TOURNAMENT TYPE ----------

$groupStandings = [];
$matchesList = [];
$brStandings = [];
$brMatches = [];

if ($tournament['type'] === 'standard' && !$isBattleRoyale) {
    // Fetch group standings
    $groups = ['A','B','C','D'];
    foreach ($groups as $group) {
        $q = $conn->prepare("
            SELECT gs.*, t.team_name
            FROM group_standings gs
            JOIN teams t ON gs.team_id = t.team_id
            WHERE gs.tournament_id = ? AND gs.group_name = ?
            ORDER BY gs.points DESC, gs.net_game DESC, gs.duration ASC
        ");
        $q->bind_param("is", $tournament_id, $group);
        $q->execute();
        $res = $q->get_result();
        while ($row = $res->fetch_assoc()) {
            $groupStandings[$group][] = $row;
        }
        $q->close();
    }

    // Fetch all matches with team names and aggregated scores
    // Changed order: third_place before final
    $matchQuery = "
        SELECT m.*,
               t1.team_name AS team1_name,
               t2.team_name AS team2_name,
               w.team_name AS winner_name,
               (SELECT SUM(team1_score) FROM match_scores WHERE match_id = m.match_id) AS total_score1,
               (SELECT SUM(team2_score) FROM match_scores WHERE match_id = m.match_id) AS total_score2
        FROM matches m
        LEFT JOIN teams t1 ON m.team1_id = t1.team_id
        LEFT JOIN teams t2 ON m.team2_id = t2.team_id
        LEFT JOIN teams w ON m.winner_team_id = w.team_id
        WHERE m.tournament_id = ?
        ORDER BY FIELD(m.round, 'group', 'quarterfinal', 'semifinal', 'third_place', 'final'),
                 m.group_name, m.match_order
    ";
    $stmt = $conn->prepare($matchQuery);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $matchesList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} elseif ($tournament['type'] === 'singleelimination' && !$isBattleRoyale) {
    // Fetch all matches for single elimination
    $matchQuery = "
        SELECT m.*,
               t1.team_name AS team1_name,
               t2.team_name AS team2_name,
               w.team_name AS winner_name,
               (SELECT SUM(team1_score) FROM match_scores WHERE match_id = m.match_id) AS total_score1,
               (SELECT SUM(team2_score) FROM match_scores WHERE match_id = m.match_id) AS total_score2
        FROM matches m
        LEFT JOIN teams t1 ON m.team1_id = t1.team_id
        LEFT JOIN teams t2 ON m.team2_id = t2.team_id
        LEFT JOIN teams w ON m.winner_team_id = w.team_id
        WHERE m.tournament_id = ?
        ORDER BY FIELD(m.round, 'R256','R128','R64','R32','R16','quarterfinal','semifinal','final'),
                 m.match_id ASC
    ";
    $stmt = $conn->prepare($matchQuery);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $matchesList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} elseif ($isBattleRoyale) {
    // Fetch battle royale standings
    $brStandings = $conn->query("
        SELECT t.team_name,
               SUM(mp.score_points) AS points,
               SUM(mp.kill_count) AS kills
        FROM tournament_teams tt
        JOIN teams t ON tt.team_id = t.team_id
        LEFT JOIN battleroyal_participants mp ON mp.tt_id = tt.id
        WHERE tt.tournament_id = $tournament_id
        GROUP BY tt.id
        ORDER BY points DESC, kills DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // Fetch the three matches with participant details
    $rounds = ['FIRST', 'SECOND', 'THIRD'];
    foreach ($rounds as $round) {
        $match = $conn->prepare("
            SELECT * FROM matches
            WHERE tournament_id = ? AND round = ? AND status = 'completed'
            ORDER BY match_id LIMIT 1
        ");
        $match->bind_param("is", $tournament_id, $round);
        $match->execute();
        $matchData = $match->get_result()->fetch_assoc();
        $match->close();
        if ($matchData) {
            $participants = $conn->prepare("
                SELECT mp.rank_position, mp.kill_count, t.team_name
                FROM battleroyal_participants mp
                JOIN tournament_teams tt ON mp.tt_id = tt.id
                JOIN teams t ON tt.team_id = t.team_id
                WHERE mp.match_id = ?
                ORDER BY mp.rank_position ASC
            ");
            $participants->bind_param("i", $matchData['match_id']);
            $participants->execute();
            $matchData['participants'] = $participants->get_result()->fetch_all(MYSQLI_ASSOC);
            $participants->close();
            $brMatches[] = $matchData;
        }
    }
}

// Determine winner for card if tournament completed
$winnerName = null;
if ($tournament['status'] === 'completed') {
    if ($isBattleRoyale) {
        // Winner is top of standings
        if (!empty($brStandings)) {
            $winnerName = $brStandings[0]['team_name'];
        }
    } else {
        // For standard/single elim, winner is final match winner
        $winnerQuery = $conn->prepare("
            SELECT t.team_name
            FROM matches m
            JOIN teams t ON m.winner_team_id = t.team_id
            WHERE m.tournament_id = ? AND m.round = 'final' AND m.status = 'completed'
            LIMIT 1
        ");
        $winnerQuery->bind_param("i", $tournament_id);
        $winnerQuery->execute();
        $winnerRes = $winnerQuery->get_result();
        if ($row = $winnerRes->fetch_assoc()) {
            $winnerName = $row['team_name'];
        }
        $winnerQuery->close();
    }
}
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

    /* INFO CARDS */
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

    /* Green changed to red */
    .green {
        color: greenyellow;
    }

    /* TABLE DESIGN */
    .table-card {
        background: var(--glass-bg);
        border: 1px solid #333;
        margin-bottom: 30px;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 500px;
    }

    th {
        padding: 20px;
        text-align: left;
        color: var(--riot-red);
        border-bottom: 1px solid #333;
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    td {
        padding: 20px;
        border-bottom: 1px solid #222;
    }

    /* Winner card - red/black */
    .winner-card {
        background: linear-gradient(135deg, #ff4655, #b3002d);
        color: #fff;
        padding: 20px;
        margin-bottom: 30px;
        text-align: center;
        font-size: 1.5rem;
        font-weight: bold;
        border: 2px solid #fff;
    }

    .winner-card i {
        color: #fff;
    }

    /* Group standings - now stacked vertically (up & down) */
    .group-standings {
        display: block;
        margin-bottom: 30px;
    }

    .group-card {
        background: #1a1a1a;
        border: 1px solid #333;
        padding: 20px;
        margin-bottom: 20px;
        overflow-x: auto;
    }

    .group-card h3 {
        color: var(--riot-red);
        margin-bottom: 15px;
        text-transform: uppercase;
    }

    /* Match cards */
    .match-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .match-card {
        background: #1a1a1a;
        border: 1px solid #333;
        padding: 20px;
    }

    .match-card.completed {
        border-color: var(--riot-red);
    }

    /* Special styling for final match */
    .match-card-final {
        border: 3px solid gold;
        box-shadow: 0 0 20px gold;
        transform: scale(1.02);
        background: linear-gradient(145deg, #1e1e1e, #2a2a2a);
    }
    .match-card-final .match-round {
        color: gold;
        font-size: 1.1rem;
    }
    .match-card-final .match-teams {
        font-size: 1.5rem;
    }
    .match-card-final .match-score {
        font-size: 1.3rem;
        color: gold;
    }
    .match-card-final .match-winner {
        color: gold;
    }

    .match-round {
        color: var(--riot-red);
        font-weight: bold;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .match-teams {
        font-size: 1.2rem;
        margin-bottom: 10px;
    }

    .match-schedule {
        color: #888;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .match-score {
        font-size: 1.1rem;
        color: var(--riot-red);
    }

    .match-winner {
        margin-top: 10px;
        color: var(--riot-red);
    }

    /* Battle royale specific */
    .br-standings-table {
        width: 100%;
        margin-bottom: 30px;
    }

    .br-match-detail {
        margin-bottom: 20px;
    }

    /* Buttons & Gradients - all forced to red/black */
    .gradient-red-pink,
    .gradient-purple-indigo,
    .gradient-orange-yellow,
    .gradient-rose-orange,
    .gradient-cyan-indigo,
    .gradient-green-teal,
    .gradient-blue-cyan {
        background: linear-gradient(135deg, #ff4655, #b3002d) !important;
    }

    .gradient-gray {
        background: #333 !important;
    }

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

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .tournament-title {
            font-size: 2rem;
        }

        .info-card {
            min-width: 100%;
        }

        .section-header {
            margin-bottom: 20px;
        }

        /* Hide thead, make td blocks with labels */
        thead {
            display: none;
        }

        td {
            display: block;
            text-align: right;
            padding-left: 50%;
            position: relative;
            border-bottom: 1px solid #333;
        }

        td::before {
            content: attr(data-label);
            position: absolute;
            left: 20px;
            color: var(--riot-red);
            font-weight: bold;
        }

        .group-card table,
        .br-standings-table {
            min-width: 100%;
        }

        .match-grid {
            grid-template-columns: 1fr;
        }

        .winner-card {
            font-size: 1.2rem;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 0 10px;
        }

        td {
            padding: 15px;
            font-size: 0.9rem;
        }

        .btn-details {
            width: 100%;
            text-align: center;
        }
    }

    /* Ensure group tables show all data clearly */
    .group-card table {
        min-width: 600px;
    }

    .group-card th,
    .group-card td {
        white-space: nowrap;
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
            <span class="value green"><?= date('d M Y', strtotime($tournament['start_date'])) ?></span>
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

    <!-- Winner Card -->
    <?php if ($tournament['status'] === 'completed' && $winnerName): ?>
        <div class="winner-card">
            <i class="fas fa-crown"></i> Champion: <?= htmlspecialchars($winnerName) ?> <i class="fas fa-crown"></i>
        </div>
    <?php endif; ?>

    <!-- Registered Teams -->
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
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($teams)): ?>
                    <?php foreach ($teams as $team): ?>
                        <tr>
                            <td data-label="Team Name"><strong><?= htmlspecialchars($team['team_name']) ?></strong></td>
                            <td data-label="Leader"><?= htmlspecialchars($team['leader_name']) ?></td>
                            <td data-label="Roster"><?= $team['player_count'] ?> Players</td>
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

    <!-- Additional tournament content based on type -->
    <?php if ($tournament['type'] === 'standard' && !$isBattleRoyale): ?>
        <!-- Group Standings - now stacked vertically -->
        <?php if (!empty($groupStandings)): ?>
            <div class="section-header" style="margin-top: 40px;">
                <h2 style="font-size: 1.5rem; text-transform: uppercase; margin: 0;">Group Stage Standings</h2>
            </div>
            <div class="group-standings">
                <?php foreach (['A','B','C','D'] as $group): ?>
                    <?php if (isset($groupStandings[$group]) && count($groupStandings[$group]) > 0): ?>
                        <div class="group-card">
                            <h3>Group <?= $group ?></h3>
                            <div style="overflow-x: auto;">
                                <table style="width:100%; min-width: 600px;">
                                    <thead>
                                        <tr>
                                            <th>Team</th>
                                            <th>P</th>
                                            <th>W</th>
                                            <th>L</th>
                                            <th>PTS</th>
                                            <th>NET</th>
                                            <th>DUR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groupStandings[$group] as $team): ?>
                                        <tr>
                                            <td data-label="Team"><?= htmlspecialchars($team['team_name']) ?></td>
                                            <td data-label="P"><?= $team['played'] ?></td>
                                            <td data-label="W"><?= $team['wins'] ?></td>
                                            <td data-label="L"><?= $team['losses'] ?></td>
                                            <td data-label="PTS"><?= $team['points'] ?></td>
                                            <td data-label="NET"><?= $team['net_game'] ?></td>
                                            <td data-label="DUR"><?= gmdate('i:s', $team['duration']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- All Matches - with final special -->
        <?php if (!empty($matchesList)): ?>
            <div class="section-header" style="margin-top: 40px;">
                <h2 style="font-size: 1.5rem; text-transform: uppercase; margin: 0;">All Matches</h2>
            </div>
            <div class="match-grid">
                <?php 
                $currentRound = '';
                foreach ($matchesList as $m): 
                    if ($currentRound !== $m['round']):
                        $currentRound = $m['round'];
                        echo "<div style='grid-column: 1 / -1; margin-top: 20px;'><h3 style='color: var(--riot-red);'>" . ucfirst($currentRound) . "</h3></div>";
                    endif;
                    $team1 = $m['team1_name'] ?? 'TBD';
                    $team2 = $m['team2_name'] ?? 'TBD';
                    $score1 = $m['total_score1'] ?? '?';
                    $score2 = $m['total_score2'] ?? '?';
                    $cardClass = 'match-card';
                    if ($m['status'] === 'completed') {
                        $cardClass .= ' completed';
                    }
                    if ($m['round'] === 'final') {
                        $cardClass .= ' match-card-final';
                    }
                ?>
                    <div class="<?= $cardClass ?>">
                        <div class="match-round">
                            <?= $m['group_name'] ? "Group ".$m['group_name'] : '' ?>
                            <?php if ($m['round'] === 'final'): ?>
                                <i class="fas fa-crown" style="margin-left: 8px; color: gold;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="match-teams"><?= htmlspecialchars($team1) ?> vs <?= htmlspecialchars($team2) ?></div>
                        <?php if ($m['scheduled_time']): ?>
                            <div class="match-schedule"><i class="fas fa-calendar-alt"></i> <?= date('d M Y, h:i A', strtotime($m['scheduled_time'])) ?></div>
                        <?php endif; ?>
                        <?php if ($m['status'] === 'completed'): ?>
                            <div class="match-score"><?= $score1 ?> : <?= $score2 ?></div>
                            <div class="match-winner">Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?></div>
                        <?php else: ?>
                            <div class="match-score">Not played</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($tournament['type'] === 'singleelimination' && !$isBattleRoyale): ?>
        <!-- Single Elimination Matches -->
        <?php if (!empty($matchesList)): ?>
            <div class="section-header" style="margin-top: 40px;">
                <h2 style="font-size: 1.5rem; text-transform: uppercase; margin: 0;">Bracket Matches</h2>
            </div>
            <div class="match-grid">
                <?php 
                $currentRound = '';
                foreach ($matchesList as $m): 
                    if ($currentRound !== $m['round']):
                        $currentRound = $m['round'];
                        echo "<div style='grid-column: 1 / -1; margin-top: 20px;'><h3 style='color: var(--riot-red);'>" . strtoupper($currentRound) . "</h3></div>";
                    endif;
                    $team1 = $m['team1_name'] ?? 'TBD';
                    $team2 = $m['team2_name'] ?? 'TBD';
                    $score1 = $m['total_score1'] ?? '?';
                    $score2 = $m['total_score2'] ?? '?';
                ?>
                    <div class="match-card <?= $m['status'] === 'completed' ? 'completed' : '' ?>">
                        <div class="match-teams"><?= htmlspecialchars($team1) ?> vs <?= htmlspecialchars($team2) ?></div>
                        <?php if ($m['scheduled_time']): ?>
                            <div class="match-schedule"><i class="fas fa-calendar-alt"></i> <?= date('d M Y, h:i A', strtotime($m['scheduled_time'])) ?></div>
                        <?php endif; ?>
                        <?php if ($m['status'] === 'completed'): ?>
                            <div class="match-score"><?= $score1 ?> : <?= $score2 ?></div>
                            <div class="match-winner">Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?></div>
                        <?php else: ?>
                            <div class="match-score">Not played</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($isBattleRoyale): ?>
        <!-- Battle Royale Standings -->
        <?php if (!empty($brStandings)): ?>
            <div class="section-header" style="margin-top: 40px;">
                <h2 style="font-size: 1.5rem; text-transform: uppercase; margin: 0;">Overall Standings</h2>
            </div>
            <div class="table-card">
                <table class="br-standings-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Team</th>
                            <th>Points</th>
                            <th>Kills</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($brStandings as $s): ?>
                        <tr>
                            <td data-label="#"><?= $rank++ ?></td>
                            <td data-label="Team"><?= htmlspecialchars($s['team_name']) ?></td>
                            <td data-label="Points"><?= $s['points'] ?? 0 ?></td>
                            <td data-label="Kills"><?= $s['kills'] ?? 0 ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Battle Royale Matches -->
        <?php if (!empty($brMatches)): ?>
            <div class="section-header" style="margin-top: 40px;">
                <h2 style="font-size: 1.5rem; text-transform: uppercase; margin: 0;">Match Results</h2>
            </div>
            <div class="match-grid">
                <?php foreach ($brMatches as $m): ?>
                    <div class="match-card completed">
                        <div class="match-round"><?= ucfirst(strtolower($m['round'])) ?> Match</div>
                        <?php if ($m['scheduled_time']): ?>
                            <div class="match-schedule"><?= date('d M Y, h:i A', strtotime($m['scheduled_time'])) ?></div>
                        <?php endif; ?>
                        <div style="margin-top: 10px; overflow-x: auto;">
                            <table style="width:100%; min-width: 400px;">
                                <thead>
                                    <tr>
                                        <th>Team</th>
                                        <th>Rank</th>
                                        <th>Kills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($m['participants'] as $p): ?>
                                    <tr>
                                        <td data-label="Team"><?= htmlspecialchars($p['team_name']) ?></td>
                                        <td data-label="Rank"><?= $p['rank_position'] ?></td>
                                        <td data-label="Kills"><?= $p['kill_count'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php include('partial/footer.php'); ?>