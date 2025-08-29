<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: resume-maker.php");
    exit();
}

// Check if resume ID is provided
if (!isset($_POST['resume_id']) || !is_numeric($_POST['resume_id'])) {
    $_SESSION['error_message'] = "Invalid resume ID.";
    header("Location: resume-maker.php");
    exit();
}

$resume_id = $_POST['resume_id'];
$section_title = trim($_POST['section_title'] ?? '');
$section_type = $_POST['section_type'] ?? 'custom';

// Validate input
if (empty($section_title)) {
    $_SESSION['error_message'] = "Section title is required.";
    header("Location: resume-editor.php?id=" . $resume_id);
    exit();
}

try {
    // Check if the resume belongs to the user
    $stmt = $conn->prepare("SELECT resume_id FROM resumes WHERE resume_id = ? AND user_id = ?");
    $stmt->execute([$resume_id, $_SESSION['user_id']]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resume) {
        $_SESSION['error_message'] = "Resume not found or you don't have permission to edit it.";
        header("Location: resume-maker.php");
        exit();
    }
    
    // Get highest current order index
    $stmt = $conn->prepare("SELECT MAX(section_order) as max_order FROM resume_sections WHERE resume_id = ?");
    $stmt->execute([$resume_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_order = ($result['max_order'] ?? 0) + 1;
    
    // Add the new section
    $stmt = $conn->prepare("INSERT INTO resume_sections (resume_id, section_type, section_title, section_order) 
                           VALUES (?, ?, ?, ?)");
    $stmt->execute([$resume_id, $section_type, $section_title, $new_order]);
    
    $_SESSION['success_message'] = "New section '{$section_title}' added successfully.";
    
} catch(Exception $e) {
    $_SESSION['error_message'] = "Error adding section: " . $e->getMessage();
}

header("Location: resume-editor.php?id=" . $resume_id);
exit();

