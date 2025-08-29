<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Portal - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: url('assets/images/background-image.jpg') center center / cover no-repeat fixed;
            position: relative;
            font-family: 'Poppins', sans-serif;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .container {
            position: relative;
            z-index: 2;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .register-card .card-body {
            padding: 2.5rem;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.6);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .logo-circle:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.8);
        }

        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: transparent !important;
            border-radius: 50%;
            padding: 0 !important;
            box-sizing: border-box !important;
            margin: auto !important;
            transform: none !important;
            clip-path: circle(50% at 50% 50%) !important;
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #333;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
        }

        .form-label {
            color: #fff;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #9658fe 0%, #3a8ffe 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .alert {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            backdrop-filter: blur(5px);
        }

        h2 {
            color: #fff;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        a {
            color: #3a8ffe;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        a:hover {
            color: #9658fe;
            text-decoration: underline;
        }

        .text-center p {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-5">
            <div class="col-md-8">
                <div class="register-card">
                    <div class="card-body">
                        <div class="logo-container">
                            <div class="logo-circle">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <div class="logo-circle">
                                <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="logo-img">
                            </div>
                        </div>
                        <h2 class="text-center mb-4">Create an Account</h2>
                        <div class="alert alert-info mb-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-lg me-2"></i>
                                <div>
                                    <strong class="d-block">Important Notice</strong>
                                    All new accounts require administrator approval before activation. You will be notified once your account has been approved.
                                </div>
                            </div>
                        </div>
                        <?php
                        if (isset($_GET['error'])) {
                            if (isset($_SESSION['register_errors']) && is_array($_SESSION['register_errors'])) {
                                echo '<div class="alert alert-danger" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); border: none;">';
                                foreach ($_SESSION['register_errors'] as $error) {
                                    echo '<div class="d-flex align-items-center mb-2">';
                                    echo '<i class="fas fa-exclamation-circle fa-lg me-2"></i>';
                                    echo '<div>' . htmlspecialchars($error) . '</div>';
                                    echo '</div>';
                                }
                                echo '</div>';
                                unset($_SESSION['register_errors']);
                            } else {
                                echo '<div class="alert alert-danger" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); border: none;">';
                                echo '<div class="d-flex align-items-center">';
                                echo '<i class="fas fa-exclamation-circle fa-lg me-2"></i>';
                                echo '<div><strong>Registration Failed</strong><br>Please try again.</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                        ?>
                        <form action="auth/register.php" method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label text-white">
                                        <i class="fas fa-user me-2"></i>Username
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text" style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                            <i class="fas fa-user text-primary"></i>
                                        </span>
                                        <input type="text" class="form-control" id="username" name="username" required 
                                               style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);"
                                               placeholder="Choose a username">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label text-white">
                                        <i class="fas fa-envelope me-2"></i>Email
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text" style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                            <i class="fas fa-envelope text-primary"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" required
                                               style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);"
                                               placeholder="Enter your email">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label text-white">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text" style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                            <i class="fas fa-lock text-primary"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" required
                                               style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);"
                                               placeholder="Create a password">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                                                style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label text-white">
                                        <i class="fas fa-lock me-2"></i>Confirm Password
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text" style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                            <i class="fas fa-lock text-primary"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                               style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);"
                                               placeholder="Confirm your password">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword"
                                                style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label text-white">
                                    <i class="fas fa-user-tag me-2"></i>Register as
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                        <i class="fas fa-user-tag text-primary"></i>
                                    </span>
                                    <select class="form-select" id="role" name="role" required
                                            style="background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 255, 255, 0.2);">
                                        <option value="">Select role</option>
                                        <option value="jobseeker">Job Seeker</option>
                                        <option value="employer">Employer</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" 
                                        style="background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%); border: none; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </button>
                            </div>
                        </form>
                        <div class="text-center mt-4">
                            <p class="text-white-50 mb-0">
                                <i class="fas fa-sign-in-alt me-1"></i>Already have an account? 
                                <a href="index.php" class="text-white">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.querySelector('#togglePassword');
            const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
            const password = document.querySelector('#password');
            const confirmPassword = document.querySelector('#confirm_password');

            function togglePasswordVisibility(button, input) {
                button.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }

            togglePasswordVisibility(togglePassword, password);
            togglePasswordVisibility(toggleConfirmPassword, confirmPassword);

            // Form validation with icons
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input[required], select[required]');

            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const icon = this.previousElementSibling.querySelector('i');
                    if (this.value.length > 0) {
                        icon.classList.remove('text-primary');
                        icon.classList.add('text-success');
                    } else {
                        icon.classList.remove('text-success');
                        icon.classList.add('text-primary');
                    }
                });
            });

            // Password match validation
            confirmPassword.addEventListener('input', function() {
                const icon = this.previousElementSibling.querySelector('i');
                if (this.value === password.value) {
                    icon.classList.remove('text-primary');
                    icon.classList.add('text-success');
                } else {
                    icon.classList.remove('text-success');
                    icon.classList.add('text-danger');
                }
            });
        });
    </script>
</body>
</html> 