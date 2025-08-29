<?php
session_start();
require_once '../config/database.php';
require_once '../jobseeker/includes/jobseeker_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'];
        
        if ($action === 'simulate_status_update') {
            // Get a sample application
            $stmt = $conn->prepare("
                SELECT a.application_id, a.jobseeker_id, j.title as job_title, c.company_name, a.status
                FROM applications a
                JOIN jobs j ON a.job_id = j.job_id
                JOIN companies c ON j.company_id = c.company_id
                LIMIT 1
            ");
            $stmt->execute();
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($application) {
                $new_status = $_POST['new_status'];
                
                // Create notification without actually updating the application
                notifyJobseekerApplicationUpdate(
                    $conn,
                    $application['jobseeker_id'],
                    $application['application_id'],
                    $application['job_title'],
                    $application['company_name'],
                    $new_status
                );
                
                $message = "✅ Demo notification sent! Jobseeker will receive notification about status change to '{$new_status}' for '{$application['job_title']}' at {$application['company_name']}.";
                $success = true;
            } else {
                $message = "❌ No applications found to simulate with.";
            }
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Get some sample applications for display
try {
    $stmt = $conn->prepare("
        SELECT a.application_id, a.status, j.title as job_title, c.company_name, 
               CONCAT(IFNULL(up.first_name, ''), ' ', IFNULL(up.last_name, '')) as applicant_name
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN companies c ON j.company_id = c.company_id
        LEFT JOIN user_profiles up ON a.jobseeker_id = up.user_id
        LIMIT 10
    ");
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $applications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Application Notifications - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .notification-demo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        .feature-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .demo-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
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
        <div class="text-center mb-2 mt-1">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i><span>Applications</span></a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="notification-demo">
                <div class="text-center">
                    <h1><i class="fas fa-bell me-3"></i>Application Status Notifications</h1>
                    <p class="lead mb-0">Automatic notifications are now sent to jobseekers when their application status changes!</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Feature Overview -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3" style="font-size: 3rem; color: #28a745;">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <h5>Automatic Notifications</h5>
                            <p class="text-muted">Notifications are sent automatically when employers or admins update application statuses.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3" style="font-size: 3rem; color: #007bff;">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <h5>Real-time Updates</h5>
                            <p class="text-muted">Jobseekers receive instant notifications about their application progress.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3" style="font-size: 3rem; color: #ffc107;">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h5>Better UX</h5>
                            <p class="text-muted">Keeps jobseekers engaged and informed throughout the application process.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demo Section -->
            <div class="demo-section">
                <h3><i class="fas fa-flask me-2"></i>Test Notification System</h3>
                <p>Send a demo notification to see how the system works when application status changes.</p>
                
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="simulate_status_update">
                    <div class="col-md-6">
                        <label class="form-label">Status to Simulate:</label>
                        <select name="new_status" class="form-select" required>
                            <option value="reviewed">📋 Reviewed</option>
                            <option value="shortlisted">⭐ Shortlisted</option>
                            <option value="hired">🎉 Hired</option>
                            <option value="rejected">❌ Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Demo Notification
                        </button>
                    </div>
                </form>
            </div>

            <!-- How It Works -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>How Automatic Notifications Work</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user-tie me-2"></i>For Employers:</h6>
                            <ul>
                                <li>Update application status in <code>employer/applications.php</code></li>
                                <li>Update status in <code>employer/view-applications.php</code></li>
                                <li>Notification automatically sent to jobseeker</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-user-shield me-2"></i>For Admins:</h6>
                            <ul>
                                <li>Update application status in <code>admin/applications.php</code></li>
                                <li>Update status in <code>admin/view-application.php</code></li>
                                <li>Notification automatically sent to jobseeker</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Types -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Application Status Notification Types</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-primary">📋 Reviewed</h6>
                                <p class="mb-0 small">"Your application has been reviewed for the position..."</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-warning">⭐ Shortlisted</h6>
                                <p class="mb-0 small">"Congratulations! You have been shortlisted for..."</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-success">🎉 Hired</h6>
                                <p class="mb-0 small">"Congratulations! You have been hired. Welcome to the team!"</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-danger">❌ Rejected</h6>
                                <p class="mb-0 small">"Your application was not selected this time. Keep applying..."</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($applications)): ?>
            <!-- Sample Applications -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-database me-2"></i>Sample Applications Available for Testing</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Applicant</th>
                                    <th>Job</th>
                                    <th>Company</th>
                                    <th>Current Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo $app['application_id']; ?></td>
                                    <td><?php echo htmlspecialchars($app['applicant_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($app['status']) {
                                                case 'pending': echo 'warning'; break;
                                                case 'reviewed': echo 'info'; break;
                                                case 'shortlisted': echo 'primary'; break;
                                                case 'hired': echo 'success'; break;
                                                case 'rejected': echo 'danger'; break;
                                                default: echo 'secondary'; break;
                                            }
                                        ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="text-center mt-4 mb-3">
                <a href="applications.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Applications
                </a>
                <a href="../jobseeker/test_notifications.php" class="btn btn-outline-success" target="_blank">
                    <i class="fas fa-eye me-2"></i>View Jobseeker Test Page
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 