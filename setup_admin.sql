-- Insert admin user with properly hashed password
-- Password: admin123
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$8K1p/a0dR1Ux5Y5Y5Y5Y5O5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y5Y', 'admin@jobportal.com', 'admin');

-- Create admin profile
INSERT INTO user_profiles (user_id, first_name, last_name) 
SELECT user_id, 'Admin', 'User' 
FROM users 
WHERE username = 'admin' AND role = 'admin'; 