<?php
require_once "partial/init.php"; // this now defines $pdo

$userId = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT username, email, image FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));


if (!$user) {
    die("User not found");
}

/* ================= UPDATE PROFILE ================= */
if (isset($_POST['update_profile'])) {

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);

    if ($username === "" || $email === "") {
        $error = "Username and Email are required";
    } else {
        $stmt = $pdo->prepare(
            "UPDATE users SET username = ?, email = ?, last_update = NOW() WHERE user_id = ?"
        );
        $stmt->execute([$username, $email, $userId]);
        $message = "Profile updated successfully";
    }
}

/* ================= UPDATE PASSWORD ================= */
if (isset($_POST['update_password'])) {

    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = "Current password is incorrect";
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "UPDATE users SET password = ?, last_update = NOW() WHERE user_id = ?"
        );
        $stmt->execute([$newHash, $userId]);
        $message = "Password changed successfully";
    }
}

/* ================= IMAGE UPLOAD ================= */
if (isset($_POST['update_image']) && isset($_FILES['avatar'])) {

    $file = $_FILES['avatar'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed)) {
        $error = "Invalid image format";
    } else {
        $name = "user_" . $userId . "_" . time() . "." . $ext;
        move_uploaded_file($file['tmp_name'], "uploads/" . $name);

        $stmt = $pdo->prepare(
            "UPDATE users SET image = ?, last_update = NOW() WHERE user_id = ?"
        );
        $stmt->execute([$name, $userId]);
        $message = "Profile image updated";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customize Profile</title>

<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="css/user/responsive.css">

<style>
:root {
    --neon: #00ffff;
    --danger: #ff2e2e;
}

body {
    background: radial-gradient(circle at top, #0b0b0b, #000);
    font-family: 'Orbitron', sans-serif;
    color: white;
}

.profile-box {
    width: 420px;
    margin: 60px auto;
    padding: 25px;
    background: rgba(15,15,15,0.9);
    border: 1px solid var(--neon);
    box-shadow: 0 0 25px var(--neon);
}

h2 {
    text-align: center;
    color: var(--neon);
    margin-bottom: 20px;
}

input {
    width: 100%;
    padding: 10px;
    background: black;
    border: 1px solid var(--neon);
    color: white;
    margin-bottom: 10px;
}

button {
    width: 100%;
    padding: 10px;
    background: var(--neon);
    border: none;
    font-weight: bold;
    cursor: pointer;
}

.avatar {
    width: 120px;
    height: 120px;
    margin: 0 auto 15px;
    border: 2px solid var(--neon);
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.msg { color: #00ff99; text-align:center; }
.err { color: var(--danger); text-align:center; }
</style>
</head>

<body>

<div class="profile-box">
    <h2>PLAYER CUSTOMIZATION</h2>

    <div class="avatar">
        <img src="uploads/<?= $user['image'] ?: 'default.png' ?>">
    </div>

    <!-- IMAGE -->
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="avatar" required>
        <button name="update_image">UPDATE AVATAR</button>
    </form>

    <hr>

    <!-- PROFILE -->
    <form method="post">
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" placeholder="Username">
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" placeholder="Email">
        <button name="update_profile">UPDATE PROFILE</button>
    </form>

    <hr>

    <!-- PASSWORD -->
    <form method="post">
        <input type="password" name="current_password" placeholder="Current Password">
        <input type="password" name="new_password" placeholder="New Password">
        <button name="update_password">CHANGE PASSWORD</button>
    </form>
</div>

</body>
</html>
