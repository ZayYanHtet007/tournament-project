<?php
include('partial/header.php');
require_once "database/dbConfig.php";

// Get game_id from URL
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
if (!$game_id) {
    die("Invalid game selection.");
}

// Fetch the game info
$stmt = $conn->prepare("SELECT name, image FROM games WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game_result = $stmt->get_result();
$game = $game_result->fetch_assoc();
$stmt->close();

// Fetch tournaments and count teams/players for each tournament
$sql = "
    SELECT 
        t.*,
        u.username AS organizer_name,
        g.name AS game_name,
        g.image AS game_image,
        COUNT(DISTINCT tt.team_id) AS teams_joined,
        COUNT(DISTINCT tm.user_id) AS players_joined
    FROM tournaments t
    INNER JOIN users u ON t.organizer_id = u.user_id
    INNER JOIN games g ON t.game_id = g.game_id
    LEFT JOIN tournament_teams tt ON tt.tournament_id = t.tournament_id
    LEFT JOIN teams te ON te.team_id = tt.team_id
    LEFT JOIN team_members tm ON tm.team_id = te.team_id
    WHERE t.game_id = ? AND t.admin_status = 'approved'
    GROUP BY t.tournament_id
    ORDER BY t.start_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$result = $stmt->get_result();

$tournaments = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tournaments[] = $row;
    }
}
$stmt->close();

// UI gradient mapping (Riot Style Colors)
$gradients = [
    'League of Legends' => 'red-pink',
    'Dota 2'            => 'purple-indigo',
    'Counter-Strike '   => 'orange-yellow',
    'Valorant'          => 'rose-orange',
    'PUBG'              => 'cyan-indigo',
    'MLBB'              => 'red-pink',
    'FIFA 24'           => 'green-teal'
];
$gradient = $gradients[$game['name']] ?? 'blue-cyan';
$image = !empty($game['image']) ? $game['image'] : 'default.png';
?>

<style>
/* RIOT STYLE THEME */
:root {
    --riot-red: #ff4655;
    --riot-dark: #0f1923;
    --riot-light: #ece8e1;
    --riot-gray: #1f2326;
    --bg-dark: #080d12;
}

body {
    background-color: var(--bg-dark);
    color: var(--riot-light);
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    overflow-x: hidden;
}

.main-wrapper {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    z-index: -1;
    background: radial-gradient(circle at center, #1a2a33 0%, #080d12 100%);
}

.grid-overlay {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), 
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px;
}

/* FADE ANIMATION CLASSES */
.game-card {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.6s ease-out, transform 0.6s ease-out;
}

.game-card.reveal {
    opacity: 1;
    transform: translateY(0);
}

