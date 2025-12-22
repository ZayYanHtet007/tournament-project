<?php
include('partial/header.php');
?>

<?php
if (isset($_POST['btnsave'])) {
}
?>

<div class="content">
    <div class="reg_container">
        <img src="" alt="" class="reg_image">
        <div class="reg_column">
            <h2>Sign Up</h2>
            <form action="" method="post" enctype="multipart/form-data">
                <div>
                    <label for="uploadInput" style="cursor: pointer; margin: 0;">Upload Profile Picture
                        <img src="" alt="" class="upload_photo" id="img">
                    </label>
                    <input type="file" id="uploadInput" name="image" style="display: none;" onchange="previewImage(event)" required>
                </div>
                <div class="reg_row">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="reg_input" required>
                </div>
                <div class="reg_row">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="reg_input" required>
                </div>
                <div class="reg_row">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="reg_input" required>
                </div>
                <div class="reg_row">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="reg_input" required>
                </div>
                <div class="reg_row">
                    <label for="role">Select Role</label>
                    <div class="dropdown">
                        <input type="password" id="role" name="role" class="reg_input" class="dropbtn" required></input>
                        <div class="dropdown-content">
                            <a href="#">Player</a>
                            <a href="#">Organizer</a>
                        </div>
                    </div>
                </div>
                <div class="g-recaptcha" data-sitekey="6LdjzgksAAAAAIz_1CCXM52-J_BSyBPYXfP_LVkw" data-theme="dark" required></div>
                <div class="reg_row">
                    <button type="submit" name="btnsave" class="reg_button">Register</button>
                    <input type="reset" value="cancel" class="reg_button">
                </div>
            </form>
            <script>
                function previewImage(event) {
                    let img = document.getElementById('img');
                    img.src = URL.createObjectURL(event.target.files[0]);
                    img.onload = function() {
                        URL.revokeObjectURL(img.src);
                    }
                }
            </script>
        </div>
    </div>
</div>

<?php
include('partial/footer.php');
?>