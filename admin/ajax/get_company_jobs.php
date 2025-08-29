<?php
// AJAX helper to get jobs for a specific company
require_once '../../config/database.php';

// Set content type to plain text
header('Content-Type: text/html');

// Check if company_id is provided
if (!isset($_GET['company_id']) || empty($_GET['company_id'])) {
    echo '<option value="">All Jobs</option>';
    exit;
}

$company_id = $_GET['company_id'];

try {
    // Get jobs for the selected company
    $stmt = $conn->prepare("SELECT job_id, title FROM jobs WHERE company_id = ? ORDER BY title");
    $stmt->execute([$company_id]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return options
    echo '<option value="">All Jobs</option>';
    foreach ($jobs as $job) {
        echo '<option value="' . $job['job_id'] . '">' . htmlspecialchars($job['title']) . '</option>';
    }
} catch(PDOException $e) {
    // Return error message
    echo '<option value="">Error loading jobs</option>';
}
?> 