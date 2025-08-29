<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Check for resend request
if (isset($_GET['resend']) && $_GET['resend'] == '1' && isset($_SESSION['reset_user_id']) && isset($_SESSION['reset_email'])) {
    $user_id = $_SESSION['reset_user_id'];
    $email = $_SESSION['reset_email'];
    
    try {
        // Get user information
        $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate a new 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Update OTP in database
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
            $stmt->execute([$otp, $otp_expiry, $user_id]);
            
            // Send new OTP
            if (sendOTPEmail($email, $user['username'], $otp)) {
                $_SESSION['fp_success'] = "A new verification code has been sent to your email address.";
            } else {
                $_SESSION['otp_error'] = "Failed to send verification code. Please try again.";
            }
        }
    } catch(PDOException $e) {
        $_SESSION['otp_error'] = "A database error occurred. Please try again later.";
    }
    
    header("Location: verify_otp.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['fp_error'] = "Please enter a valid email address.";
        header("Location: forgot_password.php");
        exit();
    }
    
    try {
        // Check if email exists in the database
        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate a 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Check if table has reset_token column
            $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'reset_token'");
            $stmt->execute();
            $has_reset_token = $stmt->rowCount() > 0;
            
            if (!$has_reset_token) {
                // Add reset_token and reset_token_expiry columns if they don't exist
                $conn->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL, ADD COLUMN reset_token_expiry DATETIME NULL");
            }
            
            // Store OTP in the database
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
            $stmt->execute([$otp, $otp_expiry, $user['user_id']]);
            
            // Check if email credentials are properly configured
            $credentialsFile = __DIR__ . '/../config/mail_credentials.php';
            if (file_exists($credentialsFile)) {
                require_once $credentialsFile;
                
                // Check if default credentials are still being used
                if (GMAIL_USERNAME === 'your_email@gmail.com' || GMAIL_PASSWORD === 'your_app_password') {
                    $_SESSION['fp_error'] = "Email configuration is not set up properly. Please contact the administrator.";
                    
                    // Log the configuration issue
                    error_log("ERROR: Default email credentials are being used. OTP could not be sent to {$email}");
                    
                    // For administrators, provide a link to the debug tool
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                        $_SESSION['fp_error'] .= " As an admin, you can <a href='debug_email.php'>configure email settings here</a>.";
                    }
                    
                    header("Location: forgot_password.php");
                    exit();
                }
            } else {
                $_SESSION['fp_error'] = "Email configuration file is missing. Please contact the administrator.";
                error_log("ERROR: Mail credentials file is missing at: {$credentialsFile}");
                header("Location: forgot_password.php");
                exit();
            }
            
            // Send email with OTP
            if (sendOTPEmail($email, $user['username'], $otp)) {
                // Store user_id in session for verification
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_email'] = $email;
                
                $_SESSION['fp_success'] = "A verification code has been sent to your email address.";
                header("Location: verify_otp.php");
                exit();
            } else {
                $_SESSION['fp_error'] = "Failed to send verification code. Please check email configuration or try again later.";
                
                // For administrators, provide a link to the debug tool
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    $_SESSION['fp_error'] .= " As an admin, you can <a href='debug_email.php'>troubleshoot email issues here</a>.";
                }
                
                header("Location: forgot_password.php");
                exit();
            }
            
        } else {
            // For security reasons, show same message even if email doesn't exist
            $_SESSION['fp_success'] = "If your email exists in our system, you will receive a verification code shortly.";
            header("Location: forgot_password.php");
            exit();
        }
        
    } catch(PDOException $e) {
        $_SESSION['fp_error'] = "A database error occurred. Please try again later.";
        error_log("Database error in forgot password: " . $e->getMessage());
        header("Location: forgot_password.php");
        exit();
    }
    
} else {
    header("Location: forgot_password.php");
    exit();
}

/**
 * Send OTP email using PHPMailer
 * 
 * @param string $email Recipient email address
 * @param string $username Recipient username
 * @param string $otp One-time password
 * @return bool Whether the email was sent successfully
 */
function sendOTPEmail($email, $username, $otp) {
    // Create a file to store Gmail credentials if it doesn't exist
    $credentialsFile = __DIR__ . '/../config/mail_credentials.php';
    
    if (!file_exists($credentialsFile)) {
        $defaultContent = <<<EOT
<?php
// Gmail credentials for sending OTP emails
// Replace these with your actual Gmail credentials
define('GMAIL_USERNAME', 'your_email@gmail.com');
define('GMAIL_PASSWORD', 'your_app_password');
define('GMAIL_SENDER_NAME', 'Job Portal Admin');
?>
EOT;
        file_put_contents($credentialsFile, $defaultContent);
    }
    
    // Load mail credentials
    require_once $credentialsFile;
    
    // Log email attempt
    error_log("Attempting to send OTP email to: " . $email);
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USERNAME;
        $mail->Password   = GMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0; // Set to 2 for debugging
        
        // Recipients - we make sure to explicitly set the recipient's email
        $mail->setFrom(GMAIL_USERNAME, GMAIL_SENDER_NAME);
        $mail->addAddress($email, $username);
        $mail->addReplyTo(GMAIL_USERNAME, GMAIL_SENDER_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Verification Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;'>
                <h2 style='color: #333366;'>Password Reset Request</h2>
                <p>Hello {$username},</p>
                <p>We received a request to reset your password for your Job Portal account. Please use the following verification code to complete the process:</p>
                <div style='background-color: #f5f5f5; padding: 15px; margin: 20px 0; text-align: center; border-radius: 4px;'>
                    <h1 style='color: #333366; margin: 0; letter-spacing: 5px;'>{$otp}</h1>
                </div>
                <p>This code will expire in 15 minutes.</p>
                <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                <p>Regards,<br>Job Portal Team</p>
                <p style='font-size: 12px; color: #999;'>This email was sent to: {$email}</p>
            </div>
        ";
        // Plain text version for non-HTML mail clients
        $mail->AltBody = "Hello {$username},\n\nWe received a request to reset your password for your Job Portal account. Please use the following verification code to complete the process: {$otp}\n\nThis code will expire in 15 minutes.\n\nIf you did not request a password reset, please ignore this email or contact support if you have concerns.\n\nRegards,\nJob Portal Team";
        
        $result = $mail->send();
        error_log("Email successfully sent to: " . $email);
        return $result;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Mailer Error when sending to {$email}: " . $mail->ErrorInfo);
        return false;
    }
}
?> 