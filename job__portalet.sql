-- Create database
CREATE DATABASE IF NOT EXISTS job_portal;
USE job_portal;

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'employer', 'jobseeker') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Admin credentials
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$8K1p/a0dR1Ux5Y5Y5Y5Y5O5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y', 'admin@jobportal.com', 'admin');

-- User profiles table
CREATE TABLE user_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    address TEXT,
    profile_picture VARCHAR(255),
    bio TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Skills table
CREATE TABLE skills (
    skill_id INT PRIMARY KEY AUTO_INCREMENT,
    skill_name VARCHAR(50) UNIQUE NOT NULL
);

-- Job seekers skills
CREATE TABLE jobseeker_skills (
    jobseeker_id INT,
    skill_id INT,
    proficiency_level ENUM('beginner', 'intermediate', 'expert'),
    FOREIGN KEY (jobseeker_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    PRIMARY KEY (jobseeker_id, skill_id)
);

-- Companies table
CREATE TABLE companies (
    company_id INT PRIMARY KEY AUTO_INCREMENT,
    employer_id INT,
    company_name VARCHAR(100) NOT NULL,
    industry VARCHAR(50),
    company_size VARCHAR(50),
    company_description TEXT,
    company_website VARCHAR(255),
    company_logo VARCHAR(255),
    FOREIGN KEY (employer_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Jobs table
CREATE TABLE jobs (
    job_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    requirements TEXT,
    salary_range VARCHAR(50),
    job_type ENUM('full-time', 'part-time', 'contract', 'internship'),
    location VARCHAR(100),
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deadline_date DATE,
    status ENUM('active', 'closed') DEFAULT 'active',
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
);

-- Job skills requirements
CREATE TABLE job_skills (
    job_id INT,
    skill_id INT,
    required_level ENUM('beginner', 'intermediate', 'expert'),
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    PRIMARY KEY (job_id, skill_id)
);

-- Applications table
CREATE TABLE applications (
    application_id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT,
    jobseeker_id INT,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'reviewed', 'shortlisted', 'rejected', 'hired') DEFAULT 'pending',
    cover_letter TEXT,
    resume_path VARCHAR(255),
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (jobseeker_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Skill matching scores
CREATE TABLE skill_matches (
    application_id INT,
    match_score DECIMAL(5,2),
    matching_skills TEXT,
    missing_skills TEXT,
    FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
    PRIMARY KEY (application_id)
);

-- Notifications table
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(100),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
); 