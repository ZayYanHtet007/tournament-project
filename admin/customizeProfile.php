<<<<<<< HEAD

=======
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

<<<<<<< HEAD

=======
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION['admin_id'];

<<<<<<< HEAD
$stmt = $conn->prepare("SELECT username, email, img FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result();
$currAdmin = $res->fetch_assoc();

$adminName = $currAdmin['username'] ?? $_SESSION['admin_name'];
$adminEmail = $currAdmin['email'] ?? $_SESSION['admin_email'];
$adminImg = $currAdmin['img'] ?? ($_SESSION['admin_img'] ?? 'default.jpg');


$imagePath = (!empty($adminImg) && file_exists('../images/upload_photos/' . $adminImg))
    ? '../images/upload_photos/' . $adminImg
    : '../images/default.jpg';

// --- HANDLE FORM SUBMISSION ---
=======
// Fetch current data
$stmt = $conn->prepare("SELECT username, email, img, password FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle Form Submission
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $confirmPwd = $_POST['confirm_password'];

<<<<<<< HEAD
    
    $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($confirmPwd, $admin['password'])) {
        $fileName = $adminImg; 

        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../images/upload_photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
            $maxSize = 5 * 1024 * 1024; 

            if (!in_array($fileType, $allowedTypes)) {
                $errorMsg = "Invalid image type.";
            } elseif ($_FILES['avatar']['size'] > $maxSize) {
                $errorMsg = "File too large.";
            } else {
                
                if ($adminImg !== 'default.jpg' && file_exists($uploadDir . $adminImg)) {
                    unlink($uploadDir . $adminImg);
                }

                $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $fileName = "admin_" . $adminId . "_" . time() . "." . strtolower($extension);
                $targetFile = $uploadDir . $fileName;

                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                    $errorMsg = "Failed to move uploaded file.";
=======
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
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
                }
            }
        }

<<<<<<< HEAD
        if (!isset($errorMsg)) {
            
            $updateStmt = $conn->prepare("UPDATE admins SET username = ?, email = ?, img = ? WHERE admin_id = ?");
            $updateStmt->bind_param("sssi", $newName, $newEmail, $fileName, $adminId);

            if ($updateStmt->execute()) {
                
                $_SESSION['admin_name'] = $newName;
                $_SESSION['admin_email'] = $newEmail;
                $_SESSION['admin_img'] = $fileName;

                
                header("Location: customizeProfile.php?status=success");
                exit();
            } else {
                $errorMsg = "Database update failed.";
            }
        }
    } else {
        $errorMsg = "Incorrect password.";
    }

    
    if (isset($errorMsg)) {
        header("Location: customizeProfile.php?status=error&msg=" . urlencode($errorMsg));
=======
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
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
        exit();
    }
}

<<<<<<< HEAD
=======
$imagePath = (!empty($admin['img']) && file_exists('../images/upload_photos/' . $admin['img']))
    ? '../images/upload_photos/' . $admin['img']
    : '../images/default.jpg';
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c

require_once __DIR__ . '/sidebar.php';
?>

<div class="custom-main-content">
<<<<<<< HEAD
    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'success'): ?>
            
        <?php elseif ($_GET['status'] == 'error'): ?>
            <div class="alert alert-danger">Error: <?= htmlspecialchars($_GET['msg'] ?? 'Unknown error') ?></div>
        <?php endif; ?>
    <?php endif; ?>

=======
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
    <div class="custom-edit-container">
        <h2>Edit Profile</h2>

        <form id="profileForm" action="customizeProfile.php" method="POST" enctype="multipart/form-data">
<<<<<<< HEAD

            <div class="custom-avatar-wrapper" style="position: relative; width: 150px; margin: 0 auto 20px;">
                <img src="<?= htmlspecialchars($imagePath) ?>" id="avatarPreview" class="avatar-img" style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%;">

                <label for="fileInput" class="edit-icon-overlay" style="position: absolute; bottom: 0; right: 0; background: #007bff; padding: 8px; border-radius: 50%; color: white; cursor: pointer;">
