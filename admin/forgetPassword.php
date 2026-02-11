<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';
require '../phpmailer/Exception.php';

$message = "";
$messageType = ""; // 'error' or 'success'

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $code = rand(100000, 999999);

    $_SESSION['reset_email'] = $email;
    $_SESSION['resetcode'] = $code;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kelvin30102003@gmail.com';
        $mail->Password = 'jfzu ayeg gcov fbij';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        
        $mail->setFrom('myattheingikyaw200234@gmail.com', 'Theint');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "RECOVERY KEY: Admin Access";
        $mail->Body = "
            <div style='background:#0f1216; color:#e2e8f0; padding:20px; font-family:sans-serif; border-top:4px solid #00d2ff;'>
                <h2 style='color:#00d2ff;'>SECURE RECOVERY REQUEST</h2>
                <p>A request was made to reset your admin credentials. Use the following digital key to proceed:</p>
                <h1 style='letter-spacing:5px; background:#161b22; padding:10px; text-align:center; border:1px solid #333;'>$code</h1>
                <p>If you did not request this, please contact system security immediately.</p>
            </div>
        ";

        $mail->send();
        header("Location: adminVerifyCode.php");
        exit;

    } catch (Exception $e) {
        $message = "SYSTEM ERROR: Could not transmit recovery key.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RECOVERY | TX NETWORK</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #080a0c;
            --card-bg: #0f1216;
            --accent-primary: #00d2ff;
            --accent-secondary: #3a7bd5;
            --text-main: #e2e8f0;
            --text-dim: #64748b;
            --input-fill: #161b22;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'JetBrains Mono', monospace;
            background-color: var(--bg-dark);
            background-image: radial-gradient(circle at 50% 50%, #111827 0%, #080a0c 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
        }

        .login_container {
            background: var(--card-bg);
            padding: 50px 40px;
            width: 100%;
            max-width: 400px;
            border-top: 4px solid var(--accent-primary);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            position: relative;
            text-align: center;
        }

        /* Scanline effect consistent with login */
        .login_container::before {
            content: " ";
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%);
            background-size: 100% 2px;
            pointer-events: none;
            z-index: 1;
        }

        h2 {
            margin: 0;
            font-family: 'Oswald', sans-serif;
            font-size: 2rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: white;
        }

        h2 span {
            color: var(--accent-primary);
        }

        .subtitle {
            color: var(--text-dim);
            font-size: 0.8rem;
            margin: 15px 0 35px 0;
            line-height: 1.6;
            text-transform: uppercase;
        }

        .error-box {
            background: rgba(255, 71, 87, 0.1);
            color: #ff4757;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 0.75rem;
            border: 1px solid rgba(255, 71, 87, 0.3);
            text-align: left;
        }

        .login_input {
            width: 100%;
            padding: 15px;
            margin-bottom: 25px;
            background-color: var(--input-fill);
            border: 1px solid #222;
            color: white;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            box-sizing: border-box;
            outline: none;
            transition: all 0.3s ease;
        }

        .login_input:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 10px rgba(0, 210, 255, 0.1);
        }

        .login_btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--accent-secondary) 0%, var(--accent-primary) 100%);
            color: white;
            border: none;
            font-family: 'Oswald', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .login_btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 210, 255, 0.3);
        }

        .back-link {
            display: block;
            margin-top: 25px;
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .back-link:hover {
            color: var(--accent-primary);
        }
    </style>
</head>
<body>
    <div class="login_container">
        <h2>password <span>RECOVERY</span></h2>
        <p class="subtitle">Enter your registered admin email to receive a secure recovery code.</p>

        <?php if (!empty($message)): ?>
            <div class="error-box">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="ADMIN_IDENTIFIER@TX.COM" required class="login_input">
            <button name="submit" class="login_btn">Send Code</button>
        </form>

        <a href="adminLogin.php" class="back-link">← Return to Login</a>
    </div>
</body>
</html>