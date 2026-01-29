<?php
require_once "database/dbConfig.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. DATA FETCHING LOGIC (Encapsulated for both initial load and AJAX)
function getTeamsData($conn, $limit, $page, $search)
{
    $offset = ($page - 1) * $limit;
    $search = $conn->real_escape_string($search);
    $whereClause = $search ? "WHERE t.team_name LIKE '%$search%'" : "";

    // Count total
    $total_res = $conn->query("SELECT COUNT(*) as count FROM teams t $whereClause")->fetch_assoc();
    $total_results = $total_res['count'];
    $total_pages = ceil($total_results / $limit);

    // Main Query
    $sql = "SELECT t.team_id, t.team_name, t.motto, t.logo, 
            GROUP_CONCAT(u.username SEPARATOR ', ') as player_list
            FROM teams t
            LEFT JOIN team_members tm ON t.team_id = tm.team_id
            LEFT JOIN users u ON tm.user_id = u.user_id
            $whereClause
            GROUP BY t.team_id
            LIMIT $limit OFFSET $offset";

    return [
        'result' => $conn->query($sql),
        'total_pages' => $total_pages,
        'page' => $page,
        'search' => $search
    ];
}

// 2. AJAX HANDLER (If this is an AJAX request, return only the grid and pagination)
if (isset($_GET['ajax'])) {
    $limit = 9;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $data = getTeamsData($conn, $limit, $page, $search);

    // Output only the inner content for AJAX
    ob_start();
    include_grid_content($data);
    echo ob_get_clean();
    exit;
}

// Initial Load
$limit = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$data = getTeamsData($conn, $limit, $page, $search);

// Helper function to render the grid (used by both initial load and AJAX)
function include_grid_content($data)
{
    $result = $data['result'];
    $total_pages = $data['total_pages'];
    $page = $data['page'];
    $search = $data['search'];

    // Pagination Window Logic (Show 4 pages starting from current)
    $start_page = $page;
    $end_page = $start_page + 3;

    // Adjust if end exceeds total
    if ($end_page > $total_pages) {
        $end_page = $total_pages;
        $start_page = max(1, $end_page - 3);
    }
?>
    <div class="team-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="team-card" onclick="openTeam('<?= addslashes($row['team_name']) ?>', '<?= addslashes($row['motto']?? '') ?>', '<?= addslashes($row['player_list'] ?? '') ?>')">
                    <div class="card-accent"></div>
                    <div class="photo-box">
                        <img src="uploads/teams/<?= $row['logo'] ?: 'default_team.png' ?>" alt="Team">
                    </div>
                    <div class="info-box">
                        <p class="motto-txt"><?= htmlspecialchars($row['motto'] ?? '') ?></p>

                        <h3 class="name-txt"><?= htmlspecialchars($row['team_name']) ?></h3>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="grid-column: 1/-1; text-align: center; font-size: 1.5rem; opacity: 0.5;">NO TEAMS FOUND MATCHING YOUR SEARCH.</p>
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
        --riot-red: #ff4654;
        --riot-dark: #0f1923;
        --riot-black: #111;
        --riot-gray: #ece8e1;
        --riot-border: rgba(255, 70, 84, 0.3);
    }

    body {
        background-color: var(--riot-dark);
        background-image:
            linear-gradient(rgba(15, 25, 35, 0.95), rgba(15, 25, 35, 0.95)),
            url('https://images.contentstack.io/v3/assets/bltb6530b271fddd0b1/blt29d7c4f6bc072d93/5eb7cdc1b1f02e23d33930ad/V_AGENTS_5b.jpg');
        background-attachment: fixed;
        background-size: cover;
        color: var(--riot-gray);
        font-family: 'Oswald', sans-serif;
    }

    .teams-container {
        max-width: 1200px;
        margin: 60px auto;
        padding: 0 20px;
        position: relative;
    }

    .header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
    }

    .search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .search-input {
        width: 0;
        padding: 10px 0;
        border: none;
        border-bottom: 2px solid var(--riot-red);
        background: transparent;
        color: white;
        transition: width 0.4s ease, padding 0.4s ease;
        outline: none;
        margin-left: -1;
        font-family: 'Teko', sans-serif;
        font-size: 1.5rem;
    }

    .search-input.active {
        width: 250px;
        padding: 10px 15px;
        background: rgba(255, 255, 255, 0.05);
    }

    .search-btn {
        font-size: 1.5rem;
        color: var(--riot-red);
        cursor: pointer;
        background: none;
        border: none;
        z-index: 2;
    }

    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 40px;
    }

    .team-card {
        background: var(--riot-black);
        position: relative;
        border: 1px solid var(--riot-border);
        clip-path: polygon(10% 0, 100% 0, 100% 70%, 90% 100%, 0 100%, 0 30%);
        transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        cursor: pointer;
    }

    .team-card:hover {
        transform: scale(1.02);
        border-color: var(--riot-red);
        box-shadow: 0 0 20px rgba(255, 70, 84, 0.3);
    }

    .photo-box {
        height: 200px;
        position: relative;
        overflow: hidden;
        background: #000;
    }

    .photo-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.7;
        transition: 0.5s;
    }

    .team-card:hover .photo-box img {
        opacity: 1;
        transform: scale(1.1);
    }

    .card-accent {
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--riot-red);
    }

    .info-box {
        padding: 25px;
        background: linear-gradient(135deg, #111 0%, #1a1a1a 100%);
    }

    .name-txt {
        font-family: 'Teko', sans-serif;
        font-size: 2.5rem;
        text-transform: uppercase;
        line-height: 0.9;
        margin: 0;
    }

    .motto-txt {
        color: var(--riot-red);
        text-transform: uppercase;
        font-size: 0.9rem;
        letter-spacing: 2px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 50px;
    }

    .pg-link {
        padding: 10px 18px;
        background: #1a1a1a;
        border: 1px solid #333;
        color: white;
        text-decoration: none;
        font-weight: bold;
        transition: 0.3s;
    }

    .pg-link:hover,
    .pg-link.active {
        background: var(--riot-red);
        border-color: var(--riot-red);
        color: var(--riot-dark);
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 25, 35, 0.98);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: var(--riot-black);
        width: 90%;
        max-width: 600px;
        padding: 50px;
        border: 1px solid var(--riot-red);
        position: relative;
    }

    .close-btn {
        position: absolute;
        top: 20px;
        right: 30px;
        font-size: 2.5rem;
        color: var(--riot-red);
        cursor: pointer;
    }

    .player-pill {
        display: inline-block;
        background: #222;
        padding: 8px 20px;
        margin: 5px;
        border-left: 4px solid var(--riot-red);
        text-transform: uppercase;
    }
