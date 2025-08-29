<?php
session_start();
require_once '../config/database.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Check if user has uploaded a resume and file exists
    $stmt = $conn->prepare("SELECT resume FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $resume = $stmt->fetchColumn();
    
    $has_resume = !empty($resume) && file_exists('../' . $resume);
    
    echo json_encode([
        'has_resume' => $has_resume,
        'resume_path' => $resume
    ]);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 