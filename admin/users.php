<?php
session_start();
require_once '../config/database.php';
require_once 'includes/admin_notifications.php';
require_once '../includes/user_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$users = [];
$error = null;
$success = null;
$added_username = null; // Track newly added username

// Handle notification action message
if (isset($_SESSION['notification_action'])) {
    $success = $_SESSION['notification_action'];
    unset($_SESSION['notification_action']);
}

// Get highlight parameter for user highlighting
$highlight_user_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Count admin users
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admin_count = $stmt->fetchColumn();
} catch(PDOException $e) {
    $admin_count = 0;
    error_log("Error counting admin users: " . $e->getMessage());
}

// Count pending approvals
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE approval_status = 'pending'");
$stmt->execute();
$pending_count = $stmt->fetchColumn();

// If there are pending approvals, show a notification at the top
if ($pending_count > 0) {
    echo '
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Attention!</strong> You have ' . $pending_count . ' pending account ' . ($pending_count == 1 ? 'approval' : 'approvals') . ' that require your review.
        <a href="?approval=pending" class="alert-link">Click here to view them</a>.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Add CSS to highlight pending rows and improve table usability
echo '
<style>
    /* Table container styles */
    .table-responsive-wrapper {
        position: relative;
        max-height: calc(100vh - 250px);
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }

    /* Fixed header styles */
    .table-responsive {
        overflow: auto;
        max-height: inherit;
        margin-bottom: 0;
    }

    /* Sticky header */
    .table thead th {
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 10;
        border-bottom: 2px solid #dee2e6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* Table styles */
    .table {
        margin-bottom: 0;
    }

    /* Row hover effect */
    .table tbody tr:hover {
        background-color: rgba(0,0,0,0.02);
    }

    /* Pending approval styles */
    tr.pending-approval {
        background-color: #fff3cd !important;
    }
    tr.pending-approval:hover {
        background-color: #ffe8b3 !important;
    }
    .badge.bg-pending {
        background-color: #fd7e14;
    }
    
    /* Highlighted user styles */
    tr.highlighted-user {
        background-color: #e3f2fd !important;
        border: 2px solid #2196f3 !important;
        box-shadow: 0 0 10px rgba(33, 150, 243, 0.3) !important;
        animation: highlightPulse 2s ease-in-out;
    }
    tr.highlighted-user:hover {
        background-color: #bbdefb !important;
    }
    
    @keyframes highlightPulse {
        0% { box-shadow: 0 0 10px rgba(33, 150, 243, 0.3); }
        50% { box-shadow: 0 0 20px rgba(33, 150, 243, 0.6); }
        100% { box-shadow: 0 0 10px rgba(33, 150, 243, 0.3); }
    }

    /* Scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Action buttons container */
    .action-buttons {
        white-space: nowrap;
        display: flex;
        gap: 3px;
        justify-content: flex-start;
        align-items: center;
    }

    .action-buttons .btn {
        padding: 0.25rem 0.4rem;
        font-size: 0.8rem;
        line-height: 1;
        border-radius: 4px;
        margin: 0;
    }

    .action-buttons .btn i {
        font-size: 0.75rem;
    }

    /* Tooltip for action buttons */
    .action-buttons .btn[data-bs-toggle="tooltip"] {
        position: relative;
    }

    /* Status badges */
    .badge {
        font-size: 0.85em;
        padding: 0.4em 0.8em;
    }

    /* Table cell padding */
    .table td, .table th {
        padding: 1rem;
        vertical-align: middle;
    }

    /* Fixed width columns */
    .table .col-username { width: 130px; }
    .table .col-name { width: 180px; }
    .table .col-email { width: 180px; }
    .table .col-role { width: 100px; }
    .table .col-status { width: 90px; }
    .table .col-approval { width: 100px; }
    .table .col-actions { width: 250px; }

    /* Clickable photo styles */
    .clickable-photo {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: 2px solid #dee2e6;
    }
    
    .clickable-photo:hover {
        transform: scale(1.15);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        border-color: #007bff;
    }
    
    .clickable-photo::after {
        content: "\\f065"; /* Font Awesome expand icon */
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 3px;
        border-radius: 50%;
        font-size: 10px;
        opacity: 0;
        transition: opacity 0.2s ease;
        pointer-events: none;
    }
    
    .clickable-photo:hover::after {
        opacity: 1;
    }
    
    /* Photo modal styling */
    #photoModalImage {
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    /* Add a subtle animation to the modal */
    .modal.fade .modal-dialog {
        transform: scale(0.8);
        transition: transform 0.3s ease;
    }
    
    .modal.show .modal-dialog {
        transform: scale(1);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .table-responsive-wrapper {
            max-height: calc(100vh - 200px);
        }
        
        .action-buttons {
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    }
</style>';

// Handle user actions
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    try {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        
        if ($action === 'activate' || $action === 'deactivate') {
            $new_status = $action === 'activate' ? 'active' : 'inactive';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$new_status, $user_id]);
            $success = "User status updated successfully.";
        } elseif ($action === 'approve' || $action === 'reject') {
            $new_approval_status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE user_id = ?");
            $stmt->execute([$new_approval_status, $user_id]);
            
            // Get user information for notification
            $stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get current admin username for the notification
            $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $admin_username = $stmt->fetchColumn();
            
            // Create notification for the user
            if ($action === 'approve') {
                // Set user to active status when approved
                $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Send standardized approval notification to user
                notifyUserAccountApproved($conn, $user_id, $user_info['username'], $user_info['role']);
            } else {
                // Send standardized rejection notification to user
                notifyUserAccountRejected($conn, $user_id, $user_info['username'], $user_info['role']);
            }
            
            // Notify all admins about the approval action
            notifyAdminAccountApproval($conn, $user_id, $user_info['username'], $user_info['role'], $new_approval_status, $admin_username);
            
            // Generate notification URL for the user
            $notification_url = generateUserNotificationUrl($user_info['username'], $new_approval_status);
            
            // Log the notification event
            logUserNotificationEvent($user_info['username'], $action, "Notification URL: $notification_url");
            
            $success = "User {$action}d successfully. User will see notification when they visit the main page.";
        } elseif ($action === 'delete') {
            // Start transaction for safe deletion
            $conn->beginTransaction();
            
            try {
                // Prevent deleting the last admin
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admin_count = $stmt->fetchColumn();
                
                $stmt = $conn->prepare("SELECT role, username FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user_data) {
                    throw new Exception("User not found.");
                }
                
                if ($user_data['role'] === 'admin' && $admin_count <= 1) {
                    $error = "Cannot delete the last admin user.";
                    $conn->rollBack();
                } else {
                    // Get additional info for employer accounts
                    $deletion_info = "";
                    if ($user_data['role'] === 'employer') {
                        // Get company and job counts
                        $stmt = $conn->prepare("
                            SELECT c.company_name,
                                   (SELECT COUNT(*) FROM jobs WHERE company_id = c.company_id) as job_count,
                                   (SELECT COUNT(*) FROM applications a 
                                    JOIN jobs j ON a.job_id = j.job_id 
                                    WHERE j.company_id = c.company_id) as application_count
                            FROM companies c 
                            WHERE c.employer_id = ?
                        ");
                        $stmt->execute([$user_id]);
                        $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($company_data) {
                            $deletion_info = " This will also delete company '{$company_data['company_name']}', {$company_data['job_count']} job(s), and {$company_data['application_count']} application(s).";
                        }
                    }
                    
                    // Delete the user (cascade will handle related records)
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Commit the transaction
                    $conn->commit();
                    
                    $success = "User '{$user_data['username']}' deleted successfully.{$deletion_info}";
                }
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_role' && isset($_POST['role'])) {
            $new_role = $_POST['role'];
            
            // Check if trying to assign admin role
            if ($new_role === 'admin') {
                // Get current admin count
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admin_count = $stmt->fetchColumn();
                
                // Get current user's role
                $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $current_role = $stmt->fetchColumn();
                
                // Allow any admin to assign admin role
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $stmt->execute([$new_role, $user_id]);
                $success = "User role updated to admin successfully.";
            } else {
                // For non-admin roles, proceed as normal
                if (in_array($new_role, ['jobseeker', 'employer'])) {
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                    $stmt->execute([$new_role, $user_id]);
                    $success = "User role updated successfully.";
                } else {
                    $error = "Invalid role selected.";
                }
            }
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle add user form submission
if (isset($_POST['add_user'])) {
    try {
        // Get form data
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        
        // Log for debugging
        error_log("Add user attempt: Username=$username, Email=$email, Role=$role");
        
        // Enhanced debugging
        error_log("Add user debug - Raw POST data: " . print_r($_POST, true));
        error_log("Add user debug - Username length: " . strlen($username) . ", Email length: " . strlen($email));
        error_log("Add user debug - Username hex: " . bin2hex($username) . ", Email hex: " . bin2hex($email));
        
        // Validate inputs
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = "Please fill in all required fields.";
            error_log("Add user validation failed: Empty required fields");
        } else {
            // Enhanced debugging for validation query
            error_log("Add user debug - About to check for existing users with username='$username' and email='$email'");
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $count = $stmt->fetchColumn();
            
            error_log("Add user debug - Validation query returned count: $count");
            
            // Additional debugging - check each condition separately
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $username_count = $stmt->fetchColumn();
            error_log("Add user debug - Username '$username' exists: $username_count times");
            
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $email_count = $stmt->fetchColumn();
            error_log("Add user debug - Email '$email' exists: $email_count times");
            
            // Show existing users for comparison
            $stmt = $conn->prepare("SELECT username, email FROM users LIMIT 5");
            $stmt->execute();
            $existing_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Add user debug - First 5 existing users: " . print_r($existing_users, true));
            
            if ($count > 0) {
                $error = "Username or email already exists.";
                error_log("Add user validation failed: Username or email already exists (count=$count)");
            } else {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert into users table with active status and approved approval_status
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, approval_status) VALUES (?, ?, ?, ?, 'active', 'approved')");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $email, $hashed_password, $role]);
                
                $user_id = $conn->lastInsertId();
                error_log("User inserted with ID: $user_id");
                
                // Insert into user_profiles table
                $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $first_name, $last_name, $phone]);
                
                // Commit transaction
                $conn->commit();
                
                // Don't log in the newly created user - keep admin logged in
                // $_SESSION['user_id'] = $user_id;
                // $_SESSION['username'] = $username;
                // $_SESSION['role'] = $role;
                
                $success = "User <strong>$username</strong> added successfully!";
                $added_username = $username; // Store the newly added username
                error_log("User added successfully: ID=$user_id, Username=$username");
            }
        }
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Error: " . $e->getMessage();
        error_log("Add user failed with error: " . $e->getMessage());
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

try {
    // Build the base query for counting total records
    $count_query = "SELECT COUNT(DISTINCT u.user_id) FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id LEFT JOIN companies c ON u.user_id = c.employer_id";
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    if ($role_filter) {
        $where_conditions[] = "u.role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter) {
        $where_conditions[] = "u.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($where_conditions)) {
        $count_query .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Get total records
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    
    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);
    
    // Ensure current page is within valid range
    if ($page < 1) $page = 1;
    if ($page > $total_pages) $page = $total_pages;
    
    // Build the main query with pagination
    $query = "SELECT DISTINCT u.*, up.first_name, up.last_name, up.phone, up.profile_picture, c.company_logo 
              FROM users u 
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN companies c ON u.user_id = c.employer_id";
    
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query .= " ORDER BY u.created_at DESC LIMIT $records_per_page OFFSET $offset";
    
    // Execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("User query executed: " . substr($query, 0, 100) . "... with " . count($users) . " results");
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
    error_log("Error fetching users: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <style>
        /* Additional user-specific styles */
        .user-photo {
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .user-photo:hover {
            transform: scale(1.1);
            cursor: pointer;
        }
        
        .user-photo-placeholder {
            border: 2px solid #dee2e6;
            font-size: 14px;
        }

        body {
            background-color: #f8f9fa;
        }

        /* Optimize Header */
        .users-header {
            padding: 0.75rem 0;
            margin-bottom: 0.75rem;
        }

        .users-header h2 {
            font-size: 1.25rem;
        }

        /* Optimize Filter Section */
        .filter-card {
            margin-bottom: 0.75rem;
        }

        .filter-card .card-body {
            padding: 0.75rem;
        }

        .form-control, .form-select {
            padding: 0.35rem 0.5rem;
            font-size: 0.85rem;
        }

        .input-group-text {
            padding: 0.35rem 0.5rem;
        }

        /* Optimize Buttons */
        .btn {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }

        /* Optimize Table */
        .users-table th {
            padding: 0.5rem;
            font-size: 0.8rem;
        }

        .users-table td {
            padding: 0.5rem;
            font-size: 0.85rem;
        }

        /* Optimize Table Container */
        .table-responsive {
            max-height: none;
            overflow-x: auto;
            margin: 0 -1rem;
            padding: 0 1rem;
        }

        /* Optimize Table Column Widths */
        .users-table th:nth-child(1), /* Photo */
        .users-table td:nth-child(1) {
            width: 8%;
            min-width: 60px;
            text-align: center;
        }

        .users-table th:nth-child(2), /* Username */
        .users-table td:nth-child(2) {
            width: 15%;
            min-width: 120px;
        }

        .users-table th:nth-child(3), /* Name */
        .users-table td:nth-child(3) {
            width: 18%;
            min-width: 180px;
        }

        .users-table th:nth-child(4), /* Email */
        .users-table td:nth-child(4) {
            width: 22%;
            min-width: 180px;
        }

        .users-table th:nth-child(5), /* Role */
        .users-table td:nth-child(5) {
            width: 10%;
            min-width: 90px;
        }

        .users-table th:nth-child(6), /* Status */
        .users-table td:nth-child(6) {
            width: 9%;
            min-width: 80px;
        }

        .users-table th:nth-child(7), /* Approval */
        .users-table td:nth-child(7) {
            width: 9%;
            min-width: 80px;
        }

        .users-table th:nth-child(8), /* Actions */
        .users-table td:nth-child(8) {
            width: 9%;
            min-width: 80px;
        }

        /* Remove horizontal scroll indicator */
        .table-responsive::after {
            display: none;
        }

        /* Optimize Badges */
        .badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Optimize User Info */
        .user-info .name {
            font-size: 0.85rem;
        }

        .user-info .phone {
            font-size: 0.75rem;
        }

        /* Optimize Dropdown */
        .dropdown-menu {
            padding: 0.25rem;
        }

        .dropdown-item {
            padding: 0.35rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Photo styling */
        .user-photo {
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .user-photo:hover {
            transform: scale(1.1);
            cursor: pointer;
        }

        .user-photo-placeholder {
            border: 2px solid #dee2e6;
            font-size: 14px;
        }

        /* Optimize Alerts */
        .alert {
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }

        /* Optimize Form Selects */
        .form-select-sm {
            padding: 0.25rem 1.5rem 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Optimize Empty State */
        .empty-state {
            padding: 1.5rem 1rem;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        /* Add Table Row Hover Effect */
        .users-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .users-table tbody tr:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }

        /* Optimize Status Badges */
        .badge i {
            font-size: 0.7rem;
            margin-right: 0.25rem;
        }

        /* Add Loading State */
        .table-loading {
            position: relative;
            min-height: 200px;
        }

        .table-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Fade highlight effect for newly added users */
        .fade-highlight {
            transition: background-color 2s ease;
            background-color: transparent !important;
        }

        /* Optimize Mobile View */
        @media (max-width: 768px) {
            .users-header {
                padding: 0.5rem 0;
            }

            .filter-card .card-body {
                padding: 0.5rem;
            }

            .btn {
                padding: 0.25rem 0.5rem;
            }

            .users-table th,
            .users-table td {
                padding: 0.35rem 0.25rem;
            }

            .table-responsive {
                max-height: calc(100vh - 200px);
            }
        }

        /* Add Table Row Selection */
        .users-table tbody tr.selected {
            background-color: #e8f0fe;
        }

        /* Optimize Action Buttons */
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
        }

        .btn-group .btn i {
            font-size: 0.8rem;
        }

        /* Add Quick Actions */
        .quick-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 0.5rem;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            z-index: 1000;
        }

        /* Optimize Modal */
        .modal-header {
            padding: 0.75rem;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            padding: 0.75rem;
        }

        /* Add Table Row Actions */
        .row-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .users-table tr:hover .row-actions {
            opacity: 1;
        }

        .sidebar, .sidebar-menu a {
            background: #1a252f !important;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #2c3e50 !important;
            color: #3498db !important;
        }

        /* Design improvements: add more spacing, card background, and hover effect */
        .users-table .table {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 6px 24px rgba(52, 152, 219, 0.08), 0 1.5px 6px rgba(44, 62, 80, 0.06);
            overflow: hidden;
        }
        .users-table th, .users-table td {
            vertical-align: middle;
        }
        .users-table tr {
            transition: background 0.2s;
        }
        .users-table tr:hover {
            background: #f1f7fd;
        }

        .pagination-wrapper {
            margin-top: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .page-link {
            padding: 0.5rem 1rem;
            color: #3a8ffe;
            border: 1px solid #dee2e6;
            margin: 0 2px;
        }
        
        .page-link:hover {
            background-color: #e9ecef;
            color: #0056b3;
            border-color: #dee2e6;
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            border-color: transparent;
        }
        
        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }
        
        .pagination .fas {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <!-- Logo centered below admin panel heading -->
        <div class="text-center mb-2 mt-1">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="active">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <span>Jobs</span>
                </a>
            </li>
            <li>
                <a href="categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Job Categories</span>
                </a>
            </li>
            <li>
                <a href="companies.php">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
                </a>
            </li>
            <li>
                <a href="applications.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li>
                <a href="job-fair-events.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Job Fair Events</span>
                </a>
            </li>
            <li>
                <a href="placements.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>Graduate Placements</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content page-transition">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <!-- Modern Page Header -->
            <div class="page-header" data-aos="fade-down">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center position-relative">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-users me-3"></i>
                                User Management
                            </h1>
                            <p class="page-subtitle">Manage all system users and their permissions</p>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-center">
                                <h3 class="text-white mb-0"><?php echo count($users); ?></h3>
                                <small class="opacity-75">Total Users</small>
                            </div>
                            <button type="button" class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus me-2"></i> Add User
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-modern" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-modern" data-aos="fade-up">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Modern Filter Section -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control form-control-modern" name="search" 
                                       placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-control-modern" name="role">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="jobseeker" <?php echo $role_filter === 'jobseeker' ? 'selected' : ''; ?>>Job Seeker</option>
                                <option value="employer" <?php echo $role_filter === 'employer' ? 'selected' : ''; ?>>Employer</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-control-modern" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modern Users Table -->
            <div class="modern-table-container" data-aos="fade-up" data-aos-delay="200">
                <table class="table modern-table">
                    <thead>
                        <tr>
                            <th class="col-photo">Photo</th>
                            <th class="col-username">Username</th>
                            <th class="col-name">Name</th>
                            <th class="col-email">Email</th>
                            <th class="col-role">Role</th>
                            <th class="col-status">Status</th>
                            <th class="col-approval">Approval</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>No users found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php $delay = 0; foreach ($users as $user): $delay += 30; ?>
                            <tr class="table-row-animated <?php echo ($added_username && $user['username'] === $added_username) ? 'table-success' : ''; ?> <?php echo ($user['approval_status'] === 'pending' ? 'pending-approval' : '') ?> <?php echo ($highlight_user_id && $user['user_id'] == $highlight_user_id) ? 'highlighted-user' : ''; ?>" 
                                data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                                    <td>
                                        <?php 
                                        $photo_src = null;
                                        $alt_text = "Profile Photo";
                                        
                                        // For employers, prioritize company logo
                                        if ($user['role'] === 'employer' && !empty($user['company_logo'])) {
                                            $photo_src = "../uploads/company_logos/" . $user['company_logo'];
                                            $alt_text = "Company Logo";
                                        }
                                        // For all users (including employers without company logo), check profile picture
                                        elseif (!empty($user['profile_picture'])) {
                                            $photo_src = "../" . $user['profile_picture'];
                                            $alt_text = "Profile Photo";
                                        }
                                        
                                        if ($photo_src): ?>
                                            <div class="position-relative d-inline-block">
                                                <img src="<?php echo htmlspecialchars($photo_src); ?>" 
                                                     alt="<?php echo $alt_text; ?>" 
                                                     class="user-photo rounded-circle clickable-photo" 
                                                     style="width: 40px; height: 40px; object-fit: cover; cursor: pointer;"
                                                     data-bs-toggle="modal"
                                                     data-bs-target="#photoViewModal"
                                                     data-photo-src="<?php echo htmlspecialchars($photo_src); ?>"
                                                     data-photo-title="<?php echo htmlspecialchars($user['username'] . ' - ' . $alt_text); ?>"
                                                     title="Click to view full size">
                                            </div>
                                        <?php else: ?>
                                            <div class="user-photo-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px; background-color: #e9ecef; color: #6c757d;"
                                                 title="No photo available">
                                                <i class="fas fa-<?php echo $user['role'] === 'employer' ? 'building' : 'user'; ?>"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <?php
                                        // Get company name for employers
                                        if ($user['role'] === 'employer') {
                                            $stmt = $conn->prepare("SELECT company_name FROM companies WHERE employer_id = ?");
                                            $stmt->execute([$user['user_id']]);
                                            $company = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($company && $company['company_name']) {
                                                echo '<span class="text-primary"><i class="fas fa-building me-1"></i> ' . 
                                                     htmlspecialchars($company['company_name']) . '</span>';
                                            } else {
                                                echo '<em class="text-muted"><i class="fas fa-building me-1"></i> Company not set</em>';
                                            }
                                        } else {
                                            // For non-employer users, show full name
                                            $full_name = trim(htmlspecialchars($user['first_name'] . ' ' . $user['last_name']));
                                            if ($full_name) {
                                                echo '<i class="fas fa-user me-1"></i> ' . $full_name;
                                            } else {
                                                echo '<em class="text-muted"><i class="fas fa-user me-1"></i> Name not set</em>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-modern <?php 
                                            if ($user['role'] === 'admin') echo 'bg-danger';
                                            elseif ($user['role'] === 'employer') echo 'bg-primary';
                                            elseif ($user['role'] === 'jobseeker') echo 'bg-success';
                                            else echo 'bg-secondary';
                                        ?>">
                                            <i class="fas fa-<?php 
                                                if ($user['role'] === 'admin') echo 'user-shield';
                                                elseif ($user['role'] === 'employer') echo 'building';
                                                elseif ($user['role'] === 'jobseeker') echo 'user';
                                                else echo 'question';
                                            ?> me-1"></i>
                                            <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-modern <?php 
                                            if ($user['status'] === 'active') echo 'bg-success';
                                            elseif ($user['status'] === 'inactive') echo 'bg-secondary';
                                            else echo 'bg-warning';
                                        ?>">
                                            <i class="fas fa-<?php 
                                                if ($user['status'] === 'active') echo 'check-circle';
                                                elseif ($user['status'] === 'inactive') echo 'times-circle';
                                                else echo 'exclamation-circle';
                                            ?> me-1"></i>
                                            <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            if ($user['approval_status'] === 'approved') echo 'bg-success';
                                            elseif ($user['approval_status'] === 'rejected') echo 'bg-danger';
                                            elseif ($user['approval_status'] === 'pending') echo 'bg-pending';
                                            else echo 'bg-secondary';
                                        ?>">
                                            <?php echo ucfirst(htmlspecialchars($user['approval_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- View button -->
                                            <button type="button" class="btn btn-info btn-sm" 
                                                    onclick="viewUser(<?php echo $user['user_id']; ?>)"
                                                    data-bs-toggle="tooltip" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Edit button -->
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                    onclick="editUser(<?php echo $user['user_id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <?php if ($user['approval_status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="confirmAction(<?php echo $user['user_id']; ?>, 'approve')"
                                                        data-bs-toggle="tooltip" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                        onclick="confirmAction(<?php echo $user['user_id']; ?>, 'reject')"
                                                        data-bs-toggle="tooltip" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-warning btn-sm" 
                                                        onclick="confirmAction(<?php echo $user['user_id']; ?>, 'deactivate')"
                                                        data-bs-toggle="tooltip" title="Deactivate">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="confirmAction(<?php echo $user['user_id']; ?>, 'activate')"
                                                        data-bs-toggle="tooltip" title="Activate">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    onclick="showRoleModal(<?php echo $user['user_id']; ?>, '<?php echo $user['role']; ?>')"
                                                    data-bs-toggle="tooltip" title="Change Role">
                                                <i class="fas fa-user-tag"></i>
                                            </button>
                                            
                                            <?php if (!($user['role'] === 'admin' && $admin_count <= 1)): ?>
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                        onclick="confirmAction(<?php echo $user['user_id']; ?>, 'delete')"
                                                        data-bs-toggle="tooltip" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper mt-3">
                    <nav aria-label="Users pagination">
                        <ul class="pagination justify-content-center">
                            <!-- Previous button -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" <?php echo $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            
                            <!-- Page numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) . '">' . $i . '</a>
                                    </li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <!-- Next button -->
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" <?php echo $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="text-center mt-2 text-muted small">
                        Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> users
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="jobseeker">Job Seeker</option>
                                    <option value="employer">Employer</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        <div class="small text-muted">* Required fields</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modern JavaScript Stack -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    
    <!-- Floating Scroll to Top Button -->
    <button id="scrollToTop" class="scroll-to-top" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
    // Initialize AOS (Animate On Scroll)
    AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: true,
        offset: 100
    });

    // Scroll to top functionality
    window.addEventListener('scroll', function() {
        const scrollBtn = document.getElementById('scrollToTop');
        if (window.pageYOffset > 100) {
            scrollBtn.classList.add('show');
        } else {
            scrollBtn.classList.remove('show');
        }
    });

    // Smooth scroll to top
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
    </script>

    <script>
    // Initialize tooltips and handle successful user addition
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover'
            });
        });

        // Clear form when add user modal is opened
        const addUserModalElement = document.getElementById('addUserModal');
        addUserModalElement.addEventListener('show.bs.modal', function() {
            console.log('Modal opening - clearing form');
            const form = addUserModalElement.querySelector('form');
            if (form) {
                form.reset();
                // Also clear any validation states
                const inputs = form.querySelectorAll('.form-control, .form-select');
                inputs.forEach(input => {
                    input.classList.remove('is-valid', 'is-invalid');
                });
            }
        });

        // Check if user was successfully added and highlight the new row
        <?php if ($success && $added_username): ?>
            // Scroll to the newly added user row
            const newUserRow = document.querySelector('tr.table-success');
            if (newUserRow) {
                newUserRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight for a few seconds then fade
                setTimeout(function() {
                    newUserRow.classList.add('fade-highlight');
                }, 2000);
            }
            
            // Close the add user modal if it's open
            const addUserModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
            if (addUserModal) {
                addUserModal.hide();
            }
            
            // Clear the form for next use
            const addUserForm = document.getElementById('addUserModal').querySelector('form');
            if (addUserForm) {
                addUserForm.reset();
            }
            
            // Auto-dismiss success alert after 5 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s ease';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.remove();
                    }, 500);
                }, 5000);
            }
        <?php endif; ?>
    });

    // Helper function to safely display data
    function safeDisplay(value, defaultValue = 'Not Available') {
        if (value === null || value === undefined || value === '' || value === 'null' || value === 'undefined') {
            return defaultValue;
        }
        return value;
    }

    // Helper functions for badges and formatting
    function getRoleBadgeClass(role) {
        switch(role) {
            case 'admin': return 'bg-danger';
            case 'employer': return 'bg-primary';
            case 'jobseeker': return 'bg-success';
            default: return 'bg-secondary';
        }
    }

    function getStatusBadgeClass(status) {
        switch(status) {
            case 'active': return 'bg-success';
            case 'inactive': return 'bg-secondary';
            default: return 'bg-warning';
        }
    }

    function getApprovalBadgeClass(status) {
        switch(status) {
            case 'approved': return 'bg-success';
            case 'rejected': return 'bg-danger';
            case 'pending': return 'bg-warning';
            default: return 'bg-secondary';
        }
    }

    function getJobStatusBadgeClass(status) {
        switch(status) {
            case 'active': return 'bg-success';
            case 'closed': return 'bg-danger';
            case 'draft': return 'bg-secondary';
            default: return 'bg-warning';
        }
    }

    function getApplicationStatusBadgeClass(status) {
        switch(status) {
            case 'accepted': return 'bg-success';
            case 'rejected': return 'bg-danger';
            case 'pending': return 'bg-warning';
            case 'interviewing': return 'bg-info';
            default: return 'bg-secondary';
        }
    }

    function formatDate(dateString) {
        if (!dateString || dateString === null || dateString === undefined) return 'Not set';
        try {
            const date = new Date(dateString);
            // Check if date is valid
            if (isNaN(date.getTime())) return 'Invalid date';
            
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString('en-US', options);
        } catch (e) {
            console.error('Error formatting date:', e);
            return 'Date error';
        }
    }

    function confirmAction(userId, action) {
        let message = 'Are you sure you want to perform this action?';
        
        if (action === 'delete') {
            message = 'Are you sure you want to delete this user? This action cannot be undone.';
        } else if (action === 'deactivate') {
            message = 'Are you sure you want to deactivate this user?';
        } else if (action === 'activate') {
            message = 'Are you sure you want to activate this user?';
        } else if (action === 'approve') {
            message = 'Are you sure you want to approve this user?';
        } else if (action === 'reject') {
            message = 'Are you sure you want to reject this user?';
        }
        
        if (confirm(message)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="action" value="${action}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function showRoleModal(userId, currentRole) {
        const roles = ['jobseeker', 'employer', 'admin'];
        const options = roles.map(role => 
            `<option value="${role}" ${role === currentRole ? 'selected' : ''}>${role.charAt(0).toUpperCase() + role.slice(1)}</option>`
        ).join('');
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Change User Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="roleForm" method="POST">
                            <input type="hidden" name="user_id" value="${userId}">
                            <input type="hidden" name="action" value="update_role">
                            <div class="mb-3">
                                <label class="form-label">Select Role</label>
                                <select name="role" class="form-select">${options}</select>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Update Role</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.bs.modal', function () {
            modal.remove();
        });
    }

    function viewUser(userId) {
        // Create and show modal with user details
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">User Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // Fetch user details
        fetch(`get_user_details.php?user_id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('User data received:', data); // Debug log
                
                if (data.error) {
                    throw new Error(data.error);
                }
                // Ensure all required data fields exist with default values
                const userData = {
                    user_id: data.user_id || '',
                    username: data.username || '',
                    email: data.email || '',
                    role: data.role || '',
                    status: data.status || '',
                    approval_status: data.approval_status || '',
                    first_name: data.first_name || '',
                    last_name: data.last_name || '',
                    phone: data.phone || '',
                    created_at: data.created_at || '',
                    updated_at: data.updated_at || '',
                    total_notifications: data.total_notifications || 0,
                    unread_notifications: data.unread_notifications || 0,
                    profile_image: data.profile_image || null,
                    company_name: data.company_name || '',
                    industry: data.industry || '',
                    company_size: data.company_size || '',
                    company_website: data.company_website || '',
                    company_description: data.company_description || '',
                    total_jobs: data.total_jobs || 0,
                    total_applications: data.total_applications || 0,
                    company_created_at: data.company_created_at || '',
                    logo_url: data.logo_url || null,
                    job_postings: Array.isArray(data.job_postings) ? data.job_postings : [],
                    applications: Array.isArray(data.applications) ? data.applications : [],
                    login_history: Array.isArray(data.login_history) ? data.login_history : [],
                    recent_notifications: Array.isArray(data.recent_notifications) ? data.recent_notifications : []
                };

                const modalBody = modal.querySelector('.modal-body');
                modalBody.innerHTML = `
                    <div class="container-fluid">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        ${userData.profile_image ? 
                                            `<img src="${userData.profile_image}" alt="Profile" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover; border: 2px solid #dee2e6;" onerror="this.onerror=null; this.src='../assets/images/default-avatar.png';">` :
                                            `<div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 100px; height: 100px; border: 2px solid #dee2e6;">
                                                <i class="fas fa-user fa-3x text-secondary"></i>
                                            </div>`
                                        }
                                    </div>
                                    <div>
                                        <h5 class="border-bottom pb-2 mb-0">Basic Information</h5>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>User ID:</strong> ${safeDisplay(userData.user_id)}</p>
                                        <p><strong>Username:</strong> ${safeDisplay(userData.username)}</p>
                                        <p><strong>Email:</strong> ${safeDisplay(userData.email)}</p>
                                        <p><strong>Role:</strong> <span class="badge ${getRoleBadgeClass(userData.role)}">${safeDisplay(userData.role)}</span></p>
                                        <p><strong>Status:</strong> <span class="badge ${getStatusBadgeClass(userData.status)}">${safeDisplay(userData.status)}</span></p>
                                        <p><strong>Approval Status:</strong> <span class="badge ${getApprovalBadgeClass(userData.approval_status)}">${safeDisplay(userData.approval_status)}</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Full Name:</strong> ${userData.first_name || userData.last_name ? `${safeDisplay(userData.first_name)} ${safeDisplay(userData.last_name)}` : 'Not Available'}</p>
                                        <p><strong>Phone:</strong> ${safeDisplay(userData.phone)}</p>
                                        <p><strong>Created:</strong> ${formatDate(userData.created_at)}</p>
                                        <p><strong>Last Updated:</strong> ${formatDate(userData.updated_at)}</p>
                                        <p><strong>Notifications:</strong> ${userData.total_notifications} (${userData.unread_notifications} unread)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ${userData.role === 'employer' ? `
                            <!-- Company Information -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            ${userData.logo_url ? 
                                                `<img src="${userData.logo_url}" alt="Company Logo" class="rounded" style="width: 120px; height: 120px; object-fit: contain; border: 2px solid #dee2e6; background: #fff; padding: 5px;" onerror="this.onerror=null; this.src='../assets/images/default-company.png';">` :
                                                `<div class="rounded bg-light d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; border: 2px solid #dee2e6;">
                                                    <i class="fas fa-building fa-3x text-secondary"></i>
                                                </div>`
                                            }
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="border-bottom pb-2 mb-0">Company Information</h5>
                                            <div class="text-muted small">
                                                <span class="badge bg-info me-2">
                                                    <i class="fas fa-briefcase me-1"></i>${userData.total_jobs || 0} Jobs Posted
                                                </span>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-users me-1"></i>${userData.total_applications || 0} Applications
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title text-primary">
                                                        <i class="fas fa-info-circle me-2"></i>Basic Details
                                                    </h6>
                                                    <p class="mb-2"><strong>Company Name:</strong> ${safeDisplay(userData.company_name)}</p>
                                                    <p class="mb-2"><strong>Industry:</strong> ${safeDisplay(userData.industry)}</p>
                                                    <p class="mb-0"><strong>Company Size:</strong> ${safeDisplay(userData.company_size)}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title text-primary">
                                                        <i class="fas fa-address-card me-2"></i>Contact Information
                                                    </h6>
                                                    <p class="mb-0"><strong>Website:</strong> ${userData.company_website ? 
                                                        `<a href="${userData.company_website}" target="_blank" class="text-primary">
                                                            <i class="fas fa-external-link-alt ms-1"></i> ${userData.company_website}
                                                        </a>` : 'Not Available'}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title text-primary">
                                                        <i class="fas fa-align-left me-2"></i>Company Description
                                                    </h6>
                                                    <p class="card-text text-muted mb-0">
                                                        ${safeDisplay(userData.company_description) || 'No description available.'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Job Postings -->
                            ${userData.job_postings && userData.job_postings.length > 0 ? `
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="border-bottom pb-2">Recent Job Postings</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Title</th>
                                                        <th>Status</th>
                                                        <th>Applications</th>
                                                        <th>Posted</th>
                                                        <th>Expires</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${userData.job_postings.map(job => `
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold">${safeDisplay(job.title)}</div>
                                                                <small class="text-muted">${safeDisplay(job.location)}</small>
                                                            </td>
                                                            <td><span class="badge ${getJobStatusBadgeClass(job.status)}">${safeDisplay(job.status)}</span></td>
                                                            <td>
                                                                <div class="d-flex gap-2">
                                                                    <span class="badge bg-secondary" title="Total Applications">
                                                                        <i class="fas fa-users me-1"></i>${job.application_count || 0}
                                                                    </span>
                                                                    <span class="badge bg-warning" title="Pending">
                                                                        <i class="fas fa-clock me-1"></i>${job.pending_applications || 0}
                                                                    </span>
                                                                    <span class="badge bg-success" title="Approved">
                                                                        <i class="fas fa-check me-1"></i>${job.approved_applications || 0}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td>${formatDate(job.created_at)}</td>
                                                            <td>${formatDate(job.deadline)}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                        ` : ''}

                        ${userData.role === 'jobseeker' ? `
                            <!-- Resume Information -->
                            ${data.resume ? `
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="border-bottom pb-2">Resume Information</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Resume Title:</strong> ${safeDisplay(data.resume.title)}</p>
                                                <p><strong>Last Updated:</strong> ${formatDate(data.resume.updated_at)}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Skills:</strong> ${safeDisplay(data.resume.skills)}</p>
                                                <p><strong>Experience:</strong> ${safeDisplay(data.resume.experience)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}

                            <!-- Job Applications -->
                            ${userData.applications.length > 0 ? `
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="border-bottom pb-2">Recent Applications</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Job Title</th>
                                                        <th>Company</th>
                                                        <th>Status</th>
                                                        <th>Applied</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${userData.applications.map(app => `
                                                        <tr>
                                                            <td>${safeDisplay(app.job_title)}</td>
                                                            <td>${safeDisplay(app.company_name)}</td>
                                                            <td><span class="badge ${getApplicationStatusBadgeClass(app.status)}">${safeDisplay(app.status)}</span></td>
                                                            <td>${formatDate(app.created_at)}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                        ` : ''}

                        <!-- Login History -->
                        ${userData.login_history.length > 0 ? `
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2">Recent Login History</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Login Time</th>
                                                    <th>IP Address</th>
                                                    <th>Device/Browser</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${userData.login_history.map(login => `
                                                    <tr>
                                                        <td>${formatDate(login.login_time)}</td>
                                                        <td>${safeDisplay(login.ip_address)}</td>
                                                        <td>${safeDisplay(login.user_agent)}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        ` : ''}

                        <!-- Recent Notifications -->
                        ${userData.recent_notifications.length > 0 ? `
                            <div class="row">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2">Recent Notifications</h5>
                                    <div class="list-group">
                                        ${userData.recent_notifications.map(notif => `
                                            <div class="list-group-item list-group-item-action ${!notif.is_read ? 'list-group-item-light' : ''}">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">${safeDisplay(notif.title)}</h6>
                                                    <small>${formatDate(notif.created_at)}</small>
                                                </div>
                                                <p class="mb-1">${safeDisplay(notif.message)}</p>
                                                ${!notif.is_read ? '<span class="badge bg-primary">Unread</span>' : ''}
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;

                // Add action buttons at the bottom of the modal
                const modalFooter = document.createElement('div');
                modalFooter.className = 'modal-footer';
                modalFooter.innerHTML = `
                    <button type="button" class="btn btn-warning" onclick="editUser(${userData.user_id})">
                        <i class="fas fa-edit me-1"></i> Edit User
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                `;
                modal.querySelector('.modal-content').appendChild(modalFooter);
            })
            .catch(error => {
                console.error('Error fetching user details:', error);
                modal.querySelector('.modal-body').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Error loading user details:</strong> ${error.message}<br>
                        Please check the browser console for more details and try again.
                    </div>
                `;
            });
        
        modal.addEventListener('hidden.bs.modal', function () {
            modal.remove();
        });
    }

    function editUser(userId) {
        // Create edit modal
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center">
                            <div class="spinner-border text-warning" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();

        // Fetch user details for editing
        fetch(`get_user_details.php?user_id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error + (data.details ? ': ' + data.details : ''));
                }
                
                // Ensure all required fields have default values to prevent JavaScript errors
                data.first_name = data.first_name || '';
                data.last_name = data.last_name || '';
                data.phone = data.phone || '';
                data.company_name = data.company_name || '';
                data.industry = data.industry || '';
                data.company_size = data.company_size || '';
                data.company_website = data.company_website || '';
                data.company_description = data.company_description || '';
                
                const modalBody = modal.querySelector('.modal-body');
                modalBody.innerHTML = `
                    <form id="editUserForm" method="POST" action="update_user.php" enctype="multipart/form-data">
                        <input type="hidden" name="user_id" value="${data.user_id}">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" value="${data.username}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="${data.email}" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" value="${data.first_name || ''}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="${data.last_name || ''}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" value="${data.phone || ''}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" required>
                                    <option value="jobseeker" ${data.role === 'jobseeker' ? 'selected' : ''}>Job Seeker</option>
                                    <option value="employer" ${data.role === 'employer' ? 'selected' : ''}>Employer</option>
                                    <option value="admin" ${data.role === 'admin' ? 'selected' : ''}>Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" name="profile_picture" accept="image/*">
                                <div class="form-text">Leave empty to keep current profile picture. Supported formats: JPG, PNG, GIF (Max: 5MB)</div>
                                ${data.profile_image ? `
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <small class="text-muted">Current profile picture:</small>
                                        <img src="${data.profile_image}" style="max-height: 40px; max-width: 40px; object-fit: cover;" class="rounded-circle" alt="Current Profile">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProfilePicture(${data.user_id})">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                ` : ''}
                                <input type="hidden" name="remove_profile_picture" id="removeProfilePictureFlag" value="0">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" ${data.status === 'active' ? 'selected' : ''}>Active</option>
                                    <option value="inactive" ${data.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Approval Status</label>
                                <select class="form-select" name="approval_status" required>
                                    <option value="pending" ${data.approval_status === 'pending' ? 'selected' : ''}>Pending</option>
                                    <option value="approved" ${data.approval_status === 'approved' ? 'selected' : ''}>Approved</option>
                                    <option value="rejected" ${data.approval_status === 'rejected' ? 'selected' : ''}>Rejected</option>
                                </select>
                            </div>
                        </div>

                        ${data.role === 'employer' ? `
                            <hr>
                            <h5 class="mb-3">Company Information</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" name="company_name" value="${data.company_name || ''}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Industry</label>
                                    <input type="text" class="form-control" name="industry" value="${data.industry || ''}">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Size</label>
                                    <select class="form-select" name="company_size">
                                        <option value="">Select Company Size</option>
                                        <option value="1-10" ${data.company_size === '1-10' ? 'selected' : ''}>1-10 employees</option>
                                        <option value="11-50" ${data.company_size === '11-50' ? 'selected' : ''}>11-50 employees</option>
                                        <option value="51-200" ${data.company_size === '51-200' ? 'selected' : ''}>51-200 employees</option>
                                        <option value="201-500" ${data.company_size === '201-500' ? 'selected' : ''}>201-500 employees</option>
                                        <option value="501-1000" ${data.company_size === '501-1000' ? 'selected' : ''}>501-1000 employees</option>
                                        <option value="1000+" ${data.company_size === '1000+' ? 'selected' : ''}>1000+ employees</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Website</label>
                                    <input type="url" class="form-control" name="company_website" value="${data.company_website || ''}">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Company Description</label>
                                    <textarea class="form-control" name="company_description" rows="3">${data.company_description || ''}</textarea>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Company Logo</label>
                                    <input type="file" class="form-control" name="company_logo" accept="image/*">
                                    <div class="form-text">Leave empty to keep current logo. Supported formats: JPG, PNG, GIF (Max: 2MB)</div>
                                    ${data.logo_url ? `<div class="mt-2"><small class="text-muted">Current logo: <img src="${data.logo_url}" style="max-height: 40px;" alt="Current Logo"></small></div>` : ''}
                                </div>
                            </div>
                        ` : ''}

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" name="new_password">
                                <div class="form-text">Only fill this if you want to change the user's password</div>
                            </div>
                        </div>
                    </form>
                `;

                // Add footer with save button
                const modalFooter = document.createElement('div');
                modalFooter.className = 'modal-footer';
                modalFooter.innerHTML = `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editUserForm" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                `;
                modal.querySelector('.modal-content').appendChild(modalFooter);

                // Handle form submission
                document.getElementById('editUserForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    fetch('update_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(result => {
                        if (result.success) {
                            // Show success message and reload page
                            alert('User updated successfully!');
                            window.location.reload();
                        } else {
                            // Show error message
                            alert('Error: ' + (result.message || 'Failed to update user'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating user: ' + error.message + '. Please try again.');
                    });
                });
            })
            .catch(error => {
                console.error('Error:', error);
                modal.querySelector('.modal-body').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Error loading user details:</strong><br>
                        ${error.message}<br>
                        <small class="text-muted">Please check the browser console for more details and try again.</small>
                    </div>
                `;
            });

        modal.addEventListener('hidden.bs.modal', function () {
            modal.remove();
        });
    }

    function removeProfilePicture(userId) {
        if (confirm('Are you sure you want to remove this profile picture?')) {
            document.getElementById('removeProfilePictureFlag').value = '1';
            // Hide the current picture preview
            const currentPictureDiv = document.querySelector('.mt-2.d-flex.align-items-center.gap-2');
            if (currentPictureDiv) {
                currentPictureDiv.style.display = 'none';
            }
            // Add a note that picture will be removed
            const noteDiv = document.createElement('div');
            noteDiv.className = 'mt-2 text-warning';
            noteDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Profile picture will be removed when you save changes.';
            document.querySelector('input[name="profile_picture"]').parentNode.appendChild(noteDiv);
        }
    }

    // Handle photo view modal
    document.addEventListener('DOMContentLoaded', function() {
        // Add photo view modal to the page
        const photoModal = document.createElement('div');
        photoModal.className = 'modal fade';
        photoModal.id = 'photoViewModal';
        photoModal.setAttribute('tabindex', '-1');
        photoModal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="photoModalTitle">User Photo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center p-0">
                        <img id="photoModalImage" src="" alt="User Photo" class="img-fluid" style="max-height: 70vh; width: auto; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a id="photoDownloadBtn" href="" download class="btn btn-primary">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(photoModal);

        // Handle photo modal show event
        document.getElementById('photoViewModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var photoSrc = button.getAttribute('data-photo-src');
            var photoTitle = button.getAttribute('data-photo-title');
            
            // Update modal content
            document.getElementById('photoModalTitle').textContent = photoTitle;
            
            // Show loading state
            var imgElement = document.getElementById('photoModalImage');
            imgElement.style.opacity = '0.5';
            imgElement.style.filter = 'blur(2px)';
            
            // Load the image
            var newImg = new Image();
            newImg.onload = function() {
                imgElement.src = photoSrc;
                imgElement.style.opacity = '1';
                imgElement.style.filter = 'none';
                imgElement.style.transition = 'opacity 0.3s ease, filter 0.3s ease';
            };
            newImg.onerror = function() {
                imgElement.alt = 'Failed to load image';
                imgElement.style.opacity = '1';
                imgElement.style.filter = 'none';
            };
            newImg.src = photoSrc;
            
            // Set download link
            document.getElementById('photoDownloadBtn').href = photoSrc;
            document.getElementById('photoDownloadBtn').download = photoTitle.toLowerCase().replace(/[^a-z0-9]/g, '-') + '.jpg';
        });

        // Add hover effect for clickable photos
        const photos = document.querySelectorAll('.clickable-photo');
        photos.forEach(photo => {
            photo.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.15)';
                this.style.transition = 'transform 0.2s ease';
            });
            photo.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
        
        // Auto-scroll to highlighted user
        const highlightedUser = document.querySelector('.highlighted-user');
        if (highlightedUser) {
            // Add a small delay to ensure the page is fully loaded
            setTimeout(function() {
                highlightedUser.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                
                // Add a visual pulse effect
                highlightedUser.style.animation = 'highlightPulse 3s ease-in-out';
            }, 500);
        }
    });
    </script>
</body>
</html>