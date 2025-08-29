-- Update profile_image column to profile_picture if it exists
ALTER TABLE user_profiles
CHANGE COLUMN IF EXISTS profile_image profile_picture VARCHAR(255) DEFAULT NULL;

-- Add profile_picture column if it doesn't exist
ALTER TABLE user_profiles
ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL; 