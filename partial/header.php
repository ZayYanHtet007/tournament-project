<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <title>Game Tournament</title>
</head>

<body>

    <header class="legacy-header">
        <div class="legacy-logo">TournaX</div>
        <nav class="legacy-headnav">
            <a href="index.php">Home</a>
            <a href="tournament.php">Tournaments</a>
            <a href="aboutUs.php">About Us</a>
            <a href="#">Contact</a>
        </nav>
        <nav class="legacy-signnav">
            <a href="login.php">Login</a>
            <button class="btn-primary">Join Now</button>
        </nav>
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