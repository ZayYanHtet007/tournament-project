<?php
require_once "database/dbConfig.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. DATA FETCHING LOGIC (UPDATED TO INCLUDE GAME TYPE)
function getTeamsData($conn, $limit, $page, $search, $game_type = '')
{
    $offset = ($page - 1) * $limit;
    $search = $conn->real_escape_string($search);
    $game_type = $conn->real_escape_string($game_type);

    $conditions = [];
    if ($search) {
        $conditions[] = "(t.team_name LIKE '%$search%' OR t.short_name LIKE '%$search%')";
    }
    // Filter by the actual game name in the games table
    if ($game_type) {
        $conditions[] = "g.name = '$game_type'";
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // Updated Count Query with Join
    $total_sql = "SELECT COUNT(*) as count FROM teams t 
                  LEFT JOIN games g ON t.aim_for = g.game_id 
                  $whereClause";
    $total_res = $conn->query($total_sql)->fetch_assoc();
    $total_results = $total_res['count'];
    $total_pages = ceil($total_results / $limit);

    // Main Query: JOIN games table and SELECT g.name
    $sql = "SELECT t.team_id, t.team_name, t.short_name, t.motto, t.logo, 
            g.name AS game_name, -- Fetch the actual game name
            l.username AS leader_name,
            GROUP_CONCAT(u.username SEPARATOR ', ') as player_list
            FROM teams t
            LEFT JOIN games g ON t.aim_for = g.game_id 
            LEFT JOIN users l ON t.leader_id = l.user_id 
            LEFT JOIN team_members tm ON t.team_id = tm.team_id
            LEFT JOIN users u ON tm.user_id = u.user_id
            $whereClause
            GROUP BY t.team_id
            LIMIT $limit OFFSET $offset";

    return [
        'result' => $conn->query($sql),
        'total_pages' => $total_pages,
        'page' => $page,
        'search' => $search,
        'game_type' => $game_type
    ];
}
$games_sql = "select name from games where game_status = 'available' order by name ASC";
$games_list = $conn->query($games_sql);

// 2. AJAX HANDLER
if (isset($_GET['ajax'])) {
    $limit = 9;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $game_type = isset($_GET['game_type']) ? $_GET['game_type'] : '';
    $data = getTeamsData($conn, $limit, $page, $search, $game_type);

    ob_start();
    include_grid_content($data);
    echo ob_get_clean();
    exit;
}

// Initial Load
$limit = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$game_type = isset($_GET['game_type']) ? $_GET['game_type'] : '';
$data = getTeamsData($conn, $limit, $page, $search, $game_type);

// Helper function to render the grid
function include_grid_content($data)
{
    $result = $data['result'];
    $total_pages = $data['total_pages'];
    $page = $data['page'];
    $search = $data['search'];
    $game_type = $data['game_type'];

    $start_page = $page;
    $end_page = $start_page + 3;
    if ($end_page > $total_pages) {
        $end_page = $total_pages;
        $start_page = max(1, $end_page - 3);
    }
?>
    <div class="team-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="team-card" onclick="openTeam(
                    '<?= addslashes($row['team_name']) ?>', 
                    '<?= addslashes($row['short_name'] ?? '') ?>', 
                    '<?= addslashes($row['motto'] ?? '') ?>', 
                    '<?= addslashes($row['leader_name'] ?? 'N/A') ?>', 
                    '<?= addslashes($row['player_list'] ?? '') ?>',
                    '<?= $row['team_id'] ?>'
                )">
                    <div class="card-accent"></div>
                    <div class="photo-box">
                        <img src="images/<?= $row['logo'] ?: 'default_team.png' ?>" alt="Team">
                        <div class="img-overlay"></div>
                    </div>
                    <div class="info-box">
                        <p class="motto-txt">
                            <?= htmlspecialchars($row['game_name'] ?: 'General') ?>
                            <?= !empty($row['motto']) ? ' // ' . htmlspecialchars($row['motto']) : '' ?>
                        </p>
                        <h3 class="name-txt"><?= htmlspecialchars($row['team_name']) ?></h3>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-results" style="grid-column: 1/-1; text-align: center; padding: 50px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--riot-red);"></i>
                <p style="margin-top: 15px;">NO TEAMS FOUND IN DATABASE.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="javascript:void(0)" onclick="fetchTeams(<?= $page - 1 ?>)" class="pg-link"><i class="fas fa-chevron-left"></i> PREV</a>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="javascript:void(0)" onclick="fetchTeams(<?= $i ?>)" class="pg-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="javascript:void(0)" onclick="fetchTeams(<?= $page + 1 ?>)" class="pg-link">NEXT <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
<?php
}

