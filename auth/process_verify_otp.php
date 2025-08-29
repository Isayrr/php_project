<?php
session_start();
require_once '../config/database.php';

// Check if user has requested password reset
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

// Track OTP verification attempts
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

// Set maximum attempts
$max_attempts = 5;

// Check if user has exceeded max attempts
if ($_SESSION['otp_attempts'] >= $max_attempts) {
    $_SESSION['otp_error'] = "Too many incorrect attempts. Please request a new verification code.";
    header("Location: verify_otp.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = trim($_POST['otp']);
    $user_id = $_SESSION['reset_user_id'];
    
    // Log the verification attempt
    error_log("OTP verification attempt for user ID: {$user_id}, Email: {$_SESSION['reset_email']}");
    
    // Validate OTP format
    if (empty($otp) || strlen($otp) != 6 || !is_numeric($otp)) {
        $_SESSION['otp_error'] = "Please enter a valid 6-digit verification code.";
        $_SESSION['otp_attempts']++;
        header("Location: verify_otp.php");
        exit();
    }
    
    try {
        // Verify OTP
        $stmt = $conn->prepare("SELECT reset_token, reset_token_expiry FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $current_time = date('Y-m-d H:i:s');
        
        // Log the verification details for debugging
        error_log("OTP verification details - User OTP: {$otp}, Stored OTP: {$user['reset_token']}, Expiry: {$user['reset_token_expiry']}, Current Time: {$current_time}");
        
        // Check if OTP is invalid
        if (!$user || !$user['reset_token'] || $user['reset_token'] !== $otp) {
            $_SESSION['otp_attempts']++;
            $remaining_attempts = $max_attempts - $_SESSION['otp_attempts'];
            
            if ($_SESSION['otp_attempts'] >= $max_attempts) {
                $_SESSION['otp_error'] = "Too many incorrect attempts. Please request a new verification code.";
            } else {
                $_SESSION['otp_error'] = "Invalid verification code. You have {$remaining_attempts} attempts remaining.";
            }
            
            header("Location: verify_otp.php");
            exit();
        }
        
        // Check if OTP has expired
        if ($current_time > $user['reset_token_expiry']) {
            $_SESSION['otp_error'] = "The verification code has expired. Please request a new one.";
            header("Location: verify_otp.php");
            exit();
        }
        
        // OTP is valid, allow user to reset password
        $_SESSION['reset_verified'] = true;
        
        // Reset the attempts counter on successful verification
        $_SESSION['otp_attempts'] = 0;
        
        // Log successful verification
        error_log("OTP verification successful for user ID: {$user_id}, Email: {$_SESSION['reset_email']}");
        
        header("Location: reset_password.php");
        exit();
        
    } catch(PDOException $e) {
        error_log("Database error during OTP verification: " . $e->getMessage());
        $_SESSION['otp_error'] = "A database error occurred. Please try again later.";
        header("Location: verify_otp.php");
        exit();
    }
    
} else {
    header("Location: verify_otp.php");
    exit();
}
?> 