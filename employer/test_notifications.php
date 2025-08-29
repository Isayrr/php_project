<?php
session_start();
require_once '../config/database.php';
require_once 'includes/employer_notifications.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = null;
$error = null;

// Handle test notification creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    $test_type = $_POST['test_type'];
    
    try {
        switch ($test_type) {
            case 'new_application':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "New Job Application Received",
                    "A new application has been submitted for the position 'Senior PHP Developer' at your company. Review the application to find the best candidate for your team.",
                    'application',
                    123
                );
                break;
                
            case 'job_approved':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "Job Posting Approved",
                    "Your job posting 'Frontend React Developer' has been approved and is now live on the job portal. Candidates can now apply for this position.",
                    'job',
                    456
                );
                break;
                
            case 'job_rejected':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "Job Posting Rejected",
                    "Your job posting 'Data Scientist' has been rejected. Reason: Job description needs more specific requirements and qualifications. Please review and resubmit.",
                    'job',
                    789
                );
                break;
                
            case 'profile_approved':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "Company Profile Approved",
                    "Your company profile has been approved by our team. You can now start posting job opportunities and connecting with talented job seekers.",
                    'company',
                    101
                );
                break;
                
            case 'job_fair_event':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "Job Fair Event Opportunity",
                    "A new job fair event 'Tech Talent Expo 2024' is scheduled for March 15, 2024. Register your company to participate and meet potential candidates face-to-face.",
                    'event',
                    202
                );
                break;
                
            case 'job_expiring':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "Job Posting Expiring Soon",
                    "Your job posting 'Marketing Manager' will expire in 7 days. The application deadline is January 30, 2024. Consider renewing or extending the posting if you haven't found the right candidate yet.",
                    'job',
                    303
                );
                break;
                
            case 'system_update':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "[System Update] New Features Available",
                    "We've added new features to the employer dashboard! You can now bulk manage applications, set up automated responses, and access advanced analytics for your job postings.",
                    'system',
                    null
                );
                break;
                
            case 'registration_confirmed':
                $result = createEmployerNotification(
                    $conn,
                    $user_id,
                    "Job Fair Registration Confirmed",
                    "Your registration for the job fair event 'Digital Skills Summit' has been confirmed. You will receive further details about booth setup and event logistics closer to the date.",
                    'event',
                    404
                );
                break;
                
            default:
                $result = false;
                $error = "Invalid test type selected.";
        }
        
        if ($result && !$error) {
            $success = "Test notification created successfully! Check your notifications dropdown or the notifications page.";
        } elseif (!$error) {
            $error = "Failed to create test notification. Please try again.";
        }
        
    } catch (Exception $e) {
        $error = "Error creating test notification: " . $e->getMessage();
    }
}

// Get current notification stats
$stats = getEmployerNotificationStats($conn, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notifications - Employer Panel</title>
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
        .test-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .test-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                <a href="dashboard.php">
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
                    <span>My Jobs</span>
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
                    <?php if ($stats['unread'] > 0): ?>
                    <span class="badge bg-danger ms-2"><?php echo $stats['unread']; ?></span>
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
                <a href="test_notifications.php" class="active">
                    <i class="fas fa-flask"></i>
                    <span>Test Notifications</span>
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
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Test Notifications</h2>
                    <p class="text-muted mb-0">Generate test notifications to see how the notification system works</p>
                </div>
                <a href="notifications.php" class="btn btn-primary">
                    <i class="fas fa-bell"></i> View All Notifications
                </a>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Notification Statistics -->
            <div class="card stats-card mb-4">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-chart-pie"></i> Your Notification Statistics
                    </h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                                <small>Total Notifications</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-1"><?php echo $stats['unread']; ?></h3>
                                <small>Unread Notifications</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-1"><?php echo $stats['today']; ?></h3>
                                <small>Today's Notifications</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-1"><?php echo $stats['this_week']; ?></h3>
                                <small>This Week</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Notification Cards -->
            <div class="row">
                <!-- New Application -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h6 class="card-title mb-0">New Job Application</h6>
                            </div>
                            <p class="card-text">Test notification for when a job seeker applies to one of your job postings.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="new_application">
                                <button type="submit" name="create_test" class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Job Approved -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h6 class="card-title mb-0">Job Posting Approved</h6>
                            </div>
                            <p class="card-text">Test notification for when admin approves your job posting.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="job_approved">
                                <button type="submit" name="create_test" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Job Rejected -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-danger text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <h6 class="card-title mb-0">Job Posting Rejected</h6>
                            </div>
                            <p class="card-text">Test notification for when admin rejects your job posting with reason.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="job_rejected">
                                <button type="submit" name="create_test" class="btn btn-danger btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Profile Approved -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h6 class="card-title mb-0">Company Profile Approved</h6>
                            </div>
                            <p class="card-text">Test notification for when your company profile gets approved.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="profile_approved">
                                <button type="submit" name="create_test" class="btn btn-info btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Job Fair Event -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h6 class="card-title mb-0">Job Fair Event</h6>
                            </div>
                            <p class="card-text">Test notification for new job fair event opportunities.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="job_fair_event">
                                <button type="submit" name="create_test" class="btn btn-warning btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Job Expiring -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-secondary text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h6 class="card-title mb-0">Job Posting Expiring</h6>
                            </div>
                            <p class="card-text">Test notification for when your job posting is about to expire.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="job_expiring">
                                <button type="submit" name="create_test" class="btn btn-secondary btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Update -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-dark text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <h6 class="card-title mb-0">System Update</h6>
                            </div>
                            <p class="card-text">Test notification for system updates and new features.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="system_update">
                                <button type="submit" name="create_test" class="btn btn-dark btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Registration Confirmed -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success text-white rounded-circle p-2 me-3">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h6 class="card-title mb-0">Registration Confirmed</h6>
                            </div>
                            <p class="card-text">Test notification for job fair registration confirmation.</p>
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="test_type" value="registration_confirmed">
                                <button type="submit" name="create_test" class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-plus"></i> Create Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> How to Test
                    </h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li><strong>Click any test button above</strong> to generate a sample notification.</li>
                        <li><strong>Check the notification dropdown</strong> in the header (bell icon) to see the new notification.</li>
                        <li><strong>Click on notifications in the dropdown</strong> to automatically delete them.</li>
                        <li><strong>Visit the full notifications page</strong> to see all notifications with advanced management features.</li>
                        <li><strong>Test different notification types</strong> to see how they appear and behave.</li>
                    </ol>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Tip:</strong> The notification system works exactly like Facebook notifications - click to delete, with smooth animations and real-time updates.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 
