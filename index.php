<?php
include('./partial/header.php');
?>
<?php
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;

// If logged in, try to fetch the user's team (first membership found)
$userTeam = null;
if ($isLoggedIn && isset($conn) && $conn) {
    $stmt = $conn->prepare("SELECT t.team_id, t.team_name FROM team_members tm JOIN teams t ON tm.team_id = t.team_id WHERE tm.user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $userTeam = $row; // ['team_id' => ..., 'team_name' => ...]
        }
        $stmt->close();
    }
}


$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createBtn'])) {
    echo '$isLoggedIn=' . ($isLoggedIn ? 'true' : 'false');

    if (!$isLoggedIn) {
        $errors[] = "You must be logged in to create a team.";
    } else {

        $teamName = trim($_POST['teamName'] ?? '');
        $shortName = trim($_POST['shortName'] ?? '');
        $motto = trim($_POST['motto'] ?? '');
        $players = (int)($_POST['players'] ?? 0);

        if ($teamName === '' || $shortName === '' || $players <= 0 || empty($_FILES['image']['name'])) {
            $errors[] = "All fields are required.";
        }

        if (strlen($teamName) < 6 || strlen($teamName) > 16) {
            $errors[] = "Team name must be 6–16 characters.";
        }

        if (strlen($shortName) < 2 || strlen($shortName) > 4) {
            $errors[] = "Short name must be 2–4 characters.";
        }

        if (empty($errors)) {
            $image = time() . "_" . basename($_FILES['image']['name']); // max file size 2 MB
            $tmp = $_FILES['image']['tmp_name'];

            $uploadDir = __DIR__ . "/images/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $path = $uploadDir . $image;

            if (!move_uploaded_file($tmp, $path)) {
                $errors[] = "Image upload failed.";
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT team_id FROM teams WHERE team_name = ?");
            $stmt->bind_param("s", $teamName);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Team name already exists.";
            }
            $stmt->close();
        }

        print_r($errors);

        if (empty($errors)) {

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
        INSERT INTO teams (team_name, leader_id, short_name, motto, total_member, logo)
        VALUES (?, ?, ?, ?, ?, ?)
        ");
                $stmt->bind_param("sissis", $teamName, $user_id, $shortName, $motto, $players, $image);
                $stmt->execute();
                $team_id = $stmt->insert_id;
                $stmt->close();

                $stmt = $conn->prepare("
        INSERT INTO team_members (team_id, user_id, role)
        VALUES (?, ?, 'leader')
        ");
                $stmt->bind_param("ii", $team_id, $user_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                header("Location: index.php?team_id=" . $team_id);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Database error.";
            }
        }
    }
}
?>


<!-- LOADER -->
<div id="loader">
    <h1 class="logo">Tourna<span>X</span></h1>

    <div class="progress-bar1">
        <div class="progress"></div>
    </div>

    <p class="loading-text">Initializing Arena…</p>
</div>

