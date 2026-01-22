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

/* ---------- VALIDATE TOURNAMENT ---------- */
if (!isset($_GET['tournament_id']) || !is_numeric($_GET['tournament_id'])) {
  die("Invalid tournament");
}

$tournament_id = (int)$_GET['tournament_id'];
$organizer_id  = (int)$_SESSION['user_id'];

/* ---------- VERIFY OWNERSHIP ---------- */
$chk = $conn->prepare("
    SELECT tournament_id 
    FROM tournaments 
    WHERE tournament_id=? AND organizer_id=?
");
$chk->bind_param("ii", $tournament_id, $organizer_id);
$chk->execute();
if (!$chk->get_result()->num_rows) {
  die("Access denied");
}

/* ---------- ROUND LABEL ---------- */
function roundLabel($r)
{
  return match ($r) {
    1 => "Group Stage",
    2 => "Quarterfinal",
    3 => "Semifinal",
    4 => "Final",
    default => "Unknown"
  };
}

/* ---------- CHECK ROUND COMPLETE ---------- */
function isRoundComplete($conn, $tid, $round)
{
  $q = $conn->prepare("
        SELECT COUNT(*) 
        FROM matches 
        WHERE tournament_id=? AND round=? AND status='pending'
    ");
  $q->bind_param("ii", $tid, $round);
  $q->execute();
  return $q->get_result()->fetch_row()[0] == 0;
}

/* ---------- GENERATE NEXT ROUND ---------- */
function generateNextRound($conn, $tid, $currentRound)
{

  if ($currentRound >= 4) return;

  $nextRound = $currentRound + 1;

  // Prevent duplicate generation
  $chk = $conn->prepare("
        SELECT COUNT(*) FROM matches 
        WHERE tournament_id=? AND round=?
    ");
  $chk->bind_param("ii", $tid, $nextRound);
  $chk->execute();
  if ($chk->get_result()->fetch_row()[0] > 0) return;

  // Collect winners
  $q = $conn->prepare("
        SELECT winner_team_id 
        FROM matches 
        WHERE tournament_id=? AND round=? AND status='completed'
    ");
  $q->bind_param("ii", $tid, $currentRound);
  $q->execute();

  $winners = [];
  $res = $q->get_result();
  while ($r = $res->fetch_assoc()) {
    $winners[] = $r['winner_team_id'];
  }

  if (count($winners) < 2) return;

  shuffle($winners);
  $order = 1;

  for ($i = 0; $i < count($winners); $i += 2) {
    if (!isset($winners[$i + 1])) break;

    $ins = $conn->prepare("
            INSERT INTO matches
            (tournament_id, round, match_order, team1_id, team2_id, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
    $ins->bind_param(
      "iiiii",
      $tid,
      $nextRound,
      $order++,
      $winners[$i],
      $winners[$i + 1]
    );
    $ins->execute();
  }
}

/* ---------- SAVE SCORE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_id'])) {

  $match_id = (int)$_POST['match_id'];
  $score1   = (int)$_POST['score1'];
  $score2   = (int)$_POST['score2'];

  if ($score1 === $score2) {
    die("Draws not allowed");
  }

  $q = $conn->prepare("
        SELECT * FROM matches 
        WHERE match_id=? AND tournament_id=? AND status='pending'
    ");
  $q->bind_param("ii", $match_id, $tournament_id);
  $q->execute();
  $match = $q->get_result()->fetch_assoc();

  if (!$match) die("Invalid match");

  $winner = $score1 > $score2 ? $match['team1_id'] : $match['team2_id'];

  $u = $conn->prepare("
        UPDATE matches SET
            score1=?, score2=?,
            winner_team_id=?,
            status='completed'
        WHERE match_id=?
    ");
  $u->bind_param("iiii", $score1, $score2, $winner, $match_id);
  $u->execute();

  if (isRoundComplete($conn, $tournament_id, $match['round'])) {
    generateNextRound($conn, $tournament_id, $match['round']);
  }

  header("Location: manage-bracket.php?tournament_id=$tournament_id");
  exit;
}

/* ---------- FETCH MATCHES ---------- */
$stmt = $conn->prepare("
    SELECT m.*,
           t1.team_name AS team1,
           t2.team_name AS team2
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    WHERE m.tournament_id=?
    ORDER BY m.round, m.match_order
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$matches = $stmt->get_result();
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Bracket Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <div class="max-w-4xl mx-auto p-6">

    <h1 class="text-2xl font-bold mb-4 text-center">Bracket Management</h1>

    <?php $currentRound = 0; ?>

    <?php while ($m = $matches->fetch_assoc()): ?>

      <?php if ($currentRound !== $m['round']):
        $currentRound = $m['round']; ?>
        <h2 class="text-xl font-semibold mt-6 mb-2">
          <?= roundLabel($currentRound) ?>
        </h2>
      <?php endif; ?>

      <div class="bg-white p-4 rounded shadow mb-3">
        <form method="post">
          <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">

          <div class="grid grid-cols-3 gap-4 text-center items-center">
            <div>
              <strong><?= htmlspecialchars($m['team1']) ?></strong>
              <input type="number" name="score1"
                class="border w-full mt-1 px-2 py-1"
                <?= $m['status'] === 'completed' ? 'readonly' : '' ?>>
            </div>

            <div class="font-bold">VS</div>

            <div>
              <strong><?= htmlspecialchars($m['team2']) ?></strong>
              <input type="number" name="score2"
                class="border w-full mt-1 px-2 py-1"
                <?= $m['status'] === 'completed' ? 'readonly' : '' ?>>
            </div>
          </div>

          <?php if ($m['status'] === 'pending'): ?>
            <button class="bg-blue-600 text-white px-4 py-1 rounded mt-3">
              Save Result
            </button>
          <?php else: ?>
            <div class="text-green-600 mt-2 font-semibold">
              Winner:
              <?= $m['winner_team_id'] == $m['team1_id'] ? $m['team1'] : $m['team2'] ?>
            </div>
          <?php endif; ?>

        </form>
      </div>

    <?php endwhile; ?>

  </div>
</body>

</html>