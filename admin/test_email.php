<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$success = false;
$error = '';
$recipient_email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $test_email = isset($_POST['test_email']) ? trim($_POST['test_email']) : '';
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $recipient_email = $test_email;
        // Load mail credentials
        $credentialsFile = __DIR__ . '/../config/mail_credentials.php';
        
        if (!file_exists($credentialsFile)) {
            $error = "Mail credentials file is missing. Please create it first.";
        } else {
            require_once $credentialsFile;
            
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->SMTPDebug = isset($_POST['debug_mode']) ? 2 : 0;
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = GMAIL_USERNAME;
                $mail->Password = GMAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Recipients
                $mail->setFrom(GMAIL_USERNAME, GMAIL_SENDER_NAME);
                $mail->addAddress($test_email);
                $mail->addReplyTo(GMAIL_USERNAME, GMAIL_SENDER_NAME);
                
                // Generate a sample OTP for the test
                $sample_otp = sprintf("%06d", mt_rand(1, 999999));
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Test OTP Email from Job Portal';
                $mail->Body = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;">
                    <h2 style="color: #333366;">Email Configuration Test</h2>
                    <p>This is a test email from your Job Portal application.</p>
                    <p>If you received this email, your email configuration for the password reset feature is working properly.</p>
                    
                    <div style="background-color: #f5f5f5; padding: 15px; margin: 20px 0; text-align: center; border-radius: 4px;">
                        <h2 style="color: #333366; margin: 0; letter-spacing: 5px;">Sample OTP: '.$sample_otp.'</h2>
                    </div>
                    
                    <p>Current settings:</p>
                    <ul>
                        <li>SMTP Server: smtp.gmail.com</li>
                        <li>Username: ' . htmlspecialchars(GMAIL_USERNAME) . '</li>
                        <li>Sender Name: ' . htmlspecialchars(GMAIL_SENDER_NAME) . '</li>
                        <li>Recipient: <strong>' . htmlspecialchars($test_email) . '</strong></li>
                    </ul>
                    <p style="margin-top: 20px;">Best regards,<br>Job Portal Admin</p>
                    <p style="font-size: 12px; color: #999;">This email was sent to: '.$test_email.'</p>
                </div>';
                
                // Plain text version
                $mail->AltBody = "Email Configuration Test\n\nThis is a test email from your Job Portal application.\n\nIf you received this email, your email configuration for the password reset feature is working properly.\n\nSample OTP: {$sample_otp}\n\nCurrent settings:\n- SMTP Server: smtp.gmail.com\n- Username: ".GMAIL_USERNAME."\n- Sender Name: ".GMAIL_SENDER_NAME."\n- Recipient: {$test_email}\n\nBest regards,\nJob Portal Admin";
                
                if (isset($_POST['debug_mode'])) {
                    // Capture output
                    ob_start();
                    $mail->send();
                    $debug_output = ob_get_clean();
                } else {
                    $mail->send();
                    $debug_output = '';
                }
                
                $success = true;
                
            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                if (isset($_POST['debug_mode'])) {
                    $debug_output = "ERROR: " . $mail->ErrorInfo;
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Configuration</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Test Email Configuration</h4>
                        <a href="dashboard.php" class="btn btn-sm btn-outline-light">Back to Dashboard</a>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Note:</strong> This page helps you test if your email configuration is working properly for the password reset feature.
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <strong>Success!</strong> Test email with sample OTP has been sent to <strong><?php echo htmlspecialchars($recipient_email); ?></strong>.
                                <hr>
                                <p class="mb-0">Please check your inbox (and spam/junk folder) to verify the email was received. The email should contain a sample OTP code and clearly indicate it was sent to your email address.</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="test_email" class="form-label">Send Test Email To:</label>
                                <input type="email" name="test_email" id="test_email" class="form-control" required placeholder="Enter your Gmail or any email address">
                                <small class="form-text text-muted">Enter the email address where you want to receive the test OTP</small>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="debug_mode" name="debug_mode" value="1">
                                <label class="form-check-label" for="debug_mode">Enable Debug Mode</label>
                                <small class="form-text text-muted d-block">Shows technical details about the email sending process</small>
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Send Test Email with Sample OTP</button>
                            </div>
                        </form>
                        
                        <?php if (isset($debug_output) && !empty($debug_output)): ?>
                            <div class="mt-4">
                                <h5>Debug Output:</h5>
                                <div class="bg-light p-3 border rounded">
                                    <pre style="white-space: pre-wrap;"><?php echo htmlspecialchars($debug_output); ?></pre>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <h5>Current Mail Configuration:</h5>
                        <?php
                        if (file_exists(__DIR__ . '/../config/mail_credentials.php')) {
                            require_once __DIR__ . '/../config/mail_credentials.php';
                            echo '<table class="table table-bordered">';
                            echo '<tr><th>Setting</th><th>Value</th></tr>';
                            echo '<tr><td>SMTP Server</td><td>smtp.gmail.com</td></tr>';
                            echo '<tr><td>Gmail Username</td><td>' . htmlspecialchars(GMAIL_USERNAME) . '</td></tr>';
                            echo '<tr><td>Password</td><td>***********</td></tr>';
                            echo '<tr><td>Sender Name</td><td>' . htmlspecialchars(GMAIL_SENDER_NAME) . '</td></tr>';
                            echo '</table>';
                            
                            if (GMAIL_USERNAME === 'your_email@gmail.com' || GMAIL_PASSWORD === 'your_app_password') {
                                echo '<div class="alert alert-warning mt-3">';
                                echo '<strong>Warning:</strong> You are still using the default placeholder email credentials. Please update them with your actual Gmail information.';
                                echo '</div>';
                            }
                            
                            echo '<div class="alert alert-info mt-3">';
                            echo '<strong>How to update these settings:</strong><br>';
                            echo 'Edit the file at <code>config/mail_credentials.php</code> to update your Gmail credentials. See the EMAIL-SETUP-GUIDE.md file for detailed instructions.';
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-danger">';
                            echo 'Mail credentials file is missing! Please create the file <code>config/mail_credentials.php</code>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 