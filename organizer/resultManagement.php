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
        <span class='tx-team'>{$team1}</span>
        <input type='number' min='0' name='score[{$match_id}][team1]' value='{$score1}' required>
        <span>vs</span>
        <input type='number' min='0' name='score[{$match_id}][team2]' value='{$score2}' required>
        <span class='tx-team'>{$team2}</span>
    </div>
</div>";
}
?>

<body class="tx-body">
  <div class="tx-container">
    <div class="tx-header">
      <h1>🏆 Tournament Results — <?= esc($tRow['title']) ?></h1>
    </div>
    <form method="post" action="save-results.php">
      <input type="hidden" name="tournament_id" value="<?= $tournament_id ?>">

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
                <h3>Group <?= esc($name) ?></h3>
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

      <div style="text-align:center;margin-top:30px;">
        <button style="padding:10px 20px;font-size:16px;cursor:pointer;" type="submit">💾 Save Scores</button>
      </div>
    </form>
  </div>
  <?php include("footer.php"); ?>