<?php
/**
 * Admin Notification Helper Functions
 * Functions for creating and managing admin notifications
 */

require_once __DIR__ . '/../../includes/notifications.php';

/**
 * Create a notification for admin users
 * 
 * @param PDO $conn - Database connection
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param int $related_id - Optional related entity ID
 * @param string $related_type - Optional related entity type
 * @return bool - Whether the notification was created successfully
 */
function createAdminNotification($conn, $title, $message, $related_id = null, $related_type = null) {
    try {
        // Get all admin users
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin' AND status = 'active'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $success_count = 0;
        foreach ($admins as $admin_id) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, ?, ?, ?, ?, NOW(), 0)
            ");
            if ($stmt->execute([$admin_id, $title, $message, $related_id, $related_type])) {
                $success_count++;
            }
        }
        
        return $success_count > 0;
    } catch (Exception $e) {
        error_log("Error creating admin notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify admins about new user registration
 * 
 * @param PDO $conn - Database connection
 * @param int $user_id - ID of the newly registered user
 * @param string $username - Username of the new user
 * @param string $role - Role of the new user
 * @return bool
 */
function notifyAdminNewUser($conn, $user_id, $username, $role) {
    $title = "New User Registration";
    $message = "A new {$role} has registered: {$username}. Please review their account.";
    return createAdminNotification($conn, $title, $message, $user_id, 'user');
}

/**
 * Notify admins about new job posting
 * 
 * @param PDO $conn - Database connection
 * @param int $job_id - ID of the new job
 * @param string $job_title - Title of the job
 * @param string $company_name - Name of the company
 * @return bool
 */
function notifyAdminNewJob($conn, $job_id, $job_title, $company_name) {
    $title = "New Job Posted";
    $message = "A new job '{$job_title}' has been posted by {$company_name}.";
    return createAdminNotification($conn, $title, $message, $job_id, 'job');
}

/**
 * Notify admins about new job application
 * 
 * @param PDO $conn - Database connection
 * @param int $application_id - ID of the application
 * @param string $job_title - Title of the job
 * @param string $applicant_name - Name of the applicant
 * @return bool
 */
function notifyAdminNewApplication($conn, $application_id, $job_title, $applicant_name) {
    $title = "New Job Application";
    $message = "{$applicant_name} has applied for the position '{$job_title}'.";
    return createAdminNotification($conn, $title, $message, $application_id, 'application');
}

/**
 * Notify admins about company profile updates
 * 
 * @param PDO $conn - Database connection
 * @param int $company_id - ID of the company
 * @param string $company_name - Name of the company
 * @return bool
 */
function notifyAdminCompanyUpdate($conn, $company_id, $company_name) {
    $title = "Company Profile Updated";
    $message = "{$company_name} has updated their company profile. Please review the changes.";
    return createAdminNotification($conn, $title, $message, $company_id, 'company');
}

/**
 * Notify admins about job fair event registrations
 * 
 * @param PDO $conn - Database connection
 * @param int $event_id - ID of the job fair event
 * @param string $event_title - Title of the event
 * @param int $registration_count - Number of new registrations
 * @return bool
 */
function notifyAdminEventRegistration($conn, $event_id, $event_title, $registration_count) {
    $title = "New Event Registration";
    $message = "{$registration_count} new registration(s) for the job fair event '{$event_title}'.";
    return createAdminNotification($conn, $title, $message, $event_id, 'event');
}

/**
 * Notify admins about account approval actions
 * 
 * @param PDO $conn - Database connection
 * @param int $user_id - ID of the user being approved/rejected
 * @param string $username - Username of the user
 * @param string $role - Role of the user
 * @param string $action - Action taken ('approved' or 'rejected')
 * @param string $admin_username - Username of the admin taking action
 * @return bool
 */
function notifyAdminAccountApproval($conn, $user_id, $username, $role, $action, $admin_username) {
    $title = "Account " . ucfirst($action);
    $message = "Admin '{$admin_username}' has {$action} the {$role} account for '{$username}'.";
    return createAdminNotification($conn, $title, $message, $user_id, 'account_approval');
}

/**
 * Notify admins about job approval actions
 * 
 * @param PDO $conn - Database connection
 * @param int $job_id - ID of the job being approved/rejected
 * @param string $job_title - Title of the job
 * @param string $company_name - Name of the company
 * @param string $action - Action taken ('approved' or 'rejected')
 * @param string $admin_username - Username of the admin taking action
 * @return bool
 */
function notifyAdminJobApproval($conn, $job_id, $job_title, $company_name, $action, $admin_username) {
    $title = "Job " . ucfirst($action);
    $message = "Admin '{$admin_username}' has {$action} the job posting '{$job_title}' from {$company_name}.";
    return createAdminNotification($conn, $title, $message, $job_id, 'job_approval');
}

/**
 * Notify admins about pending account approvals
 * 
 * @param PDO $conn - Database connection
 * @param int $user_id - ID of the user awaiting approval
 * @param string $username - Username of the user
 * @param string $role - Role of the user
 * @param string $email - Email of the user
 * @return bool
 */
function notifyAdminPendingAccountApproval($conn, $user_id, $username, $role, $email) {
    $title = "Account Pending Approval";
    $message = "New {$role} account '{$username}' ({$email}) is waiting for approval.";
    return createAdminNotification($conn, $title, $message, $user_id, 'pending_account');
}

/**
 * Notify admins about pending job approvals
 * 
 * @param PDO $conn - Database connection
 * @param int $job_id - ID of the job awaiting approval
 * @param string $job_title - Title of the job
 * @param string $company_name - Name of the company
 * @return bool
 */
function notifyAdminPendingJobApproval($conn, $job_id, $job_title, $company_name) {
    $title = "Job Pending Approval";
    $message = "New job posting '{$job_title}' from {$company_name} is waiting for approval.";
    return createAdminNotification($conn, $title, $message, $job_id, 'pending_job');
}

/**
 * Notify admins about system errors or important events
 * 
 * @param PDO $conn - Database connection
 * @param string $title - Title of the notification
 * @param string $message - Detailed message
 * @param string $priority - Priority level (low, medium, high)
 * @return bool
 */
function notifyAdminSystemAlert($conn, $title, $message, $priority = 'medium') {
    $formatted_title = "[SYSTEM] " . $title;
    $formatted_message = "[Priority: " . strtoupper($priority) . "] " . $message;
    return createAdminNotification($conn, $formatted_title, $formatted_message, null, 'system');
}

/**
 * Get notification statistics for admin dashboard
 * 
 * @param PDO $conn - Database connection
 * @param int $admin_id - Admin user ID
 * @return array - Array containing notification statistics
 */
function getAdminNotificationStats($conn, $admin_id) {
    try {
        $stats = [];
        
        // Total notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $stmt->execute([$admin_id]);
        $stats['total'] = $stmt->fetchColumn();
        
        // Unread notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$admin_id]);
        $stats['unread'] = $stmt->fetchColumn();
        
        // Today's notifications
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$admin_id]);
        $stats['today'] = $stmt->fetchColumn();
        
        // This week's notifications
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$admin_id]);
        $stats['this_week'] = $stmt->fetchColumn();
        
        // Notifications by type (last 30 days)
        $stmt = $conn->prepare("
            SELECT related_type, COUNT(*) as count
            FROM notifications 
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY related_type
        ");
        $stmt->execute([$admin_id]);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting admin notification stats: " . $e->getMessage());
        return [
            'total' => 0,
            'unread' => 0,
            'today' => 0,
            'this_week' => 0,
            'by_type' => []
        ];
    }
}

/**
 * Clean up old notifications (older than specified days)
 * 
 * @param PDO $conn - Database connection
 * @param int $days - Number of days to keep notifications (default: 90)
 * @return int - Number of notifications deleted
 */
function cleanupOldNotifications($conn, $days = 90) {
    try {
        $stmt = $conn->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND is_read = 1
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Error cleaning up old notifications: " . $e->getMessage());
        return 0;
    }
}
?> 