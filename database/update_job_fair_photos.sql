-- Update job fair events and registrations for photo upload support
-- Run this script to add photo upload capabilities to job fair events

-- Add event photo column to job_fair_events table
ALTER TABLE job_fair_events 
ADD COLUMN event_photo VARCHAR(255) NULL AFTER event_description;

-- Add registration photo column to event_registrations table
ALTER TABLE event_registrations 
ADD COLUMN registration_photo VARCHAR(255) NULL AFTER notes;

-- Create index for better performance on photo queries
CREATE INDEX IF NOT EXISTS idx_job_fair_events_photo ON job_fair_events(event_photo);
CREATE INDEX IF NOT EXISTS idx_event_registrations_photo ON event_registrations(registration_photo);

-- Create upload directories if they don't exist (this is for reference - actual directories created by PHP)
-- uploads/job_fair_events/
-- uploads/event_registrations/

-- Insert a log entry to track this update
INSERT INTO system_logs (log_type, message) 
VALUES ('database_update', 'Added photo upload support to job fair events and registrations')
ON DUPLICATE KEY UPDATE message = VALUES(message);

-- Show updated structure
SELECT 'Updated job_fair_events table structure:' as message;
DESCRIBE job_fair_events;

SELECT 'Updated event_registrations table structure:' as message;
DESCRIBE event_registrations; 