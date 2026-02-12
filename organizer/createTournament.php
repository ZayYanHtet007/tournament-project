<?php
session_start();
require_once "../database/dbConfig.php";

/* ---------- ACCESS CONTROL ---------- */
if (
    !isset($_SESSION['user_id']) ||
    !$_SESSION['is_organizer']
) {
    header("Location: ../login.php");
    exit;
}

// Default Functions
function generateDefaultSystem($genre, $maxTeams)
{
    if ($genre === 'BATTLE_ROYALE') {
        return "
•Points Based League
•All Teams Will Play 3 Matches

Rank Points:
1 → 20
2 → 16
3 → 14
4 → 12
5 → 10
6 → 8
7 → 6
8 → 4
9+ → 1

•Each Kill = 1 Point
•Highest Total Points Wins
";
    }

    if ($maxTeams == 12) {
        return "Group Stage (3 teams per group)\nBO3 Matches\nTop 8 → Quarter Final\nTop 4 → Semi Final";
    }

    return "Single Elimination Format";
}

function generateDefaultRules($genre, $description)
{
    $base = $description . "\n• Fair play is mandatory\n• Any cheating leads to disqualification\n";
    switch ($genre) {
        case 'MOBA':
            return $base . "• All matches are BO3 (Grand Final BO5)\n• MOBA Mobile can't play with emulator\n• Teams must be ready 15 minutes before match\n• Disconnects under 5 minutes → rematch\n• Organizer decision is final";
        case 'BATTLE_ROYALE':
            return $base . "• No teaming\n• Exploits and glitches are forbidden\n• Custom room only\n• Organizer decision is final";
        default:
            return $base . "• Organizer decision is final";
    }
}

/* ---------- HELPERS ---------- */
function clean($v)
{
    return htmlspecialchars(trim($v), ENT_QUOTES);
}
function valid_date($d)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}
function calculateStatus($reg_start, $start)
{
    $today = date('Y-m-d');
    if ($today < $reg_start) return 'upcoming';
    if ($today >= $reg_start && $today <= $start) return 'ongoing';
    return 'completed';
}

$message = "";
$currentStep = 1;

/* ---------- FETCH GAMES ---------- */
$games = [];
$q = $conn->query("SELECT game_id, name, genre FROM games ORDER BY name");
while ($row = $q->fetch_assoc()) {
    $games[] = $row;
}

