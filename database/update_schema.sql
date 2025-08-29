-- Add industry column to jobs table if it doesn't exist
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS industry VARCHAR(100) DEFAULT NULL AFTER job_type;

-- Update industry value from company profile for existing jobs
UPDATE jobs j 
JOIN companies c ON j.company_id = c.company_id
SET j.industry = c.industry
WHERE j.industry IS NULL AND c.industry IS NOT NULL; 