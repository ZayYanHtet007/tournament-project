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
if (!$tournament_id) die("Invalid tournament");

/* ---------- FETCH MATCHES ---------- */
$matches = $conn->query("
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

/* ---------- FETCH SCORES ---------- */
$scores = [];
$res = $conn->query("
    SELECT * FROM match_scores
    WHERE match_id IN (
        SELECT match_id FROM matches WHERE tournament_id = $tournament_id
    )
");
while ($row = $res->fetch_assoc()) {
  $scores[$row['match_id']][$row['set_number']] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
  <title>Score Management</title>
  <style>
    body {
      font-family: Arial;
      background: #f4f6f8
    }

    .container {
      max-width: 1100px;
      margin: 20px auto
    }

    .match {
      background: #fff;
      padding: 15px;
      margin-bottom: 12px;
      border-radius: 8px
    }

    .round {
      font-weight: bold;
      margin: 20px 0 10px
    }

    .row {
      display: flex;
      gap: 10px;
      align-items: center
    }

    input {
      width: 60px;
      padding: 6px
    }

    .btn {
      padding: 8px 14px;
      border: none;
      border-radius: 6px;
      cursor: pointer
    }

    .btn-save {
      background: #16a34a;
      color: #fff
    }

    .completed {
      opacity: .6
    }
  </style>
</head>

<body>
  <div class="container">
    <h2>Score Management</h2>

    <?php
    $currentRound = '';
    while ($m = $matches->fetch_assoc()):
      if ($currentRound !== $m['round']):
        $currentRound = $m['round'];
        echo "<div class='round'>" . strtoupper($currentRound) . "</div>";
      endif;

      $isCompleted = $m['status'] === 'completed';
    ?>
      <div class="match <?= $isCompleted ? 'completed' : '' ?>">

        <form method="post" action="save-score.php">
          <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">

          <strong>
            <?= $m['group_name'] ? "Group {$m['group_name']} — " : "" ?>
            <?= $m['team1'] ?? 'TBD' ?> vs <?= $m['team2'] ?? 'TBD' ?>
          </strong>

          <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="row">
              <span>Set <?= $i ?></span>
              <input type="number" name="sets[<?= $i ?>][team1]"
                value="<?= $scores[$m['match_id']][$i]['team1_score'] ?? '' ?>"
                min="0" <?= $isCompleted ? 'readonly' : '' ?>>
              <span>:</span>
              <input type="number" name="sets[<?= $i ?>][team2]"
                value="<?= $scores[$m['match_id']][$i]['team2_score'] ?? '' ?>"
                min="0" <?= $isCompleted ? 'readonly' : '' ?>>
            </div>
          <?php endfor; ?>

          <?php if (!$isCompleted): ?>
            <button class="btn btn-save">💾 Save Score</button>
          <?php else: ?>
            <p><strong>Winner:</strong>
              <?= $m['winner_team_id'] == $m['team1_id'] ? $m['team1'] : $m['team2'] ?>
            </p>
          <?php endif; ?>

        </form>
      </div>
    <?php endwhile; ?>

  </div>
</body>

</html>