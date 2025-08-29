<?php
/**
 * Jobseeker Notification Helper Functions
 * Functions for creating and managing jobseeker notifications
 */

require_once __DIR__ . '/../../includes/notifications.php';

/**
 * Create a notification for a specific jobseeker
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param int $related_id - Optional related entity ID
 * @param string $related_type - Optional related entity type
 * @return bool - Whether the notification was created successfully
 */
function createJobseekerNotification($conn, $jobseeker_id, $title, $message, $related_id = null, $related_type = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read)
            VALUES (?, ?, ?, ?, ?, NOW(), 0)
        ");
        return $stmt->execute([$jobseeker_id, $title, $message, $related_id, $related_type]);
    } catch (Exception $e) {
        error_log("Error creating jobseeker notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify jobseeker about application status update
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param int $application_id - Application ID
 * @param string $job_title - Job title
 * @param string $company_name - Company name
 * @param string $status - New application status
 * @return bool
 */
function notifyJobseekerApplicationUpdate($conn, $jobseeker_id, $application_id, $job_title, $company_name, $status) {
    $status_messages = [
        'reviewed' => 'Your application has been reviewed',
        'shortlisted' => 'Congratulations! You have been shortlisted',
        'rejected' => 'Your application was not selected this time',
        'hired' => 'Congratulations! You have been hired',
        'interview_scheduled' => 'An interview has been scheduled'
    ];
    
    $title = $status_messages[$status] ?? "Application Status Updated";
    $message = "{$title} for the position '{$job_title}' at {$company_name}.";
    
    if ($status === 'hired') {
        $message .= " Welcome to the team!";
    } elseif ($status === 'shortlisted') {
        $message .= " Please check your email for next steps.";
    } elseif ($status === 'rejected') {
        $message .= " Keep applying - the right opportunity is waiting!";
    }
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, $application_id, 'application');
}

/**
 * Notify jobseeker about new job matches
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param int $job_id - Job ID
 * @param string $job_title - Job title
 * @param string $company_name - Company name
 * @param float $match_score - Match percentage
 * @param string $matching_skills - Matching skills
 * @return bool
 */
function notifyJobseekerNewMatch($conn, $jobseeker_id, $job_id, $job_title, $company_name, $match_score, $matching_skills) {
    $title = "🎯 New Job Match Found!";
    $message = "'{$job_title}' at {$company_name} matches your skills " . round($match_score) . "%. " .
               "Matching skills: {$matching_skills}. Apply now!";
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, $job_id, 'job_match');
}

/**
 * Notify jobseeker about profile completion
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param array $missing_fields - Array of missing profile fields
 * @return bool
 */
function notifyJobseekerProfileCompletion($conn, $jobseeker_id, $missing_fields) {
    $title = "📝 Complete Your Profile";
    $message = "Your profile is incomplete. Missing: " . implode(', ', $missing_fields) . 
               ". Complete your profile to get better job matches!";
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, null, 'profile');
}

/**
 * Notify jobseeker about resume upload requirement
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @return bool
 */
function notifyJobseekerResumeUpload($conn, $jobseeker_id) {
    $title = "📄 Upload Your Resume";
    $message = "Upload your resume to start applying for jobs and get better recommendations!";
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, null, 'resume');
}

/**
 * Notify jobseeker about job fair events
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param int $event_id - Event ID
 * @param string $event_title - Event title
 * @param string $event_date - Event date
 * @param string $location - Event location
 * @return bool
 */
function notifyJobseekerJobFairEvent($conn, $jobseeker_id, $event_id, $event_title, $event_date, $location) {
    $title = "🎪 New Job Fair Event";
    $message = "Join the job fair '{$event_title}' on " . date('M d, Y', strtotime($event_date)) . 
               " at {$location}. Don't miss this opportunity!";
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, $event_id, 'job_fair');
}

/**
 * Notify jobseeker about skill recommendations
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param array $recommended_skills - Array of recommended skill names
 * @return bool
 */
function notifyJobseekerSkillRecommendations($conn, $jobseeker_id, $recommended_skills) {
    $title = "💡 Skill Recommendations";
    $message = "Based on job market trends, consider adding these skills: " . 
               implode(', ', $recommended_skills) . ". This will improve your job matches!";
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, null, 'skills');
}

/**
 * Notify jobseeker about new jobs in their field
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param int $job_count - Number of new jobs
 * @param string $category - Job category
 * @return bool
 */
function notifyJobseekerNewJobsInField($conn, $jobseeker_id, $job_count, $category) {
    $title = "🚀 New Jobs Available";
    $message = "{$job_count} new job" . ($job_count > 1 ? 's' : '') . 
               " posted in {$category}. Check them out now!";
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, null, 'new_jobs');
}

/**
 * Notify jobseeker about deadline reminders
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @param int $job_id - Job ID
 * @param string $job_title - Job title
 * @param string $company_name - Company name
 * @param string $deadline - Application deadline
 * @return bool
 */
