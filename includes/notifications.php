<?php
/**
 * Notifications Utility Functions
 */

/**
 * Send a job match notification to a jobseeker
 * 
 * @param int $jobseeker_id - The user ID of the job seeker
 * @param int $job_id - The ID of the matching job
 * @param float $match_score - The match score percentage
 * @param string $matching_skills - Comma-separated list of matching skills
 * @return bool - Whether the notification was created
 */
function sendJobMatchNotification($conn, $jobseeker_id, $job_id, $match_score, $matching_skills) {
    try {
        // Get job details
        $stmt = $conn->prepare("
            SELECT j.title as job_title, c.company_name 
            FROM jobs j
            JOIN companies c ON j.company_id = c.company_id
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return false;
        }
        
        // Create message
        $title = "New Job Match: {$job['job_title']}";
        $message = "Your skills match a new job posting from {$job['company_name']}. " . 
                  "Match score: " . round($match_score) . "%. " .
                  "Matching skills: $matching_skills";
        
        // Insert notification
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read)
            VALUES (?, ?, ?, ?, 'job', NOW(), 0)
        ");
        $result = $stmt->execute([$jobseeker_id, $title, $message, $job_id]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Error sending job match notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for new job matches for a user and send notifications
 * 
 * @param int $jobseeker_id - The user ID of the job seeker
 * @return array - Array of job IDs for which notifications were sent
 */
function checkNewJobMatches($conn, $jobseeker_id) {
    try {
        $matched_jobs = [];
        
        // Get the last job check timestamp
        $stmt = $conn->prepare("
            SELECT last_job_match_check FROM jobseekers WHERE user_id = ?
        ");
        $stmt->execute([$jobseeker_id]);
        $last_check = $stmt->fetch(PDO::FETCH_COLUMN);
        
        if (!$last_check) {
            $last_check = date('Y-m-d H:i:s', strtotime('-7 days')); // Default to 7 days ago
        }
        
        // Find new jobs that match the user's skills
        $stmt = $conn->prepare("
            SELECT 
                j.job_id,
                j.title,
                COUNT(DISTINCT js.skill_id) as matching_skills_count,
                COUNT(DISTINCT jsk.skill_id) as total_skills_required,
                GROUP_CONCAT(DISTINCT s.skill_name) as matching_skills_list
            FROM jobs j
            JOIN job_skills jsk ON j.job_id = jsk.job_id
            JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id
            JOIN skills s ON js.skill_id = s.skill_id
            LEFT JOIN notifications n ON j.job_id = n.related_id AND n.user_id = ? AND n.related_type = 'job'
            WHERE 
                j.status = 'active' AND
                j.posted_date > ? AND
                js.jobseeker_id = ? AND
                n.notification_id IS NULL
            GROUP BY j.job_id
            HAVING matching_skills_count > 0
            ORDER BY matching_skills_count DESC
        ");
        $stmt->execute([$jobseeker_id, $last_check, $jobseeker_id]);
        $matching_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Send notifications for each match
        foreach ($matching_jobs as $job) {
            $match_score = ($job['matching_skills_count'] / $job['total_skills_required']) * 100;
            
            // Only notify if match score is 50% or higher
            if ($match_score >= 50) {
                $result = sendJobMatchNotification(
                    $conn, 
                    $jobseeker_id, 
                    $job['job_id'], 
                    $match_score, 
                    $job['matching_skills_list']
                );
                
                if ($result) {
                    $matched_jobs[] = $job['job_id'];
                }
            }
        }
        
        // Update the last check timestamp
        $stmt = $conn->prepare("
            UPDATE jobseekers SET last_job_match_check = NOW() WHERE user_id = ?
        ");
        $stmt->execute([$jobseeker_id]);
        
        return $matched_jobs;
    } catch (Exception $e) {
        error_log("Error checking new job matches: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notifications for a user
 * 
 * @param int $user_id - The user ID
 * @return array - Array of notification objects
 */
function getUnreadNotifications($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting unread notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark a notification as read
 * 
 * @param int $notification_id - The notification ID
 * @param int $user_id - The user ID (for security check)
 * @return bool - Whether the operation was successful
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications SET is_read = 1
            WHERE notification_id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Create SQL table for notifications if it doesn't exist
 */
function createNotificationsTable($conn) {
    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                related_id INT,
                related_type VARCHAR(50),
                created_at DATETIME NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )
        ";
        $conn->exec($sql);
        
        $sql = "
            ALTER TABLE jobseekers ADD COLUMN IF NOT EXISTS 
            last_job_match_check DATETIME NULL
        ";
        $conn->exec($sql);
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating notifications table: " . $e->getMessage());
        return false;
    }
} 