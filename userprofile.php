<?php
require_once "partial/init.php"; // this now defines $pdo
include('partial/header.php');

$userId = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT username, email, image FROM users WHERE user_id = ?");
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
    $newImage = $user['image'];

    // Handle Image if uploaded
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $file = $_FILES['avatar'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            // FOLDER FIX: Define and check path
            $target_dir = "images/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true); // Creates folder if missing
            }

            $newImage = "user_" . $userId . "_" . time() . "." . $ext;
            if (move_uploaded_file($file['tmp_name'], $target_dir . $newImage)) {
                // Success
            } else {
                $error = "Failed to move uploaded file.";
            }
        } else {
            $error = "Invalid image format";
        }
    }

    // Update everything in one go
    if (!isset($error)) {
        if ($username === "" || $email === "") {
            $error = "Username and Email are required";
        } else {
            $stmt = $pdo->prepare(
                "UPDATE users SET username = ?, email = ?, image = ?, last_update = NOW() WHERE user_id = ?"
            );
            $stmt->execute([$username, $email, $newImage, $userId]);
            $message = "Profile synchronized successfully";
            // Refresh local data
            $user['username'] = $username;
            $user['email'] = $email;
            $user['image'] = $newImage;
        }
    }
}
?>

    <style>
        :root {
            --riot-red: #ff4655;
            --riot-bg: #0f1923;
            --riot-white: #ece8e1;
            --riot-border: rgba(255, 255, 255, 0.15);
            --gaming-dark: #080a0c; 
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* CYBER-GRID ANIMATED BACKGROUND */
        .profile-wrapper {
            margin-left: 85px;
            margin-top: 75px;
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
                linear-gradient(rgba(255, 70, 85, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 70, 85, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            background-position: center center;
        }

        /* Moving Gradient Glow */
        .profile-wrapper::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                        rgba(255, 70, 85, 0.12) 0%, 
                        transparent 50%);
            z-index: 0;
            pointer-events: none;
        }

        /* Animated Scanline */
        .profile-wrapper::after {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(
                to bottom,
                transparent 0%,
                rgba(255, 70, 85, 0.05) 50%,
                transparent 100%
            );
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
            max-width: 450px;
            background: #0f1923; /* Solid Riot Dark */
            border: 1px solid var(--riot-border);
            padding: 50px 40px;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 50px rgba(0,0,0,0.8), inset 0 0 20px rgba(255, 70, 85, 0.05);
        }

        h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3rem;
            line-height: 0.9;
            margin-bottom: 40px;
            letter-spacing: 1px;
            color: #fff;
            text-shadow: 2px 2px 0px var(--riot-red);
        }

        .close-btn{
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
            width: 160px;
            height: 160px;
            margin: 0 auto 40px;
            cursor: pointer;
        }

        .avatar-preview {
            width: 100%;
            height: 100%;
            border: 2px solid var(--riot-red);
            padding: 5px;
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
            font-size: 1.2rem;
            opacity: 0;
            transition: 0.3s;
            pointer-events: none;
            white-space: nowrap;
            color: #fff;
        }

        .avatar-upload-wrapper:hover .upload-hint { opacity: 1; }

        #file-input { display: none; }

        .field { margin-bottom: 25px; }
        
        label {
            display: block;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 1.5px;
        }

        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border: 1px solid transparent;
            border-bottom: 2px solid var(--riot-border);
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }

        input:focus {
            outline: none;
            background: rgba(255,255,255,0.07);
            border-bottom-color: var(--riot-red);
        }

        .save-btn {
            width: 100%;
            padding: 20px;
            background: transparent;
            color: #fff;
            border: 1px solid var(--riot-red);
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
            top: 0; left: 0; width: 0; height: 100%;
            background: var(--riot-red);
            transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: -1;
        }

        .save-btn:hover { color: #000; }
        .save-btn:hover::before { width: 100%; }

        .status {
            text-align: center;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .msg { color: #00ff99; text-shadow: 0 0 10px rgba(0,255,153,0.3); }
        .err { color: var(--riot-red); }
    </style>

<div class="profile-wrapper" id="bg-wrapper">
<div class="profile-container">
    <h2>EDIT PREFERENCES</h2>

    <a href="index.php" class="close-btn">X</a>

    <?php if(isset($message)): ?>
        <p class="status msg"><?= $message ?></p>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <p class="status err"><?= $error ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="avatar-upload-wrapper" onclick="document.getElementById('file-input').click()">
            <img id="preview" src="images/<?= $user['image'] ?: 'default.png' ?>" class="avatar-preview">
            <div class="upload-hint">CHANGE IMAGE</div>
            <input type="file" name="avatar" id="file-input" onchange="previewImage(this)">
        </div>

        <div class="field">
            <label>Agent Name</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>">
        </div>

        <div class="field">
            <label>Communication Link</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
        </div>

        <button type="submit" name="save_all" class="save-btn">SAVE CHANGES</button>
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
</script>