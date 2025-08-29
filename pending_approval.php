<?php
session_start();

// Check if the user has a pending registration
if (!isset($_SESSION['registration_pending'])) {
    header("Location: index.php");
    exit();
}

$username = isset($_SESSION['registered_username']) ? $_SESSION['registered_username'] : 'User';
$role = isset($_SESSION['registered_role']) ? $_SESSION['registered_role'] : 'account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Pending Approval</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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

        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            color: #fff;
        }

        .card-header {
            background: rgba(255, 255, 255, 0.1) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .alert-info {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #fff;
        }

        .btn-primary {
            background: linear-gradient(135deg, rgba(58, 143, 254, 0.8) 0%, rgba(150, 88, 254, 0.8) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, rgba(58, 143, 254, 0.9) 0%, rgba(150, 88, 254, 0.9) 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .text-warning {
            color: #ffd700 !important;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        }

        ol {
            padding-left: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
        }

        ol li {
            margin-bottom: 0.5rem;
        }

        h2, h5, p {
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        strong {
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .logo-circle, .talavera-circle {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body p-5 text-center">
                        <div class="d-flex justify-content-center align-items-center mb-4 gap-2">
                            <div class="logo-circle" style="width: 70px; height: 70px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <div class="talavera-circle" style="width: 70px; height: 70px;">
                                <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo">
                            </div>
                        </div>
                        <i class="fas fa-clock fa-4x text-warning mb-4"></i>
                        <h2 class="mb-4">Your Account is Pending Approval</h2>
                        <div class="alert alert-info">
                            <p class="mb-2">Thank you for registering, <strong><?php echo htmlspecialchars($username); ?></strong>!</p>
                            <p class="mb-2">Your <?php echo htmlspecialchars($role); ?> account has been created and is currently pending administrator approval.</p>
                            <p class="mb-0">You will receive an  notification once your account has been approved.</p>
                        </div>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">What happens next?</h5>
                            </div>
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li>Your account registration has been submitted to our administrators</li>
                                    <li>An administrator will review your information</li>
                                    <li>Once approved, you will be able to log in to your account</li>
                                    <li>You will receive a notification when your account is approved</li>
                                </ol>
                            </div>
                        </div>
                        <p>This process typically takes 24-48 hours. If you have any questions, please contact our support team.</p>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Return to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 