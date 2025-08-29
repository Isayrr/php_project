<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if user_id is provided
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit();
}

$user_id = $_GET['user_id'];

try {
    // Get basic user information - include all available profile columns
    $stmt = $conn->prepare("
        SELECT u.*, up.first_name, up.last_name, up.phone, up.address, up.experience, 
               up.profile_picture, up.bio, up.resume, up.cover_letter
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    // Set profile image
    if (!empty($user['profile_picture'])) {
        $user['profile_image'] = '../' . $user['profile_picture'];
    } else {
        $user['profile_image'] = null;
    }

    // Get company information if user is an employer
    if ($user['role'] === 'employer') {
        try {
            $stmt = $conn->prepare("
                SELECT 
                    c.company_id,
                    c.company_name,
                    c.industry,
                    c.company_size,
                    c.company_description,
                    c.company_website,
                    c.company_logo,
                    CASE 
                        WHEN c.company_logo IS NOT NULL AND c.company_logo != ''
                        THEN CONCAT('../uploads/company_logos/', c.company_logo)
                        ELSE NULL
                    END as logo_url
                FROM companies c
                WHERE c.employer_id = ?
            ");
            $stmt->execute([$user_id]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($company) {
                $user = array_merge($user, $company);
                
                // Get job count safely
                try {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM jobs j WHERE j.company_id = ?");
                    $stmt->execute([$company['company_id']]);
                    $user['total_jobs'] = $stmt->fetchColumn();
                } catch(PDOException $e) {
                    $user['total_jobs'] = 0;
                }
                
                // Get application count safely
                try {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) FROM applications a 
                        JOIN jobs j ON a.job_id = j.job_id 
                        WHERE j.company_id = ?
                    ");
                    $stmt->execute([$company['company_id']]);
                    $user['total_applications'] = $stmt->fetchColumn();
                } catch(PDOException $e) {
                    $user['total_applications'] = 0;
                }

                // Get recent job postings safely
                try {
                    $stmt = $conn->prepare("
                        SELECT j.*, 
                               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count
                        FROM jobs j
                        WHERE j.company_id = ?
                        ORDER BY j.created_at DESC
                        LIMIT 5
                    ");
                    $stmt->execute([$company['company_id']]);
                    $user['job_postings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(PDOException $e) {
                    $user['job_postings'] = [];
                }
            } else {
                // Set default values if no company found
                $user['company_name'] = null;
                $user['industry'] = null;
                $user['company_size'] = null;
                $user['company_description'] = null;
                $user['company_website'] = null;
                $user['company_logo'] = null;
                $user['logo_url'] = null;
                $user['total_jobs'] = 0;
                $user['total_applications'] = 0;
                $user['job_postings'] = [];
            }
        } catch(PDOException $e) {
            error_log("Error fetching company data: " . $e->getMessage());
            // Set default values on error
            $user['company_name'] = null;
            $user['industry'] = null;
            $user['company_size'] = null;
            $user['company_description'] = null;
            $user['company_website'] = null;
            $user['company_logo'] = null;
            $user['logo_url'] = null;
            $user['total_jobs'] = 0;
            $user['total_applications'] = 0;
            $user['job_postings'] = [];
        }
    }

    // Get applications information if user is a jobseeker
    if ($user['role'] === 'jobseeker') {
        try {
            // Get recent applications safely
            $stmt = $conn->prepare("
                SELECT a.*, j.title as job_title, c.company_name
                FROM applications a
                JOIN jobs j ON a.job_id = j.job_id
                JOIN companies c ON j.company_id = c.company_id
                WHERE a.jobseeker_id = ?
                ORDER BY a.application_date DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $user['applications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching applications: " . $e->getMessage());
            $user['applications'] = [];
        }
    }

    // Get notifications if table exists
    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $user['recent_notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get notification counts
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_notifications,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_notifications
            FROM notifications
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $notification_counts = $stmt->fetch(PDO::FETCH_ASSOC);
        $user['total_notifications'] = $notification_counts['total_notifications'];
        $user['unread_notifications'] = $notification_counts['unread_notifications'];
    } catch(PDOException $e) {
        error_log("Notifications table not found or error: " . $e->getMessage());
        $user['recent_notifications'] = [];
        $user['total_notifications'] = 0;
        $user['unread_notifications'] = 0;
    }

    // Send response
    header('Content-Type: application/json');
    echo json_encode($user);

} catch(PDOException $e) {
    error_log("Error in get_user_details.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("User ID: " . $user_id);
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred', 
        'details' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit();
} catch(Exception $e) {
    error_log("General error in get_user_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An error occurred',
        'details' => $e->getMessage()
    ]);
    exit();
}
?>