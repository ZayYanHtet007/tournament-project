<?php
session_start();
require_once "../database/dbConfig.php";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT admin_id, password FROM admins WHERE username=? ");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
      $_SESSION['admin_id'] = $user['admin_id'];
      $_SESSION['admin_username'] = $username;
      header("Location: admin-dashboard.php");
      exit;
    } else {
      $message = "Incorrect password";
    }
  } else {
    $message = "Admin not found";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/admin.css">
</head>

<body>
  <div class="login-box">
    <h2>Admin Login</h2>
    <?php if ($message) echo "<p style='color:red;'>$message</p>"; ?>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit">Sign In</button>
    </form>
    <div class="footer">Tournament Admin Panel</div>
  </div>
</body>

</html>