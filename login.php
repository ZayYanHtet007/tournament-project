<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "database/dbConfig.php";

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

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

        /* ORGANIZER LOGIN */
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
            $_SESSION['organizer_status'] = $status;

            header("Location: organizer/organizerDashboard.php");
            exit;
        }

        /* PLAYER LOGIN */
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
// 1. INCLUDE YOUR ORIGINAL HEADER
include('partial/header.php'); 
?>

<script src="https://cdn.lordicon.com/lordicon.js"></script>

<style>
    /* ================= SIDEBAR & SHELL LAYOUT ================= */
    :root {
        --primary-red: #ff3344; /* Brighter Gaming Red */
        --dark-red: #4a0a0a;
        --deep-black: #050505;
        --panel-bg: #0f0f0f;
        --sidebar-w: 80px; 
    }

    /* THE SIDEBAR */
    .riot-sidebar {
        position: fixed;
        left: 0; top: 0; bottom: 0;
        width: var(--sidebar-w);
        background: #000;
        border-right: 1px solid rgba(255, 51, 68, 0.3);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 20px;
        z-index: 9999; 
    }

    .side-icon-btn {
        margin: 25px 0;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        opacity: 0.6;
        transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .side-icon-btn:hover {
        opacity: 1;
        transform: translateX(5px);
    }

    .side-icon-btn span {
        font-size: 9px;
        color: #fff;
        font-weight: 800;
        margin-top: 6px;
        letter-spacing: 1px;
    }

    /* ================= OFFSET FOR ORIGINAL ELEMENTS ================= */
    .legacy-header, 
    .site-footer, 
    .login-container-wrapper {
        margin-left: var(--sidebar-w) !important;
        width: calc(100% - var(--sidebar-w)) !important;
    }

    /* ================= LOGIN DESIGN (HEAVY RED & RESPONSIVE) ================= */
    .login-container-wrapper {
        min-height: 85vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #000;
        /* Gaming Grid Background */
        background-image: 
            linear-gradient(rgba(255, 50, 50, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 50, 50, 0.05) 1px, transparent 1px),
            radial-gradient(circle at center, rgba(100, 0, 0, 0.6) 0%, #000000 80%);
        background-size: 30px 30px, 30px 30px, 100% 100%;
        position: relative;
    }

    /* Vignette Overlay */
    .login-container-wrapper::after {
        content: "";
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        box-shadow: inset 0 0 150px rgba(0,0,0,0.9);
        pointer-events: none;
    }

    .login-panel {
        background: rgba(15, 15, 15, 0.95);
        width: 100%;
        max-width: 420px;
        padding: 50px 40px;
        border: 1px solid #333;
        border-top: 4px solid var(--primary-red);
        border-bottom: 4px solid var(--primary-red);
        box-shadow: 
            0 0 50px rgba(255, 51, 68, 0.15),
            inset 0 0 20px rgba(0,0,0,0.8);
        position: relative;
        z-index: 2;
    }

    .login-panel h2 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 42px;
        color: #fff;
        text-align: center;
        margin-bottom: 5px;
        letter-spacing: 3px;
        text-shadow: 0 0 10px rgba(255, 51, 68, 0.5);
    }

    .login-panel p.subtitle {
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
        letter-spacing: 1px;
    }

    .form-group-custom input {
        width: 100%;
        padding: 15px;
        background: #0a0a0a;
        border: 1px solid #333;
        color: #fff;
        font-weight: 600;
        transition: 0.3s;
        font-family: monospace;
        letter-spacing: 1px;
    }

    .form-group-custom input:focus {
        border-color: var(--primary-red);
        background: #110505; /* Slight red tint on focus */
        outline: none;
        box-shadow: 0 0 15px rgba(255, 51, 68, 0.2);
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
        box-shadow: 0 5px 15px rgba(255, 51, 68, 0.3);
    }

    .btn-red-action:hover {
        background: #ff5566;
        box-shadow: 0 0 25px rgba(255, 51, 68, 0.6);
        transform: translateY(-2px);
    }

    .login-helper-links {
        margin-top: 25px;
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .login-helper-links a { color: #666; text-decoration: none; transition: 0.2s; }
    .login-helper-links a:hover { color: var(--primary-red); }

    .error-notice {
        background: rgba(255, 51, 68, 0.1);
        color: var(--primary-red);
        padding: 15px;
        border: 1px solid var(--primary-red);
        margin-bottom: 25px;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        box-shadow: 0 0 10px rgba(255, 51, 68, 0.2);
    }

    /* ================= RESPONSIVE MEDIA QUERIES ================= */
    @media (max-width: 768px) {
        /* Hide sidebar on mobile to save space */
        .riot-sidebar {
            display: none;
        }

        /* Reset margins since sidebar is gone */
        .legacy-header, 
        .site-footer, 
        .login-container-wrapper {
            margin-left: 0 !important;
            width: 100% !important;
        }

        /* Adjust Login Panel for smaller screens */
        .login-panel {
            margin: 20px;
            padding: 40px 25px;
            max-width: 100%;
            border-left: none;
            border-right: none;
        }

        .login-panel h2 {
            font-size: 32px;
        }

        .btn-red-action {
            font-size: 20px;
            padding: 15px;
        }
    }
</style>

<main class="login-container-wrapper">
    <div class="login-panel">
        <h2>LOGIN TO <span style="color: var(--primary-red);">TX</span></h2>
        <p class="subtitle">Global Esports Network</p>

        <?php if (!empty($error)) : ?>
            <div class="error-notice">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group-custom">
                <label>E-Mail Access</label>
                <input type="email" name="txtemail" placeholder="AGENT@TOURNX.COM" required>
            </div>

            <div class="form-group-custom">
                <label>Password</label>
                <input type="password" name="txtpwd" placeholder="••••••••" required>
            </div>

            <button type="submit" name="btnlogin" class="btn-red-action">
                Initialize Session
            </button>

            <div class="login-helper-links">
                <a href="signup.php">Register New Acc</a>
                <a href="forget_password.php">Forgot Password?</a>
            </div>
        </form>
    </div>
</main>