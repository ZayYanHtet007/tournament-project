<?php
include('partial/header.php');
?>
<div class="content">
    <div class="login_container">
        <form action="" method="post">
            <h3>Sign In</h3>
            <input type="email" name="txtemail" placeholder="example.gmail.com" required class="login_input">
            <input type="password" name="txtpwd" placeholder="*********" required class="login_input"> <br> <br>
            <label for="">Create New Account</label><a href="signup.php">SignUp</a><br> <br>
            
            <a href="forget_password.php" class="forget_btn">forget password?</a><br>
            <button name="btnlogin" class="btnlogin">Login</button>
            
            <input type="reset" value="Cancel" class="logcancel">
        </form>
    </div>
</div>
<?php
        if(isset($_POST['btnlogin'])){
        }
    ?>
<?php
    include('partial/footer.php');
?>