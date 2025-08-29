<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // First check if user exists and password is correct
        $stmt = $conn->prepare("SELECT user_id, username, password, role, status, approval_status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check approval status
            if ($user['approval_status'] !== 'approved') {
                // Store user info for status display
                $_SESSION['pending_user_info'] = [
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'approval_status' => $user['approval_status']
                ];
                
                // Different messages based on status
                if ($user['approval_status'] === 'pending') {
                    $_SESSION['login_error'] = 'Your account is still pending administrator approval. Please be patient while we review your application.';
                } elseif ($user['approval_status'] === 'rejected') {
                    $_SESSION['login_error'] = 'Your account application has been rejected. Please contact support for more information.';
                } else {
                    $_SESSION['login_error'] = 'Your account is not yet approved. Please contact support for assistance.';
                }
                
                // Redirect to index page with notification parameters
                $redirect_url = "../index.php?user_notification=" . urlencode($user['approval_status']) . "&username=" . urlencode($user['username']);
                header("Location: " . $redirect_url);
                exit();
            }
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                $_SESSION['login_error'] = 'Your account has been deactivated. Please contact an administrator.';
                header("Location: ../index.php?error=3");
                exit();
            }

            // Check for any unread notifications about account status
            $stmt = $conn->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                AND related_type = 'user_approval' 
                AND is_read = 0 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user['user_id']]);
            $notification = $stmt->fetch();

            if ($notification) {
                // Mark the notification as read
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
                $stmt->execute([$notification['notification_id']]);
                
                // Set the notification message in session
                $_SESSION['account_notification'] = [
                    'title' => $notification['title'],
                    'message' => $notification['message']
                ];
            }

            // All checks passed, set session and redirect
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: ../admin/dashboard.php");
                    break;
                case 'employer':
                    header("Location: ../employer/dashboard.php");
                    break;
                case 'jobseeker':
                    header("Location: ../jobseeker/dashboard.php");
                    break;
                default:
                    header("Location: ../index.php");
            }
            exit();
        } else {
            // Invalid username or password
            $_SESSION['login_error'] = 'Invalid username or password.';
            header("Location: ../index.php?error=1");
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['login_error'] = 'A database error occurred. Please try again later.';
        header("Location: ../index.php?error=4");
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
?> 