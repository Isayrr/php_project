<?php
session_start();
require_once '../config/database.php';

// Check if user has requested password reset
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];

// Get any attempt information if it exists
$attempts = isset($_SESSION['otp_attempts']) ? $_SESSION['otp_attempts'] : 0;
$max_attempts = 5; // Maximum number of attempts allowed

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .otp-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .otp-input {
            width: 50px;
            height: 50px;
            font-size: 20px;
            text-align: center;
            border: 2px solid #ced4da;
            border-radius: 8px;
        }
        .otp-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            outline: none;
        }
        #hidden-otp-input {
            position: absolute;
            opacity: 0;
            height: 0;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Verify OTP</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['otp_error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['otp_error']; 
                                unset($_SESSION['otp_error']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['fp_success'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['fp_success']; 
                                unset($_SESSION['fp_success']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($attempts >= $max_attempts): ?>
                            <div class="alert alert-danger">
                                <strong>Too many incorrect attempts!</strong> For security reasons, please request a new verification code.
                            </div>
                            <div class="text-center mt-3">
                                <a href="forgot_password.php" class="btn btn-primary">Request New Code</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p><strong>A verification code has been sent to your email:</strong> <?php echo htmlspecialchars(substr($email, 0, 3) . '***' . substr($email, strpos($email, '@'))); ?></p>
                                <p class="mb-0">Please check your inbox (including spam folder) and enter the 6-digit code below.</p>
                            </div>
                            
                            <?php if($attempts > 0): ?>
                                <div class="alert alert-warning">
                                    <strong>Warning:</strong> You have made <?php echo $attempts; ?> incorrect attempt(s). You have <?php echo $max_attempts - $attempts; ?> attempts remaining.
                                </div>
                            <?php endif; ?>
                            
                            <form action="process_verify_otp.php" method="post" id="otp-form">
                                <div class="mb-3">
                                    <label for="otp" class="form-label d-block text-center fw-bold">Enter the 6-digit verification code</label>
                                    
                                    <!-- Visual OTP input boxes -->
                                    <div class="otp-input-container">
                                        <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
                                        <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                        <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                        <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                        <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                        <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                    </div>
                                    
                                    <!-- Hidden input that will hold the combined OTP value -->
                                    <input type="hidden" name="otp" id="hidden-otp-input" required>
                                    
                                    <small class="form-text text-muted text-center d-block">The code will expire in 15 minutes from when it was sent.</small>
                                </div>
                                <div class="form-group mb-3">
                                    <button type="submit" class="btn btn-primary btn-block w-100">Verify Code</button>
                                </div>
                                <div class="text-center">
                                    <p>Didn't receive the code? <a href="process_forgot_password.php?resend=1">Resend Code</a></p>
                                    <a href="forgot_password.php">Use a different email</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle OTP input functionality
        document.addEventListener('DOMContentLoaded', function() {
            const otpInputs = document.querySelectorAll('.otp-input');
            const hiddenInput = document.getElementById('hidden-otp-input');
            const form = document.getElementById('otp-form');
            
            // Focus the first input on page load
            if (otpInputs.length > 0) {
                otpInputs[0].focus();
            }
            
            // Handle input in the OTP fields
            otpInputs.forEach((input, index) => {
                // Only allow numbers
                input.addEventListener('input', function(e) {
                    // Remove any non-numeric characters
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Move to next input if value is entered
                    if (this.value && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    
                    // Update the hidden input with the complete OTP
                    updateHiddenInput();
                });
                
                // Handle backspace
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        otpInputs[index - 1].focus();
                        otpInputs[index - 1].value = '';
                        updateHiddenInput();
                    }
                });
                
                // Handle paste (e.g., if user copies the OTP from email)
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = e.clipboardData.getData('text').trim();
                    
                    // If it's a 6-digit number, distribute across inputs
                    if (/^\d{6}$/.test(pasteData)) {
                        for (let i = 0; i < otpInputs.length; i++) {
                            otpInputs[i].value = pasteData[i] || '';
                        }
                        updateHiddenInput();
                    }
                });
            });
            
            // Before form submission, update the hidden input
            form.addEventListener('submit', function(e) {
                updateHiddenInput();
                
                // Check if the OTP is complete (6 digits)
                if (hiddenInput.value.length !== 6 || !/^\d{6}$/.test(hiddenInput.value)) {
                    e.preventDefault();
                    alert('Please enter a valid 6-digit verification code.');
                }
            });
            
            // Function to update the hidden input value
            function updateHiddenInput() {
                let otp = '';
                otpInputs.forEach(input => {
                    otp += input.value;
                });
                hiddenInput.value = otp;
            }
        });
    </script>
</body>
</html> 