<?php
include("header.php");
require_once "../database/dbConfig.php";

$tournament_id = (int)$_GET['tournament_id'];

/* ---------- FETCH GROUP STANDINGS ---------- */
$standings = [];
$stmt = $conn->prepare("
    SELECT gs.*, t.team_name
    FROM group_standings gs
    JOIN teams t ON t.team_id = gs.team_id
    WHERE gs.tournament_id = ?
    ORDER BY gs.group_name, gs.points DESC, (gs.score_for - gs.score_against) DESC
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $standings[$row['group_name']][] = $row;
}

/* ---------- FETCH MATCHES ---------- */
$matches = [];
$stmt = $conn->prepare("
    SELECT m.*, 
           t1.team_name AS team1,
           t2.team_name AS team2
    FROM matches m
    LEFT JOIN teams t1 ON t1.team_id = m.team1_id
    LEFT JOIN teams t2 ON t2.team_id = m.team2_id
    WHERE m.tournament_id = ?
    ORDER BY FIELD(m.round,'group','quarterfinal','semifinal','final'), m.match_order
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$matches = $stmt->get_result();
?>

<body class="txg-body">
  <div class="txg-container">

    <!-- ================= GROUP STANDINGS ================= -->
    <div class="txg-header">
      <h1>Group Standings</h1>
    </div>
    <div class="txg-grid">

      <?php foreach ($standings as $group => $teams): ?>
        <div class="txg-card">
          <div class="txg-title">Group <?= htmlspecialchars($group) ?></div>
          <table class="txg-table">
            <thead>
              <tr>
                <th>Team</th>
                <th>PTS</th>
                <th>Win</th>
                <th>Lose</th>
                <th>Net</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($teams as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['team_name']) ?></td>
                  <td><?= $t['points'] ?></td>
                  <td><?= $t['wins'] ?></td>
                  <td><?= $t['losses'] ?></td>
                  <td><?= $t['score_for'] - $t['score_against'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>

    </div>

    <!-- ================= MATCH SCORE ENTRY ================= -->
    <div class="txg-header">
      <h1>Match Score Entry</h1>
    </div>

    <div class="txg-grid">
      <?php while ($m = $matches->fetch_assoc()): ?>
        <form method="post" action="save-score.php" class="txg-card">
          <div class="txg-title">
            <?= strtoupper($m['round']) ?> — <?= $m['team1'] ?> vs <?= $m['team2'] ?>
          </div>

          <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">

          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span><?= $m['team1'] ?></span>
            <input type="number" name="team1_score" min="0" required>
            <strong>VS</strong>
            <input type="number" name="team2_score" min="0" required>
            <span><?= $m['team2'] ?></span>
          </div>

          <br>
          <button class="tx-save-btn">💾 SAVE SCORE</button>
        </form>
      <?php endwhile; ?>
    </div>

  </div>
  <?php include("footer.php"); ?>