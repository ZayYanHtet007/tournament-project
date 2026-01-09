<?php
session_start();
require_once "../database/dbConfig.php";


if (
  !isset($_SESSION['user_id']) ||
  !$_SESSION['is_organizer'] ||
  $_SESSION['organizer_status'] !== 'approved'
) {
  header("Location: ../login.php");
  exit;
}

if (!isset($_GET['id'])) {
  die("Invalid request");
}

$tournament_id = (int)$_GET['id'];
$organizer_id = $_SESSION['user_id'];
$message = "";


$stmt = $conn->prepare("
    SELECT *
    FROM tournaments
    WHERE tournament_id = ?
      AND organizer_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $tournament_id, $organizer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Tournament not found or access denied");
}

$tournament = $result->fetch_assoc();


if ($tournament['status'] !== 'upcoming') {
  die("You can only edit upcoming tournaments.");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $title = trim($_POST['title']);
  $description = trim($_POST['description']);
  $game_name = trim($_POST['game_name']);
  $game_type = $_POST['game_type'];
  $match_type = $_POST['match_type'];
  $format = $_POST['format'];
  $max_participants = (int)$_POST['max_participants'];
  $fee = (float)$_POST['fee'];
  $registration_deadline = $_POST['registration_deadline'];
  $start_date = $_POST['start_date'];

  if (
    empty($title) || empty($description) || empty($game_name) ||
    empty($registration_deadline) || empty($start_date)
  ) {
    $message = "❌ Please fill all required fields";
  } else {

    $update = $conn->prepare("
            UPDATE tournaments SET
                title = ?,
                description = ?,
                game_name = ?,
                game_type = ?,
                match_type = ?,
                format = ?,
                max_participants = ?,
                fee = ?,
                registration_deadline = ?,
                start_date = ?,
                last_update = NOW()
            WHERE tournament_id = ?
              AND organizer_id = ?
        ");

    $update->bind_param(
      "isssssidssii",
      $title,
      $description,
      $game_name,
      $game_type,
      $match_type,
      $format,
      $max_participants,
      $fee,
      $registration_deadline,
      $start_date,
      $tournament_id,
      $organizer_id
    );

    if ($update->execute()) {
      $message = "✅ Tournament updated successfully";
    } else {
      $message = "❌ Failed to update tournament";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Edit Tournament</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/organizer/edittour.css">
</head>

<body>
  <div class="container">

    <div class="header">
      <h1>Edit Tournament</h1>
      <p>Update your tournament details</p>
    </div>

    <?php if ($message): ?>
      <div style="margin-bottom:15px;color:#fff;background:#333;padding:10px;border-radius:6px;">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-content">

        <form method="post">

          <!-- Title -->
          <div class="form-group">
            <label>Tournament Title</label>
            <input type="text" name="title" required
              value="<?= htmlspecialchars($tournament['title']) ?>">
          </div>

          <!-- Description -->
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" required><?= htmlspecialchars($tournament['description']) ?></textarea>
          </div>

          <!-- Game Info -->
          <div class="form-row two-cols">
            <div class="form-group">
              <label>Game Name</label>
              <input type="text" name="game_name" required
                value="<?= htmlspecialchars($tournament['game_name']) ?>">
            </div>

            <div class="form-group">
              <label>Game Type</label>
              <select name="game_type" required>
                <?php
                $types = ['esport', 'board'];
                foreach ($types as $type) {
                  $selected = ($tournament['game_type'] === $type) ? "selected" : "";
                  echo "<option value='$type' $selected>$type</option>";
                }
                ?>
              </select>
            </div>
          </div>

          <!-- Match Info -->
          <div class="form-row two-cols">
            <div class="form-group">
              <label>Match Type</label>
              <select name="match_type" required>
                <option value="solo" <?= $tournament['match_type'] == 'solo' ? 'selected' : '' ?>>Solo</option>
                <option value="team" <?= $tournament['match_type'] == 'team' ? 'selected' : '' ?>>Team</option>
              </select>
            </div>

            <div class="form-group">
              <label>Format</label>
              <select name="format" required>
                <option value="single_elimination" <?= $tournament['format'] == 'single_elimination' ? 'selected' : '' ?>>Single Elimination</option>
                <option value="round_robin" <?= $tournament['format'] == 'round_robin' ? 'selected' : '' ?>>Round Robin</option>
                <option value="swiss" <?= $tournament['format'] == 'swiss' ? 'selected' : '' ?>>Swiss</option>
              </select>
            </div>
          </div>

          <!-- Participants & Fee -->
          <div class="form-row two-cols">
            <div class="form-group">
              <label>Max Participants</label>
              <input type="number" name="max_participants" min="1" required
                value="<?= $tournament['max_participants'] ?>">
            </div>

            <div class="form-group">
              <label>Entry Fee</label>
              <input type="number" name="fee" step="0.01" min="0" required
                value="<?= $tournament['fee'] ?>">
            </div>
          </div>

          <!-- Dates -->
          <div class="form-row two-cols">
            <div class="form-group">
              <label>Registration Deadline</label>
              <input type="date" name="registration_deadline" required
                value="<?= $tournament['registration_deadline'] ?>">
            </div>

            <div class="form-group">
              <label>Start Date</label>
              <input type="date" name="start_date" required
                value="<?= $tournament['start_date'] ?>">
            </div>
          </div>

          <!-- Buttons -->
          <div class="button-group">
            <button type="submit" class="btn-primary">Update Tournament</button>
            <a href="tournament-view.php?id=<?= $tournament_id ?>" class="btn-secondary">Back</a>
          </div>

        </form>

      </div>
    </div>
  </div>
</body>

</html>