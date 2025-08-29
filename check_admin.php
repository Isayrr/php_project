<?php
require_once 'config/database.php';

try {
    // Check admin user details
    $stmt = $conn->prepare("SELECT user_id, username, email, role FROM users WHERE username = 'admin' AND role = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Current admin user details:\n";
        echo "User ID: " . $admin['user_id'] . "\n";
        echo "Username: " . $admin['username'] . "\n";
        echo "Email: " . $admin['email'] . "\n";
        echo "Role: " . $admin['role'] . "\n";
    } else {
        echo "No admin user found.\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 