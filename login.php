<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "database/dbConfig.php";

// PHPMailer requirements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

/* -------------------------------------------------------------------------- */
/* LOGIC 1: LOGIN HANDLER                                                     */
/* -------------------------------------------------------------------------- */
if (isset($_POST['btnlogin'])) {
    $email = trim($_POST['txtemail']);
    $password = $_POST['txtpwd'];

    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        if (!password_verify($password, $user['password'])) {
            $_SESSION['error'] = "Invalid email or password";
            header("Location: login.php");
            exit;
        }

        if ((int)$user['is_organizer'] === 1) {
            $status = strtolower(trim($user['organizer_status']));
            if ($status !== 'approved') {
                $_SESSION['error'] = "Organizer account not approved";
                header("Location: login.php");
                exit;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_organizer'] = 1;
            header("Location: organizer/organizerDashboard.php");
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_organizer'] = 0;
        header("Location: index.php");
        exit;
    }
    $_SESSION['error'] = "Invalid email or password";
    header("Location: login.php");
    exit;
}

/* -------------------------------------------------------------------------- */
/* LOGIC 2: FORGOT PASSWORD HANDLER                                           */
/* -------------------------------------------------------------------------- */
if (isset($_POST['btnForgot'])) {
    $email = $_POST['email'];
    $code = rand(100000, 999999);
    $_SESSION['reset_email'] = $email;
    $_SESSION['resetcode'] = $code;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'theintnandarsoe16@gmail.com';
        $mail->Password = 'cqmx tiwi oqoe rpyr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('myattheingikyaw200234@gmail.com', 'Theint');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your Password Reset Code";
        $mail->Body = "<h2>Password Reset Request</h2><p>Your verification code is:</p><h1>$code</h1><p>Enter this code in the reset page.</p>";

        $mail->send();
        header("Location: verifyCode.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Mail Error: " . $mail->ErrorInfo;
        header("Location: login.php");
        exit;
    }
}

include('partial/header.php'); 
?>

<script src="https://cdn.lordicon.com/lordicon.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-red: #ff3344;
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
        background-size: 50px 50px;
        background-attachment: fixed;
    }

    /* INTERACTIVE MOUSE GLOW */
    body::before {
        content: "";
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                    rgba(255, 51, 68, 0.12) 0%, 
                    transparent 50%);
        z-index: -1;
        pointer-events: none;
    }

    /* GLOBAL ANIMATED SCANLINE */
    body::after {
        content: "";
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: linear-gradient(
            to bottom,
            transparent 0%,
            rgba(255, 51, 68, 0.03) 50%,
            transparent 100%
        );
        background-size: 100% 4px;
        animation: globalScanline 10s linear infinite;
        pointer-events: none;
        z-index: 0;
    }

    @keyframes globalScanline {
        0% { transform: translateY(-100%); }
        100% { transform: translateY(100%); }
    }

    .login-container-wrapper {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        z-index: 1;
    }

    .card-stack {
        position: relative;
        width: 100%;
        max-width: 420px;
        min-height: 550px;
        perspective: 1000px;
    }

    .login-panel {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
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

    #login-panel {
        z-index: 2;
        transform: rotateY(0deg);
        opacity: 1;
    }

    #forgot-panel {
        z-index: 1;
        transform: rotateY(180deg);
        opacity: 0;
        pointer-events: none;
    }

    .card-stack.show-forgot #login-panel {
        transform: rotateY(-180deg);
        opacity: 0;
        pointer-events: none;
    }

    .card-stack.show-forgot #forgot-panel {
        transform: rotateY(0deg);
        opacity: 1;
        z-index: 3;
        pointer-events: auto;
    }

    .login-panel h2 {
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

    .form-group-custom { margin-bottom: 25px; }
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

    .login-helper-links {
        margin-top: 25px;
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        text-transform: uppercase;
    }

    .login-helper-links a, .back-to-login { 
        color: #666; 
        text-decoration: none; 
        cursor: pointer;
        transition: 0.2s; 
    }
    .login-helper-links a:hover, .back-to-login:hover { color: var(--primary-red); }

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

    .back-to-login {
        display: block;
        text-align: center;
        margin-top: 20px;
        font-size: 11px;
        font-weight: bold;
    }
</style>

<main class="login-container-wrapper">
    <div class="card-stack" id="cardStack">
        
        <div class="login-panel" id="login-panel">
            <h2>LOGIN TO <span style="color: var(--primary-red);">TX</span></h2>
            <p class="subtitle">Global Esports Network</p>

            <?php if (!empty($error)) : ?>
                <div class="error-notice"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group-custom">
                    <label>E-Mail Access</label>
                    <input type="email" name="txtemail" placeholder="TOURNX@gmail.com" required>
                </div>
                <div class="form-group-custom">
                    <label>Password</label>
                    <input type="password" name="txtpwd" placeholder="••••••••" required>
                </div>
                <button type="submit" name="btnlogin" class="btn-red-action">Login</button>
                <div class="login-helper-links">
                    <a href="signup.php">Register New Acc</a>
                    <a onclick="toggleCard(true)">Forgot Password?</a>
                </div>
            </form>
        </div>

        <div class="login-panel" id="forgot-panel">
            <h2>RESET <span style="color: var(--primary-red);">Password</span></h2>
            <p class="subtitle">Recover your account access</p>

            <form method="POST">
                <div class="form-group-custom">
                    <label>Registered E-Mail</label>
                    <input type="email" name="email" placeholder="ENTER YOUR EMAIL" required>
                </div>
                <button type="submit" name="btnForgot" class="btn-red-action">Send Reset Code</button>
                <a class="back-to-login" onclick="toggleCard(false)">
                    BACK TO LOGIN
                </a>
            </form>
        </div>

    </div>
</main>

<script>
    // MOUSE GLOW TRACKING
    window.addEventListener('mousemove', e => {
        const x = (e.clientX / window.innerWidth) * 100;
        const y = (e.clientY / window.innerHeight) * 100;
        document.body.style.setProperty('--mouse-x', x + '%');
        document.body.style.setProperty('--mouse-y', y + '%');
    });

    function toggleCard(showForgot) {
        const stack = document.getElementById('cardStack');
        if (showForgot) {
            stack.classList.add('show-forgot');
        } else {
            stack.classList.remove('show-forgot');
        }
    }
</script>

<?php include('partial/footer.php'); ?>