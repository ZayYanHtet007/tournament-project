<?php
include 'partial/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament FAQ | Pro League Gaming</title>
    <style>
        :root {
            --primary-red: #ff4655;
            --dark-red: #8b0000;
            --bg-black: #0f0f0f;
            --card-gray: #1a1a1a;
            --text-white: #eeeeee;
            --text-dim: #999999;
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-black);
            color: var(--text-white);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 60px;
        }

        .header-section h1 {
            font-size: 3.5rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 10px;
        }

        .header-section h1 span {
            color: var(--primary-red);
        }

        .header-section p {
            color: var(--text-dim);
            font-size: 1.1rem;
        }

        h2.category-title {
            color: var(--primary-red);
            text-transform: uppercase;
            font-size: 1.2rem;
            border-left: 4px solid var(--primary-red);
            padding-left: 15px;
            margin: 40px 0 20px 0;
            letter-spacing: 1px;
        }

        /* FAQ Accordion Styling */
        .faq-item {
            background: var(--card-gray);
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid #222;
            overflow: hidden;
            transition: 0.3s;
        }

        .faq-item:hover {
            border-color: var(--primary-red);
        }

        .faq-question {
            padding: 20px;
            cursor: pointer;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, #1a1a1a 0%, #222 100%);
            text-transform: uppercase;
            font-size: 0.95rem;
        }

        .faq-question span {
            color: var(--primary-red);
            font-size: 1.5rem;
            transition: 0.3s;
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            background: #111;
            color: var(--text-dim);
            font-size: 0.95rem;
        }

        /* Toggle State */
        .faq-item.active .faq-answer {
            max-height: 300px; /* Adjust as needed */
            padding: 20px;
            border-top: 1px solid #222;
        }

        .faq-item.active .faq-question span {
            transform: rotate(45deg);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <h1>TournaX <span>Help Center</span></h1>
        <p>Everything you need to know before you drop in.</p>
    </div>

    <h2 class="category-title">01. Deployment & Eligibility</h2>
    
    <div class="faq-item">
        <div class="faq-question">How do I register my squad? <span>+</span></div>
        <div class="faq-answer">
            Navigate to the 'Events' dashboard, select the tournament, and input your teammates' Game IDs. All players must accept the invite in their dashboard to finalize the roster.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">Is there an age restriction? <span>+</span></div>
        <div class="faq-answer">
            Players must be 16 years or older to participate. Players under 18 must provide digital parental consent during the registration process.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">Can I change my Game ID after registering? <span>+</span></div>
        <div class="faq-answer">
            No. Your Game ID must match the account used during the match. Using a different account will result in an automatic forfeit.
        </div>
    </div>

    <h2 class="category-title">02. Combat Rules</h2>

    <div class="faq-item">
        <div class="faq-question">How are map vetoes handled? <span>+</span></div>
        <div class="faq-answer">
            Maps are decided via a 'Ban-Ban-Pick' system on the match page. The higher-seeded team chooses to ban first or second.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">What happens if a player disconnects? <span>+</span></div>
        <div class="faq-answer">
            Matches can be paused for a maximum of 5 minutes for technical issues. If a player cannot reconnect, the team must play shorthanded or forfeit.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">Are cosmetic skins or mods allowed? <span>+</span></div>
        <div class="faq-answer">
            In-game skins are permitted unless they provide a known visual exploit. Any third-party mods, shaders, or scripts are strictly prohibited.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">What is the policy on 'Stream Sniping'? <span>+</span></div>
        <div class="faq-answer">
            Stream sniping is a bannable offense. We recommend a minimum 3-minute delay for all players broadcasting their perspective.
        </div>
    </div>

    <h2 class="category-title">03. Verification & Rewards</h2>

    <div class="faq-item">
        <div class="faq-question">How do I submit match results? <span>+</span></div>
        <div class="faq-answer">
            The winning captain must upload a clear screenshot of the final score screen to the 'Match Details' section within 15 minutes of completion.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">What constitutes a 'Dispute'? <span>+</span></div>
        <div class="faq-answer">
            Disputes can be raised for: Incorrect score reporting, use of banned items, suspected cheating, or toxic behavior. Evidence (video/screenshot) is mandatory.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">How long do prize payouts take? <span>+</span></div>
        <div class="faq-answer">
            Once the tournament ends, a 24-hour audit is performed. Payouts are issued via your chosen method within 3 to 5 business days.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">Can I use a substitute player? <span>+</span></div>
        <div class="faq-answer">
            You may use one registered substitute per tournament. The substitute must be listed on your roster before the tournament start time.
        </div>
    </div>

    <div class="faq-item">
        <div class="faq-question">What if the game server crashes? <span>+</span></div>
        <div class="faq-answer">
            If a server-wide crash occurs, the match will be replayed from the start unless a clear winner was already mathematically determined.
        </div>
    </div>
</div>

<script>
    // FAQ Accordion Logic
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        item.querySelector('.faq-question').addEventListener('click', () => {
            // Optional: Close other items when one is opened
            faqItems.forEach(otherItem => {
                if (otherItem !== item) otherItem.classList.remove('active');
            });
            
            item.classList.toggle('active');
        });
    });
</script>

<?php
include 'partial/footer.php';
?>