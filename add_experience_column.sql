-- Add experience column to user_profiles table
ALTER TABLE user_profiles
ADD COLUMN experience VARCHAR(50) NULL AFTER address; 