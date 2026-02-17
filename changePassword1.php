<?php
include('partial/header.php');
require_once "partial/init.php";

$userId = $_SESSION['user_id'];

// Fetch current user data
$stmt = mysqli_prepare($conn, "SELECT username, email, image, password FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    die("User not found");
}

$profile_message = "";
$profile_error = "";
$password_message = "";
$password_error = "";

/* ================= PASSWORD CHANGE LOGIC ================= */
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validation
    if ($current === '' || $new === '' || $confirm === '') {
        $password_error = "All fields are required!";
    } elseif ($new !== $confirm) {
        $password_error = "Passwords do not match!";
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $password_error = "At least one uppercase letter required!";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new)) {
        $password_error = "At least one special character required!";
    } elseif (!preg_match('/[0-9]/', $new)) {
        $password_error = "At least one number required!";
    } elseif (strlen($new) < 8) {
        $password_error = "Password must be at least 8 characters!";
    } else {
        // Verify current password
        if (password_verify($current, $user['password'])) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->bind_param("si", $hashed, $userId);
            if ($update->execute()) {
                $password_message = "Password updated successfully!";
            } else {
                $password_error = "Database error. Please try again.";
            }
        } else {
            $password_error = "Current password is incorrect!";
        }
    }
}
?>

<style>
    :root {
        --riot-red: #ff4d5a;
        --riot-bg: #0f1923;
        --riot-white: #ece8e1;
        --riot-border: rgba(255, 255, 255, 0.15);
        --gaming-dark: #080a0c;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    /* CYBER-GRID ANIMATED BACKGROUND */
    .profile-wrapper {
        min-height: calc(100vh);
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        background-color: var(--gaming-dark);
        position: relative;
        overflow: hidden;
        background-image:
            linear-gradient(rgba(255, 70, 85, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 70, 85, 0.05) 1px, transparent 1px);
        background-size: 50px 50px;
        background-position: center center;
    }

    /* INTENSIFIED RED ATMOSPHERIC LIGHTING */
    .profile-wrapper::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background:
            radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
                rgba(255, 70, 85, 0.25) 0%,
                transparent 60%),
            radial-gradient(circle at 0% 0%, rgba(255, 0, 0, 0.2) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(255, 0, 0, 0.2) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(255, 0, 0, 0.1) 0%, transparent 80%);
        z-index: 0;
        pointer-events: none;
    }

    /* Animated Scanline */
    .profile-wrapper::after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(to bottom,
                transparent 0%,
                rgba(255, 70, 85, 0.05) 50%,
                transparent 100%);
        background-size: 100% 4px;
        animation: scanline 10s linear infinite;
        z-index: 0;
        pointer-events: none;
    }

    @keyframes scanline {
        0% { transform: translateY(-100%); }
        100% { transform: translateY(100%); }
    }

    .profile-container {
        width: 100%;
        max-width: 500px;
        background: black;
        border: 1px solid var(--riot-border);
        padding: 40px 35px;
        position: relative;
        z-index: 1;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), inset 0 0 20px rgba(255, 70, 85, 0.05);
    }

    h2 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 2.8rem;
        line-height: 0.9;
        margin-bottom: 30px;
        letter-spacing: 1px;
        color: #fff;
        text-shadow: 2px 2px 0px var(--riot-red);
    }

    h3 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 1.8rem;
        margin: 30px 0 20px;
        color: #fff;
        border-bottom: 1px solid var(--riot-border);
        padding-bottom: 10px;
    }

    .close-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 1.2rem;
        color: #fff;
        text-decoration: none;
        background: var(--riot-red);
        width: 30px;
        height: 30px;
        text-align: center;
        line-height: 30px;
        transition: background 0.3s;
    }

    .close-btn:hover {
        background: #ff6877;
    }

    .avatar-upload-wrapper {
        position: relative;
        width: 140px;
        height: 140px;
        margin: 0 auto 30px;
        cursor: pointer;
    }

    .avatar-preview {
        width: 100%;
        height: 100%;
        border: 2px solid var(--riot-red);
        padding: 3px;
        background: #000;
        transition: 0.3s;
        object-fit: cover;
        box-shadow: 0 0 15px rgba(255, 70, 85, 0.3);
    }

    .avatar-upload-wrapper:hover .avatar-preview {
        filter: brightness(0.6);
        transform: scale(1.05);
        box-shadow: 0 0 25px rgba(255, 70, 85, 0.5);
    }

    .upload-hint {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-family: 'Bebas Neue';
        font-size: 1.1rem;
        opacity: 0;
        transition: 0.3s;
        pointer-events: none;
        white-space: nowrap;
        color: #fff;
    }

    .avatar-upload-wrapper:hover .upload-hint {
        opacity: 1;
    }

    #file-input {
        display: none;
    }

    .field {
        margin-bottom: 20px;
    }

    label {
        display: block;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, 0.4);
        letter-spacing: 1.5px;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 14px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid transparent;
        border-bottom: 2px solid var(--riot-border);
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        transition: 0.3s;
    }

    input:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.07);
        border-bottom-color: var(--riot-red);
    }

    /* Password strength bar */
    .strength-box {
        margin-top: 8px;
    }
    .bar-container {
        width: 100%;
        height: 4px;
        background: #222;
        border-radius: 2px;
        overflow: hidden;
    }
    .bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s ease;
        border-radius: 2px;
    }
    #validation-feedback {
        font-size: 11px;
        margin-top: 5px;
        text-transform: uppercase;
        font-weight: 600;
    }

    .btn {
        display: inline-block;
        padding: 12px 24px;
        font-family: 'Bebas Neue';
        font-size: 1.3rem;
        letter-spacing: 1px;
        cursor: pointer;
        border: none;
        transition: 0.3s;
        text-transform: uppercase;
    }

    .save-btn, .password-btn {
        width: 100%;
        padding: 16px;
        background: transparent;
        color: #fff;
        border: 1px solid var(--riot-red);
        position: relative;
        z-index: 1;
        margin-top: 10px;
        font-size: 1.4rem;
    }

    .save-btn::before, .password-btn::before {
        content: "";
        position: absolute;
        top: 0; left: 0;
        width: 0; height: 100%;
        background: var(--riot-red);
        transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: -1;
    }

    .save-btn:hover, .password-btn:hover {
        color: #000;
    }
    .save-btn:hover::before, .password-btn:hover::before {
        width: 100%;
    }

    .status {
        text-align: center;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 20px;
        text-transform: uppercase;
    }
    .msg { color: #00ff99; }
    .err { color: var(--riot-red); }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.9);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .password-card {
        background: #111;
        padding: 30px;
        border-top: 4px solid #ff4655;
        width: 100%;
        max-width: 400px;
        text-align: center;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .btn-confirm {
        background: #ff4655;
        color: white;
        border: none;
        padding: 10px;
        flex: 1;
        cursor: pointer;
    }

    .btn-cancel {
        background: #222;
        color: #888;
        border: 1px solid #333;
        padding: 10px;
        flex: 1;
        cursor: pointer;
    }

    hr {
        border: 0.5px solid var(--riot-border);
        margin: 30px 0 20px;
    }
</style>

<div class="profile-wrapper" id="bg-wrapper">
    <div class="profile-container">
        <!-- Change Password Section -->
        <h3>CHANGE PASSWORD</h3>
        <form method="post">
            <?php if (!empty($password_message)): ?>
                <p class="status msg"><?= $password_message ?></p>
            <?php endif; ?>
            <?php if (!empty($password_error)): ?>
                <p class="status err"><?= $password_error ?></p>
            <?php endif; ?>

            <div class="field">
                <label>Current Password</label>
                <input type="password" name="current_password" id="current" placeholder="••••••••" required>
            </div>

            <div class="field">
                <label>New Password</label>
                <input type="password" name="new_password" id="new" placeholder="Enter new password" required>
                
            </div>

            <div class="field">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm" placeholder="Confirm new password" required>
                <div class="strength-box">
                    <div class="bar-container">
                        <div id="strength-bar" class="bar"></div>
                    </div>
                    <p id="validation-feedback" style="font-size: 12px; margin-top: 8px;"></p>
                </div>
            </div>

            <button type="submit" name="change_password" class="password-btn">UPDATE PASSWORD</button>
        </form>
    </div>
</div>

<script>
    // Preview image logic
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Interactive Background Glow
    const wrapper = document.getElementById('bg-wrapper');
    wrapper.addEventListener('mousemove', e => {
        const rect = wrapper.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        wrapper.style.setProperty('--mouse-x', x + '%');
        wrapper.style.setProperty('--mouse-y', y + '%');
    });

    // Modal functions
    function openModal() {
        document.getElementById('passwordModal').style.display = 'flex';
        document.getElementById('modalPassword').focus();
    }
    function closeModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }
    window.onclick = function(event) {
        let modal = document.getElementById('passwordModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Password strength meter (for change password)
    const newInput = document.getElementById('new');
    const bar = document.getElementById('strength-bar');
    const feedback = document.getElementById('validation-feedback');

    if (newInput) {
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

            if (val.length < 8) {
                message = "❌ Too short (min 8)";
                score = 1;
                gradient = "linear-gradient(90deg, #ff4d4d, #ff9e4d)";
            } else if (!/[A-Z]/.test(val)) {
                message = "❌ Need uppercase";
                score = 2;
                gradient = "linear-gradient(90deg, #ff9e4d, #ffcf4d)";
            } else if (!/[0-9]/.test(val)) {
                message = "❌ Need number";
                score = 3;
                gradient = "linear-gradient(90deg, #ffcf4d, #4dff88)";
            } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) {
                message = "❌ Need special character";
                score = 4;
                gradient = "linear-gradient(90deg, #4dff88, #00e676)";
            } else {
                message = "✅ Strong password";
                score = 5;
                gradient = "linear-gradient(90deg, #00e676, #00c853)";
            }

            feedback.textContent = message;
            bar.style.width = (score * 20) + '%';
            bar.style.background = gradient;
            bar.style.boxShadow = `0 0 10px ${score > 3 ? 'rgba(0, 230, 118, 0.3)' : 'rgba(255, 77, 77, 0.2)'}`;
            feedback.style.color = (score === 5) ? "#00c853" : "#ff4d4d";
        });
    }
</script>