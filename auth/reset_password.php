<?php
session_start();
require_once '../config/database.php';

// Check if user has been verified through OTP
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
    $_SESSION['fp_error'] = "Please complete the verification process first.";
    header("Location: forgot_password.php");
    exit();
}

// Get the user's email from session
$email = $_SESSION['reset_email'];

// Additional security check - verify that reset_token exists and is valid in database
try {
    $stmt = $conn->prepare("SELECT user_id, reset_token, reset_token_expiry FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $user = $stmt->fetch();
    
    $current_time = date('Y-m-d H:i:s');
    
    // If user not found, token is missing, or token is expired
    if (!$user || !$user['reset_token'] || $current_time > $user['reset_token_expiry']) {
        // Log the security issue
        error_log("Security issue: Invalid reset attempt for user ID: {$_SESSION['reset_user_id']}, Email: {$email}");
        
        // Clear session variables
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_verified']);
        unset($_SESSION['otp_attempts']);
        
        $_SESSION['fp_error'] = "Your verification has expired. Please restart the password reset process.";
        header("Location: forgot_password.php");
        exit();
    }
} catch(PDOException $e) {
    error_log("Database error during reset password security check: " . $e->getMessage());
    $_SESSION['reset_error'] = "A database error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Reset Password</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success mb-4">
                            <i class="fas fa-check-circle me-2"></i> Email verified successfully! You can now create a new password.
                        </div>
                        
                        <?php if(isset($_SESSION['reset_error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['reset_error']; 
                                unset($_SESSION['reset_error']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="process_reset_password.php" method="post" id="resetForm">
                            <div class="form-group mb-3">
                                <label for="new_password">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required>
                                <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                                <div id="password-strength" class="mt-2"></div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                <div id="password-match" class="mt-2"></div>
                            </div>
                            <div class="form-group mb-3">
                                <button type="submit" class="btn btn-primary btn-block w-100">Reset Password</button>
                            </div>
                            
                            <!-- Hidden field to prevent CSRF attacks -->
                            <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(32)); ?>">
                            <?php $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password validation and real-time feedback
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('password-strength');
            const passwordMatch = document.getElementById('password-match');
            const form = document.getElementById('resetForm');
            
            // Update password strength indicator
            newPassword.addEventListener('input', function() {
                const value = this.value;
                let strength = '';
                let className = '';
                
                if (value.length === 0) {
                    strength = '';
                    className = '';
                } else if (value.length < 6) {
                    strength = 'Too short';
                    className = 'text-danger';
                } else if (value.length < 8) {
                    strength = 'Weak';
                    className = 'text-warning';
                } else if (value.length < 12) {
                    strength = 'Medium';
                    className = 'text-info';
                } else {
                    strength = 'Strong';
                    className = 'text-success';
                }
                
                // Additional checks for stronger passwords
                if (value.length >= 8) {
                    const hasUpperCase = /[A-Z]/.test(value);
                    const hasLowerCase = /[a-z]/.test(value);
                    const hasNumbers = /\d/.test(value);
                    const hasSpecial = /[^A-Za-z0-9]/.test(value);
                    
                    const score = [hasUpperCase, hasLowerCase, hasNumbers, hasSpecial].filter(Boolean).length;
                    
                    if (score === 4 && value.length >= 10) {
                        strength = 'Very Strong';
                        className = 'text-success fw-bold';
                    } else if (score >= 3) {
                        strength = 'Strong';
                        className = 'text-success';
                    } else if (score >= 2) {
                        strength = 'Medium';
                        className = 'text-info';
                    }
                }
                
                passwordStrength.textContent = strength;
                passwordStrength.className = className;
            });
            
            // Update password match indicator
            confirmPassword.addEventListener('input', function() {
                if (this.value === newPassword.value) {
                    passwordMatch.textContent = 'Passwords match';
                    passwordMatch.className = 'text-success';
                } else {
                    passwordMatch.textContent = 'Passwords do not match';
                    passwordMatch.className = 'text-danger';
                }
            });
            
            // Validate form submission
            form.addEventListener('submit', function(e) {
                const password = newPassword.value;
                const confirm = confirmPassword.value;
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return false;
                }
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }
            });
        });
    </script>
</body>
</html> 