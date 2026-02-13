<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database/dbConfig.php';
include('partial/header.php');

$statusMsg = '';
$sender_name = "";
$sender_email = "";
$sender_id = NULL;
$is_logged_in = false;


if (isset($_SESSION['user_id'])) {
    $is_logged_in = true;
    $sender_id = $_SESSION['user_id'];

    $u_stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    $u_stmt->bind_param("i", $sender_id);
    $u_stmt->execute();
    $result = $u_stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $sender_name = $row['username'];
        $sender_email = $row['email'];
    }
    $u_stmt->close();
}

// ၂။ Form Submit Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $is_logged_in ? $sender_name : $_POST['sender_name'];
    $email = $is_logged_in ? $sender_email : $_POST['sender_email'];
    $message_content = $_POST['sender_message'];

    if (!empty($message_content)) {
        // user_id ကို Foreign Key အဖြစ် သိမ်းဆည်းခြင်း
        $stmt = $conn->prepare("INSERT INTO messages (user_id, name, email, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $sender_id, $name, $email, $message_content);

        if ($stmt->execute()) {
            $statusMsg = "Mission Accomplished! Dispatch Sent.";
        }
        $stmt->close();
    }
}
?>

<style>
    :root {
        --primary-red: #ff4655;
        --dark-bg: #0f1923;
        --card-bg: rgba(23, 27, 34, 0.95);
        --input-bg: #1b2733;
        --success-green: #11e06e;
    }

    body {
        background: var(--dark-bg);
        background-image: linear-gradient(rgba(15, 25, 35, 0.85), rgba(15, 25, 35, 0.85)),
            url('https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=2070&auto=format&fit=crop');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        color: #ece8e1;
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        overflow-x: hidden;
        position: relative;
        color: #ffffff; 
        font-family: 'Segoe UI', Helvetica, Arial, sans-serif; 
        
        /* INTENSE RED GRADIENT BACKGROUND */
        background-image: 
            radial-gradient(circle at 10% 20%, rgba(255, 77, 90, 0.15) 0%, transparent 40%),
            radial-gradient(circle at 90% 80%, rgba(255, 77, 90, 0.15) 0%, transparent 40%),
            linear-gradient(rgba(255, 77, 90, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 77, 90, 0.05) 1px, transparent 1px);
        background-size: 100% 100%, 100% 100%, 30px 30px, 30px 30px;
        background-attachment: fixed;
    }

    /* ENHANCED DYNAMIC RED GLOW */
    body::before {
        content: "";
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                    rgba(255, 77, 90, 0.25) 0%, 
                    transparent 45%);
        z-index: -1;
        pointer-events: none;
    }

    .page {
        padding: 80px 8%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin-left: var(--sidebar-w);
    }

    /* Fix: Success Alert Box - Matching the image design */
    .alert-success {
        width: 100%;
        max-width: 550px;
        background: rgba(17, 224, 110, 0.1);
        color: var(--success-green);
        border: 1px solid var(--success-green);
        padding: 15px 20px;
        margin-bottom: 25px;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-sizing: border-box;
        animation: fadeIn 0.4s ease;
    }

    .alert-close {
        background: transparent;
        border: none;
        color: var(--success-green);
        cursor: pointer;
        font-size: 20px;
        line-height: 1;
        opacity: 0.8;
        width: auto;
        padding: 0 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-transform: none;
    }

    .contact-container {
        background: var(--card-bg);
        backdrop-filter: blur(15px);
        padding: 50px 40px;
        border: 1px solid rgba(255, 77, 90, 0.3);
        width: 100%;
        max-width: 550px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        clip-path: polygon(0 0, 100% 0, 100% 95%, 95% 100%, 0 100%);
    }

    h1 {
        color: var(--primary-red);
        text-transform: uppercase;
        font-size: 3rem;
        margin-bottom: 10px;
        font-style: italic;
    }

    .subtitle {
        color: #8b978f;
        margin-bottom: 30px;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 1px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    label {
        display: block;
        text-transform: uppercase;
        font-size: 0.7rem;
        color: var(--primary-red);
        margin-bottom: 5px;
        font-weight: bold;
    }

    input,
    textarea {
        width: 100%;
        padding: 15px;
        background: var(--input-bg);
        border: 1px solid #333;
        color: #fff;
        box-sizing: border-box;
        outline: none;
        transition: 0.3s;
    }

    input[readonly] {
        background: #141b23;
        color: #8b978f;
        border-color: #2c3540;
        cursor: not-allowed;
    }

    textarea {
        height: 120px;
        resize: none;
    }

    .submit-btn {
        color: #fff;
        background: var(--primary-red);
        padding: 18px;
        border: none;
        width: 100%;
        font-weight: 900;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.2s;
    }

    .submit-btn:hover { 
        background: #fff;
        color: var(--primary-red);
        box-shadow: 0 0 30px rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
    }

    @media (max-width: 850px) {
        .page { margin-left: 0; }
        h1 { font-size: 2.2rem; }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<section class="page">
    <div style="text-align: center;">
        <h1>CONTACT US</h1>
        <p class="subtitle">Direct Support Line</p>
    </div>

    <?php if ($statusMsg): ?>
        <div class="alert-success">
            <span><?= htmlspecialchars($statusMsg) ?></span>
            <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <div class="contact-container">
        <form action="" method="POST">
            <div class="form-group">
                <label>Identification</label>
                <input type="text" name="sender_name" value="<?= htmlspecialchars($sender_name); ?>"
                    <?= $is_logged_in ? 'readonly' : 'required'; ?>>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="sender_email" value="<?= htmlspecialchars($sender_email); ?>"
                    <?= $is_logged_in ? 'readonly' : 'required'; ?>>
            </div>

            <div class="form-group">
                <label>Message</label>
                <textarea name="sender_message" placeholder="TYPE YOUR MESSAGE HERE..." required></textarea>
            </div>

            <button type="submit" class="submit-btn">Send Dispatch</button>
        </form>
    </div>
</section>

<script>
    window.addEventListener('mousemove', e => {
        const x = (e.clientX / window.innerWidth) * 100;
        const y = (e.clientY / window.innerHeight) * 100;
        document.body.style.setProperty('--mouse-x', x + '%');
        document.body.style.setProperty('--mouse-y', y + '%');
    });
</script>