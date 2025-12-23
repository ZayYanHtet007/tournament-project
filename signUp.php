<?php
include('partial/header.php');
?>

<?php
if (isset($_POST['btnsave'])) {
}
?>

<div class="legacy-content">
    <div class="legacy-reg_container">
        <img src="" alt="" class="legacy-reg_image">
        <div class="legacy-reg_column">
            <h2>Sign Up</h2>
            <form action="" method="post" enctype="multipart/form-data">
                <div>
                    <label for="uploadInput" style="cursor: pointer; margin: 0;">Upload Profile Picture
                        <img src="" alt="" class="legacy-upload_photo" id="img">
                    </label>
                    <input type="file" id="uploadInput" name="image" style="display: none;" onchange="previewImage(event)" required>
                </div>
                <div class="legacy-reg_row">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="legacy-reg_input" required>
                </div>
                <div class="legacy-reg_row">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="legacy-reg_input" required>
                </div>
                <div class="legacy-reg_row">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="legacy-reg_input" required>
                </div>
                <div class="legacy-reg_row">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="legacy-reg_input" required>
                </div>
                <div class="legacy-reg_row">
                    <label for="role">Select Role</label>
                    <div class="legacy-dropdown">
                        <input type="password" id="role" name="role" class="legacy-dropbtn" required></input>
                        <div class="legacy-dropdown-content">
                            <a href="#">Player</a>
                            <a href="#">Organizer</a>
                        </div>
                    </div>
                </div>
                <div class="legacy-g-recaptcha" data-sitekey="6LdjzgksAAAAAIz_1CCXM52-J_BSyBPYXfP_LVkw" data-theme="dark" required></div>
                <div class="legacy-reg_row">
                    <button type="submit" name="btnsave" class="legacy-reg_button">Register</button>
                    <input type="reset" value="cancel" class="legacy-reg_cancel">
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