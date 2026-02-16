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

/* ---------- FETCH TOURNAMENT ---------- */
if (!isset($_GET['tournament_id']) || !is_numeric($_GET['tournament_id'])) die("Invalid request");

$tournament_id = (int)$_GET['tournament_id'];
$organizer_id  = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM tournaments WHERE tournament_id=? AND organizer_id=? LIMIT 1");
$stmt->bind_param("ii", $tournament_id, $organizer_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

if (!$tournament) die("Tournament not found or access denied");

/* ---------- FETCH ANNOUNCEMENT ---------- */
$annStmt = $conn->prepare("
  SELECT rules, system_info 
  FROM tournament_announcements 
  WHERE tournament_id=? 
  LIMIT 1
");
$annStmt->bind_param("i", $tournament_id);
$annStmt->execute();
$announcement = $annStmt->get_result()->fetch_assoc();
$hasAnnouncement = is_array($announcement);
if (!$hasAnnouncement) {
  $announcement = [
    'rules' => '',
    'system_info' => ''
  ];
}

/* ---------- PERMISSIONS ---------- */
$status       = $tournament['status'];
$wasRejected  = ($tournament['admin_status'] ?? '') === 'rejected';
$canEditAll   = ($status === 'upcoming' || $wasRejected);
$canEditDates = in_array($status, ['upcoming', 'approved', 'ongoing']) || $wasRejected;
$isLocked     = ($status === 'completed');

$readOnly = $isLocked ? 'readonly' : '';
$disabled = $isLocked ? 'disabled' : '';

$message = "";

/* ---------- SINGLE UPDATE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {

  $conn->begin_transaction();

  try {
    $reg_start = $_POST['registration_start_date'];
    $reg_end   = $_POST['registration_deadline'];
    $start     = $_POST['start_date'];

    if (strtotime($reg_start) >= strtotime($reg_end)) {
      throw new Exception("❌ Registration start must be before deadline.");
    }
    if (strtotime($start) <= strtotime($reg_end)) {
      throw new Exception("❌ Tournament start must be after registration deadline.");
    }

    /* ---- TOURNAMENT ---- */
    if ($canEditAll) {
      $title = clean($_POST['title']);
      $description = clean($_POST['description']);
      $max_participants = (int)$_POST['max_participants'];
      $team_size = (int)$_POST['team_size'];
      $fee = (float)$_POST['fee'];
      $prize_pool = (float)$_POST['prize_pool'];

      if ($max_participants < 12) {
        throw new Exception("❌ Minimum participants is 12.");
      }

      $update = $conn->prepare("
        UPDATE tournaments SET
          title=?, description=?, max_participants=?, team_size=?,
          fee=?, prize_pool=?,
          registration_start_date=?, registration_deadline=?, start_date=?,
          last_update=NOW()
        WHERE tournament_id=? AND organizer_id=?
      ");
      $update->bind_param(
        "ssiiddsssii",
        $title,
        $description,
        $max_participants,
        $team_size,
        $fee,
        $prize_pool,
        $reg_start,
        $reg_end,
        $start,
        $tournament_id,
        $organizer_id
      );
      $update->execute();
    }

    /* ---- ANNOUNCEMENT ---- */
    $rules = trim($_POST['rules']);
    $system_info = trim($_POST['system_info']);

    if ($hasAnnouncement) {
      $annUpdate = $conn->prepare("
        UPDATE tournament_announcements
        SET rules=?, system_info=?, last_update=NOW()
        WHERE tournament_id=?
      ");
      $annUpdate->bind_param("ssi", $rules, $system_info, $tournament_id);
      $annUpdate->execute();
      $annUpdate->close();
    } else {
      $annInsert = $conn->prepare("
        INSERT INTO tournament_announcements (tournament_id, title, rules, system_info, created_at, last_update)
        VALUES (?, ?, ?, ?, NOW(), NOW())
      ");
      $annTitle = $tournament['title'] ?? 'Tournament';
      $annInsert->bind_param("isss", $tournament_id, $annTitle, $rules, $system_info);
      $annInsert->execute();
      $annInsert->close();
      $hasAnnouncement = true;
    }

    if ($wasRejected) {
      $pending = 'pending';
      $adminUpdate = $conn->prepare("
        UPDATE tournaments
        SET admin_status=?, last_update=NOW()
        WHERE tournament_id=? AND organizer_id=?
      ");
      $adminUpdate->bind_param("sii", $pending, $tournament_id, $organizer_id);
      $adminUpdate->execute();
      $adminUpdate->close();

      $updatedTitle = $canEditAll ? $title : $tournament['title'];
      $notiTitle = "Tournament Resubmitted";
      $notiMessage = "Tournament resubmitted for approval: \"{$updatedTitle}\" (tournament ID #{$tournament_id}).";
      $notiStmt = $conn->prepare("
        INSERT INTO admin_notifications (admin_id, title, message, type, created_at)
        SELECT admin_id, ?, ?, 'tournament_resubmitted', NOW() FROM admins
      ");
      if ($notiStmt) {
        $notiStmt->bind_param("ss", $notiTitle, $notiMessage);
        $notiStmt->execute();
        $notiStmt->close();
      }
    }

    $conn->commit();
    $message = "✅ Tournament updated successfully.";

    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    $annStmt->execute();
    $announcement = $annStmt->get_result()->fetch_assoc();
    if (!is_array($announcement)) {
      $announcement = [
        'rules' => '',
        'system_info' => ''
      ];
      $hasAnnouncement = false;
    } else {
      $hasAnnouncement = true;
    }

  } catch (Exception $e) {
    $conn->rollback();
    $message = $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Tournament</title>
<style>
    body {
      background-color: #010a13;
      color: #f0f5f5;
      font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      margin: 0;
      padding: 40px 20px;
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
      background: #051923;
      padding: 40px;
      border: 1px solid #00eeff;
      border-radius: 4px;
    }

    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 1px solid rgba(0, 238, 255, 0.2);
        padding-bottom: 20px;
    }

    h1 {
      margin: 0;
      font-size: 1.8rem;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    .btn-back {
        text-decoration: none;
        color: #00eeff;
        font-size: 0.8rem;
        font-weight: bold;
        text-transform: uppercase;
        border: 1px solid #00eeff;
        padding: 8px 15px;
        transition: 0.3s;
    }

    .btn-back:hover {
        background: #00eeff;
        color: #000;
    }

    h2 {
      margin-top: 30px;
      font-size: 1rem;
      color: #00eeff;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    label {
        display: block;
        font-size: 0.75rem;
        color: #a0a0a0;
        margin-top: 15px;
        margin-bottom: 5px;
    }

    input, textarea {
      width: 100%;
      padding: 12px;
      background: #010a13;
      border: 1px solid #1a2e38;
      border-radius: 4px;
      color: #fff;
      font-size: 1rem;
      box-sizing: border-box;
    }

    input:focus, textarea:focus {
      outline: none;
      border-color: #00eeff;
    }

    input[readonly], textarea[readonly] {
        background: rgba(255,255,255,0.05);
        color: #777;
        cursor: not-allowed;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 15px;
    }

    button {
      margin-top: 40px;
      width: 100%;
      padding: 15px;
      background: #00eeff;
      color: #000;
      border: none;
      border-radius: 4px;
      font-size: 1rem;
      font-weight: bold;
      text-transform: uppercase;
      cursor: pointer;
      transition: 0.3s;
    }

    button:hover:not(:disabled) {
      background: #00c2cf;
    }

    button:disabled {
      background: #1a2e38;
      color: #555;
      cursor: not-allowed;
    }

    .alert {
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
      text-align: center;
      font-weight: bold;
    }
    .alert.error { background: #fee2e2; color: #991b1b; }
    .alert.success { background: #dcfce7; color: #166534; }
</style>
</head>

<body>
<div class="container">

<div class="header-row">
    <h1>Edit Tournament</h1>
    <a href="manageTournament.php" class="btn-back">← Back</a>
</div>

<?php if ($message): ?>
<div class="alert <?= str_contains($message,'❌')?'error':'success' ?>">
  <?= $message ?>
</div>
<?php endif; ?>

<form method="post">

<h2>🏷 Tournament Information</h2>
<label>Title</label>
<input type="text" name="title" value="<?= htmlspecialchars($tournament['title']) ?>" <?= $readOnly ?>>

<label>Description</label>
<textarea name="description" rows="3" <?= $readOnly ?>><?= htmlspecialchars($tournament['description']) ?></textarea>

<div class="grid">
    <div>
        <label>Max Participants</label>
        <input type="number" name="max_participants" value="<?= $tournament['max_participants'] ?>" min="12" <?= $readOnly ?>>
    </div>
    <div>
        <label>Team Size</label>
        <input type="number" name="team_size" value="<?= $tournament['team_size'] ?>" min="1" <?= $readOnly ?>>
    </div>
</div>

<h2>💰 Fees & Prize Pool</h2>
<div class="grid">
    <div>
        <label>Fee</label>
        <input type="number" step="0.01" name="fee" value="<?= $tournament['fee'] ?>" <?= $readOnly ?>>
    </div>
    <div>
        <label>Prize Pool</label>
        <input type="number" step="0.01" name="prize_pool" value="<?= $tournament['prize_pool'] ?>" <?= $readOnly ?>>
    </div>
</div>

<h2>📅 Tournament Dates</h2>
<div class="grid">
  <div>
    <label>Registration Start</label>
    <input type="date" name="registration_start_date" value="<?= $tournament['registration_start_date'] ?>" <?= $readOnly ?>>
  </div>
  <div>
    <label>Deadline</label>
    <input type="date" name="registration_deadline" value="<?= $tournament['registration_deadline'] ?>" <?= $readOnly ?>>
  </div>
  <div>
    <label>Start Date</label>
    <input type="date" name="start_date" value="<?= $tournament['start_date'] ?>" <?= $readOnly ?>>
  </div>
</div>

<h2>📜 Rules & Regulations</h2>
<textarea name="rules" rows="5" <?= $readOnly ?>><?= htmlspecialchars($announcement['rules']) ?></textarea>

<h2>⚙ Tournament System</h2>
<textarea name="system_info" rows="4" <?= $readOnly ?>><?= htmlspecialchars($announcement['system_info']) ?></textarea>

<button <?= $disabled ?>>Update Tournament</button>

</form>
</div>
</body>
</html>
