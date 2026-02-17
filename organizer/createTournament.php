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
$q = $conn->query("SELECT game_id, name, genre FROM games WHERE game_status = 'available' 
                   ORDER BY name");
while ($row = $q->fetch_assoc()) {
    $games[] = $row;
}

/* ---------- FORM SUBMIT ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnCreate'])) {
    $currentStep = isset($_POST['current_step']) ? (int)$_POST['current_step'] : 1;
    $organizer_id = (int)$_SESSION['user_id'];
    $game_id = (int)($_POST['game_id'] ?? 0);
    $title = clean($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'standard';
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
        $_SESSION['pending_tournament'] = [
            'organizer_id' => $organizer_id,
            'game_id' => $game_id,
            'title' => $title,
            'type' => $type,                       // <-- added here
            'description' => $description,
            'max_participants' => $max_participants,
            'team_size' => $team_size,
            'fee' => $fee,
            'prize_pool' => $prize_pool,
            'registration_start_date' => $reg_start,
            'registration_deadline' => $reg_end,
            'start_date' => $start
        ];
       
            header("Location: stripe-payment.php?tournament_id=" . $tournamentId);
            exit;
        } 
    }

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Tournament | Pro Gamer Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Font Awesome for icons (monochrome) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ================= SQUARE DESIGN, BRIGHTER BLUE ACCENTS ================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #0b0d12;
            color: #e1e5ec;
            font-family: 'Inter', sans-serif;
            padding: 2rem 1rem;
            min-height: 100vh;
        }

        /* Main container – dark, square */
        .max-w-4xl {
            max-width: 1280px;
            margin: 0 auto;
            background-color: #10131c;
            border: 1px solid #2f3a4a;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
            padding: 2rem;
        }

        /* Header with title and back button */
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid #2f3a4a;
            padding-bottom: 1rem;
        }

        h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #b8d0f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        /* Back button */
        .btn-back {
            background-color: #1e3142;
            border: 1px solid #2d7ff9;
            color: white;
            font-weight: 500;
            padding: 0.7rem 1.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.1s;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border-radius: 0;
        }
        .btn-back:hover {
            background-color: #264763;
            border-color: #5a9eff;
        }

        /* Alert message */
        .bg-red-900\/50 {
            background-color: #1b2535;
            border: 1px solid #2d7ff9;
            color: #c0dcff;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 0;
        }

        /* Glass card */
        .glass-card {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6);
            padding: 1.5rem;
        }

        /* Progress bar */
        .bg-gray-800 {
            background-color: #1f2632;
        }
        #progress {
            background: #2d7ff9;
            height: 100%;
            transition: width 0.5s;
        }

        /* Step indicator */
        .text-center.text-blue-400 {
            color: #b8d0f0;
            font-weight: 500;
        }

        /* Labels */
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: #9aaec9;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Inputs */
        .input, select.input, textarea.input {
            width: 100%;
            padding: 0.75rem;
            background-color: #0f141e;
            border: 1px solid #2f3a4a;
            color: #e0e6f0;
            font-size: 0.95rem;
            border-radius: 0;
            transition: 0.1s;
            margin-bottom: 1.5rem;
        }
        .input:focus, select.input:focus, textarea.input:focus {
            outline: none;
            border-color: #2d7ff9;
            box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
        }
        .input[readonly] {
            background-color: #1c222d;
            color: #7c8a9c;
            border-color: #353e4e;
        }

        /* Buttons */
        .btn {
            padding: 0.7rem 1.5rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: 0.1s;
            border-radius: 0;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background-color: #1e3142;
            border: 1px solid #2d7ff9;
            color: white;
        }
        .btn-primary:hover {
            background-color: #264763;
            border-color: #5a9eff;
        }
        .btn-outline {
            background-color: transparent;
            border: 1px solid #2f3a4a;
            color: #b8d0f0;
        }
        .btn-outline:hover {
            border-color: #2d7ff9;
            background-color: #1e3142;
        }

        /* Format cards */
        .format-section {
            margin-bottom: 1.5rem;
        }
        .card {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: 0.1s;
        }
        .card:hover {
            border-color: #2d7ff9;
            box-shadow: 0 0 10px #2d7ff9;
        }
        .card.selected {
            border: 2px solid #2d7ff9;
            box-shadow: 0 0 15px #2d7ff9;
            background-color: #1e3142;
        }
        .disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        /* Bracket preview container with relative positioning for SVG lines */
        .bracket-container {
            position: relative;
            border: 1px solid #2f3a4a;
            background-color: #0f141e;
            min-height: 120px;
            overflow-x: auto;
        }
        .bracket {
            display: flex;
            gap: 30px;
            padding: 1rem;
            align-items: center;
            justify-content: center;
            min-width: max-content;
        }
        .round {
            display: flex;
            flex-direction: column;
            gap: 15px;
            position: relative;
        }
        .match {
            background-color: #161b26;
            border-left: 3px solid #2d7ff9;
            padding: 8px 12px;
            font-size: 11px;
            color: #9aaec9;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0,0,0,0.5);
            min-width: 80px;
            text-align: center;
        }
        /* SVG overlay for lines */
        .bracket-svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 5;
        }
        .bracket-svg line {
            stroke: #2d7ff9;
            stroke-width: 2;
            stroke-dasharray: 5,3;
        }

        /* Icons */
        i {
            color: inherit;
        }

        /* Grid utilities */
        .grid {
            display: grid;
            gap: 1rem;
        }
        .grid-cols-1 { grid-template-columns: repeat(1, 1fr); }
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .md\:grid-cols-2 { @media (min-width: 768px) { grid-template-columns: repeat(2, 1fr); } }
        .md\:grid-cols-3 { @media (min-width: 768px) { grid-template-columns: repeat(3, 1fr); } }
        .md\:grid-cols-6 { @media (min-width: 768px) { grid-template-columns: repeat(6, 1fr); } }
        .gap-6 { gap: 1.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }

        /* Margins */
        .mb-6 { margin-bottom: 1.5rem; }
        .mb-8 { margin-bottom: 2rem; }
        .mt-3 { margin-top: 0.75rem; }
        .mt-6 { margin-top: 1.5rem; }
        .pt-6 { padding-top: 1.5rem; }

        /* Flex */
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .justify-end { justify-content: flex-end; }
        .items-center { align-items: center; }
        .gap-3 { gap: 0.75rem; }

        /* Hidden */
        .hidden { display: none; }

        /* Border */
        .border-t { border-top: 1px solid #2f3a4a; }

        /* Text colors */
        .text-blue-400 { color: #b8d0f0; }
        .text-white { color: #e1e5ec; }
        .text-gray-400 { color: #9aaec9; }
        .text-sm { font-size: 0.875rem; }
        .tracking-widest { letter-spacing: 1px; }
        .font-bold { font-weight: 600; }
    </style>
</head>

<body class="p-4 md:p-10">
    <div class="max-w-4xl mx-auto">
        <!-- Header with title and back button -->
        <div class="header-row">
            <h1><i class="fas fa-bolt"></i> Create Tournament</h1>
            <a href="organizerDashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($message): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-100 p-4 rounded mb-6">
                <i class="fas fa-exclamation-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="current_step" id="current_step" value="<?= $currentStep ?>">
            <!-- Hidden input for tournament type -->
            <input type="hidden" name="type" id="tournamentType" value="standard">

            <div class="glass-card p-6 md:p-8">
                <div class="mb-8">
                    <div class="bg-gray-800 h-1.5">
                        <div id="progress" style="width:<?= $currentStep / 3 * 100 ?>%"></div>
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
                        <button type="button" class="btn btn-primary" onclick="go(2)"><i class="fas fa-arrow-right"></i> Next</button>
                    </div>
                </section>

                <section id="step2" class="<?= $currentStep !== 2 ? 'hidden' : '' ?>">
                    <div class="mb-8 space-y-6">
                        <div class="border border-white/5 p-4 bg-white/5">
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

                        <div class="border border-white/5 p-4 bg-white/5">
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
                        <button type="button" class="btn btn-outline" onclick="go(1)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-primary" onclick="go(3)">Proceed <i class="fas fa-arrow-right"></i></button>
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
                        <div id="bracketContainer" class="bracket-container" style="min-height: 150px;">
                            <div id="bracketPreview" class="bracket"></div>
                            <svg id="bracketSvg" class="bracket-svg"></svg>
                        </div>
                    </div>

                    <div class="flex justify-between border-t border-white/10 pt-6">
                        <button type="button" class="btn btn-outline" onclick="go(2)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="submit" name="btnCreate" class="btn btn-primary"><i class="fas fa-bolt"></i> Deploy Tournament</button>
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
        const bracketSvg = document.getElementById('bracketSvg');
        const bracketContainer = document.getElementById('bracketContainer');
        const tournamentTypeInput = document.getElementById('tournamentType');

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

            // If moving to step3 and we have a single elimination bracket, redraw lines
            if (step === 3 && currentFormat === 'elim' && bracketPreview.children.length > 0) {
                setTimeout(() => {
                    clearSvg();
                    drawBracketLines();
                }, 100);
            }
        }

        // Track current format
        let currentFormat = 'standard'; // standard or elim

        function toggleFormat(type) {
            const isStandard = (type === 'standard');
            document.getElementById('checkStandard').checked = isStandard;
            document.getElementById('checkElim').checked = !isStandard;

            // Update hidden input value
            tournamentTypeInput.value = isStandard ? 'standard' : 'singleelimination';

            const stdGrid = document.getElementById('standardGrid');
            const elimGrid = document.getElementById('elimGrid');

            if (isStandard) {
                stdGrid.classList.remove('disabled');
                elimGrid.classList.add('disabled');
                currentFormat = 'standard';
            } else {
                elimGrid.classList.remove('disabled');
                stdGrid.classList.add('disabled');
                currentFormat = 'elim';
            }
            
            // Clear selections when switching formats
            document.querySelectorAll('.p-card').forEach(c => c.classList.remove('selected'));
            maxParticipantsInput.value = "";
            bracketPreview.innerHTML = "";
            clearSvg();

            // If a team count was already selected, regenerate preview for new format
            if (maxParticipantsInput.value) {
                generateBracket(maxParticipantsInput.value, currentFormat);
            }
        }

        function pick(v, el) {
            maxParticipantsInput.value = v;
            document.querySelectorAll('.p-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            generateBracket(v, currentFormat);
        }

        function generateBracket(teams, format) {
            if (format === 'standard') {
                generateStandardBracket(teams);
            } else {
                generateSingleElimBracket(teams);
            }
        }

        function generateStandardBracket(teams) {
            bracketPreview.innerHTML = '';
            clearSvg();
            teams = parseInt(teams);
            if (!teams) return;

            let groupCount = teams > 8 ? 4 : 2;
            let baseGroupSize = Math.floor(teams / groupCount);
            let extra = teams % groupCount;

            for (let g = 1; g <= groupCount; g++) {
                let size = baseGroupSize + (extra > 0 ? 1 : 0);
                if (extra > 0) extra--;
                let roundDiv = document.createElement('div');
                roundDiv.className = 'round';
                roundDiv.innerHTML = `<h3 class="text-[9px] text-blue-400 font-bold mb-2 uppercase">Pool ${g}</h3>`;
                for (let i = 1; i <= size; i++) {
                    roundDiv.innerHTML += `<div class="match">Team</div>`;
                }
                bracketPreview.appendChild(roundDiv);
            }
        }

        function generateSingleElimBracket(teams) {
            bracketPreview.innerHTML = '';
            clearSvg();
            teams = parseInt(teams);
            if (!teams || teams < 2) return;

            // Number of rounds = log2(teams)
            let rounds = Math.log2(teams);
            let roundNames = [];
            if (teams === 256) roundNames = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
            else if (teams === 128) roundNames = ['R64', 'R32', 'R16', 'QF', 'SF', 'F'];
            else if (teams === 64) roundNames = ['R32', 'R16', 'QF', 'SF', 'F'];
            else if (teams === 32) roundNames = ['R16', 'QF', 'SF', 'F'];
            else if (teams === 16) roundNames = ['R16', 'QF', 'SF', 'F'];
            else if (teams === 8) roundNames = ['QF', 'SF', 'F'];
            else roundNames = Array(rounds).fill('Round');

            let matchesPerRound = teams / 2;
            for (let r = 0; r < rounds; r++) {
                let roundDiv = document.createElement('div');
                roundDiv.className = 'round';
                roundDiv.dataset.round = r;
                roundDiv.dataset.matches = matchesPerRound;
                let roundLabel = roundNames[r] || `Round ${r+1}`;
                roundDiv.innerHTML = `<h3 class="text-[9px] text-blue-400 font-bold mb-2 uppercase">${roundLabel}</h3>`;
                for (let m = 0; m < matchesPerRound; m++) {
                    roundDiv.innerHTML += `<div class="match" data-match="${m}">Match</div>`;
                }
                bracketPreview.appendChild(roundDiv);
                matchesPerRound = Math.floor(matchesPerRound / 2);
            }

            // Draw lines after a short delay to ensure DOM is updated
            setTimeout(drawBracketLines, 50);
        }

        function clearSvg() {
            bracketSvg.innerHTML = '';
        }

        function drawBracketLines() {
            if (currentFormat !== 'elim') return;
            
            const rounds = bracketPreview.querySelectorAll('.round');
            if (rounds.length < 2) return;

            // Get container dimensions
            const containerRect = bracketContainer.getBoundingClientRect();
            bracketSvg.setAttribute('viewBox', `0 0 ${containerRect.width} ${containerRect.height}`);
            
            // For each round except the last, draw lines from matches to next round
            for (let r = 0; r < rounds.length - 1; r++) {
                const currentRound = rounds[r];
                const nextRound = rounds[r + 1];
                
                const currentMatches = currentRound.querySelectorAll('.match');
                const nextMatches = nextRound.querySelectorAll('.match');
                
                for (let i = 0; i < currentMatches.length; i++) {
                    const match = currentMatches[i];
                    const matchRect = match.getBoundingClientRect();
                    
                    // Determine which next match this connects to (floor(i/2))
                    const nextIndex = Math.floor(i / 2);
                    if (nextIndex >= nextMatches.length) continue;
                    
                    const nextMatch = nextMatches[nextIndex];
                    const nextMatchRect = nextMatch.getBoundingClientRect();
                    
                    // Calculate line start (right side of current match, center Y)
                    const startX = matchRect.right - containerRect.left;
                    const startY = matchRect.top + matchRect.height/2 - containerRect.top;
                    
                    // Calculate line end (left side of next match, center Y)
                    const endX = nextMatchRect.left - containerRect.left;
                    const endY = nextMatchRect.top + nextMatchRect.height/2 - containerRect.top;
                    
                    // Create SVG line
                    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                    line.setAttribute("x1", startX);
                    line.setAttribute("y1", startY);
                    line.setAttribute("x2", endX);
                    line.setAttribute("y2", endY);
                    line.setAttribute("stroke", "#2d7ff9");
                    line.setAttribute("stroke-width", "2");
                    line.setAttribute("stroke-dasharray", "5,3");
                    bracketSvg.appendChild(line);
                }
            }
        }

        // Redraw lines on window resize
        window.addEventListener('resize', () => {
            if (currentFormat === 'elim' && bracketPreview.children.length > 0) {
                clearSvg();
                drawBracketLines();
            }
        });

        document.getElementById('game').addEventListener('change', function() {
            document.getElementById('gameType').value = this.options[this.selectedIndex].dataset.genre || '';
        });

        regStart.addEventListener('change', () => regEnd.min = regStart.value);
        regEnd.addEventListener('change', () => startDate.min = regEnd.value);

        // Initialize with default value if any
        if (maxParticipantsInput.value) generateBracket(maxParticipantsInput.value, currentFormat);
    </script>
</body>
</html>