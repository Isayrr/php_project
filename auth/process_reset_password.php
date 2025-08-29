<?php
session_start();
require_once '../config/database.php';

// Check if user has been verified through OTP
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
    $_SESSION['fp_error'] = "Please complete the verification process first.";
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['reset_user_id'];
    $email = $_SESSION['reset_email'];
    
    // Validate password
    if (empty($new_password) || strlen($new_password) < 6) {
        $_SESSION['reset_error'] = "Password must be at least 6 characters long.";
        header("Location: reset_password.php");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("Location: reset_password.php");
        exit();
    }
    
    try {
        // Verify user exists and has a valid reset token before updating
        $stmt = $conn->prepare("SELECT reset_token, reset_token_expiry FROM users WHERE user_id = ? AND email = ?");
        $stmt->execute([$user_id, $email]);
        $user = $stmt->fetch();
        
        $current_time = date('Y-m-d H:i:s');
        
        if (!$user || !$user['reset_token'] || $current_time > $user['reset_token_expiry']) {
            // Log the security issue
            error_log("Security issue: Invalid password reset attempt for user ID: {$user_id}, Email: {$email}");
            
            // Clear session variables
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);
            unset($_SESSION['otp_attempts']);
            
            $_SESSION['fp_error'] = "Your verification has expired. Please restart the password reset process.";
            header("Location: forgot_password.php");
            exit();
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user password and clear reset token
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        // Log successful password reset
        error_log("Password reset successful for user ID: {$user_id}, Email: {$email}");
        
        // Clear reset session variables
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_verified']);
        unset($_SESSION['otp_attempts']);
        unset($_SESSION['csrf_token']);
        
        // Set success message
        $_SESSION['login_success'] = "Your password has been reset successfully. You can now login with your new password.";
        
        header("Location: ../index.php?reset=success");
        exit();
        
    } catch(PDOException $e) {
        error_log("Database error during password reset: " . $e->getMessage());
        $_SESSION['reset_error'] = "A database error occurred. Please try again later.";
        header("Location: reset_password.php");
        exit();
    }
    
} else {
    header("Location: reset_password.php");
    exit();
}
?> 