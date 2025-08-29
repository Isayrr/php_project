<?php
session_start();
require_once 'config/database.php';

// Check if user info is available
$user_info = null;
$show_check_form = false;

if (isset($_SESSION['pending_user_info'])) {
    $user_info = $_SESSION['pending_user_info'];
} elseif (isset($_POST['check_username']) && isset($_POST['check_email'])) {
    // Allow users to check status with username and email
    $username = trim($_POST['check_username']);
    $email = trim($_POST['check_email']);
    
    try {
        $stmt = $conn->prepare("SELECT username, email, role, approval_status, created_at FROM users WHERE username = ? AND email = ?");
        $stmt->execute([$username, $email]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $user_info = $user_data;
        } else {
            $error_message = "No account found with the provided username and email combination.";
        }
    } catch (PDOException $e) {
        $error_message = "Unable to check status at this time. Please try again later.";
    }
} else {
    $show_check_form = true;
}

// Get login error message if available
$login_error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Status Check - PESO Job Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            position: relative;
            background-color: #fff;
            color: #333;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/background-image.jpg') center center / cover no-repeat;
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            color: #fff;
        }

        .status-pending {
            color: #ffc107;
        }

        .status-approved {
            color: #28a745;
        }

        .status-rejected {
            color: #dc3545;
        }

        .btn-glass {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            color: #fff;
            font-weight: 500;
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            transform: translateY(-2px);
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            backdrop-filter: blur(8px);
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0.5rem;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }

        .timeline-item.completed::before {
            background: #28a745;
        }

        .timeline-item.current::before {
            background: #ffc107;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: 0.6875rem;
            top: 1.25rem;
            width: 2px;
            height: calc(100% - 1.25rem);
            background: rgba(255, 255, 255, 0.2);
        }

        .timeline-item:last-child::after {
            display: none;
        }

        h1, h2, h3, h4, h5, h6, p, span {
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .alert {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            color: #fff;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .logo-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }

        .logo-circle img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6">
                <div class="glass-card p-4">
                    <div class="logo-container">
                        <div class="logo-circle">
                            <img src="assets/images/new Peso logo.jpg" alt="PESO Logo">
                        </div>
                        <div class="logo-circle">
                            <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo">
                        </div>
                    </div>

                    <?php if ($show_check_form): ?>
                        <!-- Status Check Form -->
                        <div class="text-center mb-4">
                            <h2><i class="fas fa-search me-2"></i>Check Account Status</h2>
                            <p class="text-light">Enter your credentials to check your account approval status</p>
                        </div>

                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="check_username" placeholder="Enter your username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="check_email" placeholder="Enter your email address" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Check Status
                                </button>
                                <a href="index.php" class="btn btn-glass">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                                </a>
                            </div>
                        </form>

                    <?php elseif ($user_info): ?>
                        <!-- Status Display -->
                        <div class="text-center mb-4">
                            <h2><i class="fas fa-user-circle me-2"></i>Account Status</h2>
                        </div>

                        <?php if ($login_error): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i><?php echo $login_error; ?>
                            </div>
                        <?php endif; ?>

                        <!-- User Information -->
                        <div class="glass-card p-3 mb-4">
                            <h5><i class="fas fa-user me-2"></i>Account Information</h5>
                            <div class="row">
                                <div class="col-sm-6">
                                    <strong>Username:</strong><br>
                                    <span class="text-light"><?php echo htmlspecialchars($user_info['username']); ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <strong>Account Type:</strong><br>
                                    <span class="text-light"><?php echo ucfirst(htmlspecialchars($user_info['role'])); ?></span>
                                </div>
                            </div>
                            <?php if (isset($user_info['created_at'])): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Registered:</strong><br>
                                    <span class="text-light"><?php echo date('F j, Y \a\t g:i A', strtotime($user_info['created_at'])); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Current Status -->
                        <div class="glass-card p-3 mb-4">
                            <h5><i class="fas fa-info-circle me-2"></i>Current Status</h5>
                            <div class="text-center">
                                <?php if ($user_info['approval_status'] === 'pending'): ?>
                                    <i class="fas fa-clock fa-3x status-pending mb-3"></i>
                                    <h4 class="status-pending">Pending Approval</h4>
                                    <p>Your account is waiting for administrator review. This typically takes 24-48 hours.</p>
                                <?php elseif ($user_info['approval_status'] === 'approved'): ?>
                                    <i class="fas fa-check-circle fa-3x status-approved mb-3"></i>
                                    <h4 class="status-approved">Approved</h4>
                                    <p>Your account has been approved! You can now log in to the system.</p>
                                <?php elseif ($user_info['approval_status'] === 'rejected'): ?>
                                    <i class="fas fa-times-circle fa-3x status-rejected mb-3"></i>
                                    <h4 class="status-rejected">Application Rejected</h4>
                                    <p>Unfortunately, your account application has been rejected. Please contact support for more information.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Progress Timeline -->
                        <div class="glass-card p-3 mb-4">
                            <h5><i class="fas fa-tasks me-2"></i>Application Progress</h5>
                            <div class="timeline">
                                <div class="timeline-item completed">
                                    <h6>Registration Submitted</h6>
                                    <small class="text-light">Your account registration has been received</small>
                                </div>
                                <div class="timeline-item <?php echo ($user_info['approval_status'] === 'pending') ? 'current' : 'completed'; ?>">
                                    <h6>Under Review</h6>
                                    <small class="text-light">Administrator is reviewing your application</small>
                                </div>
                                <div class="timeline-item <?php echo ($user_info['approval_status'] === 'approved') ? 'completed' : ''; ?>">
                                    <h6>Account Approved</h6>
                                    <small class="text-light">You can log in and access the system</small>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <?php if ($user_info['approval_status'] === 'approved'): ?>
                                <a href="index.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                                </a>
                            <?php elseif ($user_info['approval_status'] === 'pending'): ?>
                                <button type="button" class="btn btn-glass" onclick="checkStatusAgain()">
                                    <i class="fas fa-sync-alt me-2"></i>Refresh Status
                                </button>
                            <?php elseif ($user_info['approval_status'] === 'rejected'): ?>
                                <a href="mailto:support@example.com" class="btn btn-glass">
                                    <i class="fas fa-envelope me-2"></i>Contact Support
                                </a>
                                <a href="register.php" class="btn btn-glass">
                                    <i class="fas fa-user-plus me-2"></i>Register New Account
                                </a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-glass">
                                <i class="fas fa-home me-2"></i>Back to Home
                            </a>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkStatusAgain() {
            window.location.reload();
        }

        // Auto-refresh for pending status every 30 seconds
        <?php if ($user_info && $user_info['approval_status'] === 'pending'): ?>
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        <?php endif; ?>

        // Clear session data after displaying
        <?php 
        if (isset($_SESSION['pending_user_info'])) {
            unset($_SESSION['pending_user_info']);
        }
        ?>
    </script>
</body>
</html> 