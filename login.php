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
        --primary-red: #ff4655;
        --deep-black: #0a0a0a;
        --sidebar-w: 80px; /* Width of the Riot Sidebar */
    }

    /* THE SIDEBAR */
    .riot-sidebar {
        position: fixed;
        left: 0; top: 0; bottom: 0;
        width: var(--sidebar-w);
        background: #000;
        border-right: 1px solid rgba(255, 70, 85, 0.2);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 20px;
        z-index: 9999; /* Ensure it stays above everything */
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
        transform: translateX(5px); /* Subtle Riot-style hover nudge */
    }

    .side-icon-btn span {
        font-size: 9px;
        color: #fff;
        font-weight: 800;
        margin-top: 6px;
        letter-spacing: 1px;
    }

    /* ================= OFFSET FOR ORIGINAL ELEMENTS ================= */
    /* This pushes your original header, footer, and main content to the right */
    .legacy-header, 
    .site-footer, 
    .login-container-wrapper {
        margin-left: var(--sidebar-w) !important;
        width: calc(100% - var(--sidebar-w)) !important;
    }

    /* ================= LOGIN DESIGN (RED & BLACK) ================= */
    .login-container-wrapper {
        min-height: 85vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: radial-gradient(circle at center, #1a080a 0%, #000000 100%);
    }

    .login-panel {
        background: #111;
        width: 100%;
        max-width: 400px;
        padding: 50px 40px;
        border: 1px solid #222;
        border-top: 4px solid var(--primary-red);
        box-shadow: 0 40px 100px rgba(0,0,0,0.8);
    }

    .login-panel h2 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 38px;
        color: #fff;
        text-align: center;
        margin-bottom: 5px;
        letter-spacing: 2px;
    }

    .login-panel p.subtitle {
        color: #555;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: bold;
        text-align: center;
        margin-bottom: 40px;
        letter-spacing: 1px;
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
        padding: 14px;
        background: #1a1a1a;
        border: 1px solid #333;
        color: #fff;
        font-weight: 600;
        transition: 0.3s;
    }

    .form-group-custom input:focus {
        border-color: var(--primary-red);
        background: #222;
        outline: none;
    }

    .btn-red-action {
        width: 100%;
        padding: 18px;
        background: var(--primary-red);
        color: #fff;
        border: none;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 22px;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        letter-spacing: 1px;
    }

    .btn-red-action:hover {
        background: #cc3844;
        filter: brightness(1.2);
        box-shadow: 0 0 20px rgba(255, 70, 85, 0.3);
    }

    .login-helper-links {
        margin-top: 25px;
        display: flex;
        justify-content: space-between;
        font-size: 12px;
    }

    .login-helper-links a { color: #888; text-decoration: none; transition: 0.2s; }
    .login-helper-links a:hover { color: var(--primary-red); }

    .error-notice {
        background: rgba(255, 70, 85, 0.1);
        color: var(--primary-red);
        padding: 12px;
        border: 1px solid var(--primary-red);
        margin-bottom: 25px;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
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
                <label>Passphrase</label>
                <input type="password" name="txtpwd" placeholder="••••••••" required>
            </div>

            <button type="submit" name="btnlogin" class="btn-red-action">
                Initialize Session
            </button>

            <div class="login-helper-links">
                <a href="signup.php">Register New Agent</a>
                <a href="forget_password.php">Forgot Password?</a>
            </div>
        </form>
    </div>
</main>

<?php 
// 3. INCLUDE YOUR ORIGINAL FOOTER
include('partial/footer.php'); 
?>