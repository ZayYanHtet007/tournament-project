
<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';


if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION['admin_id'];

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $confirmPwd = $_POST['confirm_password'];

    
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
                }
            }
        }

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
        exit();
    }
}


require_once __DIR__ . '/sidebar.php';
?>

<div class="custom-main-content">
    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'success'): ?>
            
        <?php elseif ($_GET['status'] == 'error'): ?>
            <div class="alert alert-danger">Error: <?= htmlspecialchars($_GET['msg'] ?? 'Unknown error') ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="custom-edit-container">
        <h2>Edit Profile</h2>

        <form id="profileForm" action="customizeProfile.php" method="POST" enctype="multipart/form-data">

            <div class="custom-avatar-wrapper" style="position: relative; width: 150px; margin: 0 auto 20px;">
                <img src="<?= htmlspecialchars($imagePath) ?>" id="avatarPreview" class="avatar-img" style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%;">

                <label for="fileInput" class="edit-icon-overlay" style="position: absolute; bottom: 0; right: 0; background: #007bff; padding: 8px; border-radius: 50%; color: white; cursor: pointer;">
                    <i class="fa fa-camera"></i>
                </label>
                <input type="file" id="fileInput" name="avatar" style="display:none" onchange="previewImage(this)">
            </div>

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
        </form>
    </div>
</div>

<div id="pwdModal" class="modal">
    <div class="modal-content">
        <h3>Verify Identity</h3>
        <p style="color: #6b7280; font-size: 14px;">Confirm password to save changes.</p>
        <input type="password" id="confirmPwd" class="form-control mb-3" placeholder="Current Password" autocomplete="off">
        <div class="modal-btns">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="checkFinal()" style="margin: 0;">Confirm</button>
        </div>
    </div>
</div>

<script>
    
    function enableInput(id) {
        const input = document.getElementById(id);
        input.readOnly = false;
        input.classList.remove('form-control-plaintext'); 
        input.focus();
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function showPwdModal() {
        document.getElementById('pwdModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('pwdModal').style.display = 'none';
    }

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
        form.submit();
    }
</script>