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

if ($joined < $maxParticipants) {
    $needed = $maxParticipants - $joined;
    die("Tournament needs $needed more teams to start scheduling.");
}

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

if ($existingMatches === 0) {
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
                $ts = strtotime($datetime);
                if ($ts === false || $ts <= time()) continue;

                $stmt = $conn->prepare("
                    UPDATE matches
                    SET scheduled_time = ?
                    WHERE tournament_id = ?
                      AND round = ?
                      AND status = 'pending'
                ");
                $stmt->bind_param("sis", $datetime, $tournament_id, $round);
                $stmt->execute();
                $updatedCount += $stmt->affected_rows;
            }
        } else {
            foreach ($_POST['schedule'] as $match_id => $datetime) {
                if (empty($datetime)) continue;
                $ts = strtotime($datetime);
                if ($ts === false || $ts <= time()) continue;

                $stmt = $conn->prepare("
                    UPDATE matches
                    SET scheduled_time = ?
                    WHERE match_id = ? AND tournament_id = ? AND status = 'pending'
                ");
                $stmt->bind_param("sii", $datetime, $match_id, $tournament_id);
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function toggleMode(mode) {
            document.getElementById('round-mode').style.display = mode === 'round' ? 'block' : 'none';
            document.getElementById('match-mode').style.display = mode === 'match' ? 'block' : 'none';
        }
    </script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">📅 Schedule – <?= htmlspecialchars($tournament['title'] ?? '') ?></h1>
            <a href="singleEliminationScore.php?tournament_id=<?= $tournament_id ?>"
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                🏆 Go to Score Entry
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $flash ?>
            </div>
        <?php endif; ?>

        <?php if ($tournamentStatus === 'completed' && $champion): ?>
            <div class="bg-gradient-to-r from-yellow-100 to-yellow-200 border border-yellow-400 rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold text-center text-yellow-800">🏆 Champion: <?= htmlspecialchars($champion['team_name'] ?? '') ?> 🏆</h2>
            </div>
        <?php endif; ?>

        <!-- Mode selection (only if tournament not completed) -->
        <?php if ($tournamentStatus !== 'completed'): ?>
        <div class="bg-white p-4 rounded shadow mb-6">
            <span class="font-semibold mr-4">Scheduling mode:</span>
            <label class="mr-4">
                <input type="radio" name="mode" value="match" checked onclick="toggleMode('match')"> Match by match
            </label>
            <label>
                <input type="radio" name="mode" value="round" onclick="toggleMode('round')"> Round by round
            </label>
        </div>
        <?php endif; ?>

        <form method="post">
            <!-- Round-by-round mode (hidden by default) -->
            <div id="round-mode" style="display: none;">
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
                    <div class="bg-white p-4 rounded shadow mb-4">
                        <h3 class="font-semibold text-lg mb-2"><?= strtoupper($round) ?></h3>
                        <input type="datetime-local"
                        name="schedule_round[<?= $round ?>]"
                        min="<?= date('Y-m-d\TH:i') ?>"
                        class="border rounded px-3 py-2 w-full max-w-xs"
                        <?= $tournamentStatus === 'completed' ? 'disabled' : '' ?>
                        <?= $tournamentStatus !== 'completed' ? 'required' : '' ?>>
                        <p class="text-sm text-gray-500 mt-1">This time will be applied to all <?= count($roundMatches) ?> matches of this round.</p>
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
                        echo "<h2 class='text-xl font-semibold mt-6 mb-2'>" . strtoupper($currentRound) . "</h2>";
                    endif;

                    $teamA = $m['team1_name'] ?? 'TBD';
                    $teamB = $m['team2_name'] ?? 'TBD';
                    $isCompleted = ($m['status'] === 'completed');
                    $disabled = ($tournamentStatus === 'completed' || $isCompleted) ? 'disabled' : '';
                ?>
                    <div class="bg-white p-4 rounded shadow mb-2 flex flex-wrap items-center gap-4">
                        <div class="w-64">
                            <span class="font-medium"><?= htmlspecialchars($teamA) ?> vs <?= htmlspecialchars($teamB) ?></span>
                        </div>
                        <div class="flex-1">
                        <input type="datetime-local"
                        name="schedule[<?= $m['match_id'] ?>]"
                        value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>"
                        min="<?= date('Y-m-d\TH:i') ?>"
                        class="border rounded px-3 py-2 w-full max-w-xs"
                        <?= $disabled ?>
                        <?= (!$isCompleted && $tournamentStatus !== 'completed') ? 'required' : '' ?>>
                        </div>
                        <?php if ($isCompleted): ?>
                            <span class="text-green-600 font-semibold text-sm bg-green-50 px-3 py-1 rounded">
                                ✓ Winner: <?= htmlspecialchars($m['winner_name'] ?? '') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Save button – always visible, disabled when tournament completed or no pending matches -->
            <div class="mt-6 flex justify-end">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded shadow disabled:opacity-50 disabled:cursor-not-allowed"
                        <?= ($tournamentStatus === 'completed' || !$hasPendingMatches) ? 'disabled' : '' ?>>
                    💾 Save Schedules
                </button>
            </div>
        </form>
    </div>
</body>
</html>