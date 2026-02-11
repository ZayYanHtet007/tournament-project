<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "database/dbConfig.php";

$error = "";
$success = "";

if (isset($_POST['btnsave'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirmPassword'];
    $isOrganizerChecked = isset($_POST['isOrganizer']);
    
    $profile_image = "default.png"; // Default fallback

    // File upload logic 
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $imageName = time() . "_" . basename($_FILES['profile_image']['name']);
        $tmp = $_FILES['profile_image']['tmp_name'];
        $uploadDir = __DIR__ . "/images/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $path = $uploadDir . $imageName;

        if (move_uploaded_file($tmp, $path)) {
            $profile_image = $imageName;
        } else {
            $error = "Image upload failed.";
        }
    }

    if (empty($error)) {
        if (strlen($username) < 3) {
            $error = "Username must be at least 3 characters";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match";
        } else {
            $check = "SELECT user_id FROM users WHERE username = ? OR email = ?";
            $stmt = mysqli_prepare($conn, $check);
            mysqli_stmt_bind_param($stmt, "ss", $username, $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "Username or Email already exists";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $is_organizer = $isOrganizerChecked ? 1 : 0;
                $organizer_status = $isOrganizerChecked ? "pending" : NULL;

                $sql = "INSERT INTO users (username, email, password, is_organizer, organizer_status, image) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssiss", $username, $email, $hashed_password, $is_organizer, $organizer_status, $profile_image);

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: login.php?msg=signup_success");
                    exit();
                } else {
                    $error = "Signup failed. Please try again.";
                }
            }
        }
    }
}
include('partial/header.php');
?>

