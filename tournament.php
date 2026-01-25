<?php
include('partial/header.php');
require_once "database/dbConfig.php";


$sql = "
    SELECT 
        g.game_id,
        g.name,
        g.image,
        COUNT(DISTINCT t.tournament_id) AS tournament_count,
        COUNT(DISTINCT tm.user_id) AS player_count
    FROM games g
    LEFT JOIN tournaments t 
        ON t.game_id = g.game_id
        AND t.admin_status = 'approved'
        AND t.status IN ('upcoming','ongoing')
    LEFT JOIN tournament_teams tt
        ON tt.tournament_id = t.tournament_id
    LEFT JOIN teams te
        ON te.team_id = tt.team_id
    LEFT JOIN team_members tm
        ON tm.team_id = te.team_id
    GROUP BY g.game_id
    ORDER BY g.name ASC
";

$result = $conn->query($sql);
$games = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $games[] = $row;
    }
}


/* UI gradients (since DB doesn’t store them yet) */
$gradients = [
    'League of Legends' => 'red-pink',
    'Dota 2'            => 'purple-indigo',
    'Counter-Strike '   => 'orange-yellow',
    'Valorant'          => 'rose-orange',
    'PUBG'              => 'cyan-indigo',
    'MLBB'              => 'red-pink',
    'FIFA 24'           => 'green-teal'
];
?>

<div class="strike-overlay">
    <div class="strike strike-1"></div>
    <div class="strike strike-2"></div>
</div>

<div class="main-wrapper">
    <div class="bg-gradient"></div>
    <div class="grid-overlay"></div>

    <div class="floating-elements">
        <?php for ($i = 0; $i < 8; $i++): ?>
            <div class="floating-cube cube-<?php echo $i; ?>">
                <div class="cube-inner"></div>
            </div>
        <?php endfor; ?>

        <?php for ($i = 0; $i < 5; $i++): ?>
            <div class="glowing-orb orb-<?php echo $i; ?>"></div>
        <?php endfor; ?>

        <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="hexagon hex-<?php echo $i; ?>">
                <svg viewBox="0 0 100 100">
                    <polygon points="50 1 95 25 95 75 50 99 5 75 5 25"
                        fill="none" stroke-width="2" opacity="0.3" />
                </svg>
            </div>
        <?php endfor; ?>
    </div>
</div>

<section class="games-section" id="games">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">
                <span class="gradient-text-alt">FEATURED GAMES</span>
            </h2>
            <p class="section-subtitle">
                Compete in your favorite games and dominate the leaderboards
            </p>
        </div>

        <div class="games-grid">
            <?php foreach ($games as $index => $game):
                $gradient = $gradients[$game['name']] ?? 'blue-cyan';
                $image = !empty($game['image']) ? $game['image'] : 'default.png';
            ?>
                <div class="game-card game-card-<?php echo $index; ?>">
                    <div class="game-bg gradient-<?php echo $gradient; ?>"></div>

                    <div class="game-icon gradient-<?php echo $gradient; ?>">
                        <img src="images/games/<?php echo htmlspecialchars($image); ?>"
                            alt="<?php echo htmlspecialchars($game['name']); ?>"
                            class="game-img">
                    </div>

                    <h3 class="game-name">
                        <?php echo htmlspecialchars($game['name']); ?>
                    </h3>

                    <div class="game-stats">
                        <div class="game-stat">
                            <span class="game-stat-label">Active Tournaments</span>
                            <span class="game-stat-value">
                                <?php echo (int)$game['tournament_count']; ?>
                            </span>
                        </div>

                        <div class="game-stat">
                            <span class="game-stat-label">Players</span>
                            <span class="game-stat-value gradient-<?php echo $gradient; ?>">
                                <?php echo number_format((int)$game['player_count']); ?>
                            </span>
                        </div>
                    </div>

                    <a href="tournaments.php?game_id=<?php echo (int)$game['game_id']; ?>"
                        class="btn-game gradient-<?php echo $gradient; ?>">
                        Browse Tournaments
                    </a>

                    <div class="game-particles">
                        <div class="game-particle particle-1 gradient-<?php echo $gradient; ?>"></div>
                        <div class="game-particle particle-2 gradient-<?php echo $gradient; ?>"></div>
                        <div class="game-particle particle-3 gradient-<?php echo $gradient; ?>"></div>
                    </div>

                    <div class="game-glow gradient-<?php echo $gradient; ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include('partial/footer.php'); ?>