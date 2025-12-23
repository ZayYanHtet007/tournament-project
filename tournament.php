<?php
include('partial/header.php');
?>

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
                        ['name' => 'Valorant','image' => 'valorant.png', 'tournaments' => 45, 'players' => '25K+', 'gradient' => 'red-pink'],
                        ['name' => 'League of Legends', 'image' => 'lol.png', 'tournaments' => 78, 'players' => '50K+', 'gradient' => 'blue-cyan'],
                        ['name' => 'CS:GO', 'image' => 'csgo.png', 'tournaments' => 62, 'players' => '35K+', 'gradient' => 'orange-yellow'],
                        ['name' => 'Dota 2', 'image' => 'dota2.png', 'tournaments' => 34, 'players' => '18K+', 'gradient' => 'purple-indigo'],
                        ['name' => 'Apex Legends', 'image' => 'valorant.png', 'tournaments' => 28, 'players' => '15K+', 'gradient' => 'rose-orange'],
                        ['name' => 'Rocket League', 'image' => 'valorant.png', 'tournaments' => 19, 'players' => '12K+', 'gradient' => 'cyan-indigo']
                    ];
                    foreach($games as $index => $game): 
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