<?php include('partial/header.php');
?>
<?php
session_start();

require_once "database/dbConfig.php";

$error = "";
$success = "";

if (isset($_POST['btnsave'])) {

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirmPassword'];

    // Checkbox
    $isOrganizerChecked = isset($_POST['isOrganizer']);

    /* ===== PHP VALIDATION ===== */
    if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match";
    } else {

        /* ===== DUPLICATE CHECK ===== */
        $check = "SELECT user_id FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $check);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Username or Email already exists";
        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            if ($isOrganizerChecked) {
                $is_organizer = 1;
                $organizer_status = "pending";
            } else {
                $is_organizer = 0;
                $organizer_status = NULL;
            }

            /* ===== INSERT USER ===== */
            $sql = "INSERT INTO users 
                    (username, email, password, is_organizer, organizer_status)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "sssis",
                $username,
                $email,
                $hashed_password,
                $is_organizer,
                $organizer_status
            );

            if (mysqli_stmt_execute($stmt)) {
                $success = "Signup successful. You can now login.";
            } else {
                $error = "Signup failed. Please try again.";
            }
        }
    }
}
?>

<!-- ================= BACKGROUND EFFECTS ================= -->
<div class="geometric-shape-1"></div>
<div class="geometric-shape-2"></div>

<div class="floating-card-signup"></div>
<div class="floating-card-signup"></div>
<div class="floating-card-signup"></div>

<div class="wave-container">
    <div class="wave-line"></div>
    <div class="wave-line"></div>
    <div class="wave-line"></div>
</div>

<div class="ring ring-1"></div>
<div class="ring ring-2"></div>

<div class="orb-signup orb-signup-1"></div>
<div class="orb-signup orb-signup-2"></div>

<div class="grid-overlay"></div>

<!-- ================= SIGNUP CARD ================= -->
<div class="card-container-signup">
    <div class="card-signup">
        <div class="card-header-signup">
            <h1 class="card-title-signup">Tournament Registration</h1>
            <p class="card-description-signup">
                Create your account to join the competition
            </p>
        </div>

        <div class="card-content-signup">

            <!-- ERROR / SUCCESS -->
            <?php if ($error): ?>
                <div class="error-message" style="color:#ff4d4d; margin-bottom:15px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message" style="color:#4caf50; margin-bottom:15px;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <!-- Username -->
                <div class="form-field-signup">
                    <label>Username</label>
                    <input type="text" name="username" required minlength="3"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <!-- Email -->
                <div class="form-field-signup">
                    <label>Email</label>
                    <input type="email" name="email" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <!-- Password -->
                <div class="form-field-signup">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8">
                </div>

                <!-- Confirm Password -->
                <div class="form-field-signup">
                    <label>Confirm Password</label>
                    <input type="password" name="confirmPassword" required>
                </div>

                <!-- Organizer -->
                <div class="checkbox-field-signup">
                    <input type="checkbox" name="isOrganizer"
                        <?= isset($_POST['isOrganizer']) ? 'checked' : '' ?>>
                    <label>I am a tournament organizer</label>
                </div>

                <!-- Buttons -->
                <div class="button-group-signup">
                    <button type="submit" name="btnsave" class="btn-primary-form btn-signup">
                        Register
                    </button>

                    <a href="index.php" class="btn-secondary-form btn-signup">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include('partial/footer.php'); ?>