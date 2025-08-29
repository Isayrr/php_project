-- Update notifications table for job fair event notifications
-- Run this script to ensure your notifications table supports the new event notification features

-- Check if notifications table exists, if not create it
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT NULL,
    related_type VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Add related_id column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'notifications' 
    AND COLUMN_NAME = 'related_id'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE notifications ADD COLUMN related_id INT NULL AFTER message', 
    'SELECT "related_id column already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add related_type column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'notifications' 
    AND COLUMN_NAME = 'related_type'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE notifications ADD COLUMN related_type VARCHAR(50) NULL AFTER related_id', 
    'SELECT "related_type column already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on related_type for better performance
CREATE INDEX IF NOT EXISTS idx_notifications_related_type ON notifications(related_type);
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);

-- Create logs directory table for tracking script executions (optional)
CREATE TABLE IF NOT EXISTS system_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    log_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert a log entry to track this update
INSERT INTO system_logs (log_type, message) 
VALUES ('database_update', 'Updated notifications table for job fair event notifications support');

-- Show current structure of notifications table
SELECT 'Current notifications table structure:' as message;
DESCRIBE notifications; 