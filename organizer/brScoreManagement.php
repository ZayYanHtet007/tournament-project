<?php
require '../database/dbConfig.php';

$tournament_id = $_GET['tournament_id'] ?? $_POST['tournament_id'] ?? 0;
$errors = [];
$successMessage = '';

/* ================= POINT CALC ================= */
function calcPoints($rank, $kills)
{
    $rankPoints = [1 => 20, 2 => 16, 3 => 14, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4];
    return ($rankPoints[$rank] ?? 1) + $kills;
}

/* ================= SAVE MATCH ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_id'])) {
    $ranks = [];
    foreach ($_POST['rank'] as $pid => $rank) {
        if (!$rank || $rank < 1) $errors[$pid]['rank'] = "Rank required";
        elseif (in_array($rank, $ranks)) $errors[$pid]['rank'] = "Duplicate rank";
        $ranks[] = $rank;

        if ($_POST['kills'][$pid] === '' || $_POST['kills'][$pid] < 0) {
            $errors[$pid]['kills'] = "Invalid kills";
        }
    }

    if (empty($errors)) {
        foreach ($_POST['rank'] as $pid => $rank) {
            $kills = $_POST['kills'][$pid];
            $points = calcPoints($rank, $kills);
            $isWinner = ($rank == 1) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE battleroyal_participants 
                SET rank_position=?, kill_count=?, score_points=?, is_winner=? 
                WHERE participation_id=?");
            $stmt->execute([(int)$rank, (int)$kills, (int)$points, (int)$isWinner, (int)$pid]);
        }
        $pdo->prepare("UPDATE matches SET status='completed' WHERE match_id=?")
            ->execute([$_POST['match_id']]);
        $successMessage = "Match saved successfully!";
    }
}

/* ================= TOURNAMENT ================= */
$tournament = $pdo->prepare("SELECT tournament_id, title, status, max_participants FROM tournaments WHERE tournament_id=?");
$tournament->execute([$tournament_id]);
$tournament = $tournament->fetch();
if (!$tournament) die("Tournament not found");

// Determine kill limit based on max_participants
$killLimit = ($tournament['max_participants'] == 16) ? 63 : 99;

