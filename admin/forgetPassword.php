<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// <require=include>
require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';
require '../phpmailer/Exception.php';

if (isset($_POST['submit'])) {

    $email = $_POST['email'];

    // Generate 6-digit random OTP code
    $code = rand(100000, 999999);

    // Save OTP in session
    $_SESSION['reset_email'] = $email;
    $_SESSION['resetcode'] = $code;

    // Send Email
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'theintnandarsoe16@gmail.com';
        $mail->Password = 'cqmx tiwi oqoe rpyr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        // give a code
        $mail->setFrom('myattheingikyaw200234@gmail.com', 'Theint');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your Password Reset Code";
        $mail->Body = "
            <h2>Password Reset Request</h2>
            <p>Your verification code is:</p>
            <h1>$code</h1>
            <p>Enter this code in the reset page.</p>
        ";

        $mail->send();
        echo "Reset code sent to email.";
        header("Location: adminVerifyCode.php");
        exit;

    } catch (Exception $e) {
        echo "Error sending email: {$mail->ErrorInfo}";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forget Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
    body {
        background-color: #F3F1F9;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }

    .login_container {
        background: #ffffff;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        width: 100%;
        max-width: 350px;
        text-align: center;
    }

    .login_container::before {
        content: "Forgot Password?";
        display: block;
        font-size: 22px;
        font-weight: bold;
        color: #100b4b;
        margin-bottom: 10px;
    }

    .login_container::after {
        content: "Please write your email to receive a confirmation code to set a new password.";
        display: block;
        font-size: 13px;
        color: #666;
        margin-bottom: 30px;
        line-height: 1.5;
    }

    .login_input {
        width: 100%;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid #E0E0E0;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 14px;
        background-color: #FAFAFA;
        outline: none;
        transition: border 0.3s;
    }

    .login_input:focus {
        border: 1px solid #8E70F5;
    }

    .login_btn {
        width: 100%;
        padding: 15px;
        background:  linear-gradient(135deg, #120c4d, #849dd3);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s;
        margin-top: 10px;
    }

    .login_btn:hover {
        background-color: #7656E3;
    }
    </style>
</head>
<body>
    <div class="login_container">
    <form method="POST">
        <input type="email" name="email" placeholder="Enter email" required class="login_input">
        <button name="submit" class="login_btn">Send Code</button>
    </form>
</div>
</body>
</html>



