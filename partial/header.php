<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <title>Game Tournament</title>
</head>

<body>

    <header class="legacy-header">
    <div class="legacy-logo">Tourna<span>X</span></div>
    <nav class="legacy-headnav">
        <a href="index.php">Home</a>
        <a href="tournament.php">Tournaments</a>
        <div class="help-container">
            <a>Help & Info <i class="fas fa-caret-down"></i></a>
            <div class="HelpInfo">
                <a href="aboutUs.php">About Us</a>
                <a href="FAQS.php">FAQs</a>
            </div>
        </div>
        <a href="#">Contact</a>
    </nav>

    <?php
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $sql = "select * from users where user_id=$uid";
        $result = mysqli_query($conn, $sql);
        $user = mysqli_fetch_assoc($result);
    ?>
        <a href=""><img src="images/<?= $user['profile_img'] ?>" alt="" class="profilegif"><?= $user['username'] ?></a>
        <a href="logout.php">LogOut</a>
    <?php } else { ?>
        <nav class="legacy-signnav">
            <a href="login.php">Login</a>
            <button class="btn-primary">Join Now</button>
        </nav>
    <?php } ?>
</header>


    <script>
        window.addEventListener("scroll", () => {
            const header = document.querySelector(".legacy-header");

            if (window.scrollY > 50) {
                header.classList.add("scrolled");
            } else {
                header.classList.remove("scrolled");
            }
        });
    </script>