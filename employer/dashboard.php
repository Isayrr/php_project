<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notifications.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$error = null;
$company = null;
$active_jobs = [];
$recent_applications = [];
$stats = [
    'active_jobs' => 0,
    'total_applications' => 0,
    'total_hires' => 0
];

try {
    // Get company profile
    $stmt = $conn->prepare("SELECT c.* FROM companies c WHERE c.employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug information
    if ($company) {
        $debug_info = "Company Profile Values:<br>";
        $debug_info .= "Name: " . ($company['company_name'] ? htmlspecialchars($company['company_name']) : 'empty') . "<br>";
        $debug_info .= "Industry: " . ($company['industry'] ? htmlspecialchars($company['industry']) : 'empty') . "<br>";
        $debug_info .= "Size: " . ($company['company_size'] ? htmlspecialchars($company['company_size']) : 'empty') . "<br>";
        $debug_info .= "Description: " . ($company['company_description'] ? 'set' : 'empty') . "<br>";
    }
    
    // Check if company exists and has required fields filled out
    if (!$company || empty($company['company_name']) || empty($company['industry']) || empty($company['company_size']) || empty($company['company_description'])) {
        throw new Exception("Please complete your company profile first. Make sure to fill out the company name, industry, company size, and description.");
    }
    
    // Get active jobs
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE company_id = ? AND status = 'active' ORDER BY posted_date DESC");
    $stmt->execute([$company['company_id']]);
    $active_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent applications
    $stmt = $conn->prepare("SELECT a.*, j.title as job_title, u.email, up.first_name, up.last_name 
                           FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
                           JOIN users u ON a.jobseeker_id = u.user_id 
                           LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                           WHERE j.company_id = ? 
                           ORDER BY a.application_date DESC LIMIT 5");
    $stmt->execute([$company['company_id']]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE j.company_id = ?");
    $stmt->execute([$company['company_id']]);
    $total_applications = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE j.company_id = ? AND a.status = 'hired'");
    $stmt->execute([$company['company_id']]);
    $total_hires = $stmt->fetchColumn();

    $stats = [
        'active_jobs' => count($active_jobs),
        'total_applications' => $total_applications,
        'total_hires' => $total_hires
    ];
    
    // Get unread notifications
    $notifications = getUnreadNotifications($conn, $_SESSION['user_id']);
    
    // Mark notification as read if requested
    if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
        markNotificationAsRead($conn, $_GET['mark_read'], $_SESSION['user_id']);
        header("Location: dashboard.php");
        exit();
    }
    
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - Job Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .sidebar, .sidebar-menu a {
            background: #1a252f !important;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #2c3e50 !important;
            color: #3498db !important;
        }
        /* Updated UI/UX design styles */
        .main-content {
            background-color: #f0f2f5;
            padding: 20px;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 10px 10px 0 0;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn {
            border-radius: 5px;
            font-weight: 600;
            padding: 0.5rem 1rem;
        }

        .btn-lg {
            padding: 0.75rem 1.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: #495057;
        }

        .table td {
            vertical-align: middle;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
        }

        .alert {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Employer Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <!-- Logo centered below employer panel heading -->
        <div class="text-center mb-2 mt-1">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <i class="fas fa-building"></i>
                    <span>Company Profile</span>
                </a>
            </li>
            <li>
                <a href="jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <span>Manage Jobs</span>
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
                    <?php 
                    $notification_count = 0;
                    try {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->execute([$_SESSION['user_id']]);
                        $notification_count = $stmt->fetchColumn();
                    } catch (Exception $e) {
                        // Silently fail
                    }
                    if ($notification_count > 0): ?>
                    <span class="badge bg-danger ms-2"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
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

    <!-- Employer Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <?php if (isset($debug_info)): ?>
                        <hr>
                        <div class="small">
                            <?php echo $debug_info; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!$company): ?>
                        <br>
                        <a href="profile.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus"></i> Complete Company Profile
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Account Status Notification -->
            <?php if (isset($_SESSION['account_notification'])): ?>
            <div class="alert alert-<?php echo strpos($_SESSION['account_notification']['title'], 'Approved') !== false ? 'success' : 'warning'; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo strpos($_SESSION['account_notification']['title'], 'Approved') !== false ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <strong><?php echo htmlspecialchars($_SESSION['account_notification']['title']); ?></strong>
                <p class="mb-0"><?php echo htmlspecialchars($_SESSION['account_notification']['message']); ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['account_notification']); ?>
            <?php endif; ?>

            <!-- Job Fair Event Notifications -->
            <?php if (!empty($notifications)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bell"></i> Notifications
                    </h5>
                    <span class="badge bg-danger"><?php echo count($notifications); ?></span>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php if ($notification['related_type'] === 'event_full'): ?>
                                            <i class="fas fa-calendar-times text-warning"></i>
                                        <?php elseif ($notification['related_type'] === 'spot_available'): ?>
                                            <i class="fas fa-calendar-check text-success"></i>
                                        <?php elseif ($notification['related_type'] === 'event_reminder'): ?>
                                            <i class="fas fa-bell text-info"></i>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle text-primary"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('M d, g:i a', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php if (in_array($notification['related_type'], ['event_full', 'spot_available', 'event_reminder']) && $notification['related_id']): ?>
                                        <a href="job-fair-events.php" class="btn btn-sm btn-primary">
                                            <i class="fas fa-calendar-alt"></i> View Events
                                        </a>
                                    <?php elseif ($notification['related_type'] === 'job' && $notification['related_id']): ?>
                                        <a href="jobs.php" class="btn btn-sm btn-primary">
                                            <i class="fas fa-briefcase"></i> View Jobs
                                        </a>
                                    <?php else: ?>
                                        <div></div>
                                    <?php endif; ?>
                                    <a href="dashboard.php?mark_read=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-check"></i> Mark as Read
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($company): ?>
                <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($company['company_name']); ?></h2>
                
                <!-- Quick Actions Panel -->
                <div class="card mb-4 bg-light">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <a href="jobs.php" class="btn btn-success btn-lg w-100 mb-2">
                                    <i class="fas fa-plus-circle"></i> Post New Job
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="jobs.php" class="btn btn-primary btn-lg w-100 mb-2">
                                    <i class="fas fa-briefcase"></i> Manage Jobs
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="applications.php" class="btn btn-info btn-lg w-100 mb-2">
                                    <i class="fas fa-file-alt"></i> View Applications
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="reports.php" class="btn btn-warning btn-lg w-100 mb-2">
                                    <i class="fas fa-chart-bar"></i> View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-briefcase me-2"></i>Active Jobs
                                        </h5>
                                        <h2 class="card-text mb-0"><?php echo $stats['active_jobs']; ?></h2>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-briefcase" style="font-size: 3rem; opacity: 0.3;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-file-alt me-2"></i>Total Applications
                                        </h5>
                                        <h2 class="card-text mb-0"><?php echo $stats['total_applications']; ?></h2>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-file-alt" style="font-size: 3rem; opacity: 0.3;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-check-circle me-2"></i>Total Hires
                                        </h5>
                                        <h2 class="card-text mb-0"><?php echo $stats['total_hires']; ?></h2>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-check-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Jobs -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Active Jobs</h5>
                        <a href="jobs.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Post New Job
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_jobs)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No active jobs found. Start by posting a new job!</p>
                                <a href="jobs.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Post New Job
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Applications</th>
                                            <th>Posted Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_jobs as $job): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($job['title']); ?></td>
                                            <td><?php echo ucfirst($job['job_type']); ?></td>
                                            <td>
                                                <?php
                                                $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?");
                                                $stmt->execute([$job['job_id']]);
                                                echo $stmt->fetchColumn();
                                                ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></td>
                                            <td>
                                                <a href="edit-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="view-applications.php?job_id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-users"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Applications -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Applications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_applications)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No applications received yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Job</th>
                                            <th>Applied Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_applications as $application): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($application['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($application['application_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($application['status']) {
                                                        case 'pending':
                                                            echo 'warning';
                                                            break;
                                                        case 'reviewed':
                                                            echo 'info';
                                                            break;
                                                        case 'shortlisted':
                                                            echo 'primary';
                                                            break;
                                                        case 'rejected':
                                                            echo 'danger';
                                                            break;
                                                        case 'hired':
                                                            echo 'success';
                                                            break;
                                                        default:
                                                            echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($application['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view-application.php?id=<?php echo $application['application_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 