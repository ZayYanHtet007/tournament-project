<?php
session_start();


/* Already logged in? Redirect */
if (isset($_SESSION['user_id'])) {
  if ($_SESSION['is_organizer'] == 1) {
    header("Location: ../organizer/dashboard.php");
  } else {
    header("Location: ../player/dashboard.php");
  }
  exit;
}
require_once "database/dbConfig.php";

$error = "";

if (isset($_POST['login'])) {

  $username = trim($_POST['username']);
  $password = $_POST['password'];

  $sql = "SELECT * FROM users 
            WHERE username = ? OR email = ?
            LIMIT 1";

  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "ss", $username, $username);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);

  if ($user = mysqli_fetch_assoc($result)) {

    if (!password_verify($password, $user['password'])) {
      $error = "Invalid password";
    } else {

      /* ORGANIZER LOGIN */
      if ($user['is_organizer'] == 1) {

        if ($user['organizer_status'] !== 'approved') {
          $error = "Organizer account not approved";
        } else {
          $_SESSION['user_id'] = $user['user_id'];
          $_SESSION['username'] = $user['username'];
          $_SESSION['is_organizer'] = 1;

          header("Location: ../organizer/dashboard.php");
          exit;
        }
      }
      /* PLAYER LOGIN */ else {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_organizer'] = 0;

        header("Location: ../player/dashboard.php");
        exit;
      }
    }
  } else {
    $error = "User not found";
  }
}
?>

<!DOCTYPE html>
<html>

<head>
  <title>Login</title>
  <style>
    body {
      font-family: Arial;
      background: #f4f4f4;
    }

    .box {
      width: 350px;
      margin: 100px auto;
      background: white;
      padding: 20px;
      border-radius: 8px;
    }

    input,
    button {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
    }

    button {
      background: #007bff;
      color: white;
      border: none;
    }

    .error {
      color: red;
    }
  </style>
</head>

<body>

  <div class="box">
    <h2>Login</h2>

    <?php if ($error) echo "<p class='error'>$error</p>"; ?>

    <form method="POST">
      <input type="text" name="username" placeholder="Username or Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <button name="login">Login</button>
    </form>
  </div>

</body>

</html>