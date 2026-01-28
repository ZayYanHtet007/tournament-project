<?php
include('partial/header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us — TournaX</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Bebas+Neue&display=swap');

        :root {
            --riot-red: #ff4655;
            --dark-bg: #0b0e14;
            --card-bg: #11141d;
        }

        body {
            margin: 0;
            background: var(--dark-bg);
            color: #fff;
            font-family: 'Rajdhani', sans-serif;
            overflow-x: hidden;
        }

        /* SCANLINE OVERLAY */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(
                to bottom,
                rgba(255,255,255,0.03),
                rgba(255,255,255,0.03) 1px,
                transparent 1px,
                transparent 3px
            );
            pointer-events: none;
            z-index: 999;
        }

        /* CONTAINER */
        .AboutContainer {
            max-width: 1200px;
            margin: auto;
            padding: 80px 20px;
        }

        /* HERO SECTION */
        .AboutHero,
        .AboutHero2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 60px;
            align-items: center;
        }

        .AboutHero-text h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(40px, 6vw, 64px);
            letter-spacing: 4px;
            color: var(--riot-red);
            margin-bottom: 15px;
            text-shadow: 0 0 25px rgba(255,70,85,0.5);
        }

        .AboutP {
            opacity: 0.7;
            letter-spacing: 1px;
            margin-bottom: 30px;
        }

        .About-h3 {
            font-size: 14px;
            letter-spacing: 3px;
            color: #aaa;
            text-transform: uppercase;
        }

        .About-h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 48px;
            letter-spacing: 3px;
            margin: 10px 0;
        }

        .description {
            opacity: 0.8;
            max-width: 500px;
        }

        /* IMAGES */
        .AboutHero-image img,
        .AboutHero-image2 img {
            width: 100%;
            filter: drop-shadow(0 0 40px rgba(255,70,85,0.4));
            animation: float 4s ease-in-out infinite;
        }

        @keyframes float {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* CARDS */
        .Aboutcards-column {
            display: grid;
            gap: 25px;
        }

        .Aboutcard {
            background: linear-gradient(160deg, #15192a, #0b0e14);
            border-left: 4px solid var(--riot-red);
            padding: 30px;
            position: relative;
            overflow: hidden;
            transition: 0.4s cubic-bezier(.2,.8,.2,1);
        }

        .Aboutcard::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                120deg,
                transparent,
                rgba(255,70,85,0.35),
                transparent
            );
            transform: translateX(-120%);
        }

        .Aboutcard:hover::before {
            animation: sweep 1.2s linear;
        }

        @keyframes sweep {
            to { transform: translateX(120%); }
        }

        .Aboutcard:hover {
            transform: translateY(-8px);
            box-shadow: 0 0 35px rgba(255,70,85,0.45);
        }

        .Aboutcard h3 {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 2px;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .Aboutcard p {
            opacity: 0.75;
        }

        .icon-mission,
        .icon-vision,
        .icon-values {
            color: var(--riot-red);
            margin-right: 10px;
            text-shadow: 0 0 15px var(--riot-red);
        }

        /* MOBILE */
        @media (max-width: 600px) {
            .AboutContainer {
                padding: 60px 15px;
            }

            .AboutHero,
            .AboutHero2 {
                gap: 40px;
            }
        }
    </style>
</head>

<body>

<div class="AboutContainer">
    <section class="AboutHero">
        <div class="AboutHero-text">
            <h1>About Us</h1>
            <p class="AboutP">
                We organize esports and board game tournaments for players, organizers, and admins
            </p>

            <h3 class="About-h3">Who We Are</h3>
            <h2 class="About-h2">TournaX</h2>

            <p class="description">
                TournaX is a tournament organizer platform built to simplify competition management.
                From small local events to large competitive tournaments, we help teams and players
                compete fairly.
            </p>
        </div>

        <div class="AboutHero-image">
            <img src="images/esport.png" alt="TournaX Team">
        </div>
    </section>
</div>

<div class="AboutContainer">
    <section class="AboutHero2">
        <div class="Aboutcards-column">
            <div class="Aboutcard">
                <h3><span class="icon-mission">★</span> Our Mission</h3>
                <p>To provide a simple, powerful, and fair tournament management system.</p>
            </div>

            <div class="Aboutcard">
                <h3><span class="icon-vision">◉</span> Our Vision</h3>
                <p>To become the leading competitive platform for all games worldwide.</p>
            </div>

            <div class="Aboutcard">
                <h3><span class="icon-values">♥</span> Our Values</h3>
                <p>Fair play, transparency, and community-driven growth.</p>
            </div>
        </div>

        <div class="AboutHero-image2">
            <img src="images/ValorantViper.png" alt="TournaX Values">
        </div>
    </section>
</div>

</body>
</html>

<?php
include('partial/footer.php');
?>
