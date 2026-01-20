<?php
session_start();
include('partial/header.php');
?>

<form method="post">
    <input type="password" name="txtpwd" placeholder="New password" required>
    <input type="password" name="txtcpwd" placeholder="Confirm password" required>
    <button name="submit">Change</button>
</form>

<?php
if(isset($_POST['submit'])){
    $pwd = $_POST['txtpwd'];
    $cpwd = $_POST['txtcpwd'];
    $email = $_SESSION['reset_email'];

    if($pwd != $cpwd){
        echo "<script>alert('Passwords do not match');</script>";
    } else {
        $hashed = password_hash($pwd, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password='$hashed' WHERE email='$email'";
        mysqli_query($conn, $sql);
        header("Location: login.php");
        exit();
    }
}
include('partial/footer.php');
?>