=======
            <div class="custom-avatar-wrapper">
                <img src="<?= htmlspecialchars($imagePath) ?>" id="avatarPreview" class="avatar-img">
                <label for="fileInput" class="edit-icon-overlay">
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
                    <i class="fa fa-camera"></i>
                </label>
                <input type="file" id="fileInput" name="avatar" style="display:none" onchange="previewImage(this)">
            </div>

<<<<<<< HEAD
            <div class="custom-form-group mb-3">
                <label class="form-label d-flex justify-content-between">
                    Full Name
                    <span class="label-edit" style="cursor:pointer; color: blue;" onclick="enableInput('nameInput')"><i class="fa fa-pencil"></i> Edit</span>
                </label>
                <input type="text" class="form-control" id="nameInput" name="name" value="<?= htmlspecialchars($adminName) ?>" readonly>
            </div>

            <div class="custom-form-group mb-3">
                <label class="form-label d-flex justify-content-between">
                    Email Address
                    <span class="label-edit" style="cursor:pointer; color: blue;" onclick="enableInput('emailInput')"><i class="fa fa-pencil"></i> Edit</span>
                </label>
                <input type="email" class="form-control" id="emailInput" name="email" value="<?= htmlspecialchars($adminEmail) ?>" readonly>
            </div>

            <button type="button" class="btn btn-primary w-100" onclick="showPwdModal()">Save Changes</button>
=======
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
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
        </form>
    </div>
</div>

<div id="pwdModal" class="modal">
    <div class="modal-content">
<<<<<<< HEAD
        <h3>Verify Identity</h3>
        <p style="color: #6b7280; font-size: 14px;">Confirm password to save changes.</p>
        <input type="password" id="confirmPwd" class="form-control mb-3" placeholder="Current Password" autocomplete="off">
        <div class="modal-btns">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="checkFinal()" style="margin: 0;">Confirm</button>
=======
        <h3 style="margin-bottom: 10px;">Verify Identity</h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">Please enter your password to confirm updates.</p>
        <input type="password" id="confirmPwd" placeholder="Current Password">
        <div class="modal-btns">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitWithPassword()">Confirm</button>
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
        </div>
    </div>
</div>

<script>
<<<<<<< HEAD
    
    function enableInput(id) {
        const input = document.getElementById(id);
        input.readOnly = false;
        input.classList.remove('form-control-plaintext'); 
        input.focus();
=======
    function enableInput(id) {
        const el = document.getElementById(id);
        el.readOnly = false;
        el.focus();
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
<<<<<<< HEAD
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
            }
=======
            const reader = new FileReader();
            reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
            reader.readAsDataURL(input.files[0]);
        }
    }

    function showPwdModal() {
        document.getElementById('pwdModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('pwdModal').style.display = 'none';
    }

<<<<<<< HEAD
    function checkFinal() {
        const pwd = document.getElementById('confirmPwd').value;
        if (!pwd) {
            alert("Please enter your password.");
            return;
        }

        let form = document.getElementById('profileForm');


        let oldInput = document.getElementById('hidden-pwd');
        if (oldInput) oldInput.remove();

        let pwdInput = document.createElement("input");
        pwdInput.type = "hidden";
        pwdInput.id = "hidden-pwd";
        pwdInput.name = "confirm_password";
        pwdInput.value = pwd;
        form.appendChild(pwdInput);
=======
    function submitWithPassword() {
        const pwd = document.getElementById('confirmPwd').value;
        if (!pwd) return alert("Password required");

        const form = document.getElementById('profileForm');
        const hiddenPwd = document.createElement("input");
        hiddenPwd.type = "hidden";
        hiddenPwd.name = "confirm_password";
        hiddenPwd.value = pwd;

        form.appendChild(hiddenPwd);
>>>>>>> 674794ea9479042f0773ec00c2a16743816f3d1c
        form.submit();
    }
</script>