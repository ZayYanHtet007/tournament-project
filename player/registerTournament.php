<?php
session_start();
require_once "../database/dbConfig.php";

/*
 * Player Tournament Registration
 * - Must be logged in
 * - Select a tournament via ?tournament_id=...
 * - Create a team (team leader). Optionally you can extend to "join team" later.
 * - Checks:
 *   - tournament exists
 *   - within registration window
 *   - max participants not exceeded
 *   - user not already on a team for this tournament
 *   - unique team name per tournament
 * - Inserts team and team_members atomically; redirects to stripe-payment page for payment
 */

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$message = '';

if (!$tournament_id) {
  $message = 'Invalid tournament.';
} else {
  // fetch tournament
  $stmt = $conn->prepare("SELECT tournament_id, title, registration_start_date, registration_deadline, start_date, max_participants, team_size, fee, status, admin_status FROM tournaments WHERE tournament_id = ?");
  $stmt->bind_param("i", $tournament_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $tournament = $res->fetch_assoc();
  $stmt->close();

  if (!$tournament) {
    $message = 'Tournament not found.';
  } else {
    // check admin_status and tournament status as needed
    // allow registration only if admin approved (optional)
    if ($tournament['admin_status'] !== 'approved') {
      $message = 'Tournament is not open for registration (awaiting admin approval).';
    } else {
      $today = date('Y-m-d');
      if ($today < $tournament['registration_start_date'] || $today > $tournament['registration_deadline']) {
        $message = 'Registration is closed for this tournament.';
      }
    }
  }
}

// Handle form submission: create team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_team']) && $tournament && !$message) {
  $team_name = trim($_POST['team_name'] ?? '');
  if ($team_name === '') {
    $message = 'Team name is required.';
  } else {
    // check if user already in a team for this tournament
    $q = $conn->prepare("
            SELECT tm.team_member_id
            FROM team_members tm
            JOIN teams t ON tm.team_id = t.team_id
            WHERE tm.user_id = ? AND t.tournament_id = ?
            LIMIT 1
        ");
    $q->bind_param("ii", $user_id, $tournament_id);
    $q->execute();
    $r = $q->get_result();
    if ($r->num_rows > 0) {
      $message = 'You are already registered (in a team) for this tournament.';
      $q->close();
    } else {
      $q->close();

      // check current teams count vs max_participants
      $q2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM teams WHERE tournament_id = ?");
      $q2->bind_param("i", $tournament_id);
      $q2->execute();
      $res2 = $q2->get_result()->fetch_assoc();
      $q2->close();

      $current_teams = (int)$res2['cnt'];
      $max_teams = (int)$tournament['max_participants'];

      if ($current_teams >= $max_teams) {
        $message = 'Tournament is full. No more teams can register.';
      } else {
        // check unique team name for this tournament
        $q3 = $conn->prepare("SELECT team_id FROM teams WHERE tournament_id = ? AND team_name = ? LIMIT 1");
        $q3->bind_param("is", $tournament_id, $team_name);
        $q3->execute();
        $res3 = $q3->get_result();
        if ($res3->num_rows > 0) {
          $message = 'A team with that name already exists for this tournament. Choose another name.';
          $q3->close();
        } else {
          $q3->close();

          // proceed to create team and membership in a transaction
          $conn->begin_transaction();
          try {
            $ins = $conn->prepare("INSERT INTO teams (tournament_id, team_name, leader_id) VALUES (?, ?, ?)");
            $ins->bind_param("isi", $tournament_id, $team_name, $user_id);
            if (!$ins->execute()) {
              throw new Exception("DB error creating team: " . $ins->error);
            }
            $team_id = $ins->insert_id;
            $ins->close();

            $ins2 = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'leader')");
            $ins2->bind_param("ii", $team_id, $user_id);
            if (!$ins2->execute()) {
              throw new Exception("DB error adding team member: " . $ins2->error);
            }
            $ins2->close();

            // If tournament has fee, we don't create a payment record here necessarily.
            // We'll redirect to stripe-payment.php which can create the payment record.
            $conn->commit();

            // Redirect to payment flow (stripe-payment.php) with team and tournament info
            header("Location: ../organizer/stripe-payment.php?tournament_id={$tournament_id}&team_id={$team_id}");
            exit;
          } catch (Exception $e) {
            $conn->rollback();
            $message = "Error creating team: " . $e->getMessage();
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>Register for Tournament - <?= htmlspecialchars($tournament['title'] ?? 'Tournament') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .input {
      width: 100%;
      padding: .5rem;
      border: 1px solid #e5e7eb;
      border-radius: .375rem
    }

    .btn {
      padding: .5rem .75rem;
      border-radius: .375rem;
      cursor: pointer
    }

    .btn-primary {
      background: #2563eb;
      color: #fff;
      border: none
    }
  </style>
</head>

<body class="bg-gray-50">
  <div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Register for Tournament</h1>

    <?php if ($message): ?>
      <div class="text-red-600 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($tournament && !$message): ?>
      <div class="bg-white p-4 rounded shadow mb-4">
        <h2 class="font-semibold"><?= htmlspecialchars($tournament['title']) ?></h2>
        <p class="text-sm text-gray-600">
          Registration: <?= htmlspecialchars($tournament['registration_start_date']) ?> to <?= htmlspecialchars($tournament['registration_deadline']) ?>
        </p>
        <p class="text-sm text-gray-600">Team size: <?= (int)$tournament['team_size'] ?> — Fee per team: <?= number_format((float)$tournament['fee'], 2) ?></p>
      </div>

      <form method="post" class="bg-white p-6 rounded shadow">
        <label class="block mb-1">Team Name *</label>
        <input class="input mb-4" name="team_name" maxlength="50" required>

        <div class="flex items-center">
          <a class="btn btn-outline mr-2" href="../tournaments.php">Back to tournaments</a>
          <button class="btn btn-primary ml-auto" name="create_team" type="submit">Create Team & Register</button>
        </div>
      </form>
    <?php elseif ($tournament): ?>
      <!-- If there's a message but tournament exists, show tournament info and message -->
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-semibold"><?= htmlspecialchars($tournament['title']) ?></h2>
        <p class="text-sm text-gray-600">
          Registration: <?= htmlspecialchars($tournament['registration_start_date']) ?> to <?= htmlspecialchars($tournament['registration_deadline']) ?>
        </p>
        <p class="mt-2"><a class="text-blue-600" href="../tournaments.php">Back to tournaments</a></p>
      </div>
    <?php else: ?>
      <p class="mt-4"><a class="text-blue-600" href="../tournaments.php">Back to tournaments</a></p>
    <?php endif; ?>
  </div>
</body>

</html>