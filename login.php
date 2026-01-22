
<?php
session_start();
require_once "database/dbConfig.php";

/* ================= ERROR HANDLING ================= */
$error = "";
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

/* ================= LOGIN LOGIC ================= */
if (isset($_POST['btnlogin'])) {

    $email = trim($_POST['txtemail']);
    $password = $_POST['txtpwd'];

    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {

        if (!password_verify($password, $user['password'])) {
            $_SESSION['error'] = "Invalid email or password";
            header("Location: login.php");
            exit;
        }

        /* ORGANIZER LOGIN */
        if ((int)$user['is_organizer'] === 1) {

            $status = strtolower(trim($user['organizer_status']));

            if ($status !== 'approved') {
                $_SESSION['error'] = "Organizer account not approved";
                header("Location: login.php");
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_organizer'] = 1;
            $_SESSION['organizer_status'] = $status;

            header("Location: organizer/organizerDashboard.php");
            exit;
        }

        /* PLAYER LOGIN */
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_organizer'] = 0;

        header("Location: index.php");
        exit;
    }

    $_SESSION['error'] = "Invalid email or password";
    header("Location: login.php");
    exit;
}
?>

<?php
include('partial/header.php'); 
?>

<!-- ================= BACKGROUND EFFECTS ================= -->
<div class="geometric-shape-1"></div>
<div class="geometric-shape-2"></div>

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

<!-- ================= LOGIN CARD ================= -->
<div class="card-container-signup">
    <div class="card-signup">
        <div class="card-header-signup">
            <h1 class="card-title-signup">Sign In</h1>
            <p class="card-description-signup">Login to join the competition</p>
        </div>

        <div class="card-content-signup">

            <!-- ERROR MESSAGE -->
            <?php if (!empty($error)) : ?>
                <div class="error-message" style="color:#ff4d4d; margin-bottom:15px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <!-- EMAIL -->
                <div class="form-field-signup">
                    <label>Email</label>
                    <input
                        type="email"
                        name="txtemail"
                        placeholder="example@gmail.com"
                        required>
                </div>

                <!-- PASSWORD -->
                <div class="form-field-signup">
                    <label>Password</label>
                    <input
                        type="password"
                        name="txtpwd"
                        placeholder="********"
                        required>
                </div>

                <br>
                <label class="loginlabel">Create New Account</label>
                <a href="signup.php" class="signup_btn">SignUp</a><br><br>

                <a href="forget_password.php" class="legacy-forget_btn">Forget password?</a><br><br>
                <!-- BUTTONS -->
                <div class="button-group-signup">
                    <button type="submit" name="btnlogin" class="btn-primary-form btn-signup">
                        Sign In
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