<?php
// Script to test and verify skills functionality

try {
    require_once 'config/database.php';
    echo "<h2>Testing Skills Functionality</h2>";
    
    // Check skills table structure
    echo "<h3>Skills Table Structure:</h3>";
    $stmt = $conn->prepare("DESCRIBE skills");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check jobseeker_skills table structure
    echo "<h3>Jobseeker Skills Table Structure:</h3>";
    $stmt = $conn->prepare("DESCRIBE jobseeker_skills");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Get sample data from skills table
    echo "<h3>Sample Skills Data:</h3>";
    $stmt = $conn->prepare("SELECT * FROM skills LIMIT 10");
    $stmt->execute();
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($skills);
    echo "</pre>";
    
    echo "<p>Everything looks good! You can now visit <a href='jobseeker/skills.php'>the skills page</a> to manage your skills and see the word cloud visualization.</p>";
} catch(Exception $e) {
    echo "<h3>Error:</h3> " . $e->getMessage();
}
?> 