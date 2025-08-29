# Job Fair Event Notification System

## 📋 Overview

The Job Fair Event Notification System automatically informs employers when participant limits are reached for job fair events. This comprehensive system includes real-time notifications, email alerts, and automated reminders.

## ✨ Features

### 🚨 **Automatic Participant Limit Notifications**
- **Event Full Notifications**: When an event reaches its maximum participant capacity, all unregistered employers are automatically notified
- **Spot Available Notifications**: When someone cancels and a spot becomes available, previously notified employers are informed
- **Real-time Updates**: Notifications are sent immediately when registration status changes

### 🔔 **Smart Notification System**
- **Prevents Duplicate Notifications**: System tracks who has been notified to avoid spam
- **Contextual Messages**: Different notification types with specific messaging
- **Visual Indicators**: Different icons and colors for different notification types

### ⏰ **Automated Event Reminders**
- **3-Day Reminder**: Sent 3 days before the event
- **1-Day Reminder**: Sent 1 day before the event  
- **Same-Day Reminder**: Sent on the morning of the event
- **Automatic Status Updates**: Past events are automatically marked as completed

## 🔧 Implementation Details

### Notification Types

| Type | Description | When Triggered |
|------|-------------|----------------|
| `event_full` | Event reached maximum capacity | When registration count = max_employers |
| `spot_available` | Spot became available in full event | When someone cancels from a full event |
| `event_reminder` | Reminder for registered employers | 3 days, 1 day, and same day before event |

### Files Modified/Added

#### New Files:
- `includes/event_notifications.php` - Core notification functions
- `includes/event_reminder_cron.php` - Automated reminder script
- `database/update_notifications.sql` - Database structure updates
- `JOB_FAIR_EVENT_NOTIFICATIONS.md` - This documentation

#### Modified Files:
- `employer/job-fair-events.php` - Added notification triggers
- `employer/dashboard.php` - Added notification display

## 📖 How It Works

### 1. **Event Registration Process**
```php
// When employer registers for event
if ($_POST['action'] === 'register') {
    // ... registration logic ...
    
    // Check if event reached capacity and notify other employers
    if (checkEventCapacityAndNotify($conn, $_POST['event_id'])) {
        $notified_count = notifyEmployersEventFull($conn, $_POST['event_id']);
    }
}
```

### 2. **Event Cancellation Process**
```php
// When employer cancels registration
if ($_POST['action'] === 'cancel') {
    // ... cancellation logic ...
    
    // Check if spot became available and notify interested employers
    $notified_count = notifyEmployersSpotAvailable($conn, $_POST['event_id']);
}
```

### 3. **Notification Display**
Notifications appear on the employer dashboard with:
- **Event Full**: 🗓️ Orange warning icon
- **Spot Available**: ✅ Green check icon  
- **Event Reminder**: 🔔 Blue bell icon
- **Other**: ℹ️ Blue info icon

## 🚀 Setup Instructions

### 1. Database Setup
Run the database update script:
```sql
-- Execute the SQL script
SOURCE database/update_notifications.sql;
```

### 2. Test the System
1. Create a job fair event with a low participant limit (e.g., 2 employers)
2. Register employers until the limit is reached
3. Check that unregistered employers receive notifications
4. Cancel a registration and verify spot available notifications

### 3. Set Up Automated Reminders (Optional)

#### For Linux/macOS (Cron Job):
```bash
# Open crontab
crontab -e

# Add this line to run daily at 9 AM
0 9 * * * /usr/bin/php /path/to/your/project/includes/event_reminder_cron.php
```

#### For Windows (Task Scheduler):
1. Open Task Scheduler
2. Create new task
3. Set trigger: Daily at 9:00 AM
4. Set action: Start program
5. Program: `php.exe`
6. Arguments: `"C:\path\to\your\project\includes\event_reminder_cron.php"`

#### For Web-based Testing:
```php
// Add this line to the top of event_reminder_cron.php for testing
define('ALLOW_CRON_WEB_ACCESS', true);

// Then visit: yourdomain.com/includes/event_reminder_cron.php
```

## 📊 Notification Flow Diagram

```
Event Registration
       ↓
Check if event full?
       ↓
[YES] → Get unregistered employers → Send "Event Full" notifications
       ↓
[NO] → Continue normal flow

Event Cancellation
       ↓
Check if spot available?
       ↓
[YES] → Get previously notified employers → Send "Spot Available" notifications
       ↓
[NO] → Continue normal flow
```

## 🎯 Key Functions

### `notifyEmployersEventFull($conn, $event_id)`
- Sends notifications when event reaches capacity
- Targets unregistered employers only
- Prevents duplicate notifications

### `notifyEmployersSpotAvailable($conn, $event_id)`
- Sends notifications when spots become available
- Targets employers who received "event full" notifications
- Only notifies if they haven't registered yet

### `sendEventReminder($conn, $event_id, $days_before)`
- Sends reminders to registered employers
- Customizable timing (3 days, 1 day, same day)
- Prevents duplicate reminders on same day

### `checkEventCapacityAndNotify($conn, $event_id)`
- Checks if event has reached capacity
- Triggers notifications automatically
- Returns boolean success status

## 🔍 Monitoring and Logs

### Notification Tracking
- All notifications are stored in the `notifications` table
- Track read/unread status
- Monitor notification types and frequency

### Cron Job Logs
- Logs stored in `logs/event_reminders.log`
- Includes timestamps, event details, and notification counts
- Error logging for troubleshooting

### Database Cleanup
- Automatically removes notifications older than 30 days
- Prevents database bloat
- Maintains system performance

## ⚠️ Important Notes

1. **Database Requirements**: Ensure `notifications` table has `related_id` and `related_type` columns
2. **Performance**: System uses efficient queries to prevent duplicate notifications
3. **Scalability**: Designed to handle multiple events and hundreds of employers
4. **Error Handling**: Comprehensive error logging and admin notifications for issues

## 🐛 Troubleshooting

### Common Issues:

**Notifications not appearing:**
- Check that `includes/notifications.php` is included in dashboard
- Verify `notifications` table structure
- Check user permissions

**Duplicate notifications:**
- System should prevent duplicates automatically
- Check notification tracking logic
- Verify database constraints

**Cron job not running:**
- Check cron job syntax and file permissions
- Verify PHP path in cron command
- Check log files for errors

### Debug Mode:
Add this to test notification functions:
```php
// Test notification system
try {
    $result = notifyEmployersEventFull($conn, $event_id);
    echo "Notified $result employers";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## 📈 Future Enhancements

Potential future improvements:
- **Email Notifications**: Integrate with email system for external notifications
- **SMS Notifications**: Add mobile notifications via SMS API
- **Notification Preferences**: Allow employers to customize notification types
- **Advanced Scheduling**: More flexible reminder scheduling options
- **Analytics Dashboard**: Track notification effectiveness and engagement

---

**Version**: 1.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+ with PDO MySQL extension 