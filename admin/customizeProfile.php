<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION['admin_id'];

// Fetch current data
$stmt = $conn->prepare("SELECT username, email, img, password FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $confirmPwd = $_POST['confirm_password'];

    // Verify Password before updating
    if ($admin && password_verify($confirmPwd, $admin['password'])) {
        $fileName = $admin['img'];

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../images/upload_photos/';
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $fileName = "admin_" . $adminId . "_" . time() . "." . strtolower($extension);

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $fileName)) {
                if ($admin['img'] !== 'default.jpg' && file_exists($uploadDir . $admin['img'])) {
                    unlink($uploadDir . $admin['img']);
                }
            }
        }

        $updateStmt = $conn->prepare("UPDATE admins SET username = ?, email = ?, img = ? WHERE admin_id = ?");
        $updateStmt->bind_param("sssi", $newName, $newEmail, $fileName, $adminId);

        if ($updateStmt->execute()) {
            $_SESSION['admin_name'] = $newName;
            $_SESSION['admin_email'] = $newEmail;
            $_SESSION['admin_img'] = $fileName;
            header("Location: customizeProfile.php?status=success");
            exit();
        }
    } else {
        header("Location: customizeProfile.php?status=error&msg=Incorrect password");
        exit();
    }
}

$imagePath = (!empty($admin['img']) && file_exists('../images/upload_photos/' . $admin['img']))
    ? '../images/upload_photos/' . $admin['img']
    : '../images/default.jpg';

require_once __DIR__ . '/sidebar.php';
?>

<div class="custom-main-content">
    <div class="custom-edit-container">
        <h2>Edit Profile</h2>

        <form id="profileForm" action="customizeProfile.php" method="POST" enctype="multipart/form-data">
            <div class="custom-avatar-wrapper">
                <img src="<?= htmlspecialchars($imagePath) ?>" id="avatarPreview" class="avatar-img">
                <label for="fileInput" class="edit-icon-overlay">
                    <i class="fa fa-camera"></i>
                </label>
                <input type="file" id="fileInput" name="avatar" style="display:none" onchange="previewImage(this)">
            </div>

            <div class="custom-form-group">
                <label>
                    Full Name
                    <span class="label-edit" onclick="enableInput('nameInput')">
                        <i class="fa fa-edit"></i> EDIT
                    </span>
                </label>
                <input type="text" id="nameInput" name="name" value="<?= htmlspecialchars($admin['username']) ?>" readonly>
            </div>

            <div class="custom-form-group">
                <label>
                    Email Address
                    <span class="label-edit" onclick="enableInput('emailInput')">
                        <i class="fa fa-edit"></i> EDIT
                    </span>
                </label>
                <input type="email" id="emailInput" name="email" value="<?= htmlspecialchars($admin['email']) ?>" readonly>
            </div>

            <button type="button" class="btn btn-primary" onclick="showPwdModal()">Save Changes</button>
        </form>
    </div>
</div>

<div id="pwdModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-bottom: 10px;">Verify Identity</h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Please enter your password to confirm updates.</p>
        <input type="password" id="confirmPwd" placeholder="Current Password">
        <div class="modal-btns">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitWithPassword()">Confirm</button>
        </div>
    </div>
</div>

<script>
    function enableInput(id) {
        const el = document.getElementById(id);
        el.readOnly = false;
        el.focus();
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
            reader.readAsDataURL(input.files[0]);
        }
    }

    function showPwdModal() {
        document.getElementById('pwdModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('pwdModal').style.display = 'none';
    }

    function submitWithPassword() {
        const pwd = document.getElementById('confirmPwd').value;
        if (!pwd) return alert("Password required");

        const form = document.getElementById('profileForm');
        const hiddenPwd = document.createElement("input");
        hiddenPwd.type = "hidden";
        hiddenPwd.name = "confirm_password";
        hiddenPwd.value = pwd;

        form.appendChild(hiddenPwd);
        form.submit();
    }
</script>