<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}
?>
<!DOCTYPE html>
<html>

<head>
  <title>Payment Successful</title>
  <style>
    body {
      font-family: Arial;
      background: #0f172a;
      color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .card {
      background: #111827;
      padding: 30px;
      border-radius: 10px;
      text-align: center;
      max-width: 400px;
    }

    .success {
      font-size: 50px;
      color: #22c55e;
    }

    a {
      color: #38bdf8;
      text-decoration: none;
    }
  </style>
</head>

<body>

  <div class="card">
    <div class="success">✔</div>
    <h2>Payment Successful</h2>
    <p>Your tournament has been created.</p>
    <p><strong>Status:</strong> Waiting for admin approval</p>
    <br>
    <a href="my-tournaments.php">Go to My Tournaments</a>
  </div>

</body>

</html>