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

<!-- Fonts & Icon libraries (same as login.php) -->
<script src="https://cdn.lordicon.com/lordicon.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-red: #ff4d5a;
        --dark-red: #4a0a0a;
        --deep-black: #050505;
        --riot-dark: #080a0c;
        --panel-bg: #0f0f0f;
    }

    body {
        background-color: var(--riot-dark) !important;
        margin: 0;
        overflow-x: hidden;
        position: relative;
        /* CYBER GRID BACKGROUND */
        background-image:
            linear-gradient(rgba(255, 50, 50, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 50, 50, 0.05) 1px, transparent 1px);
        
        background-attachment: fixed;
    }

    /* INTENSIFIED RED ATMOSPHERIC LIGHTING */
    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background:
            radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
                rgba(255, 51, 68, 0.25) 0%,
                transparent 60%),
            radial-gradient(circle at 10% 10%, rgba(255, 0, 0, 0.15) 0%, transparent 40%),
            radial-gradient(circle at 90% 90%, rgba(255, 0, 0, 0.15) 0%, transparent 40%);
        z-index: -1;
        pointer-events: none;
    }

    /* GLOBAL ANIMATED SCANLINE */
    body::after {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: linear-gradient(to bottom,
                transparent 0%,
                rgba(255, 51, 68, 0.03) 50%,
                transparent 100%);
        background-size: 100% 4px;
        animation: globalScanline 10s linear infinite;
        pointer-events: none;
        z-index: 0;
    }

    @keyframes globalScanline {
        0% {
            transform: translateY(-100%);
        }

        100% {
            transform: translateY(100%);
        }
    }

    .verify-container-wrapper {
        min-height: 88vh;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        z-index: 1;
        margin-left: 85px;  /* match sidebar width */
        margin-top: 25px;   /* match header height */
        box-sizing: border-box;
    }

    .verify-panel {
        width: 100%;
        max-width: 420px;
        background: rgba(10, 10, 10, 0.95);
        backdrop-filter: blur(10px);
        padding: 50px 40px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-top: 4px solid var(--primary-red);
        border-bottom: 4px solid var(--primary-red);
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        backface-visibility: hidden;
        box-sizing: border-box;
    }

    .verify-panel h2 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 42px;
        color: #fff;
        text-align: center;
        margin-bottom: 5px;
        letter-spacing: 3px;
    }

    .subtitle {
        color: #888;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: bold;
        text-align: center;
        margin-bottom: 40px;
        letter-spacing: 2px;
        border-bottom: 1px solid #333;
        padding-bottom: 15px;
    }

    .form-group-custom {
        margin-bottom: 25px;
    }

    .form-group-custom label {
        display: block;
        color: var(--primary-red);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .form-group-custom input {
        width: 100%;
        padding: 15px;
        background: #0a0a0a;
        border: 1px solid #333;
        color: #fff;
        transition: 0.3s;
        box-sizing: border-box;
        font-size: 18px;
        letter-spacing: 4px;
        text-align: center;
    }

    .form-group-custom input:focus {
        border-color: var(--primary-red);
        outline: none;
        background: #110505;
        box-shadow: 0 0 10px rgba(255, 51, 68, 0.1);
    }

    .btn-red-action {
        width: 100%;
        padding: 18px;
        background: var(--primary-red);
        color: #fff;
        border: none;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 24px;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        letter-spacing: 2px;
    }

    .btn-red-action:hover {
        filter: brightness(1.2);
        box-shadow: 0 0 20px rgba(255, 51, 68, 0.4);
    }

    .error-notice {
        background: rgba(255, 51, 68, 0.1);
        color: var(--primary-red);
        padding: 15px;
        border-left: 3px solid var(--primary-red);
        margin-bottom: 25px;
        font-size: 12px;
        text-transform: uppercase;
        text-align: center;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .verify-container-wrapper {
            margin-left: 0;
            margin-top: 70px;
        }
    }
</style>

<main class="verify-container-wrapper">
    <div class="verify-panel">
        <h2>VERIFY <span style="color: var(--primary-red);">ACCESS</span></h2>
        <p class="subtitle">Enter the 6‑digit code</p>

        <?php if (isset($error)): ?>
            <div class="error-notice"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group-custom">
                <label>VERIFICATION CODE</label>
                <input type="text" name="resetcode" placeholder="000000" maxlength="6" autocomplete="off" required>
            </div>

            <button type="submit" name="submit" class="btn-red-action">AUTHORIZE</button>
        </form>
    </div>
</main>

<script>
    // MOUSE GLOW TRACKING (same as login.php)
    window.addEventListener('mousemove', e => {
        const x = (e.clientX / window.innerWidth) * 100;
        const y = (e.clientY / window.innerHeight) * 100;
        document.body.style.setProperty('--mouse-x', x + '%');
        document.body.style.setProperty('--mouse-y', y + '%');
    });
</script>