/* ---------- FORM SUBMIT ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnCreate'])) {
    $currentStep = isset($_POST['current_step']) ? (int)$_POST['current_step'] : 1;
    $organizer_id = (int)$_SESSION['user_id'];
    $game_id = (int)($_POST['game_id'] ?? 0);
    $title = clean($_POST['title'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $max_participants = (int)($_POST['max_participants'] ?? 0);
    $team_size = (int)($_POST['team_size'] ?? 0);
    $fee = (float)($_POST['fee'] ?? 0);
    $prize_pool = (float)($_POST['prize_pool'] ?? 0);
    $reg_start = $_POST['registration_start_date'] ?? '';
    $reg_end   = $_POST['registration_deadline'] ?? '';
    $start     = $_POST['start_date'] ?? '';

    if (!$game_id || !$title || !$description || $max_participants < 8 || !$team_size || !valid_date($reg_start) || !valid_date($reg_end) || !valid_date($start)) {
        $message = "❌ Please fill all required fields correctly.";
        $currentStep = 2;
    } elseif ($reg_start >= $reg_end) {
        $message = "❌ Registration start must be before deadline.";
        $currentStep = 3;
    } elseif ($start <= $reg_end) {
        $message = "❌ Tournament start must be after registration deadline.";
        $currentStep = 3;
    } else {
        $status = calculateStatus($reg_start, $start);
        $stmt = $conn->prepare("INSERT INTO tournaments (organizer_id, game_id, title, description, max_participants, team_size, fee, registration_start_date, registration_deadline, start_date, status, admin_status, prize_pool) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("iissiidssssd", $organizer_id, $game_id, $title, $description, $max_participants, $team_size, $fee, $reg_start, $reg_end, $start, $status, $prize_pool);

        if ($stmt->execute()) {
            $tournamentId = $stmt->insert_id;
            $stmt1 = $pdo->prepare("SELECT genre FROM games WHERE game_id = ?");
            $stmt1->execute([$game_id]);
            $game = $stmt1->fetch(PDO::FETCH_ASSOC);
            $genre = $game['genre'];

            $defaultRules  = generateDefaultRules($genre, $description);
            $defaultSystem = generateDefaultSystem($genre, $max_participants);

            $stmtAnn = $pdo->prepare("INSERT INTO tournament_announcements (tournament_id, title, rules, system_info, created_at) VALUES (?,?,?,?, NOW())");
            $stmtAnn->execute([$tournamentId, $title, $defaultRules, $defaultSystem]);

            // Notify admins about new tournament submission
            $notiTitle = "Tournament Pending Approval";
            $notiMessage = "New tournament submitted: \"{$title}\" (tournament ID #{$tournamentId}).";
            $notiStmt = $conn->prepare("
                INSERT INTO admin_notifications (admin_id, title, message, type, created_at)
                SELECT admin_id, ?, ?, 'tournament_pending', NOW() FROM admins
            ");
            if ($notiStmt) {
                $notiStmt->bind_param("ss", $notiTitle, $notiMessage);
                $notiStmt->execute();
                $notiStmt->close();
            }

            header("Location: stripe-payment.php?tournament_id=" . $tournamentId);
            exit;
        } else {
            $message = "❌ DB Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Tournament | Pro Gamer Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --neon-blue: #00d2ff;
            --deep-blue: #004e92;
            --dark-bg: #060b28;
            --glass-bg: rgba(15, 23, 42, 0.9);
        }

        body {
            background: radial-gradient(circle at top, #101e4a 0%, #060b28 100%);
            background-attachment: fixed;
            color: #e2e8f0;
            font-family: 'Rajdhani', sans-serif;
            min-height: 100vh;
        }

        h1, h2, h3, .font-bold {
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .input {
            width: 100%;
            padding: .75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 210, 255, 0.3);
            border-radius: .5rem;
            color: white;
            transition: all 0.3s ease;
        }

        .input:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.4);
        }

        .btn {
            padding: .75rem 1.5rem;
            border-radius: .5rem;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(90deg, #004e92, #00d2ff);
            color: #fff;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 210, 255, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 0 25px rgba(0, 210, 255, 0.6);
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 1px solid var(--neon-blue);
            background: transparent;
            color: var(--neon-blue);
        }

        .btn-outline:hover {
            background: rgba(0, 210, 255, 0.1);
        }

        .hidden { display: none; }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 210, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.8);
        }

        .card {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            border-radius: .5rem;
            transition: 0.3s;
        }

        .card.selected {
            background: rgba(0, 210, 255, 0.25);
            border-color: var(--neon-blue);
            color: white;
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.3);
        }

        .bracket {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 15px;
            border: 1px solid rgba(0, 210, 255, 0.2);
            background: rgba(0, 0, 0, 0.3);
        }

        .match {
            background: rgba(255, 255, 255, 0.05);
            border-left: 3px solid var(--neon-blue);
            padding: 6px;
            margin-bottom: 8px;
            font-size: 11px;
        }

        #progress {
            box-shadow: 0 0 10px var(--neon-blue);
        }

        label { color: var(--neon-blue); font-size: 0.9rem; margin-bottom: 0.4rem; display: block; }
        
        .format-section.disabled {
            opacity: 0.25;
            pointer-events: none;
            filter: grayscale(1);
        }
    </style>
</head>

<body class="p-4 md:p-10">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8 text-center text-white italic">
            <span class="text-blue-400">⚡</span> Create Tournament <span class="text-blue-400">⚡</span>
        </h1>

        <?php if ($message): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-100 p-4 rounded mb-6">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="current_step" id="current_step" value="<?= $currentStep ?>">

            <div class="glass-card p-6 md:p-8 rounded-xl">
                <div class="mb-8">
                    <div class="bg-gray-800 h-1.5 rounded-full overflow-hidden">
                        <div id="progress" class="bg-gradient-to-r from-blue-600 to-cyan-400 h-full transition-all duration-500" style="width:<?= $currentStep / 3 * 100 ?>%"></div>
                    </div>
                    <div class="mt-3 text-center text-blue-400 text-sm tracking-widest font-bold">
                        PHASE 0<span id="stepNum"><?= $currentStep ?></span> / 03
                    </div>
                </div>

                <section id="step1" class="<?= $currentStep !== 1 ? 'hidden' : '' ?>">
                    <label>Tournament Title *</label>
                    <input name="title" class="input mb-6" placeholder="Tournament Name" value="<?= $_POST['title'] ?? '' ?>" required>

                    <label>General Information / Rules Summary *</label>
                    <textarea name="description" class="input mb-6" rows="4" placeholder="Enter tournament details..." required><?= $_POST['description'] ?? '' ?></textarea>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label>Select Game *</label>
                            <select name="game_id" id="game" class="input bg-slate-900" required>
                                <option value="">Target Game</option>
                                <?php foreach ($games as $g): ?>
                                    <option value="<?= $g['game_id'] ?>" data-genre="<?= $g['genre'] ?>" <?= (($_POST['game_id'] ?? '') == $g['game_id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($g['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Genre Type</label>
                            <input id="gameType" class="input bg-slate-800/50 text-blue-300" placeholder="System Detecting..." readonly>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" class="btn btn-primary" onclick="go(2)">Next</button>
                    </div>
                </section>

                <section id="step2" class="<?= $currentStep !== 2 ? 'hidden' : '' ?>">
                    <div class="mb-8 space-y-6">
                        <div class="border border-white/5 rounded-lg p-4 bg-white/5">
                            <label class="text-lg font-bold flex items-center gap-3 mb-4 cursor-pointer">
                                <input type="checkbox" id="checkStandard" checked onclick="toggleFormat('standard')" class="w-5 h-5 accent-blue-500">
                                <span>Standard Format</span>
                            </label>
                            <div id="standardGrid" class="format-section grid grid-cols-3 gap-4">
                                <?php foreach ([12, 16, 24] as $p): ?>
                                    <div class="card p-card <?= (($_POST['max_participants'] ?? '') == $p ? 'selected' : '') ?>" onclick="pick(<?= $p ?>, this)">
                                        <?= $p ?> Teams
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="border border-white/5 rounded-lg p-4 bg-white/5">
                            <label class="text-lg font-bold flex items-center gap-3 mb-4 cursor-pointer">
                                <input type="checkbox" id="checkElim" onclick="toggleFormat('elim')" class="w-5 h-5 accent-blue-500">
                                <span>Single Elimination</span>
                            </label>
                            <div id="elimGrid" class="format-section disabled grid grid-cols-3 md:grid-cols-6 gap-3">
                                <?php foreach ([8, 16, 32, 64, 128, 256] as $p): ?>
                                    <div class="card p-card" onclick="pick(<?= $p ?>, this)">
                                        <?= $p ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="max_participants" id="max_participants" value="<?= $_POST['max_participants'] ?? 12 ?>">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label>Team Size</label>
                            <input type="number" name="team_size" class="input" value="<?= $_POST['team_size'] ?? 5 ?>" min="1">
                        </div>
                        <div>
                            <label>Entry Fee (USD)</label>
                            <input type="number" step="0.01" name="fee" class="input" value="<?= $_POST['fee'] ?? 0 ?>">
                        </div>
                        <div>
                            <label>Prize Pool (USD)</label>
                            <input type="number" step="0.01" name="prize_pool" class="input" value="<?= $_POST['prize_pool'] ?? 0 ?>">
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" class="btn btn-outline" onclick="go(1)">Back</button>
                        <button type="button" class="btn btn-primary" onclick="go(3)">Proceed</button>
                    </div>
                </section>

                <section id="step3" class="<?= $currentStep !== 3 ? 'hidden' : '' ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label>Registration Start *</label>
                            <input type="date" id="regStart" name="registration_start_date" class="input" value="<?= $_POST['registration_start_date'] ?? '' ?>">
                        </div>
                        <div>
                            <label>Registration Deadline *</label>
                            <input type="date" id="regEnd" name="registration_deadline" class="input" value="<?= $_POST['registration_deadline'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="mb-8">
                        <label>Tournament Kickoff *</label>
                        <input type="date" id="startDate" name="start_date" class="input" value="<?= $_POST['start_date'] ?? '' ?>">
                    </div>

                    <div class="mb-8">
                        <label class="font-bold">Tournament Structure Preview</label>
                        <div id="bracketPreview" class="bracket rounded-lg min-h-[120px] flex items-center justify-center">
                        </div>
                    </div>

                    <div class="flex justify-between border-t border-white/10 pt-6">
                        <button type="button" class="btn btn-outline" onclick="go(2)">Back</button>
                        <button type="submit" name="btnCreate" class="btn btn-primary">⚡ Deploy Tournament</button>
                    </div>
                </section>
            </div>
        </form>
    </div>

    <script>
        let step = <?= $currentStep ?>;
        const today = new Date().toISOString().split('T')[0];

        const regStart = document.getElementById('regStart');
        const regEnd = document.getElementById('regEnd');
        const startDate = document.getElementById('startDate');
        const maxParticipantsInput = document.getElementById('max_participants');
        const bracketPreview = document.getElementById('bracketPreview');

        regStart.min = today;
        regEnd.min = today;
        startDate.min = today;

        function go(n) {
            step = n;
            document.getElementById('current_step').value = step;
            ['step1', 'step2', 'step3'].forEach((id, i) => {
                document.getElementById(id).classList.toggle('hidden', i + 1 !== step);
            });
            document.getElementById('stepNum').textContent = step;
            document.getElementById('progress').style.width = (step / 3 * 100) + '%';
        }

        function toggleFormat(type) {
            const isStandard = (type === 'standard');
            document.getElementById('checkStandard').checked = isStandard;
            document.getElementById('checkElim').checked = !isStandard;

            const stdGrid = document.getElementById('standardGrid');
            const elimGrid = document.getElementById('elimGrid');

            if (isStandard) {
                stdGrid.classList.remove('disabled');
                elimGrid.classList.add('disabled');
            } else {
                elimGrid.classList.remove('disabled');
                stdGrid.classList.add('disabled');
            }
            
            // Clear selections when switching formats
            document.querySelectorAll('.p-card').forEach(c => c.classList.remove('selected'));
            maxParticipantsInput.value = "";
            bracketPreview.innerHTML = "";
        }

        function pick(v, el) {
            maxParticipantsInput.value = v;
            document.querySelectorAll('.p-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            generateBracket(v);
        }

        function generateBracket(teams) {
            bracketPreview.innerHTML = '';
            teams = parseInt(teams);
            if (!teams) return;

            let groupCount = teams > 8 ? 4 : 2;
            let baseGroupSize = Math.floor(teams / groupCount);
            let extra = teams % groupCount;

            for (let g = 1; g <= groupCount; g++) {
                let size = baseGroupSize + (extra > 0 ? 1 : 0);
                if (extra > 0) extra--;
                let col = document.createElement('div');
                col.className = 'flex-shrink-0';
                col.innerHTML = `<h3 class="text-[9px] text-blue-400 font-bold mb-2 uppercase">Pool ${g}</h3>`;
                for (let i = 1; i <= size; i++) {
                    col.innerHTML += `<div class="match text-gray-400">Team</div>`;
                }
                bracketPreview.appendChild(col);
            }
        }

        document.getElementById('game').addEventListener('change', function() {
            document.getElementById('gameType').value = this.options[this.selectedIndex].dataset.genre || '';
        });

        regStart.addEventListener('change', () => regEnd.min = regStart.value);
        regEnd.addEventListener('change', () => startDate.min = regEnd.value);

        if (maxParticipantsInput.value) generateBracket(maxParticipantsInput.value);
    </script>
</body>
</html>
