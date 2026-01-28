<?php
 include('partial/header.php');
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


<?php

// Simulated login/team data
// Replace these with your real session/database logic
$isLoggedIn = isset($_SESSION['user_id']); // true if logged in
$userTeam = $isLoggedIn ? ($_SESSION['team_name'] ?? null) : null;

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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>TournaX — Elite Esports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        /* ================= RESET & CORE ================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --riot: #ff4655;
            --riot-dark: #bd3944;
            --bg: #06080f;
            --surface: #11141d;
            --text-dim: rgba(255, 255, 255, 0.6);
            --transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: #fff;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ================= BACKGROUND ================= */
        .bg-fx {
            position: fixed;
            inset: 0;
            z-index: -2;
            background:
                radial-gradient(circle at 10% 10%, rgba(255, 70, 85, 0.12), transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(0, 229, 255, 0.08), transparent 40%);
        }

    
        .noise {
            position: fixed;
            inset: 0;
            z-index: -1;
            opacity: .03;
            pointer-events: none;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* ================= GLOBAL ANIMATION (IN/OUT) ================= */
        .reveal {
            opacity: 0;
            transform: translateY(50px) scale(0.95);
            transition: var(--transition);
        }

        .reveal.active {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .logo {
            font-weight: 900;
            font-size: 1.4rem;
            letter-spacing: 2px;
        }

        .logo span {
            color: var(--riot);
        }

        /* HERO */
        .tx-hero {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 0 10%;
            position: relative;
        }

        .tx-hero-inner {
            max-width: 1100px;
            text-align: center;
            z-index: 2;
            position: relative;
        }

        .tx-kicker {
            letter-spacing: .35em;
            color: #9aa3b2;
            font-weight: 700;
        }

        .tx-hero h1 {
            font-family: 'Press Start 2P', monospace;
            font-size: clamp(2rem, 6vw, 5rem);
            line-height: 1;
            margin: 18px 0;
        }

        .tx-hero h1 span {
            color: var(--riot);
        }

        .tx-hero p {
            max-width: 560px;
            opacity: .75;
            margin-bottom: 36px;
        }

        .tx-actions {
            display: flex;
            gap: 22px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .tx-btn {
            position: relative;
            padding: 16px 36px;
            font-weight: 800;
            letter-spacing: .08em;
            text-decoration: none;
            overflow: hidden;
            border-radius: 6px;
            transition: .3s;
            cursor: pointer;
            background-color: transparent;
        }

        .tx-btn-primary {
            background: var(--riot);
            color: #000;
            box-shadow: 0 0 18px var(--riot);
        }

        .tx-btn-ghost {
            border: 2px solid var(--riot);
            color: var(--riot);
        }

        .tx-btn:hover {
            transform: scale(1.05);
        }

        .tx-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent, rgba(255, 255, 255, .5), transparent);
            transform: translateX(-120%);
            transition: .6s;
        }

        .tx-btn:hover::after {
            transform: translateX(120%);
        }

        /* Floating 3D letters */
        .floating-letters {
            position: absolute;
            inset: 0;
            z-index: 1;
            perspective: 1000px;
            pointer-events: none;
        }

        .floating-letters span {
            position: absolute;
            font-weight: 900;
            color: rgba(255, 70, 85, 0.15);
            transform-style: preserve-3d;
            transition: transform 0.1s linear;
        }

        /* ================= STATS ================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            padding: 80px 10%;
            text-align: center;
            gap: 20px;
        }

        .stat-box h4 {
            font-size: 3rem;
            color: var(--riot);
            font-weight: 900;
        }

        .stat-box p {
            font-size: 0.8rem;
            opacity: 0.5;
            letter-spacing: 2px;
        }

        /* ================= GAME GRID ================= */
        .game-container {
            padding: 100px 10%;
        }

        .game-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            perspective: 1000px;
        }

        .game-card {
            background: var(--surface);
            height: 400px;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.4s ease, transform 0.3s ease;
            transform-style: preserve-3d;
        }

        .game-card:hover {
            border-color: var(--riot);
            transform: translateY(-10px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg));
        }

        .game-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, #000, transparent);
            z-index: 1;
        }

        .game-card img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.5;
            transition: 0.5s;
        }

        .game-card:hover img {
            opacity: 0.8;
            scale: 1.1;
        }

        .game-info {
            position: relative;
            z-index: 2;
        }

        .game-info h3 {
            font-size: 1.5rem;
            font-weight: 800;
        }

        .game-info p {
            color: var(--riot);
            font-weight: 700;
            font-size: 0.75rem;
        }

        /* ================= LIVE MATCHES ================= */
        .match-section {
            padding: 100px 10%;
            background: rgba(255, 255, 255, 0.02);
        }

        .match-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .match-item {
            background: var(--surface);
            padding: 20px 40px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            border-radius: 8px;
            transition: 0.3s;
        }

        .match-item:hover {
            background: #1a1e2b;
        }

        .match-team {
            font-weight: 800;
            font-size: 1.2rem;
        }

        .match-vs {
            background: var(--riot);
            padding: 5px 15px;
            border-radius: 4px;
            font-weight: 900;
            font-size: 0.8rem;
            margin: 0 30px;
        }

        /* ================= TEAM MODAL ================= */
        #teamCard {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        #teamCard div {
            background: var(--surface);
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width:992px) {
            .game-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width:600px) {
            .game-grid {
                grid-template-columns: 1fr;
            }

            .match-item {
                grid-template-columns: 1fr;
                gap: 10px;
                text-align: center;
            }
        }

