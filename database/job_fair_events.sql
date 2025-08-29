-- Job Fair Events Database Schema

-- Job Fair Events table
CREATE TABLE job_fair_events (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(255) NOT NULL,
    event_description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    max_employers INT DEFAULT 50,
    registration_deadline DATE NOT NULL,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Event Registrations table (for employers joining events)
CREATE TABLE event_registrations (
    registration_id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    employer_id INT NOT NULL,
    company_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'confirmed', 'cancelled') DEFAULT 'registered',
    notes TEXT,
    FOREIGN KEY (event_id) REFERENCES job_fair_events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (employer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_employer (event_id, employer_id)
);

-- Insert some sample data
INSERT INTO job_fair_events (event_name, event_description, event_date, start_time, end_time, location, max_employers, registration_deadline, created_by) 
VALUES 
('Tech Job Fair 2024', 'Annual technology job fair featuring top tech companies and startups', '2024-03-15', '09:00:00', '17:00:00', 'Manila Convention Center', 30, '2024-03-10', 1),
('Healthcare Career Expo', 'Healthcare professionals job fair with hospitals and medical centers', '2024-04-20', '10:00:00', '16:00:00', 'SMX Convention Center', 25, '2024-04-15', 1); 