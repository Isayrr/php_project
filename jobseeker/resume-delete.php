<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

// Check if resume ID is provided
if (!isset($_POST['resume_id']) || !is_numeric($_POST['resume_id'])) {
    $_SESSION['error_message'] = "Invalid resume ID.";
    header("Location: resume-maker.php");
    exit();
}

$resume_id = $_POST['resume_id'];

try {
    // Check if the resume belongs to the user
    $stmt = $conn->prepare("SELECT resume_id FROM resumes WHERE resume_id = ? AND user_id = ?");
    $stmt->execute([$resume_id, $_SESSION['user_id']]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resume) {
        $_SESSION['error_message'] = "Resume not found or you don't have permission to delete it.";
        header("Location: resume-maker.php");
        exit();
    }
    
    // Delete all resume sections first (due to foreign key constraints)
    $stmt = $conn->prepare("DELETE FROM resume_sections WHERE resume_id = ?");
    $stmt->execute([$resume_id]);
    
    // Now delete the resume
    $stmt = $conn->prepare("DELETE FROM resumes WHERE resume_id = ?");
    $stmt->execute([$resume_id]);
    
    $_SESSION['success_message'] = "Resume deleted successfully.";
    
} catch(Exception $e) {
    $_SESSION['error_message'] = "Error deleting resume: " . $e->getMessage();
}

header("Location: resume-maker.php");
exit();