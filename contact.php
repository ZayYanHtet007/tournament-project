<?php include('partial/header.php'); ?>

<style>
    :root {
        --primary-red: #ff4655;
        --dark-bg: #0f1923;
        --card-bg: rgba(23, 27, 34, 0.95);
        --input-bg: #1b2733;
    }

    body { 
        background: var(--dark-bg);
        /* Replace the URL below with your actual gaming wallpaper */
        background-image: linear-gradient(rgba(15, 25, 35, 0.85), rgba(15, 25, 35, 0.85)), 
                          url('images/anonymous.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        color: #ece8e1; 
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
        margin: 0;
    }

    .page { 
        padding: 100px 8%; 
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .contact-container {
        background: var(--card-bg);
        padding: 40px;
        border-left: 4px solid var(--primary-red);
        width: 100%;
        max-width: 550px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        clip-path: polygon(0 0, 100% 0, 100% 95%, 95% 100%, 0 100%); /* Modern angled corner */
    }

    h1 { 
        color: var(--primary-red); 
        text-transform: uppercase;
        font-size: 3rem;
        letter-spacing: 2px;
        margin-bottom: 10px;
        font-style: italic;
    }

    p.subtitle {
        color: #8b978f;
        margin-bottom: 30px;
        text-transform: uppercase;
        font-size: 0.9rem;
        letter-spacing: 1px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: bold;
        color: var(--primary-red);
        margin-bottom: 5px;
    }

    input, textarea { 
        width: 100%; 
        padding: 15px; 
        background: var(--input-bg); 
        border: 1px solid #333; 
        color: #fff; 
        box-sizing: border-box;
        transition: 0.3s;
        outline: none;
    }

    input:focus, textarea:focus {
        border-color: var(--primary-red);
        background: #232e3a;
    }

    textarea {
        height: 150px;
        resize: none;
    }

    button { 
        background: var(--primary-red); 
        color: #fff;
        padding: 18px; 
        border: none; 
        width: 100%; 
        font-weight: 900; 
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.2s;
        position: relative;
        overflow: hidden;
    }

    button:hover { 
        background: #fff;
        color: var(--primary-red);
        transform: translateY(-2px);
    }

    button:active {
        transform: translateY(0);
    }
</style>

<section class="page">
    <h1>Contact Us</h1>
    <p class="subtitle">Secure Transmission // Global Operations</p>

    <div class="contact-container">
        <form action="#" method="POST">
            <div class="form-group">
                <label>Your Name</label>
                <input type="text" placeholder="ENTER NAME..." required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" placeholder="ENCRYPTED EMAIL..." required>
            </div>

            <div class="form-group">
                <label>Message</label>
                <textarea placeholder="TYPE YOUR MESSAGE HERE..."></textarea>
            </div>

            <button type="submit">Send</button>
        </form>
    </div>
</section>

<?php include('partial/footer.php'); ?>