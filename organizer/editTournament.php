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

/* ---------- HELPERS ---------- */
function clean($v)
{
  return htmlspecialchars(trim($v), ENT_QUOTES);
}
function valid_date($d)
{
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

/* ---------- FETCH TOURNAMENT ---------- */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die("Invalid request");
$tournament_id = (int)$_GET['id'];
$organizer_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM tournaments WHERE tournament_id=? AND organizer_id=? LIMIT 1");
$stmt->bind_param("ii", $tournament_id, $organizer_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
if (!$tournament) die("Tournament not found or access denied");

/* ---------- PERMISSIONS ---------- */
$status = $tournament['status'];
$canEditAll   = ($status === 'upcoming');
$canEditDates = in_array($status, ['upcoming', 'approved', 'ongoing']);
$isLocked     = ($status === 'completed');

$message = "";

/* ---------- FORM SUBMIT ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
  $reg_start = $_POST['registration_start_date'];
  $reg_end   = $_POST['registration_deadline'];
  $start     = $_POST['start_date'];

  $reg_start_ts = strtotime($reg_start);
  $reg_end_ts   = strtotime($reg_end);
  $start_ts     = strtotime($start);

  if (!$reg_start_ts || !$reg_end_ts || !$start_ts) {
    $message = "❌ Invalid date format.";
  } elseif ($reg_start_ts >= $reg_end_ts) {
    $message = "❌ Registration start must be before registration deadline.";
  } elseif ($start_ts <= $reg_end_ts) {
    $message = "❌ Tournament start date must be after registration deadline.";
  } else {
    if ($canEditAll) {
      $title = clean($_POST['title']);
      $description = clean($_POST['description']);
      $max_participants = (int)$_POST['max_participants'];
      $team_size = (int)$_POST['team_size'];
      $fee = (float)$_POST['fee'];

      $update = $conn->prepare("
                UPDATE tournaments SET
                    title=?, description=?, max_participants=?, team_size=?, fee=?,
                    registration_start_date=?, registration_deadline=?, start_date=?, last_update=NOW()
                WHERE tournament_id=? AND organizer_id=?
            ");
      $update->bind_param(
        "ssiidsssii",
        $title,
        $description,
        $max_participants,
        $team_size,
        $fee,
        $reg_start,
        $reg_end,
        $start,
        $tournament_id,
        $organizer_id
      );
      $update->execute();
      $message = "✅ Tournament updated successfully.";
    } elseif ($canEditDates) {
      $update = $conn->prepare("
                UPDATE tournaments SET
                    registration_start_date=?, registration_deadline=?, start_date=?, last_update=NOW()
                WHERE tournament_id=? AND organizer_id=?
            ");
      $update->bind_param("sssii", $reg_start, $reg_end, $start, $tournament_id, $organizer_id);
      $update->execute();
      $message = "✅ Dates updated successfully.";
    }

    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Edit Tournament</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../css/user/responsive.css">
  <style>
    .input {
      width: 100%;
      padding: .5rem;
      border: 1px solid #e5e7eb;
      border-radius: .375rem;
    }

    .btn {
      padding: .5rem .75rem;
      border-radius: .375rem;
      cursor: pointer;
    }

    .btn-primary {
      background: #2563eb;
      color: #fff;
      border: none;
    }

    .btn-outline {
      border: 1px solid #d1d5db;
      background: #fff;
    }

    .bracket {
      display: flex;
      gap: 20px;
      overflow-x: auto;
      padding: 10px;
    }

    .round {
      min-width: 160px;
    }

    .match {
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 6px;
      margin-bottom: 12px;
      text-align: center;
    }

    input[readonly],
    textarea[readonly] {
      background: #f3f4f6;
      cursor: not-allowed;
    }
  </style>
</head>

<body class="bg-gray-50">
  <div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4 text-center">Edit Tournament</h1>

    <?php if ($message): ?>
      <div class="text-green-600 mb-4"><?= $message ?></div>
    <?php endif; ?>

    <form method="post" class="bg-white p-6 rounded shadow">
      <?php if ($canEditAll): ?>
        <label>Title *</label>
        <input type="text" name="title" class="input mb-4" value="<?= htmlspecialchars($tournament['title']) ?>" <?= $isLocked ? 'readonly' : '' ?>>

        <label>Description *</label>
        <textarea name="description" class="input mb-4" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($tournament['description']) ?></textarea>

        <label>Max Participants *</label>
        <input type="number" name="max_participants" id="max_participants" class="input mb-4" value="<?= $tournament['max_participants'] ?>" min="2">

        <label>Team Size *</label>
        <input type="number" name="team_size" class="input mb-4" value="<?= $tournament['team_size'] ?>" min="1">

        <label>Entry Fee</label>
        <input type="number" step="0.01" name="fee" class="input mb-4" value="<?= $tournament['fee'] ?>" min="0">
      <?php endif; ?>

      <label>Registration Start *</label>
      <input type="date" name="registration_start_date" class="input mb-4" value="<?= $tournament['registration_start_date'] ?>">

      <label>Registration Deadline *</label>
      <input type="date" name="registration_deadline" class="input mb-4" value="<?= $tournament['registration_deadline'] ?>">

      <label>Start Date *</label>
      <input type="date" name="start_date" class="input mb-4" value="<?= $tournament['start_date'] ?>">

      <h2 class="font-semibold mb-2">Bracket / Group Stage Preview</h2>
      <div id="bracketPreview" class="bracket bg-gray-100 rounded p-2 mb-4"></div>

      <button type="submit" class="btn btn-primary">Update Tournament</button>
    </form>
  </div>

  <script>
    const bracketPreview = document.getElementById('bracketPreview');
    const maxInput = document.getElementById('max_participants');

    function generateBracket(teams) {
      bracketPreview.innerHTML = '';
      teams = parseInt(teams);
      if (!teams || teams < 2) return;

      // Determine group count dynamically
      let groupCount;
      if (teams <= 4) groupCount = 1;
      else if (teams <= 8) groupCount = 2;
      else if (teams <= 16) groupCount = 4;
      else groupCount = 8;

      let baseSize = Math.floor(teams / groupCount);
      let extra = teams % groupCount;

      for (let g = 1; g <= groupCount; g++) {
        let size = baseSize + (extra > 0 ? 1 : 0);
        if (extra > 0) extra--;

        let col = document.createElement('div');
        col.className = 'round';
        col.innerHTML = `<h3>Group ${g} (${size} teams)</h3>`;
        for (let i = 1; i <= size; i++) {
          col.innerHTML += `<div class="match">Team TBD</div>`;
        }
        bracketPreview.appendChild(col);
      }
    }

    // Update bracket when max participants changes
    maxInput.addEventListener('input', () => generateBracket(maxInput.value));

    // Initial bracket
    document.addEventListener('DOMContentLoaded', () => generateBracket(maxInput.value));
  </script>
</body>

</html>