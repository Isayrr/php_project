<?php
require_once 'config/database.php';

try {
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = 'admin' AND role = 'admin'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "Admin user already exists.\n";
        exit;
    }
    
    // Create admin user with properly hashed password
    $username = 'admin';
    $password = 'admin123';
    $email = 'admin@jobportal.com';
    $role = 'admin';
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert admin user
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hashed_password, $email, $role]);
    
    // Get the admin user ID
    $admin_id = $conn->lastInsertId();
    
    // Create admin profile
    $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)");
    $stmt->execute([$admin_id, 'Admin', 'User']);
    
    echo "Admin user created successfully!\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 