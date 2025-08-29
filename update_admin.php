<?php
require_once 'config/database.php';

try {
    // Update admin password with proper hashing
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin' AND role = 'admin'");
    $stmt->execute([$hashed_password]);
    
    if ($stmt->rowCount() > 0) {
        echo "Admin password updated successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        echo "No admin user found to update.\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 