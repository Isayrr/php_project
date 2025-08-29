-- Skills table with priority
CREATE TABLE skills (
    skill_id INT PRIMARY KEY AUTO_INCREMENT,
    skill_name VARCHAR(50) UNIQUE NOT NULL,
    priority INT DEFAULT 1 -- Priority weight (1-10, higher = more important)
);

-- Job seekers skills
CREATE TABLE jobseeker_skills (
    jobseeker_id INT,
    skill_id INT,
    proficiency_level ENUM('beginner', 'intermediate', 'expert') DEFAULT 'intermediate',
    FOREIGN KEY (jobseeker_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    PRIMARY KEY (jobseeker_id, skill_id)
);

-- Job skills (required skills for a job)
CREATE TABLE job_skills (
    job_id INT,
    skill_id INT,
    required_level ENUM('beginner', 'intermediate', 'expert') DEFAULT 'intermediate',
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    PRIMARY KEY (job_id, skill_id)
);

-- Skill matches (stores the match data when a job seeker applies for a job)
CREATE TABLE skill_matches (
    application_id INT PRIMARY KEY,
    match_score DECIMAL(5,2) NOT NULL, -- Percentage match
    matching_skills TEXT, -- Comma-separated list of matching skills
    missing_skills TEXT, -- Comma-separated list of missing skills
    FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE
);

-- Notifications table for job matches and other alerts
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT, -- ID of related entity (e.g., job_id for job match)
    related_type VARCHAR(50), -- Type of related entity (e.g., 'job')
    created_at DATETIME NOT NULL,
    is_read TINYINT(1) DEFAULT 0, -- 0 = unread, 1 = read
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Add last job match check to jobseekers table
ALTER TABLE jobseekers ADD COLUMN last_job_match_check DATETIME NULL; 