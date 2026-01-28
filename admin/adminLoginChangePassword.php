<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';
if(isset($_POST['submit'])){
    $pwd = $_POST['txtpwd'];
    $cpwd = $_POST['txtcpwd'];
    $email = $_SESSION['reset_email'];

    if($pwd != $cpwd){
        echo "<script>alert('Passwords do not match');</script>";
    } else {
        $hashed = password_hash($pwd, PASSWORD_DEFAULT);
        $sql = "UPDATE admins SET password='$hashed' WHERE email='$email'";
        if($conn->query($sql)){
            echo "<script>alert('Password updated successfully'); window.location.href='AdminLogin.php';</script>";
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Password</title>
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
            text-align: left; 
        }

        .title {
            font-size: 22px;
            font-weight: bold;
            color: #100b4b;
            margin: 0 0 10px 0;
            text-align: center;
        }

        .subtitle {
            font-size: 13px;
            color: #666;
            margin-bottom: 25px;
            text-align: center;
        }

        .input_group {
            margin-bottom: 15px;
        }

        .input_label {
            display: block;
            font-size: 13px;
            color: #888;
            margin-bottom: 5px;
        }

        .login_input {
            width: 100%;
            padding: 14px;
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
            margin-top: 10px;
            transition: background 0.3s;
        }

        .login_btn:hover {
            background-color: #7656E3;
        }
    </style>
</head>
<body>

<div class="login_container">
    <h2 class="title">New Password</h2>
    <p class="subtitle">Please write your new password.</p>

    <form method="post">
        <div class="input_group">
            <span class="input_label">Password</span>
            <input type="password" name="txtpwd" placeholder="••••••••" required class="login_input">
        </div>

        <div class="input_group">
            <span class="input_label">Confirm Password</span>
            <input type="password" name="txtcpwd" placeholder="••••••••" required class="login_input">
        </div>

        <button name="submit" class="login_btn">Confirm Password</button>
    </form>
</div>

</body>
</html>