<style>
    :root {
        --primary-red: #ff3344;
        --sidebar-w: 80px;
        --riot-dark: #080a0c;
    }

    body {
        background-color: var(--riot-dark) !important;
        margin: 0;
        overflow-x: hidden;
        position: relative;
        /* CYBER GRID BACKGROUND */
        background-image: 
            linear-gradient(rgba(255, 50, 50, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 50, 50, 0.05) 1px, transparent 1px);
        background-size: 50px 50px;
        background-attachment: fixed;
    }

    /* INTERACTIVE MOUSE GLOW */
    body::before {
        content: "";
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                    rgba(255, 50, 68, 0.12) 0%, 
                    transparent 50%);
        z-index: -1;
        pointer-events: none;
    }

    /* GLOBAL ANIMATED SCANLINE */
    body::after {
        content: "";
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: linear-gradient(
            to bottom,
            transparent 0%,
            rgba(255, 51, 68, 0.03) 50%,
            transparent 100%
        );
        background-size: 100% 4px;
        animation: globalScanline 10s linear infinite;
        pointer-events: none;
        z-index: 0;
    }

    @keyframes globalScanline {
        0% { transform: translateY(-100%); }
        100% { transform: translateY(100%); }
    }

    .signup-main-wrapper {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        margin-left: var(--sidebar-w);
        position: relative;
        z-index: 1;
    }

    .signup-panel {
        background: rgba(10, 10, 10, 0.95);
        backdrop-filter: blur(10px);
        width: 100%;
        max-width: 850px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-top: 4px solid var(--primary-red);
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        display: flex;
        overflow: hidden;
    }

    /* LEFT SIDE: PHOTO SECTION */
    .signup-left {
        flex: 1;
        background: rgba(255, 51, 68, 0.02);
        border-right: 1px solid #222;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px;
    }

    .photo-preview-box {
        width: 180px;
        height: 180px;
        border: 1px solid #333;
        background: #050505;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        margin-bottom: 20px;
        transition: 0.3s;
    }

    .photo-preview-box::before {
        content: '';
        position: absolute;
        top: -5px;
        left: -5px;
        width: 20px;
        height: 20px;
        border-top: 2px solid var(--primary-red);
        border-left: 2px solid var(--primary-red);
    }

    .photo-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-preview-box i {
        color: #222;
        font-size: 60px;
    }

    .file-input-wrapper {
        position: relative;
        width: 100%;
        text-align: center;
    }

    .file-label-custom {
        display: block;
        padding: 10px;
        background: #111;
        border: 1px solid #333;
        color: #888;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        cursor: pointer;
        transition: 0.3s;
    }

    .file-label-custom:hover {
        border-color: var(--primary-red);
        color: #fff;
    }

    /* RIGHT SIDE: FORM SECTION */
    .signup-right {
        flex: 1.5;
        padding: 50px;
    }

    .signup-right h1 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 38px;
        color: #fff;
        margin: 0;
        letter-spacing: 2px;
    }

    .tagline {
        color: #666;
        font-size: 10px;
        text-transform: uppercase;
        margin-bottom: 30px;
        letter-spacing: 2px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .full-row {
        grid-column: span 2;
    }

    .form-field-signup label {
        display: block;
        color: var(--primary-red);
        font-size: 10px;
        font-weight: 900;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .form-field-signup input {
        width: 100%;
        padding: 12px;
        background: #0a0a0a;
        border: 1px solid #333;
        color: #fff;
        font-family: monospace;
        transition: 0.3s;
    }

    .form-field-signup input:focus {
        border-color: var(--primary-red);
        outline: none;
        background: #110505;
        box-shadow: 0 0 10px rgba(255, 51, 68, 0.1);
    }

    .organizer-toggle-card {
        margin-top: 25px;
        padding: 20px;
        background: #0d0d0d;
        border: 1px solid #222;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: 0.3s;
    }

    .organizer-toggle-card:has(input:checked) {
        border-color: var(--primary-red);
        background: rgba(255, 51, 68, 0.05);
    }

    .org-text h4 {
        color: #fff;
        margin: 0;
        font-size: 14px;
        text-transform: uppercase;
    }

    .org-text p {
        color: #555;
        margin: 0;
        font-size: 10px;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .switch input { opacity: 0; width: 0; height: 0; }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #333;
        transition: .4s;
        border-radius: 2px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 2px;
    }

    input:checked+.slider { background-color: var(--primary-red); }
    input:checked+.slider:before { transform: translateX(26px); }

    .btn-signup-red {
        width: 100%;
        padding: 18px;
        background: var(--primary-red);
        color: #fff;
        border: none;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 24px;
        text-transform: uppercase;
        cursor: pointer;
        margin-top: 30px;
        transition: 0.3s;
    }

    .btn-signup-red:hover {
        filter: brightness(1.2);
        box-shadow: 0 0 20px rgba(255, 51, 68, 0.4);
    }

    .btn-cancel {
        display: block;
        text-align: center;
        margin-top: 15px;
        color: #444;
        text-decoration: none;
        font-size: 10px;
        text-transform: uppercase;
    }

    @media (max-width: 850px) {
        .signup-panel { flex-direction: column; }
        .signup-main-wrapper { margin-left: 0; }
        .form-grid { grid-template-columns: 1fr; }
        .full-row { grid-column: span 1; }
    }
</style>

<main class="signup-main-wrapper">
    <div class="signup-panel">
        <form method="POST" enctype="multipart/form-data" style="display: contents;">
            <div class="signup-left">
                <div class="photo-preview-box" id="imagePreview">
                    <i class="fas fa-user-secret"></i>
                </div>
                <div class="file-input-wrapper">
                    <label for="profileInput" class="file-label-custom">Upload Identity</label>
                    <input type="file" name="profile_image" id="profileInput" accept="image/*" onchange="previewImage(event)" style="display:none">
                </div>
                <p style="color: #444; font-size: 9px; margin-top: 20px; text-align: center;">IMAGE WILL BE USED FOR<br>GLOBAL RANKING PROFILES</p>
            </div>

            <div class="signup-right">
                <h1>JOIN THE <span style="color: var(--primary-red);">ARENA</span></h1>
                <p class="tagline">Creating Account</p>

                <?php if ($error): ?>
                    <div style="color:var(--primary-red); font-size:11px; margin-bottom:15px; background: rgba(255,0,0,0.1); padding: 10px; border-left: 3px solid var(--primary-red);">[ERR] <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-field-signup full-row">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="YOUR NAME" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-field-signup full-row">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="TOURNX@GMAIL.COM" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="form-field-signup">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>

                    <div class="form-field-signup">
                        <label>Confirm Password</label>
                        <input type="password" name="confirmPassword" required>
                    </div>
                </div>

                <div class="organizer-toggle-card">
                    <div class="org-text">
                        <h4>For Organizer Account</h4>
                        <p>Apply for tournament creation permissions</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="isOrganizer" id="orgCheck" <?= isset($_POST['isOrganizer']) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <button type="submit" name="btnsave" class="btn-signup-red">Create Account</button>
                <a href="login.php" class="btn-cancel">Return to Login</a>
            </div>
        </form>
    </div>
</main>

<script>
    // MOUSE GLOW TRACKING
    window.addEventListener('mousemove', e => {
        const x = (e.clientX / window.innerWidth) * 100;
        const y = (e.clientY / window.innerHeight) * 100;
        document.body.style.setProperty('--mouse-x', x + '%');
        document.body.style.setProperty('--mouse-y', y + '%');
    });

    function previewImage(event) {
        var reader = new FileReader();
        reader.onload = function() {
            var output = document.getElementById('imagePreview');
            output.innerHTML = '<img src="' + reader.result + '">';
            output.style.borderColor = "var(--primary-red)";
        }
        if(event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }
</script>

<?php include('partial/footer.php'); ?>