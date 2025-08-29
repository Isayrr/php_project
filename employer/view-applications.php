<?php
session_start();
require_once '../config/database.php';
require_once '../jobseeker/includes/jobseeker_notifications.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'pending':
            return 'warning';
        case 'reviewed':
            return 'info';
        case 'interviewed':
            return 'dark';
        case 'shortlisted':
            return 'primary';
        case 'rejected':
            return 'danger';
        case 'hired':
            return 'success';
        default:
            return 'secondary';
    }
}

$error = null;
$success = null;

try {
    // Get company ID
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new Exception("Please complete your company profile first.");
    }

    // Get job ID from URL
    $job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

    // Verify job belongs to company
    $stmt = $conn->prepare("SELECT j.*, c.company_name 
                           FROM jobs j 
                           JOIN companies c ON j.company_id = c.company_id 
                           WHERE j.job_id = ? AND j.company_id = ?");
    $stmt->execute([$job_id, $company['company_id']]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("Invalid job ID or you don't have permission to view this job.");
    }

    // Handle application status update
    if (isset($_POST['action']) && isset($_POST['application_id'])) {
        $application_id = $_POST['application_id'];
        $action = $_POST['action'];

        // Verify application belongs to job
        $stmt = $conn->prepare("SELECT application_id FROM applications WHERE application_id = ? AND job_id = ?");
        $stmt->execute([$application_id, $job_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid application ID.");
        }

        if ($action === 'update_status') {
            $new_status = $_POST['status'];
            
            // Get application details for notification
            $stmt = $conn->prepare("SELECT a.jobseeker_id 
                                   FROM applications a 
                                   WHERE a.application_id = ?");
            $stmt->execute([$application_id]);
            $app_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update application status
            $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
            $stmt->execute([$new_status, $application_id]);
            
            // Create notification for jobseeker
            if ($app_details) {
                notifyJobseekerApplicationUpdate(
                    $conn, 
                    $app_details['jobseeker_id'], 
                    $application_id,
                    $job['title'], 
                    $job['company_name'], 
                    $new_status
                );
            }
            
            $success = "Application status updated successfully.";
        }
    }

    // Get all applications for the job
    $stmt = $conn->prepare("SELECT a.*, 
                           u.email as applicant_email,
                           CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
                           up.phone as applicant_phone,
                           sm.match_score, sm.matching_skills, sm.missing_skills
                           FROM applications a 
                           JOIN users u ON a.jobseeker_id = u.user_id 
                           LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                           LEFT JOIN skill_matches sm ON a.application_id = sm.application_id 
                           WHERE a.job_id = ? 
                           ORDER BY a.application_date DESC");
    $stmt->execute([$job_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Applications - Employer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .application-card {
            transition: transform 0.2s;
        }
        .application-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .skill-match {
            height: 5px;
            margin-top: 5px;
        }
        .sidebar, .sidebar-menu a {
            background: #1a252f !important;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #2c3e50 !important;
            color: #3498db !important;
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Applications for: <?php echo htmlspecialchars($job['title']); ?></h2>
                    <p class="text-muted mb-0">
                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?> |
                        <i class="fas fa-briefcase"></i> <?php echo ucfirst($job['job_type']); ?> |
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?>
                    </p>
                </div>
                <a href="jobs.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Jobs
                </a>
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

            <?php if (empty($applications)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No applications received for this job yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($applications as $application): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card application-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-1">
                                                <?php echo htmlspecialchars(($application['applicant_name'] ?? 
                                                    (($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? '')))); ?>
                                            </h5>
                                            <p class="text-muted mb-2"><?php echo htmlspecialchars($application['applicant_email']); ?></p>
                                            <?php if (!empty($application['applicant_phone'])): ?>
                                                <p class="text-muted mb-2">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($application['applicant_phone']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($application['status']); ?> status-badge">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                    </div>

                                    <?php if ($application['match_score'] !== null): ?>
                                    <div class="mt-3">
                                        <h6>Skill Match</h6>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $application['match_score']; ?>%"
                                                 aria-valuenow="<?php echo $application['match_score']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo round($application['match_score']); ?>%
                                            </div>
                                        </div>
                                        <?php if ($application['matching_skills']): ?>
                                            <p class="mb-1"><strong>Matching Skills:</strong></p>
                                            <div class="mb-2">
                                                <?php foreach (explode(',', $application['matching_skills']) as $skill): ?>
                                                    <span class="badge bg-success me-1"><?php echo htmlspecialchars($skill); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($application['missing_skills']): ?>
                                            <p class="mb-1"><strong>Missing Skills:</strong></p>
                                            <div>
                                                <?php foreach (explode(',', $application['missing_skills']) as $skill): ?>
                                                    <span class="badge bg-danger me-1"><?php echo htmlspecialchars($skill); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($application['cover_letter']): ?>
                                    <div class="mt-3">
                                        <h6>Cover Letter</h6>
                                        <p>
                                            <a href="<?php echo '../' . htmlspecialchars($application['cover_letter']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-download"></i> Download Cover Letter
                                            </a>
                                        </p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewApplication<?php echo $application['application_id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStatus<?php echo $application['application_id']; ?>">
                                                <i class="fas fa-edit"></i> Update Status
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- View Application Modal -->
                            <div class="modal fade" id="viewApplication<?php echo $application['application_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Application Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if ($application['cover_letter']): ?>
                                            <h6>Cover Letter</h6>
                                            <p>
                                                <a href="<?php echo '../' . htmlspecialchars($application['cover_letter']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download"></i> Download Cover Letter
                                                </a>
                                            </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($application['resume_path']): ?>
                                                <h6>Resume</h6>
                                                <a href="<?php echo htmlspecialchars($application['resume_path']); ?>" 
                                                   class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="fas fa-download"></i> Download Resume
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($application['matching_skills']): ?>
                                                <h6 class="mt-3">Matching Skills</h6>
                                                <p><?php echo htmlspecialchars($application['matching_skills']); ?></p>
                                            <?php endif; ?>

                                            <?php if ($application['missing_skills']): ?>
                                                <h6>Missing Skills</h6>
                                                <p><?php echo htmlspecialchars($application['missing_skills']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Status Modal -->
                            <div class="modal fade" id="updateStatus<?php echo $application['application_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Application Status</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                <input type="hidden" name="action" value="update_status">
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" name="status" required>
                                                        <option value="pending" <?php echo $application['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="reviewed" <?php echo $application['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                        <option value="interviewed" <?php echo $application['status'] === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                                        <option value="shortlisted" <?php echo $application['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                        <option value="rejected" <?php echo $application['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                        <option value="hired" <?php echo $application['status'] === 'hired' ? 'selected' : ''; ?>>Hired</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Status</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 
