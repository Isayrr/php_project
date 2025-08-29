<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get the last check time from query parameter
    $last_check = isset($_GET['last_check']) ? (int)$_GET['last_check'] : 0;
    $last_check_date = date('Y-m-d H:i:s', $last_check / 1000); // Convert from milliseconds
    
    // Query to check for recent resume updates
    $query = "SELECT 
                a.application_id,
                a.jobseeker_id,
                up.first_name,
                up.last_name,
                up.resume,
                UNIX_TIMESTAMP(up.updated_at) * 1000 as last_modified,
                j.title as job_title
              FROM applications a
              JOIN user_profiles up ON a.jobseeker_id = up.user_id
              JOIN jobs j ON a.job_id = j.job_id
              WHERE up.updated_at > ? 
                AND up.resume IS NOT NULL 
                AND up.resume != ''
              ORDER BY up.updated_at DESC
              LIMIT 50";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$last_check_date]);
    $updated_resumes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get count of applications with resumes
    $count_query = "SELECT COUNT(*) as total_with_resumes
                    FROM applications a
                    JOIN user_profiles up ON a.jobseeker_id = up.user_id
                    WHERE up.resume IS NOT NULL AND up.resume != ''";
    
    $stmt = $conn->prepare($count_query);
    $stmt->execute();
    $resume_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'updated_resumes' => $updated_resumes,
        'total_with_resumes' => $resume_stats['total_with_resumes'],
        'last_check' => time() * 1000, // Current timestamp in milliseconds
        'update_count' => count($updated_resumes)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?> 