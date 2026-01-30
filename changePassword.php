<?php
session_start();
require_once "partial/init.php"; // Ensure $conn or $pdo is defined here
include('partial/header.php');

// Handle Logic before any HTML output to allow header redirection
if(isset($_POST['submit'])){
    $pwd = $_POST['txtpwd'];
    $cpwd = $_POST['txtcpwd'];
    $email = $_SESSION['reset_email'] ?? '';

    if(empty($email)) {
        $error = "SESSION EXPIRED. PLEASE RE-INITIATE RESET.";
    } elseif($pwd != $cpwd){
        $error = "SECURITY ALERT: PASSWORDS DO NOT MATCH";
    } else {
        $hashed = password_hash($pwd, PASSWORD_DEFAULT);
        // Using Prepared Statement for Security
        $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE email=?");
        mysqli_stmt_bind_param($stmt, "ss", $hashed, $email);
        
        if(mysqli_stmt_execute($stmt)) {
            header("Location: login.php?reset=success");
            exit();
        } else {
            $error = "SYSTEM ERROR: UNABLE TO UPDATE DATA";
        }
    }
}
?>

<style>
    :root {
        --riot-red: #ff4655;
        --riot-white: #ece8e1;
        --riot-border: rgba(255, 255, 255, 0.15);
        --gaming-bg: #1a1d23; 
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    .reset-wrapper {
        margin-left: 85px; /* Matches your layout */
        margin-top: 75px;
        min-height: calc(100vh - 75px);
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        background-color: var(--gaming-bg);
        position: relative;
        overflow: hidden;
        /* Crosshair/Grid Pattern */
        background-image: 
            radial-gradient(circle, rgba(255,255,255,0.03) 1px, transparent 1px),
            linear-gradient(to right, rgba(255,255,255,0.02) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(255,255,255,0.02) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    /* Pulsing Red Glow Layer */
    .reset-wrapper::before {
        content: "";
        position: absolute;
        width: 800px;
        height: 800px;
        background: radial-gradient(circle, rgba(255, 70, 85, 0.15) 0%, transparent 65%);
        top: -200px;
        right: -200px;
        z-index: 0;
        animation: pulse-glow 6s ease-in-out infinite;
    }

    @keyframes pulse-glow {
        0%, 100% { opacity: 0.4; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.1); }
    }

    .reset-container {
        width: 100%;
        max-width: 400px;
        background: #111;
        border: 1px solid var(--riot-border);
        padding: 50px 40px;
        position: relative;
        z-index: 1;
        box-shadow: 0 40px 100px rgba(0,0,0,0.5);
    }

    /* Tactical Label */
    .reset-container::after {
        content: "SECURITY // OVERRIDE";
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
        margin-bottom: 30px;
        letter-spacing: 1px;
        color: #fff;
        text-transform: uppercase;
    }

    .field { margin-bottom: 20px; }

    label {
        display: block;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 8px;
        color: rgba(255,255,255,0.4);
        letter-spacing: 1.5px;
    }

    input[type="password"] {
        width: 100%;
        padding: 15px;
        background: #1a1a1a;
        border: 1px solid transparent;
        border-bottom: 2px solid var(--riot-border);
        color: #fff;
        font-size: 14px;
        transition: 0.3s;
    }

    input[type="password"]:focus {
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
        margin-top: 10px;
        transition: 0.3s;
    }

    .submit-btn::before {
        content: "";
        position: absolute;
        top: 0; left: 0; width: 0; height: 100%;
        background: var(--riot-red);
        transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: -1;
    }

    .submit-btn:hover { color: #000; }
    .submit-btn:hover::before { width: 100%; }

    .err-msg {
        color: var(--riot-red);
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 20px;
        text-align: center;
        text-transform: uppercase;
        font-family: sans-serif;
    }
</style>

<div class="reset-wrapper">
    <div class="reset-container">
        <h2>Reset Password</h2>

        <?php if(isset($error)): ?>
            <p class="err-msg"><?= $error ?></p>
        <?php endif; ?>

        <form method="post">
            <div class="field">
                <label>New Password</label>
                <input type="password" name="txtpwd" placeholder="••••••••" required>
            </div>

            <div class="field">
                <label>Confirm Password</label>
                <input type="password" name="txtcpwd" placeholder="••••••••" required>
            </div>

            <button type="submit" name="submit" class="submit-btn">UPDATE PROTOCOL</button>
        </form>
    </div>
</div>

<?php include('partial/footer.php'); ?>