<?php
include('partial/header.php');
?>
<div class="legacy-content">
    <div class="legacy-login_container">
        <form action="" method="post">
            <h3>Sign In</h3>
            <input type="email" name="txtemail" placeholder="example.gmail.com" required class="legacy-login_input">
            <input type="password" name="txtpwd" placeholder="*********" required class="legacy-login_input"> <br> <br>
            <label for="">Create New Account</label><a href="signup.php">SignUp</a><br> <br>
            
            <a href="forget_password.php" class="legacy-forget_btn">forget password?</a><br>
            <button name="btnlogin" class="legacy-btnlogin">Login</button>
            
            <input type="reset" value="Cancel" class="legacy-logcancel">
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