<?php
session_start();
include('partial/header.php');
?>

<form method="post">
    <input type="text" name="resetcode" placeholder="Enter Code" required>
    <button name="submit">Send</button>
</form>

<?php
if(isset($_POST['submit'])){
    $code = $_POST['resetcode'];
    $resetcode = $_SESSION['resetcode'];

    if($code == $resetcode){
        header("Location: changePassword.php");
        exit();
    } else {
        echo "<script>alert('Invalid code');</script>";
    }
}
include('partial/footer.php');
?>
