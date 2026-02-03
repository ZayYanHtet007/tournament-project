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

<style>
    /* ================= CORE THEME OVERRIDES ================= */
    :root {
        --primary-red: #ff4655;
        --deep-black: #0a0a0a;
    }

    body {
        background-color: var(--deep-black) !important;
    }

    /* ================= FADE ANIMATION ================= */
    .game-card {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease-out, transform 0.8s ease-out, border-color 0.3s;
    }

    .game-card.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* ================= GAMES SECTION ================= */
    .games-section {
        padding: 80px 0;
        background: radial-gradient(circle at center, #1a080a 0%, #0a0a0a 100%);
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .section-header {
        text-align: center;
        margin-bottom: 60px;
    }

    .gradient-text-alt {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 50px;
        color: #fff;
        letter-spacing: 2px;
        text-shadow: 2px 2px 0px var(--primary-red);
    }

    .section-subtitle {
        color: #888;
        text-transform: uppercase;
        letter-spacing: 2px;
        font-size: 12px;
        margin-top: 10px;
    }

    /* ================= GRID ================= */
    .games-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        perspective: 1200px;
    }

    @media (max-width: 992px) {
        .games-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .games-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ================= MODERN GLASS CARD ================= */
    .game-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(12px) saturate(150%);
        -webkit-backdrop-filter: blur(12px) saturate(150%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 40px 30px;
        position: relative;
        overflow: hidden;
        transform-style: preserve-3d;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        border-radius: 8px;
    }

    /* IMAGE BG LAYER */
    .card-image-bg {
        position: absolute;
        inset: 0;
        z-index: 0;
        background-size: cover;
        background-position: center;
        filter: brightness(0.3) grayscale(0.2);
        transition: transform 0.6s ease, filter 0.6s ease, opacity 0.5s ease;
    }

    /* VIDEO BG LAYER - NEW */
    .card-video-bg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: 1;
        opacity: 0;
        filter: brightness(0.4);
        transition: opacity 0.5s ease;
        pointer-events: none;
    }

    .game-card.visible:hover {
        transform: translateY(-12px) scale(1.03);
        border-color: var(--primary-red);
        box-shadow: 0 0 30px rgba(255, 70, 85, 0.3);
    }

    .game-card:hover .card-image-bg {
        transform: scale(1.1);
        opacity: 0;
        /* Hide image on hover to show video */
    }

    .game-card:hover .card-video-bg {
        opacity: 1;
        /* Show video on hover */
    }

    .card-content {
        position: relative;
        z-index: 2;
        width: 100%;
    }

    /* SCANLINE TEXTURE */
    .game-card::after {
        content: "";
        position: absolute;
        inset: 0;
        background: repeating-linear-gradient(to bottom, rgba(255, 255, 255, 0.02) 0 1px, transparent 1px 4px);
        opacity: 0.15;
        pointer-events: none;
        z-index: 3;
    }

    /* NEON SWEEP */
    .game-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(120deg, transparent, rgba(255, 70, 85, 0.2), transparent);
        transform: translateX(-120%);
        z-index: 4;
    }

    .game-card:hover::before {
        animation: riotSweep 1.2s linear;
    }

    @keyframes riotSweep {
        to {
            transform: translateX(120%);
        }
    }

    .game-icon {
        width: 100%;
        height: 120px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .game-img {
        max-width: 70%;
        max-height: 100px;
        filter: drop-shadow(0 0 10px rgba(0, 0, 0, 0.5));
        transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .game-card:hover .game-img {
        transform: translateZ(40px) scale(1.1);
        filter: drop-shadow(0 0 15px var(--primary-red));
    }

    .game-name {
        color: #fff;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 32px;
        margin-bottom: 20px;
        letter-spacing: 1px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
    }

    .game-stats {
        width: 100%;
        display: flex;
        justify-content: space-around;
        margin-bottom: 25px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 20px;
    }

    .game-stat-label {
        display: block;
        font-size: 9px;
        color: #bbb;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .game-stat-value {
        color: #fff;
        font-weight: 800;
        font-size: 18px;
    }

    .btn-game {
        display: block;
        width: 100%;
        padding: 15px;
        background: rgba(0, 0, 0, 0.5);
        border: 1px solid var(--primary-red);
        color: #fff;
        font-weight: bold;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        letter-spacing: 1px;
        text-decoration: none;
    }

    .btn-game:hover {
        background: var(--primary-red);
        box-shadow: 0 0 20px rgba(255, 70, 85, 0.5);
    }

    /* 2. STRIKE OVERLAY */
    .strike-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        pointer-events: none;
    }

    /* 3. THE INTENSE NEON STRIKE */
    .strike {
        position: absolute;
        background-color: #fff; /* White core */
        height: 6px; 
        width: 0%; 
        border-radius: 100px;
        
        /* Multi-layered Neon Glow */
        box-shadow: 
            0 0 10px #fff,
            0 0 20px #fe1313,
            0 0 40px #fe1313,
            0 0 80px #fe1313,
            0 0 120px rgba(254, 19, 172, 0.5);
    }

    .strike-1 {
        transform: rotate(45deg);
        animation: slash 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .strike-2 {
        transform: rotate(-45deg);
        animation: slash 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.17s forwards;
    }

    /* 4. THE ANIMATION (Slash and Dissolve) */
    @keyframes slash {
        0% {
            width: 0%;
            opacity: 0;
            filter: brightness(1);
        }
        40% {
            width: 150%;
            opacity: 1;
            filter: brightness(2); /* Extra flash when they cross */
        }
        100% {
            width: 180%;
            opacity: 0;
            filter: brightness(1);
        }
    }
</style>

<div class="strike-overlay">
    <div class="strike strike-1"></div>
    <div class="strike strike-2"></div>
</div>

<section class="games-section" id="games">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><span class="gradient-text-alt">FEATURED GAMES</span></h2>
            <p class="section-subtitle">Compete in your favorite games and dominate the leaderboards</p>
        </div>

        <div class="games-grid">
            <?php foreach ($games as $index => $game):
                $image = !empty($game['image']) ? $game['image'] : 'default.png';
                $imagePath = "images/games/" . htmlspecialchars($image);
                // Video filename logic: Valorant.mp4, Dota2.mp4, etc.
                $videoFile = str_replace(' ', '', $game['name']) . ".mp4";
                $videoPath = "Videos/" . htmlspecialchars($videoFile);
            ?>
                <div class="game-card">
                    <div class="card-image-bg" style="background-image: url('<?php echo $imagePath; ?>');"></div>

                    <video class="card-video-bg" muted loop playsinline preload="none">
                        <source src="<?php echo $videoPath; ?>" type="video/mp4">
                    </video>

                    <div class="card-content">
                        <div class="game-icon">
                            <img src="<?php echo $imagePath; ?>" alt="icon" class="game-img">
                        </div>

                        <h3 class="game-name"><?php echo htmlspecialchars($game['name']); ?></h3>

                        <div class="game-stats">
                            <div class="game-stat">
                                <span class="game-stat-label">Tournaments</span>
                                <span class="game-stat-value"><?php echo (int)$game['tournament_count']; ?></span>
                            </div>
                            <div class="game-stat">
                                <span class="game-stat-label">Players</span>
                                <span class="game-stat-value"><?php echo number_format((int)$game['player_count']); ?></span>
                            </div>
                        </div>

                        <a href="tournaments.php?game_id=<?php echo (int)$game['game_id']; ?>" class="btn-game">
                            Browse Tournaments
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.game-card');

        // Intersection Observer for card entrance
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                } else {
                    entry.target.classList.remove('visible');
                }
            });
        }, {
            threshold: 0.1
        });

        cards.forEach(card => {
            observer.observe(card);

            // Play video on hover, pause and reset on leave
            const video = card.querySelector('.card-video-bg');
            card.addEventListener('mouseenter', () => {
                if (video) video.play().catch(e => {});
            });
            card.addEventListener('mouseleave', () => {
                if (video) {
                    video.pause();
                    video.currentTime = 0;
                }
            });
        });
    });
</script>

<?php include('partial/footer.php'); ?>