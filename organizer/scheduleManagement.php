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
    <title>Manage Schedule – Tournament Dashboard</title>
    <!-- Simple, clean font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ----- SQUARE DESIGN, BRIGHTER BLUE ACCENTS, MINIMAL ICONS (NO COLOR) ----- */
        body {
            background-color: #0b0d12;
            color: #e1e5ec;
            font-family: 'Inter', sans-serif;
            padding: 2rem 1rem;
        }

        /* Main container – dark, square */
        .square-container {
            max-width: 1280px;
            margin: 0 auto;
            background-color: #10131c;
            border: 1px solid #2f3a4a;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
            padding: 2rem;
        }

        /* Header – brighter blue border */
        .header-title {
            font-size: 2rem;
            font-weight: 600;
            color: #b8d0f0;
            border-bottom: 2px solid #2d7ff9; /* brighter blue */
            padding-bottom: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Square cards */
        .square-card {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6);
            transition: all 0.15s ease;
        }
        .square-card:hover {
            border-color: #2d7ff9; /* bright blue */
            box-shadow: 0 0 15px #2d7ff9;
        }

        /* Card header – flat, no icon colors */
        .card-header-square {
            background-color: #1f2632;
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid #2f3a4a;
            font-weight: 600;
            font-size: 1.2rem;
            color: #d0e0f5;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header-square i {
            color: #a0b8d0; /* muted icon */
            width: 1.6rem;
            text-align: center;
            font-size: 1.2rem;
        }

        /* Match row – straight lines */
        .match-row-square {
            background-color: #1a202b;
            border: 1px solid #2b3442;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
        }
        .match-row-square:hover {
            background-color: #202636;
            border-color: #2d7ff9; /* bright blue */
        }

        /* Team name */
        .team-name-square {
            font-weight: 500;
            color: #d6e2f0;
        }

        /* Input fields – sharp edges */
        input[type="datetime-local"] {
            background-color: #0f141e;
            border: 1px solid #2f3a4a;
            color: #e0e6f0;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 0; /* square */
            transition: 0.1s;
        }
        input[type="datetime-local"]:focus {
            outline: none;
            border-color: #2d7ff9; /* bright blue */
            box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
        }
        input[type="datetime-local"]:read-only {
            background-color: #1c222d;
            color: #7c8a9c;
            border-color: #353e4e;
        }

        /* Completed badge – simple, with bright blue border */
        .completed-badge-square {
            background-color: #1d2c3a;
            color: #b0d0ee;
            border: 1px solid #2d7ff9; /* bright blue */
            padding: 0.25rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Group badge – brighter blue border */
        .group-badge-square {
            background-color: #1b2533;
            border: 1px solid #2d7ff9; /* bright blue */
            color: #c0dcff;
            padding: 0.25rem 1.2rem;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Buttons – square, brighter blue */
        .btn-square-primary {
            background-color: #1e3142;
            border: 1px solid #2d7ff9; /* bright blue */
            color: white;
            font-weight: 500;
            padding: 0.7rem 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.1s;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border-radius: 0;
        }
        .btn-square-primary:hover {
            background-color: #264763;
            border-color: #5a9eff; /* even brighter */
        }

        .btn-square-secondary {
            background-color: #222834;
            border: 1px solid #3f4b5c;
            color: #d0dbea;
            font-weight: 500;
            padding: 0.7rem 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.1s;
            border-radius: 0;
        }
        .btn-square-secondary:hover {
            background-color: #2b3442;
            border-color: #2d7ff9; /* bright blue */
            color: white;
        }

        /* Flash message – square with bright blue border */
        .flash-square {
            background-color: #1b2535;
            border: 1px solid #2d7ff9; /* bright blue */
            color: #c0dcff;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Round label – bright blue left border */
        .round-label-square {
            font-size: 1.6rem;
            font-weight: 600;
            color: #b8d0f0;
            border-left: 5px solid #2d7ff9; /* bright blue */
            padding-left: 1rem;
            margin: 2rem 0 1rem 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .round-label-square i {
            color: #7f9bc0;
            font-size: 1.4rem;
        }

        /* Icons are all muted – no bright colors, but they inherit color from parent */
        i {
            color: inherit;
        }

        /* Force all icons to be monochrome */
        .fa, .fas, .far {
            color: inherit;
        }

        /* Additional bright blue hover effects */
        .square-card:hover .card-header-square {
            border-bottom-color: #2d7ff9;
        }
    </style>
    <!-- Font Awesome for icons (simple, we'll keep them monochrome) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="square-container">
        <!-- Header with tournament title (no colorful icons) -->
        <div class="flex justify-between items-center flex-wrap gap-4 mb-6">
            <h1 class="header-title">
                <i class="fas fa-calendar-alt"></i>
                Match Schedule – <?= htmlspecialchars($tournament['title']) ?>
            </h1>
            <a href="resultManagement.php?tournament_id=<?= $tournament_id ?>" 
               class="btn-square-primary">
                <i class="fas fa-trophy"></i> Go to Bracket
            </a>
        </div>

        <!-- Flash messages -->
        <?php if (isset($_GET['generated'])): ?>
            <div class="flash-square">
                <i class="fas fa-check-circle"></i>
                Tournament matches have been generated successfully.
            </div>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="flash-square">
                <i class="fas fa-info-circle"></i>
                <?= $flash ?>
            </div>
        <?php endif; ?>

        <?php if ($matches->num_rows === 0): ?>
            <!-- No matches card -->
            <div class="square-card">
                <div class="card-header-square">
                    <i class="fas fa-hourglass-half"></i> No Matches Yet
                </div>
                <div class="p-5 text-gray-300 flex items-center gap-3">
                    <i class="fas fa-clock text-2xl"></i>
                    <div>
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
            <form method="post">
                <?php foreach ($roundOrder as $round): ?>
                    <?php if (!isset($matchesArray[$round])) continue; ?>

                    <!-- Round heading -->
                    <div class="round-label-square">
                        <?php if ($round === 'group'): ?>
                            <i class="fas fa-layer-group"></i>
                        <?php elseif ($round === 'quarterfinal'): ?>
                            <i class="fas fa-chart-simple"></i>
                        <?php elseif ($round === 'semifinal'): ?>
                            <i class="fas fa-chart-line"></i>
                        <?php elseif ($round === 'final'): ?>
                            <i class="fas fa-crown"></i>
                        <?php else: ?>
                            <i class="fas fa-medal"></i>
                        <?php endif; ?>
                        <?= $roundLabels[$round] ?? ucfirst($round) ?>
                    </div>

                    <?php if ($round === 'group'): ?>
                        <!-- Group Stage: each group in its own square card -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-2">
                            <?php 
                            $groupNames = ['A', 'B', 'C', 'D'];
                            foreach ($groupNames as $group): 
                                if (!isset($matchesArray['group'][$group])) continue;
                                $groupMatches = $matchesArray['group'][$group];
                            ?>
                                <div class="square-card">
                                    <div class="card-header-square">
                                        <i class="fas fa-users"></i> Group <?= $group ?>
                                        <span class="ml-auto group-badge-square text-sm">
                                            <i class="fas fa-shield-alt"></i> <?= count($groupMatches) ?> matches
                                        </span>
                                    </div>
                                    <div class="p-4 space-y-2">
                                        <?php foreach ($groupMatches as $m): ?>
                                            <div class="match-row-square">
                                                <div class="flex items-center gap-3 w-full md:w-auto flex-1">
                                                    <span class="text-gray-400 text-sm w-8">#<?= $m['match_order'] ?></span>
                                                    <span class="team-name-square">
                                                        <?= htmlspecialchars($m['team1'] ?? 'TBD') ?>
                                                    </span>
                                                    <span class="text-gray-500">vs</span>
                                                    <span class="team-name-square">
                                                        <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-3 ml-auto">
                                                    <input type="datetime-local" 
                                                           name="schedule[<?= $m['match_id'] ?>]" 
                                                           value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>"
                                                           <?= $m['status'] === 'completed' ? 'readonly' : '' ?>
                                                           class="border-0">
                                                    <?php if ($m['status'] === 'completed'): ?>
                                                        <span class="completed-badge-square"><i class="fas fa-check-circle mr-1"></i> Completed</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Knockout Rounds: each round in a single square card -->
                        <div class="square-card mt-4">
                            <div class="card-header-square">
                                <i class="fas fa-bracket-curly"></i> <?= $roundLabels[$round] ?? ucfirst($round) ?>
                                <span class="ml-auto text-sm text-gray-400"><?= count($matchesArray[$round]) ?> matches</span>
                            </div>
                            <div class="p-4 space-y-2">
                                <?php foreach ($matchesArray[$round] as $m): ?>
                                    <div class="match-row-square">
                                        <div class="flex items-center gap-3 w-full md:w-auto flex-1">
                                            <span class="text-gray-400 text-sm w-8">#<?= $m['match_order'] ?></span>
                                            <span class="team-name-square">
                                                <?= htmlspecialchars($m['team1'] ?? 'TBD') ?>
                                            </span>
                                            <span class="text-gray-500">vs</span>
                                            <span class="team-name-square">
                                                <?= htmlspecialchars($m['team2'] ?? 'TBD') ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3 ml-auto">
                                            <input type="datetime-local" 
                                                   name="schedule[<?= $m['match_id'] ?>]" 
                                                   value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>"
                                                   <?= $m['status'] === 'completed' ? 'readonly' : '' ?>
                                                   class="border-0">
                                            <?php if ($m['status'] === 'completed'): ?>
                                                <span class="completed-badge-square"><i class="fas fa-check-circle mr-1"></i> Completed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Bottom action buttons -->
                <div class="mt-10 flex justify-between items-center flex-wrap gap-4">
                    <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="btn-square-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="btn-square-primary">
                        <i class="fas fa-save"></i> Save Schedules
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Disable past dates in datetime-local inputs (except readonly ones)
        (function() {
            const now = new Date();
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