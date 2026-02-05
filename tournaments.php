<?php
include('partial/header.php');
require_once "database/dbConfig.php";

/* =========================
   1. GET GAME SELECTION
========================= */
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
if (!$game_id) {
    die("<div style='color:white; text-align:center; margin-top:100px;'>
        <h1>Invalid game selection.</h1>
        <a href='index.php'>Return Home</a>
    </div>");
}

/* =========================
   2. FETCH GAME INFO
========================= */
$stmt = $conn->prepare("SELECT name, image FROM games WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$resultGame = $stmt->get_result();
$game = $resultGame->fetch_assoc();
$stmt->close();

if (!$game) {
    die("<div style='color:white; text-align:center; margin-top:100px;'>
        <h1>Game not found.</h1>
        <a href='index.php'>Return Home</a>
    </div>");
}

/* =========================
   3. VIDEO MAPPING
========================= */
$game_videos = [
    'Dota 2'            => 'Smnqsde5Sg0',
    'League of Legends' => 'mDYqT0_9VR4',
    'Counter-Strike'    => 'edYCtaNueQY',
    'Valorant'          => 'e_E9W2vsRbQ',
    'PUBG'              => 'u1wprFtkMLg',
    'MLBB'              => 'YfPOn7iV1nE',
    'FIFA 24'           => 'vMwwZ898t60',
    'Mobile Legends'    => 'YfPOn7iV1nE'
];

$current_game_name = $game['name'];
$youtube_id = $game_videos[$current_game_name] ?? '07IsS8p3B30';

/* =========================
   4. FETCH TOURNAMENTS
========================= */
$sql = "
    SELECT 
        t.*,
        u.username AS organizer_name,
        g.name AS game_name,
        COUNT(DISTINCT tt.team_id) AS teams_joined
    FROM tournaments t
    INNER JOIN users u ON t.organizer_id = u.user_id
    INNER JOIN games g ON t.game_id = g.game_id
    LEFT JOIN tournament_teams tt ON tt.tournament_id = t.tournament_id
    WHERE t.game_id = ? AND t.admin_status = 'approved'
    GROUP BY t.tournament_id
    ORDER BY t.start_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<style>
:root {
    --riot-red: #ff4655;
    --riot-dark: #0f1923;
    --glass-bg: rgba(15, 25, 35, 0.85);
}

body {
    background: #000;
    color: #ece8e1;
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    margin: 0;
    overflow-x: hidden;
}

/* =========================
   VIDEO BACKGROUND (FADE-IN)
========================= */
.video-container {
    position: fixed;
    inset: 0;
    z-index: -2;
    pointer-events: none;
    opacity: 0;
    transition: opacity 2s ease-in-out;
}

.video-container.loaded {
    opacity: 1;
}

#bg-video-player {
    width: 100vw;
    height: 56.25vw;
    min-width: 177.77vh;
    min-height: 100vh;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.video-overlay {
    position: fixed;
    inset: 0;
    background: radial-gradient(circle, rgba(0,0,0,.25), rgba(15,25,35,.95));
    z-index: -1;
}

/* =========================
   MAIN CONTENT
========================= */
.container {
    max-width: 1100px;
    margin: 100px auto;
    padding: 0 20px;
}

.section-header {
    border-left: 8px solid var(--riot-red);
    padding-left: 20px;
    margin-bottom: 50px;
}

.game-title {
    font-size: 4rem;
    font-weight: 900;
    margin: 0;
    text-transform: uppercase;
}

/* =========================
   TABLE DESIGN
========================= */
.table-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.1);
    box-shadow: 0 20px 50px rgba(0,0,0,.6);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    padding: 20px;
    text-transform: uppercase;
    color: var(--riot-red);
    font-size: .8rem;
    border-bottom: 2px solid rgba(255,70,85,.3);
}

td {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,.05);
}

tr:hover td {
    background: rgba(255,255,255,.03);
}

.btn-details {
    background: var(--riot-red);
    color: #fff;
    padding: 10px 20px;
    text-decoration: none;
    font-weight: bold;
    text-transform: uppercase;
    transition: .3s;
}

.btn-details:hover {
    background: transparent;
    border: 1px solid var(--riot-red);
    box-shadow: 0 0 15px var(--riot-red);
    color: var(--riot-red);
}

/* MOBILE */
@media (max-width: 768px) {
    .game-title { font-size: 2.5rem; }
    thead { display: none; }
    td {
        display: block;
        text-align: right;
        padding-left: 50%;
        position: relative;
    }
    td::before {
        content: attr(data-label);
        position: absolute;
        left: 20px;
        color: var(--riot-red);
        font-weight: bold;
    }
}
</style>

<div class="video-container" id="videoWrap">
    <div id="bg-video-player"></div>
</div>
<div class="video-overlay"></div>

<div class="container">
    <div class="section-header">
        <h1 class="game-title"><?= htmlspecialchars($game['name']) ?></h1>
        <p style="opacity:.6; letter-spacing:3px;">Active Tournaments & Missions</p>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Tournament</th>
                    <th>Organizer</th>
                    <th>Date</th>
                    <th>Teams</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td data-label="Tournament"><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                    <td data-label="Organizer"><?= htmlspecialchars($row['organizer_name']) ?></td>
                    <td data-label="Date"><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                    <td data-label="Teams">
                        <span style="color:var(--riot-red)"><?= $row['teams_joined'] ?></span>
                        / <?= $row['max_participants'] ?>
                    </td>
                    <td data-label="View">
                        <a class="btn-details" href="tournament_details.php?tournament_id=<?= $row['tournament_id'] ?>">
                            View Dossier
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:80px; opacity:.6;">
                        No active tournaments found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var tag = document.createElement('script');
tag.src = "https://www.youtube.com/iframe_api";
document.body.appendChild(tag);

var player;
var videoLoaded = false;
var fallbackTimer;

function onYouTubeIframeAPIReady() {
    fallbackTimer = setTimeout(showFallback, 4000); // 4s timeout

    player = new YT.Player('bg-video-player', {
        videoId: '<?= $youtube_id ?>',
        playerVars: {
            autoplay: 1,
            controls: 0,
            mute: 1,
            loop: 1,
            playlist: '<?= $youtube_id ?>',
            modestbranding: 1,
            iv_load_policy: 3
        },
        events: {
            onReady: function(e) {
                e.target.mute();
                e.target.playVideo();
            },
            onStateChange: function(e) {
                if (e.data === YT.PlayerState.PLAYING && !videoLoaded) {
                    videoLoaded = true;
                    clearTimeout(fallbackTimer);
                    document.getElementById('videoWrap').classList.add('loaded');
                }
            },
            onError: showFallback
        }
    });
}

function showFallback() {
    console.warn("YouTube failed — using fallback image");
    document.getElementById('videoWrap').classList.add('loaded');
}
</script>
