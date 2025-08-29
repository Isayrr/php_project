<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get notification ID from URL
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notification_id <= 0) {
    header("Location: notifications.php");
    exit();
}

try {
    // Get notification details
    $stmt = $conn->prepare("
        SELECT notification_id, title, message, related_id, related_type, is_read
        FROM notifications 
        WHERE notification_id = ? AND user_id = ?
    ");
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$notification) {
        header("Location: notifications.php");
        exit();
    }
    
    // Mark notification as read if not already read
    if (!$notification['is_read']) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->execute([$notification_id]);
    }
    
    // Route based on notification type and related_id
    $redirect_url = "notifications.php"; // Default fallback
    
    switch ($notification['related_type']) {
        case 'account_approval':
        case 'pending_account':
        case 'user':
            // Navigate to users page with specific user highlighted
            if ($notification['related_id']) {
                $redirect_url = "users.php?highlight=" . $notification['related_id'];
                
                // If it's a pending account, filter to show pending approvals
                if ($notification['related_type'] === 'pending_account') {
                    $redirect_url = "users.php?approval=pending&highlight=" . $notification['related_id'];
                }
            } else {
                $redirect_url = "users.php";
            }
            break;
            
        case 'job_approval':
        case 'pending_job':
        case 'job':
            // Navigate to jobs page with specific job highlighted
            if ($notification['related_id']) {
                $redirect_url = "jobs.php?highlight=" . $notification['related_id'];
                
                // If it's a pending job, filter to show pending approvals
                if ($notification['related_type'] === 'pending_job') {
                    $redirect_url = "jobs.php?approval=pending&highlight=" . $notification['related_id'];
                }
            } else {
                $redirect_url = "jobs.php";
            }
            break;
            
        case 'application':
            // Navigate to applications page with specific application highlighted
            if ($notification['related_id']) {
                $redirect_url = "applications.php?highlight=" . $notification['related_id'];
            } else {
                $redirect_url = "applications.php";
            }
            break;
            
        case 'company':
            // Navigate to companies page with specific company highlighted
            if ($notification['related_id']) {
                $redirect_url = "companies.php?highlight=" . $notification['related_id'];
            } else {
                $redirect_url = "companies.php";
            }
            break;
            
        case 'event':
            // Navigate to job fair events page with specific event highlighted
            if ($notification['related_id']) {
                $redirect_url = "job-fair-events.php?highlight=" . $notification['related_id'];
            } else {
                $redirect_url = "job-fair-events.php";
            }
            break;
            
        case 'system':
            // For system notifications, go to dashboard or appropriate system page
            $redirect_url = "dashboard.php";
            break;
            
        default:
            // For unknown types, stay on notifications page
            $redirect_url = "notifications.php";
            break;
    }
    
    // Store success message in session to show on target page
    $_SESSION['notification_action'] = "Viewed notification: " . htmlspecialchars($notification['title']);
    
    // Redirect to the appropriate page
    header("Location: " . $redirect_url);
    exit();
    
} catch (Exception $e) {
    error_log("Notification router error: " . $e->getMessage());
    header("Location: notifications.php?error=routing_failed");
    exit();
}
?> 