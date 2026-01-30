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

<style>
    /* ================= CORE THEME OVERRIDES (Red & Black) ================= */
    :root {
        --primary-red: #ff4655;
        --deep-black: #0a0a0a;
        --card-bg: #111111;
    }

    body {
        background-color: var(--deep-black) !important;
    }

    /* ================= FADE IN/OUT ANIMATION CLASS ================= */
    .game-card {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease-out, transform 0.8s ease-out;
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
        .games-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .games-grid { grid-template-columns: 1fr; }
    }

    /* ================= RIOT / VALORANT CARD UPGRADE ================= */
    .game-card {
        background: linear-gradient(160deg, #141827, #0b0e14);
        border: 1px solid #222;
        padding: 40px 30px;
        position: relative;
        overflow: hidden;
        transform-style: preserve-3d;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    /* HOVER TRANSITION (Modified to work with the visibility transform) */
    .game-card.visible:hover {
        transform: translateY(-12px) rotateX(6deg) scale(1.03);
        border-color: var(--primary-red);
        box-shadow:
            0 0 25px rgba(255,70,85,0.6),
            0 0 80px rgba(255,70,85,0.25);
    }

    /* SCANLINE TEXTURE INSIDE CARD */
    .game-card::after {
        content: "";
        position: absolute;
        inset: 0;
        background: repeating-linear-gradient(
            to bottom,
            rgba(255,255,255,0.025),
            rgba(255,255,255,0.025) 1px,
            transparent 1px,
            transparent 4px
        );
        opacity: 0.15;
        pointer-events: none;
    }

    /* NEON SWEEP */
    .game-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(
            120deg,
            transparent,
            rgba(255,70,85,0.35),
            transparent
        );
        transform: translateX(-120%);
    }

    .game-card:hover::before {
        animation: riotSweep 1.2s linear;
    }

    @keyframes riotSweep {
        to { transform: translateX(120%); }
    }

    .game-icon {
        width: 100%;
        height: 150px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 2;
    }

    .game-img {
        max-width: 80%;
        max-height: 120px;
        filter: grayscale(1) brightness(0.75);
        transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .game-card:hover .game-img {
        filter: grayscale(0) brightness(1.1)
            drop-shadow(0 0 18px var(--primary-red));
        transform: translateZ(60px) scale(1.12);
    }

    .game-name {
        color: #fff;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 28px;
        margin-bottom: 20px;
        letter-spacing: 1px;
        z-index: 2;
    }

    .game-stats {
        width: 100%;
        display: flex;
        justify-content: space-around;
        margin-bottom: 25px;
        border-top: 1px solid #222;
        padding-top: 20px;
        z-index: 2;
    }

    .game-stat-label {
        display: block;
        font-size: 9px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .game-stat-value {
        color: #fff;
        font-weight: 800;
        font-size: 16px;
    }

    .btn-game {
        width: 100%;
        padding: 15px;
        background: transparent;
        border: 1px solid var(--primary-red);
        color: #fff;
        font-weight: bold;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        letter-spacing: 1px;
        z-index: 2;
        text-decoration: none;
    }

    .btn-game:hover {
        background: var(--primary-red);
        box-shadow: 0 0 25px rgba(255, 70, 85, 0.5);
    }

    .game-glow {
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at center,
            rgba(255, 70, 85, 0.18) 0%,
            transparent 70%);
        opacity: 0;
        transition: 0.5s;
        pointer-events: none;
    }

    .game-card:hover .game-glow {
        opacity: 1;
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
        animation: slash 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.15s forwards;
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.game-card');
        
        const observerOptions = {
            threshold: 0.1 // Triggers when 10% of the card is visible
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                } else {
                    entry.target.classList.remove('visible'); // Fades out when scrolling away
                }
            });
        }, observerOptions);

        cards.forEach(card => {
            observer.observe(card);
        });
    });
</script>

<?php
include('partial/footer.php');
?>