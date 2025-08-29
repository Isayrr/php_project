<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/notifications.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get recent notifications (limit to 15 for dropdown)
    $stmt = $conn->prepare("
        SELECT 
            notification_id,
            title,
            message,
            related_id,
            related_type,
            created_at,
            is_read
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 15
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
    // Format notifications for frontend
    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        // Determine the appropriate action URL based on notification type
        $action_url = '#';
        switch ($notification['related_type']) {
            case 'job_match':
            case 'job':
                $action_url = 'view-job.php?id=' . $notification['related_id'];
                break;
            case 'application':
                $action_url = 'applications.php';
                break;
            case 'profile':
                $action_url = 'profile.php';
                break;
            case 'resume':
                $action_url = 'profile.php#resume-section';
                break;
            case 'skills':
                $action_url = 'skills.php';
                break;
            case 'job_fair':
                $action_url = 'job-fair-events.php';
                break;
            case 'deadline':
                $action_url = 'view-job.php?id=' . $notification['related_id'];
                break;
            case 'new_jobs':
                $action_url = 'jobs.php';
                break;
            default:
                $action_url = 'dashboard.php';
        }
        
        // Get icon based on notification type
        $icon = 'fas fa-bell';
        switch ($notification['related_type']) {
            case 'job_match':
                $icon = 'fas fa-bullseye text-success';
                break;
            case 'application':
                $icon = 'fas fa-paper-plane text-primary';
                break;
            case 'profile':
                $icon = 'fas fa-user-edit text-warning';
                break;
            case 'resume':
                $icon = 'fas fa-file-alt text-info';
                break;
            case 'skills':
                $icon = 'fas fa-lightbulb text-warning';
                break;
            case 'job_fair':
                $icon = 'fas fa-calendar-alt text-purple';
                break;
            case 'deadline':
                $icon = 'fas fa-clock text-danger';
                break;
            case 'new_jobs':
                $icon = 'fas fa-briefcase text-success';
                break;
            case 'announcement':
                $icon = 'fas fa-megaphone text-primary';
                break;
        }
        
        $formatted_notifications[] = [
            'notification_id' => $notification['notification_id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'related_id' => $notification['related_id'],
            'related_type' => $notification['related_type'],
            'created_at' => $notification['created_at'],
            'is_read' => $notification['is_read'],
            'action_url' => $action_url,
            'icon' => $icon,
            'time_ago' => getTimeAgo($notification['created_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications,
        'unread_count' => (int)$unread_count
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching jobseeker notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("General error fetching jobseeker notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}

/**
 * Get time ago string for notification
 */
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Just now';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', strtotime($datetime));
    }
}
?> 