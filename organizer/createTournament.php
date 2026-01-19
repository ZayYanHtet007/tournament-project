<?php
session_start();
require_once "../database/dbConfig.php";

/* ---------- ACCESS CONTROL ---------- */
if (
    !isset($_SESSION['user_id']) ||
    !$_SESSION['is_organizer'] ||
    $_SESSION['organizer_status'] !== 'approved'
) {
    header("Location: ../login.php");
    exit;
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
    if ($today > $start) return 'completed';
    return 'upcoming';
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

    $reg_start = $_POST['registration_start_date'] ?? '';
    $reg_end   = $_POST['registration_deadline'] ?? '';
    $start     = $_POST['start_date'] ?? '';

    /* ---------- SERVER VALIDATION (MIN 12 ENFORCED) ---------- */
    if (
        !$game_id || !$title || !$description ||
        $max_participants < 12 ||
        !$team_size ||
        !valid_date($reg_start) ||
        !valid_date($reg_end) ||
        !valid_date($start)
    ) {
        $message = "❌ Minimum participants must be at least 12.";
        $currentStep = 2;
    } elseif ($reg_start >= $reg_end) {
        $message = "❌ Registration start must be before registration deadline.";
        $currentStep = 2;
    } elseif ($start <= $reg_end) {
        $message = "❌ Tournament start date must be after registration deadline.";
        $currentStep = 3;
    } else {

        $status = calculateStatus($reg_start, $start);

        $stmt = $conn->prepare("
            INSERT INTO tournaments
            (organizer_id, game_id, title, description,
             max_participants, team_size, fee,
             registration_start_date, registration_deadline, start_date,
             status, admin_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->bind_param(
            "iissiiissss",
            $organizer_id,
            $game_id,
            $title,
            $description,
            $max_participants,
            $team_size,
            $fee,
            $reg_start,
            $reg_end,
            $start,
            $status
        );

        if ($stmt->execute()) {
            header("Location: stripe-payment.php?tournament_id=" . $stmt->insert_id);
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
    <title>Create Tournament</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/organizer/createtour.css">
    <link rel="stylesheet" href="../css/user/responsive.css">
</head>
<body>
<div id="root"></div>

<script>
/* -------------------- DATA -------------------- */

const GAMES = [
    { name: 'League of Legends', type: 'MOBA' },
    { name: 'Dota 2', type: 'MOBA' },
    { name: 'Counter-Strike 2', type: 'FPS' },
    { name: 'Valorant', type: 'FPS' },
    { name: 'Overwatch 2', type: 'FPS' },
    { name: 'Rocket League', type: 'Sports' },
    { name: 'FIFA 24', type: 'Sports' },
    { name: 'Street Fighter 6', type: 'Fighting' },
    { name: 'Tekken 8', type: 'Fighting' },
    { name: 'Fortnite', type: 'Battle Royale' },
    { name: 'Apex Legends', type: 'Battle Royale' },
    { name: 'Minecraft', type: 'Sandbox' },
    { name: 'Starcraft II', type: 'RTS' },
    { name: 'Age of Empires IV', type: 'RTS' },
    { name: 'Hearthstone', type: 'Card Game' },
    { name: 'Magic: The Gathering Arena', type: 'Card Game' },
];

let currentStep = 1;

const formData = {
    title: '',
    description: '',
    gameName: '',
    gameType: '',
    maxParticipants: null,
    entryFee: '',
    registrationStartDate: '',
    registrationDeadline: '',
    gameStartDate: '',
    status: 'upcoming'
};

const root = document.getElementById('root');

/* -------------------- RENDER -------------------- */

function render() {
    root.innerHTML = `
        <div class="min-h-screen p-8">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-3xl font-bold text-center mb-8">Create Tournament</h1>

                <div class="card">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold">Step ${currentStep} of 3</h2>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:${currentStep * 33.33}%"></div>
                        </div>
                    </div>
                    <div class="card-content" id="stepContent"></div>
                </div>
            </div>
        </div>
    `;

    renderStep();
}

/* -------------------- STEP RENDERERS -------------------- */

function renderStep() {
    const container = document.getElementById('stepContent');

    if (currentStep === 1) container.innerHTML = step1();
    if (currentStep === 2) container.innerHTML = step2();
    if (currentStep === 3) container.innerHTML = step3();

}

/* -------------------- STEP 1 -------------------- */

function step1() {
    return `
        <div class="space-y-6">
            <div>
                <label class="block mb-2 font-medium">Tournament Title</label>
                <input class="input" value="${formData.title}" 
                    oninput="formData.title=this.value">
            </div>

            <div>
                <label class="block mb-2 font-medium">Description</label>
                <textarea class="input" rows="4"
                    oninput="formData.description=this.value">${formData.description}</textarea>
            </div>

            <div>
                <label class="block mb-2 font-medium">Game</label>
                <select class="input" onchange="selectGame(this.value)">
                    <option value="">Select game...</option>
                    ${GAMES.map(g =>
                        `<option value="${g.name}" ${g.name===formData.gameName?'selected':''}>${g.name}</option>`
                    ).join('')}
                </select>
            </div>

            ${formData.gameType ? `
                <div>
                    <label class="block mb-2 font-medium">Game Type</label>
                    <input class="input" disabled value="${formData.gameType}">
                </div>
            ` : ''}

            <div class="flex justify-end">
                <button class="btn btn-primary" onclick="nextStep()" 
                    ${!formData.title || !formData.description || !formData.gameName ? 'disabled':''}>
                    Next
                </button>
            </div>
        </div>
    `;
}

function selectGame(name) {
    const game = GAMES.find(g => g.name === name);
    formData.gameName = game?.name || '';
    formData.gameType = game?.type || '';
    render();
}

/* -------------------- STEP 2 -------------------- */

function step2() {
    const options = [12, 16, 24];

    return `
        <div class="space-y-6">
            <div>
                <label class="block mb-2 font-medium">Max Participants</label>
                <div class="grid grid-cols-3 gap-4">
                    ${options.map(o => `
                        <div class="participant-card ${formData.maxParticipants===o?'selected':''}"
                            onclick="setParticipants(${o})">
                            <div class="text-2xl font-bold">${o}</div>
                            <div class="text-sm text-gray-500">Participants</div>
                        </div>
                    `).join('')}
                </div>
            </div>

            ${formData.maxParticipants ? bracketPreview(formData.maxParticipants) : ''}

            <div>
                <label class="block mb-2 font-medium">Entry Fee</label>
                <input class="input" type="number" value="${formData.entryFee}"
                    oninput="formData.entryFee=this.value">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <input class="input" type="datetime-local"
                    value="${formData.registrationStartDate}"
                    onchange="formData.registrationStartDate=this.value">
                <input class="input" type="datetime-local"
                    value="${formData.registrationDeadline}"
                    onchange="formData.registrationDeadline=this.value">
            </div>

            <div class="flex justify-between">
                <button class="btn btn-outline" onclick="prevStep()">Back</button>
                <button class="btn btn-primary" onclick="nextStep()">Next</button>
            </div>
        </div>
    `;
}

function setParticipants(n) {
    formData.maxParticipants = n;
    render();
}

/* -------------------- BRACKET -------------------- */

function bracketPreview(players) {
    const rounds = Math.log2(players);

    return `
        <div>
            <label class="block mb-2 font-medium">Bracket Preview</label>
            <div class="bracket-container">
                ${Array.from({length: rounds}).map((_, i) => `
                    <div class="bracket-round">
                        <strong>${i+1===rounds?'Final':`Round ${i+1}`}</strong>
                        ${Array.from({length: players / (2 ** (i+1))}).map(() =>
                            `<div class="bracket-match">
                                <div class="bracket-player">Player</div>
                                <div class="bracket-player">Player</div>
                            </div>`
                        ).join('')}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

/* -------------------- STEP 3 -------------------- */

function step3() {
    return `
        <div class="space-y-6">
            <div>
                <label class="block mb-2 font-medium">Game Start Date</label>
                <input class="input" type="datetime-local"
                    value="${formData.gameStartDate}"
                    onchange="formData.gameStartDate=this.value">
            </div>

            <div class="bg-gray-50 p-6 rounded">
                <h3 class="font-semibold mb-4">Summary</h3>
                <p><strong>${formData.title}</strong></p>
                <p>${formData.gameName} (${formData.gameType})</p>
                <p>Participants: ${formData.maxParticipants}</p>
                <p>Entry Fee: $${formData.entryFee || 0}</p>
            </div>

            <div class="flex justify-between">
                <button class="btn btn-outline" onclick="prevStep()">Back</button>
                <div class="flex gap-2">
                    <button class="btn btn-outline" onclick="saveDraft()">💾 Save Draft</button>
                    <button class="btn btn-primary" onclick="createTournament()">🏆 Create</button>
                </div>
            </div>
        </div>
    `;
}

/* -------------------- NAV -------------------- */

function nextStep() {
    if (currentStep < 3) currentStep++;
    render();
}

function prevStep() {
    if (currentStep > 1) currentStep--;
    render();
}

function saveDraft() {
    console.log('Draft:', formData);
    alert('Saved as draft');
}

function createTournament() {
    console.log('Created:', formData);
    alert('Tournament created!');
}

/* -------------------- INIT -------------------- */

render();
</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .input {
            width: 100%;
            padding: .5rem;
            border: 1px solid #e5e7eb;
            border-radius: .375rem
        }

        .btn {
            padding: .5rem .75rem;
            border-radius: .375rem;
            cursor: pointer
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
            border: none
        }

        .btn-outline {
            border: 1px solid #d1d5db;
            background: #fff
        }

        .hidden {
            display: none
        }

        .card {
            border: 1px solid #e5e7eb;
            padding: .75rem;
            text-align: center;
            cursor: pointer;
            border-radius: .375rem
        }

        .card.selected {
            background: #eff6ff;
            border-color: #2563eb
        }

        .bracket {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 10px
        }

        .round {
            min-width: 160px
        }

        .match {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px;
            margin-bottom: 12px;
            text-align: center
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-4 text-center">Create Tournament</h1>

        <?php if ($message): ?>
            <div class="text-red-600 mb-4"><?= $message ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="current_step" id="current_step" value="<?= $currentStep ?>">

            <div class="bg-white p-6 rounded shadow">

                <!-- STEP INDICATOR -->
                <div class="flex items-center mb-4">
                    Step <span id="stepNum" class="mx-1"><?= $currentStep ?></span> / 3
                    <div class="flex-1 ml-4 bg-gray-200 h-2 rounded">
                        <div id="progress" class="bg-blue-600 h-2 rounded" style="width:<?= $currentStep / 3 * 100 ?>%"></div>
                    </div>
                </div>

                <!-- STEP 1 -->
                <section id="step1" class="<?= $currentStep !== 1 ? 'hidden' : '' ?>">
                    <label>Title *</label>
                    <input name="title" class="input mb-4" value="<?= $_POST['title'] ?? '' ?>">

                    <label>Description *</label>
                    <textarea name="description" class="input mb-4"><?= $_POST['description'] ?? '' ?></textarea>

                    <label>Game *</label>
                    <select name="game_id" id="game" class="input mb-4">
                        <option value="">Select game</option>
                        <?php foreach ($games as $g): ?>
                            <option value="<?= $g['game_id'] ?>" data-genre="<?= $g['genre'] ?>" <?= (($_POST['game_id'] ?? '') == $g['game_id'] ? 'selected' : '') ?>>
                                <?= htmlspecialchars($g['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Game Type</label>
                    <input id="gameType" class="input mb-4 bg-gray-100" readonly>

                    <button type="button" class="btn btn-primary" onclick="go(2)">Next</button>
                </section>

                <!-- STEP 2 -->
                <section id="step2" class="<?= $currentStep !== 2 ? 'hidden' : '' ?>">
                    <label>Max Participants *</label>
                    <div class="grid grid-cols-4 gap-3 mb-4">
                        <?php foreach ([12, 16, 24] as $p): ?>
                            <div class="card <?= (($_POST['max_participants'] ?? '') == $p ? 'selected' : '') ?>" onclick="pick(<?= $p ?>)">
                                <?= $p ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <input type="number" name="max_participants" id="max_participants" class="input mb-4"
                        value="<?= $_POST['max_participants'] ?? 12 ?>" min="12" step="1">

                    <label>Team Size *</label>
                    <input type="number" name="team_size" class="input mb-4" value="<?= $_POST['team_size'] ?? 5 ?>" min="1">

                    <label>Entry Fee</label>
                    <input type="number" step="0.01" name="fee" class="input mb-4" min="0" value="<?= $_POST['fee'] ?? 0 ?>">

                    <label>Registration Start *</label>
                    <input type="date" id="regStart" name="registration_start_date" class="input mb-4" value="<?= $_POST['registration_start_date'] ?? '' ?>">

                    <label>Registration Deadline *</label>
                    <input type="date" id="regEnd" name="registration_deadline" class="input mb-4" value="<?= $_POST['registration_deadline'] ?? '' ?>">

                    <button type="button" class="btn btn-outline" onclick="go(1)">Back</button>
                    <button type="button" class="btn btn-primary float-right" onclick="go(3)">Next</button>
                </section>

                <!-- STEP 3 -->
                <section id="step3" class="<?= $currentStep !== 3 ? 'hidden' : '' ?>">
                    <label>Start Date *</label>
                    <input type="date" id="startDate" name="start_date" class="input mb-4" value="<?= $_POST['start_date'] ?? '' ?>">

                    <label>Status</label>
                    <input type="text" class="input mb-4 bg-gray-100" value="Will auto-update" readonly>

                    <h2 class="font-semibold mb-2">Bracket Preview</h2>
                    <div id="bracketPreview" class="bracket bg-gray-100 rounded mb-4"></div>

                    <button type="button" class="btn btn-outline" onclick="go(2)">Back</button>
                    <button type="submit" name="btnCreate" class="btn btn-primary float-right">Create</button>
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
        const stepNum = document.getElementById('stepNum');
        const progress = document.getElementById('progress');
        const game = document.getElementById('game');
        const gameType = document.getElementById('gameType');
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
            stepNum.textContent = step;
            progress.style.width = (step / 3 * 100) + '%';
        }

        game.addEventListener('change', () => {
            gameType.value = game.options[game.selectedIndex].dataset.genre || '';
        });

        regStart.addEventListener('change', () => {
            regEnd.min = regStart.value;
        });
        regEnd.addEventListener('change', () => {
            startDate.min = regEnd.value;
        });

        function pick(v) {
            maxParticipantsInput.value = v;
            document.querySelectorAll('.card').forEach(c => c.classList.remove('selected'));
            event.target.classList.add('selected');
            generateBracket(v);
        }

        function generateBracket(teams) {
            bracketPreview.innerHTML = '';
            teams = parseInt(teams);

            if (!teams || teams < 12) {
                bracketPreview.innerHTML = '<p class="text-sm text-gray-500">Minimum 12 teams required</p>';
                return;
            }

            let groupCount = teams > 8 ? 4 : 2;
            let baseGroupSize = Math.floor(teams / groupCount);
            let extra = teams % groupCount;

            for (let g = 1; g <= groupCount; g++) {
                let size = baseGroupSize + (extra > 0 ? 1 : 0);
                if (extra > 0) extra--;

                let col = document.createElement('div');
                col.className = 'round';
                col.innerHTML = `<h3>Group ${g} (${size} teams)</h3>`;
                for (let i = 1; i <= size; i++) {
                    col.innerHTML += `<div class="match">Team TBD</div>`;
                }
                bracketPreview.appendChild(col);
            }
        }

        maxParticipantsInput.addEventListener('input', () => {
            generateBracket(maxParticipantsInput.value);
        });

        if (maxParticipantsInput.value) {
            generateBracket(maxParticipantsInput.value);
        }
    </script>
</body>

</html>