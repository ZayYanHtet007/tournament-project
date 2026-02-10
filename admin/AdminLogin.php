<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

$message = "";

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
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
        $message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT admin_id, username, password, img, role FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['admin_id'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['admin_name'] = $user['username'];
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_img'] = $user['img'];
                $_SESSION['login_attempts'] = 0;

                header("Location: adminDashboard.php");
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $message = "Invalid email or password.";
            }
        } else {
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
    <title>ADMIN ACCESS | TX</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #080a0c;
            --card-bg: #0f1216;
            --accent-primary: #00d2ff; /* Electric Blue */
            --accent-secondary: #3a7bd5; /* Deep Sea Blue */
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

        .login-card {
            background: var(--card-bg);
            padding: 50px 40px;
            width: 100%;
            max-width: 420px;
            border-top: 4px solid var(--accent-primary);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 210, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        /* Subtle scanline effect */
        .login-card::before {
            content: " ";
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%), 
                        linear-gradient(90deg, rgba(255, 0, 0, 0.02), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.02));
            background-size: 100% 2px, 3px 100%;
            pointer-events: none;
        }

        h2 {
            margin: 0;
            font-family: 'Oswald', sans-serif;
            font-size: 2.2rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
        }

        h2 span {
            color: var(--accent-primary);
            text-shadow: 0 0 10px rgba(0, 210, 255, 0.5);
        }

        .subtitle {
            color: var(--text-dim);
            font-family: 'Oswald', sans-serif;
            font-size: 0.85rem;
            letter-spacing: 4px;
            margin-bottom: 40px;
            text-transform: uppercase;
            text-align: center;
        }

        .error-box {
            background: rgba(0, 210, 255, 0.05);
            color: #7dd3fc;
            padding: 12px;
            margin-bottom: 25px;
            font-size: 0.8rem;
            border-left: 2px solid var(--accent-primary);
            text-transform: uppercase;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--accent-primary);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        input {
            width: 100%;
            padding: 14px 18px;
            background-color: var(--input-fill);
            border: 1px solid #222;
            color: white;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            box-sizing: border-box;
            outline: none;
            transition: all 0.3s ease;
        }

        input:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 10px rgba(0, 210, 255, 0.15);
            background-color: #1c2128;
        }

        button {
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
            margin-bottom: 25px;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 210, 255, 0.4);
        }

        button:disabled {
            background: #2d3748;
            color: #718096;
            cursor: not-allowed;
        }

        .footer-links {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
        }

        .footer-links a {
            color: var(--text-dim);
            text-decoration: none;
            text-transform: uppercase;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--accent-primary);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h2>ADMIN <span>LOGIN</span></h2>
        <p class="subtitle">Global Esports Network</p>

        <?php if (!empty($message)): ?>
            <div class="error-box">
                // SYSTEM ERROR: <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php
        $is_locked = ($_SESSION['login_attempts'] >= 8 && (time() - $_SESSION['last_attempt_time']) < 900);
        ?>

        <form method="POST">
            <div class="input-group">
                <label>Admin Username</label>
                <input type="email" name="email" placeholder="ADMIN@TOURNX.COM" required
                    <?php echo $is_locked ? 'disabled' : ''; ?>>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required
                    <?php echo $is_locked ? 'disabled' : ''; ?>>
            </div>

            <button type="submit" <?php echo $is_locked ? 'disabled' : ''; ?>>
                <?php echo $is_locked ? 'System Lockdown' : ' Login'; ?>
            </button>

            <div class="footer-links">
                <a href="forgetPassword.php">Forget Password</a>
            </div>
        </form>
    </div>

</body>
</html>