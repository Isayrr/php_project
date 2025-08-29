-- Add columns for resume, cover letter, and experience to user_profiles table
ALTER TABLE user_profiles
ADD COLUMN resume VARCHAR(255) NULL AFTER profile_picture,
ADD COLUMN cover_letter VARCHAR(255) NULL AFTER resume,
ADD COLUMN experience VARCHAR(50) NULL AFTER address; 