/* ================= TEAM COUNT ================= */
$totalTeams = $pdo->query("
    SELECT COUNT(*) FROM tournament_teams WHERE tournament_id=$tournament_id
")->fetchColumn();

$canStart = in_array($totalTeams, [16, 25]);

/* ================= STANDINGS ================= */
$standings = $pdo->query("
    SELECT t.team_name,
           SUM(mp.score_points) AS points,
           SUM(mp.kill_count) AS kills
    FROM tournament_teams tt
    JOIN teams t ON tt.team_id=t.team_id
    LEFT JOIN battleroyal_participants mp ON mp.tt_id=tt.id
    WHERE tt.tournament_id=$tournament_id
    GROUP BY tt.id
    ORDER BY points DESC, kills DESC
")->fetchAll();

/* ================= CURRENT MATCH ================= */
$currentMatch = $pdo->query("
    SELECT * FROM matches
    WHERE tournament_id=$tournament_id
      AND scheduled_time IS NOT NULL
      AND status != 'completed'
    ORDER BY FIELD(round,'FIRST','SECOND','THIRD')
    LIMIT 1
")->fetch();

/* ================= PARTICIPANTS ================= */
$participants = [];
if ($currentMatch) {
    $participants = $pdo->query("
        SELECT mp.participation_id, t.team_name
        FROM battleroyal_participants mp
        JOIN tournament_teams tt ON mp.tt_id=tt.id
        JOIN teams t ON tt.team_id=t.team_id
        WHERE mp.match_id={$currentMatch['match_id']}
    ")->fetchAll();
}

/* ================= FINISHED MATCHES ================= */
$finishedMatches = $pdo->query("
    SELECT m.match_id, m.round, m.scheduled_time, mp.rank_position, mp.kill_count, t.team_name
    FROM matches m
    JOIN battleroyal_participants mp ON mp.match_id=m.match_id
    JOIN tournament_teams tt ON mp.tt_id=tt.id
    JOIN teams t ON tt.team_id=t.team_id
    WHERE m.tournament_id=$tournament_id AND m.status='completed'
    ORDER BY FIELD(m.round,'FIRST','SECOND','THIRD'), mp.rank_position ASC
")->fetchAll();

/* ================= TOURNAMENT FINISHED ================= */
$isFinished = $pdo->query("
    SELECT COUNT(*) FROM matches
    WHERE tournament_id=$tournament_id AND status='completed'
")->fetchColumn() == 3;

$top3 = [];
if ($isFinished) {
    $top3 = $pdo->query("
        SELECT tt.team_id, t.team_name,
               SUM(mp.score_points) AS total_points,
               SUM(mp.kill_count) AS total_kills
        FROM tournament_teams tt
        JOIN teams t ON tt.team_id = t.team_id
        JOIN battleroyal_participants mp ON mp.tt_id = tt.id
        WHERE tt.tournament_id = $tournament_id
        GROUP BY tt.team_id
        ORDER BY total_points DESC, total_kills DESC
        LIMIT 3
    ")->fetchAll();

    if (!empty($top3)) {
        $check = $pdo->prepare("SELECT 1 FROM tournament_results WHERE tournament_id = ?");
        $check->execute([$tournament_id]);
        if (!$check->fetch()) {
            $winner_team_id = $top3[0]['team_id'];
            $insert = $pdo->prepare("INSERT INTO tournament_results (tournament_id, winner_team_id) VALUES (?, ?)");
            $insert->execute([$tournament_id, $winner_team_id]);
        }
    }

    $pdo->prepare("UPDATE tournaments SET status='completed' WHERE tournament_id=?")
        ->execute([$tournament_id]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Battle Royal Score</title>
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
        .br-message.info {
            border-color: #2d7ff9;
        }

        /* Top 3 cards */
        .br-top3-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .br-top3-card {
            background-color: #161b26;
            border: 1px solid #2d7ff9;
            padding: 1.5rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 600;
            min-width: 200px;
            border-radius: 0;
        }
        .br-top3-card.gold {
            border-color: #ffd966;
            color: #ffd966;
            transform: scale(1.1);
        }
        .br-top3-card.silver {
            border-color: #c0c0c0;
            color: #c0c0c0;
        }
        .br-top3-card.bronze {
            border-color: #cd7f32;
            color: #cd7f32;
        }

        /* Tables */
        .br-table-wrapper {
            margin-bottom: 2rem;
        }
        .br-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #161b26;
            border: 1px solid #2f3a4a;
        }
        .br-table th {
            background-color: #1f2632;
            color: #b8d0f0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #2f3a4a;
        }
        .br-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #2f3a4a;
            color: #d6e2f0;
        }
        .br-table tr:last-child td {
            border-bottom: none;
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
        .pending-match-card {
            border: 2px solid #2d7ff9;
            box-shadow: 0 0 15px #2d7ff9;
            grid-column: 1 / -1; /* full width */
        }
        .br-match-round {
            font-size: 1.3rem;
            font-weight: 600;
            color: #b8d0f0;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        /* Form inputs */
        input[type="number"] {
            background-color: #0f141e;
            border: 1px solid #2f3a4a;
            color: #e0e6f0;
            padding: 0.5rem;
            font-size: 0.95rem;
            border-radius: 0;
            width: 100%;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: #2d7ff9;
            box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
        }
        .error-border {
            border-color: #ef4444 !important;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
        }

        .team-name-cell {
            font-weight: 500;
            color: #d6e2f0;
        }

        .br-btn-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        /* Icons */
        i {
            color: inherit;
        }
    </style>
</head>

<body class="br-body">
    <div class="br-container">

        <!-- TITLE -->
        <div class="br-title">
            <h1><i class="fas fa-fire"></i> Battle Royale</h1>
            <p><?= htmlspecialchars($tournament['title']) ?></p>
        </div>

        <!-- Back to Tournament Management -->
        <div style="margin-bottom: 20px;">
            <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="br-btn"><i class="fas fa-arrow-left"></i> Back </a>
        </div>

        <!-- INLINE MESSAGES -->
        <?php if ($successMessage): ?>
            <div class="br-message success">
                <i class="fas fa-check-circle"></i> <?= $successMessage ?>
            </div>
        <?php endif; ?>

        <?php if (!$canStart): ?>
            <div class="br-message info">
                <i class="fas fa-info-circle"></i> Tournament needs <?= $totalTeams < 16 ? (16 - $totalTeams) : (25 - $totalTeams) ?> more teams to start
            </div>
        <?php endif; ?>

        <?php if (!$currentMatch && !$isFinished && $canStart): ?>
            <div class="br-message info"><i class="fas fa-clock"></i> Next match schedule not set or previous match not finished.</div>
        <?php elseif ($isFinished): ?>
            <div class="br-message success"><i class="fas fa-trophy"></i> Tournament Completed!</div>
        <?php endif; ?>

        <!-- TOP 3 TEAMS -->
        <?php if ($top3): ?>
            <div class="br-top3-wrapper">
                <div class="br-top3-card silver"><i class="fas fa-medal"></i> <?= htmlspecialchars($top3[1]['team_name'] ?? '') ?></div>
                <div class="br-top3-card gold"><i class="fas fa-crown"></i> <?= htmlspecialchars($top3[0]['team_name'] ?? '') ?></div>
                <div class="br-top3-card bronze"><i class="fas fa-medal"></i> <?= htmlspecialchars($top3[2]['team_name'] ?? '') ?></div>
            </div>
        <?php endif; ?>

        <!-- STANDINGS -->
        <div class="br-table-wrapper">
            <table class="br-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Team</th>
                        <th>Points</th>
                        <th>Kills</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $r = 1;
                    foreach ($standings as $s): ?>
                        <tr>
                            <td><?= $r++ ?></td>
                            <td><?= htmlspecialchars($s['team_name']) ?></td>
                            <td><?= $s['points'] ?? 0 ?></td>
                            <td><?= $s['kills'] ?? 0 ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- PENDING MATCH (full width) -->
        <div class="br-match-grid">
            <?php if ($currentMatch): ?>
                <div class="br-match-card pending-match-card">
                    <div class="br-match-round"><i class="fas fa-play-circle"></i> <?= ucfirst(strtolower($currentMatch['round'])) ?> Match</div>
                    <div style="text-align:center; margin-bottom:10px; color:#9aaec9;">
                        <i class="fas fa-calendar-alt"></i> Scheduled: <?= date('Y-m-d h:i A', strtotime($currentMatch['scheduled_time'])) ?>
                    </div>
                    <form method="POST" id="scoreForm" onsubmit="return validateForm();">
                        <input type="hidden" name="match_id" value="<?= $currentMatch['match_id'] ?>">
                        <input type="hidden" name="tournament_id" value="<?= $tournament_id ?>">
                        <!-- Pass kill limit to JS -->
                        <input type="hidden" id="killLimit" value="<?= $killLimit ?>">

                        <div class="br-table-wrapper" style="padding:10px;">
                            <table class="br-table">
                                <thead>
                                    <tr>
                                        <th>Team</th>
                                        <th>Rank</th>
                                        <th>Kills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $p): ?>
                                        <tr>
                                            <td class="team-name-cell"><?= htmlspecialchars($p['team_name']) ?></td>
                                            <td>
                                                <input type="number" 
                                                       name="rank[<?= $p['participation_id'] ?>]" 
                                                       id="rank_<?= $p['participation_id'] ?>"
                                                       min="1" 
                                                       placeholder="Rank"
                                                       class="rank-input"
                                                       oninput="validateField(this)">
                                                <span class="error-message" id="rank_error_<?= $p['participation_id'] ?>"></span>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       name="kills[<?= $p['participation_id'] ?>]" 
                                                       id="kills_<?= $p['participation_id'] ?>"
                                                       min="0" 
                                                       placeholder="Kills"
                                                       class="kills-input"
                                                       oninput="validateField(this)">
                                                <span class="error-message" id="kills_error_<?= $p['participation_id'] ?>"></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="br-btn-wrapper">
                            <button type="submit" class="br-btn"><i class="fas fa-save"></i> Save Match</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="br-match-card">
                    <div class="br-btn-wrapper">
                        <button type="button" class="br-btn" disabled><i class="fas fa-save"></i> Save Match</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- COMPLETED MATCHES -->
        <?php if ($finishedMatches): ?>
            <div class="br-match-grid">
                <?php
                $rounds = [];
                foreach ($finishedMatches as $m) {
                    $rounds[$m['round']][] = $m;
                }

                foreach ($rounds as $round => $matches):
                ?>
                    <div class="br-match-card completed">
                        <div class="br-match-round"><i class="fas fa-check-circle"></i> <?= ucfirst(strtolower($round)) ?> Match</div>
                        <div style="text-align:center; font-size:0.9rem; margin-bottom:10px; color:#9aaec9;">
                            <i class="fas fa-calendar-alt"></i> <?= date('Y-m-d h:i A', strtotime($matches[0]['scheduled_time'])) ?>
                        </div>
                        <div class="br-table-wrapper" style="padding:10px;">
                            <table class="br-table">
                                <thead>
                                    <tr>
                                        <th>Team</th>
                                        <th>Rank</th>
                                        <th>Kills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matches as $m): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($m['team_name']) ?></td>
                                            <td><?= $m['rank_position'] ?></td>
                                            <td><?= $m['kill_count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <script>
        const killLimit = parseInt(document.getElementById('killLimit').value);

        function validateField(input) {
            const fieldId = input.id;
            const errorSpanId = fieldId.replace('rank_', 'rank_error_').replace('kills_', 'kills_error_');
            const errorSpan = document.getElementById(errorSpanId);
            const value = input.value.trim();

            if (input.classList.contains('rank-input')) {
                if (value === '') {
                    input.classList.add('error-border');
                    errorSpan.textContent = 'Rank is required';
                } else if (parseInt(value) < 1) {
                    input.classList.add('error-border');
                    errorSpan.textContent = 'Rank must be at least 1';
                } else {
                    input.classList.remove('error-border');
                    errorSpan.textContent = '';
                }
            } else if (input.classList.contains('kills-input')) {
                if (value === '') {
                    input.classList.add('error-border');
                    errorSpan.textContent = 'Kills are required';
                } else if (parseInt(value) < 0) {
                    input.classList.add('error-border');
                    errorSpan.textContent = 'Kills cannot be negative';
                } else if (parseInt(value) > killLimit) {
                    input.classList.add('error-border');
                    errorSpan.textContent = 'Kills exceed limit (' + killLimit + ')';
                } else {
                    input.classList.remove('error-border');
                    errorSpan.textContent = '';
                }
            }
        }

        function validateForm() {
            let isValid = true;
            const rankInputs = document.querySelectorAll('.rank-input');
            const killsInputs = document.querySelectorAll('.kills-input');
            const rankValues = [];

            // Validate each input
            rankInputs.forEach(input => {
                validateField(input);
                if (input.classList.contains('error-border')) isValid = false;
            });
            killsInputs.forEach(input => {
                validateField(input);
                if (input.classList.contains('error-border')) isValid = false;
            });

            // Check duplicate ranks
            rankInputs.forEach(input => {
                const val = input.value.trim();
                if (val !== '') {
                    if (rankValues.includes(val)) {
                        input.classList.add('error-border');
                        const errorSpan = document.getElementById(input.id.replace('rank_', 'rank_error_'));
                        errorSpan.textContent = 'Duplicate rank';
                        isValid = false;
                    }
                    rankValues.push(val);
                }
            });

            return isValid;
        }

        window.onload = function() {
            document.querySelectorAll('.rank-input, .kills-input').forEach(input => {
                validateField(input);
            });
        };
    </script>
</body>

</html>