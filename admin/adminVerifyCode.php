<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code</title>
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
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 350px;
            text-align: center;
        }

        .title {
            font-size: 22px;
            font-weight: bold;
            color: #100b4b;
            margin: 0 0 10px 0;
        }

        .subtitle {
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
            font-size: 18px;
            letter-spacing: 5px;
            text-align: center;
            background-color: #FAFAFA;
            outline: none;
            transition: border 0.3s;
        }

        .login_input:focus {
            border: 2px solid #8E70F5;
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
        }

        .login_btn:hover {
            background-color: #7656E3;
        }

        .resend_text {
            margin-top: 25px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>

<div class="login_container">
    <h2 class="title">Verify email address</h2>
    <p class="subtitle">
        Verification code sent to:<br>
        <strong><?php echo isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : 'your email'; ?></strong>
    </p>

    <form method="post">
        <input type="text" name="resetcode" placeholder="Enter Code" maxlength="6" required class="login_input">
        <button name="submit" class="login_btn">Confirm Code</button>
    </form>

   
</div>

<?php
if(isset($_POST['submit'])){
    $code = $_POST['resetcode'];
    $resetcode = $_SESSION['resetcode'];

    if($code == $resetcode){
        echo "<script>window.location.href='adminLoginChangePassword.php';</script>";
        exit();
    } else {
        echo "<script>alert('Invalid code');</script>";
    }
}
?>

</body>
</html>