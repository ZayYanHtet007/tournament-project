<?php include('partial/header.php'); ?>

<style>
    :root {
        /* Lighter, more vibrant red for lighting */
        --primary-red: #ff4d5a; 
        --glow-red: rgba(255, 77, 90, 0.4);
        --dark-bg: #050505;
        --card-bg: rgba(10, 10, 10, 0.9);
        --sidebar-w: 80px;
    }

    body { 
        background-color: var(--dark-bg) !important;
        margin: 0;
        overflow-x: hidden;
        position: relative;
        color: #ffffff; 
        font-family: 'Segoe UI', Helvetica, Arial, sans-serif; 
        
        /* INTENSE RED GRADIENT BACKGROUND */
        background-image: 
            radial-gradient(circle at 10% 20%, rgba(255, 77, 90, 0.15) 0%, transparent 40%),
            radial-gradient(circle at 90% 80%, rgba(255, 77, 90, 0.15) 0%, transparent 40%),
            linear-gradient(rgba(255, 77, 90, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 77, 90, 0.05) 1px, transparent 1px);
        background-size: 100% 100%, 100% 100%, 30px 30px, 30px 30px;
        background-attachment: fixed;
    }

    /* ENHANCED DYNAMIC RED GLOW */
    body::before {
        content: "";
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                    rgba(255, 77, 90, 0.25) 0%, 
                    transparent 45%);
        z-index: -1;
        pointer-events: none;
    }

    .page { 
        min-height: 100vh;
        padding: 80px 20px; 
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin-left: var(--sidebar-w);
    }

    .contact-container {
        background: var(--card-bg);
        backdrop-filter: blur(15px);
        padding: 50px 40px;
        border: 1px solid rgba(255, 77, 90, 0.3);
        width: 100%;
        max-width: 480px;
        box-shadow: 0 0 40px rgba(255, 77, 90, 0.1), inset 0 0 20px rgba(255, 77, 90, 0.05);
        position: relative;
    }

    /* Glow bar at top */
    .contact-container::before {
        content: '';
        position: absolute;
        top: -1px; left: 0; width: 100%; height: 3px;
        background: linear-gradient(90deg, transparent, var(--primary-red), transparent);
    }

    h1 { 
        color: #fff; 
        text-transform: uppercase;
        font-size: 2.8rem;
        letter-spacing: 2px;
        margin-bottom: 0;
        font-weight: 800;
        text-shadow: 0 0 20px var(--glow-red);
    }

    .subtitle {
        color: #aaaaaa;
        margin-bottom: 40px;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 4px;
        font-weight: 400;
    }

    .form-group {
        margin-bottom: 25px;
    }

    label {
        display: block;
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--primary-red);
        margin-bottom: 10px;
        letter-spacing: 1.5px;
    }

    input, textarea { 
        width: 100%; 
        padding: 16px; 
        background: rgba(255, 255, 255, 0.03); 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        color: #fff; 
        box-sizing: border-box;
        transition: 0.3s;
        outline: none;
        font-size: 15px;
    }

    input:focus, textarea:focus {
        border-color: var(--primary-red);
        background: rgba(255, 77, 90, 0.05);
        box-shadow: 0 0 15px rgba(255, 77, 90, 0.1);
    }

    textarea {
        height: 140px;
        resize: none;
    }

    button { 
        background: var(--primary-red); 
        color: #fff;
        padding: 18px; 
        border: none; 
        width: 100%; 
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.4s;
        letter-spacing: 2px;
        margin-top: 10px;
        box-shadow: 0 4px 15px rgba(255, 77, 90, 0.3);
    }

    button:hover { 
        background: #fff;
        color: var(--primary-red);
        box-shadow: 0 0 30px rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
    }

    @media (max-width: 850px) {
        .page { margin-left: 0; }
        h1 { font-size: 2.2rem; }
    }
</style>

<section class="page">
    <div style="text-align: center;">
        <h1>CONTACT US</h1>
        <p class="subtitle">Direct Support Line</p>
    </div>

    <div class="contact-container">
        <form action="#" method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" placeholder="Enter your name" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label>Your Message</label>
                <textarea placeholder="How can we help you?"></textarea>
            </div>

            <button type="submit">Send Message</button>
        </form>
    </div>
</section>

<script>
    window.addEventListener('mousemove', e => {
        const x = (e.clientX / window.innerWidth) * 100;
        const y = (e.clientY / window.innerHeight) * 100;
        document.body.style.setProperty('--mouse-x', x + '%');
        document.body.style.setProperty('--mouse-y', y + '%');
    });
</script>