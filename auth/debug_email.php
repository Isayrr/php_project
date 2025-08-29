<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Security check - only allow access in development environments
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
    die("This debugging page is only available in development environments.");
}

$error = '';
$success = '';
$output = '';
$emailConfig = [];

// Read current email configuration
$credentialsFile = __DIR__ . '/../config/mail_credentials.php';
if (file_exists($credentialsFile)) {
    include $credentialsFile;
    $emailConfig = [
        'username' => defined('GMAIL_USERNAME') ? GMAIL_USERNAME : 'not set',
        'password' => defined('GMAIL_PASSWORD') ? (GMAIL_PASSWORD === 'your_app_password' ? 'using default password (not set)' : 'set (hidden)') : 'not set',
        'sender_name' => defined('GMAIL_SENDER_NAME') ? GMAIL_SENDER_NAME : 'not set',
    ];
} else {
    $error = "Mail credentials file doesn't exist at: $credentialsFile";
}

// Process email test request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'test_email') {
        $test_email = filter_input(INPUT_POST, 'test_email', FILTER_VALIDATE_EMAIL);
        
        if (!$test_email) {
            $error = "Please enter a valid email address.";
        } else {
            // Generate a test OTP
            $otp = sprintf("%06d", rand(100000, 999999));
            
            // Create a new PHPMailer instance with debug output
            $mail = new PHPMailer(true);
            
            try {
                // Enable debug output
                $mail->SMTPDebug = 3; // Enable verbose debug output
                ob_start(); // Start output buffering to capture debug info
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                
                // Use the values from mail_credentials.php
                if (defined('GMAIL_USERNAME') && defined('GMAIL_PASSWORD')) {
                    $mail->Username = GMAIL_USERNAME;
                    $mail->Password = GMAIL_PASSWORD;
                } else {
                    throw new Exception('Gmail credentials are not properly defined.');
                }
                
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Recipients
                $mail->setFrom(GMAIL_USERNAME, GMAIL_SENDER_NAME);
                $mail->addAddress($test_email); // The recipient's email
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Debug Test - OTP Verification Code';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;'>
                        <h2 style='color: #333366;'>Test OTP Email</h2>
                        <p>This is a test email from the debug page.</p>
                        <p>Your verification code is:</p>
                        <div style='background-color: #f5f5f5; padding: 15px; margin: 20px 0; text-align: center; border-radius: 4px;'>
                            <h1 style='color: #333366; margin: 0; letter-spacing: 5px;'>{$otp}</h1>
                        </div>
                        <p>If you didn't request this test, you can ignore this email.</p>
                        <p>Debug Info:</p>
                        <ul>
                            <li>Timestamp: " . date('Y-m-d H:i:s') . "</li>
                            <li>Sender: " . GMAIL_USERNAME . "</li>
                            <li>Recipient: " . $test_email . "</li>
                        </ul>
                    </div>
                ";
                $mail->AltBody = "Test OTP Email\n\nThis is a test email from the debug page.\n\nYour verification code is: {$otp}\n\nIf you didn't request this test, you can ignore this email.";
                
                // Send the email
                $mail->send();
                
                // Get the debug output
                $output = ob_get_clean();
                $success = "Test email with OTP {$otp} sent to {$test_email} successfully! Check your inbox (and spam folder).";
                
            } catch (Exception $e) {
                // Get the debug output even if there was an error
                $output = ob_get_clean();
                $error = "Message could not be sent. Error: {$mail->ErrorInfo}";
            }
        }
    }
    
    // Handle updating email configuration
    if (isset($_POST['action']) && $_POST['action'] === 'update_config') {
        $newUsername = filter_input(INPUT_POST, 'gmail_username', FILTER_VALIDATE_EMAIL);
        $newPassword = $_POST['gmail_password'];
        $newSenderName = filter_var($_POST['sender_name'], FILTER_SANITIZE_STRING);
        
        if (!$newUsername) {
            $error = "Please enter a valid Gmail address.";
        } elseif (empty($newPassword)) {
            $error = "Please enter your app password.";
        } elseif (empty($newSenderName)) {
            $error = "Please enter a sender name.";
        } else {
            // Create the new configuration content
            $configContent = "<?php
// Gmail credentials for sending OTP emails

// Your Gmail email address
define('GMAIL_USERNAME', '{$newUsername}');

// Your Gmail app password
define('GMAIL_PASSWORD', '{$newPassword}');

// Name that will appear as the sender
define('GMAIL_SENDER_NAME', '{$newSenderName}');
?>";
            
            // Write the new configuration to the file
            if (file_put_contents($credentialsFile, $configContent)) {
                $success = "Email configuration updated successfully!";
                
                // Update the displayed configuration
                $emailConfig = [
                    'username' => $newUsername,
                    'password' => 'set (hidden)',
                    'sender_name' => $newSenderName,
                ];
            } else {
                $error = "Failed to write to the configuration file. Check file permissions.";
            }
        }
    }
}

