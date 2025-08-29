<?php
/**
 * Test Script for Notification Navigation Feature
 * This file helps test the notification click-to-navigate functionality
 */

session_start();
require_once '../config/database.php';
require_once 'includes/admin_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notification Navigation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2><i class="fas fa-test-tube"></i> Notification Navigation Test</h2>
        <p class="text-muted">This page helps test the new notification click-to-navigate functionality.</p>';

// Test creating sample notifications
if (isset($_POST['create_test_notifications'])) {
    try {
        // Create test notifications of different types
        
        // 1. Account approval notification
        $test_user_id = 1; // Adjust as needed
        notifyAdminAccountApproval($conn, $test_user_id, "test_user", "jobseeker", "approved", "test_admin");
        
        // 2. Job approval notification  
        $test_job_id = 1; // Adjust as needed
        notifyAdminJobApproval($conn, $test_job_id, "Test Job Position", "Test Company", "approved", "test_admin");
        
        // 3. Pending account notification
        notifyAdminPendingAccountApproval($conn, $test_user_id, "pending_user", "employer", "test@example.com");
        
        // 4. Pending job notification
        notifyAdminPendingJobApproval($conn, $test_job_id, "Pending Job Title", "Pending Company");
        
        echo '<div class="alert alert-success">Test notifications created successfully!</div>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error creating test notifications: ' . $e->getMessage() . '</div>';
    }
}

echo '
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clipboard-check"></i> Testing Instructions</h5>
                    </div>
                    <div class="card-body">
                        <h6>How to Test Notification Navigation:</h6>
                        <ol>
                            <li><strong>Create Test Notifications:</strong> Click the button below to create sample notifications</li>
                            <li><strong>Go to Notifications Page:</strong> <a href="notifications.php" class="btn btn-sm btn-primary">Open Notifications</a></li>
                            <li><strong>Test Clicking:</strong> Click on any notification card to navigate to the relevant page</li>
                            <li><strong>Check Highlighting:</strong> Verify that the specific item is highlighted on the target page</li>
                            <li><strong>Test Different Types:</strong> Try clicking different notification types (account approvals, job approvals, etc.)</li>
                        </ol>
                        
                        <h6 class="mt-4">Expected Behavior:</h6>
                        <ul>
                            <li><span class="badge bg-info">Account Notifications</span> → Navigate to users.php with user highlighted</li>
                            <li><span class="badge bg-primary">Job Notifications</span> → Navigate to jobs.php with job highlighted</li>
                            <li><span class="badge bg-warning">Pending Items</span> → Navigate with approval filter applied</li>
                            <li><span class="badge bg-success">Auto-scroll</span> → Page automatically scrolls to highlighted item</li>
                            <li><span class="badge bg-secondary">Mark as Read</span> → Notification marked as read when clicked</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> Test Actions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <button type="submit" name="create_test_notifications" class="btn btn-warning">
                                <i class="fas fa-plus-circle"></i> Create Test Notifications
                            </button>
                        </form>
                        
                        <div class="mt-3">
                            <a href="notifications.php" class="btn btn-primary me-2">
                                <i class="fas fa-bell"></i> View Notifications
                            </a>
                            <a href="users.php" class="btn btn-success me-2">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                            <a href="jobs.php" class="btn btn-info">
                                <i class="fas fa-briefcase"></i> Manage Jobs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Feature Overview</h5>
                    </div>
                    <div class="card-body">
                        <h6>New Features Added:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Clickable notification cards</li>
                            <li><i class="fas fa-check text-success"></i> Smart routing based on notification type</li>
                            <li><i class="fas fa-check text-success"></i> Item highlighting on target pages</li>
                            <li><i class="fas fa-check text-success"></i> Auto-scroll to highlighted items</li>
                            <li><i class="fas fa-check text-success"></i> Visual indicators for clickable notifications</li>
                            <li><i class="fas fa-check text-success"></i> Automatic "mark as read" functionality</li>
                        </ul>
                        
                        <h6 class="mt-3">Files Modified:</h6>
                        <ul class="small">
                            <li><code>notification_router.php</code> <span class="badge bg-success">New</span></li>
                            <li><code>notifications.php</code> <span class="badge bg-warning">Enhanced</span></li>
                            <li><code>users.php</code> <span class="badge bg-warning">Enhanced</span></li>
                            <li><code>jobs.php</code> <span class="badge bg-warning">Enhanced</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>';
?> 