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

// Check if tournament is already completed
$tournamentCompleted = ($tournament['status'] === 'completed');

/* ================= TEAM COUNT ================= */
$totalTeams = $pdo->query("
    SELECT COUNT(*) FROM tournament_teams WHERE tournament_id=$tournament_id
")->fetchColumn();

$canSchedule = in_array($totalTeams, [16, 25]);

/* ================= AUTO CREATE MATCHES ================= */
if ($canSchedule && !$tournamentCompleted) {

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

if ($tournamentCompleted) {
    $allCompleted = true;
}

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
        $count = count($savedRounds);
        $roundsList = implode(', ', $savedRounds);
        $successMessage = "✅ $count schedule(s) saved: $roundsList.";
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

$minDateTime = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Battle Royale Schedule</title>
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
        .br-container {
            max-width: 1280px;
            margin: 0 auto;
            background-color: #10131c;
            border: 1px solid #2f3a4a;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
            padding: 2rem;
        }

        /* Title area */
        .br-title {
            margin-bottom: 2rem;
        }
        .br-title h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #b8d0f0;
            border-bottom: 2px solid #2d7ff9; /* brighter blue */
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .br-title p {
            color: #9aaec9;
            margin-top: 0.5rem;
        }

        /* Back button */
        .br-btn {
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
        .br-btn:hover {
            background-color: #264763;
            border-color: #5a9eff;
        }
        .br-btn:disabled {
            background-color: #1a1f2a;
            border-color: #353e4e;
            color: #6b7a8f;
            cursor: not-allowed;
        }

        /* Inline messages */
        .br-message {
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
        .br-message.success {
            border-color: #2d7ff9;
        }
        .br-message.error {
            border-color: #2d7ff9;
        }
        .br-message.info {
            border-color: #2d7ff9;
        }

        /* Match grid */
        .br-match-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .br-match-card {
            background-color: #161b26;
            border: 1px solid #2f3a4a;
            padding: 1.5rem;
            border-radius: 0;
        }
        .br-match-card.completed {
            border-color: #2d7ff9;
            background-color: #1a202b;
        }
        .br-match-round {
            font-size: 1.3rem;
            font-weight: 600;
            color: #b8d0f0;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .br-match-time {
            text-align: center;
            color: #9aaec9;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        /* Form inputs */
        .br-input-group {
            margin-bottom: 1rem;
        }
        .br-input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: #9aaec9;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input[type="datetime-local"] {
            width: 100%;
            background-color: #0f141e;
            border: 1px solid #2f3a4a;
            color: #e0e6f0;
            padding: 0.75rem;
            font-size: 0.95rem;
            border-radius: 0;
            transition: 0.1s;
        }
        input[type="datetime-local"]:focus {
            outline: none;
            border-color: #2d7ff9;
            box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
        }
        input[type="datetime-local"]:disabled {
            background-color: #1c222d;
            color: #7c8a9c;
            border-color: #353e4e;
        }

        /* Button wrapper */
        .br-btn-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        /* Icons */
        i {
            color: inherit;
        }
    </style>
</head>

<body class="br-body">
    <div class="br-container">

        <div class="br-title">
            <h1><i class="fas fa-calendar-alt"></i> Battle Royale Schedule</h1>
            <p><?= htmlspecialchars($tournament['title']) ?></p>
        </div>

        <!-- Back to Tournament Management -->
        <div style="margin-bottom: 20px;">
            <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="br-btn"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($errorMessage): ?>
            <div class="br-message error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>

        <?php elseif ($successMessage): ?>
            <div class="br-message success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
            </div>

        <?php elseif ($allCompleted): ?>
            <div class="br-message success">
                <i class="fas fa-flag-checkered"></i> Tournament is completed. You can review results or enable edit mode.
            </div>

        <?php else: ?>
            <div class="br-message info">
                <i class="fas fa-clock"></i> Set schedules to unlock score management.
            </div>
        <?php endif; ?>

        <?php if (!$canSchedule && !$allCompleted): ?>
            <div class="br-message error">
                <i class="fas fa-exclamation-triangle"></i> Tournament needs more teams to schedule matches.
            </div>
            </div> <!-- close container -->
            </body>
            </html>
            <?php exit;
        endif; ?>

        <?php if ($tournamentCompleted): ?>
            <div class="br-message info"><i class="fas fa-info-circle"></i> Tournament is already marked as completed.</div>
        <?php endif; ?>

        <form method="POST">

            <div class="br-match-grid">

                <?php foreach ($matches as $m): ?>
                    <div class="br-match-card <?= $m['status'] === 'completed' ? 'completed' : '' ?>">

                        <?php if ($m['status'] === 'completed'): ?>
                            <div class="br-match-round">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($m['round']) ?>
                            </div>
                            <div class="br-match-time">
                                <i class="fas fa-calendar-alt"></i> <?= date('d M Y, h:i A', strtotime($m['scheduled_time'])) ?>
                            </div>
                        <?php else: ?>
                            <div class="br-match-round">
                                <i class="fas fa-play-circle"></i> Match <?= htmlspecialchars($m['round']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="br-input-group">
                            <label><i class="fas fa-clock"></i> Schedule Date & Time</label>
                            <input
                                type="datetime-local"
                                name="schedule[<?= $m['match_id'] ?>]"
                                value="<?= $m['scheduled_time']
                                            ? date('Y-m-d\TH:i', strtotime($m['scheduled_time']))
                                            : '' ?>"
                                min="<?= $minDateTime ?>"
                                <?= $m['status'] === 'completed' || $allCompleted ? 'readonly disabled' : '' ?>>
                        </div>

                        <?php if ($m['status'] === 'completed'): ?>
                            <div class="br-message info" style="margin-top: 1rem;">
                                <i class="fas fa-check"></i> Match completed
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>

            </div>

            <div class="br-btn-wrapper">
                <button class="br-btn" type="submit" <?= $allCompleted ? 'disabled' : '' ?>>
                    <i class="fas fa-save"></i> Save Schedule
                </button>
            </div>

        </form>

    </div>
</body>

</html>