<!-- WEBSITE CONTENT -->
<div id="site" class="hidden">

    <canvas id="bg"></canvas>

    <main>

        <!-- Hero Section -->
        <section class="hero" id="hero3d">

            <div class="hero-container">
                <div class="hero-content">

                    <h1 class="hero-title">
                        <span class="gradient-text">TournaX</span>
                        <br>
                        <span>COMPETE FOR GLORY</span>
                    </h1>

                    <p class="hero-subtitle">
                        Join the world's premier esports tournament platform. Compete against the best,
                        win epic prizes, and become a legend.
                    </p>

                    <div class="hero-cta">
                        <button class="btn-large btn-gradient">
                            <span>Join Tournament</span>
                        </button>
                        <?php if (! $isLoggedIn): ?>
                            <a href="login.php" class="btn-large btn-outline">Create Team</a>
                        <?php elseif ($userTeam): ?>
                            <a href="./player/team.php?team_id=<?php echo $userTeam['team_id']; ?>" class="btn-large btn-outline">Team: <?php echo htmlspecialchars($userTeam['team_name']); ?></a>
                        <?php else: ?>
                            <button id="openTeam" class="btn-large btn-outline">Create Team</button>
                        <?php endif; ?>
                    </div>

                    <div class="hero-stats">
                        <div class="stat-card stat-card-1">
                            <div class="stat-icon icon-cyan">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
                                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
                                    <path d="M4 22h16" />
                                    <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
                                    <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
                                    <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" />
                                </svg>
                            </div>
                            <div class="stat-value gradient-cyan">$2.5M+</div>
                            <div class="stat-label">Total Prize Pool</div>
                        </div>

                        <div class="stat-card stat-card-2">
                            <div class="stat-icon icon-purple">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                </svg>
                            </div>
                            <div class="stat-value gradient-purple">150K+</div>
                            <div class="stat-label">Active Players</div>
                        </div>

                        <div class="stat-card stat-card-3">
                            <div class="stat-icon icon-pink">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <circle cx="12" cy="12" r="6" />
                                    <circle cx="12" cy="12" r="2" />
                                </svg>
                            </div>
                            <div class="stat-value gradient-pink">500+</div>
                            <div class="stat-label">Live Tournaments</div>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-wrapper">
                    <div class="stats-bg-pattern"></div>
                    <div class="floating-shape-circle"></div>
                    <div class="floating-shape-square"></div>

                    <div class="stats-grid">
                        <?php
                        // DEBUG GUARD: set to true to force default data (skip DB queries)
                        // Toggle to false to re-enable DB-driven stats once database is verified.
                        $forceDefaultStats = true;

                        // Try to load stats from database; fall back to defaults if unavailable
                        $stats = [];
                        if (!$forceDefaultStats && isset($conn) && $conn) {
                            $playersCount = 0;
                            $tournamentsCount = 0;
                            $gamesCount = 0;
                            $prizeTotal = null;

                            $res = @$conn->query("SELECT COUNT(*) AS cnt FROM users");
                            if ($res) {
                                $r = $res->fetch_assoc();
                                $playersCount = intval($r['cnt'] ?? 0);
                            }

                            $res = @$conn->query("SELECT COUNT(*) AS cnt FROM tournaments");
                            if ($res) {
                                $r = $res->fetch_assoc();
                                $tournamentsCount = intval($r['cnt'] ?? 0);
                            }

                            $res = @$conn->query("SELECT COUNT(DISTINCT game) AS cnt FROM tournaments");
                            if ($res) {
                                $r = $res->fetch_assoc();
                                $gamesCount = intval($r['cnt'] ?? 0);
                            }

                            // Try numeric prize column first (common name: prize_amount)
                            $res = @$conn->query("SELECT SUM(prize_amount) AS sum FROM tournaments");
                            if ($res) {
                                $r = $res->fetch_assoc();
                                $prizeTotal = $r['sum'] ?? null;
                            }

                            // Build display values with sensible fallbacks
                            $stats = [
                                ['value' => $prizeTotal ? ('$' . number_format($prizeTotal)) : '$2.5M+', 'label' => 'Total Prize Pool', 'gradient' => 'cyan', 'icon' => 'users'],
                                ['value' => $playersCount ? number_format($playersCount) : '150K+', 'label' => 'Registered Players', 'gradient' => 'purple', 'icon' => 'trophy'],
                                ['value' => $tournamentsCount ? number_format($tournamentsCount) : '500+', 'label' => 'Live Tournaments', 'gradient' => 'yellow', 'icon' => 'gamepad'],
                                ['value' => $gamesCount ? number_format($gamesCount) : '50+', 'label' => 'Supported Games', 'gradient' => 'pink', 'icon' => 'zap']
                            ];
                        } else {
                            // DB not available - keep original defaults
                            $stats = [
                                ['value' => '2.5M+', 'label' => 'Registered Players', 'gradient' => 'cyan', 'icon' => 'users'],
                                ['value' => '15K+', 'label' => 'Tournaments Hosted', 'gradient' => 'purple', 'icon' => 'trophy'],
                                ['value' => '50+', 'label' => 'Supported Games', 'gradient' => 'yellow', 'icon' => 'gamepad'],
                                ['value' => '$100M+', 'label' => 'Total Prizes Awarded', 'gradient' => 'pink', 'icon' => 'zap']
                            ];
                        }

                        foreach ($stats as $index => $stat):
                        ?>
                            <div class="stats-item stats-item-<?php echo $index; ?>">
                                <div class="stats-icon-wrapper">
                                    <div class="stats-icon icon-<?php echo $stat['gradient']; ?>">
                                        <?php if ($stat['icon'] == 'users'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                                <circle cx="9" cy="7" r="4" />
                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                            </svg>
                                        <?php elseif ($stat['icon'] == 'trophy'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
                                                <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
                                                <path d="M4 22h16" />
                                                <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
                                                <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
                                                <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" />
                                            </svg>
                                        <?php elseif ($stat['icon'] == 'gamepad'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="6" x2="10" y1="12" y2="12" />
                                                <line x1="8" x2="8" y1="10" y2="14" />
                                                <line x1="15" x2="15.01" y1="13" y2="13" />
                                                <line x1="18" x2="18.01" y1="11" y2="11" />
                                                <rect width="20" height="12" x="2" y="6" rx="2" />
                                            </svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="stats-icon-ring"></div>
                                </div>
                                <div class="stats-value gradient-<?php echo $stat['gradient']; ?>"><?php echo $stat['value']; ?></div>
                                <div class="stats-label"><?php echo $stat['label']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tournaments Section -->
        <section class="tournaments-section" id="tournaments">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="gradient-text">FEATURED TOURNAMENTS</span>
                    </h2>
                    <p class="section-subtitle">Join the biggest esports competitions and prove your skills</p>
                </div>

                <div class="tournaments-grid">
                    <?php
                    // DEBUG GUARD: reuse the same toggle above; when true we skip DB queries
                    // Try to load tournaments from DB; fall back to hardcoded list if unavailable
                    $tournaments = [];
                    if (!$forceDefaultStats && isset($conn) && $conn) {
                        $sql = "SELECT title, game, prize, players, `date`, status, image FROM tournaments ORDER BY `date` DESC LIMIT 6";
                        $res = @$conn->query($sql);
                        if ($res && $res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                // Ensure expected keys exist
                                $tournaments[] = [
                                    'title' => $row['title'] ?? 'Untitled',
                                    'game' => $row['game'] ?? 'Unknown',
                                    'prize' => $row['prize'] ?? '$0',
                                    'players' => $row['players'] ?? '0/0',
                                    'date' => $row['date'] ?? '',
                                    'status' => $row['status'] ?? 'Upcoming',
                                    'image' => $row['image'] ?? '',
                                    'gradient' => $row['gradient'] ?? 'cyan-blue'
                                ];
                            }
                        }
                    }

                    if (empty($tournaments)) {
                        $tournaments = [
                            [
                                'title' => 'Apex Legends Championship',
                                'game' => 'Apex Legends',
                                'prize' => '$50,000',
                                'players' => '128/128',
                                'date' => 'Dec 28, 2025',
                                'status' => 'Live',
                                'image' => 'https://images.unsplash.com/photo-1688377051459-aebb99b42bff?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjeWJlcnB1bmslMjBuZW9uJTIwY2l0eXxlbnwxfHx8fDE3NjYzNDI3MDJ8MA&ixlib=rb-4.1.0&q=80&w=1080',
                                'gradient' => 'red-orange'
                            ],
                            [
                                'title' => 'Valorant Masters',
                                'game' => 'Valorant',
                                'prize' => '$75,000',
                                'players' => '64/64',
                                'date' => 'Dec 30, 2025',
                                'status' => 'Upcoming',
                                'image' => 'https://images.unsplash.com/photo-1628089700970-0012c5718efc?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxnYW1pbmclMjBrZXlib2FyZCUyMGxpZ2h0c3xlbnwxfHx8fDE3NjYzNzI5NzF8MA&ixlib=rb-4.1.0&q=80&w=1080',
                                'gradient' => 'pink-purple'
                            ],
                            [
                                'title' => 'CS:GO Pro League',
                                'game' => 'Counter-Strike',
                                'prize' => '$100,000',
                                'players' => '32/32',
                                'date' => 'Jan 5, 2026',
                                'status' => 'Registration Open',
                                'image' => 'https://images.unsplash.com/photo-1553492206-f609eddc33dd?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlc3BvcnRzJTIwZ2FtaW5nJTIwYXJlbmF8ZW58MXx8fHwxNzY2Mjg1MzkxfDA&ixlib=rb-4.1.0&q=80&w=1080',
                                'gradient' => 'cyan-blue'
                            ]
                        ];
                    }

                    foreach ($tournaments as $tournament):
                    ?>
                        <div class="tournament-card">
                            <div class="tournament-image">
                                <img src="<?php echo $tournament['image']; ?>" alt="<?php echo $tournament['title']; ?>">
                                <div class="tournament-image-overlay"></div>
                                <div class="tournament-status status-<?php echo strtolower($tournament['status']); ?> gradient-<?php echo $tournament['gradient']; ?>">
                                    <?php echo $tournament['status']; ?>
                                </div>
                                <?php if ($tournament['status'] == 'Live'): ?>
                                    <div class="tournament-live-indicator">
                                        <span class="live-dot"></span>
                                        <span>LIVE</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tournament-content">
                                <div class="tournament-game"><?php echo $tournament['game']; ?></div>
                                <h3 class="tournament-title"><?php echo $tournament['title']; ?></h3>

                                <div class="tournament-info">
                                    <div class="info-item">
                                        <svg class="icon-yellow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
                                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
                                            <path d="M4 22h16" />
                                            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
                                            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
                                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" />
                                        </svg>
                                        <span>Prize Pool: <strong><?php echo $tournament['prize']; ?></strong></span>
                                    </div>
                                    <div class="info-item">
                                        <svg class="icon-cyan" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                            <circle cx="9" cy="7" r="4" />
                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                        </svg>
                                        <span>Players: <strong><?php echo $tournament['players']; ?></strong></span>
                                    </div>
                                    <div class="info-item">
                                        <svg class="icon-purple" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
                                            <line x1="16" x2="16" y1="2" y2="6" />
                                            <line x1="8" x2="8" y1="2" y2="6" />
                                            <line x1="3" x2="21" y1="10" y2="10" />
                                        </svg>
                                        <span><?php echo $tournament['date']; ?></span>
                                    </div>
                                </div>

                                <button class="btn-tournament gradient-<?php echo $tournament['gradient']; ?>">
                                    View Tournament
                                </button>
                            </div>

                            <div class="tournament-glow"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="section-footer">
                    <button class="btn-view-all">
                        <span>View All Tournaments</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>
</div>

<!-- CREATE TEAM CARD (HIDDEN) -->
<div id="teamOverlay">
    <div class="teamCard">
        <span class="closeBtn">&times;</span>
        <h2>Create Team</h2>

        <form method="POST" enctype="multipart/form-data" action="">

            <div class="form-row-top">
                <div class="upload-section">
                    <label for="uploadInput" style="cursor:pointer;">
                        <img src="images/gif9.gif" class="upload_photo" id="img">
                    </label>
                    <input type="file" name="image" id="uploadInput" hidden required onchange="previewImage(event)">
                </div>

                <div class="name-fields">
                    <input type="text" name="teamName" placeholder="Team Name (6-16 chars)" required>
                    <input type="text" name="shortName" placeholder="Short Name (2-4 chars)" required>
                </div>
            </div>
            <textarea name="motto" placeholder="Motto (Within 100 chars)"></textarea>
            <input type="number" name="players" placeholder="Players" min="1" required>

            <button type="submit" name="createBtn" class="createBtn">
                Create Team
            </button>
        </form>
    </div>
</div>



</main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>

<script>
    // --- THREE.JS SCENE SETUP ---
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({
        canvas: document.querySelector('#bg'),
        antialias: true,
        alpha: true
    });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(window.devicePixelRatio);

    // CORE 3D OBJECT
    const geometry = new THREE.IcosahedronGeometry(10, 1);
    const material = new THREE.MeshStandardMaterial({
        color: 0x00f3ff,
        wireframe: true,
        emissive: 0xbc13fe,
        emissiveIntensity: 0.5
    });
    const core = new THREE.Mesh(geometry, material);
    scene.add(core);

    // --- BACKGROUND LETTERS (T & X) WITH GLOW ---
    const group = new THREE.Group();
    const loader = new THREE.FontLoader();

    loader.load('https://threejs.org/examples/fonts/helvetiker_bold.typeface.json', function(font) {
        // Create glowing materials
        const cyanGlow = new THREE.MeshStandardMaterial({
            color: 0x00f3ff,
            emissive: 0x00f3ff,
            emissiveIntensity: 2,
            transparent: true,
            opacity: 0.8
        });

        const purpleGlow = new THREE.MeshStandardMaterial({
            color: 0xbc13fe,
            emissive: 0xbc13fe,
            emissiveIntensity: 2,
            transparent: true,
            opacity: 0.8
        });

        const letters = ['T', 'O', 'U', 'R', 'N', 'A', 'X'];

        for (let i = 0; i < 200; i++) {
            const char = letters[Math.floor(Math.random() * letters.length)];
            const textGeo = new THREE.TextGeometry(char, {
                font: font,
                size: 0.8,
                height: 0.1
            });

            // Randomly pick between cyan or purple glow
            const material = Math.random() > 0.5 ? cyanGlow : purpleGlow;
            const mesh = new THREE.Mesh(textGeo, material);

            mesh.position.set(
                (Math.random() - 0.5) * 150,
                (Math.random() - 0.5) * 150,
                (Math.random() - 0.5) * 150
            );
            mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);
            group.add(mesh);
        }
    });
    scene.add(group);

    // Using a hosted font for the letters
    loader.load('https://threejs.org/examples/fonts/helvetiker_bold.typeface.json', function(font) {
        const textMaterial = new THREE.MeshBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.2
        });
        const letters = ['T', 'X'];

        for (let i = 0; i < 200; i++) {
            const char = letters[Math.floor(Math.random() * letters.length)];
            const textGeo = new THREE.TextGeometry(char, {
                font: font,
                size: 0.8,
                height: 0.1
            });
            const mesh = new THREE.Mesh(textGeo, textMaterial);

            mesh.position.set(
                (Math.random() - 0.5) * 150,
                (Math.random() - 0.5) * 150,
                (Math.random() - 0.5) * 150
            );
            mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);
            group.add(mesh);
        }
    });
    scene.add(group);

    const light = new THREE.PointLight(0x00f3ff, 2, 100);
    light.position.set(10, 10, 10);
    scene.add(light, new THREE.AmbientLight(0xffffff, 0.2));
    camera.position.z = 30;

    // --- GSAP & INTERACTION ---
    gsap.registerPlugin(ScrollTrigger);
    const tl = gsap.timeline({
        scrollTrigger: {
            trigger: "body",
            start: "top top",
            end: "bottom bottom",
            scrub: 1.5,
            onUpdate: (self) => {
                document.getElementById('bar').style.height = (self.progress * 100) + "%";
            }
        }
    });
    tl.to(core.rotation, {
        y: Math.PI * 4,
        x: Math.PI
    }).to(camera.position, {
        z: 15
    }, 0);

    let mouseX = 0,
        mouseY = 0;
    document.addEventListener('mousemove', (e) => {
        mouseX = (e.clientX / window.innerWidth) - 0.5;
        mouseY = (e.clientY / window.innerHeight) - 0.5;
    });

    function animate() {
        requestAnimationFrame(animate);
        core.rotation.z += 0.002;
        group.rotation.y += 0.001; // Rotate the T/X cloud
        camera.position.x += (mouseX * 20 - camera.position.x) * 0.05;
        camera.position.y += (-mouseY * 20 - camera.position.y) * 0.05;
        camera.lookAt(scene.position);
        renderer.render(scene, camera);
    }
    animate();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });

    // Fallback/explicit scroll handler: map page scroll to 3D scene values
    // This ensures the 3D core and camera respond even if ScrollTrigger isn't active
    function updateSceneByScroll() {
        const scrollTop = window.scrollY || window.pageYOffset;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const progress = docHeight > 0 ? scrollTop / docHeight : 0;

        // Rotate core on X/Y based on scroll progress
        core.rotation.x = progress * Math.PI * 2; // full two turns
        core.rotation.y = progress * Math.PI * 4; // faster yaw

        // Move camera closer as user scrolls down
        camera.position.z = 30 - (progress * 15); // from 30 -> 15
    }

    // Use passive listener for performance
    window.addEventListener('scroll', updateSceneByScroll, {
        passive: true
    });
    // Initialize once on load
    updateSceneByScroll();

    // MODAL LOGIC
    const openBtn = document.getElementById("openTeam");
    const overlay = document.getElementById("teamOverlay");
    const closeBtn = document.querySelector(".closeBtn");

    if (openBtn) {
        openBtn.onclick = () => overlay.classList.add("active");
    }
    if (closeBtn) {
        closeBtn.onclick = () => overlay.classList.remove("active");
    }
    window.onclick = (e) => {
        if (e.target == overlay) overlay.classList.remove("active");
    }

    function previewImage(event) {
        let img = document.getElementById("img");
        img.src = URL.createObjectURL(event.target.files[0]);
    }
