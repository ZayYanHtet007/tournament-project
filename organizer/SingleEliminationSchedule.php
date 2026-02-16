<?php
session_start();
require_once "../database/dbConfig.php";

/* ---------- AUTH ---------- */
if (!isset($_SESSION['user_id'])) {
    die("Login required");
}

$organizer_id = $_SESSION['user_id'];
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if (!$tournament_id) {
    die("Invalid tournament");
}

$stmt = $conn->prepare("
    SELECT tournament_id, max_participants, type, status, title
    FROM tournaments
    WHERE tournament_id = ? AND organizer_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $tournament_id, $organizer_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

if (!$tournament || $tournament['type'] !== 'singleelimination') {
    die("Unauthorized access");
}

$maxParticipants = (int)$tournament['max_participants'];
$tournamentStatus = $tournament['status'];

/* ---------- CHECK JOINED TEAMS ---------- */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tournament_teams
    WHERE tournament_id = ?
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$joined = (int)$stmt->get_result()->fetch_assoc()['total'];

$teamsNeeded = $maxParticipants - $joined;

/* ---------- ROUND ORDER ---------- */
$roundOrder = [
    'R256','R128','R64','R32','R16',
    'quarterfinal','semifinal','final'
];

/* ---------- GENERATE ALL MATCHES IF NONE EXIST ---------- */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM matches
    WHERE tournament_id = ?
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$existingMatches = (int)$stmt->get_result()->fetch_assoc()['total'];

if ($teamsNeeded <= 0 && $existingMatches === 0) {
    $firstRoundMap = [
        256 => 'R256',
        128 => 'R128',
        64  => 'R64',
        32  => 'R32',
        16  => 'R16',
        8   => 'quarterfinal'
    ];

    if (!isset($firstRoundMap[$maxParticipants])) {
        die("Unsupported participant count");
    }

    $firstRound = $firstRoundMap[$maxParticipants];
    $firstRoundIndex = array_search($firstRound, $roundOrder);
    if ($firstRoundIndex === false) {
        die("Invalid round mapping");
    }

    // Get all teams and shuffle
    $stmt = $conn->prepare("
        SELECT team_id
        FROM tournament_teams
        WHERE tournament_id = ?
    ");
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (count($teams) !== $maxParticipants || count($teams) % 2 !== 0) {
        die("Invalid team count for match generation");
    }
    shuffle($teams);

    // Build list of rounds with number of matches
    $rounds = [];
    $matchCount = $maxParticipants / 2;
    for ($i = $firstRoundIndex; $i < count($roundOrder); $i++) {
        $rounds[] = [
            'name' => $roundOrder[$i],
            'matches' => $matchCount
        ];
        $matchCount = (int)($matchCount / 2);
        if ($matchCount < 1) break;
    }

    mysqli_begin_transaction($conn);
    try {
        $teamPointer = 0;
        foreach ($rounds as $roundInfo) {
            $roundName = $roundInfo['name'];
            $numMatches = $roundInfo['matches'];

            for ($m = 0; $m < $numMatches; $m++) {
                if ($roundName === $firstRound) {
                    // First round: actual teams
                    $team1 = $teams[$teamPointer]['team_id'];
                    $team2 = $teams[$teamPointer + 1]['team_id'];
                    $teamPointer += 2;
                    $stmt = $conn->prepare("
                        INSERT INTO matches
                        (tournament_id, round, team1_id, team2_id, status)
                        VALUES (?, ?, ?, ?, 'pending')
                    ");
                    $stmt->bind_param("isii", $tournament_id, $roundName, $team1, $team2);
                } else {
                    // Later rounds: placeholders
                    $stmt = $conn->prepare("
                        INSERT INTO matches
                        (tournament_id, round, team1_id, team2_id, status)
                        VALUES (?, ?, NULL, NULL, 'pending')
                    ");
                    $stmt->bind_param("is", $tournament_id, $roundName);
                }
                $stmt->execute();
            }
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("Match generation failed: " . $e->getMessage());
    }
}

/* ---------- FETCH ALL MATCHES FOR DISPLAY ---------- */
$matches = $conn->prepare("
    SELECT m.*,
           t1.team_name AS team1_name,
           t2.team_name AS team2_name,
           w.team_name AS winner_name
    FROM matches m
    LEFT JOIN teams t1 ON m.team1_id = t1.team_id
    LEFT JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN teams w ON m.winner_team_id = w.team_id
    WHERE m.tournament_id = ?
    ORDER BY FIELD(m.round, 'R256','R128','R64','R32','R16','quarterfinal','semifinal','final'),
             m.match_id ASC
");
$matches->bind_param("i", $tournament_id);
$matches->execute();
$matchesResult = $matches->get_result();

/* ---------- HANDLE POST (SAVE SCHEDULES) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = 'match'; // default
    if (isset($_POST['schedule_round']) && is_array($_POST['schedule_round'])) {
        $mode = 'round';
    } elseif (isset($_POST['schedule']) && is_array($_POST['schedule'])) {
        $mode = 'match';
    } else {
        $_SESSION['flash'] = "No schedule data received.";
        header("Location: SingleEliminationSchedule.php?tournament_id=$tournament_id");
        exit;
    }

    $conn->begin_transaction();
    try {
        $updatedCount = 0;
        if ($mode === 'round') {
            foreach ($_POST['schedule_round'] as $round => $datetime) {
                if (empty($datetime)) continue;
                // Convert to MySQL format
                $mysqlDatetime = date('Y-m-d H:i:s', strtotime($datetime));
                if ($mysqlDatetime === false) continue;

                $stmt = $conn->prepare("
                    UPDATE matches
                    SET scheduled_time = ?
                    WHERE tournament_id = ?
                      AND round = ?
                      AND status = 'pending'
                ");
                $stmt->bind_param("sis", $mysqlDatetime, $tournament_id, $round);
                $stmt->execute();
                $updatedCount += $stmt->affected_rows;
            }
        } else {
            foreach ($_POST['schedule'] as $match_id => $datetime) {
                if (empty($datetime)) continue;
                $mysqlDatetime = date('Y-m-d H:i:s', strtotime($datetime));
                if ($mysqlDatetime === false) continue;

                $stmt = $conn->prepare("
                    UPDATE matches
                    SET scheduled_time = ?
                    WHERE match_id = ? AND tournament_id = ? AND status = 'pending'
                ");
                $stmt->bind_param("sii", $mysqlDatetime, $match_id, $tournament_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) $updatedCount++;
            }
        }
        $conn->commit();
        $_SESSION['flash'] = "✅ $updatedCount schedule(s) saved.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash'] = "❌ Error: " . $e->getMessage();
    }
    header("Location: SingleEliminationSchedule.php?tournament_id=$tournament_id");
    exit;
}

/* ---------- FETCH TOURNAMENT RESULTS (FOR CHAMPION CARD) ---------- */
$champion = null;
if ($tournamentStatus === 'completed') {
    $result = $conn->prepare("
        SELECT t.team_name
        FROM matches m
        JOIN teams t ON m.winner_team_id = t.team_id
        WHERE m.tournament_id = ? AND m.round = 'final' AND m.status = 'completed'
        LIMIT 1
    ");
    $result->bind_param("i", $tournament_id);
    $result->execute();
    $champion = $result->get_result()->fetch_assoc();
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Determine if any pending matches exist (to enable/disable save button)
$hasPendingMatches = false;
$matchesResult->data_seek(0);
while ($m = $matchesResult->fetch_assoc()) {
    if ($m['status'] === 'pending') {
        $hasPendingMatches = true;
        break;
    }
}
$matchesResult->data_seek(0); // reset for display
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Single Elimination Schedule</title>
    <!-- Font Awesome for icons (monochrome) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Font -->
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
        .max-w-6xl {
            max-width: 1280px;
            margin: 0 auto;
            background-color: #10131c;
            border: 1px solid #2f3a4a;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
            padding: 2rem;
        }

        /* Header */
        .flex.justify-between.items-center {
            margin-bottom: 2rem;
            border-bottom: 1px solid #2f3a4a;
            padding-bottom: 1rem;
        }

        h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #b8d0f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .btn-primary {
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
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #264763;
            border-color: #5a9eff;
        }

        /* Flash message */
        .flash-message {
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

        /* Champion card */
        .champion-card {
            background-color: #1b2535;
            border: 1px solid #2d7ff9;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
            border-radius: 0;
        }
        .champion-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #b8d0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        /* Info/warning box */
        .info-box {
            background-color: #1b2535;
            border: 1px solid #2d7ff9;
            color: #c0dcff;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0;
        }
        .info-box.warning {
            border-color: #f59e0b;
        }
        .info-box p {
            margin-top: 0.25rem;
        }

        /* Mode selector */
        .mode-selector {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0;
        }
        .mode-selector span {
            font-weight: 600;
            color: #b8d0f0;
            margin-right: 1rem;
        }
        .mode-selector label {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            margin-right: 1rem;
        }
        .mode-selector input[type="radio"] {
            accent-color: #2d7ff9;
            width: 1rem;
            height: 1rem;
        }

        /* Round card */
        .round-card {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0;
        }
        .round-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #b8d0f0;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        .round-card input[type="datetime-local"] {
            background-color: #0f141e;
            border: 1px solid #2f3a4a;
            color: #e0e6f0;
            padding: 0.5rem;
            font-size: 0.95rem;
            border-radius: 0;
            width: 100%;
            max-width: 300px;
        }
        .round-card input[type="datetime-local"]:focus {
            outline: none;
            border-color: #2d7ff9;
            box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
        }
        .round-card p {
            color: #9aaec9;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Match row */
        .match-row {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            border-radius: 0;
        }
        .match-row .match-teams {
            width: 250px;
            font-weight: 500;
            color: #d6e2f0;
        }
        .match-row input[type="datetime-local"] {
            background-color: #0f141e;
            border: 1px solid #2f3a4a;
            color: #e0e6f0;
            padding: 0.5rem;
            font-size: 0.95rem;
            border-radius: 0;
            width: 100%;
            max-width: 250px;
        }
        .match-row input[type="datetime-local"]:focus {
            outline: none;
            border-color: #2d7ff9;
            box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
        }
        .match-row input:disabled {
            background-color: #1c222d;
            color: #7c8a9c;
            border-color: #353e4e;
        }
        .winner-badge {
            background-color: #1d2c3a;
            border: 1px solid #2d7ff9;
            color: #b0d0ee;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 0;
            min-width: 250px; 
        }

        /* Round header */
        .round-header {
            font-size: 1.6rem;
            font-weight: 600;
            color: #b8d0f0;
            border-left: 5px solid #2d7ff9;
            padding-left: 1rem;
            margin: 2rem 0 1rem 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Save button */
        .btn-save {
            background-color: #1e3142;
            border: 1px solid #2d7ff9;
            color: white;
            font-weight: 600;
            padding: 0.9rem 2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: 0.1s;
            border-radius: 0;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-save:hover:not(:disabled) {
            background-color: #264763;
            border-color: #5a9eff;
        }
        .btn-save:disabled {
            background-color: #1a1f2a;
            border-color: #353e4e;
            color: #6b7a8f;
            cursor: not-allowed;
        }

        /* Icons */
        i {
            color: inherit;
        }

        /* Utility classes */
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .items-center { align-items: center; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-4 { gap: 1rem; }
        .gap-2 { gap: 0.5rem; }
        .mt-6 { margin-top: 1.5rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .text-sm { font-size: 0.875rem; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .inline-block { display: inline-block; }
    </style>
    <script>
        function toggleMode(mode) {
            const roundDiv = document.getElementById('round-mode');
            const matchDiv = document.getElementById('match-mode');

            if (mode === 'round') {
                roundDiv.style.display = 'block';
                matchDiv.style.display = 'none';
                // Enable all round inputs, disable all match inputs
                roundDiv.querySelectorAll('input').forEach(input => input.disabled = false);
                matchDiv.querySelectorAll('input').forEach(input => input.disabled = true);
            } else {
                roundDiv.style.display = 'none';
                matchDiv.style.display = 'block';
                // Enable all match inputs, disable all round inputs
                roundDiv.querySelectorAll('input').forEach(input => input.disabled = true);
                matchDiv.querySelectorAll('input').forEach(input => input.disabled = false);
            }
        }

        // Initialize on page load: match mode active, round inputs disabled
        window.onload = function() {
            toggleMode('match');
        };
    </script>
</head>
<body>
    <div class="max-w-6xl">
        <div class="flex justify-between items-center mb-6">
            <h1><i class="fas fa-calendar-alt"></i> Schedule – <?= htmlspecialchars($tournament['title'] ?? '') ?></h1>
            <a href="singleEliminationScore.php?tournament_id=<?= $tournament_id ?>" class="btn-primary">
                <i class="fas fa-arrow-right"></i> Go to Score Entry
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message">
                <i class="fas fa-info-circle"></i>
                <?= $flash ?>
            </div>
        <?php endif; ?>

        <?php if ($tournamentStatus === 'completed' && $champion): ?>
            <div class="champion-card">
                <h2><i class="fas fa-crown" style="color: #ffd966;"></i> Champion: <?= htmlspecialchars($champion['team_name'] ?? '') ?> <i class="fas fa-crown" style="color: #ffd966;"></i></h2>
            </div>
        <?php endif; ?>

        <?php if ($teamsNeeded > 0): ?>
            <div class="info-box warning">
                <p class="font-bold"><i class="fas fa-hourglass-half"></i> Tournament not full</p>
                <p>Currently <?= $joined ?> / <?= $maxParticipants ?> teams registered. <?= $teamsNeeded ?> more team(s) needed to start scheduling.</p>
            </div>
            <!-- Back button when tournament not full -->
            <div class="mt-6">
                <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="btn-primary inline-block">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        <?php else: ?>
            <!-- Mode selection (only if tournament not completed) -->
            <?php if ($tournamentStatus !== 'completed'): ?>
            <div class="mode-selector">
                <span><i class="fas fa-sliders-h"></i> Scheduling mode:</span>
                <label>
                    <input type="radio" name="mode" value="match" checked onclick="toggleMode('match')"> <i class="fas fa-list"></i> Match by match
                </label>
                <label>
                    <input type="radio" name="mode" value="round" onclick="toggleMode('round')"> <i class="fas fa-layer-group"></i> Round by round
                </label>
            </div>
            <?php endif; ?>

            <form method="post">
                <!-- Round-by-round mode (initially hidden and disabled) -->
                <div id="round-mode">
                    <?php
                    $matchesResult->data_seek(0);
                    $roundGroups = [];
                    while ($m = $matchesResult->fetch_assoc()) {
                        $roundGroups[$m['round']][] = $m;
                    }
                    foreach ($roundGroups as $round => $roundMatches):
                        $hasPending = false;
                        foreach ($roundMatches as $m) {
                            if ($m['status'] === 'pending') {
                                $hasPending = true;
                                break;
                            }
                        }
                        if (!$hasPending) continue;
                    ?>
                        <div class="round-card">
                            <h3><i class="fas fa-circle"></i> <?= strtoupper($round) ?></h3>
                            <input type="datetime-local"
                                   name="schedule_round[<?= $round ?>]"
                                   min="<?= date('Y-m-d\TH:i') ?>"
                                   <?= $tournamentStatus === 'completed' ? 'disabled' : '' ?>>
                            <p class="text-sm">This time will be applied to all <?= count($roundMatches) ?> matches of this round.</p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Match-by-match mode (default visible) -->
                <div id="match-mode">
                    <?php
                    $matchesResult->data_seek(0);
                    $currentRound = '';
                    while ($m = $matchesResult->fetch_assoc()):
                        if ($currentRound !== $m['round']):
                            $currentRound = $m['round'];
                            echo "<h2 class='round-header'><i class='fas fa-bracket-curly'></i> " . strtoupper($currentRound) . "</h2>";
                        endif;

                        $teamA = $m['team1_name'] ?? 'TBD';
                        $teamB = $m['team2_name'] ?? 'TBD';
                        $isCompleted = ($m['status'] === 'completed');
                        $disabled = ($tournamentStatus === 'completed' || $isCompleted) ? 'disabled' : '';
                    ?>
                        <div class="match-row">
                            <div class="match-teams">
                                <?= htmlspecialchars($teamA) ?> vs <?= htmlspecialchars($teamB) ?>
                            </div>
                            <div style="flex:1;">
                                <input type="datetime-local"
                                       name="schedule[<?= $m['match_id'] ?>]"
                                       value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>"
                                       min="<?= date('Y-m-d\TH:i') ?>"
                                       <?= $disabled ?>>
                            </div>
                            <?php if ($isCompleted): ?>
                                <span class="winner-badge">
                                    <i class="fas fa-check-circle"></i> <span class="winner">Winner: &nbsp;</span> <span><?= htmlspecialchars($m['winner_name'] ?? '') ?></span>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Bottom action buttons: Back on left, Save on right (if applicable) -->
                <div class="mt-6 flex justify-between items-center">
                    <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="btn-primary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <?php if ($tournamentStatus !== 'completed' && $hasPendingMatches): ?>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Schedules</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>