<?php
/**
 * Job Fair Event Notifications Utility Functions
 */

/**
 * Notify employers when a job fair event reaches its participant limit
 * 
 * @param PDO $conn - Database connection
 * @param int $event_id - The event ID that reached capacity
 * @return int - Number of employers notified
 */
function notifyEmployersEventFull($conn, $event_id) {
    try {
        // Get event details
        $stmt = $conn->prepare("
            SELECT event_name, event_date, location, max_employers,
                   (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = ?) as registered_count
            FROM job_fair_events 
            WHERE event_id = ?
        ");
        $stmt->execute([$event_id, $event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event || $event['registered_count'] < $event['max_employers']) {
            return 0; // Event not full or not found
        }
        
        // Get all employers who are NOT registered for this event
        $stmt = $conn->prepare("
            SELECT DISTINCT u.user_id, u.email, c.company_name
            FROM users u
            JOIN companies c ON u.user_id = c.employer_id
            WHERE u.role = 'employer' 
            AND u.user_id NOT IN (
                SELECT er.employer_id 
                FROM event_registrations er 
                WHERE er.event_id = ?
            )
            AND u.user_id NOT IN (
                SELECT n.user_id
                FROM notifications n
                WHERE n.related_id = ? 
                AND n.related_type = 'event_full'
            )
        ");
        $stmt->execute([$event_id, $event_id]);
        $unregistered_employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notified_count = 0;
        foreach ($unregistered_employers as $employer) {
            // Create notification
            $title = "Job Fair Event Full: {$event['event_name']}";
            $message = "The job fair event '{$event['event_name']}' scheduled for " . 
                      date('F d, Y', strtotime($event['event_date'])) . 
                      " at {$event['location']} has reached its maximum capacity of {$event['max_employers']} participants. " .
                      "We recommend registering early for future events to secure your spot.";
            
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, ?, ?, ?, 'event_full', NOW(), 0)
            ");
            $result = $stmt->execute([
                $employer['user_id'], 
                $title, 
                $message, 
                $event_id
            ]);
            
            if ($result) {
                $notified_count++;
            }
        }
        
        return $notified_count;
    } catch (Exception $e) {
        error_log("Error notifying employers about full event: " . $e->getMessage());
        return 0;
    }
}

/**
 * Notify employers when a spot becomes available in a full event
 * 
 * @param PDO $conn - Database connection
 * @param int $event_id - The event ID that has a spot available
 * @return int - Number of employers notified
 */
function notifyEmployersSpotAvailable($conn, $event_id) {
    try {
        // Get event details
        $stmt = $conn->prepare("
            SELECT event_name, event_date, location, max_employers,
                   (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = ?) as registered_count
            FROM job_fair_events 
            WHERE event_id = ?
        ");
        $stmt->execute([$event_id, $event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event || $event['registered_count'] >= $event['max_employers']) {
            return 0; // Event still full or not found
        }
        
        // Get employers who received the "event full" notification
        $stmt = $conn->prepare("
            SELECT DISTINCT u.user_id, u.email, c.company_name
            FROM users u
            JOIN companies c ON u.user_id = c.employer_id
            JOIN notifications n ON u.user_id = n.user_id
            WHERE u.role = 'employer' 
            AND n.related_id = ?
            AND n.related_type = 'event_full'
            AND u.user_id NOT IN (
                SELECT er.employer_id 
                FROM event_registrations er 
                WHERE er.event_id = ?
            )
            AND u.user_id NOT IN (
                SELECT n2.user_id
                FROM notifications n2
                WHERE n2.related_id = ? 
                AND n2.related_type = 'spot_available'
                AND n2.created_at > n.created_at
            )
        ");
        $stmt->execute([$event_id, $event_id, $event_id]);
        $interested_employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notified_count = 0;
        foreach ($interested_employers as $employer) {
            // Create notification
            $title = "Spot Available: {$event['event_name']}";
            $message = "Good news! A spot has become available for the job fair event '{$event['event_name']}' " .
                      "scheduled for " . date('F d, Y', strtotime($event['event_date'])) . 
                      " at {$event['location']}. Register now to secure your participation!";
            
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, ?, ?, ?, 'spot_available', NOW(), 0)
            ");
            $result = $stmt->execute([
                $employer['user_id'], 
                $title, 
                $message, 
                $event_id
            ]);
            
            if ($result) {
                $notified_count++;
            }
        }
        
        return $notified_count;
    } catch (Exception $e) {
        error_log("Error notifying employers about available spot: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if an event has reached capacity and trigger notifications
 * 
 * @param PDO $conn - Database connection
 * @param int $event_id - The event ID to check
 * @return bool - Whether notifications were sent
 */
function checkEventCapacityAndNotify($conn, $event_id) {
    try {
        // Get current registration count and max capacity
        $stmt = $conn->prepare("
            SELECT max_employers,
                   (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = ?) as registered_count
            FROM job_fair_events 
            WHERE event_id = ?
        ");
        $stmt->execute([$event_id, $event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            return false;
        }
        
        // If event just reached capacity, notify employers
        if ($event['registered_count'] >= $event['max_employers']) {
            $notified = notifyEmployersEventFull($conn, $event_id);
            return $notified > 0;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error checking event capacity: " . $e->getMessage());
        return false;
    }
}

/**
 * Send event reminder notifications to registered employers
 * 
 * @param PDO $conn - Database connection
 * @param int $event_id - The event ID
 * @param int $days_before - Number of days before event to send reminder
 * @return int - Number of employers notified
 */
function sendEventReminder($conn, $event_id, $days_before = 3) {
    try {
        // Get event details
        $stmt = $conn->prepare("
            SELECT event_name, event_date, start_time, location
            FROM job_fair_events 
            WHERE event_id = ? 
            AND event_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND status = 'upcoming'
        ");
        $stmt->execute([$event_id, $days_before]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            return 0; // Event not found or not within reminder timeframe
        }
        
        // Get registered employers who haven't received reminder yet
        $stmt = $conn->prepare("
            SELECT u.user_id, u.email, c.company_name
            FROM event_registrations er
            JOIN users u ON er.employer_id = u.user_id
            JOIN companies c ON er.company_id = c.company_id
            WHERE er.event_id = ? 
            AND er.status IN ('registered', 'confirmed')
            AND u.user_id NOT IN (
                SELECT n.user_id
                FROM notifications n
                WHERE n.related_id = ? 
                AND n.related_type = 'event_reminder'
                AND DATE(n.created_at) = CURDATE()
            )
        ");
        $stmt->execute([$event_id, $event_id]);
        $registered_employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notified_count = 0;
        foreach ($registered_employers as $employer) {
            // Create reminder notification
            $title = "Event Reminder: {$event['event_name']}";
            $message = "Reminder: You are registered for the job fair event '{$event['event_name']}' " .
                      "on " . date('F d, Y', strtotime($event['event_date'])) . 
                      " at " . date('h:i A', strtotime($event['start_time'])) . 
                      " at {$event['location']}. We look forward to seeing you there!";
            
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, ?, ?, ?, 'event_reminder', NOW(), 0)
            ");
            $result = $stmt->execute([
                $employer['user_id'], 
                $title, 
                $message, 
                $event_id
            ]);
            
            if ($result) {
                $notified_count++;
            }
        }
        
        return $notified_count;
    } catch (Exception $e) {
        error_log("Error sending event reminders: " . $e->getMessage());
        return 0;
    }
}
?> 