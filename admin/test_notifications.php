<?php
session_start();
require_once '../config/database.php';
require_once 'includes/admin_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create some sample notifications
        $notifications_created = 0;
        
        // Sample new user notification
        if (notifyAdminNewUser($conn, 1, 'john_doe', 'jobseeker')) {
            $notifications_created++;
        }
        
        // Sample new job notification
        if (notifyAdminNewJob($conn, 1, 'Software Developer', 'Tech Corp')) {
            $notifications_created++;
        }
        
        // Sample new application notification
        if (notifyAdminNewApplication($conn, 1, 'Marketing Manager', 'Jane Smith')) {
            $notifications_created++;
        }
        
        // Sample company update notification
        if (notifyAdminCompanyUpdate($conn, 1, 'ABC Company')) {
            $notifications_created++;
        }
        
        // Sample event registration notification
        if (notifyAdminEventRegistration($conn, 1, 'Spring Job Fair 2024', 5)) {
            $notifications_created++;
        }
        
        // Sample system alert
        if (notifyAdminSystemAlert($conn, 'Database Backup Completed', 'Weekly database backup completed successfully.', 'low')) {
            $notifications_created++;
        }
        
        $message = "Created {$notifications_created} test notifications successfully!";
        
    } catch (Exception $e) {
        $message = "Error creating test notifications: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notifications - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/header.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                <a href="users.php">
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
                <a href="notifications.php">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
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
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <h2 class="mb-4">Test Notifications</h2>
            
            <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Create Test Notifications</h5>
                </div>
                <div class="card-body">
                    <p>Click the button below to create sample notifications for testing the notification system.</p>
                    <form method="POST">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Test Notifications
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <h6>What this will create:</h6>
                    <ul>
                        <li>New user registration notification</li>
                        <li>New job posting notification</li>
                        <li>New job application notification</li>
                        <li>Company profile update notification</li>
                        <li>Job fair event registration notification</li>
                        <li>System alert notification</li>
                    </ul>
                    
                    <div class="mt-3">
                        <a href="notifications.php" class="btn btn-outline-secondary">
                            <i class="fas fa-eye"></i> View Notifications
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 