<?php
// Admin login handler
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load DB connection
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/../admin/sidebar.php';


// If already logged in, redirect to dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: /adminDashboard.php');
    exit;
}

$error_msg = '';
$email_raw = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_raw = $_POST['email'] ?? '';
    $email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || $password === '') {
        $error_msg = 'Please enter a valid email and password.';
    } else {
        // Try admins table first, then users with role 'admin'
        $queries = [
            ['sql' => "SELECT admin_id, username, password FROM admins WHERE email = ? LIMIT 1"],

        ];

        $found = false;
        foreach ($queries as $q) {
            if ($stmt = $conn->prepare($q['sql'])) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $user = $res->fetch_assoc();
                    $found = true;
                    $stmt->close();
                    break;
                }
                $stmt->close();
            }
        }

        if (!$found) {
            $error_msg = 'No admin account found for that email.';
        } else {
            $hash = $user['password'] ?? '';
            if (!empty($hash) && password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['name'] ?: $user['email'];
                header('Location: /adminDashboard.php');
                exit;
            } else {
                // allow legacy plain-text check as a last resort (not recommended)
                if ($password === $hash && $hash !== '') {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['name'] ?: $user['email'];
                    header('Location: adminDashboard.php');
                    exit;
                }
                $error_msg = 'Incorrect password.';
            }
        }
    }
}

?>

<div id="Admin-login-container">
    <h2>Admin Account</h2>

   

    <form action="" method="post" id="loginForm">
        <div class="Admin-login-input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" placeholder="Email" minlength="5" maxlength="254" required>
        </div>
        <div class="error-message" id="email-error"></div>
        

        <div class="Admin-login-input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Password" minlength="5" maxlength="64" required>
        </div>
        <div class="error-message" id="password-error"></div>

        <button type="submit" class="Admin-login-btn-submit">Login</button>

    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const emailInput = document.querySelector('input[name="email"]');
        const passwordInput = document.querySelector('input[name="password"]');
        const emailError = document.getElementById('email-error');
        const passwordError = document.getElementById('password-error');

        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).toLowerCase());
        }

        loginForm.addEventListener('submit', function(e) {
            let isValid = true;
            emailError.textContent = '';
            passwordError.textContent = '';

            if (!validateEmail(emailInput.value.trim())) {
                emailError.textContent = 'Please enter a valid email address.';
                isValid = false;
            }

            if (passwordInput.value.length < 5) {
                passwordError.textContent = 'Password must be at least 5 characters.';
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    });
</script>