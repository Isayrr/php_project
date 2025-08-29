<?php
session_start();
require_once '../config/database.php';
require_once '../jobseeker/includes/jobseeker_notifications.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
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

    // Handle application status update
    if (isset($_POST['action']) && isset($_POST['application_id'])) {
        $application_id = $_POST['application_id'];
        $action = $_POST['action'];

        // Verify application belongs to company's job
        $stmt = $conn->prepare("SELECT a.application_id 
                               FROM applications a 
                               JOIN jobs j ON a.job_id = j.job_id 
                               WHERE a.application_id = ? AND j.company_id = ?");
        $stmt->execute([$application_id, $company['company_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid application ID.");
        }

        if ($action === 'update_status') {
            $new_status = $_POST['status'];
            
            // Get application details for notification
            $stmt = $conn->prepare("SELECT a.jobseeker_id, j.title as job_title, c.company_name 
                                   FROM applications a 
                                   JOIN jobs j ON a.job_id = j.job_id 
                                   JOIN companies c ON j.company_id = c.company_id 
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
                    $app_details['job_title'], 
                    $app_details['company_name'], 
                    $new_status
                );
            }
            
            $success = "Application status updated successfully.";
        }
    }

    // Get all applications for company's jobs
    $stmt = $conn->prepare("SELECT a.*, j.title as job_title, j.job_type, j.salary_range as job_salary,
                           u.email as applicant_email,
                           CONCAT(IFNULL(up.first_name, ''), ' ', IFNULL(up.last_name, '')) as applicant_name,
                           up.first_name, up.last_name, up.phone as applicant_phone, up.address, up.bio,
                           up.experience, up.resume, up.profile_picture,
                           sm.match_score, sm.matching_skills, sm.missing_skills,
                           GROUP_CONCAT(DISTINCT s.skill_name) as applicant_skills
                           FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
                           JOIN users u ON a.jobseeker_id = u.user_id 
                           LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                           LEFT JOIN skill_matches sm ON a.application_id = sm.application_id
                           LEFT JOIN jobseeker_skills js ON a.jobseeker_id = js.jobseeker_id
                           LEFT JOIN skills s ON js.skill_id = s.skill_id
                           WHERE j.company_id = ? 
                           GROUP BY a.application_id
                           ORDER BY a.application_date DESC");
    $stmt->execute([$company['company_id']]);
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
    <title>Manage Applications - Employer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Updated professional design styles */
        .main-content {
            background-color: #f0f2f5;
            padding: 20px;
        }

        .application-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            background: #fff;
        }

        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .card-body {
            padding: 1.25rem;
            position: relative;
        }

        .status-badge {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            border-radius: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 2;
        }

        .application-header {
            margin-top: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .job-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            line-height: 1.3;
            padding-right: 5rem; /* space for status badge, avoid overlap */
            white-space: normal;
            overflow: visible;
            overflow-wrap: anywhere;
        }

        .applicant-info {
            margin-bottom: 1rem;
        }

        .applicant-name {
            font-size: 1rem;
            font-weight: 600;
            color: #34495e;
            margin-bottom: 0.25rem;
        }

        .contact-details {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.4;
        }
        .contact-details span{overflow-wrap:anywhere;}

        .contact-details i {
            width: 16px;
            margin-right: 8px;
            color: #95a5a6;
        }

        .contact-item {
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
        }

        .job-details {
            background: #f8f9fa;
            padding: 0.6rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border-left: 3px solid #3498db;
        }

        .job-detail-item {
            font-size: 0.78rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
        }

        .job-detail-item:last-child {
            margin-bottom: 0;
        }

        .job-detail-item i {
            width: 14px;
            margin-right: 6px;
            color: #7f8c8d;
        }

        .salary {
            font-weight: 700;
            color: #27ae60;
        }

        .skill-match-section {
            margin-bottom: 1rem;
        }

        .skill-match {
            height: 10px;
            margin-top: 0.5rem;
            border-radius: 5px;
            background-color: #ecf0f1;
            overflow: hidden;
        }

        .skill-match .progress-bar {
            border-radius: 5px;
        }

        .skill-match-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .skill-match-percentage {
            font-size: 0.75rem;
            color: #27ae60;
            font-weight: 600;
            margin-top: 0.3rem;
        }

        .application-date {
            flex-grow: 1;
        }

        .application-date small {
            font-size: 0.75rem;
            color: #95a5a6;
        }

        .application-date i {
            color: #bdc3c7;
        }

        .action-buttons {
            flex-shrink: 0;
        }

        .action-buttons .btn {
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            transition: all 0.2s ease;
        }

        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* Card body flexbox for proper footer positioning */
        .application-card .card-body {
            display: flex;
            flex-direction: column;
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            .application-card {
                margin-bottom: 1rem;
            }
            
            .card-body {
                padding: 1.25rem;
            }
            
            .status-badge {
                top: 0.75rem;
                right: 0.75rem;
                font-size: 0.7rem;
                padding: 0.3rem 0.6rem;
            }
            
            .job-title {
                font-size: 1rem;
                margin-right: 4rem; /* Make room for status badge */
            }
            
            .applicant-info img {
                width: 45px !important;
                height: 45px !important;
            }
            
            .contact-details {
                font-size: 0.8rem;
            }
            
            .job-details {
                padding: 0.5rem;
            }
            
            .action-buttons .btn {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
            
            .action-buttons .btn .me-1 {
                margin-right: 0.25rem !important;
            }
        }

        @media (max-width: 576px) {
            .applicant-info {
                flex-direction: column;
                text-align: center;
            }
            
            .applicant-info img {
                margin: 0 auto 0.75rem auto !important;
            }
            
            .contact-item {
                justify-content: center;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .application-date {
                text-align: center;
            }
            
            .action-buttons {
                text-align: center;
            }
        }

        /* Modal styling improvements */
        .modal-content {
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: none;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 12px 12px 0 0;
        }

        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 12px 12px;
        }

        .modal-xl {
            max-width: 1200px;
        }

        .contact-info .mb-2, .contact-info .mb-3 {
            display: flex;
            align-items: center;
        }

        .skills-container {
            max-height: 150px;
            overflow-y: auto;
        }

        .progress {
            border-radius: 10px;
            overflow: hidden;
        }

        .badge {
            font-size: 0.75rem;
        }

        /* Modal responsive improvements */
        @media (max-width: 768px) {
            .modal-body .row {
                flex-direction: column;
            }
            
            .col-md-4 {
                border-bottom: 1px solid #dee2e6;
                border-right: none !important;
            }
            
            .skills-container {
                max-height: 100px;
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
                <a href="jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <span>Manage Jobs</span>
                </a>
            </li>
            <li>
                <a href="applications.php" class="active">
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
            <h2 class="mb-4">Manage Applications</h2>

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
                    <i class="fas fa-info-circle"></i> No applications received yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($applications as $application): ?>
                        <div class="col-sm-6 col-lg-4 mb-3">
                            <div class="card application-card h-100">
                                <div class="card-body">
                                    <!-- Status Badge -->
                                    <span class="badge <?php 
                                        switch($application['status']) {
                                            case 'pending':
                                                echo 'bg-warning text-dark';
                                                break;
                                            case 'reviewed':
                                                echo 'bg-info text-white';
                                                break;
                                            case 'interviewed':
                                                echo 'bg-dark text-white';
                                                break;
                                            case 'shortlisted':
                                                echo 'bg-primary text-white';
                                                break;
                                            case 'rejected':
                                                echo 'bg-danger text-white';
                                                break;
                                            case 'hired':
                                                echo 'bg-success text-white';
                                                break;
                                            default:
                                                echo 'bg-secondary text-white';
                                        }
                                    ?> status-badge">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>

                                    <!-- Application Header -->
                                    <div class="application-header">
                                        <h6 class="job-title"><?php echo htmlspecialchars($application['job_title']); ?></h6>
                                    </div>

                                    <!-- Applicant Profile Section -->
                                    <div class="d-flex align-items-start applicant-info">
                                        <?php
                                        $profile_picture = !empty($application['profile_picture']) ? '../' . $application['profile_picture'] : '../assets/images/default-user.png';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                                             alt="Profile Picture" 
                                             class="rounded-circle me-3" 
                                             style="width: 55px; height: 55px; object-fit: cover; border: 2px solid #e9ecef; flex-shrink: 0;">
                                        <div class="flex-grow-1">
                                            <div class="applicant-name"><?php echo htmlspecialchars($application['applicant_name']); ?></div>
                                            <div class="contact-details">
                                                <div class="contact-item">
                                                    <i class="fas fa-envelope"></i>
                                                    <span><?php echo htmlspecialchars($application['applicant_email']); ?></span>
                                                </div>
                                                <?php if ($application['applicant_phone']): ?>
                                                <div class="contact-item">
                                                    <i class="fas fa-phone"></i>
                                                    <span><?php echo htmlspecialchars($application['applicant_phone']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Job Details Section -->
                                    <div class="job-details">
                                        <div class="job-detail-item">
                                            <i class="fas fa-briefcase"></i>
                                            <span><?php echo ucfirst($application['job_type']); ?></span>
                                        </div>
                                        <div class="job-detail-item">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span class="salary"><?php echo !empty($application['job_salary']) ? '₱' . htmlspecialchars($application['job_salary']) : 'Not specified'; ?></span>
                                        </div>
                                    </div>

                                    <!-- Skill Match Section -->
                                    <?php if ($application['match_score']): ?>
                                        <div class="skill-match-section">
                                            <div class="skill-match-label">Skill Match</div>
                                            <div class="progress skill-match">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $application['match_score']; ?>%">
                                                </div>
                                            </div>
                                            <div class="skill-match-percentage">
                                                <?php echo number_format($application['match_score'], 1); ?>% match
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Application Footer -->
                                    <div class="d-flex justify-content-between align-items-center mt-auto pt-3">
                                        <div class="application-date">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                Applied: <?php echo date('M d, Y', strtotime($application['application_date'])); ?>
                                            </small>
                                        </div>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewApplication<?php echo $application['application_id']; ?>"
                                                    title="View Full Details">
                                                <i class="fas fa-eye me-1"></i> View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStatus<?php echo $application['application_id']; ?>"
                                                    title="Update Status">
                                                <i class="fas fa-edit me-1"></i> Status
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- View Application Modal -->
                            <div class="modal fade" id="viewApplication<?php echo $application['application_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">
                                                <i class="fas fa-user-circle me-2"></i>
                                                Job Seeker Profile & Application Details
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-0">
                                            <div class="row g-0">
                                                <!-- Job Seeker Profile Section -->
                                                <div class="col-md-4 bg-light border-end">
                                                    <div class="p-4">
                                                        <div class="text-center mb-4">
                                                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                                                                 alt="Profile Picture" 
                                                                 class="rounded-circle mb-3" 
                                                                 style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #007bff;">
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($application['applicant_name']); ?></h5>
                                                            <p class="text-muted mb-0">Job Applicant</p>
                                                        </div>

                                                        <div class="contact-info">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="fas fa-address-card me-2"></i>Contact Information
                                                            </h6>
                                                            <div class="mb-2">
                                                                <i class="fas fa-envelope text-muted me-2"></i>
                                                                <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($application['applicant_email']); ?>
                                                                </a>
                                                            </div>
                                                            <?php if ($application['applicant_phone']): ?>
                                                            <div class="mb-2">
                                                                <i class="fas fa-phone text-muted me-2"></i>
                                                                <?php echo htmlspecialchars($application['applicant_phone']); ?>
                                                            </div>
                                                            <?php endif; ?>
                                                            <?php if ($application['address']): ?>
                                                            <div class="mb-3">
                                                                <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                                                <?php echo htmlspecialchars($application['address']); ?>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if ($application['experience']): ?>
                                                        <div class="experience-info">
                                                            <h6 class="text-primary mb-2">
                                                                <i class="fas fa-briefcase me-2"></i>Experience Level
                                                            </h6>
                                                            <p class="mb-3"><?php echo htmlspecialchars($application['experience']); ?></p>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if ($application['applicant_skills']): ?>
                                                        <div class="skills-info">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="fas fa-tools me-2"></i>Skills
                                                            </h6>
                                                            <div class="skills-container">
                                                                <?php 
                                                                $skills = explode(',', $application['applicant_skills']);
                                                                foreach ($skills as $skill): ?>
                                                                    <span class="badge bg-light text-dark border me-1 mb-1 px-2 py-1">
                                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                                        <?php echo htmlspecialchars(trim($skill)); ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Application Details Section -->
                                                <div class="col-md-8">
                                                    <div class="p-4">
                                                        <!-- Application Status -->
                                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                                            <h6 class="text-primary mb-0">
                                                                <i class="fas fa-file-alt me-2"></i>Application Status
                                                            </h6>
                                                            <span class="badge <?php 
                                                                switch($application['status']) {
                                                                    case 'pending': echo 'bg-warning'; break;
                                                                    case 'reviewed': echo 'bg-info'; break;
                                                                    case 'interviewed': echo 'bg-dark'; break;
                                                                    case 'shortlisted': echo 'bg-primary'; break;
                                                                    case 'rejected': echo 'bg-danger'; break;
                                                                    case 'hired': echo 'bg-success'; break;
                                                                    default: echo 'bg-secondary';
                                                                }
                                                            ?> fs-6 px-3 py-2">
                                                                <?php echo ucfirst($application['status']); ?>
                                                            </span>
                                                        </div>

                                                        <!-- Job Details -->
                                                        <div class="job-details mb-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="fas fa-briefcase me-2"></i>Job Applied For
                                                            </h6>
                                                            <div class="bg-light p-3 rounded">
                                                                <h5 class="mb-2"><?php echo htmlspecialchars($application['job_title']); ?></h5>
                                                                <div class="row">
                                                                    <div class="col-sm-6">
                                                                        <small class="text-muted">Job Type:</small><br>
                                                                        <span class="badge bg-secondary"><?php echo ucfirst($application['job_type']); ?></span>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <small class="text-muted">Salary Range:</small><br>
                                                                        <strong class="text-success">
                                                                            <?php echo !empty($application['job_salary']) ? '₱' . htmlspecialchars($application['job_salary']) : 'Not specified'; ?>
                                                                        </strong>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-2">
                                                                    <small class="text-muted">Applied on:</small><br>
                                                                    <span><?php echo date('F d, Y \a\t g:i A', strtotime($application['application_date'])); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <?php if ($application['match_score']): ?>
                                                        <!-- Skill Match -->
                                                        <div class="skill-match-section mb-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="fas fa-chart-line me-2"></i>Skill Match Analysis
                                                            </h6>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="progress mb-2" style="height: 20px;">
                                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                                             style="width: <?php echo $application['match_score']; ?>%">
                                                                            <?php echo number_format($application['match_score'], 1); ?>%
                                                                        </div>
                                                                    </div>
                                                                    <small class="text-muted">Overall Match Score</small>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <?php if ($application['matching_skills']): ?>
                                                                    <small class="text-success">
                                                                        <i class="fas fa-check me-1"></i>
                                                                        Matching: <?php echo htmlspecialchars($application['matching_skills']); ?>
                                                                    </small><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($application['missing_skills']): ?>
                                                                    <small class="text-warning">
                                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                                        Missing: <?php echo htmlspecialchars($application['missing_skills']); ?>
                                                                    </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <!-- Bio Section -->
                                                        <?php if ($application['bio']): ?>
                                                        <div class="bio-section mb-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="fas fa-user-edit me-2"></i>About the Applicant
                                                            </h6>
                                                            <div class="bg-light p-3 rounded">
                                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($application['bio'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <!-- Resume & Documents -->
                                                        <div class="documents-section mb-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="fas fa-file-pdf me-2"></i>Resume & Documents
                                                            </h6>
                                                            
                                                            <?php if (!empty($application['resume'])): ?>
                                                                <?php 
                                                                $resume_path = '../' . $application['resume'];
                                                                $resume_exists = file_exists($resume_path);
                                                                ?>
                                                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded mb-3">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="me-3">
                                                                            <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                                                        </div>
                                                                        <div>
                                                                            <h6 class="mb-1">Resume Document</h6>
                                                                            <p class="text-muted mb-0">
                                                                                <small>
                                                                                    <?php if ($resume_exists): ?>
                                                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                                                        File: <?php echo htmlspecialchars(basename($application['resume'])); ?>
                                                                                    <?php else: ?>
                                                                                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                                                                        File not found: <?php echo htmlspecialchars(basename($application['resume'])); ?>
                                                                                    <?php endif; ?>
                                                                                </small>
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="d-flex gap-2">
                                                                        <?php if ($resume_exists): ?>
                                                                            <a href="<?php echo htmlspecialchars($resume_path); ?>" 
                                                                               target="_blank" 
                                                                               class="btn btn-outline-primary btn-sm">
                                                                                <i class="fas fa-eye me-1"></i> View
                                                                            </a>
                                                                            <a href="<?php echo htmlspecialchars($resume_path); ?>" 
                                                                               download 
                                                                               class="btn btn-primary btn-sm">
                                                                                <i class="fas fa-download me-1"></i> Download
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <button class="btn btn-outline-secondary btn-sm" disabled>
                                                                                <i class="fas fa-times me-1"></i> Not Available
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="text-center py-4 bg-light rounded">
                                                                    <i class="fas fa-file-pdf fa-3x text-muted mb-3"></i>
                                                                    <h6 class="text-muted">No Resume Uploaded</h6>
                                                                    <p class="text-muted mb-0">The applicant has not uploaded a resume yet.</p>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($application['cover_letter'])): ?>
                                                            <div class="cover-letter-section">
                                                                <h6 class="text-primary mb-3 mt-4">
                                                                    <i class="fas fa-file-alt me-2"></i>Cover Letter
                                                                </h6>
                                                                <div class="bg-light p-3 rounded">
                                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?></p>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="fas fa-times me-1"></i> Close
                                            </button>
                                            <button type="button" class="btn btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStatus<?php echo $application['application_id']; ?>"
                                                    data-bs-dismiss="modal">
                                                <i class="fas fa-edit me-1"></i> Update Status
                                            </button>
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
