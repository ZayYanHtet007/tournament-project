<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SECURE VERIFICATION | TX</title>
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
            max-width: 420px;
            border-top: 4px solid var(--accent-primary);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            text-align: center;
            position: relative;
        }

        /* Scanline effect */
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
            margin: 0;
        }

        .title span {
            color: var(--accent-primary);
        }

        .subtitle {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin: 15px 0 35px 0;
            text-transform: uppercase;
            line-height: 1.6;
        }

        .otp-container {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 30px;
        }

        .otp-input {
            width: 50px;
            height: 60px;
            background-color: var(--input-fill);
            border: 1px solid #222;
            color: var(--accent-primary);
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            outline: none;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.2);
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
        }

        .login_btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 210, 255, 0.3);
        }

        .resend_text {
            margin-top: 25px;
            font-size: 0.7rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .resend_text a {
            color: var(--accent-primary);
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="login_container">
    <h2 class="title">VERIFY <span>ACCESS</span></h2>
    <p class="subtitle">
        System Key transmitted to:<br>
        <span style="color: white;"><?php echo isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : 'ADMIN_SESSION'; ?></span>
    </p>

    <form method="post" id="otp-form">
        <input type="hidden" name="resetcode" id="combined_code">
        
        <div class="otp-container">
            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric" required>
        </div>

        <button name="submit" class="login_btn">Confirm Identity</button>
    </form>

    <p class="resend_text">No signal received? <a href="forgetPassword.php">Re-transmit Key</a></p>
</div>

<script>
    const inputs = document.querySelectorAll('.otp-input');
    const hiddenInput = document.getElementById('combined_code');

    inputs.forEach((input, index) => {
        // Handle typing
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            combineValues();
        });

        // Handle backspace
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });

    function combineValues() {
        let code = "";
        inputs.forEach(input => code += input.value);
        hiddenInput.value = code;
    }
</script>

<?php
if(isset($_POST['submit'])){
    $code = $_POST['resetcode'];
    $resetcode = $_SESSION['resetcode'];

    if($code == $resetcode){
        echo "<script>window.location.href='adminLoginChangePassword.php';</script>";
        exit();
    } else {
        echo "<script>alert('ERROR: INVALID SECURITY KEY');</script>";
    }
}
?>

</body>
</html>