include('partial/header.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Teko:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    :root {
        --riot-red: #ff4d5a;
        --riot-dark: #050505;
        --riot-black: #111;
        --riot-gray: #ece8e1;
        --riot-white: #ffffff;
    }

    body {
        background-color: var(--riot-dark);
        color: var(--riot-gray);
        font-family: 'Oswald', sans-serif;
        margin: 0;
        overflow-x: hidden;
        min-height: 100vh;
        position: relative;
        background-image:
            radial-gradient(circle at 15% 15%, rgba(255, 77, 90, 0.15) 0%, transparent 40%),
            radial-gradient(circle at 85% 85%, rgba(255, 77, 90, 0.15) 0%, transparent 40%),
            linear-gradient(rgba(255, 70, 85, 0.08) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 70, 85, 0.08) 1px, transparent 1px);
        background-size: 100% 100%, 100% 100%, 50px 50px, 50px 50px;
        background-attachment: fixed;
    }

    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
                rgba(255, 70, 85, 0.22) 0%,
                transparent 50%);
        z-index: -1;
        pointer-events: none;
    }

    body::after {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: linear-gradient(to bottom, transparent 0%, rgba(255, 70, 85, 0.04) 50%, transparent 100%);
        background-size: 100% 4px;
        animation: scanline 10s linear infinite;
        pointer-events: none;
        z-index: 0;
    }

    @keyframes scanline {
        0% {
            transform: translateY(-100%);
        }

        100% {
            transform: translateY(100%);
        }
    }

    /* --- ENHANCED PUNCH THROUGH INTRO --- */
    #intro-screen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: black;
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
        animation: fadeOutScreen 0.6s cubic-bezier(0.4, 0, 0.2, 1) 3.2s forwards;
    }

    .intro-3d-text {
        font-family: 'Teko', sans-serif;
        font-size: 18rem;
        font-weight: 700;
        color: white;
        text-transform: uppercase;
        letter-spacing: 15px;
        line-height: 1;
        position: relative;
        /* Masking creates the "Hole" effect better than destination-out in some browsers */
        animation: portalFlyThrough 3.5s cubic-bezier(0.7, 0, 0.3, 1) forwards;
    }

    .loading-bar {
        position: absolute;
        bottom: 20%;
        width: 0%;
        height: 2px;
        background: var(--riot-red);
        box-shadow: 0 0 15px var(--riot-red);
        animation: loadBarFast 2.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }

    @keyframes portalFlyThrough {
        0% {
            transform: scale(0.5);
            opacity: 0;
            letter-spacing: 100px;
            filter: blur(10px);
        }

        20% {
            transform: scale(1);
            opacity: 1;
            letter-spacing: 15px;
            filter: blur(0px);
        }

        70% {
            transform: scale(1.1);
            opacity: 1;
            letter-spacing: 15px;
        }

        100% {
            transform: scale(80);
            opacity: 0;
            letter-spacing: -20px;
        }
    }

    @keyframes loadBarFast {
        0% {
            width: 0;
            opacity: 1;
        }

        80% {
            width: 400px;
            opacity: 1;
        }

        100% {
            width: 100vw;
            opacity: 0;
        }
    }

    @keyframes fadeOutScreen {
        to {
            opacity: 0;
            visibility: hidden;
        }
    }

    .teams-container {
        max-width: 1400px;
        margin: 80px auto;
        padding: 0 150px;
        position: relative;
        z-index: 10;
        opacity: 0;
        animation: fadeInContent 1s ease-out 2.8s forwards;
    }

    @keyframes fadeInContent {
        to {
            opacity: 1;
        }
    }

    .header-flex {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        /* Changed to flex-end for better alignment with filter */
        margin-bottom: 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 20px;
    }

    .page-title {
        font-size: 4.5rem;
        margin: 0;
        text-transform: uppercase;
        font-family: 'Teko', sans-serif;
        line-height: 1;
        letter-spacing: -2px;
    }

    /* GAME FILTER STYLES */
    .game-filter-nav {
        display: flex;
        gap: 15px;
        margin-bottom: 40px;
    }

    .filter-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--riot-gray);
        padding: 8px 25px;
        font-family: 'Teko', sans-serif;
        font-size: 1.2rem;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
    }

    .filter-btn:hover {
        background: rgba(255, 77, 90, 0.1);
        border-color: var(--riot-red);
    }

    .filter-btn.active {
        background: var(--riot-red);
        border-color: var(--riot-red);
        color: white;
    }

    .search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    /* Custom Dropdown Styling */
    .game-filter-nav select.filter-btn {
        background: #0f1923;
        /* Matching team card background */
        color: var(--riot-gray);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 10px 40px 10px 20px;
        font-family: 'Teko', sans-serif;
        font-size: 1.2rem;
        text-transform: uppercase;
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        width: 200px;
        transition: border-color 0.3s;
    }

    .game-filter-nav select.filter-btn:focus {
        border-color: var(--riot-red);
        outline: none;
        box-shadow: 0 0 10px rgba(255, 77, 90, 0.2);
    }

    /* This fixes the "invisible" text in the dropdown list */
    .game-filter-nav select.filter-btn option {
        background-color: #0f1923;
        color: white;
        padding: 10px;
    }

    .search-input {
        width: 0;
        padding: 10px 0;
        border: none;
        border-bottom: 2px solid var(--riot-red);
        background: transparent;
        color: white;
        transition: width 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        outline: none;
        font-family: 'Teko', sans-serif;
        font-size: 1.5rem;
        opacity: 0;
    }

    .search-input.active {
        width: 250px;
        padding: 10px 15px;
        background: rgba(255, 255, 255, 0.05);
        opacity: 1;
    }

    .search-btn {
        font-size: 1.5rem;
        color: var(--riot-red);
        cursor: pointer;
        background: none;
        border: none;
        z-index: 2;
        padding: 10px;
        transition: transform 0.2s;
    }

    .search-btn:hover {
        transform: scale(1.1);
    }

    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 30px;
        margin-bottom: 40px;
    }

    .team-card {
        background: #0f1923;
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.1);
        clip-path: polygon(20px 0, 100% 0, 100% calc(100% - 20px), calc(100% - 20px) 100%, 0 100%, 0 20px);
        transition: all 0.3s ease;
        cursor: pointer;
        overflow: hidden;
    }

    .team-card:hover {
        transform: translateY(-5px);
        border-color: var(--riot-red);
        box-shadow: 0 0 20px rgba(255, 77, 90, 0.2);
    }

    .card-accent {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--riot-red);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
        z-index: 10;
    }

    .team-card:hover .card-accent {
        transform: scaleX(1);
    }

    .photo-box {
        height: 180px;
        position: relative;
        overflow: hidden;
        background: #000;
    }

    .photo-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.6;
        transition: 0.5s;
    }

    .team-card:hover .photo-box img {
        opacity: 1;
        transform: scale(1.1);
    }

    .info-box {
        padding: 20px 25px;
        background: linear-gradient(180deg, rgba(20, 20, 20, 0) 0%, rgba(20, 20, 20, 1) 100%);
    }

    .name-txt {
        font-family: 'Teko', sans-serif;
        font-size: 2.2rem;
        text-transform: uppercase;
        line-height: 0.9;
        margin: 5px 0 0;
        color: white;
    }

    .motto-txt {
        color: var(--riot-red);
        text-transform: uppercase;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 1px;
        margin: 0;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 50px;
    }

    .pg-link {
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        text-decoration: none;
        font-family: 'Teko', sans-serif;
        font-size: 1.2rem;
        transition: 0.3s;
        clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
    }

    .pg-link:hover,
    .pg-link.active {
        background: var(--riot-red);
        border-color: var(--riot-red);
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 11000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(8px);
        padding: 20px;
    }

    .modal-content {
        background: #0f1923;
        width: 100%;
        max-width: 650px;
        padding: 40px;
        border: 1px solid var(--riot-red);
        position: relative;
        box-shadow: 0 0 50px rgba(255, 77, 90, 0.2);
        clip-path: polygon(0 0, 100% 0, 100% calc(100% - 30px), calc(100% - 30px) 100%, 30px 100%, 0 calc(100% - 30px));
    }

    .close-btn {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 2rem;
        color: var(--riot-red);
        cursor: pointer;
        transition: 0.3s;
    }

    .close-btn:hover {
        color: white;
        transform: rotate(90deg);
    }

    .modal-header h2 {
        font-size: 4rem;
        margin: 0;
        line-height: 0.9;
    }

    .modal-short {
        color: transparent;
        -webkit-text-stroke: 1px var(--riot-red);
        font-size: 2.5rem;
        margin-left: 10px;
    }

    .modal-leader {
        margin-top: 15px;
        font-size: 1.1rem;
        border-left: 3px solid var(--riot-red);
        padding-left: 10px;
    }

    #m-players {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .player-card {
        background: rgba(255, 255, 255, 0.05);
        padding: 12px;
        border-left: 3px solid var(--riot-red);
        display: flex;
        align-items: center;
        gap: 10px;
        transition: 0.3s;
        clip-path: polygon(0 0, 100% 0, 100% 100%, 10px 100%, 0 calc(100% - 10px));
    }

    .player-card:hover {
        background: rgba(255, 70, 85, 0.1);
        transform: translateX(5px);
    }

    .player-avatar {
        width: 35px;
        height: 35px;
        background: var(--riot-red);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.8rem;
        color: white;
    }

    .player-info-name {
        font-family: 'Teko', sans-serif;
        font-size: 1.2rem;
        color: #fff;
        text-transform: uppercase;
    }

    .modal-join-btn {
        display: block;
        width: 100%;
        margin-top: 30px;
        padding: 15px;
        background: var(--riot-red);
        border: none;
        color: white;
        font-family: 'Teko', sans-serif;
        font-size: 1.8rem;
        cursor: pointer;
        transition: 0.3s;
        clip-path: polygon(0 0, 100% 0, 100% 100%, 20px 100%, 0 calc(100% - 20px));
    }

    .modal-join-btn:hover {
        background: white;
        color: #000;
    }

    @keyframes glitch-anim-1 {
        0% {
            clip-path: inset(20% 0 80% 0);
            transform: translate(-2px, 1px);
        }

        20% {
            clip-path: inset(60% 0 10% 0);
            transform: translate(2px, -1px);
        }

        40% {
            clip-path: inset(40% 0 50% 0);
            transform: translate(-2px, 2px);
        }

        60% {
            clip-path: inset(80% 0 5% 0);
            transform: translate(2px, -2px);
        }

        80% {
            clip-path: inset(10% 0 70% 0);
            transform: translate(-1px, 1px);
        }

        100% {
            clip-path: inset(30% 0 20% 0);
            transform: translate(1px, -1px);
        }
    }

    @keyframes glitch-anim-2 {
        0% {
            clip-path: inset(10% 0 60% 0);
            transform: translate(2px, -1px);
        }

        20% {
            clip-path: inset(80% 0 5% 0);
            transform: translate(-2px, 1px);
        }

        40% {
            clip-path: inset(30% 0 20% 0);
            transform: translate(2px, 2px);
        }

        60% {
            clip-path: inset(15% 0 80% 0);
            transform: translate(-2px, -2px);
        }

        80% {
            clip-path: inset(55% 0 10% 0);
            transform: translate(1px, -1px);
        }

        100% {
            clip-path: inset(40% 0 30% 0);
            transform: translate(-1px, 1px);
        }
    }

    @media (max-width: 768px) {
        .teams-container {
            margin: 40px auto;
            padding: 0 15px;
        }

        .header-flex {
            flex-direction: column;
            text-align: center;
            align-items: center;
        }

        .page-title {
            font-size: 3rem;
        }

        .game-filter-nav {
            flex-wrap: wrap;
            justify-content: center;
        }

        .search-input.active {
            width: 100%;
        }

        .team-grid {
            grid-template-columns: 1fr;
        }

        .modal-header h2 {
            font-size: 3rem;
        }
    }
