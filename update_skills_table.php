<?php
try {
    require_once 'config/database.php';
    
    // Check if the skills table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'skills'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create skills table if it doesn't exist
        $conn->exec("CREATE TABLE skills (
            skill_id INT AUTO_INCREMENT PRIMARY KEY,
            skill_name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            priority INT DEFAULT 1
        )");
        echo "Created skills table.<br>";
    } else {
        // Check if description column exists
        $stmt = $conn->prepare("SHOW COLUMNS FROM skills LIKE 'description'");
        $stmt->execute();
        $descriptionExists = $stmt->rowCount() > 0;
        
        if (!$descriptionExists) {
            $conn->exec("ALTER TABLE skills ADD COLUMN description TEXT AFTER skill_name");
            echo "Added description column to skills table.<br>";
        } else {
            echo "Description column already exists.<br>";
        }
        
        // Check if priority column exists
        $stmt = $conn->prepare("SHOW COLUMNS FROM skills LIKE 'priority'");
        $stmt->execute();
        $priorityExists = $stmt->rowCount() > 0;
        
        if (!$priorityExists) {
            $conn->exec("ALTER TABLE skills ADD COLUMN priority INT DEFAULT 1");
            echo "Added priority column to skills table.<br>";
        } else {
            echo "Priority column already exists.<br>";
        }
    }
    
    // Check if jobseeker_skills table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'jobseeker_skills'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create jobseeker_skills table if it doesn't exist
        $conn->exec("CREATE TABLE jobseeker_skills (
            jobseeker_id INT NOT NULL,
            skill_id INT NOT NULL,
            proficiency_level ENUM('beginner', 'intermediate', 'expert'),
            PRIMARY KEY (jobseeker_id, skill_id)
        )");
        echo "Created jobseeker_skills table.<br>";
    }
    
    echo "Skills table structure updated successfully!";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 