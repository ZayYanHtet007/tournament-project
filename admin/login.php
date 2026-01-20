<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

$message = "";

// 1. Initialize Brute-Force Protection
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

$lockout_time = 900;
if ($_SESSION['login_attempts'] >= 8) {
    $time_passed = time() - $_SESSION['last_attempt_time'];

    if ($time_passed < $lockout_time) {
        $wait_minutes = ceil(($lockout_time - $time_passed) / 60);
        $message = "Too many failed attempts. Please try again in $wait_minutes minutes.";
    } else {
        // Time is up, reset attempts
        $_SESSION['login_attempts'] = 0;
    }
}

// 3. Process Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
        $message = "Please enter a valid email address.";
    } else {
        // Use Prepared Statements for Security
        $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Verify password using Bcrypt
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['admin_id'];
                $_SESSION['admin_name'] = $user['username'];
                $_SESSION['admin_email'] = $email;
                $_SESSION['login_attempts'] = 0;

                header("Location: adminDashboard.php");
                exit;
            } else {
                // WRONG PASSWORD
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $message = "Invalid email or password.";
            }
        } else {
            // EMAIL NOT FOUND
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $message = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        h2 {
            margin-bottom: 30px;
            color: #1f2937;
            font-weight: 600;
        }

        p.subtitle {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border: 1px solid #fecaca;
            animation: shake 0.5s ease-in-out;
        }

        .input-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 5px;
            margin-left: 5px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
            outline: none;
        }

        input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        input:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }

        button {
            width: 100%;
            padding: 14px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            margin-bottom: 30px;
        }

        button:hover:not(:disabled) {
            background-color: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }
    </style>
</head>

<body>

    <div class="login-card">
        <h2>Admin Login</h2>
        <?php if (!empty($message)): ?>
            <div class="error-box">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php

        $is_locked = ($_SESSION['login_attempts'] >= 8 && (time() - $_SESSION['last_attempt_time']) < 900);
        ?>

        <form method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="example@gmail.com" required
                    <?php echo $is_locked ? 'disabled' : ''; ?>>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="password" required
                    <?php echo $is_locked ? 'disabled' : ''; ?>>
            </div>

            <button type="submit" <?php echo $is_locked ? 'disabled' : ''; ?>>
                <?php echo $is_locked ? 'Account Locked' : 'Sign In'; ?>
            </button>
        </form>

    </div>

</body>

</html>