</style>

<div id="intro-screen">
    <div class="intro-wrapper">
        <div class="intro-3d-text" data-text="ROSTER">ROSTER</div>
    </div>
    <div class="loading-bar"></div>
</div>

<div class="teams-container">
    <div class="header-flex">
        <h1 class="page-title">Roster <span style="color:var(--riot-red)">//</span></h1>
        <div class="search-wrapper">
            <input type="text" id="teamSearch" class="search-input <?= $search ? 'active' : '' ?>" placeholder="SEARCH TEAM..." onkeyup="handleSearch()" value="<?= htmlspecialchars($search) ?>">
            <button class="search-btn" onclick="toggleSearch()"><i class="fas fa-search"></i></button>
        </div>
    </div>

    <div class="game-filter-nav">
        <div class="filter-wrapper" style="position: relative; display: inline-block;">
            <select class="filter-btn"
                onchange="filterGame(this.value)"
                style="appearance: none; padding-right: 40px; background-color: rgba(255, 255, 255, 0.05); color: var(--riot-gray); border: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer;">

                <option value="" <?= $game_type == '' ? 'selected' : '' ?>>ALL GAMES</option>

                <?php if ($games_list && $games_list->num_rows > 0): ?>
                    <?php while ($game = $games_list->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($game['name']) ?>" <?= $game_type == $game['name'] ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($game['name'])) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <i class="fas fa-chevron-down" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--riot-red);"></i>
        </div>
    </div>

    <div id="dynamic-content">
        <?php include_grid_content($data); ?>
    </div>
</div>

<div id="teamModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn" onclick="closeTeam()">&times;</span>
        <div class="modal-header">
            <h2 style="font-family: 'Teko'; text-transform: uppercase;">
                <span id="m-name"></span>
                <span id="m-short" class="modal-short"></span>
            </h2>
            <div class="modal-leader">
                <i class="fas fa-crown" style="color:#ffd700; margin-right:5px;"></i> LEADER: <span id="m-leader" style="color:white; font-weight:bold;"></span>
            </div>
            <p id="m-motto" style="color: var(--riot-red); font-size: 1rem; margin-top: 15px; text-transform: uppercase; font-style: italic; opacity: 0.8;"></p>
        </div>
        <h4 style="color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:2px; margin: 25px 0 10px; font-size:0.9rem;">Active Agents</h4>
        <div id="m-players"></div>
        <button id="m-join-btn" class="modal-join-btn">Request to Join</button>
    </div>
</div>

<script>
    let searchTimer;
    let currentGameType = '<?= $game_type ?>';

    window.addEventListener('mousemove', e => {
        const x = (e.clientX / window.innerWidth) * 100;
        const y = (e.clientY / window.innerHeight) * 100;
        document.body.style.setProperty('--mouse-x', x + '%');
        document.body.style.setProperty('--mouse-y', y + '%');
    });

    function toggleSearch() {
        const input = document.getElementById('teamSearch');
        input.classList.toggle('active');
        if (input.classList.contains('active')) {
            input.focus();
        }
    }

    function handleSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            fetchTeams(1);
        }, 300);
    }

    // NEW FILTER FUNCTION
    function filterGame(type) {
        currentGameType = type;

        // Update Active States
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.innerText.toLowerCase() === type.toLowerCase() || (type === '' && btn.innerText === 'All Games')) {
                btn.classList.add('active');
            }
        });

        fetchTeams(1);
    }

    function fetchTeams(page) {
        const search = document.getElementById('teamSearch').value;
        const dynamicContent = document.getElementById('dynamic-content');
        dynamicContent.style.opacity = '0.5';

        const url = `?ajax=1&page=${page}&search=${encodeURIComponent(search)}&game_type=${encodeURIComponent(currentGameType)}`;

        fetch(url)
            .then(response => response.text())
            .then(html => {
                dynamicContent.innerHTML = html;
                dynamicContent.style.opacity = '1';

                const pushUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + `?page=${page}&search=${encodeURIComponent(search)}&game_type=${encodeURIComponent(currentGameType)}`;
                window.history.pushState({
                    path: pushUrl
                }, '', pushUrl);
            })
            .catch(err => {
                console.warn('Something went wrong.', err);
                dynamicContent.style.opacity = '1';
            });
    }

    function openTeam(name, short, motto, leader, players, teamId) {
        document.getElementById('m-name').innerText = name;
        document.getElementById('m-short').innerText = short ? `[${short}]` : '';
        document.getElementById('m-motto').innerText = motto ? `"${motto}"` : '';
        document.getElementById('m-leader').innerText = leader;

        let html = '';
        if (players && players.trim() !== '') {
            players.split(',').forEach(p => {
                const nameTrimmed = p.trim();
                const initial = nameTrimmed.charAt(0).toUpperCase();
                html += `
                    <div class="player-card">
                        <div class="player-avatar">${initial}</div>
                        <div class="player-info-name">${nameTrimmed}</div>
                    </div>`;
            });
        } else {
            html = '<p style="opacity:0.5; font-style:italic;">No registered agents found.</p>';
        }
        document.getElementById('m-players').innerHTML = html;

        const joinBtn = document.getElementById('m-join-btn');
        joinBtn.onclick = function() {
            requestJoin(teamId);
        };
        document.getElementById('teamModal').style.display = 'flex';
    }

    function closeTeam() {
        document.getElementById('teamModal').style.display = 'none';
    }

    function requestJoin(teamId) {
        if (confirm("Do you want to send a request to join this team?")) {
            const joinBtn = document.getElementById('m-join-btn');
            const originalText = joinBtn.innerText;
            joinBtn.innerText = "SENDING...";
            joinBtn.disabled = true;
            const formData = new URLSearchParams();
            formData.append('team_id', teamId);
            fetch('player/request_join.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                })
                .then(async response => {
                    const text = await response.text();
                    if (!response.ok) throw new Error(text || "Server Error");
                    return text;
                })
                .then(message => {
                    alert(message);
                    closeTeam();
                })
                .catch(error => {
                    alert("Failed: " + error.message);
                })
                .finally(() => {
                    joinBtn.innerText = originalText;
                    joinBtn.disabled = false;
                });
        }
    }
    window.onclick = function(e) {
        if (e.target.className === 'modal-overlay') closeTeam();
    }
</script>

<?php
$conn->close();
include('partial/footer.php');
?>