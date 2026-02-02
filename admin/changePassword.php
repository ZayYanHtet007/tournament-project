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
<div class="custom-main-wrapper">
    <div class="chgpwdcontainer">
        <h2>Change Password</h2>
        <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 30px;">
            Update your account security settings.
        </p>

        <?php if ($alert != ""): ?>
            <div class="alert-msg <?= $alertType === 'success' ? 'alert-success' : 'alert-error' ?>"
                style="border-radius: 12px; padding: 15px; margin-bottom: 20px; font-weight: 600;">
                <?= $alert ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="changefield">
                <label>Current Password</label>
                <div class="password-wrap">
                    <input type="password" name="current" id="current" placeholder="••••••••" required>
                </div>
            </div>

            <div class="changefield">
                <label>New Password</label>
                <div class="password-wrap">
                    <input type="password" name="new" id="new" placeholder="Enter new password" required>
                </div>
                <div class="strength-box">
                    <div class="bar-container">
                        <div id="strength-bar" class="bar"></div>
                    </div>
                    <p id="validation-feedback" style="font-size: 12px; margin-top: 8px;"></p>
                </div>
            </div>

            <div class="changefield">
                <label>Confirm Password</label>
                <div class="password-wrap">
                    <input type="password" name="confirm" id="confirm" placeholder="Confirm new password" required>
                </div>
            </div>

            <div class="changepwdactions">
                <button type="reset" class="btn-discard">Discard</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>
    const newInput = document.getElementById('new');
    const bar = document.getElementById('strength-bar');
    const feedback = document.getElementById('validation-feedback');

    newInput.addEventListener('input', () => {
        const val = newInput.value;
        let message = "";
        let score = 0;
        let gradient = "";

        if (val === "") {
            feedback.textContent = "";
            bar.style.width = "0%";
            return;
        }

        // Logic for strength score
        if (val.length < 8) {
            message = "❌ Too Short";
            score = 1;
            gradient = "linear-gradient(90deg, #ff4d4d, #ff9e4d)"; // Red to Orange
        } else if (!/^[A-Z]/.test(val)) {
            message = "❌ Must start with Uppercase";
            score = 2;
            gradient = "linear-gradient(90deg, #ff9e4d, #ffcf4d)"; // Orange to Yellow
        } else if (!/\d/.test(val)) {
            message = "❌ Add a number";
            score = 3;
            gradient = "linear-gradient(90deg, #ffcf4d, #4dff88)"; // Yellow to Light Green
        } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) {
            message = "❌ Add a special character";
            score = 4;
            gradient = "linear-gradient(90deg, #4dff88, #00e676)"; 
        } else {
            message = "✅ Password is Strong";
            score = 5;
            gradient = "linear-gradient(90deg, #00e676, #00c853)"; 
        }

        feedback.textContent = message;
        bar.style.width = (score * 20) + '%';
        bar.style.background = gradient;
        bar.style.boxShadow = `0 0 10px ${score > 3 ? 'rgba(0, 230, 118, 0.3)' : 'rgba(255, 77, 77, 0.2)'}`;
        feedback.style.color = (score === 5) ? "#00c853" : "#ff4d4d";
    });
</script>