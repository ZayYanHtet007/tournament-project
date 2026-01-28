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

$teamCount = count($teams);
if ($count == 0 && $teamCount >= 2) {

    shuffle($teams);

    $groupCount = min(4, $teamCount);
    if ($groupCount <= 0) $groupCount = 1;
    $groups = array_chunk($teams, ceil($teamCount / $groupCount));
    $groupNamesAll = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $groupNames = array_slice($groupNamesAll, 0, count($groups));

    $order = 1;

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
        <div class='tx-vs-container'>
            <span class='tx-team'>" . ($m['team1'] ?? 'TBD') . "</span>
            <span class='tx-vs-badge'>VS</span>
            <span class='tx-team'>" . ($m['team2'] ?? 'TBD') . "</span>
        </div>
        <div class='tx-date-wrap'>
            <label>Set Intel Date</label>
            <div class='date-input-container'>
                <input type='datetime-local'
                       class='match-date'
                       name='schedule[{$m['match_id']}]'
                       value='{$time}'>
            </div>
        </div>
    </div>
</div>";
}
?>

<head>
    <style>
        :root {
            --riot-blue: #0bc6e3;
            --riot-dark: #010a13;
            --riot-surface: #051923;
            --riot-border: rgba(11, 198, 227, 0.2);
            --riot-gold: #c8aa6e;
        }

        .tx-body {
            background-color: var(--riot-dark);
            color: #fff;
            font-family: 'Segoe UI', Roboto, sans-serif;
            background-image: radial-gradient(circle at 50% 50%, #051923 0%, #010a13 100%);
            min-height: 100vh;
        }

        .tx-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .tx-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .tx-header h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3.5rem;
            color: var(--riot-blue);
            text-transform: uppercase;
            letter-spacing: 5px;
            text-shadow: 0 0 20px rgba(11, 198, 227, 0.4);
        }

        .tx-section {
            margin-bottom: 60px;
        }

        .tx-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-left: 4px solid var(--riot-blue);
            padding-left: 15px;
            margin-bottom: 30px;
        }

        .tx-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .tx-card {
            background: var(--riot-surface);
            border: 1px solid var(--riot-border);
            padding: 15px;
            position: relative;
            clip-path: polygon(0 0, 100% 0, 100% 92%, 92% 100%, 0 100%);
            transition: 0.3s;
            display: flex;
            flex-direction: column;
        }

        .tx-card:hover {
            border-color: var(--riot-blue);
            box-shadow: 0 0 15px rgba(11, 198, 227, 0.1);
        }

        .tx-card h3 {
            color: var(--riot-gold);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(200, 170, 110, 0.2);
            padding-bottom: 5px;
        }

        .tx-vs-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .tx-team {
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            flex: 1;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tx-vs-badge {
            background: var(--riot-blue);
            color: #000;
            font-size: 0.6rem;
            font-weight: 900;
            padding: 2px 6px;
            border-radius: 2px;
            margin: 0 5px;
        }

        .tx-date-wrap {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .tx-date-wrap label {
            font-size: 0.65rem;
            color: var(--riot-blue);
            text-transform: uppercase;
            font-weight: bold;
        }

        /* CALENDAR STYLING */
        .date-input-container {
            position: relative;
            width: 100%;
        }

        .match-date {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid var(--riot-border);
            color: #fff;
            padding: 8px 12px;
            font-size: 0.85rem;
            outline: none;
            width: 100%;
            font-family: inherit;
            cursor: text;
        }

        /* Customizing the native calendar icon */
        .match-date::-webkit-calendar-picker-indicator {
            filter: invert(75%) sepia(80%) saturate(2500%) hue-rotate(160deg) brightness(100%) contrast(100%);
            cursor: pointer;
            margin-left: 10px;
        }

        .match-date:focus {
            border-color: var(--riot-blue);
            background: #000;
        }

        .save-btn {
            background: transparent;
            color: var(--riot-blue);
            border: 1px solid var(--riot-blue);
            padding: 15px 40px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        .save-btn:hover {
            background: var(--riot-blue);
            color: #000;
            box-shadow: 0 0 20px var(--riot-blue);
        }

        @media (max-width: 1024px) {
            .tx-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 600px) {
            .tx-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body class="tx-body">
    <div class="tx-container">

        <div class="tx-header">
            <h1>🏆 Tournament Schedule</h1>
        </div>

        <form method="post" action="save-schedule.php">
            <div id="txContent">

                <?php if (!empty($matches['group'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Group Stage Deployment</div>
                        <div class="tx-grid">
                            <?php
                            $groups = [];
                            foreach ($matches['group'] as $m) {
                                $groups[$m['group_name']][] = $m;
                            }
                            foreach ($groups as $name => $games):
                            ?>
                                <div class="tx-card">
                                    <h3>Sector Group <?= htmlspecialchars($name) ?></h3>
                                    <?php foreach ($games as $m) echo matchCard($m); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($matches['quarterfinal'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Quarterfinal Rounds</div>
                        <div class="tx-grid">
                            <?php $i = 1;
                            foreach ($matches['quarterfinal'] as $m): ?>
                                <div class="tx-card">
                                    <h3>Match Protocol <?= $i++ ?></h3>
                                    <?= matchCard($m) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($matches['semifinal'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Semifinal Elimination</div>
                        <div class="tx-grid">
                            <?php $i = 1;
                            foreach ($matches['semifinal'] as $m): ?>
                                <div class="tx-card">
                                    <h3>Strategic Semi <?= $i++ ?></h3>
                                    <?= matchCard($m) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($matches['final']) || !empty($matches['third_place'])): ?>
                    <div class="tx-section">
                        <div class="tx-title">Championship Deciders</div>
                        <div class="tx-grid">
                            <?php if (!empty($matches['final'])): ?>
                                <div class="tx-card" style="border-color: var(--riot-gold);">
                                    <h3 style="color: #fff; background: var(--riot-gold); color: #000; padding: 2px 5px;">🏆 Grand Final</h3>
                                    <?= matchCard($matches['final'][0]) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($matches['third_place'])): ?>
                                <div class="tx-card">
                                    <h3>Consolation Final</h3>
                                    <?= matchCard($matches['third_place'][0]) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <div style="text-align:center;margin-top:50px; padding-bottom: 50px;">
                <button class="save-btn" type="submit">
                    💾 Save Tournament
                </button>
            </div>

        </form>
    </div>

    <?php include("footer.php"); ?>
</body>