// Function to check if a string is the default placeholder
function is_default($value, $default) {
    return $value === $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Debug Tool</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .code-block {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .debug-output {
            max-height: 400px;
            overflow-y: auto;
        }
        .status-container {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-error {
            background-color: #ffebee;
            border-left: 5px solid #f44336;
        }
        .status-success {
            background-color: #e8f5e9;
            border-left: 5px solid #4caf50;
        }
        .status-warning {
            background-color: #fff8e1;
            border-left: 5px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Email Debug Tool</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h5>⚠️ This is a debugging page for troubleshooting OTP email issues</h5>
                            <p class="mb-0">Use this page to test your email configuration and diagnose problems with OTP sending.</p>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="status-container status-error">
                            <h5>❌ Error</h5>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="status-container status-success">
                            <h5>✅ Success</h5>
                            <p><?php echo htmlspecialchars($success); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <h5>Current Email Configuration</h5>
                        <div class="status-container <?php echo (is_default($emailConfig['username'] ?? '', 'your_email@gmail.com') || is_default($emailConfig['password'] ?? '', 'using default password (not set)')) ? 'status-warning' : 'status-success'; ?>">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Setting</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                </tr>
                                <tr>
                                    <td>Gmail Username</td>
                                    <td><?php echo htmlspecialchars($emailConfig['username'] ?? 'not set'); ?></td>
                                    <td>
                                        <?php if (is_default($emailConfig['username'] ?? '', 'your_email@gmail.com')): ?>
                                            <span class="badge bg-danger">Not Configured</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Configured</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Gmail Password</td>
                                    <td><?php echo htmlspecialchars($emailConfig['password'] ?? 'not set'); ?></td>
                                    <td>
                                        <?php if (is_default($emailConfig['password'] ?? '', 'using default password (not set)')): ?>
                                            <span class="badge bg-danger">Not Configured</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Configured</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Sender Name</td>
                                    <td><?php echo htmlspecialchars($emailConfig['sender_name'] ?? 'not set'); ?></td>
                                    <td>
                                        <?php if (is_default($emailConfig['sender_name'] ?? '', 'Job Portal Admin')): ?>
                                            <span class="badge bg-warning">Default Value</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Configured</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php if (is_default($emailConfig['username'] ?? '', 'your_email@gmail.com') || is_default($emailConfig['password'] ?? '', 'using default password (not set)')): ?>
                            <div class="alert alert-danger">
                                <strong>Configuration Issue:</strong> You are still using the default placeholder values for your email configuration. You must update these with your actual Gmail credentials for OTP emails to work.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">1. Update Email Configuration</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <input type="hidden" name="action" value="update_config">
                                            
                                            <div class="mb-3">
                                                <label for="gmail_username" class="form-label">Gmail Email Address</label>
                                                <input type="email" class="form-control" id="gmail_username" name="gmail_username" 
                                                    value="<?php echo htmlspecialchars($emailConfig['username'] === 'your_email@gmail.com' ? '' : $emailConfig['username']); ?>" 
                                                    placeholder="your.gmail@gmail.com" required>
                                                <small class="form-text text-muted">This must be a valid Gmail account.</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="gmail_password" class="form-label">Gmail App Password</label>
                                                <input type="password" class="form-control" id="gmail_password" name="gmail_password" placeholder="16-character app password" required>
                                                <small class="form-text text-muted">
                                                    <a href="https://myaccount.google.com/apppasswords" target="_blank">Generate an App Password</a> 
                                                    (requires 2-step verification to be enabled on your Google account)
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="sender_name" class="form-label">Sender Name</label>
                                                <input type="text" class="form-control" id="sender_name" name="sender_name" 
                                                    value="<?php echo htmlspecialchars($emailConfig['sender_name']); ?>" 
                                                    placeholder="Your Company Name" required>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Save Configuration</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">2. Send Test OTP Email</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <input type="hidden" name="action" value="test_email">
                                            
                                            <div class="mb-3">
                                                <label for="test_email" class="form-label">Send Test OTP To</label>
                                                <input type="email" class="form-control" id="test_email" name="test_email" placeholder="Enter recipient email" required>
                                                <small class="form-text text-muted">This can be any email address where you want to receive the test OTP.</small>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-success" <?php echo (is_default($emailConfig['username'] ?? '', 'your_email@gmail.com') || is_default($emailConfig['password'] ?? '', 'using default password (not set)')) ? 'disabled' : ''; ?>>
                                                Send Test OTP
                                            </button>
                                            
                                            <?php if (is_default($emailConfig['username'] ?? '', 'your_email@gmail.com') || is_default($emailConfig['password'] ?? '', 'using default password (not set)')): ?>
                                            <small class="form-text text-danger">You must configure your email settings before sending a test.</small>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($output): ?>
                        <div class="mt-4">
                            <h5>SMTP Debug Output</h5>
                            <div class="code-block debug-output">
<?php echo htmlspecialchars($output); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5>How to Fix OTP Email Issues</h5>
                            <ol>
                                <li><strong>Update Email Configuration:</strong> Use the form above to set your Gmail credentials.</li>
                                <li><strong>Generate App Password:</strong> You must use an App Password, not your regular Gmail password.
                                    <ul>
                                        <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                                        <li>Enable 2-Step Verification if not already enabled</li>
                                        <li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">App Passwords</a></li>
                                        <li>Generate a new app password for "Mail" and device "Other (Job Portal)"</li>
                                        <li>Copy the 16-character password and paste it in the configuration form</li>
                                    </ul>
                                </li>
                                <li><strong>Test the Email Delivery:</strong> After updating the configuration, send a test OTP to verify it works.</li>
                                <li><strong>Check Spam Folder:</strong> OTP emails might be delivered to your spam/junk folder.</li>
                                <li><strong>Review Debug Output:</strong> The SMTP debug output above can help identify specific issues.</li>
                            </ol>
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="../index.php" class="btn btn-secondary">Back to Home</a>
                            <a href="forgot_password.php" class="btn btn-primary">Go to Forgot Password</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 