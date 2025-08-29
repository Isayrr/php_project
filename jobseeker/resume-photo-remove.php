<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Remove the X-Requested-With validation for the same reasons as in resume-photo-upload.php
// if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
//     http_response_code(400);
//     echo json_encode(['error' => 'Invalid request']);
//     exit();
// }

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Check if resume ID is provided
if (!isset($data['resume_id']) || !is_numeric($data['resume_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid resume ID']);
    exit();
}

$resume_id = $data['resume_id'];
$response = [];

try {
    // Check if the resume belongs to the user
    $stmt = $conn->prepare("SELECT resume_id, photo FROM resumes WHERE resume_id = ? AND user_id = ?");
    $stmt->execute([$resume_id, $_SESSION['user_id']]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resume) {
        throw new Exception("Resume not found or you don't have permission to update it.");
    }
    
    if (empty($resume['photo'])) {
        throw new Exception("No photo to remove.");
    }
    
    // Delete the physical file if it exists
    $photo_path = '../uploads/' . $resume['photo'];
    if (file_exists($photo_path)) {
        if (!unlink($photo_path)) {
            throw new Exception("Failed to delete the photo file. Check file permissions.");
        }
    }
    
    // Update resume to remove photo reference
    $stmt = $conn->prepare("UPDATE resumes SET photo = NULL WHERE resume_id = ?");
    $stmt->execute([$resume_id]);
    
    $response = [
        'success' => true,
        'message' => 'Photo removed successfully'
    ];
    
} catch(Exception $e) {
    http_response_code(400);
    $response = ['error' => $e->getMessage()];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>