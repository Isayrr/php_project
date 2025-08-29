<?php
require_once 'config/database.php';

try {
    echo "<h2>Jobs Table Structure</h2>";
    $stmt = $conn->prepare("DESCRIBE jobs");
    $stmt->execute();
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    echo "<h2>Sample Job Data</h2>";
    $stmt = $conn->prepare("SELECT * FROM jobs LIMIT 3");
    $stmt->execute();
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 