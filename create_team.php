<?php
session_start();
require_once "database/dbConfig.php";

// ---------- ACCESS CONTROL ----------
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$message = '';

// ---------- CREATE TEAM ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_team'])) {
  $team_name = trim($_POST['team_name'] ?? '');

  // Validate team name
  if ($team_name === '') {
    $errors[] = "Team name is required.";
  } elseif (mb_strlen($team_name) > 50) {
    $errors[] = "Team name must be 50 characters or fewer.";
  }

  // Check uniqueness
  if (empty($errors)) {
    $stmt = $conn->prepare("SELECT team_id FROM teams WHERE team_name = ? LIMIT 1");
    $stmt->bind_param("s", $team_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
      $errors[] = "A team with that name already exists. Choose another name.";
    }
    $stmt->close();
  }

  // Insert team and leader
  if (empty($errors)) {
    $conn->begin_transaction();
    try {
      // Insert team
      $stmt = $conn->prepare("INSERT INTO teams (team_name, leader_id) VALUES (?, ?)");
      $stmt->bind_param("si", $team_name, $user_id);
      if (!$stmt->execute()) {
        throw new Exception("DB error creating team: " . $stmt->error);
      }
      $team_id = $stmt->insert_id;
      $stmt->close();

      // Insert team leader
      $stmt2 = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'leader')");
      $stmt2->bind_param("ii", $team_id, $user_id);
      if (!$stmt2->execute()) {
        throw new Exception("DB error adding team leader: " . $stmt2->error);
      }
      $stmt2->close();

      $conn->commit();
      $message = "Team created successfully!";
      header("Location: team.php?team_id={$team_id}");
      exit;
    } catch (Exception $e) {
      $conn->rollback();
      $errors[] = "Failed to create team: " . $e->getMessage();
    }
  }
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>Create Team</title>
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

    .btn-outline {
      border: 1px solid #d1d5db;
      background: #fff;
      padding: .45rem .7rem;
      border-radius: .375rem
    }
  </style>
</head>

<body class="bg-gray-50">
  <div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Create Team</h1>

    <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded mb-4">
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 p-3 rounded mb-4">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="bg-white p-6 rounded shadow">
      <label class="block mb-1">Team Name *</label>
      <input name="team_name" class="input mb-4" maxlength="50" value="<?= htmlspecialchars($_POST['team_name'] ?? '') ?>" required>

      <div class="flex items-center">
        <a class="btn btn-outline mr-2" href="../dashboard.php">Back</a>
        <button class="btn btn-primary ml-auto" name="create_team" type="submit">Create Team</button>
      </div>
    </form>
  </div>
</body>

</html>