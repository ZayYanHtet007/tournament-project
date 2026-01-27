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
  <title>Create Team - TournaX</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #06080f;
      color: #fff;
      min-height: 100vh;
    }

    .container {
      max-width: 500px;
      margin: 4rem auto;
      padding: 2rem;
      background: #11141d;
      border-radius: 12px;
      box-shadow: 0 0 20px rgba(255, 70, 85, 0.2);
    }

    h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2.5rem;
      margin-bottom: 1.5rem;
      text-align: center;
      color: #ff4655;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 700;
      color: #fff;
    }

    .input {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid #33374d;
      border-radius: 8px;
      background: #1a1c28;
      color: #fff;
      font-size: 1rem;
      margin-bottom: 1.5rem;
      transition: 0.2s;
    }

    .input:focus {
      border-color: #ff4655;
      outline: none;
      box-shadow: 0 0 8px rgba(255, 70, 85, 0.5);
    }

    .btn {
      padding: 0.6rem 1.2rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 700;
      transition: 0.2s;
      text-transform: uppercase;
    }

    .btn-primary {
      background: #ff4655;
      color: #fff;
      border: none;
    }

    .btn-primary:hover {
      background: #ff3b4b;
    }

    .btn-outline {
      border: 2px solid #ff4655;
      background: transparent;
      color: #ff4655;
    }

    .btn-outline:hover {
      background: #ff4655;
      color: #fff;
    }

    .alert {
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }

    .alert-error {
      background: rgba(255, 70, 85, 0.1);
      border: 1px solid #ff4655;
      color: #ff4655;
    }

    .alert-success {
      background: rgba(0, 255, 150, 0.1);
      border: 1px solid #00ff96;
      color: #00ff96;
    }

    a {
      text-decoration: none;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>Create Team</h1>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert alert-success">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <label for="team_name">Team Name *</label>
      <input id="team_name" name="team_name" class="input" maxlength="50" value="<?= htmlspecialchars($_POST['team_name'] ?? '') ?>" required>

      <div class="flex justify-between mt-6">
        <a class="btn btn-outline" href="../dashboard.php">Back</a>
        <button class="btn btn-primary" name="create_team" type="submit">Create Team</button>
      </div>
    </form>
  </div>
</body>

</html>
