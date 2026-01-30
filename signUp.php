<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('partial/header.php'); 
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
    :root {
        --primary-red: #ff4655;
        --sidebar-w: 80px;
    }

    body {
        background-color: #0a0a0a !important;
        margin: 0;
    }

    /* ================= OFFSET SHELL ================= */
    .legacy-header, .site-footer, .signup-main-wrapper {
        margin-left: var(--sidebar-w) !important;
    }

    /* ================= SIGNUP CARD DESIGN ================= */
    .signup-main-wrapper {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: radial-gradient(circle at center, #1a080a 0%, #000000 100%);
        padding: 60px 20px;
    }

    .signup-panel {
        background: #111;
        width: 100%;
        max-width: 500px;
        padding: 50px;
        border: 1px solid #222;
        border-top: 4px solid var(--primary-red);
        box-shadow: 0 40px 100px rgba(0,0,0,0.8);
        position: relative;
    }

    .signup-panel h1 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 42px;
        color: #fff;
        text-align: center;
        letter-spacing: 2px;
        margin-bottom: 10px;
    }

    .signup-panel p.tagline {
        color: #555;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 800;
        text-align: center;
        margin-bottom: 40px;
        letter-spacing: 1.5px;
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
        background: #1a1a1a;
        border: 1px solid #333;
        color: #fff;
        font-weight: 600;
        transition: 0.3s;
    }

    .form-field-signup input:focus {
        border-color: var(--primary-red);
        background: #222;
        outline: none;
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
        font-size: 24px;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        letter-spacing: 1px;
    }

    .btn-signup-red:hover {
        filter: brightness(1.2);
        box-shadow: 0 0 30px rgba(255, 70, 85, 0.3);
    }

    .btn-cancel {
        display: block;
        text-align: center;
        margin-top: 15px;
        color: #666;
        text-decoration: none;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 800;
    }

    .btn-cancel:hover { color: #fff; }

    /* Feedback */
    .msg-box {
        padding: 12px;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 25px;
        border: 1px solid;
    }
</style>

<main class="signup-main-wrapper">
    <div class="signup-panel">
        <h1>JOIN THE <span style="color: var(--primary-red);">ARENA</span></h1>
        <p class="tagline">Agent Initialization Protocol</p>

        <?php if ($error): ?>
            <div class="msg-box" style="background:rgba(255,70,85,0.1); color:var(--primary-red); border-color:var(--primary-red);">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="msg-box" style="background:rgba(76,175,80,0.1); color:#4caf50; border-color:#4caf50;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-field-signup">
                <label>Codename (Username)</label>
                <input type="text" name="username" required minlength="3" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-field-signup">
                <label>Communication Uplink (Email)</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-field-signup">
                <label>Access Key (Password)</label>
                <input type="password" name="password" required minlength="8">
            </div>

            <div class="form-field-signup">
                <label>Confirm Access Key</label>
                <input type="password" name="confirmPassword" required>
            </div>

            <div class="checkbox-field-signup">
                <input type="checkbox" name="isOrganizer" id="orgCheck" <?= isset($_POST['isOrganizer']) ? 'checked' : '' ?>>
                <label for="orgCheck">I intend to organize and host official tournaments</label>
            </div>

            <button type="submit" name="btnsave" class="btn-signup-red">
                Complete Registration
            </button>

            <a href="index.php" class="btn-cancel">Abort Protocol</a>
        </form>
    </div>
</main>

<?php include('partial/footer.php'); ?>