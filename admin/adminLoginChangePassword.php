<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

if(isset($_POST['submit'])){
    $pwd = $_POST['txtpwd'];
    $cpwd = $_POST['txtcpwd'];
    $email = $_SESSION['reset_email'];

    if($pwd != $cpwd){
        echo "<script>alert('ERROR: PASSWORDS DO NOT MATCH');</script>";
    } else {
        $hashed = password_hash($pwd, PASSWORD_DEFAULT);
        $sql = "UPDATE admins SET password='$hashed' WHERE email='$email'";
        if($conn->query($sql)){
            echo "<script>alert('SUCCESS: SECURITY KEY UPDATED'); window.location.href='AdminLogin.php';</script>";
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
    <title>SECURE RESET | TX NETWORK</title>
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
        }

        /* Scanline effect consistent with entire suite */
        .login_container::before {
            content: " ";
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%);
            background-size: 100% 2px;
            pointer-events: none;
        }

        .title {
            font-family: 'Oswald', sans-serif;
            font-size: 2rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
            margin: 0;
        }

        .title span {
            color: var(--accent-primary);
        }

        .subtitle {
            font-size: 0.8rem;
            color: var(--text-dim);
            text-align: center;
            margin: 15px 0 35px 0;
            text-transform: uppercase;
        }

        .input_group {
            margin-bottom: 25px;
        }

        .input_label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--accent-primary);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .login_input {
            width: 100%;
            padding: 15px;
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
            box-shadow: 0 0 10px rgba(0, 210, 255, 0.15);
            background-color: #1c2128;
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
            margin-top: 10px;
        }

        .login_btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 210, 255, 0.4);
        }
    </style>
</head>
<body>

<div class="login_container">
    <h2 class="title">NEW <span>PASSPHRASE</span></h2>
    <p class="subtitle">Update admin credentials for <?php echo htmlspecialchars($_SESSION['reset_email']); ?></p>

    <form method="post">
        <div class="input_group">
            <span class="input_label">Primary Key</span>
            <input type="password" name="txtpwd" placeholder="••••••••" required class="login_input">
        </div>

        <div class="input_group">
            <span class="input_label">Verify Key</span>
            <input type="password" name="txtcpwd" placeholder="••••••••" required class="login_input">
        </div>

        <button name="submit" class="login_btn">Finalize Update</button>
    </form>
</div>

</body>
</html>