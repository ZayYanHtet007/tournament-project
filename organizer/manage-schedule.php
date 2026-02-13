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
                $insertKnockout->bind_param("isi", $tournament_id, $round, $order++);
                $insertKnockout->execute();
            }
        }

        $conn->commit();

        // Redirect to avoid resubmission
        header("Location: manage-schedule.php?tournament_id=$tournament_id&generated=1");
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
    header("Location: manage-schedule.php?tournament_id=$tournament_id");
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
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">📅 Match Schedule – <?= htmlspecialchars($tournament['title']) ?></h1>
            <a href="manage-bracket.php?tournament_id=<?= $tournament_id ?>" 
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                🏆 Go to Bracket
            </a>
        </div>

        <?php if (isset($_GET['generated'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                ✅ Tournament matches have been generated successfully.
            </div>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                <?= $flash ?>
            </div>
        <?php endif; ?>

        <?php if ($matches->num_rows === 0): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                ⏳ No matches yet. 
                <?php if ($tournament['status'] !== 'ongoing'): ?>
                    Tournament status is <strong><?= $tournament['status'] ?></strong>. 
                    Matches will be generated when status becomes 'ongoing' and all teams have registered.
                <?php elseif ($teamCount < $tournament['max_participants']): ?>
                    Waiting for more teams to register (<?= $teamCount ?> / <?= $tournament['max_participants'] ?>).
                <?php elseif (!in_array($teamCount, [12,16,24])): ?>
                    This tournament has <?= $teamCount ?> teams, but only 12, 16, or 24 are supported for standard bracket.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post" class="space-y-4">
                <?php
                $currentRound = '';
                while ($m = $matches->fetch_assoc()):
                    if ($currentRound !== $m['round']):
                        $currentRound = $m['round'];
                        echo "<h2 class='text-xl font-semibold mt-6 mb-2 capitalize'>" . str_replace('_', ' ', $currentRound) . "</h2>";
                    endif;
                ?>
                    <div class="bg-white p-4 rounded shadow flex flex-wrap items-center gap-4">
                        <div class="w-64">
                            <?= $m['group_name'] ? "<span class='text-sm font-medium bg-gray-200 px-2 py-1 rounded'>Group {$m['group_name']}</span>" : '' ?>
                            <span class="font-medium">
                                <?= htmlspecialchars($m['team1'] ?? 'TBD') ?> vs <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                            </span>
                        </div>
                        <div class="flex-1">
                            <input type="datetime-local" 
                                   name="schedule[<?= $m['match_id'] ?>]" 
                                   value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>"
                                   class="border rounded px-3 py-2 w-full max-w-xs">
                        </div>
                        <?php if ($m['status'] === 'completed'): ?>
                            <span class="text-green-600 font-semibold text-sm bg-green-50 px-3 py-1 rounded">✓ Completed</span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>

                <div class="mt-6 flex justify-end">
                    <button type="submit" 
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded shadow">
                        💾 Save All Schedules
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>