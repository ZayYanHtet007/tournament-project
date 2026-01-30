<?php
session_start();
require_once __DIR__ . '/../database/dbConfig.php';

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'main_admin') {
    header("Location: adminDashboard.php");
    exit;
}

require_once __DIR__ . '/sidebar.php';

$message = "";
$messageType = "";
$username = $email = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin_action'])) {

    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = trim($_POST['role'] ?? '');
    $identityPw = $_POST['identity_password'] ?? '';

    $errors = [];
    $allowed_roles = ['admin', 'main_admin'];

    $adminId = $_SESSION['admin_id'];

    $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $adminData = $stmt->get_result()->fetch_assoc();

    if (!$adminData || !password_verify($identityPw, $adminData['password'])) {
        $errors[] = "Identity verification failed. Incorrect password.";
    }

    if (!$username || !$email || !$password || !$role) {
        $errors[] = "All fields are required.";
    }

    if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
        $errors[] = "Username must be 3–50 characters (letters, numbers, _ or -).";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }

    if (!in_array($role, $allowed_roles)) {
        $errors[] = "Invalid role selected.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if (empty($errors)) {

        // Check email
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Email already exists.";
        }

        // Check username
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
    }

    if (empty($errors)) {

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("
            INSERT INTO admins (username, email, password, role, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $role);

        if ($stmt->execute()) {
            $message = "Admin account created successfully.";
            $messageType = "success";
            $username = $email = "";
        } else {
            $message = "Database error. Please try again.";
            $messageType = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $messageType = "error";
    }
}
?>

<div class="custom-main-wrapper">
    <div class="admin-create-container">
        <h2>Create New Admin</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <div class="alert-content">
                    <span><?= $message ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.parentElement.style.display='none'">
                        ×
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" id="createAdminForm">
            <input type="hidden" name="create_admin_action" value="1">

            <div class="adminCreate">
                <label>Username *</label>
                <input type="text" name="username" class="glass-input"
                    value="<?= htmlspecialchars($username) ?>" required>
            </div>

            <div class="adminCreate">
                <label>Email *</label>
                <input type="email" name="email" class="glass-input"
                    value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="adminCreate">
                <label>Assign Role</label>
                <select name="role" class="glass-input role-select" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="main_admin">Main Admin</option>
                </select>
            </div>

            <div class="adminCreate">
                <label>Password *</label>
                <input type="password" name="password" class="glass-input" required minlength="8">
            </div>

            <div class="changepwdactions">
                <a href="manageAdmin.php" class="btn-discard">Cancel</a>
                <button type="button" class="btn-primary" onclick="openModal()">Create Admin</button>
            </div>
        </form>

        
    </div>
</div>

<div id="identityModal" class="modal">
    <div class="modal-content">
        <h3>Verify Identity</h3>
        <p>Enter <b>your password</b> to confirm this action.</p>

        <input type="password" id="identityPassword" class="glass-input"
            placeholder="Your password">

        <div class="modal-btns">
            <button class="btn-discard" onclick="closeModal()">Cancel</button>
            <button class="btn-primary" onclick="submitForm()">Confirm</button>
        </div>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('identityModal').style.display = 'flex';
        document.getElementById('identityPassword').focus();
    }

    function closeModal() {
        document.getElementById('identityModal').style.display = 'none';
        document.getElementById('identityPassword').value = '';
    }

    function submitForm() {
        const pw = document.getElementById('identityPassword').value;
        if (!pw) {
            alert("Password required.");
            return;
        }

        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "identity_password";
        input.value = pw;

        document.getElementById('createAdminForm').appendChild(input);
        document.getElementById('createAdminForm').submit();
    }
</script>