/* NEW RIOT MODAL STYLE */
        #teamOverlay {
            position: fixed;
            inset: 0;
            background: rgba(6, 8, 15, 0.95);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
            z-index: 99999;
        }

        #teamOverlay.active { opacity: 1; visibility: visible; }

        .teamCard {
            background: var(--surface);
            width: 500px;
            padding: 50px 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            box-shadow: 0 40px 100px rgba(0,0,0,0.8);
        }

        .teamCard::before {
            content: "ESTABLISH SQUAD // 02";
            position: absolute;
            top: 0; left: 0;
            background: var(--riot);
            color: #000;
            font-size: 10px;
            font-weight: 900;
            padding: 2px 8px;
        }

        .teamCard h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 30px;
            letter-spacing: 2px;
            text-align: left;
        }

        .form-row-top { display: flex; gap: 20px; margin-bottom: 20px; align-items: center; }
        
        .upload_photo {
            width: 100px; height: 100px;
            border: 2px solid var(--riot);
            object-fit: cover;
            padding: 4px;
            background: #000;
        }

        .teamCard input, .teamCard textarea {
            width: 100%;
            padding: 15px;
            background: #111;
            border: 1px solid transparent;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            color: #fff;
            margin-bottom: 15px;
            transition: 0.3s;
        }

        .teamCard input:focus { outline: none; border-bottom-color: var(--riot); background: #151515; }

        .createBtn {
            width: 100%;
            padding: 18px;
            background: transparent;
            border: 1px solid var(--riot);
            color: #fff;
            font-family: 'Bebas Neue';
            font-size: 1.5rem;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        .createBtn:hover { background: var(--riot); color: #000; }

        .closeBtn {
            position: absolute;
            top: 15px; right: 15px;
            font-size: 30px;
            cursor: pointer;
            color: rgba(255,255,255,0.3);
            transition: 0.3s;
        }
        .closeBtn:hover { color: var(--riot); }


@media (min-width: 768px) {
    .tournaments-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .gradient-text {
        font-size: 5rem;
        margin-top: 30px;
    }
}

@media (min-width: 1024px) {
    .tournaments-grid {
        grid-template-columns: repeat(3, 1fr);
    }

}
    </style>
</head>

<body>
        <canvas id="bg"></canvas>
        <div class="bg-fx"></div>
        <div class="noise"></div>
        <main>

            <!-- HERO -->
            <section class="tx-hero">
                <div class="tx-hero-inner">
                    <div class="tx-kicker">TOURNAMENTS PLATFORM</div>
                    <h1><span>TournaX</span><br>RISE TO DOMINANCE</h1>
                    <br>
                    <div class="tx-actions">
                        <a class="tx-btn tx-btn-primary" href="#">JOIN TOURNAMENT</a>
                        <?php if (!$isLoggedIn): ?>
                            <a class="tx-btn tx-btn-ghost" href="login.php">CREATE TEAM</a>
                        <?php elseif ($userTeam): ?>
                            <a href="./player/team.php?team_id=<?php echo $userTeam['team_id']; ?>" class="btn-large btn-outline">Team: <?php echo htmlspecialchars($userTeam['team_name']); ?></a>
                        <?php else: ?>
                            <button id="openTeam" class="tx-btn tx-btn-ghost">Create Team</button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- TEAM MODAL -->
            <div id="teamCard">
                <div>
                    <h2 id="teamName"></h2>
                    <p>Team members, stats, and other details here...</p>
                    <button id="closeTeam" style="margin-top:20px;padding:10px 20px;border:none;border-radius:6px;background:var(--riot);color:#000;font-weight:800;cursor:pointer;">Close</button>
                </div>
            </div>

            <!-- STATS -->
            <section class="stats-grid">
                <div class="stat-box reveal">
                    <h4 data-target="500">0</h4>
                    <p>PLAYERS</p>
                </div>
                <div class="stat-box reveal">
                    <h4 data-target="100">0</h4>
                    <p>TEAMS</p>
                </div>
                <div class="stat-box reveal">
                    <h4 data-target="20">0</h4>
                    <p>MATCHES TODAY</p>
                </div>
                <div class="stat-box reveal">
                    <h4 data-target="250000">0</h4>
                    <p>PRIZE POOL ($)</p>
                </div>
            </section>

            <!-- GAME CARDS -->
            <section class="game-container">
                <div class="game-grid">
                    <?php
                    $games = [
                        ['img' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80&w=800', 'title' => 'VALORANT', 'type' => 'TACTICAL SHOOTER'],
                        ['img' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?auto=format&fit=crop&q=80&w=800', 'title' => 'CS2', 'type' => 'ACTION'],
                        ['img' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&q=80&w=800', 'title' => 'DOTA 2', 'type' => 'MOBA'],
                        ['img' => 'https://images.unsplash.com/photo-1593305841991-05c297ba4575?auto=format&fit=crop&q=80&w=800', 'title' => 'PUBG', 'type' => 'BATTLE ROYALE'],
                        ['img' => 'https://images.unsplash.com/photo-1624138784614-87fd1b6528f8?auto=format&fit=crop&q=80&w=800', 'title' => 'FREE FIRE', 'type' => 'SURVIVAL'],
                        ['img' => 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5?auto=format&fit=crop&q=80&w=800', 'title' => 'FORTNITE', 'type' => 'BUILDER'],
                    ];
                    foreach ($games as $game): ?>
                        <div class="game-card reveal">
                            <img data-src="<?= $game['img'] ?>" alt="<?= $game['title'] ?>">
                            <div class="game-info">
                                <p><?= $game['type'] ?></p>
                                <h3><?= $game['title'] ?></h3>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- LIVE MATCHES -->
            <section class="match-section">
                <div class="match-list">
                    <div class="match-item reveal">
                        <div class="match-team" style="text-align:right">TEAM LIQUID</div>
                        <div class="match-vs">VS</div>
                        <div class="match-team">VITALITY</div>
                    </div>
                    <div class="match-item reveal">
                        <div class="match-team" style="text-align:right">ZETA DIVISION</div>
                        <div class="match-vs">VS</div>
                        <div class="match-team">DRX</div>
                    </div>
                    <div class="match-item reveal">
                        <div class="match-team" style="text-align:right">CLOUD9</div>
                        <div class="match-vs">VS</div>
                        <div class="match-team">NAVI</div>
                    </div>
                </div>
            </section>
        </main>
        <!-- CREATE TEAM CARD (HIDDEN) -->
<div id="teamOverlay">
        <div class="teamCard">
            <span class="closeBtn">&times;</span>
            <h2>CREATE SQUAD</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row-top">
                    <label for="uploadInput" style="cursor:pointer;">
                        <img src="images/gif9.gif" class="upload_photo" id="img">
                    </label>
                    <input type="file" name="image" id="uploadInput" hidden required onchange="previewImage(event)">
                    <div style="flex:1">
                        <input type="text" name="teamName" placeholder="TEAM NAME (6-16 CHARS)" required>
                        <input type="text" name="shortName" placeholder="TAG (2-4 CHARS)" required>
                    </div>
                </div>
                <textarea name="motto" placeholder="TEAM MOTTO" rows="2"></textarea>
                <input type="number" name="players" placeholder="MAX PLAYERS" min="1" required>
                <button type="submit" name="createBtn" class="createBtn">CONFIRM FORMATION</button>
            </form>
        </div>
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
        // Keep canvas transparent so page backgrounds/overlays show through
        renderer.setClearColor(0x000000, 0);
        renderer.setPixelRatio(window.devicePixelRatio);

        // CORE 3D OBJECT
        const geometry = new THREE.IcosahedronGeometry(10, 1);
        const material = new THREE.MeshStandardMaterial({
            color: 0xff0000,
            wireframe: true,
            emissive: 0xff0000,
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
                color: 0xff0000,
                emissive: 0xff0000,
                emissiveIntensity: 2,
                transparent: true,
                opacity: 0.8
            });

            const purpleGlow = new THREE.MeshStandardMaterial({
                color: 0xff0000,
                emissive: 0xff0000,
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

        // ================= TEAM CARD LOGIC =================
        const teamBtn = document.getElementById('teamBtn');
        const teamCard = document.getElementById('teamCard');
        const teamName = document.getElementById('teamName');
        const closeTeam = document.getElementById('closeTeam');
        if (teamBtn) {
            teamBtn.addEventListener('click', () => {
                teamName.innerText = teamBtn.innerText;
                teamCard.style.display = 'flex';
            });
        }
        closeTeam.addEventListener('click', () => {
            teamCard.style.display = 'none';
        });

        // ================= REVEAL + LAZY LOAD =================
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    // Stat counter
                    const counter = entry.target.querySelector('h4');
                    if (counter && !counter.classList.contains('counted')) {
                        counter.classList.add('counted');
                        const target = +counter.getAttribute('data-target');
                        let count = 0;
                        const increment = target / 50;
                        const update = () => {
                            if (count < target) {
                                count += increment;
                                counter.innerText = Math.floor(count).toLocaleString();
                                setTimeout(update, 30);
                            } else counter.innerText = target.toLocaleString();
                        };
                        update();
                    }
                    // Lazy load images
                    const img = entry.target.querySelector('img[data-src]');
                    if (img && !img.src) img.src = img.getAttribute('data-src');
                } else {
                    entry.target.classList.remove('active');
                }
            });
        }, {
            threshold: 0.15
        });
        document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

        // ================= 3D CARD TILT =================
        const cards = document.querySelectorAll('.game-card');
        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * 10;
                const rotateY = ((x - centerX) / centerX) * 10;
                card.style.setProperty('--rx', `${-rotateX}deg`);
                card.style.setProperty('--ry', `${rotateY}deg`);
            });
            card.addEventListener('mouseleave', () => {
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        });

        function previewImage(event) {
        let img = document.getElementById("img");
        img.src = URL.createObjectURL(event.target.files[0]);
        img.onload = function() {
            URL.revokeObjectURL(img.src);
        }
    }
    </script>

<?php
 include('partial/footer.php');
?>