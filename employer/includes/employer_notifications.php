<?php
/**
 * Employer Notification Helper Functions
 * 
 * This file contains helper functions for creating employer-specific notifications
 * in the job portal system.
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Create a notification for a specific employer
 */
function createEmployerNotification($conn, $employer_id, $title, $message, $related_type = null, $related_id = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_type, related_id, created_at, is_read)
            VALUES (?, ?, ?, ?, ?, NOW(), 0)
        ");
        
        return $stmt->execute([$employer_id, $title, $message, $related_type, $related_id]);
    } catch (Exception $e) {
        error_log("Error creating employer notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer when a job seeker applies to their job
 */
function notifyEmployerNewApplication($conn, $job_id) {
    try {
        // Get job and employer details
        $stmt = $conn->prepare("
            SELECT j.title, j.company_id, c.employer_id, c.company_name 
            FROM jobs j 
            JOIN companies c ON j.company_id = c.company_id 
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return false;
        }
        
        $title = "New Job Application Received";
        $message = "A new application has been submitted for the position '{$job['title']}' at {$job['company_name']}.";
        
        return createEmployerNotification(
            $conn, 
            $job['employer_id'], 
            $title, 
            $message, 
            'application', 
            $job_id
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of new application: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer when their job posting is approved by admin
 */
function notifyEmployerJobApproved($conn, $job_id) {
    try {
        // Get job details
        $stmt = $conn->prepare("
            SELECT j.title, j.company_id, c.employer_id, c.company_name 
            FROM jobs j 
            JOIN companies c ON j.company_id = c.company_id 
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return false;
        }
        
        $title = "Job Posting Approved";
        $message = "Your job posting '{$job['title']}' has been approved and is now live on the job portal.";
        
        return createEmployerNotification(
            $conn, 
            $job['employer_id'], 
            $title, 
            $message, 
            'job', 
            $job_id
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of job approval: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer when their job posting is rejected by admin
 */
function notifyEmployerJobRejected($conn, $job_id, $reason = '') {
    try {
        // Get job details
        $stmt = $conn->prepare("
            SELECT j.title, j.company_id, c.employer_id, c.company_name 
            FROM jobs j 
            JOIN companies c ON j.company_id = c.company_id 
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return false;
        }
        
        $title = "Job Posting Rejected";
        $message = "Your job posting '{$job['title']}' has been rejected.";
        if (!empty($reason)) {
            $message .= " Reason: " . $reason;
        }
        $message .= " Please review and resubmit if necessary.";
        
        return createEmployerNotification(
            $conn, 
            $job['employer_id'], 
            $title, 
            $message, 
            'job', 
            $job_id
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of job rejection: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer when their company profile is approved
 */
function notifyEmployerProfileApproved($conn, $company_id) {
    try {
        // Get company details
        $stmt = $conn->prepare("
            SELECT employer_id, company_name 
            FROM companies 
            WHERE company_id = ?
        ");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$company) {
            return false;
        }
        
        $title = "Company Profile Approved";
        $message = "Your company profile for '{$company['company_name']}' has been approved. You can now start posting jobs.";
        
        return createEmployerNotification(
            $conn, 
            $company['employer_id'], 
            $title, 
            $message, 
            'company', 
            $company_id
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of profile approval: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer about job fair event opportunities
 */
function notifyEmployerJobFairEvent($conn, $employer_id, $event_id, $event_name, $event_date) {
    try {
        $title = "Job Fair Event Opportunity";
        $message = "A new job fair event '{$event_name}' is scheduled for " . date('M j, Y', strtotime($event_date)) . ". Register your company to participate and meet potential candidates.";
        
        return createEmployerNotification(
            $conn, 
            $employer_id, 
            $title, 
            $message, 
            'event', 
            $event_id
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of job fair event: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer when job fair registration is confirmed
 */
function notifyEmployerEventRegistrationConfirmed($conn, $employer_id, $event_id, $event_name) {
    try {
        $title = "Job Fair Registration Confirmed";
        $message = "Your registration for the job fair event '{$event_name}' has been confirmed. You will receive further details about the event closer to the date.";
        
        return createEmployerNotification(
            $conn, 
            $employer_id, 
            $title, 
            $message, 
            'event', 
            $event_id
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of event registration confirmation: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer about expiring job postings
 */
function notifyEmployerJobExpiring($conn, $job_id, $days_until_expiry = 7) {
    try {
        // Get job details
        $stmt = $conn->prepare("
            SELECT j.title, j.company_id, c.employer_id, c.company_name, j.application_deadline
            FROM jobs j 
            JOIN companies c ON j.company_id = c.company_id 
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return false;
        }
        
        $title = "Job Posting Expiring Soon";
        $message = "Your job posting '{$job['title']}' will expire in {$days_until_expiry} days. The application deadline is " . date('M j, Y', strtotime($job['application_deadline'])) . ". Consider renewing or extending the posting if needed.";
        
        return createEmployerNotification(
            $conn, 
            $job['employer_id'], 
            $title, 
            $message, 
            'job', 
            $job_id
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of expiring job: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify employer with system updates or announcements
 */
function notifyEmployerSystemUpdate($conn, $employer_id, $title, $message, $priority = 'normal') {
    try {
        $notification_title = "[System Update] " . $title;
        
        return createEmployerNotification(
            $conn, 
            $employer_id, 
            $notification_title, 
            $message, 
            'system', 
            null
        );
    } catch (Exception $e) {
        error_log("Error notifying employer of system update: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all employers about a system-wide announcement
 */
function notifyAllEmployersAnnouncement($conn, $title, $message) {
    try {
        // Get all employer IDs
        $stmt = $conn->prepare("
            SELECT employer_id 
            FROM companies 
            WHERE employer_id IS NOT NULL
        ");
        $stmt->execute();
        $employers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $success_count = 0;
        foreach ($employers as $employer_id) {
            if (notifyEmployerSystemUpdate($conn, $employer_id, $title, $message)) {
                $success_count++;
            }
        }
        
        return $success_count;
    } catch (Exception $e) {
        error_log("Error notifying all employers: " . $e->getMessage());
        return false;
    }
}

/**
 * Get employer notification statistics
 */
function getEmployerNotificationStats($conn, $employer_id) {
    try {
        $stats = [];
        
        // Total notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $stmt->execute([$employer_id]);
        $stats['total'] = $stmt->fetchColumn();
        
        // Unread notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$employer_id]);
        $stats['unread'] = $stmt->fetchColumn();
        
        // Today's notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$employer_id]);
        $stats['today'] = $stmt->fetchColumn();
        
        // This week's notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute([$employer_id]);
        $stats['this_week'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting employer notification stats: " . $e->getMessage());
        return [
            'total' => 0,
            'unread' => 0,
            'today' => 0,
            'this_week' => 0
        ];
    }
}

/**
 * Clean up old read notifications for employers (older than 30 days)
 */
function cleanupOldEmployerNotifications($conn, $days_old = 30) {
    try {
        $stmt = $conn->prepare("
            DELETE FROM notifications 
            WHERE is_read = 1 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND user_id IN (SELECT employer_id FROM companies WHERE employer_id IS NOT NULL)
        ");
        
        $stmt->execute([$days_old]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Error cleaning up old employer notifications: " . $e->getMessage());
        return false;
    }
}
?> 
