<?php
session_start();
include('partial/header.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// <require=include>
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

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
        header("Location: verifyCode.php");
        exit;

    } catch (Exception $e) {
        echo "Error sending email: {$mail->ErrorInfo}";
    }
}
?>
<div class="login_container">
    <form method="POST">
        <input type="email" name="email" placeholder="Enter email" required class="login_input">
        <button name="submit" class="login_btn">Send Code</button>
    </form>
</div>

<?php
include('partial/footer.php');
?>