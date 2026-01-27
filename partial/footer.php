<?php
/**
 * Master Footer Component
 * Style: Riot Games / Valorant Industrial
 */
?>
</div>
<style>
    /* ================= RIOT FOOTER STYLE ================= */
    .site-footer {
        background: #000;
        color: #fff;
        padding: 80px 40px 40px calc(var(--sidebar-w) + 40px); /* Offsets for sidebar */
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        position: relative;
        z-index: 10;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 60px;
    }

    .footer-col.about .footer-logo {
        height: 50px;
        margin-bottom: 25px;
        filter: grayscale(1) brightness(2);
        transition: 0.3s;
    }

    .footer-col.about .footer-logo:hover {
        filter: grayscale(0) drop-shadow(0 0 10px var(--riot));
    }

    .footer-col p {
        color: #7e7e7e;
        line-height: 1.6;
        font-size: 14px;
        max-width: 400px;
    }

    .footer-col h4 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 22px;
        letter-spacing: 2px;
        margin-bottom: 25px;
        color: #fff;
        text-transform: uppercase;
    }

    .footer-col ul {
        list-style: none;
        padding: 0;
    }

    .footer-col ul li {
        margin-bottom: 12px;
    }

    .footer-col ul li a {
        color: #7e7e7e;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s;
        text-transform: uppercase;
    }

    .footer-col ul li a:hover {
        color: var(--riot);
        padding-left: 5px;
    }

    /* Social Icons with Lordicon */
    .social-links {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .social-btn {
        width: 45px;
        height: 45px;
        background: rgba(255, 255, 255, 0.03);
        display: grid;
        place-items: center;
        border-radius: 4px;
        transition: 0.3s;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .social-btn:hover {
        background: var(--riot);
        transform: translateY(-5px);
    }

    /* Footer Bottom Section */
    .footer-bottom {
        margin-top: 80px;
        padding-top: 30px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .footer-bottom p {
        font-size: 11px;
        color: #444;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Scroll Progress Bar (Vertical Right Side) */
    .progress-container {
        position: fixed;
        right: 0;
        top: 0;
        width: 3px;
        height: 100%;
        background: rgba(255, 255, 255, 0.05);
        z-index: 9999;
    }

    .progress-bar {
        width: 100%;
        background: var(--riot);
        height: 0%;
        box-shadow: 0 0 10px var(--riot);
    }

    @media (max-width: 768px) {
        .footer-container { grid-template-columns: 1fr; gap: 40px; }
        .site-footer { padding-left: 40px; }
    }
</style>

<div class="progress-container">
    <div class="progress-bar" id="bar"></div>
</div>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-col about">
            <img src="images/TX.png" class="footer-logo" alt="TournaX">
            <p>The ultimate arena for competitive gaming. Join daily tournaments, climb the global leaderboard, and win legendary prizes in the most secure environment.</p>
            
            <div class="social-links">
                <a href="#" class="social-btn">
                    <lord-icon src="https://cdn.lordicon.com/hpivxauj.json" trigger="hover" colors="primary:#ffffff" style="width:24px;height:24px"></lord-icon>
                </a>
                <a href="#" class="social-btn">
                    <lord-icon src="https://cdn.lordicon.com/iqaguvqv.json" trigger="hover" colors="primary:#ffffff" style="width:24px;height:24px"></lord-icon>
                </a>
                <a href="#" class="social-btn">
                    <lord-icon src="https://cdn.lordicon.com/khheayfj.json" trigger="hover" colors="primary:#ffffff" style="width:24px;height:24px"></lord-icon>
                </a>
            </div>
        </div>

        <div class="footer-col links">
            <h4>Tournament</h4>
            <ul>
                <li><a href="tournaments.php">Active Events</a></li>
                <li><a href="brackets.php">Brackets</a></li>
                <li><a href="rules.php">Official Rules</a></li>
                <li><a href="leaderboard.php">Rankings</a></li>
            </ul>
        </div>

        <div class="footer-col links">
            <h4>Support</h4>
            <ul>
                <li><a href="faq.php">Help Center</a></li>
                <li><a href="contact.php">Contact Us</a></li>
                <li><a href="privacy.php">Privacy Policy</a></li>
                <li><a href="terms.php">Terms of Service</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> TOURNAX INTERACTIVE. ALL RIGHTS RESERVED.</p>
        <p style="color: #7e7e7e;">MADE BY AGENTS FOR AGENTS</p>
    </div>
</footer>

<script src="https://cdn.lordicon.com/lordicon.js"></script>
<script src="assets/js/main.js"></script>

<script>
/**
 * Riot Style Scroll Progress Logic
 */
window.addEventListener("scroll", function() {
    const progressBar = document.getElementById("bar");
    if (progressBar) {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const documentHeight = document.documentElement.scrollHeight - window.innerHeight;
        // Avoid division by zero
        if (documentHeight > 0) {
            const scrollPercent = (scrollTop / documentHeight) * 100;
            progressBar.style.height = scrollPercent + "%";
        }
    }
});

// Subtle Footer Reveal Animation
const footerObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = "1";
            entry.target.style.transform = "translateY(0)";
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.footer-col').forEach(col => {
    col.style.opacity = "0";
    col.style.transform = "translateY(20px)";
    col.style.transition = "all 0.6s ease-out";
    footerObserver.observe(col);
});
</script>

</body>
</html>