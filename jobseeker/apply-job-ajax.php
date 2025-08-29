<?php
session_start();
require_once '../config/database.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    echo json_encode([
        'success' => false,
        'message' => 'Please login as a jobseeker to apply.'
    ]);
    exit;
}

try {
    // Check if job_id is provided
    if (!isset($_POST['job_id'])) {
        throw new Exception('Invalid request.');
    }

    // Check if user has a resume and file exists
    $stmt = $conn->prepare("SELECT resume FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $resume = $stmt->fetchColumn();
    
    if (empty($resume) || !file_exists('../' . $resume)) {
        throw new Exception('Please upload your resume before applying to jobs.');
    }

    // Check if already applied
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND jobseeker_id = ?");
    $stmt->execute([$_POST['job_id'], $_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('You have already applied for this job.');
    }

    // Check if job exists and is active
    $stmt = $conn->prepare("SELECT job_id FROM jobs WHERE job_id = ? AND status = 'active' AND approval_status = 'approved'");
    $stmt->execute([$_POST['job_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('This job is no longer available.');
    }

    // Insert application
    $stmt = $conn->prepare("INSERT INTO applications (job_id, jobseeker_id, application_date, status) 
                           VALUES (?, ?, NOW(), 'pending')");
    $stmt->execute([
        $_POST['job_id'],
        $_SESSION['user_id']
    ]);
    $application_id = $conn->lastInsertId();

    // Fire notifications (best-effort)
    try {
        require_once __DIR__ . '/../admin/includes/admin_notifications.php';
        require_once __DIR__ . '/../employer/includes/employer_notifications.php';
        
        // Pull minimal context for messages
        $stmt = $conn->prepare("SELECT title FROM jobs WHERE job_id = ?");
        $stmt->execute([$_POST['job_id']]);
        $job_title = (string)($stmt->fetchColumn() ?: 'Job');
        
        $stmt = $conn->prepare("SELECT first_name, last_name FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        $applicant_name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
        
        notifyAdminNewApplication($conn, $application_id, $job_title, $applicant_name);
        notifyEmployerNewApplication($conn, (int)$_POST['job_id']);
    } catch (Exception $e) {
        error_log('Notification error (apply-job-ajax): ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully!'
    ]);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 