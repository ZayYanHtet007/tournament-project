<?php
session_start();
require_once "../database/dbConfig.php";
include("header.php");

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['user_id']) || !$_SESSION['is_organizer'] || $_SESSION['organizer_status'] !== 'approved') {
  header("Location: ../login.php");
  exit;
}

$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if (!$tournament_id) die("Invalid tournament");

/* Verify organizer owns the tournament */
$stmt = $conn->prepare("SELECT organizer_id, title FROM tournaments WHERE tournament_id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tRow = $stmt->get_result()->fetch_assoc();
if (!$tRow) {
  die("Tournament not found");
}
if ($tRow['organizer_id'] != $_SESSION['user_id']) {
  header("Location: ../login.php");
  exit;
}

/* ---------- FETCH MATCHES + LATEST SCORES PER MATCH ---------- */
/* We join to a derived table that returns the latest set_number per match to avoid duplicate rows */
$sql = "
SELECT m.*, t1.team_name AS team1, t2.team_name AS team2,
       COALESCE(ms.team1_score, 0) AS score1, COALESCE(ms.team2_score, 0) AS score2
FROM matches m
LEFT JOIN teams t1 ON m.team1_id = t1.team_id
LEFT JOIN teams t2 ON m.team2_id = t2.team_id
LEFT JOIN (
    SELECT ms1.match_id, ms1.team1_score, ms1.team2_score
    FROM match_scores ms1
    JOIN (
        SELECT match_id, MAX(set_number) AS max_set
        FROM match_scores
        GROUP BY match_id
    ) ms2 ON ms1.match_id = ms2.match_id AND ms1.set_number = ms2.max_set
) ms ON m.match_id = ms.match_id
WHERE m.tournament_id = ?
ORDER BY FIELD(m.round,'group','quarterfinal','semifinal','final','third_place'), m.group_name, m.match_order
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();

$matches = [];
while ($row = $result->fetch_assoc()) {
  $matches[$row['round']][] = $row;
}

/* ---------- HELPER ---------- */
function esc($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function matchCard($m)
{
  $team1 = esc($m['team1'] ?? 'TBD');
  $team2 = esc($m['team2'] ?? 'TBD');
  $score1 = (int)($m['score1'] ?? 0);
  $score2 = (int)($m['score2'] ?? 0);
  $match_id = (int)$m['match_id'];
  return "
<div class='tx-match'>
    <div class='tx-match-row'>
        <div class='tx-score-entry'>
            <div class='team-block'>
                <span class='tx-team-name'>{$team1}</span>
                <input type='number' min='0' class='tx-score-input' name='score[{$match_id}][team1]' value='{$score1}' required>
            </div>
            <div class='tx-vs-divider'>VS</div>
            <div class='team-block'>
                <input type='number' min='0' class='tx-score-input' name='score[{$match_id}][team2]' value='{$score2}' required>
                <span class='tx-team-name'>{$team2}</span>
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
            --riot-red: #ff4655;
        }

        .tx-body {
            background-color: var(--riot-dark);
            color: #fff;
            font-family: 'Segoe UI', Roboto, sans-serif;
            background-image: radial-gradient(circle at 50% 50%, #051923 0%, #010a13 100%);
            min-height: 100vh;
            margin: 0;
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
            font-size: 3rem;
            color: var(--riot-blue);
            text-transform: uppercase;
            letter-spacing: 4px;
            text-shadow: 0 0 20px rgba(11, 198, 227, 0.3);
        }

        .tx-section {
            margin-bottom: 60px;
        }

        .tx-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-left: 4px solid var(--riot-red);
            padding-left: 15px;
            margin-bottom: 30px;
        }

        .tx-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .tx-card {
            background: var(--riot-surface);
            border: 1px solid var(--riot-border);
            padding: 20px;
            position: relative;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 95% 100%, 0 100%);
            transition: 0.3s;
        }

        .tx-card:hover {
            border-color: var(--riot-blue);
        }

        .tx-card h3 {
            color: var(--riot-gold);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(200, 170, 110, 0.2);
            padding-bottom: 8px;
        }

        /* SCORE ENTRY STYLING */
        .tx-score-entry {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.4);
            padding: 15px 10px;
            border-radius: 4px;
        }

        .team-block {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .team-block:last-child {
            justify-content: flex-end;
        }

        .tx-team-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: #eee;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tx-score-input {
            width: 50px;
            background: #000;
            border: 1px solid var(--riot-blue);
            color: var(--riot-blue);
            text-align: center;
            padding: 8px 5px;
            font-weight: 900;
            font-size: 1.1rem;
            border-radius: 2px;
        }

        .tx-score-input:focus {
            outline: none;
            box-shadow: 0 0 10px var(--riot-blue);
        }

        .tx-vs-divider {
            color: #555;
            font-weight: 900;
            font-size: 0.7rem;
            padding: 0 5px;
        }

        .save-btn {
            background: var(--riot-red);
            color: #fff;
            border: none;
            padding: 15px 50px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: 0.3s;
            clip-path: polygon(10% 0, 100% 0, 90% 100%, 0 100%);
        }

        .save-btn:hover {
            background: #ff5e6a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 70, 85, 0.4);
        }

        @media (max-width: 768px) {
            .tx-grid { grid-template-columns: 1fr; }
            .tx-team-name { max-width: 80px; }
        }
    </style>
</head>

<body class="tx-body">
  <div class="tx-container">
    <div class="tx-header">
      <h1>🏆 Tournament Results</h1>
      <p style="color: var(--riot-gold); letter-spacing: 2px;"><?= esc($tRow['title']) ?></p>
    </div>
    
    <form method="post" action="save-results.php">
      <input type="hidden" name="tournament_id" value="<?= $tournament_id ?>">

      <?php if (!empty($matches['group'])): ?>
        <div class="tx-section">
          <div class="tx-title">Group Stage Standings</div>
          <div class="tx-grid">
            <?php
            $groups = [];
            foreach ($matches['group'] as $m) {
              $groups[$m['group_name']][] = $m;
            }
            foreach ($groups as $name => $games):
            ?>
              <div class="tx-card">
                <h3>Group <?= esc($name) ?></h3>
                <?php foreach ($games as $m) echo matchCard($m); ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

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

      <?php if (!empty($matches['final']) || !empty($matches['third_place'])): ?>
        <div class="tx-section">
          <div class="tx-title">Championship Finals</div>
          <div class="tx-grid">
            <?php if (!empty($matches['final'])): ?>
              <div class="tx-card" style="border-color: var(--riot-gold);">
                <h3 style="color: var(--riot-gold);">Champion Title Match</h3>
                <?= matchCard($matches['final'][0]) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($matches['third_place'])): ?>
              <div class="tx-card">
                <h3>3rd Place Decider</h3>
                <?= matchCard($matches['third_place'][0]) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div style="text-align:center; margin-top:50px; padding-bottom: 50px;">
        <button class="save-btn" type="submit">💾 Commit Scores to Database</button>
      </div>
    </form>
  </div>
  <?php include("footer.php"); ?>
</body>