<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;

// Check for success message from URL parameter (from post-job.php redirect)
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

try {
    // Get company ID
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new Exception("Please complete your company profile first.");
    }

    // Handle job status update
    if (isset($_POST['action']) && isset($_POST['job_id'])) {
        $job_id = $_POST['job_id'];
        $action = $_POST['action'];

        // Verify job belongs to company
        $stmt = $conn->prepare("SELECT job_id FROM jobs WHERE job_id = ? AND company_id = ?");
        $stmt->execute([$job_id, $company['company_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid job ID.");
        }

        if ($action === 'delete') {
            // Delete job
            $stmt = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
            $stmt->execute([$job_id]);
            $success = "Job deleted successfully.";
        } elseif ($action === 'toggle_status') {
            // Toggle job status
            $stmt = $conn->prepare("UPDATE jobs SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE job_id = ?");
            $stmt->execute([$job_id]);
            $success = "Job status updated successfully.";
        }
    }

    // Get all jobs for the company
    $stmt = $conn->prepare("SELECT j.*, 
                           (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count 
                           FROM jobs j 
                           WHERE j.company_id = ? 
                           ORDER BY j.posted_date DESC");
    $stmt->execute([$company['company_id']]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Employer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Enhanced table design */
        .main-content {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 2rem;
            min-height: 100vh;
        }

        .jobs-table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            overflow-y: visible;
            position: relative;
        }

        .jobs-table-container::-webkit-scrollbar {
            height: 8px;
        }

        .jobs-table-container::-webkit-scrollbar-track {
            background: #f1f3f4;
            border-radius: 4px;
        }

        .jobs-table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }

        .jobs-table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }

        .jobs-table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            min-width: 1000px;
        }

        .jobs-table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1.25rem 1rem;
            border: none;
            font-size: 0.85rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .jobs-table thead th:first-child {
            border-top-left-radius: 12px;
        }

        .jobs-table thead th:last-child {
            border-top-right-radius: 12px;
        }

        .jobs-table tbody tr {
            background: white;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f3f4;
        }

        .jobs-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
            transform: scale(1.01);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .jobs-table tbody tr:last-child {
            border-bottom: none;
        }

        .jobs-table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }

        .jobs-table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }

        .jobs-table td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            border: none;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .jobs-table td:last-child {
            width: 160px;
            min-width: 160px;
            white-space: nowrap;
        }

        .jobs-table td:first-child {
            white-space: normal;
            max-width: 200px;
        }

        .job-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0;
            display: block;
        }

        .job-details {
            line-height: 1.6;
        }

        .job-details .detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .job-details .detail-item:last-child {
            margin-bottom: 0;
        }

        .job-details .detail-item i {
            width: 16px;
            margin-right: 0.5rem;
            color: #667eea;
            font-size: 0.9rem;
        }

        .salary-amount {
            font-weight: 700;
            color: #28a745;
            font-size: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status-badge.active {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }

        .status-badge.inactive {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4);
        }

        .application-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 100px;
            justify-content: center;
        }

        .posted-date {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: nowrap;
            min-width: 140px;
        }

        .action-buttons .btn {
            border-radius: 8px;
            padding: 0.6rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border-width: 2px;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        .btn-outline-warning:hover {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            border-color: #ffc107;
        }

        .btn-outline-danger:hover {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-color: #dc3545;
        }

        /* Enhanced page header */
        .page-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        /* Sidebar styling updates */
        .sidebar, .sidebar-menu a {
            background: #1a252f !important;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #2c3e50 !important;
            color: #3498db !important;
        }

        /* Responsive design for table */
        @media (max-width: 1200px) {
            .jobs-table-container {
                padding: 1.5rem;
            }
            
            .jobs-table td, .jobs-table th {
                padding: 1rem 0.75rem;
            }

            .jobs-table {
                min-width: 1200px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .page-title {
                font-size: 2rem;
            }

            .jobs-table-container {
                padding: 1rem;
                overflow-x: auto;
                border-radius: 12px;
            }

            .jobs-table {
                min-width: 1200px;
            }

            .jobs-table td, .jobs-table th {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            .action-buttons {
                gap: 0.5rem;
                min-width: 120px;
            }

            .action-buttons .btn {
                width: 35px;
                height: 35px;
                font-size: 0.8rem;
                padding: 0.5rem;
            }

            .job-title {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .jobs-table {
                min-width: 1200px;
            }
            
            .jobs-table-container {
                padding: 0.75rem;
            }

            .action-buttons {
                gap: 0.4rem;
                min-width: 110px;
            }

            .action-buttons .btn {
                width: 32px;
                height: 32px;
                font-size: 0.75rem;
            }
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
                <a href="jobs.php" class="active">
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
            <!-- Enhanced Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Manage Jobs</h1>
                    <p class="text-muted mb-0">Create, edit, and manage your job postings</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postJobModal">
                    <i class="fas fa-plus me-2"></i> Post New Job
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($jobs)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You haven't posted any jobs yet. 
                    <button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#postJobModal">Post your first job</button>.
                </div>
            <?php else: ?>
                <div class="alert alert-info d-md-none mb-3">
                    <i class="fas fa-arrows-alt-h"></i> Scroll horizontally to view all job details and actions
                </div>
                <div class="jobs-table-container">
                    <table class="table jobs-table">
                        <thead>
                            <tr>
                                <th scope="col">Job Information</th>
                                <th scope="col">Job Details</th>
                                <th scope="col">Salary</th>
                                <th scope="col">Applications</th>
                                <th scope="col">Status</th>
                                <th scope="col">Posted Date</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td>
                                        <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    </td>
                                    <td>
                                        <div class="job-details">
                                            <div class="detail-item">
                                                <i class="fas fa-briefcase"></i>
                                                <span><?php echo htmlspecialchars($job['job_type']); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($job['location']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($job['salary_range'])): ?>
                                            <span class="salary-amount">₱<?php echo htmlspecialchars($job['salary_range']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="application-count">
                                            <i class="fas fa-file-alt"></i>
                                            <span><?php echo $job['application_count']; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $job['status']; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="posted-date">
                                            <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit-job.php?id=<?php echo $job['job_id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="Edit Job">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to <?php echo $job['status'] === 'active' ? 'deactivate' : 'activate'; ?> this job?');">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <button type="submit" class="btn btn-outline-warning"
                                                        title="<?php echo $job['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Job">
                                                    <i class="fas fa-<?php echo $job['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this job? This action cannot be undone.');">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-outline-danger"
                                                        title="Delete Job">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Post Job Modal -->
    <div class="modal fade" id="postJobModal" tabindex="-1" aria-labelledby="postJobModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="postJobModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Post New Job
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="postJobForm" method="POST" action="post-job.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="job_title" class="form-label">Job Title *</label>
                                <input type="text" class="form-control" id="job_title" name="job_title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="job_type" class="form-label">Job Type *</label>
                                <select class="form-select" id="job_type" name="job_type" required>
                                    <option value="">Select Job Type</option>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Internship">Internship</option>
                                    <option value="Temporary">Temporary</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="industry" class="form-label">Industry *</label>
                                <input type="text" class="form-control" id="industry" name="industry" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="salary" class="form-label">Salary Range</label>
                                <input type="text" class="form-control" id="salary" name="salary" placeholder="e.g., ₱15,000 - ₱25,000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vacancies" class="form-label">Number of Vacancies</label>
                                <input type="number" class="form-control" id="vacancies" name="vacancies" value="1" min="1">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="job_description" class="form-label">Job Description *</label>
                            <textarea class="form-control" id="job_description" name="job_description" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="requirements" class="form-label">Requirements</label>
                            <textarea class="form-control" id="requirements" name="requirements" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="deadline" class="form-label">Application Deadline</label>
                            <input type="date" class="form-control" id="deadline" name="deadline">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="postJobForm" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Post Job
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 