</script>


<script>
    function previewImage(event) {
        let img = document.getElementById("img");
        img.src = URL.createObjectURL(event.target.files[0]);
        img.onload = function() {
            URL.revokeObjectURL(img.src);
        }
    }
</script>

<script>
    document.addEventListener("DOMContentLoaded", () => {

        const openBtn = document.getElementById("openTeam");
        const overlay = document.getElementById("teamOverlay");
        const closeBtn = document.querySelector(".closeBtn");

        if (openBtn) {
            openBtn.addEventListener("click", () => {
                overlay.classList.add("active");
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener("click", () => {
                overlay.classList.remove("active");
            });
        }

        if (overlay) {
            overlay.addEventListener("click", (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove("active");
                }
            });
        }
    });

    function previewImage(event) {
        const img = document.getElementById("img");
        img.src = URL.createObjectURL(event.target.files[0]);
        img.onload = () => URL.revokeObjectURL(img.src);
    }
</script>


<script>
    (function() {
        try {
            const loader1 = document.getElementById("loader");
            const site = document.getElementById("site");
            const firstVisit = !localStorage.getItem("tournax_loaded");

            function revealSite() {
                try {
                    if (loader1) {
                        loader1.style.opacity = "0";
                        setTimeout(() => {
                            if (loader1) loader1.style.display = "none";
                        }, 600);
                    }
                    if (site) {
                        site.classList.remove("hidden");
                        site.classList.add("loaded");
                    }
                } catch (e) {
                    console.error('revealSite error', e);
                }
            }

            if (firstVisit) {
                // Use both load event and timeout fallback in case load never fires
                window.addEventListener("load", () => {
                    try {
                        localStorage.setItem("tournax_loaded", "true");
                    } catch (e) {}
                    revealSite();
                });

                // Fallback: force reveal after 4s to avoid permanent loader
                setTimeout(() => {
                    if (!site || !site.classList.contains('loaded')) revealSite();
                }, 4000);
            } else {
                revealSite();
            }
        } catch (err) {
            console.error('Loader init error', err);
            // Best-effort fallback
            try {
                document.getElementById('loader') && (document.getElementById('loader').style.display = 'none');
            } catch (e) {}
            try {
                document.getElementById('site') && document.getElementById('site').classList.remove('hidden');
            } catch (e) {}
        }
    })();
</script>

<?php
include('partial/footer.php');
?>