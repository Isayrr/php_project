<?php
/**
 * User Notification Functions
 * 
 * These functions handle notifications to users about their account status,
 * job applications, and other important updates.
 */

/**
 * Notify user when their account is approved by admin
 */
function notifyUserAccountApproved($conn, $user_id, $username, $role) {
    try {
        $title = "Account Approved - Welcome!";
        $message = "Congratulations! Your " . ucfirst($role) . " account has been approved by our administrators. You can now access all features of the job portal. Welcome aboard!";
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, 'user_approval', ?, 0, NOW())
        ");
        
        $result = $stmt->execute([$user_id, $title, $message, $user_id]);
        
        // Also log the approval for public notification display
        if ($result) {
            error_log("Account approved for user: $username (ID: $user_id). Notification created.");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error creating user approval notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify user when their account is rejected by admin
 */
function notifyUserAccountRejected($conn, $user_id, $username, $role, $reason = null) {
    try {
        $title = "Account Application Update";
        $message = "Unfortunately, your " . ucfirst($role) . " account application has been reviewed and could not be approved at this time.";
        
        if ($reason) {
            $message .= " Reason: " . $reason;
        }
        
        $message .= " If you have questions or would like to reapply, please contact our support team.";
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, 'user_rejection', ?, 0, NOW())
        ");
        return $stmt->execute([$user_id, $title, $message, $user_id]);
    } catch (PDOException $e) {
        error_log("Error creating user rejection notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify user about important account updates
 */
function notifyUserAccountUpdate($conn, $user_id, $title, $message) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, 'account_update', ?, 0, NOW())
        ");
        return $stmt->execute([$user_id, $title, $message, $user_id]);
    } catch (PDOException $e) {
        error_log("Error creating user account update notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify user when a job they applied for has been updated
 */
function notifyUserJobUpdate($conn, $user_id, $job_id, $job_title, $status_change) {
    try {
        $title_map = [
            'approved' => "Job Application Approved",
            'rejected' => "Job Application Update", 
            'under_review' => "Application Under Review",
            'interview_scheduled' => "Interview Scheduled",
            'hired' => "Congratulations - You're Hired!"
        ];
        
        $message_map = [
            'approved' => "Great news! Your application for '{$job_title}' has been approved and is moving forward in the hiring process.",
            'rejected' => "Thank you for your interest in '{$job_title}'. While your application was not selected this time, we encourage you to apply for other positions.",
            'under_review' => "Your application for '{$job_title}' is currently under review by the hiring team.",
            'interview_scheduled' => "Your application for '{$job_title}' has progressed! An interview has been scheduled. Check your email for details.",
            'hired' => "Congratulations! You have been selected for the position '{$job_title}'. Welcome to the team!"
        ];
        
        $title = $title_map[$status_change] ?? "Job Application Update";
        $message = $message_map[$status_change] ?? "There has been an update to your job application for '{$job_title}'.";
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, 'job_application', ?, 0, NOW())
        ");
        return $stmt->execute([$user_id, $title, $message, $job_id]);
    } catch (PDOException $e) {
        error_log("Error creating job update notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify user of system-wide announcements
 */
function notifyUserAnnouncement($conn, $user_id, $title, $message, $announcement_id = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, 'announcement', ?, 0, NOW())
        ");
        return $stmt->execute([$user_id, $title, $message, $announcement_id]);
    } catch (PDOException $e) {
        error_log("Error creating announcement notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's unread notifications count
 */
function getUserUnreadNotificationCount($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting unread notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get user's recent notifications
 */
function getUserNotifications($conn, $user_id, $limit = 10, $unread_only = false) {
    try {
        $where_clause = "WHERE user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $where_clause .= " AND is_read = 0";
        }
        
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            {$where_clause}
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark user notification as read
 */
function markUserNotificationAsRead($conn, $notification_id, $user_id) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE notification_id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all user notifications as read
 */
function markAllUserNotificationsAsRead($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = ? AND is_read = 0
        ");
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete old notifications (cleanup function)
 */
function deleteOldUserNotifications($conn, $days_old = 30) {
    try {
        $stmt = $conn->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_read = 1
        ");
        return $stmt->execute([$days_old]);
    } catch (PDOException $e) {
        error_log("Error deleting old notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification (if email system is configured)
 */
function sendEmailNotification($user_email, $subject, $message) {
    // This function can be implemented when email system is set up
    // For now, it's a placeholder
    try {
        // TODO: Implement email sending using PHPMailer or similar
        // require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        // ... email implementation
        
        error_log("Email notification would be sent to: {$user_email}, Subject: {$subject}");
        return true;
    } catch (Exception $e) {
        error_log("Error sending email notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admins about a new user registration
 */
function notifyAdminsNewUserRegistration($conn, $new_user_id, $username, $role) {
    try {
        $title = "New User Registration Pending";
        $message = "A new " . ucfirst($role) . " account ('{$username}') has been registered and is awaiting approval.";
        
        // Get all admin users
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $success_count = 0;
        foreach ($admins as $admin) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, related_type, related_id, is_read, created_at) 
                VALUES (?, ?, ?, 'pending_user_approval', ?, 0, NOW())
            ");
            if ($stmt->execute([$admin['user_id'], $title, $message, $new_user_id])) {
                $success_count++;
            }
        }
        
        return $success_count > 0;
    } catch (PDOException $e) {
        error_log("Error notifying admins about new user registration: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a notification URL for displaying on the main page
 */
function generateUserNotificationUrl($username, $notification_type, $base_url = null) {
    if (!$base_url) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        $base_url = $protocol . '://' . $host . $path;
        
        // Clean up the path to get to the root
        $base_url = rtrim(str_replace('/admin', '', $base_url), '/');
    }
    
    $params = [
        'user_notification' => urlencode($notification_type),
        'username' => urlencode($username)
    ];
    
    return $base_url . '/index.php?' . http_build_query($params);
}

/**
 * Log user notification event for debugging
 */
function logUserNotificationEvent($username, $event_type, $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] User Notification: $event_type for user '$username'";
    
    if ($details) {
        $log_message .= " - $details";
    }
    
    error_log($log_message);
}

/**
 * Check if user has pending notifications that should be displayed publicly
 */
function hasPublicNotificationPending($conn, $username) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM notifications n
            JOIN users u ON n.user_id = u.user_id
            WHERE u.username = ? 
            AND n.related_type IN ('user_approval', 'user_rejection')
            AND n.is_read = 0
        ");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking pending notifications: " . $e->getMessage());
        return false;
    }
}
?> 