</style>

<div class="teams-container">
    <div class="header-flex">
        <h1 style="font-size: 4rem; margin: 0; text-transform: uppercase; letter-spacing: -2px;">Roster <span style="color:var(--riot-red)">//</span></h1>

        <div class="search-wrapper">
            <input type="text" id="teamSearch" class="search-input <?= $search ? 'active' : '' ?>" placeholder="SEARCH TEAM..." onkeyup="handleSearch()" value="<?= htmlspecialchars($search) ?>">
            <button class="search-btn" onclick="toggleSearch()"><i class="fas fa-search"></i></button>
        </div>
    </div>

    <div id="dynamic-content">
        <?php include_grid_content($data); ?>
    </div>
</div>

<div id="teamModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn" onclick="closeTeam()">&times;</span>
        <h2 id="m-name" style="font-size: 3.5rem; margin: 0; text-transform: uppercase; font-family: 'Teko'"></h2>
        <p id="m-motto" style="color: var(--riot-red); font-size: 1.1rem; margin-bottom: 25px; text-transform: uppercase;"></p>
        <div id="m-players"></div>
    </div>
</div>

<script>
    let searchTimer;

    // Toggle Search Bar
    function toggleSearch() {
        const input = document.getElementById('teamSearch');
        input.classList.toggle('active');
        if (input.classList.contains('active')) {
            input.focus();
        }
    }

    // This handles the typing (Debounced to prevent server overload)
    function handleSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            fetchTeams(1); // Always reset to page 1 when searching
        }, 300);
    }

    // AJAX function to fetch data from the server
    function fetchTeams(page) {
        const search = document.getElementById('teamSearch').value;
        const dynamicContent = document.getElementById('dynamic-content');

        dynamicContent.style.opacity = '0.5';

        fetch(`?ajax=1&page=${page}&search=${encodeURIComponent(search)}`)
            .then(response => response.text())
            .then(html => {
                dynamicContent.innerHTML = html;
                dynamicContent.style.opacity = '1';

                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + `?page=${page}&search=${encodeURIComponent(search)}`;
                window.history.pushState({
                    path: newUrl
                }, '', newUrl);
            })
            .catch(err => console.warn('Something went wrong.', err));
    }

    // Modal Logic
    function openTeam(name, motto, players) {
        document.getElementById('m-name').innerText = name;
        document.getElementById('m-motto').innerText = motto;
        let html = '';
        if (players && players.trim() !== '') {
            players.split(',').forEach(p => {
                html += `<div class="player-pill">${p.trim()}</div>`;
            });
        } else {
            html = '<p style="opacity:0.5">No registered members found.</p>';
        }
        document.getElementById('m-players').innerHTML = html;
        document.getElementById('teamModal').style.display = 'flex';
    }

    function closeTeam() {
        document.getElementById('teamModal').style.display = 'none';
    }

    window.onclick = function(e) {
        if (e.target.className === 'modal-overlay') closeTeam();
    }
</script>

<?php
$conn->close();
include('partial/footer.php');
?>