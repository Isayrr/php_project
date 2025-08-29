<?php
session_start();
require_once '../config/database.php';
require_once '../admin/includes/admin_notifications.php';
require_once '../includes/user_notifications.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validation
    $errors = [];

    if (empty($username)) {
        $errors[] = "Username is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (empty($role) || !in_array($role, ['jobseeker', 'employer'])) {
        $errors[] = "Invalid role selected";
    }

    // Check if username or email already exists
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username or email already exists";
        }
    } catch(PDOException $e) {
        $errors[] = "Database error occurred";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();

            // Insert user with pending approval status
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, approval_status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$username, $email, $hashed_password, $role]);
            $user_id = $conn->lastInsertId();

            // Create user profile
            $stmt = $conn->prepare("INSERT INTO user_profiles (user_id) VALUES (?)");
            $stmt->execute([$user_id]);

            // If employer, create company profile
            if ($role === 'employer') {
                $stmt = $conn->prepare("INSERT INTO companies (employer_id) VALUES (?)");
                $stmt->execute([$user_id]);
            }

            // Notify admins about the pending account approval
            notifyAdminPendingAccountApproval($conn, $user_id, $username, $role, $email);

            // Commit transaction
            $conn->commit();

            // Set session to indicate pending approval
            $_SESSION['registration_pending'] = true;
            $_SESSION['registered_username'] = $username;
            $_SESSION['registered_role'] = $role;

            // Redirect to pending approval page
            header("Location: ../pending_approval.php");
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            $_SESSION['register_errors'] = ["Database error: " . $e->getMessage()];
            header("Location: ../register.php?error=1");
            exit();
        }
    } else {
        $_SESSION['register_errors'] = $errors;
        header("Location: ../register.php?error=1");
        exit();
    }
} else {
    header("Location: ../register.php");
    exit();
}
?> 