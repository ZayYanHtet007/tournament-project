<?php
require_once "../partial/init.php";
include('header.php');

$userId = $_SESSION['user_id'];

// Fetch current user data
$stmt = mysqli_prepare($conn, "SELECT username, email, image, password FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    die("User not found");
}

/* ================= CONSOLIDATED UPDATE LOGIC ================= */
if (isset($_POST['save_all'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $input_password = $_POST['verify_password'];
    $newImage = $user['image'];

    // 1. Verify Password first
    if (password_verify($input_password, $user['password'])) {

        // 2. Handle Image if uploaded
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
            $file = $_FILES['avatar'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed)) {
                $target_dir = "images/"; // Fixed path
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $imageName = time() . "_" . basename($file['name']);
                if (move_uploaded_file($file['tmp_name'], $target_dir . $imageName)) {
                    $newImage = $imageName;
                }
            }
        }

        // 3. Update Database
        $update_stmt = $conn->prepare("UPDATE users SET username=?, email=?, image=? WHERE user_id=?");
        $update_stmt->bind_param("sssi", $username, $email, $newImage, $userId);

        if ($update_stmt->execute()) {
            echo "<script>alert('Profile updated successfully!'); window.location.href='userprofile.php';</script>";
            exit;
        } else {
            $error = "Update failed.";
        }
    } else {
        echo "<script>alert('Incorrect password! Changes denied.');</script>";
    }
}
?>

<style>
    :root {
        --riot-blue: #00f2ff;
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
        min-height: calc(100vh - 75px);
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        background-color: var(--gaming-dark);
        position: relative;
        overflow: hidden;
        /* The Grid */
        background-image:
            linear-gradient(rgba(0, 242, 255, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0, 242, 255, 0.05) 1px, transparent 1px);
        background-size: 50px 50px;
        background-position: center center;
    }

    /* INTENSIFIED BLUE ATMOSPHERIC LIGHTING */
    .profile-wrapper::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background:
            radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
                rgba(0, 242, 255, 0.25) 0%,
                transparent 60%),
            radial-gradient(circle at 0% 0%, rgba(0, 242, 255, 0.2) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(0, 242, 255, 0.2) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(0, 242, 255, 0.1) 0%, transparent 80%);
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
                rgba(0, 242, 255, 0.05) 50%,
                transparent 100%);
        background-size: 100% 4px;
        animation: scanline 10s linear infinite;
        z-index: 0;
        pointer-events: none;
    }

    @keyframes scanline {
        0% {
            transform: translateY(-100%);
        }

        100% {
            transform: translateY(100%);
        }
    }

    .profile-container {
        width: 100%;
        max-width: 450px;
        background: black;
        /* Solid Riot Dark */
        border: 1px solid var(--riot-border);
        padding: 50px 40px;
        position: relative;
        z-index: 1;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), inset 0 0 20px rgba(0, 242, 255, 0.05);
    }

    h2 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 3rem;
        line-height: 0.9;
        margin-bottom: 40px;
        letter-spacing: 1px;
        color: #fff;
        text-shadow: 2px 2px 0px var(--riot-blue);
    }

    .close-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 1.2rem;
        color: #fff;
        text-decoration: none;
        background: var(--riot-blue);
        width: 30px;
        height: 30px;
        text-align: center;
        line-height: 30px;
        transition: background 0.3s;
    }

    .close-btn:hover {
        background: #33f5ff;
    }

    .avatar-upload-wrapper {
        position: relative;
        width: 160px;
        height: 160px;
        margin: 0 auto 40px;
        cursor: pointer;
    }

    .avatar-preview {
        width: 100%;
        height: 100%;
        border: 2px solid var(--riot-blue);
        padding: 5px;
        background: #000;
        transition: 0.3s;
        object-fit: cover;
        box-shadow: 0 0 15px rgba(0, 242, 255, 0.3);
    }

    .avatar-upload-wrapper:hover .avatar-preview {
        filter: brightness(0.6);
        transform: scale(1.05);
        box-shadow: 0 0 25px rgba(0, 242, 255, 0.5);
    }

    .upload-hint {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-family: 'Bebas Neue';
        font-size: 1.2rem;
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
        margin-bottom: 25px;
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
    input[type="email"] {
        width: 100%;
        padding: 15px;
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
        border-bottom-color: var(--riot-blue);
    }

    .save-btn {
        width: 100%;
        padding: 20px;
        background: transparent;
        color: #fff;
        border: 1px solid var(--riot-blue);
        font-family: 'Bebas Neue';
        font-size: 1.5rem;
        letter-spacing: 2px;
        cursor: pointer;
        position: relative;
        z-index: 1;
        margin-top: 20px;
        transition: 0.3s;
    }

    .save-btn::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 0;
        height: 100%;
        background: var(--riot-blue);
        transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: -1;
    }

    .save-btn:hover {
        color: #000;
    }

    .save-btn:hover::before {
        width: 100%;
    }

    .status {
        text-align: center;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 20px;
        text-transform: uppercase;
    }

    .msg {
        color: #00ff99;
        text-shadow: 0 0 10px rgba(0, 255, 153, 0.3);
    }

    .err {
        color: var(--riot-blue);
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.9);
        display: none;
        /* Hidden by default */
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .password-card {
        background: #111;
        padding: 30px;
        border-top: 4px solid var(--riot-blue);
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
        background: var(--riot-blue);
        color: black;
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
</style>

<div class="profile-wrapper" id="bg-wrapper">
    <div class="profile-container">
        <h2>EDIT PREFERENCES</h2>

        <a href="organizerDashboard.php" class="close-btn">X</a>

        <?php if (isset($message)): ?>
            <p class="status msg"><?= $message ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="status err"><?= $error ?></p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="avatar-upload-wrapper" onclick="document.getElementById('file-input').click()">
                <img id="preview" src="images/<?= $user['image'] ?? 'default.png' ?>" class="avatar-preview">
                <div class="upload-hint">CHANGE IMAGE</div>
                <input type="file" name="avatar" id="file-input" onchange="previewImage(this)">
            </div>

            <div class="field">
                <label>User Name</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>">
            </div>

            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
            </div>

            <button type="button" class="save-btn" onclick="openModal()">SAVE CHANGES</button>

            <div id="passwordModal" class="modal-overlay">
                <div class="password-card">
                    <h3 style="color: var(--riot-blue); font-family:'Bebas Neue';">CONFIRM PASSWORD</h3>
                    <p style="font-size: 12px; color: #888; margin-bottom: 20px;">Please enter your password to save changes.</p>

                    <div class="field">
                        <input type="password" name="verify_password" id="modalPassword" placeholder="ENTER CURRENT PASSWORD">
                    </div>

                    <div class="modal-actions">
                        <button type="button" onclick="closeModal()" class="btn-cancel">CANCEL</button>
                        <button type="submit" name="save_all" class="btn-confirm">VERIFY & SAVE</button>
                    </div>
                </div>
            </div>
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

    function openModal() {
        document.getElementById('passwordModal').style.display = 'flex';
        document.getElementById('modalPassword').focus();
    }

    function closeModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }

    // Close modal if user clicks outside the card
    window.onclick = function(event) {
        let modal = document.getElementById('passwordModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>