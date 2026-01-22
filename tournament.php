<?php
include('partial/header.php');
?>

<div class="strike-overlay">
        <div class="strike strike-1"></div>
        <div class="strike strike-2"></div>
    </div>

<div class="main-wrapper">
    <!-- Animated background gradient -->
    <div class="bg-gradient"></div>

    <!-- Grid overlay -->
    <div class="grid-overlay"></div>

    <!-- Floating 3D elements -->
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
                    <polygon points="50 1 95 25 95 75 50 99 5 75 5 25" fill="none" stroke-width="2" opacity="0.3" />
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
            <p class="section-subtitle">Compete in your favorite games and dominate the leaderboards</p>
        </div>

        <div class="games-grid">
            <?php
            $games = [
                ['name' => 'Mobile Legends Bang Bang', 'image' => 'mlbb.png', 'tournaments' => 45, 'players' => '25K+', 'gradient' => 'red-pink'],
                ['name' => 'PUBG Mobile', 'image' => 'pubgmobile.png', 'tournaments' => 78, 'players' => '50K+', 'gradient' => 'blue-cyan'],
                ['name' => 'CS:GO', 'image' => 'csgo.png', 'tournaments' => 62, 'players' => '35K+', 'gradient' => 'orange-yellow'],
                ['name' => 'Dota 2', 'image' => 'dota2.png', 'tournaments' => 34, 'players' => '18K+', 'gradient' => 'purple-indigo'],
                ['name' => 'Valorant', 'image' => 'valorant.png', 'tournaments' => 28, 'players' => '15K+', 'gradient' => 'rose-orange'],
                ['name' => 'PUBG', 'image' => 'pubg.png', 'tournaments' => 19, 'players' => '12K+', 'gradient' => 'cyan-indigo'],
                ['name' => 'PUBG', 'image' => 'pubg.png', 'tournaments' => 19, 'players' => '12K+', 'gradient' => 'black-white'],
                ['name' => 'PUBG', 'image' => 'pubg.png', 'tournaments' => 19, 'players' => '12K+', 'gradient' => 'green-teal'],
                ['name' => 'PUBG', 'image' => 'pubg.png', 'tournaments' => 19, 'players' => '12K+', 'gradient' => 'yellow-green']
            ];
            foreach ($games as $index => $game):
            ?>
                <div class="game-card game-card-<?php echo $index; ?>">
                    <div class="game-bg gradient-<?php echo $game['gradient']; ?>"></div>

                    <div class="game-icon gradient-<?php echo $game['gradient']; ?>">
                        <img src="images/games/<?php echo $game['image']; ?>" alt="<?php echo $game['name']; ?>"
                            alt="<?php echo $game['name']; ?>"
                            class="game-img">
                    </div>

                    <h3 class="game-name"><?php echo $game['name']; ?></h3>

                    <div class="game-stats">
                        <div class="game-stat">
                            <span class="game-stat-label">Active Tournaments</span>
                            <span class="game-stat-value"><?php echo $game['tournaments']; ?></span>
                        </div>
                        <div class="game-stat">
                            <span class="game-stat-label">Players</span>
                            <span class="game-stat-value gradient-<?php echo $game['gradient']; ?>"><?php echo $game['players']; ?></span>
                        </div>
                    </div>

                    <button class="btn-game gradient-<?php echo $game['gradient']; ?>">
                        Browse Tournaments
                    </button>

                    <div class="game-particles">
                        <div class="game-particle particle-1 gradient-<?php echo $game['gradient']; ?>"></div>
                        <div class="game-particle particle-2 gradient-<?php echo $game['gradient']; ?>"></div>
                        <div class="game-particle particle-3 gradient-<?php echo $game['gradient']; ?>"></div>
                    </div>

                    <div class="game-glow gradient-<?php echo $game['gradient']; ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
include('partial/footer.php');
?>