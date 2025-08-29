<?php
session_start();
require_once '../config/database.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if section ID is provided
if (!isset($_POST['section_id']) || !is_numeric($_POST['section_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid section ID']);
    exit();
}

$section_id = $_POST['section_id'];
$action = $_POST['action'] ?? 'get_section_data';

try {
    // Check if the section belongs to the user
    $stmt = $conn->prepare("SELECT s.*, r.user_id 
                           FROM resume_sections s 
                           JOIN resumes r ON s.resume_id = r.resume_id 
                           WHERE s.section_id = ? AND r.user_id = ?");
    $stmt->execute([$section_id, $_SESSION['user_id']]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        echo json_encode(['success' => false, 'error' => 'Section not found or you don\'t have permission to access it']);
        exit();
    }
    
    // Prepare response data
    $response = [
        'success' => true,
        'section_id' => $section_id,
        'section_type' => $section['section_type'],
        'section_title' => $section['section_title'],
        'content' => $section['content'],
        'metadata' => !empty($section['metadata']) ? json_decode($section['metadata'], true) : null
    ];
    
    // Get additional data based on section type
    switch ($section['section_type']) {
        case 'education':
            // Get education entries
            $stmt = $conn->prepare("SELECT * FROM resume_education WHERE section_id = ? ORDER BY order_index ASC");
            $stmt->execute([$section_id]);
            $education_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format dates for better display
            foreach ($education_entries as &$entry) {
                // Format dates as needed
                if (!empty($entry['start_date'])) {
                    $entry['start_year'] = date('Y', strtotime($entry['start_date']));
                }
                
                if (!empty($entry['end_date'])) {
                    $entry['end_year'] = date('Y', strtotime($entry['end_date']));
                }
            }
            
            $response['education'] = $education_entries;
            break;
            
        case 'experience':
            // Get experience entries
            $stmt = $conn->prepare("SELECT * FROM resume_experience WHERE section_id = ? ORDER BY order_index ASC");
            $stmt->execute([$section_id]);
            $response['experience'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'skills':
            // Get skill entries
            $stmt = $conn->prepare("SELECT * FROM resume_skills WHERE section_id = ? ORDER BY order_index ASC");
            $stmt->execute([$section_id]);
            $response['skills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
    
    echo json_encode($response);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 