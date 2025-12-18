<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

<div class="wrapper">

    <div class="sidebar">
        <h2>Tournament Admin</h2>
        <a href="index.php"><i class="fa fa-chart-line"></i> Dashboard</a>
        <a href="tournaments.php"><i class="fa fa-trophy"></i> Tournaments</a>
        <a href="players.php"><i class="fa fa-users"></i> Players</a>
        <a href="matches.php"><i class="fa fa-gamepad"></i> Matches</a>
        <a href="results.php"><i class="fa fa-medal"></i> Results</a>
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main">

        <div class="header">
            <h3>Dashboard</h3>
            <div class="admin-name">
                <?= $_SESSION['admin_name'] ?? 'Admin' ?>
            </div>
        </div>

        <div class="main-content container-fluid mt-4">
