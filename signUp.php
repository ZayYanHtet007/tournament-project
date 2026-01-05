<?php
include('partial/header.php');
?>

<?php
if (isset($_POST['btnsave'])) {
}
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
                <h1 class="card-title-signup">Tournament Registration</h1>
                <p class="card-description-signup">Create your account to join the competition</p>
            </div>
            <div class="card-content-signup">
                <form class="form" id="registrationForm">
                    <!-- Username -->
                    <div class="form-field-signup">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                placeholder="Enter your username"
                                required
                                minlength="3"
                            >
                        </div>
                        <span class="error-message" id="usernameError" style="display: none;"></span>
                    </div>

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

                    <!-- Confirm Password -->
                    <div class="form-field-signup">
                        <label for="confirmPassword">Confirm Password</label>
                        <div class="input-wrapper">
                            <input 
                                type="password" 
                                id="confirmPassword" 
                                name="confirmPassword" 
                                placeholder="Confirm your password"
                                required
                            >
                        </div>
                        <span class="error-message" id="confirmPasswordError" style="display: none;"></span>
                    </div>

                    <!-- Is Organizer Checkbox -->
                    <div class="checkbox-field-signup">
                        <input 
                            type="checkbox" 
                            id="isOrganizer" 
                            name="isOrganizer"
                        >
                        <label for="isOrganizer">I am a tournament organizer</label>
                    </div>

                    <!-- Buttons -->
                    <div class="button-group-signup">
                        <div class="button-wrapper">
                            <button type="submit" class="btn-primary-form btn-signup">Register</button>
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
include('partial/footer.php');
?>
