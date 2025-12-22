<?php
session_start();
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['txtemail'] ?? '');
    $pwd   = $_POST['txtpwd'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($pwd === '') {
        $error = 'Please enter your password.';
    } else {
        require_once __DIR__ . '/../database/dbConfig.php';

        $queries = [
            "SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1",
            "SELECT id, username AS name, email, password FROM users WHERE email = ? AND role = 'admin' LIMIT 1"
        ];

        $row = null;
        foreach ($queries as $sql) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $stmt->close();
                break;
            }
            $stmt->close();
        }

        if (!$row) {
            $error = 'No admin account found.';
        } else {
            if (password_verify($pwd, $row['password'])) {
                $_SESSION['admin_id']   = $row['id'];
                $_SESSION['admin_name'] = $row['name'] ?: $row['email'];
                header('Location: adminDashboard.php');
                exit;
            } else {
                $error = 'Incorrect password.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<div class="login_body"> 

    <div id="Admin_login-container">
        <h2>Admin Login</h2>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="Admin_login_input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="txtemail"
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="Email" required>
            </div>

            <div class="Admin_login_input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="txtpwd" placeholder="Password" required>
            </div>

            <button type="submit">Login</button>
        </form>
    </div>

</div> 
</html>
>

