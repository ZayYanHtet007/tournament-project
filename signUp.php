<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "database/dbConfig.php";

$error = "";
$success = "";

if (isset($_POST['btnsave'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirmPassword'];
    $isOrganizerChecked = isset($_POST['isOrganizer']);

    /* ===== PHP VALIDATION preserved ===== */
    if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match";
    } else {
        /* ===== DUPLICATE CHECK preserved ===== */
        $check = "SELECT user_id FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $check);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Username or Email already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if ($isOrganizerChecked) {
                $is_organizer = 1;
                $organizer_status = "pending";
            } else {
                $is_organizer = 0;
                $organizer_status = NULL;
            }

            /* ===== INSERT USER preserved ===== */
            $sql = "INSERT INTO users (username, email, password, is_organizer, organizer_status) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssis", $username, $email, $hashed_password, $is_organizer, $organizer_status);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Signup successful. You can now login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Signup failed. Please try again.";
            }
        }
    }
}
include('partial/header.php'); 
?>

<script src="https://cdn.lordicon.com/lordicon.js"></script>

<style>
    /* ================= CORE STYLING & VARIABLES ================= */
    :root {
        --primary-red: #ff3344;
        --dark-red: #4a0a0a;
        --deep-black: #050505;
        --sidebar-w: 80px;
    }

    body {
        background-color: #000 !important;
        margin: 0;
    }

    /* ================= OFFSET FOR SIDEBAR ================= */
    .legacy-header, .site-footer, .signup-main-wrapper {
        margin-left: var(--sidebar-w) !important;
        width: calc(100% - var(--sidebar-w)) !important;
    }

    /* ================= GAMING BACKGROUND STYLE ================= */
    .signup-main-wrapper {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        /* Gaming Grid & Red Glow Background */
        background-image: 
            linear-gradient(rgba(255, 50, 50, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 50, 50, 0.05) 1px, transparent 1px),
            radial-gradient(circle at center, rgba(80, 0, 0, 0.5) 0%, #000000 85%);
        background-size: 30px 30px, 30px 30px, 100% 100%;
        padding: 60px 20px;
        position: relative;
    }

    /* ================= SIGNUP PANEL ================= */
    .signup-panel {
        background: rgba(15, 15, 15, 0.95);
        width: 100%;
        max-width: 500px;
        padding: 50px;
        border: 1px solid #333;
        border-top: 4px solid var(--primary-red);
        border-bottom: 4px solid var(--primary-red);
        box-shadow: 0 0 50px rgba(255, 51, 68, 0.15);
        position: relative;
        z-index: 2;
        backdrop-filter: blur(5px);
    }

    .signup-panel h1 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 42px;
        color: #fff;
        text-align: center;
        letter-spacing: 3px;
        margin-bottom: 5px;
        text-shadow: 0 0 10px rgba(255, 51, 68, 0.5);
    }

    .signup-panel p.tagline {
        color: #888;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 800;
        text-align: center;
        margin-bottom: 40px;
        letter-spacing: 2px;
        border-bottom: 1px solid #333;
        padding-bottom: 15px;
    }

    /* Form Fields */
    .form-field-signup {
        margin-bottom: 22px;
    }

    .form-field-signup label {
        display: block;
        color: var(--primary-red);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .form-field-signup input {
        width: 100%;
        padding: 14px;
        background: #0a0a0a;
        border: 1px solid #333;
        color: #fff;
        font-weight: 600;
        transition: 0.3s;
        font-family: monospace;
    }

    .form-field-signup input:focus {
        border-color: var(--primary-red);
        background: #110505;
        outline: none;
        box-shadow: 0 0 15px rgba(255, 51, 68, 0.2);
    }

    /* Checkbox Styling */
    .checkbox-field-signup {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 30px 0;
        padding: 15px;
        background: rgba(255, 70, 85, 0.05);
        border: 1px dashed rgba(255, 70, 85, 0.3);
    }

    .checkbox-field-signup input {
        accent-color: var(--primary-red);
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .checkbox-field-signup label {
        font-size: 12px;
        color: #bbb;
        font-weight: 600;
        cursor: pointer;
    }

    /* Buttons */
    .btn-signup-red {
        width: 100%;
        padding: 18px;
        background: var(--primary-red);
        color: #fff;
        border: none;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 26px;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        letter-spacing: 2px;
        box-shadow: 0 5px 15px rgba(255, 51, 68, 0.3);
    }

    .btn-signup-red:hover {
        background: #ff5566;
        box-shadow: 0 0 25px rgba(255, 51, 68, 0.6);
        transform: translateY(-2px);
    }

    .btn-cancel {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: #666;
        text-decoration: none;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 1px;
        transition: 0.2s;
    }

    .btn-cancel:hover { color: var(--primary-red); }

    /* Feedback Box */
    .msg-box {
        padding: 15px;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 25px;
        border: 1px solid;
        text-transform: uppercase;
    }

    /* ================= RESPONSIVE DESIGN ================= */
    @media (max-width: 768px) {
        .legacy-header, .site-footer, .signup-main-wrapper {
            margin-left: 0 !important;
            width: 100% !important;
        }

        .signup-panel {
            padding: 35px 20px;
            margin: 10px;
            border-left: none;
            border-right: none;
        }

        .signup-panel h1 {
            font-size: 32px;
        }
    }
</style>

<main class="signup-main-wrapper">
    <div class="signup-panel">
        <h1>JOIN THE <span style="color: var(--primary-red);">ARENA</span></h1>
        <p class="tagline">Agent Initialization Protocol</p>

        <?php if ($error): ?>
            <div class="msg-box" style="background:rgba(255,70,85,0.1); color:var(--primary-red); border-color:var(--primary-red);">
                [SYSTEM ERROR]: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="msg-box" style="background:rgba(76,175,80,0.1); color:#4caf50; border-color:#4caf50;">
                [SUCCESS]: <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-field-signup">
                <label>Username</label>
                <input type="text" name="username" placeholder="NAME" required minlength="3" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-field-signup">
                <label>Email</label>
                <input type="email" name="email" placeholder="EMAIL@NETWORK.COM" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-field-signup">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••••••" required minlength="8">
            </div>

            <div class="form-field-signup">
                <label>Comfirm Password</label>
                <input type="password" name="confirmPassword" placeholder="••••••••••••" required>
            </div>

            <div class="checkbox-field-signup">
                <input type="checkbox" name="isOrganizer" id="orgCheck" <?= isset($_POST['isOrganizer']) ? 'checked' : '' ?>>
                <label for="orgCheck">Elevate Clearance: Apply for Tournament Organizer Status</label>
            </div>

            <button type="submit" name="btnsave" class="btn-signup-red">
                Initialize Account
            </button>

            <a href="index.php" class="btn-cancel">Abort Protocol</a>
        </form>
    </div>
</main>