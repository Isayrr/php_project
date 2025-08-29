<?php
/**
 * Event Reminder Cron Job Script
 * 
 * This script should be run daily via cron job to automatically send event reminders
 * to registered employers.
 * 
 * To set up cron job (Linux/macOS):
 * Run: crontab -e
 * Add: 0 9 * * * /usr/bin/php /path/to/your/project/includes/event_reminder_cron.php
 * (This runs daily at 9 AM)
 * 
 * For Windows Task Scheduler:
 * Create a new task that runs daily and executes:
 * php.exe "C:\path\to\your\project\includes\event_reminder_cron.php"
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !defined('ALLOW_CRON_WEB_ACCESS')) {
    http_response_code(403);
    die('This script can only be run from command line or as a cron job.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/event_notifications.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    
    // Log to file if possible
    $log_file = __DIR__ . '/../logs/event_reminders.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from CLI
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

try {
    logMessage("Starting event reminder cron job...");
    
    // Get all upcoming events that need reminders
    $stmt = $conn->prepare("
        SELECT event_id, event_name, event_date, start_time, location
        FROM job_fair_events 
        WHERE status = 'upcoming' 
        AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY event_date ASC
    ");
    $stmt->execute();
    $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($upcoming_events) . " upcoming events to check for reminders.");
    
    $total_reminders_sent = 0;
    
    foreach ($upcoming_events as $event) {
        $event_date = new DateTime($event['event_date']);
        $current_date = new DateTime();
        $days_until_event = $current_date->diff($event_date)->days;
        
        logMessage("Processing event: {$event['event_name']} - {$days_until_event} days away");
        
        // Send reminders at different intervals
        $reminder_sent = false;
        
        // 3-day reminder
        if ($days_until_event == 3) {
            $count = sendEventReminder($conn, $event['event_id'], 3);
            if ($count > 0) {
                logMessage("Sent 3-day reminder for '{$event['event_name']}' to $count employers");
                $total_reminders_sent += $count;
                $reminder_sent = true;
            }
        }
        
        // 1-day reminder
        if ($days_until_event == 1) {
            $count = sendEventReminder($conn, $event['event_id'], 1);
            if ($count > 0) {
                logMessage("Sent 1-day reminder for '{$event['event_name']}' to $count employers");
                $total_reminders_sent += $count;
                $reminder_sent = true;
            }
        }
        
        // Same day reminder (morning of the event)
        if ($days_until_event == 0) {
            $count = sendEventReminder($conn, $event['event_id'], 0);
            if ($count > 0) {
                logMessage("Sent same-day reminder for '{$event['event_name']}' to $count employers");
                $total_reminders_sent += $count;
                $reminder_sent = true;
            }
        }
        
        if (!$reminder_sent) {
            logMessage("No reminders needed for '{$event['event_name']}' at this time");
        }
    }
    
    // Check for events that have passed their deadline and update status
    $stmt = $conn->prepare("
        UPDATE job_fair_events 
        SET status = 'completed' 
        WHERE status = 'upcoming' 
        AND event_date < CURDATE()
    ");
    $stmt->execute();
    $completed_events = $stmt->rowCount();
    
    if ($completed_events > 0) {
        logMessage("Marked $completed_events past events as completed");
    }
    
    // Summary
    logMessage("Event reminder cron job completed successfully.");
    logMessage("Total reminders sent: $total_reminders_sent");
    logMessage("Events marked as completed: $completed_events");
    
    // Clean up old notifications (optional - keep only last 30 days)
    $stmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND related_type IN ('event_full', 'spot_available', 'event_reminder')
    ");
    $stmt->execute();
    $cleaned_notifications = $stmt->rowCount();
    
    if ($cleaned_notifications > 0) {
        logMessage("Cleaned up $cleaned_notifications old event notifications");
    }
    
} catch (Exception $e) {
    $error_message = "Error in event reminder cron job: " . $e->getMessage();
    logMessage($error_message);
    
    // Send error notification to admin (optional)
    try {
        // Get admin users
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, related_type, created_at, is_read)
                VALUES (?, ?, ?, 'system_error', NOW(), 0)
            ");
            $stmt->execute([
                $admin['user_id'],
                'Event Reminder Cron Job Error',
                "The automated event reminder system encountered an error: " . $e->getMessage()
            ]);
        }
    } catch (Exception $notification_error) {
        logMessage("Failed to send error notification: " . $notification_error->getMessage());
    }
    
    // Exit with error code
    exit(1);
}

// Exit successfully
exit(0);
?> 