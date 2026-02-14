<?php
// Logic must come before any HTML output to avoid "Headers already sent" error
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['submit'])) {
    $code = $_POST['resetcode'];
    $resetcode = $_SESSION['reset_code'] ?? '';

    if ($code == $resetcode) {
        header("Location: changePassword.php");
        exit();
    } else {
        $error = "INVALID VERIFICATION CODE";
    }
}

include('partial/header.php');
?>

<style>
    :root {
        --riot-red: #ff4655;
        --riot-white: #ece8e1;
        --riot-border: rgba(255, 255, 255, 0.15);
        --gaming-bg: #1a1d23;
    }

    .reset-wrapper {
        margin-left: 85px;
        /* Alignment with your side layout */
        margin-top: 75px;
        min-height: calc(100vh - 75px);
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        background-color: var(--gaming-bg);
        position: relative;
        overflow: hidden;
        /* Cyber Grid Pattern */
        background-image:
            radial-gradient(circle, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
            linear-gradient(to right, rgba(255, 255, 255, 0.02) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    /* Red Atmospheric Glow */
    .reset-wrapper::before {
        content: "";
        position: absolute;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(255, 70, 85, 0.12) 0%, transparent 70%);
        bottom: -100px;
        left: -100px;
        z-index: 0;
        animation: pulse-glow 8s ease-in-out infinite;
    }

    @keyframes pulse-glow {

        0%,
        100% {
            opacity: 0.3;
            transform: scale(1);
        }

        50% {
            opacity: 0.6;
            transform: scale(1.1);
        }
    }

    .reset-container {
        width: 100%;
        max-width: 400px;
        background: #111;
        border: 1px solid var(--riot-border);
        padding: 50px 40px;
        position: relative;
        z-index: 1;
        box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
    }

    /* Tactical Label */
    .reset-container::after {
        content: "IDENTITY // VERIFICATION";
        position: absolute;
        top: 0;
        right: 0;
        background: var(--riot-red);
        color: #000;
        font-family: 'Bebas Neue', sans-serif;
        padding: 2px 10px;
        font-size: 12px;
    }

    h2 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 2.5rem;
        line-height: 0.9;
        margin-bottom: 10px;
        letter-spacing: 1px;
        color: #fff;
        text-transform: uppercase;
    }

    .subtitle {
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        text-transform: uppercase;
        margin-bottom: 30px;
        letter-spacing: 1px;
    }

    .field {
        margin-bottom: 25px;
    }

    label {
        display: block;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 8px;
        color: var(--riot-red);
        letter-spacing: 1.5px;
    }

    input[type="text"] {
        width: 100%;
        padding: 15px;
        background: #1a1a1a;
        border: 1px solid transparent;
        border-bottom: 2px solid var(--riot-border);
        color: #fff;
        font-size: 18px;
        letter-spacing: 4px;
        text-align: center;
        transition: 0.3s;
    }

    input[type="text"]:focus {
        outline: none;
        background: #222;
        border-bottom-color: var(--riot-red);
    }

    .submit-btn {
        width: 100%;
        padding: 18px;
        background: transparent;
        color: #fff;
        border: 1px solid var(--riot-red);
        font-family: 'Bebas Neue', sans-serif;
        font-size: 1.4rem;
        letter-spacing: 2px;
        cursor: pointer;
        position: relative;
        z-index: 1;
        transition: 0.3s;
    }

    .submit-btn::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 0;
        height: 100%;
        background: var(--riot-red);
        transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: -1;
    }

    .submit-btn:hover {
        color: #000;
    }

    .submit-btn:hover::before {
        width: 100%;
    }

    .err-msg {
        background: rgba(255, 70, 85, 0.1);
        color: var(--riot-red);
        padding: 10px;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 20px;
        text-align: center;
        text-transform: uppercase;
        border-left: 3px solid var(--riot-red);
    }
</style>

<div class="reset-wrapper">
    <div class="reset-container">
        <h2>Verify Access</h2>
        <p class="subtitle">Enter the 6-digit code sent to your email.</p>

        <?php if (isset($error)): ?>
            <p class="err-msg"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <div class="field">
                <label>Access Token</label>
                <input type="text" name="resetcode" placeholder="000000" maxlength="6" autocomplete="off" required>
            </div>

            <button type="submit" name="submit" class="submit-btn">AUTHORIZE ACCESS</button>
        </form>
    </div>
</div>

<?php include('partial/footer.php'); ?>