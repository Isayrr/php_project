<?php
session_start();
require_once '../config/database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Forgot Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['fp_error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['fp_error']; 
                                unset($_SESSION['fp_error']);
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
                        
                        <form action="process_forgot_password.php" method="post">
                            <div class="form-group mb-3">
                                <label for="email">Enter your email address</label>
                                <input type="email" name="email" id="email" class="form-control" required>
                                <small class="form-text text-muted">We'll send a verification code (OTP) to this email.</small>
                            </div>
                            <div class="form-group mb-3">
                                <button type="submit" class="btn btn-primary btn-block">Send Verification Code</button>
                            </div>
                            <div class="text-center">
                                <a href="../index.php">Back to Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 