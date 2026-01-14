<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION['admin_id'];
$alert = "";
$alertType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // Server-side validation (matching the JavaScript logic)
    if ($current === '' || $new === '' || $confirm === '') {
        $alert = "All fields are required!";
        $alertType = "error";
    } elseif ($new !== $confirm) {
        $alert = "Passwords do not match!";
        $alertType = "error";
    } elseif (!preg_match('/^[A-Z]/', $new)) {
        $alert = "First letter must be Uppercase!";
        $alertType = "error";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new)) {
        $alert = "At least one special character required!";
        $alertType = "error";
    } elseif (strlen($new) < 8) {
        $alert = "Password must be at least 8 characters!";
        $alertType = "error";
    } else {
        // Check current password
        $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if (!$admin || !password_verify($current, $admin['password'])) {
            $alert = "Current Password is incorrect!";
            $alertType = "error";
        } else {
            $newHashedPassword = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $update->bind_param("si", $newHashedPassword, $adminId);
            
            if ($update->execute()) {
                $alert = "Password updated successfully!";
                $alertType = "success";
            } else {
                $alert = "Database error. Please try again.";
                $alertType = "error";
            }
            $update->close();
        }
    }
}

require_once __DIR__ . '/sidebar.php';
?>
<div class="chgpwdcontainer">
    <h2 style="margin-bottom: 5px; color: #1e293b;">Change Password</h2>
    <p style="color: #64748b; font-size: 14px; margin-bottom: 25px;">Update your  password.</p>

    <?php if ($alert != ""): ?>
        <div class="alert-msg <?= $alertType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= $alert ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="changefield">
            <label>Current Password</label>
            <div class="password-wrap">
                <input type="password" name="current" id="current" required>
                
            </div>
        </div>

        <div class="changefield">
            <label>New Password</label>
            <div class="password-wrap">
                <input type="password" name="new" id="new" required>
                
            </div>

            <div class="strength-box">
                <div class="bar-container">
                    <div id="strength-bar" class="bar"></div>
                </div>
                <p id="validation-feedback"></p>
            </div>
        </div>

        <div class="changefield">
            <label>Confirm New Password</label>
            <div class="password-wrap">
                <input type="password" name="confirm" id="confirm" required>
                
            </div>
        </div>

        <div class="changepwdactions">
            <button type="reset" class="btn btn-secondary">Discard</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<script>

    const newInput = document.getElementById('new');
    const bar = document.getElementById('strength-bar');
    const feedback = document.getElementById('validation-feedback');

    newInput.addEventListener('input', () => {
        const val = newInput.value;
        let message = "";
        let score = 0;

        if (val === "") {
            feedback.textContent = "";
            bar.style.width = "0%";
            return;
        }

        
        if (val.length < 8) {
            message = "❌ At least 8 characters required";
            score = 1;
        } else if (!/^[A-Z]/.test(val)) {
            message = "❌ First letter must be Uppercase";
            score = 2;
        } else if (!/\d/.test(val)) {
            message = "❌ Add at least one number";
            score = 3;
        } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) {
            message = "❌ Add a special character (!@#$)";
            score = 4;
        } else {
            message = "✅ Password is Strong";
            score = 5;
        }

        
        feedback.textContent = message;
        
        
        const colors = ['#f1f5f9', '#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#059669'];
        bar.style.width = (score * 20) + '%';
        bar.style.background = colors[score];
        
        
        feedback.style.color = (score === 5) ? "#059669" : "#ef4444";
    });
</script>