function notifyJobseekerDeadlineReminder($conn, $jobseeker_id, $job_id, $job_title, $company_name, $deadline) {
    $days_left = ceil((strtotime($deadline) - time()) / (60 * 60 * 24));
    
    $title = "⏰ Application Deadline Reminder";
    $message = "Only {$days_left} day" . ($days_left > 1 ? 's' : '') . 
               " left to apply for '{$job_title}' at {$company_name}. Apply now!";
    
    return createJobseekerNotification($conn, $jobseeker_id, $title, $message, $job_id, 'deadline');
}

/**
 * Notify all jobseekers about system announcements
 * 
 * @param PDO $conn - Database connection
 * @param string $title - Announcement title
 * @param string $message - Announcement message
 * @return int - Number of notifications sent
 */
function notifyAllJobseekers($conn, $title, $message) {
    try {
        // Get all active jobseekers
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'jobseeker' AND status = 'active'");
        $stmt->execute();
        $jobseekers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $success_count = 0;
        foreach ($jobseekers as $jobseeker_id) {
            if (createJobseekerNotification($conn, $jobseeker_id, $title, $message, null, 'announcement')) {
                $success_count++;
            }
        }
        
        return $success_count;
    } catch (Exception $e) {
        error_log("Error notifying all jobseekers: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get notification statistics for jobseeker dashboard
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @return array - Array containing notification statistics
 */
function getJobseekerNotificationStats($conn, $jobseeker_id) {
    try {
        $stats = [];
        
        // Total notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $stmt->execute([$jobseeker_id]);
        $stats['total'] = $stmt->fetchColumn();
        
        // Unread notifications
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$jobseeker_id]);
        $stats['unread'] = $stmt->fetchColumn();
        
        // Today's notifications
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$jobseeker_id]);
        $stats['today'] = $stmt->fetchColumn();
        
        // Notifications by type (last 30 days)
        $stmt = $conn->prepare("
            SELECT related_type, COUNT(*) as count
            FROM notifications 
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY related_type
        ");
        $stmt->execute([$jobseeker_id]);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting jobseeker notification stats: " . $e->getMessage());
        return [
            'total' => 0,
            'unread' => 0,
            'today' => 0,
            'by_type' => []
        ];
    }
}

/**
 * Check and create automatic notifications for jobseeker
 * 
 * @param PDO $conn - Database connection
 * @param int $jobseeker_id - Jobseeker user ID
 * @return array - Array of created notification types
 */
function checkAndCreateAutomaticNotifications($conn, $jobseeker_id) {
    $created_notifications = [];
    
    try {
        // Check if profile is incomplete
        $stmt = $conn->prepare("
            SELECT first_name, last_name, phone, address, bio, resume, profile_picture 
            FROM user_profiles WHERE user_id = ?
        ");
        $stmt->execute([$jobseeker_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $missing_fields = [];
        if (empty($profile['first_name'])) $missing_fields[] = 'First Name';
        if (empty($profile['last_name'])) $missing_fields[] = 'Last Name';
        if (empty($profile['phone'])) $missing_fields[] = 'Phone';
        if (empty($profile['address'])) $missing_fields[] = 'Location';
        if (empty($profile['bio'])) $missing_fields[] = 'Bio';
        if (empty($profile['profile_picture'])) $missing_fields[] = 'Profile Picture';
        
        // Notify about missing profile fields (only if there are missing fields and no recent notification)
        if (!empty($missing_fields)) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND related_type = 'profile' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$jobseeker_id]);
            $recent_profile_notifications = $stmt->fetchColumn();
            
            if ($recent_profile_notifications == 0) {
                notifyJobseekerProfileCompletion($conn, $jobseeker_id, $missing_fields);
                $created_notifications[] = 'profile_completion';
            }
        }
        
        // Check if resume is missing
        if (empty($profile['resume'])) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND related_type = 'resume' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$jobseeker_id]);
            $recent_resume_notifications = $stmt->fetchColumn();
            
            if ($recent_resume_notifications == 0) {
                notifyJobseekerResumeUpload($conn, $jobseeker_id);
                $created_notifications[] = 'resume_upload';
            }
        }
        
        // Check for upcoming job fair events
        $stmt = $conn->prepare("
            SELECT event_id, title, event_date, location 
            FROM job_fair_events 
            WHERE status = 'active' 
            AND event_date >= CURDATE() 
            AND event_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        ");
        $stmt->execute();
        $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($upcoming_events as $event) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND related_id = ? AND related_type = 'job_fair'
            ");
            $stmt->execute([$jobseeker_id, $event['event_id']]);
            $existing_notification = $stmt->fetchColumn();
            
            if ($existing_notification == 0) {
                notifyJobseekerJobFairEvent(
                    $conn, 
                    $jobseeker_id, 
                    $event['event_id'], 
                    $event['title'], 
                    $event['event_date'], 
                    $event['location']
                );
                $created_notifications[] = 'job_fair';
            }
        }
        
        return $created_notifications;
    } catch (Exception $e) {
        error_log("Error checking automatic notifications: " . $e->getMessage());
        return [];
    }
}
?> 