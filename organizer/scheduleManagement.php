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

$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if (!$tournament_id) {
    die("Invalid tournament");
}

/* ---------- FETCH TOURNAMENT INFO ---------- */
$stmt = $conn->prepare("
    SELECT tournament_id, organizer_id, title, max_participants, type, status
    FROM tournaments
    WHERE tournament_id = ? AND organizer_id = ?
");
$stmt->bind_param("ii", $tournament_id, $_SESSION['user_id']);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
if (!$tournament) {
    die("Tournament not found or access denied");
}

// Only proceed if tournament type is 'standard'
if ($tournament['type'] !== 'standard') {
    die("This tournament does not use the standard bracket system.");
}

/* ---------- COUNT REGISTERED TEAMS ---------- */
$teamCountResult = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM tournament_teams 
    WHERE tournament_id = $tournament_id
");
$teamCount = $teamCountResult->fetch_assoc()['cnt'];

/* ---------- CHECK IF MATCHES ALREADY EXIST ---------- */
$matchCheck = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM matches 
    WHERE tournament_id = $tournament_id
");
$matchesExist = $matchCheck->fetch_assoc()['cnt'] > 0;

/* ---------- AUTOMATIC MATCH GENERATION ---------- */
// Conditions:
// 1. Tournament status is 'ongoing'
// 2. Registered teams == max_participants
// 3. No matches exist yet
// 4. Team count is one of 12, 16, 24
if (
    $tournament['status'] === 'ongoing' &&
    $teamCount == $tournament['max_participants'] &&
    !$matchesExist &&
    in_array($teamCount, [12, 16, 24])
) {
    // Fetch all teams (shuffled)
    $teams = [];
    $q = $conn->prepare("
        SELECT t.team_id, t.team_name
        FROM tournament_teams tt
        JOIN teams t ON tt.team_id = t.team_id
        WHERE tt.tournament_id = ?
    ");
    $q->bind_param("i", $tournament_id);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $teams[] = $row;
    }
    shuffle($teams);

    // Determine teams per group
    $perGroup = $teamCount / 4; // 3, 4, or 6
    $groups = array_chunk($teams, $perGroup);
    $groupNames = ['A', 'B', 'C', 'D'];

    // Begin transaction
    $conn->begin_transaction();
    try {
        // 1. Insert initial group_standings rows for every team
        $insertStanding = $conn->prepare("
            INSERT INTO group_standings 
                (tournament_id, team_id, group_name, played, wins, losses, points, net_game, duration)
            VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0)
        ");
        foreach ($groups as $gi => $groupTeams) {
            $group = $groupNames[$gi];
            foreach ($groupTeams as $team) {
                $insertStanding->bind_param("iis", $tournament_id, $team['team_id'], $group);
                $insertStanding->execute();
            }
        }

        // 2. Generate group stage matches (round robin)
        $order = 1;
        $insertMatch = $conn->prepare("
            INSERT INTO matches 
                (tournament_id, round, group_name, match_order, team1_id, team2_id, status)
            VALUES (?, 'group', ?, ?, ?, ?, 'pending')
        ");
        foreach ($groups as $gi => $groupTeams) {
            $group = $groupNames[$gi];
            for ($i = 0; $i < count($groupTeams); $i++) {
                for ($j = $i + 1; $j < count($groupTeams); $j++) {
                    $insertMatch->bind_param(
                        "isiii",
                        $tournament_id,
                        $group,
                        $order,
                        $groupTeams[$i]['team_id'],
                        $groupTeams[$j]['team_id']
                    );
                    $insertMatch->execute();
                    $order++;
                }
            }
        }

        // 3. Generate knockout placeholders (team1_id = team2_id = NULL)
        $knockoutRounds = [
            'quarterfinal' => 4,
            'semifinal'    => 2,
            'final'        => 1,
            'third_place'  => 1
        ];
        $insertKnockout = $conn->prepare("
            INSERT INTO matches 
                (tournament_id, round, match_order, status)
            VALUES (?, ?, ?, 'pending')
        ");
        foreach ($knockoutRounds as $round => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $plusOrder = $order++;
                $insertKnockout->bind_param("isi", $tournament_id, $round, $plusOrder);
                $insertKnockout->execute();
            }
        }

        $conn->commit();

        // Redirect to avoid resubmission
        header("Location: scheduleManagement.php?tournament_id=$tournament_id&generated=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die("Failed to generate matches: " . $e->getMessage());
    }
}

/* ---------- BULK SCHEDULE UPDATE ---------- */
$flashMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule'])) {
    $updatedCount = 0;
    $conn->begin_transaction();
    try {
        $updateStmt = $conn->prepare("
            UPDATE matches 
            SET scheduled_time = ? 
            WHERE match_id = ? AND tournament_id = ?
        ");
        foreach ($_POST['schedule'] as $match_id => $datetime) {
            $match_id = (int)$match_id;
            // Skip empty datetime strings
            if ($datetime === '') continue;

            $updateStmt->bind_param("sii", $datetime, $match_id, $tournament_id);
            $updateStmt->execute();
            if ($updateStmt->affected_rows > 0) {
                $updatedCount++;
            }
        }
        $conn->commit();
        $_SESSION['flash'] = "✅ $updatedCount match schedule(s) saved successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash'] = "❌ Error saving schedules: " . $e->getMessage();
    }
    header("Location: scheduleManagement.php?tournament_id=$tournament_id");
    exit;
}

/* ---------- FETCH ALL MATCHES FOR DISPLAY ---------- */
$matches = $conn->query("
    SELECT m.*, 
           t1.team_name AS team1, 
           t2.team_name AS team2
    FROM matches m
    LEFT JOIN teams t1 ON m.team1_id = t1.team_id
    LEFT JOIN teams t2 ON m.team2_id = t2.team_id
    WHERE m.tournament_id = $tournament_id
    ORDER BY 
        FIELD(m.round, 'group', 'quarterfinal', 'semifinal', 'final', 'third_place'),
        m.group_name,
        m.match_order
");

// Capture flash message and clear it
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Schedule</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Riot style blue‑black theme with enhanced gaming touches */
        body {
            background-color: #0a0c10;
            color: #e8e9ea;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
        }
        .riot-card {
            border-top: 4px solid #0b4a8f;
            transition: all 0.3s ease;
            background-color: #1a1c22;
            border-color: #2f3136;
            box-shadow: 0 0 10px rgba(11, 74, 143, 0.3);
            position: relative;
            overflow: hidden;
        }
        .riot-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(11, 74, 143, 0.2) 50%,
                transparent 70%
            );
            transform: rotate(25deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        .riot-card:hover::after {
            opacity: 1;
            animation: shine 1.5s infinite;
        }
        .riot-card:hover {
            box-shadow: 0 0 25px rgba(11, 74, 143, 0.9);
            transform: translateY(-2px);
        }
        @keyframes shine {
            0% { transform: rotate(25deg) translateX(-100%); }
            100% { transform: rotate(25deg) translateX(100%); }
        }
        .group-badge {
            background: linear-gradient(135deg, #0b4a8f 0%, #0a1a2b 100%);
            text-shadow: 0 0 8px rgba(255,255,255,0.5);
        }
        input[type="datetime-local"] {
            background-color: #2d2f36;
            border-color: #3f424a;
            color: #ffffff;
            border-radius: 0.5rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        input[type="datetime-local"]:focus {
            ring-color: #0b4a8f;
            border-color: #0b4a8f;
            outline: none;
            box-shadow: 0 0 15px #0b4a8f;
        }
        input[type="datetime-local"]:read-only {
            background-color: #1e1f25;
            color: #9ca3af;
            cursor: default;
            border-color: #3f424a;
            box-shadow: none;
        }
        .round-header {
            border-bottom-color: #0b4a8f;
            text-shadow: 0 0 10px #0b4a8f;
        }
        .completed-badge {
            background-color: #065f46;
            color: #d1fae5;
            border: 1px solid #10b981;
            box-shadow: 0 0 8px #10b981;
        }
        .btn-gaming {
            background: linear-gradient(145deg, #0b4a8f, #062c4f);
            border: 1px solid #2f6bb0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-gaming::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255,255,255,0.3) 50%,
                transparent 70%
            );
            transform: rotate(25deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        .btn-gaming:hover::after {
            opacity: 1;
            animation: shine 1.5s infinite;
        }
        .btn-gaming:hover {
            background: linear-gradient(145deg, #0f5bb0, #083a66);
            box-shadow: 0 0 25px #0b4a8f;
            transform: scale(1.05);
        }
        .back-btn {
            background: linear-gradient(145deg, #2d2f36, #1a1c22);
            border: 1px solid #0b4a8f;
        }
        .back-btn:hover {
            background: linear-gradient(145deg, #3a3d47, #23262e);
            box-shadow: 0 0 20px #0b4a8f;
        }
    </style>
</head>
<body class="p-6 font-sans antialiased">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-blue-400 flex items-center gap-2" style="text-shadow: 0 0 12px #3b82f6;">
                <span>📅</span> Match Schedule – <?= htmlspecialchars($tournament['title']) ?>
            </h1>
            <a href="resultManagement.php?tournament_id=<?= $tournament_id ?>" 
               class="btn-gaming text-white px-5 py-2.5 rounded-lg transition shadow-md flex items-center gap-2 border border-blue-500">
                🏆 Go to Bracket
            </a>
        </div>

        <?php if (isset($_GET['generated'])): ?>
            <div class="bg-blue-900 border-l-4 border-blue-400 text-blue-100 px-4 py-3 rounded-lg shadow-lg mb-4 flex items-center">
                <span class="text-xl mr-2">✅</span> Tournament matches have been generated successfully.
            </div>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="bg-blue-900 border-l-4 border-blue-400 text-blue-100 px-4 py-3 rounded-lg shadow-lg mb-4 flex items-center">
                <span class="text-xl mr-2">ℹ️</span> <?= $flash ?>
            </div>
        <?php endif; ?>

        <?php if ($matches->num_rows === 0): ?>
            <div class="bg-gray-800 border-l-4 border-yellow-600 text-yellow-200 px-4 py-3 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <span class="text-xl mr-2">⏳</span>
                    <div>
                        No matches yet. 
                        <?php if ($tournament['status'] !== 'ongoing'): ?>
                            Tournament status is <strong><?= $tournament['status'] ?></strong>. 
                            Matches will be generated when status becomes 'ongoing' and all teams have registered.
                        <?php elseif ($teamCount < $tournament['max_participants']): ?>
                            Waiting for more teams to register (<?= $teamCount ?> / <?= $tournament['max_participants'] ?>).
                        <?php elseif (!in_array($teamCount, [12,16,24])): ?>
                            This tournament has <?= $teamCount ?> teams, but only 12, 16, or 24 are supported for standard bracket.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: 
            // Reorganize matches by round and group
            $matchesArray = [];
            while ($row = $matches->fetch_assoc()) {
                $round = $row['round'];
                if ($round === 'group') {
                    $group = $row['group_name'] ?? '?';
                    $matchesArray['group'][$group][] = $row;
                } else {
                    $matchesArray[$round][] = $row;
                }
            }

            // Define round order for display
            $roundOrder = ['group', 'quarterfinal', 'semifinal', 'final', 'third_place'];
            $roundLabels = [
                'group' => 'Group Stage',
                'quarterfinal' => 'Quarterfinals',
                'semifinal' => 'Semifinals',
                'final' => 'Final',
                'third_place' => 'Third Place Match'
            ];
        ?>
            <form method="post" class="space-y-6">
                <?php foreach ($roundOrder as $round): ?>
                    <?php if (!isset($matchesArray[$round])) continue; ?>

                    <!-- Round heading -->
                    <h2 class="text-2xl font-bold text-gray-200 border-b-2 border-blue-800 pb-1 mt-6 first:mt-0 flex items-center round-header">
                        <span class="bg-blue-700 text-white text-sm px-3 py-1 rounded-full mr-3 shadow-lg">
                            <?= $round === 'group' ? '👥' : '🏆' ?>
                        </span>
                        <?= $roundLabels[$round] ?? ucfirst($round) ?>
                    </h2>

                    <?php if ($round === 'group'): ?>
                        <!-- Group Stage: each group in its own card -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
                            <?php 
                            $groupNames = ['A', 'B', 'C', 'D'];
                            foreach ($groupNames as $group): 
                                if (!isset($matchesArray['group'][$group])) continue;
                                $groupMatches = $matchesArray['group'][$group];
                            ?>
                                <div class="riot-card rounded-xl shadow-md overflow-hidden border border-gray-700">
                                    <div class="group-badge px-4 py-3 text-white font-bold text-lg flex items-center">
                                        <span class="bg-white text-blue-800 rounded-full w-7 h-7 flex items-center justify-center mr-2 text-sm">⚔️</span>
                                        Group <?= $group ?>
                                        <span class="ml-auto text-sm bg-blue-900 px-3 py-1 rounded-full border border-blue-400"><?= count($groupMatches) ?> matches</span>
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <?php foreach ($groupMatches as $m): ?>
                                            <div class="flex flex-wrap items-center gap-3 border-b border-gray-700 pb-2 last:border-0">
                                                <div class="w-8 text-sm font-medium text-gray-400">#<?= $m['match_order'] ?></div>
                                                <div class="flex-1 font-medium text-gray-200">
                                                    <?= htmlspecialchars($m['team1'] ?? 'TBD') ?> 
                                                    <span class="text-gray-500 mx-1">vs</span> 
                                                    <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <input type="datetime-local" 
                                                           name="schedule[<?= $m['match_id'] ?>]" 
                                                           value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>"
                                                           <?= $m['status'] === 'completed' ? 'readonly' : '' ?>
                                                           class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-600 focus:border-blue-600">
                                                    <?php if ($m['status'] === 'completed'): ?>
                                                        <span class="completed-badge text-xs font-semibold px-2.5 py-1 rounded-full">✓ Completed</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Knockout Rounds: each round in a single card -->
                        <div class="riot-card rounded-xl shadow-md overflow-hidden border border-gray-700 mt-4">
                            <div class="bg-gray-800 px-4 py-3 border-b border-gray-700 flex items-center">
                                <span class="text-blue-400 font-semibold">🏅 <?= $roundLabels[$round] ?? ucfirst($round) ?></span>
                                <span class="ml-auto text-sm text-gray-400"><?= count($matchesArray[$round]) ?> matches</span>
                            </div>
                            <div class="p-4 space-y-3">
                                <?php foreach ($matchesArray[$round] as $m): ?>
                                    <div class="flex flex-wrap items-center gap-3 border-b border-gray-700 pb-2 last:border-0">
                                        <div class="w-8 text-sm font-medium text-gray-400">#<?= $m['match_order'] ?></div>
                                        <div class="flex-1 font-medium text-gray-200">
                                            <?= htmlspecialchars($m['team1'] ?? 'TBD') ?> 
                                            <span class="text-gray-500 mx-1">vs</span> 
                                            <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="datetime-local" 
                                                   name="schedule[<?= $m['match_id'] ?>]" 
                                                   value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>"
                                                   <?= $m['status'] === 'completed' ? 'readonly' : '' ?>
                                                   class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-600 focus:border-blue-600">
                                            <?php if ($m['status'] === 'completed'): ?>
                                                <span class="completed-badge text-xs font-semibold px-2.5 py-1 rounded-full">✓ Completed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="mt-8 flex justify-between items-center">
                    <!-- Back button at bottom left -->
                    <a href="tournaments.php" 
                       class="back-btn text-white px-6 py-3 rounded-lg shadow-lg transition transform hover:scale-105 flex items-center gap-2 border border-blue-500">
                        <span>←</span> Back to Dashboard
                    </a>
                    <!-- Save button at bottom right -->
                    <button type="submit" 
                            class="btn-gaming text-white font-bold py-3 px-8 rounded-lg shadow-lg transition transform hover:scale-105 flex items-center gap-2 border border-blue-500">
                        <span>💾</span> Save Schedules
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Disable past dates in datetime-local inputs (except readonly ones)
        (function() {
            const now = new Date();
            // Format: YYYY-MM-DDTHH:MM (local)
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;

            document.querySelectorAll('input[type="datetime-local"]:not([readonly])').forEach(input => {
                input.setAttribute('min', minDateTime);
            });
        })();
    </script>
</body>
</html>