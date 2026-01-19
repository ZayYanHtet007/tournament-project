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

if (!$tournament) {
  die("Tournament not found");
}

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

/* ---------- GENERATE MATCHES (ONLY ONCE) ---------- */
$check = $conn->prepare("SELECT COUNT(*) c FROM matches WHERE tournament_id = ?");
$check->bind_param("i", $tournament_id);
$check->execute();
$count = $check->get_result()->fetch_assoc()['c'];

if ($count == 0 && count($teams) >= 12) {

  shuffle($teams);

  $groupCount = 4;
  $groups = array_chunk($teams, ceil(count($teams) / $groupCount));
  $groupNames = ['A', 'B', 'C', 'D'];

  $order = 1;

  /* GROUP STAGE */
  foreach ($groups as $gi => $groupTeams) {
    for ($i = 0; $i < count($groupTeams); $i++) {
      for ($j = $i + 1; $j < count($groupTeams); $j++) {
        $stmt = $conn->prepare("
                    INSERT INTO matches
                    (tournament_id, round, group_name, match_order, team1_id, team2_id)
                    VALUES (?, 'group', ?, ?, ?, ?)
                ");
        $matchOrder = $order++;
        $stmt->bind_param(
          "isiii",
          $tournament_id,
          $groupNames[$gi],
          $matchOrder,
          $groupTeams[$i]['team_id'],
          $groupTeams[$j]['team_id']
        );
        $stmt->execute();
      }
    }
  }

  /* KNOCKOUT PLACEHOLDERS */
  $rounds = [
    'quarterfinal' => 4,
    'semifinal' => 2,
    'final' => 1,
    'third_place' => 1
  ];

  foreach ($rounds as $round => $matches) {
    for ($i = 1; $i <= $matches; $i++) {
      $stmt = $conn->prepare("
                INSERT INTO matches
                (tournament_id, round, match_order)
                VALUES (?, ?, ?)
            ");
      $matchOrder = $order++;
      $stmt->bind_param("isi", $tournament_id, $round, $matchOrder);
      $stmt->execute();
    }
  }
}

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
?>

<!DOCTYPE html>
<html>

<head>
  <title>Manage Match Schedule</title>
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
      padding: 12px;
      margin-bottom: 10px;
      border-radius: 6px
    }

    .round {
      font-weight: bold;
      margin-top: 20px
    }

    input[type=datetime-local] {
      padding: 6px
    }

    button {
      padding: 10px 16px;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer
    }
  </style>
</head>

<body>
  <div class="container">
    <h2>Match Schedule</h2>

    <form method="post" action="save-schedule.php">

      <?php
      $currentRound = '';
      while ($m = $matches->fetch_assoc()):
        if ($currentRound !== $m['round']):
          $currentRound = $m['round'];
          echo "<div class='round'>" . strtoupper($currentRound) . "</div>";
        endif;
      ?>
        <div class="match">
          <div>
            <?= $m['group_name'] ? "Group {$m['group_name']} — " : "" ?>
            <?= $m['team1'] ?? 'TBD' ?> vs <?= $m['team2'] ?? 'TBD' ?>
          </div>

          <input type="datetime-local"
            name="schedule[<?= $m['match_id'] ?>]"
            value="<?= $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_time'])) : '' ?>">
        </div>
      <?php endwhile; ?>

      <button type="submit">💾 Save Schedule</button>
    </form>

  </div>
</body>

</html>