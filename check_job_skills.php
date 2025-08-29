<?php
require_once 'config/database.php';

try {
    echo "<h2>Job Skills Table Structure</h2>";
    $stmt = $conn->prepare("SHOW TABLES LIKE 'job_skills'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("DESCRIBE job_skills");
        $stmt->execute();
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
    } else {
        echo "<p>job_skills table does not exist</p>";
    }
    
    echo "<h2>Skills Table Structure</h2>";
    $stmt = $conn->prepare("SHOW TABLES LIKE 'skills'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("DESCRIBE skills");
        $stmt->execute();
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
    } else {
        echo "<p>skills table does not exist</p>";
    }
    
    echo "<h2>Sample Job Skills Data</h2>";
    $stmt = $conn->prepare("SHOW TABLES LIKE 'job_skills'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("SELECT * FROM job_skills LIMIT 5");
        $stmt->execute();
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 