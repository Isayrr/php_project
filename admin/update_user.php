<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get POST data
$user_id = $_POST['user_id'] ?? null;
$username = $_POST['username'] ?? null;
$email = $_POST['email'] ?? null;
$role = $_POST['role'] ?? null;
$status = $_POST['status'] ?? null;
$approval_status = $_POST['approval_status'] ?? null;
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$phone = $_POST['phone'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Validate required fields
if (!$user_id || !$username || !$email || !$role || !$status || !$approval_status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit();
}

try {
    $conn->beginTransaction();

    // Check if username or email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $stmt->execute([$username, $email, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit();
    }

    // Update user table
    $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, approval_status = ? WHERE user_id = ?";
    $params = [$username, $email, $role, $status, $approval_status, $user_id];

    // If new password is provided, update it
    if (!empty($new_password)) {
        $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, approval_status = ?, password = ? WHERE user_id = ?";
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $params = [$username, $email, $role, $status, $approval_status, $hashed_password, $user_id];
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // Handle profile picture removal
    $remove_profile_picture = isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] === '1';
    
    // Handle profile picture upload if provided
    $profile_picture_path = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed.']);
            exit();
        }

        if ($_FILES['profile_picture']['size'] > $max_size) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 5MB.']);
            exit();
        }

        $upload_dir = '../uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $profile_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $profile_filename;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture']);
            exit();
        }

        $profile_picture_path = 'uploads/profile_pictures/' . $profile_filename;

        // Get current profile picture to delete old one
        $stmt = $conn->prepare("SELECT profile_picture FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_profile && $current_profile['profile_picture']) {
            $old_picture_path = '../' . $current_profile['profile_picture'];
            if (file_exists($old_picture_path)) {
                unlink($old_picture_path);
            }
        }
    } elseif ($remove_profile_picture) {
        // Remove current profile picture
        $stmt = $conn->prepare("SELECT profile_picture FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_profile && $current_profile['profile_picture']) {
            $old_picture_path = '../' . $current_profile['profile_picture'];
            if (file_exists($old_picture_path)) {
                unlink($old_picture_path);
            }
        }
        
        // Set to null to remove from database
        $profile_picture_path = 'REMOVE';
    }

    // Update user_profiles table
    if ($profile_picture_path === 'REMOVE') {
        // Remove profile picture from database
        $stmt = $conn->prepare("
            INSERT INTO user_profiles (user_id, first_name, last_name, phone, profile_picture)
            VALUES (?, ?, ?, ?, NULL)
            ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            profile_picture = NULL
        ");
        $stmt->execute([$user_id, $first_name, $last_name, $phone]);
    } elseif ($profile_picture_path) {
        // Update with new profile picture
        $stmt = $conn->prepare("
            INSERT INTO user_profiles (user_id, first_name, last_name, phone, profile_picture)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            profile_picture = VALUES(profile_picture)
        ");
        $stmt->execute([$user_id, $first_name, $last_name, $phone, $profile_picture_path]);
    } else {
        // Update without changing profile picture
    $stmt = $conn->prepare("
        INSERT INTO user_profiles (user_id, first_name, last_name, phone)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name),
        last_name = VALUES(last_name),
        phone = VALUES(phone)
    ");
    $stmt->execute([$user_id, $first_name, $last_name, $phone]);
    }

    // If user is an employer, update company information
    if ($role === 'employer') {
        $company_name = $_POST['company_name'] ?? '';
        $industry = $_POST['industry'] ?? '';
        $company_website = $_POST['company_website'] ?? '';
        $company_description = $_POST['company_description'] ?? '';
        $company_size = $_POST['company_size'] ?? '';

        // Validate required company fields
        if (empty($company_name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Company name is required']);
            exit();
        }

        // Handle company logo upload if provided
        $logo_url = null;
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($_FILES['company_logo']['type'], $allowed_types)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed.']);
                exit();
            }

            if ($_FILES['company_logo']['size'] > $max_size) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 2MB.']);
                exit();
            }

            $upload_dir = '../uploads/company_logos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
            $logo_filename = 'company_logo_' . $user_id . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $logo_filename;

            if (!move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_path)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to upload company logo']);
                exit();
            }
        }

        // Check if company exists
        $stmt = $conn->prepare("SELECT company_id, company_logo FROM companies WHERE employer_id = ?");
        $stmt->execute([$user_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($company) {
            // Update existing company
            $sql = "UPDATE companies SET 
                company_name = ?,
                industry = ?,
                company_size = ?,
                company_website = ?,
                company_description = ?";
            
            $params = [
                $company_name,
                $industry,
                $company_size,
                $company_website,
                $company_description
            ];

            // Add company_logo to update if new logo was uploaded
            if (isset($logo_filename)) {
                $sql .= ", company_logo = ?";
                $params[] = $logo_filename;

                // Delete old logo if it exists
                if ($company['company_logo']) {
                    $old_logo_path = '../uploads/company_logos/' . $company['company_logo'];
                    if (file_exists($old_logo_path)) {
                        unlink($old_logo_path);
                    }
                }
            }

            $sql .= " WHERE employer_id = ?";
            $params[] = $user_id;

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        } else {
            // Create new company
            $stmt = $conn->prepare("
                INSERT INTO companies (
                    employer_id, company_name, industry, company_size, 
                    company_website, company_description, company_logo
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $company_name,
                $industry,
                $company_size,
                $company_website,
                $company_description,
                $logo_filename ?? null
            ]);
        }
    }

    // Create notification for the user (if notifications table exists)
    try {
    $notification_title = "Account Updated";
    $notification_message = "Your account information has been updated by an administrator.";
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $notification_title, $notification_message]);
    } catch(PDOException $e) {
        // Ignore notification errors - table might not exist
        error_log("Notification creation failed: " . $e->getMessage());
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);

} catch(PDOException $e) {
    $conn->rollBack();
    error_log("Error in update_user.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    exit();
}
?> 