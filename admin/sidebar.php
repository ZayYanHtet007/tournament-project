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
    
     <?php
    include("../database/dbConfig.php");
    session_start();
    ?>

<div class="wrapper">

    <div class="sidebar">
        <h2>Tournament Admin</h2>
        <a href="adminDashboard.php"><i class="fa fa-chart-line"></i> Dashboard</a>
        <a href="players.php"><i class="fa fa-users"></i> Players</a>
        <a href="tournaments.php"><i class="fa fa-trophy"></i> Tournaments</a>
        <a href="post.php"><i class="fa fa-pen-to-square"></i> Post</a>
        <a href="approveUserAccount.php"><i class="fa fa-user-check"></i> Approve Account User</a>
        <a href="notification.php"><i class="fa fa-bell"></i> Notification</a>
        <a href="message.php"><i class="fa fa-envelope"></i> Message</a>
        <a href="accountSetting.php"><i class="fa fa-cog"></i> Account Setting</a>
        <div class="admin_profile">
        <div class="admin_avatar">AD</div>
        <div class="info">
            <div class="name">Admin User</div>
            <div class="email">admin@gmail.com</div>
        </div>
    </div>
</div>

   

    <div class="main">

        <div class="header">
            <h3>Dashboard</h3>
            <div class="admin-name">
                <?= $_SESSION['admin_name'] ?? 'Admin' ?>
            </div>
        </div>

        <div class="main-content">
