<?php
session_start();
require_once "../database/dbConfig.php";
include("header.php");

/* ---------- ACCESS CONTROL ---------- */
if (
    !isset($_SESSION['user_id']) ||
    !$_SESSION['is_organizer'] ||
    $_SESSION['organizer_status'] !== 'approved'
) {
    header("Location: ../login.php");
    exit;
}

/* ---------- TOURNAMENT ---------- */
$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if (!$tournament_id) {
    die("Invalid tournament");
}

/* ---------- CHECK TOURNAMENT ---------- */
$stmt = $conn->prepare("
    SELECT tournament_id, max_participants 
    FROM tournaments 
    WHERE tournament_id = ?
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
if (!$tournament) die("Tournament not found");

/* ---------- FETCH TEAMS ---------- */
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

/* ---------- GENERATE MATCHES (ONCE) ---------- */
$check = $conn->prepare("SELECT COUNT(*) c FROM matches WHERE tournament_id = ?");
$check->bind_param("i", $tournament_id);
$check->execute();
$count = (int)$check->get_result()->fetch_assoc()['c'];

/*
 * Create matches only when there are no matches yet.
 * Make the generator adaptive for smaller tournaments (not forced >=12).
 */
$teamCount = count($teams);
if ($count == 0 && $teamCount >= 2) {

    shuffle($teams);

    // Use up to 4 groups, or fewer when there are fewer teams
    $groupCount = min(4, $teamCount);
    if ($groupCount <= 0) $groupCount = 1;
    $groups = array_chunk($teams, ceil($teamCount / $groupCount));
    // Group names A,B,C,D... (use as many as needed)
    $groupNamesAll = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $groupNames = array_slice($groupNamesAll, 0, count($groups));

    $order = 1;

    /* GROUP STAGE: round-robin inside each group */
    foreach ($groups as $gi => $groupTeams) {
        $gname = $groupNames[$gi] ?? ('G' . ($gi + 1));
        $n = count($groupTeams);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $stmt = $conn->prepare("
                    INSERT INTO matches
                    (tournament_id, round, group_name, match_order, team1_id, team2_id)
                    VALUES (?, 'group', ?, ?, ?, ?)
                ");
                $matchOrder = $order++;
                $stmt->bind_param(
                    "isiii",
                    $tournament_id,
                    $gname,
                    $matchOrder,
                    $groupTeams[$i]['team_id'],
                    $groupTeams[$j]['team_id']
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    /* KNOCKOUT PLACEHOLDERS
     * Always create placeholders after group stage so bracket code can populate them.
     * We'll create placeholders for quarterfinal, semifinal, final, third_place
     * but if fewer teams require fewer quarterfinal slots it's still safe:
     * populateQuarterFinals will place top teams where possible.
     */
    $rounds = [
        'quarterfinal' => 4,
        'semifinal' => 2,
        'final' => 1,
        'third_place' => 1
    ];

    foreach ($rounds as $round => $matches) {
        for ($i = 1; $i <= $matches; $i++) {
            $stmt = $conn->prepare("
                INSERT INTO matches (tournament_id, round, match_order)
                VALUES (?, ?, ?)
            ");
            $matchOrder = $order++;
            $stmt->bind_param("isi", $tournament_id, $round, $matchOrder);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/* ---------- FETCH MATCHES ---------- */
$result = $conn->query("
    SELECT m.*, 
           t1.team_name AS team1, 
           t2.team_name AS team2
    FROM matches m
    LEFT JOIN teams t1 ON m.team1_id = t1.team_id
    LEFT JOIN teams t2 ON m.team2_id = t2.team_id
    WHERE m.tournament_id = $tournament_id
    ORDER BY 
      FIELD(m.round,'group','quarterfinal','semifinal','final','third_place'),
      m.group_name,
      m.match_order
");

$matches = [];
while ($row = $result->fetch_assoc()) {
    $matches[$row['round']][] = $row;
}

/* ---------- UI HELPER ---------- */
function matchCard($m)
{
    $time = $m['scheduled_time']
        ? date('Y-m-d\TH:i', strtotime($m['scheduled_time']))
        : '';

    return "
<div class='tx-match'>
    <div class='tx-match-row'>
        <span class='tx-team'>" . ($m['team1'] ?? 'TBD') . "</span>
        <span>vs</span>
        <span class='tx-team'>" . ($m['team2'] ?? 'TBD') . "</span>

        <div class='tx-date-wrap'>
            <input type='datetime-local'
                   class='match-date'
                   name='schedule[{$m['match_id']}]'
                   value='{$time}'>
        </div>
    </div>
</div>";
}
?>

<body class="tx-body">
    <div class="tx-container">

        <div class="tx-header">
            <h1>🏆 Tournament Schedule</h1>
        </div>

        <form method="post" action="save-schedule.php">
            <div id="txContent">

                <!-- GROUP STAGE -->
                <?php if (!empty($matches['group'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Group Stage</div>
                        <div class="tx-grid">
                            <?php
                            $groups = [];
                            foreach ($matches['group'] as $m) {
                                $groups[$m['group_name']][] = $m;
                            }
                            foreach ($groups as $name => $games):
                            ?>
                                <div class="tx-card">
                                    <h3>Group <?= htmlspecialchars($name) ?></h3>
                                    <?php foreach ($games as $m) echo matchCard($m); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- QUARTERFINALS -->
                <?php if (!empty($matches['quarterfinal'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Quarterfinals</div>
                        <div class="tx-grid">
                            <?php $i = 1;
                            foreach ($matches['quarterfinal'] as $m): ?>
                                <div class="tx-card">
                                    <h3>Match <?= $i++ ?></h3>
                                    <?= matchCard($m) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SEMIFINALS -->
                <?php if (!empty($matches['semifinal'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Semifinals</div>
                        <div class="tx-grid">
                            <?php $i = 1;
                            foreach ($matches['semifinal'] as $m): ?>
                                <div class="tx-card">
                                    <h3>Semifinal <?= $i++ ?></h3>
                                    <?= matchCard($m) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FINALS -->
                <?php if (!empty($matches['final']) || !empty($matches['third_place'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Finals</div>
                        <div class="tx-grid">
                            <?php if (!empty($matches['final'])): ?>
                                <div class="tx-card">
                                    <h3>Champion</h3>
                                    <?= matchCard($matches['final'][0]) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($matches['third_place'])): ?>
                                <div class="tx-card">
                                    <h3>3rd Place</h3>
                                    <?= matchCard($matches['third_place'][0]) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <div style="text-align:center;margin-top:30px;">
                <button style="padding:10px 20px;font-size:16px;cursor:pointer;" type="submit">
                    💾 SAVE TOURNAMENT
                </button>
            </div>

        </form>
    </div>

    <?php include("footer.php"); ?>