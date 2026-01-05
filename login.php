<?php
include('partial/header.php');
?>

<!-- 3D Animated Geometric Shapes -->
    <div class="geometric-shape-1"></div>
    <div class="geometric-shape-2"></div>

    <!-- Floating 3D Cards -->
    <div class="floating-card-signup"></div>
    <div class="floating-card-signup"></div>
    <div class="floating-card-signup"></div>
    <div class="floating-card-signup"></div>
    <div class="floating-card-signup"></div>
    <div class="floating-card-signup"></div>
    <div class="floating-card-signup"></div>
    <div class="floating-card-signup"></div>

    <!-- Animated Wave Lines -->
    <div class="wave-container">
        <div class="wave-line"></div>
        <div class="wave-line"></div>
        <div class="wave-line"></div>
        <div class="wave-line"></div>
        <div class="wave-line"></div>
    </div>

    <!-- Glowing Particles -->
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>
    <div class="particle-signup"></div>

    <!-- 3D Rotating Rings -->
    <div class="ring ring-1"></div>
    <div class="ring ring-2"></div>
    <div class="ring ring-3"></div>

    <!-- Animated Background Orbs -->
    <div class="orb-signup orb-signup-1"></div>
    <div class="orb-signup orb-signup-2"></div>
    <div class="orb-signup orb-signup-3"></div>

    <!-- Grid Overlay -->
    <div class="grid-overlay"></div>

    <!-- Registration Form Card -->
    <div class="card-container-signup">
        <div class="card-signup">
            <div class="card-header-signup">
                <h1 class="card-title-signup">Sign In</h1>
                <p class="card-description-signup">Login to join the competition</p>
            </div>
            <div class="card-content-signup">
                <form class="form" id="registrationForm">

                    <!-- Email -->
                    <div class="form-field-signup">
                        <label for="email">Email</label>
                        <div class="input-wrapper">
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="Enter your email"
                                required
                            >
                        </div>
                        <span class="error-message" id="emailError" style="display: none;"></span>
                    </div>

                    <!-- Password -->
                    <div class="form-field-signup">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your password"
                                required
                                minlength="8"
                            >
                        </div>
                        <span class="error-message" id="passwordError" style="display: none;"></span>
                    </div>

                    <label for="" class="loginlabel">Create New Account</label><a href="signup.php" class="signup_btn">SignUp</a><br> <br>
                    <a href="forget_password.php" class="legacy-forget_btn">forget password?</a><br>
                    

                    <!-- Buttons -->
                    <div class="button-group-signup">
                        <div class="button-wrapper">
                            <button type="submit" class="btn-primary-form btn-signup">Sign In</button>
                        </div>
                        <div class="button-wrapper">
                            <button type="button" class="btn-secondary-form  btn-signup" id="cancelBtn">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Form validation and submission
        const form = document.getElementById('registrationForm');
        const cancelBtn = document.getElementById('cancelBtn');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear previous errors
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
            
            // Get form values
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const isOrganizer = document.getElementById('isOrganizer').checked;
            
            let isValid = true;
            
            // Validate username
            if (username.length < 3) {
                showError('usernameError', 'Username must be at least 3 characters');
                isValid = false;
            }
            
            // Validate email
            const emailRegex = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i;
            if (!emailRegex.test(email)) {
                showError('emailError', 'Invalid email address');
                isValid = false;
            }
            
            // Validate password
            if (password.length < 8) {
                showError('passwordError', 'Password must be at least 8 characters');
                isValid = false;
            }
            
            // Validate confirm password
            if (password !== confirmPassword) {
                showError('confirmPasswordError', 'Passwords do not match');
                isValid = false;
            }
            
            if (isValid) {
                alert(`Registration successful!\nUsername: ${username}\nEmail: ${email}\nOrganizer: ${isOrganizer}`);
                form.reset();
            }
        });

        cancelBtn.addEventListener('click', function() {
            form.reset();
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
        });

        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    </script>

<?php
        if(isset($_POST['btnlogin'])){
        }
    ?>
<?php
    include('partial/footer.php');
?>