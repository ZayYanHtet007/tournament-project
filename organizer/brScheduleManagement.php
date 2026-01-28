<?php
require '../database/dbConfig.php';

$tournament_id = $_GET['tournament_id'] ?? $_POST['tournament_id'] ?? 0;
$errorMessage = '';
$successMessage = '';

/* ================= TOURNAMENT ================= */
$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE tournament_id=?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();
if (!$tournament) {
    die("Tournament not found");
}

/* ================= TEAM COUNT ================= */
$totalTeams = $pdo->query("
    SELECT COUNT(*) FROM tournament_teams WHERE tournament_id=$tournament_id
")->fetchColumn();

$canSchedule = in_array($totalTeams, [16, 25]);

/* ================= AUTO CREATE MATCHES ================= */
if ($canSchedule) {

    $existingRounds = $pdo->query("
        SELECT round FROM matches WHERE tournament_id=$tournament_id
    ")->fetchAll(PDO::FETCH_COLUMN);

    $rounds = ['FIRST', 'SECOND', 'THIRD'];

    foreach ($rounds as $round) {
        if (!in_array($round, $existingRounds)) {

            $pdo->prepare("
                INSERT INTO matches (tournament_id, round, status)
                VALUES (?, ?, 'pending')
            ")->execute([$tournament_id, $round]);

            $match_id = $pdo->lastInsertId();

            $teams = $pdo->query("
                SELECT id FROM tournament_teams WHERE tournament_id=$tournament_id
            ");

            foreach ($teams as $t) {
                $pdo->prepare("
                    INSERT INTO battleroyal_participants (match_id, tt_id)
                    VALUES (?, ?)
                ")->execute([$match_id, $t['id']]);
            }
        }
    }
}

/* ================= FETCH MATCHES ================= */
$stmt = $pdo->prepare("
    SELECT * FROM matches
    WHERE tournament_id=?
    ORDER BY FIELD(round,'FIRST','SECOND','THIRD')
");
$stmt->execute([$tournament_id]);
$matches = $stmt->fetchAll();

/* ================= COMPLETION CHECK ================= */
$allCompleted = count($matches) === 3 &&
    count(array_filter($matches, fn($m) => $m['status'] === 'completed')) === 3;

/* ================= SAVE SCHEDULE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$allCompleted) {

    $previousTime = null;
    $savedRounds = [];

    foreach ($matches as $m) {

        if ($m['status'] === 'completed') {
            continue;
        }

        $match_id = $m['match_id'];
        $round = $m['round'];
        $input = $_POST['schedule'][$match_id] ?? '';

        if (!$input) {
            continue;
        }

        $scheduleTime = strtotime($input);

        if ($scheduleTime <= time()) {
            $errorMessage = "❌ $round match cannot be scheduled in the past.";
            break;
        }

        if ($previousTime && $scheduleTime <= $previousTime) {
            $errorMessage = "❌ $round match must be after previous match.";
            break;
        }

        $pdo->prepare("
            UPDATE matches
            SET scheduled_time=?
            WHERE match_id=?
        ")->execute([$input, $match_id]);

        $previousTime = $scheduleTime;
        $savedRounds[] = $round;
    }

    if (!$errorMessage && $savedRounds) {
        $successMessage = "✅ " . implode(', ', $savedRounds) . " schedule saved successfully.";
    }
}

/* ================= REFETCH MATCHES ================= */
$stmt = $pdo->prepare("
    SELECT * FROM matches
    WHERE tournament_id=?
    ORDER BY FIELD(round,'FIRST','SECOND','THIRD')
");
$stmt->execute([$tournament_id]);
$matches = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Battle Royale Schedule</title>
    <link rel="stylesheet" href="../css/organizer/brscore.css">
</head>

<body class="br-body">
    <div class="br-container">

        <div class="br-title">
            <h1>📅 Battle Royale Schedule</h1>
            <p><?= htmlspecialchars($tournament['title']) ?></p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="br-message error"><?= htmlspecialchars($errorMessage) ?></div>

        <?php elseif ($successMessage): ?>
            <div class="br-message success"><?= htmlspecialchars($successMessage) ?></div>

        <?php elseif ($allCompleted): ?>
            <div class="br-message success">
                🏁 Tournament is completed. You can review results or enable edit mode.
            </div>

        <?php else: ?>
            <div class="br-message info">
                Set schedules to unlock score management.
            </div>
        <?php endif; ?>

        <?php if (!$canSchedule): ?>
            <div class="br-message error">
                Tournament needs more teams to schedule matches.
            </div>
    </div>
</body>

</html>
<?php exit;
        endif; ?>

<form method="POST">

    <div class="br-match-grid">

        <?php foreach ($matches as $m): ?>
            <div class="br-match-card <?= $m['status'] === 'completed' ? 'completed' : '' ?>">

                <?php if ($m['status'] === 'completed'): ?>
                    <div class="br-match-round">
                        <?= htmlspecialchars($m['round']) ?>
                    </div>
                    <div class="br-match-time">
                        <?= date('d M Y, h:i A', strtotime($m['scheduled_time'])) ?>
                    </div>
                <?php else: ?>
                    <div class="br-match-round">
                        Match <?= htmlspecialchars($m['round']) ?>
                    </div>
                <?php endif; ?>

                <div class="br-input-group">
                    <label>Schedule Date & Time</label>
                    <input
                        type="datetime-local"
                        name="schedule[<?= $m['match_id'] ?>]"
                        value="<?= $m['scheduled_time']
                                    ? date('Y-m-d\TH:i', strtotime($m['scheduled_time']))
                                    : '' ?>"
                        <?= $m['status'] === 'completed' || $allCompleted ? 'readonly disabled' : '' ?>>
                </div>

                <?php if ($m['status'] === 'completed'): ?>
                    <div class="br-message info">
                        Match completed
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    </div>

    <div class="br-btn-wrapper">
        <button class="br-btn" type="submit" disabled="<?= $allCompleted ? 'disabled' : '' ?>">
            Save Schedule
        </button>
    </div>

</form>

</div>
</body>

</html>