/* Gradient Classes */
.gradient-red-pink { background: linear-gradient(135deg, #ff4655, #ff858d); color: #fff; }
.gradient-purple-indigo { background: linear-gradient(135deg, #7b2ff7, #3f51b5); color: #fff; }
.gradient-orange-yellow { background: linear-gradient(135deg, #ff9800, #ffeb3b); color: #000; }
.gradient-rose-orange { background: linear-gradient(135deg, #f43f5e, #fb923c); color: #fff; }
.gradient-cyan-indigo { background: linear-gradient(135deg, #06b6d4, #6366f1); color: #fff; }
.gradient-green-teal { background: linear-gradient(135deg, #10b981, #14b8a6); color: #fff; }
.gradient-blue-cyan { background: linear-gradient(135deg, #3b82f6, #06b6d4); color: #fff; }

.gradient-text-alt {
    background: linear-gradient(90deg, #ff4655, #ece8e1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-transform: uppercase;
    font-weight: 900;
    letter-spacing: 2px;
}

.games-section { padding: 80px 0; position: relative; }

.section-header {
    text-align: left;
    margin-bottom: 60px;
    border-left: 5px solid var(--riot-red);
    padding-left: 20px;
    display: block;
    max-width: fit-content;
}

.section-title { font-size: 3.5rem; margin: 0; }
.section-subtitle { opacity: 0.7; font-size: 1.1rem; }

/* RESPONSIVE CONTAINER */
.container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Sidebar Offset for Desktop */
@media (min-width: 1200px) {
    .container { margin-left: 280px; }
}

/* Grid & Cards */
.games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
    justify-content: center;
}

.game-card {
    background: var(--riot-gray);
    position: relative;
    padding: 40px 30px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    clip-path: polygon(0 0, 100% 0, 100% 90%, 90% 100%, 0 100%);
    /* CENTER CONTENT */
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.game-card.reveal:hover {
    transform: translateY(-10px) scale(1.02);
    border-color: var(--riot-red);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.game-icon {
    width: 100px;
    height: 100px;
    margin-bottom: 25px;
    padding: 5px;
    clip-path: polygon(25% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 25%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.game-img {
    max-width: 80%;
    max-height: 80%;
    object-fit: contain;
}

.game-name {
    font-size: 1.6rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 25px;
    letter-spacing: 1px;
    width: 100%;
}

.game-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
    width: 100%;
    border-top: 1px solid rgba(255,255,255,0.05);
    padding-top: 20px;
}

.game-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.game-stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    opacity: 0.5;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.game-stat-value {
    font-weight: 600;
    font-size: 1rem;
}

/* Button Riot Style */
.btn-game {
    width: 100%;
    display: block;
    text-align: center;
    padding: 15px;
    text-transform: uppercase;
    font-weight: 900;
    text-decoration: none;
    letter-spacing: 2px;
    position: relative;
    transition: 0.3s;
}

.btn-game:hover {
    filter: brightness(1.2);
    box-shadow: 0 0 20px rgba(255, 70, 85, 0.4);
}

/* Glow Effects */
.game-glow {
    position: absolute;
    bottom: -20px;
    right: -20px;
    width: 120px;
    height: 120px;
    filter: blur(50px);
    opacity: 0.15;
    z-index: -1;
}

/* Mobile Adjustments */
@media (max-width: 1199px) {
    .container { margin-left: auto; margin-right: auto; }
    .section-header { text-align: center; border-left: none; border-bottom: 4px solid var(--riot-red); padding-left: 0; padding-bottom: 10px; margin: 0 auto 60px auto; }
}

@media (max-width: 768px) {
    .section-title { font-size: 2.5rem; }
    .games-grid { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto; }
}

.strike-overlay {
    position: fixed;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}
.strike {
    position: absolute;
    background: rgba(255, 70, 85, 0.05);
    transform: rotate(-45deg);
}
.strike-1 { width: 100%; height: 2px; top: 20%; }
.strike-2 { width: 100%; height: 100px; top: 50%; }
</style>

<div class="strike-overlay">
    <div class="strike strike-1"></div>
    <div class="strike strike-2"></div>
</div>

<div class="main-wrapper">
    <div class="bg-gradient"></div>
    <div class="grid-overlay"></div>
</div>

<section class="games-section" id="tournaments">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">
                <span class="gradient-text-alt">
                    <?php echo htmlspecialchars($game['name'] ?? 'Tournaments'); ?>
                </span>
            </h2>
            <p class="section-subtitle">
                Browse all tournaments and secure your spot in the arena.
            </p>
        </div>

        <div class="games-grid">
            <?php if (empty($tournaments)): ?>
                <div class="game-card reveal" style="grid-column: 1/-1;">
                    <p>No active missions found for this game yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tournaments as $index => $tournament): ?>
                    <div class="game-card">
                        <div class="game-bg gradient-<?php echo $gradient; ?>"></div>

                        <div class="game-icon gradient-<?php echo $gradient; ?>">
                            <img src="images/games/<?php echo htmlspecialchars($image); ?>"
                                alt="<?php echo htmlspecialchars($game['name']); ?>"
                                class="game-img">
                        </div>

                        <h3 class="game-name">
                            <?php echo htmlspecialchars($tournament['title']); ?>
                        </h3>

                        <div class="game-stats">
                            <div class="game-stat">
                                <span class="game-stat-label">Organizer</span>
                                <span class="game-stat-value">
                                    <?php echo htmlspecialchars($tournament['organizer_name']); ?>
                                </span>
                            </div>

                            <div class="game-stat">
                                <span class="game-stat-label">Start Date</span>
                                <span class="game-stat-value">
                                    <?php echo date('d M Y', strtotime($tournament['start_date'])); ?>
                                </span>
                            </div>

                            <div class="game-stat">
                                <span class="game-stat-label">Teams</span>
                                <span class="game-stat-value">
                                    <?php echo (int)$tournament['teams_joined']; ?> / <?php echo (int)$tournament['max_participants']; ?>
                                </span>
                            </div>

                            <div class="game-stat">
                                <span class="game-stat-label">Team Size</span>
                                <span class="game-stat-value">
                                    <?php echo (int)$tournament['team_size']; ?>v<?php echo (int)$tournament['team_size']; ?>
                                </span>
                            </div>
                        </div>

                        <a href="tournament_details.php?tournament_id=<?php echo (int)$tournament['tournament_id']; ?>"
                            class="btn-game gradient-<?php echo $gradient; ?>">
                            View Details
                        </a>

                        <div class="game-glow gradient-<?php echo $gradient; ?>"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.game-card');
        
        const observerOptions = {
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal');
                } else {
                    entry.target.classList.remove('reveal');
                }
            });
        }, observerOptions);

        cards.forEach(card => {
            observer.observe(card);
        });
    });
</script>

<?php include('partial/footer.php'); ?>