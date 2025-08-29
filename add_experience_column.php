<?php
try {
    // Include database connection
    require_once 'config/database.php';
    
    // Check if the column already exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM user_profiles LIKE 'experience'");
    $stmt->execute();
    $experienceExists = $stmt->rowCount() > 0;
    
    if ($experienceExists) {
        echo "Experience column already exists.<br>";
    } else {
        // Add the experience column
        $conn->exec("ALTER TABLE user_profiles ADD COLUMN experience VARCHAR(50) NULL AFTER address");
        echo "Added experience column to user_profiles table.<br>";
    }
    
    echo "Database update completed successfully!";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 