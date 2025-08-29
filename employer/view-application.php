<?php
session_start();
require_once '../config/database.php';
require_once '../jobseeker/includes/jobseeker_notifications.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

// Check if application ID is provided
if (!isset($_GET['id'])) {
    header("Location: applications.php");
    exit();
}

$application_id = $_GET['id'];
$error = null;
$success = null;
$application = null;

try {
    // Get company ID
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new Exception("Please complete your company profile first.");
    }

    // Get application details with related information (only for company's jobs)
    $query = "SELECT a.*, 
              j.title as job_title, j.description as job_description, j.job_type as job_type, j.salary_range,
              c.company_name, c.industry, c.company_website,
              u.email as applicant_email,
              up.first_name, up.last_name, up.phone as applicant_phone, up.resume, up.profile_picture, up.address, up.bio, up.experience,
              GROUP_CONCAT(DISTINCT s.skill_name) as applicant_skills,
              GROUP_CONCAT(DISTINCT s2.skill_name) as required_skills,
              sm.match_score, sm.matching_skills, sm.missing_skills
              FROM applications a
              JOIN jobs j ON a.job_id = j.job_id
              JOIN companies c ON j.company_id = c.company_id
              JOIN users u ON a.jobseeker_id = u.user_id
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN jobseeker_skills js ON a.jobseeker_id = js.jobseeker_id
              LEFT JOIN skills s ON js.skill_id = s.skill_id
              LEFT JOIN job_skills js2 ON j.job_id = js2.job_id
              LEFT JOIN skills s2 ON js2.skill_id = s2.skill_id
              LEFT JOIN skill_matches sm ON a.application_id = sm.application_id
              WHERE a.application_id = ? AND j.company_id = ?
              GROUP BY a.application_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$application_id, $company['company_id']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        throw new Exception("Application not found or you don't have permission to view it.");
    }
    
    // Handle application actions
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_status' && isset($_POST['status'])) {
            $new_status = $_POST['status'];
            
            // Get jobseeker ID before updating
            $jobseeker_id = $application['jobseeker_id'];
            
            // Update application status
            $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
            $stmt->execute([$new_status, $application_id]);
            
            // Create notification for jobseeker
            notifyJobseekerApplicationUpdate(
                $conn, 
                $jobseeker_id, 
                $application_id,
                $application['job_title'], 
                $application['company_name'], 
                $new_status
            );
            
            $success = "Application status updated to " . ucfirst($new_status) . " successfully.";
            
            // Refresh application data
            $stmt = $conn->prepare($query);
            $stmt->execute([$application_id, $company['company_id']]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - Employer Dashboard</title>
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
            <h3>Employer Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="text-center mb-2 mt-1">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li><a href="jobs.php"><i class="fas fa-briefcase"></i><span>My Jobs</span></a></li>
            <li><a href="applications.php" class="active"><i class="fas fa-file-alt"></i><span>Applications</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i><span>Profile</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Application Details</h2>
                <a href="applications.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Applications
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($application): ?>
            <div class="row">
                <!-- Application Status Card -->
                <div class="col-md-12 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1">Application Status</h4>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        Submitted on <?php echo date('F d, Y', strtotime($application['application_date'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="badge bg-<?php 
                                        switch($application['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'reviewed': echo 'info'; break;
                                            case 'interviewed': echo 'dark'; break;
                                            case 'shortlisted': echo 'primary'; break;
                                            case 'rejected': echo 'danger'; break;
                                            case 'hired': echo 'success'; break;
                                            default: echo 'secondary';
                                        }
                                    ?> fs-5 px-3 py-2">
                                        <i class="fas fa-circle me-1"></i>
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Applicant Information -->
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user me-2 text-primary"></i>
                                Applicant Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-4">
                                <?php if (!empty($application['profile_picture']) && file_exists('../' . $application['profile_picture'])): ?>
                                    <div class="me-3">
                                        <img src="../<?php echo htmlspecialchars($application['profile_picture']); ?>" 
                                             alt="Profile Picture" 
                                             class="rounded-circle" 
                                             style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #e9ecef;">
                                    </div>
                                <?php else: ?>
                                    <div class="avatar-circle bg-primary text-white me-3">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h4>
                                    <p class="text-muted mb-0">Job Applicant</p>
                                </div>
                            </div>

                            <div class="contact-info">
                                <div class="mb-3">
                                    <label class="text-muted mb-1">Email Address</label>
                                    <p class="mb-0">
                                        <i class="fas fa-envelope me-2 text-primary"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($application['applicant_email']); ?>
                                        </a>
                                    </p>
                                </div>

                                <?php if (!empty($application['applicant_phone'])): ?>
                                <div class="mb-3">
                                    <label class="text-muted mb-1">Phone Number</label>
                                    <p class="mb-0">
                                        <i class="fas fa-phone me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($application['applicant_phone']); ?>
                                    </p>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($application['address'])): ?>
                                <div class="mb-3">
                                    <label class="text-muted mb-1">Address</label>
                                    <p class="mb-0">
                                        <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($application['address']); ?>
                                    </p>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($application['experience'])): ?>
                                <div class="mb-3">
                                    <label class="text-muted mb-1">Experience</label>
                                    <p class="mb-0">
                                        <i class="fas fa-briefcase me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($application['experience']); ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($application['bio'])): ?>
                            <div class="bio-section mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-user-edit me-2 text-primary"></i>
                                    Bio
                                </h6>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($application['bio'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="skills-section mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-tools me-2 text-primary"></i>
                                    Applicant Skills
                                </h6>
                                <?php if (!empty($application['applicant_skills'])): ?>
                                    <div class="skills-container">
                                        <?php 
                                        $skills = explode(',', $application['applicant_skills']);
                                        foreach ($skills as $skill): ?>
                                            <span class="badge bg-light text-dark border me-2 mb-2 px-3 py-2">
                                                <i class="fas fa-check-circle text-success me-1"></i>
                                                <?php echo htmlspecialchars($skill); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No skills listed</p>
                                <?php endif; ?>
                            </div>

                            <?php if (isset($application['match_score']) && $application['match_score'] !== null): ?>
                            <div class="match-score mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-percentage me-2 text-primary"></i>
                                    Skill Match Score
                                </h6>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-3" style="height: 25px;">
                                        <div class="progress-bar bg-success" 
                                             role="progressbar" 
                                             style="width: <?php echo $application['match_score']; ?>%"
                                             aria-valuenow="<?php echo $application['match_score']; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo round($application['match_score']); ?>%
                                        </div>
                                    </div>
                                    <span class="badge bg-success fs-6"><?php echo round($application['match_score']); ?>%</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Job Information -->
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-briefcase me-2 text-primary"></i>
                                Job Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="job-header mb-4">
                                <h4 class="mb-2"><?php echo htmlspecialchars($application['job_title']); ?></h4>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-building me-2"></i>
                                    <?php echo htmlspecialchars($application['company_name']); ?>
                                </p>
                            </div>

                            <div class="job-details">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted mb-1">Job Type</label>
                                        <p class="mb-0">
                                            <i class="fas fa-briefcase me-2 text-primary"></i>
                                            <?php echo ucfirst($application['job_type']); ?>
                                        </p>
                                    </div>
                                    <?php if (!empty($application['salary_range'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted mb-1">Salary Range</label>
                                        <p class="mb-0">
                                            <i class="fas fa-money-bill-wave me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($application['salary_range']); ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($application['cover_letter'])): ?>
                            <div class="cover-letter mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-file-alt me-2 text-primary"></i>
                                    Cover Letter
                                </h6>
                                <a href="<?php echo '../' . htmlspecialchars($application['cover_letter']); ?>" 
                                   class="btn btn-outline-primary" target="_blank">
                                    <i class="fas fa-download me-2"></i>
                                    Download Cover Letter
                                </a>
                            </div>
                            <?php endif; ?>

                            <div class="required-skills mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-tasks me-2 text-primary"></i>
                                    Required Skills
                                </h6>
                                <?php if (!empty($application['required_skills'])): ?>
                                    <div class="skills-container">
                                        <?php 
                                        $skills = explode(',', $application['required_skills']);
                                        foreach ($skills as $skill): ?>
                                            <span class="badge bg-primary me-2 mb-2 px-3 py-2">
                                                <i class="fas fa-star me-1"></i>
                                                <?php echo htmlspecialchars($skill); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No specific skills required</p>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($application['matching_skills'])): ?>
                            <div class="matching-skills mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-check-double me-2 text-success"></i>
                                    Matching Skills
                                </h6>
                                <div class="skills-container">
                                    <?php 
                                    $matching_skills = explode(',', $application['matching_skills']);
                                    foreach ($matching_skills as $skill): ?>
                                        <span class="badge bg-success me-2 mb-2 px-3 py-2">
                                            <i class="fas fa-check me-1"></i>
                                            <?php echo htmlspecialchars($skill); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($application['missing_skills'])): ?>
                            <div class="missing-skills mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-times-circle me-2 text-warning"></i>
                                    Missing Skills
                                </h6>
                                <div class="skills-container">
                                    <?php 
                                    $missing_skills = explode(',', $application['missing_skills']);
                                    foreach ($missing_skills as $skill): ?>
                                        <span class="badge bg-warning text-dark me-2 mb-2 px-3 py-2">
                                            <i class="fas fa-times me-1"></i>
                                            <?php echo htmlspecialchars($skill); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Job Description -->
                <div class="col-md-12 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-align-left me-2 text-primary"></i>
                                Job Description
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($application['job_description'])): ?>
                                <div class="job-description p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($application['job_description'])); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No job description available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Resume Viewer Section -->
                <div class="col-md-12 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-file-pdf me-2 text-primary"></i>
                                Resume & Documents
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($application['resume'])): ?>
                                <?php 
                                $resume_path = '../' . $application['resume'];
                                $resume_exists = file_exists($resume_path);
                                ?>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
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
                                <div class="text-center py-4">
                                    <i class="fas fa-file-pdf fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">No Resume Uploaded</h6>
                                    <p class="text-muted mb-0">The applicant has not uploaded a resume yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Application Actions -->
                <div class="col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cogs me-2 text-primary"></i>
                                Application Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-end">
                                <div class="dropdown">
                                    <button class="btn btn-primary dropdown-toggle" type="button" id="actionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-cog me-2"></i> Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionDropdown">
                                        <li class="dropdown-header">Change Status</li>
                                        <li>
                                            <form method="POST" class="dropdown-item-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="status" value="pending">
                                                <button type="submit" class="dropdown-item" 
                                                        onclick="return confirm('Change status to Pending?')">
                                                    <i class="fas fa-clock text-warning me-2"></i> Pending
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" class="dropdown-item-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="status" value="reviewed">
                                                <button type="submit" class="dropdown-item" 
                                                        onclick="return confirm('Change status to Reviewed?')">
                                                    <i class="fas fa-check text-info me-2"></i> Reviewed
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" class="dropdown-item-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="status" value="interviewed">
                                                <button type="submit" class="dropdown-item" 
                                                        onclick="return confirm('Change status to Interviewed?')">
                                                    <i class="fas fa-user-check text-dark me-2"></i> Interviewed
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" class="dropdown-item-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="status" value="shortlisted">
                                                <button type="submit" class="dropdown-item" 
                                                        onclick="return confirm('Change status to Shortlisted?')">
                                                    <i class="fas fa-list text-primary me-2"></i> Shortlisted
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" class="dropdown-item-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="status" value="hired">
                                                <button type="submit" class="dropdown-item" 
                                                        onclick="return confirm('Change status to Hired?')">
                                                    <i class="fas fa-user-check text-success me-2"></i> Hired
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" class="dropdown-item-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="dropdown-item text-danger" 
                                                        onclick="return confirm('Change status to Rejected?')">
                                                    <i class="fas fa-times me-2"></i> Rejected
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <style>
                .avatar-circle {
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .skills-container {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                }
                .badge {
                    font-weight: 500;
                }
                .card {
                    transition: transform 0.2s;
                }
                .card:hover {
                    transform: translateY(-5px);
                }
                .contact-info label {
                    font-size: 0.875rem;
                }
                .job-description {
                    white-space: pre-line;
                    line-height: 1.6;
                }
                .dropdown-header {
                    font-weight: 600;
                    color: #6c757d;
                    padding: 0.5rem 1rem;
                }
                .dropdown-item-form {
                    margin: 0;
                }
                .dropdown-item-form button {
                    width: 100%;
                    text-align: left;
                    border: none;
                    background: none;
                    padding: 0.5rem 1rem;
                }
                .dropdown-item-form button:hover {
                    background-color: #f8f9fa;
                }
                .dropdown-item {
                    cursor: pointer;
                }
                .dropdown-item:hover {
                    background-color: #f8f9fa;
                }
            </style>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 