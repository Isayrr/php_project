<?php
session_start();
require_once '../config/database.php';

// For debugging only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if resume ID is provided
if (!isset($_POST['resume_id']) || !is_numeric($_POST['resume_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid resume ID']);
    exit();
}

$resume_id = $_POST['resume_id'];
$response = [];
$debug_info = [];

try {
    // Check if the resume belongs to the user
    $stmt = $conn->prepare("SELECT resume_id FROM resumes WHERE resume_id = ? AND user_id = ?");
    $stmt->execute([$resume_id, $_SESSION['user_id']]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resume) {
        throw new Exception("Resume not found or you don't have permission to update it.");
    }
    
    // Debug info
    $debug_info['resume_check'] = 'Resume belongs to user';
    $debug_info['files'] = $_FILES;
    $debug_info['post'] = $_POST;
    
    // Handle image upload
    if (!isset($_FILES['photo'])) {
        throw new Exception("No file was submitted.");
    }
    
    if ($_FILES['photo']['error'] != 0) {
        $upload_errors = [
            1 => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            2 => "The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form",
            3 => "The uploaded file was only partially uploaded",
            4 => "No file was uploaded",
            6 => "Missing a temporary folder",
            7 => "Failed to write file to disk",
            8 => "A PHP extension stopped the file upload"
        ];
        $error_message = isset($upload_errors[$_FILES['photo']['error']]) ? 
                        $upload_errors[$_FILES['photo']['error']] : 
                        "Unknown upload error";
        throw new Exception("Upload error: " . $error_message);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($_FILES['photo']['type'], $allowed_types)) {
        throw new Exception("Invalid file type. Please upload JPG, PNG or GIF images only.");
    }
    
    // Validate file size (max 2MB)
    if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
        throw new Exception("File size is too large. Maximum size is 2MB.");
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/resume_photos/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception("Failed to create upload directory. Error: " . error_get_last()['message']);
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        throw new Exception("Upload directory is not writable. Please check permissions.");
    }
    
    // Generate unique filename
    $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = 'resume_photo_' . $resume_id . '_' . time() . '.' . $extension;
    $upload_path = $upload_dir . $filename;
    
    // Debug info
    $debug_info['upload_path'] = $upload_path;
    $debug_info['tmp_name'] = $_FILES['photo']['tmp_name'];
    $debug_info['file_info'] = [
        'name' => $_FILES['photo']['name'],
        'type' => $_FILES['photo']['type'],
        'size' => $_FILES['photo']['size'],
        'tmp_name' => $_FILES['photo']['tmp_name'],
        'error' => $_FILES['photo']['error']
    ];
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
        $last_error = error_get_last();
        throw new Exception("Failed to save uploaded file. Error: " . ($last_error ? $last_error['message'] : 'Unknown error'));
    }
    
    // Update resume with photo path
    $photo_path = 'resume_photos/' . $filename;
    $stmt = $conn->prepare("UPDATE resumes SET photo = ? WHERE resume_id = ?");
    $stmt->execute([$photo_path, $resume_id]);
    
    $response = [
        'success' => true,
        'message' => 'Photo uploaded successfully',
        'photo_url' => '../uploads/' . $photo_path,
        'debug' => $debug_info
    ];
    
} catch(Exception $e) {
    http_response_code(400);
    $response = [
        'error' => $e->getMessage(),
        'debug' => $debug_info
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();

