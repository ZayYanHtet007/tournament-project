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
    <link rel="stylesheet" href="../css/organizer/brscore.css">
    <style>
        /* Make pending match card full‑width and distinct */
        .pending-match-card {
            flex: 0 0 100% !important;
            max-width: 100% !important;
            background-color: #e0f2fe !important;
            border: 3px solid #00f2ff !important;
        }
        .pending-match-card input {
            font-size: 1.2rem;
            width: 150px;
        }
        .pending-match-card .team-name-cell {
            font-size: 1.2rem;
            min-width: 250px;
        }
        .error-border {
            border: 2px solid #ef4444 !important;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: 4px;
            display: block;
        }
    </style>
</head>

<body class="br-body">
    <div class="br-container">

        <!-- TITLE -->
        <div class="br-title">
            <h1>🔥 Battle Royale</h1>
            <p><?= htmlspecialchars($tournament['title']) ?></p>
        </div>

        <!-- Back to Tournament Management -->
        <div style="margin-bottom: 20px;">
            <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="br-btn" style="background: #4b5563;">← Back to Tournament</a>
        </div>

        <!-- INLINE MESSAGES -->
        <?php if ($successMessage): ?>
            <div class="br-message success"><?= $successMessage ?></div>
        <?php endif; ?>

        <?php if (!$canStart): ?>
            <div class="br-message info">
                Tournament needs <?= $totalTeams < 16 ? (16 - $totalTeams) : (25 - $totalTeams) ?> more teams to start
            </div>
        <?php endif; ?>

        <?php if (!$currentMatch && !$isFinished && $canStart): ?>
            <div class="br-message info">Next match schedule not set or previous match not finished.</div>
        <?php elseif ($isFinished): ?>
            <div class="br-message success">Tournament Completed!</div>
        <?php endif; ?>

        <!-- TOP 3 TEAMS -->
        <?php if ($top3): ?>
            <div class="br-top3-wrapper">
                <div class="br-top3-card silver">🥈 <?= htmlspecialchars($top3[1]['team_name'] ?? '') ?></div>
                <div class="br-top3-card gold">🥇 <?= htmlspecialchars($top3[0]['team_name'] ?? '') ?></div>
                <div class="br-top3-card bronze">🥉 <?= htmlspecialchars($top3[2]['team_name'] ?? '') ?></div>
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
                    <div class="br-match-round"><?= ucfirst(strtolower($currentMatch['round'])) ?> Match</div>
                    <div style="text-align:center; margin-bottom:10px; color:#10b981;">
                        Scheduled: <?= date('Y-m-d h:i A', strtotime($currentMatch['scheduled_time'])) ?>
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
                            <button type="submit" class="br-btn">Save Match</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="br-match-card">
                    <div class="br-btn-wrapper">
                        <button type="button" class="br-btn" disabled>Save Match</button>
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
                        <div class="br-match-round"><?= ucfirst(strtolower($round)) ?> Match</div>
                        <div style="text-align:center; font-size:0.9rem; margin-bottom:10px;">
                            <?= date('Y-m-d h:i A', strtotime($matches[0]['scheduled_time'])) ?>
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