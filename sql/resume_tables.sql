-- Resume management tables

-- Main resume table
CREATE TABLE IF NOT EXISTS `resumes` (
  `resume_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `template_id` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resume_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `resume_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resume sections table
CREATE TABLE IF NOT EXISTS `resume_sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `resume_id` int(11) NOT NULL,
  `section_type` enum('personal', 'summary', 'education', 'experience', 'skills', 'certifications', 'projects', 'languages', 'custom') NOT NULL,
  `section_title` varchar(255) DEFAULT NULL,
  `section_order` int(11) DEFAULT 0,
  `content` text DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`section_id`),
  KEY `resume_id` (`resume_id`),
  CONSTRAINT `section_resume_fk` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`resume_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resume templates table
CREATE TABLE IF NOT EXISTS `resume_templates` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `preview_image` varchar(255) DEFAULT NULL,
  `css_file` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default templates
INSERT INTO `resume_templates` (`name`, `description`, `preview_image`, `css_file`, `is_active`) VALUES
('Professional', 'A clean and professional resume template suitable for most industries', 'professional.jpg', 'professional.css', 1),
('Modern', 'A modern and creative resume template with a slight flair', 'modern.jpg', 'modern.css', 1),
('Classic', 'A traditional resume template with a timeless design', 'classic.jpg', 'classic.css', 1);

-- Resume education items table (for specific education entries)
CREATE TABLE IF NOT EXISTS `resume_education` (
  `education_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `institution` varchar(255) NOT NULL,
  `degree` varchar(255) DEFAULT NULL,
  `field_of_study` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  PRIMARY KEY (`education_id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `education_section_fk` FOREIGN KEY (`section_id`) REFERENCES `resume_sections` (`section_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resume experience items table (for specific work experience entries)
CREATE TABLE IF NOT EXISTS `resume_experience` (
  `experience_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  PRIMARY KEY (`experience_id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `experience_section_fk` FOREIGN KEY (`section_id`) REFERENCES `resume_sections` (`section_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resume skill items table (for specific skill entries)
CREATE TABLE IF NOT EXISTS `resume_skills` (
  `skill_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `skill_name` varchar(255) NOT NULL,
  `proficiency_level` enum('beginner', 'intermediate', 'advanced', 'expert') DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  PRIMARY KEY (`skill_id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `skill_section_fk` FOREIGN KEY (`section_id`) REFERENCES `resume_sections` (`section_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 