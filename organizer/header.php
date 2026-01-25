<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TournaX | Home</title>
    <link rel="stylesheet" href="../css/organizer/organizer.css">
    <link rel="stylesheet" href="../css/organizer/tournaments.css">
    <link rel="stylesheet" href="../css/organizer/managetour.css">
    <link rel="stylesheet" href="../css/organizer/scheduletour.css">
    <link rel="stylesheet" href="../css/organizer/resulttour.css">
    <link rel="stylesheet" href="../css/user/responsive.css">
    <link rel="stylesheet" href="../css/organizer/brscore.css">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />

</head>

<body>

    <header class="legacy-header">
        <div class="legacy-logo">Tourna<span>X</span></div>

        <nav class="legacy-headnav">
            <a href="manageTournament.php">Tournament</a>
            <a href="#">Post</a>
            <a href="#">Report</a>
        </nav>

        <nav class="legacy-signnav">
            <a href="#">Profile</a>
            <a href="../logout.php">Logout</a>
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