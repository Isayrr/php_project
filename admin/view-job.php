<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: jobs.php");
    exit();
}

$job_id = (int)$_GET['id'];
$error = null;
$job = null;
$job_skills = [];
$applications = [];
$company = null;

try {
    // Get job details with company information
    $stmt = $conn->prepare("
        SELECT j.*, c.*, 
               jc.category_name,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as total_applications,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.status = 'pending') as pending_applications,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.status = 'shortlisted') as shortlisted_applications,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.status = 'hired') as hired_applications
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        LEFT JOIN job_categories jc ON j.category_id = jc.category_id
        WHERE j.job_id = ?
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        $error = "Job not found.";
    } else {
        // Get skills required for this job
        $stmt = $conn->prepare("
            SELECT s.skill_name, js.required_level
            FROM job_skills js
            JOIN skills s ON js.skill_id = s.skill_id
            WHERE js.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent applications
        $stmt = $conn->prepare("
            SELECT a.*, 
                   u.email as applicant_email,
                   CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
                   up.phone as applicant_phone
            FROM applications a
            JOIN users u ON a.jobseeker_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE a.job_id = ?
            ORDER BY a.application_date DESC
            LIMIT 5
        ");
        $stmt->execute([$job_id]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Job Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <style>
        .modal-xl {
            max-width: 90%;
        }
        .job-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            background: white;
            border-radius: 8px;
            padding: 8px;
        }
        /* Map legacy .detail-card to modern look for any remaining uses */
        .detail-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: none;
        }
        .skill-badge {
            background-color: #e9f7fe;
            color: #3498db;
            border: 1px solid #3498db;
            padding: 4px 12px;
            border-radius: 15px;
            margin: 3px;
            display: inline-block;
            font-size: 0.9rem;
        }
        .modal-body {
            max-height: 80vh;
            overflow-y: auto;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            color: #3498db;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="modal fade" id="jobDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-modern"><?php echo $error; ?></div>
                    <?php elseif ($job): ?>
                        <!-- Job Header -->
                        <div class="job-header">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center">
                                    <?php if ($job['company_logo']): ?>
                                        <img src="../uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                             alt="<?php echo htmlspecialchars($job['company_name']); ?>" 
                                             class="company-logo">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-10">
                                    <h4 class="mb-2"><?php echo htmlspecialchars($job['title']); ?></h4>
                                    <h5 class="mb-3"><?php echo htmlspecialchars($job['company_name']); ?></h5>
                                    <div class="d-flex gap-3">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                        <span><i class="fas fa-briefcase"></i> <?php echo ucfirst($job['job_type']); ?></span>
                                        <span><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                        <span><i class="fas fa-calendar"></i> Posted: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs mb-3" id="jobTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="details-tab" data-bs-toggle="tab" href="#details" role="tab">Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="applications-tab" data-bs-toggle="tab" href="#applications" role="tab">
                                    Applications
                                    <span class="badge bg-primary ms-1"><?php echo $job['total_applications']; ?></span>
                                </a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="jobTabsContent">
                            <!-- Details Tab -->
                            <div class="tab-pane fade show active" id="details" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-8">
                                        <!-- Job Description -->
                                        <div class="modern-card p-3">
                                            <h5 class="mb-3">Job Description</h5>
                                            <div class="job-description">
                                                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                            </div>
                                        </div>

                                        <!-- Requirements -->
                                        <div class="modern-card p-3">
                                            <h5 class="mb-3">Requirements</h5>
                                            <div class="requirements">
                                                <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                            </div>
                                        </div>

                                        <!-- Required Skills -->
                                        <?php if (!empty($job_skills)): ?>
                                        <div class="modern-card p-3">
                                            <h5 class="mb-3">Required Skills</h5>
                                            <div class="skills-container">
                                                <?php foreach ($job_skills as $skill): ?>
                                                    <span class="skill-badge">
                                                        <?php echo htmlspecialchars($skill['skill_name']); ?>
                                                        <small class="text-muted">(<?php echo ucfirst($skill['required_level']); ?>)</small>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- Job Status -->
                                        <div class="modern-card p-3">
                                            <h5 class="mb-3">Job Status</h5>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Status:</span>
                                                <span class="badge bg-<?php echo $job['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($job['status']); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Category:</span>
                                                <span><?php echo htmlspecialchars($job['category_name'] ?? 'Not specified'); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Industry:</span>
                                                <span><?php echo htmlspecialchars($job['industry'] ?? 'Not specified'); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Deadline:</span>
                                                <span><?php echo $job['deadline_date'] ? date('M d, Y', strtotime($job['deadline_date'])) : 'No deadline'; ?></span>
                                            </div>
                                        </div>

                                        <!-- Application Statistics -->
                                        <div class="modern-card p-3">
                                            <h5 class="mb-3">Application Statistics</h5>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Total:</span>
                                                <span class="badge bg-primary"><?php echo $job['total_applications']; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Pending:</span>
                                                <span class="badge bg-warning"><?php echo $job['pending_applications']; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Shortlisted:</span>
                                                <span class="badge bg-info"><?php echo $job['shortlisted_applications']; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Hired:</span>
                                                <span class="badge bg-success"><?php echo $job['hired_applications']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Applications Tab -->
                            <div class="tab-pane fade" id="applications" role="tabpanel">
                                <?php if (!empty($applications)): ?>
                                    <div class="list-group">
                                        <?php foreach ($applications as $application): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($application['applicant_name']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($application['applicant_email']); ?></small>
                                                    </div>
                                                    <span class="badge bg-<?php 
                                                        switch($application['status']) {
                                                            case 'pending': echo 'warning'; break;
                                                            case 'shortlisted': echo 'info'; break;
                                                            case 'hired': echo 'success'; break;
                                                            case 'rejected': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($application['status']); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    Applied: <?php echo date('M d, Y', strtotime($application['application_date'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3">
                                        <a href="applications.php?job_id=<?php echo $job_id; ?>" class="btn btn-primary btn-sm w-100">
                                            View All Applications
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info alert-modern">No applications yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="window.location.href='jobs.php'">Close</button>
                    <a href="edit-job.php?id=<?php echo $job_id; ?>" class="btn btn-modern btn-modern-primary">
                        <i class="fas fa-edit"></i> Edit Job
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show the modal when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            var jobDetailsModal = new bootstrap.Modal(document.getElementById('jobDetailsModal'));
            jobDetailsModal.show();

            // Handle modal close
            document.getElementById('jobDetailsModal').addEventListener('hidden.bs.modal', function () {
                window.location.href = 'jobs.php';
            });
        });
    </script>
</body>
</html> 