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
<!-- Font Awesome for icons (monochrome) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ================= SQUARE DESIGN, BRIGHTER BLUE ACCENTS ================= */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background-color: #0b0d12;
        color: #e1e5ec;
        font-family: 'Inter', sans-serif;
        padding: 2rem 1rem;
        min-height: 100vh;
    }

    /* Main container – dark, square */
    .container {
        max-width: 800px;
        margin: 0 auto;
        background-color: #10131c;
        border: 1px solid #2f3a4a;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
        padding: 2rem;
    }

    /* Header row */
    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        border-bottom: 1px solid #2f3a4a;
        padding-bottom: 1rem;
    }

    h1 {
        font-size: 1.8rem;
        font-weight: 600;
        color: #b8d0f0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
    }

    .btn-back {
        text-decoration: none;
        color: #b8d0f0;
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        border: 1px solid #2d7ff9;
        padding: 0.5rem 1rem;
        transition: 0.1s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 0;
    }
    .btn-back:hover {
        background-color: #264763;
        border-color: #5a9eff;
        color: white;
    }

    h2 {
        font-size: 1.2rem;
        font-weight: 600;
        color: #b8d0f0;
        border-left: 4px solid #2d7ff9;
        padding-left: 0.75rem;
        margin: 2rem 0 1rem 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    label {
        display: block;
        font-size: 0.8rem;
        font-weight: 500;
        color: #9aaec9;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    input, textarea {
        width: 100%;
        padding: 0.75rem;
        background-color: #0f141e;
        border: 1px solid #2f3a4a;
        color: #e0e6f0;
        font-size: 0.95rem;
        border-radius: 0; /* square */
        transition: 0.1s;
        font-family: 'Inter', sans-serif;
    }

    input:focus, textarea:focus {
        outline: none;
        border-color: #2d7ff9;
        box-shadow: 0 0 0 2px rgba(45, 127, 249, 0.3);
    }

    input[readonly], textarea[readonly] {
        background-color: #1c222d;
        color: #7c8a9c;
        border-color: #353e4e;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-top: 0.5rem;
    }

    button {
        margin-top: 2rem;
        width: 100%;
        padding: 0.9rem;
        background-color: #1e3142;
        border: 1px solid #2d7ff9;
        color: white;
        font-weight: 600;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: 0.1s;
        border-radius: 0;
    }

    button:hover:not(:disabled) {
        background-color: #264763;
        border-color: #5a9eff;
    }

    button:disabled {
        background-color: #1a1f2a;
        border-color: #353e4e;
        color: #6b7a8f;
        cursor: not-allowed;
    }

    /* Alert messages – square, bright blue border */
    .alert {
        padding: 1rem;
        margin-bottom: 1.5rem;
        border: 1px solid #2d7ff9;
        background-color: #1b2535;
        color: #c0dcff;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-radius: 0;
    }
    .alert.error {
        border-color: #2d7ff9; /* consistent, no extra colors */
    }
    .alert.success {
        border-color: #2d7ff9;
    }

    /* Icons */
    i {
        color: inherit;
    }

    /* Adjust spacing for textareas */
    textarea {
        resize: vertical;
    }
</style>
</head>

<body>
<div class="container">

<div class="header-row">
    <h1><i class="fas fa-pen"></i> Edit Tournament</h1>
    <a href="manageTournament.php?tournament_id=<?= $tournament_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<?php if ($message): ?>
<div class="alert <?= strpos($message,'❌') !== false ? 'error' : 'success' ?>">
    <i class="fas <?= strpos($message,'❌') !== false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
    <?= $message ?>
</div>
<?php endif; ?>

<form method="post">

<h2><i class="fas fa-tag"></i> Tournament Information</h2>
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

<h2><i class="fas fa-coins"></i> Fees & Prize Pool</h2>
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

<h2><i class="fas fa-calendar-alt"></i> Tournament Dates</h2>
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

<h2><i class="fas fa-gavel"></i> Rules & Regulations</h2>
<textarea name="rules" rows="5" <?= $readOnly ?>><?= htmlspecialchars($announcement['rules']) ?></textarea>

<h2><i class="fas fa-cog"></i> Tournament System</h2>
<textarea name="system_info" rows="4" <?= $readOnly ?>><?= htmlspecialchars($announcement['system_info']) ?></textarea>

<button <?= $disabled ?>><i class="fas fa-save"></i> Update Tournament</button>

</form>
</div>
</body>
</html>