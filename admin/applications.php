<?php
session_start();
require_once '../config/database.php';
require_once '../jobseeker/includes/jobseeker_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$applications = [];
$error = null;
$success = null;

// Handle application actions
if (isset($_POST['action']) && isset($_POST['application_id'])) {
    try {
        $application_id = $_POST['application_id'];
        $action = $_POST['action'];
        
        if ($action === 'update_status' && isset($_POST['status'])) {
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
            
            $success = "Application status updated to " . ucfirst($new_status) . " successfully.";
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM applications WHERE application_id = ?");
            $stmt->execute([$application_id]);
            $success = "Application deleted successfully.";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$job_filter = isset($_GET['job']) ? $_GET['job'] : '';
$company_filter = isset($_GET['company']) ? $_GET['company'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'application_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;

try {
    // Build the query
    $query = "SELECT a.*, 
              j.title as job_title, j.job_type as job_type,
              c.company_name,
              u.email as applicant_email,
              up.first_name, up.last_name, up.resume, 
              js.skills as applicant_skills
              FROM applications a
              JOIN jobs j ON a.job_id = j.job_id
              JOIN companies c ON j.company_id = c.company_id
              JOIN users u ON a.jobseeker_id = u.user_id
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN (
                  SELECT js.jobseeker_id as user_id, GROUP_CONCAT(s.skill_name) as skills 
                  FROM jobseeker_skills js
                  JOIN skills s ON js.skill_id = s.skill_id 
                  GROUP BY js.jobseeker_id
              ) js ON a.jobseeker_id = js.user_id";
    $count_query = "SELECT COUNT(*) FROM applications a
                    JOIN jobs j ON a.job_id = j.job_id
                    JOIN companies c ON j.company_id = c.company_id
                    JOIN users u ON a.jobseeker_id = u.user_id";
    $params = [];
    
    if ($search) {
        $query .= " WHERE (j.title LIKE ? OR c.company_name LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR u.email LIKE ?)";
        $count_query .= " WHERE (j.title LIKE ? OR c.company_name LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    } else {
        $query .= " WHERE 1=1";
        $count_query .= " WHERE 1=1";
    }
    
    if ($status_filter) {
        $query .= " AND a.status = ?";
        $count_query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    if ($job_filter) {
        $query .= " AND a.job_id = ?";
        $count_query .= " AND a.job_id = ?";
        $params[] = $job_filter;
    }
    
    if ($company_filter) {
        $query .= " AND j.company_id = ?";
        $count_query .= " AND j.company_id = ?";
        $params[] = $company_filter;
    }
    
    // Get total count for pagination
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_applications = $stmt->fetchColumn();
    $total_pages = ceil($total_applications / $per_page);
    
    // Add sorting and pagination
    $valid_sort_columns = ['application_date', 'job_title', 'company_name', 'status'];
    $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'application_date';
    $sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
    
    $query .= " ORDER BY $sort_by $sort_order";
    $query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page);
    
    // Execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get jobs for filter
    $stmt = $conn->query("SELECT job_id, title FROM jobs ORDER BY title");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for filter
    $stmt = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $total_pages = 0; // Initialize total_pages in case of error
}

// Initialize total_pages if not set
if (!isset($total_pages)) {
    $total_pages = 0;
}

// Function to generate sort URL
function getSortUrl($column) {
    global $sort_by, $sort_order, $search, $status_filter, $job_filter, $company_filter;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'DESC' : 'ASC';
    $params = [
        'sort' => $column,
        'order' => $new_order,
        'search' => $search,
        'status' => $status_filter,
        'job' => $job_filter,
        'company' => $company_filter
    ];
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <style>
        /* Applications-specific modern enhancements */
        .status-badge {
            transition: all 0.3s ease;
        }
        
        .status-badge:hover {
            transform: scale(1.05);
        }
        
        .application-details {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .skill-tag {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            margin: 0.125rem;
            display: inline-block;
        }

        /* Ensure dropdowns are anchored and not sticky while scrolling */
        .dropdown-menu {
            position: absolute !important;
            transform: none !important;
            will-change: auto !important;
        }
        .table { overflow: visible; }
        .table-responsive, .modern-table-container { overflow: visible !important; }
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
                <a href="placements.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>Graduate Placements</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
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
    <div class="main-content page-transition">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <!-- Modern Page Header -->
            <div class="page-header" data-aos="fade-down">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center position-relative">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-file-alt me-3"></i>
                                Application Management
                            </h1>
                            <p class="page-subtitle">Monitor and manage all job applications</p>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-center">
                                <h3 class="text-white mb-0"><?php echo $total_applications; ?></h3>
                                <small class="opacity-75">Total Applications</small>
                            </div>
                            <button type="button" class="btn btn-modern" onclick="exportApplications()">
                                <i class="fas fa-download me-2"></i> Export Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-modern" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-modern" data-aos="fade-up">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            

            
            <!-- Modern Filter Section -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control form-control-modern" name="search" 
                                       placeholder="Search applications..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-control-modern" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="interviewed" <?php echo $status_filter === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                <option value="shortlisted" <?php echo $status_filter === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="hired" <?php echo $status_filter === 'hired' ? 'selected' : ''; ?>>Hired</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-control-modern" name="job">
                                <option value="">All Jobs</option>
                                <?php foreach ($jobs as $job): ?>
                                    <option value="<?php echo $job['job_id']; ?>" 
                                            <?php echo $job_filter == $job['job_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-control-modern" name="company">
                                <option value="">All Companies</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['company_id']; ?>" 
                                            <?php echo $company_filter == $company['company_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modern Applications Table -->
            <div class="modern-table-container" data-aos="fade-up" data-aos-delay="200">
                <table class="table modern-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?php echo getSortUrl('application_date'); ?>" class="sort-link">
                                    <span>Date</span>
                                    <?php if ($sort_by === 'application_date'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Applicant</th>
                            <th>
                                <a href="<?php echo getSortUrl('job_title'); ?>" class="sort-link">
                                    <span>Job</span>
                                    <?php if ($sort_by === 'job_title'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortUrl('company_name'); ?>" class="sort-link">
                                    <span>Company</span>
                                    <?php if ($sort_by === 'company_name'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortUrl('status'); ?>" class="sort-link">
                                    <span>Status</span>
                                    <?php if ($sort_by === 'status'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Resume</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <p>No applications found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php $delay = 0; foreach ($applications as $application): $delay += 30; ?>
                            <tr class="table-row-animated" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                                            <?php echo date('M d, Y', strtotime($application['application_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold">
                                                <?php echo htmlspecialchars(($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? '')); ?>
                                            </span>
                                            <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>" 
                                               class="text-muted text-decoration-none">
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($application['applicant_email']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold"><?php echo htmlspecialchars($application['job_title']); ?></span>
                                            <span class="text-muted">
                                                <i class="fas fa-briefcase me-1"></i>
                                                <?php echo ucfirst($application['job_type']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-building text-muted me-2"></i>
                                            <?php echo htmlspecialchars($application['company_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-modern bg-<?php 
                                            echo $application['status'] === 'hired' ? 'success' : 
                                                ($application['status'] === 'rejected' ? 'danger' :
                                                ($application['status'] === 'interviewed' ? 'dark' :
                                                ($application['status'] === 'shortlisted' ? 'primary' :
                                                ($application['status'] === 'reviewed' ? 'info' : 'warning')))); 
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo $application['status'] === 'hired' ? 'check-circle' : 
                                                    ($application['status'] === 'rejected' ? 'times-circle' :
                                                    ($application['status'] === 'interviewed' ? 'user-clock' :
                                                    ($application['status'] === 'shortlisted' ? 'star' :
                                                    ($application['status'] === 'reviewed' ? 'eye' : 'clock')))); 
                                            ?> me-1"></i>
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        // Handle resume file path - check if it already includes the path
                                        $resume_file = $application['resume'];
                                        $resume_path = '';
                                        $display_name = '';
                                        $file_exists = false;
                                        
                                        if (!empty($resume_file)) {
                                            // Check if resume already includes the path
                                            if (strpos($resume_file, 'uploads/resumes/') === 0) {
                                                // File path includes directory
                                                $resume_path = '../' . $resume_file;
                                                $display_name = basename($resume_file);
                                            } else {
                                                // Just filename
                                                $resume_path = '../uploads/resumes/' . $resume_file;
                                                $display_name = $resume_file;
                                            }
                                            $file_exists = file_exists($resume_path);
                                        }
                                        ?>
                                        
                                        <?php if (!empty($resume_file) && $file_exists): ?>
                                            <div class="resume-actions">
                                                <a href="<?php echo htmlspecialchars($resume_path); ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-success" 
                                                   title="View Resume">
                                                    <i class="fas fa-file-pdf me-1"></i> View
                                                </a>
                                                <a href="<?php echo htmlspecialchars($resume_path); ?>" 
                                                   download 
                                                   class="btn btn-sm btn-outline-primary ms-1" 
                                                   title="Download Resume">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                            <small class="text-muted d-block mt-1" title="<?php echo htmlspecialchars($resume_path); ?>">
                                                <?php echo htmlspecialchars($display_name); ?>
                                            </small>
                                        <?php elseif (!empty($application['resume_path']) && file_exists($application['resume_path'])): ?>
                                            <!-- Handle application-specific resume -->
                                            <div class="resume-actions">
                                                <a href="<?php echo htmlspecialchars($application['resume_path']); ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-success" 
                                                   title="View Application Resume">
                                                    <i class="fas fa-file-pdf me-1"></i> View
                                                </a>
                                                <a href="<?php echo htmlspecialchars($application['resume_path']); ?>" 
                                                   download 
                                                   class="btn btn-sm btn-outline-primary ms-1" 
                                                   title="Download Application Resume">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Application Resume
                                            </small>
                                        <?php elseif (!empty($resume_file) && !$file_exists): ?>
                                            <!-- File is referenced but doesn't exist -->
                                            <div class="text-center">
                                                <span class="badge bg-warning text-dark" title="File: <?php echo htmlspecialchars($display_name); ?>">
                                                    <i class="fas fa-exclamation-triangle me-1"></i> File Missing
                                                </span>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                <?php echo htmlspecialchars($display_name); ?>
                                            </small>
                                        <?php else: ?>
                                            <div class="text-center">
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-file-excel me-1"></i> No Resume
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view-application.php?id=<?php echo $application['application_id']; ?>" 
                                               class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <div class="dropdown d-inline">
                                                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li class="dropdown-header">Change Status</li>
                                                    <li>
                                                        <form method="POST" class="dropdown-item-form">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
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
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
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
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
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
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
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
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="status" value="rejected">
                                                            <button type="submit" class="dropdown-item text-danger" 
                                                                    onclick="return confirm('Change status to Rejected?')">
                                                                <i class="fas fa-times me-2"></i> Rejected
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" class="dropdown-item-form">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="dropdown-item text-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this application?')">
                                                                <i class="fas fa-trash me-2"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-top-0">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&job=<?php echo urlencode($job_filter); ?>&company=<?php echo urlencode($company_filter); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&job=<?php echo urlencode($job_filter); ?>&company=<?php echo urlencode($company_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&job=<?php echo urlencode($job_filter); ?>&company=<?php echo urlencode($company_filter); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resume Column Styles -->
    <style>
        /* Resume column specific styling */
        .resume-actions {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .resume-actions .btn {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
        }
        
        .resume-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Ensure resume column doesn't interfere with action buttons */
        .table th:nth-child(6), 
        .table td:nth-child(6) {
            min-width: 120px;
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Action buttons column adjustments */
        .table th:nth-child(7), 
        .table td:nth-child(7) {
            min-width: 120px;
            text-align: center;
        }
        
        /* File icon styling for better UX */
        .resume-actions .fa-file-pdf {
            color: #dc3545;
        }
        
        .resume-actions .fa-download {
            color: #0d6efd;
        }
        
        /* Badge styling for no resume */
        .badge.bg-light {
            color: #6c757d !important;
            border: 1px solid #dee2e6;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table th:nth-child(6), 
            .table td:nth-child(6) {
                min-width: 100px;
                max-width: 120px;
            }
            
            .resume-actions {
                flex-direction: column;
                gap: 0.125rem;
            }
            
            .resume-actions .btn {
                font-size: 0.7rem;
                padding: 0.125rem 0.375rem;
            }
        }
        
        /* Enhanced tooltips for resume buttons */
        .resume-actions .btn[title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.7rem;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 0.25rem;
        }
        
        /* Animation for resume status updates */
        .resume-actions.updated {
            animation: resumeUpdate 0.6s ease-in-out;
        }
        
        @keyframes resumeUpdate {
            0% { background-color: transparent; }
            50% { background-color: rgba(40, 167, 69, 0.1); }
            100% { background-color: transparent; }
        }
        
        /* Feature notification styling */
        .alert-modern.alert-info {
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 202, 240, 0.1) 100%);
            border: 1px solid rgba(13, 110, 253, 0.2);
            border-left: 4px solid #0d6efd;
        }
        
        .alert-modern .alert-heading {
            color: #0d6efd;
            font-weight: 600;
        }
    </style>

    <!-- Modern JavaScript Stack -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    
    <!-- Floating Scroll to Top Button -->
    <button id="scrollToTop" class="scroll-to-top" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
    // Initialize AOS (Animate On Scroll)
    AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: true,
        offset: 100
    });

    // Scroll to top functionality
    window.addEventListener('scroll', function() {
        const scrollBtn = document.getElementById('scrollToTop');
        if (window.pageYOffset > 100) {
            scrollBtn.classList.add('show');
        } else {
            scrollBtn.classList.remove('show');
        }
    });

    // Smooth scroll to top
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Export applications functionality
    function exportApplications() {
        alert('Export functionality will be implemented for applications data');
    }

    // Close any open dropdown menus on scroll to prevent sticky menus
    function closeOpenDropdowns() {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            const toggle = menu.parentElement && menu.parentElement.querySelector('[data-bs-toggle="dropdown"]');
            try {
                if (toggle) {
                    const dd = bootstrap.Dropdown.getOrCreateInstance(toggle);
                    dd.hide();
                } else {
                    menu.classList.remove('show');
                }
            } catch (e) {
                menu.classList.remove('show');
            }
        });
    }

    // Attach scroll listeners (capture phase to catch nested scrollables)
    window.addEventListener('scroll', closeOpenDropdowns, true);
    document.addEventListener('scroll', closeOpenDropdowns, true);

    // Resume viewer functionality with real-time updates
    let lastResumeCheck = Date.now();
    
    function checkResumeUpdates() {
        // Make AJAX call to check for resume updates
        fetch(`api/check-resume-updates.php?last_check=${lastResumeCheck}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.update_count > 0) {
                    console.log(`Found ${data.update_count} resume updates`);
                    
                    // Update the page display for updated resumes
                    data.updated_resumes.forEach(update => {
                        updateResumeDisplay(update);
                    });
                    
                    // Show notification for resume updates
                    showResumeUpdateNotification(data.update_count);
                }
                lastResumeCheck = data.last_check;
            })
            .catch(error => {
                console.error('Error checking resume updates:', error);
            });
    }
    
    function updateResumeDisplay(resumeUpdate) {
        // Find the row for this jobseeker and highlight the resume cell
        const rows = document.querySelectorAll('.table tbody tr');
        rows.forEach(row => {
            const emailCell = row.querySelector('td:nth-child(2) a[href*="mailto"]');
            if (emailCell) {
                const jobseekerName = `${resumeUpdate.first_name} ${resumeUpdate.last_name}`.trim();
                const rowName = row.querySelector('td:nth-child(2) .fw-bold').textContent.trim();
                
                if (rowName === jobseekerName) {
                    const resumeCell = row.querySelector('td:nth-child(6)');
                    if (resumeCell) {
                        resumeCell.classList.add('updated');
                        setTimeout(() => resumeCell.classList.remove('updated'), 5000);
                        
                        // Update the resume links if needed
                        const resumeActions = resumeCell.querySelector('.resume-actions');
                        if (resumeActions && resumeUpdate.resume) {
                            const viewBtn = resumeActions.querySelector('a[target="_blank"]');
                            const downloadBtn = resumeActions.querySelector('a[download]');
                            
                            if (viewBtn) {
                                viewBtn.href = `../uploads/resumes/${resumeUpdate.resume}`;
                            }
                            if (downloadBtn) {
                                downloadBtn.href = `../uploads/resumes/${resumeUpdate.resume}`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    function showResumeUpdateNotification(count) {
        // Create and show a notification for resume updates
        const notification = document.createElement('div');
        notification.className = 'alert alert-info alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-file-pdf me-2"></i>
            <strong>${count}</strong> resume${count > 1 ? 's' : ''} updated recently!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Enhanced resume button interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Add click tracking for resume views
        document.querySelectorAll('.resume-actions a[target="_blank"]').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Resume viewed:', this.href);
                // You can add analytics tracking here
            });
        });

        // Add download tracking
        document.querySelectorAll('.resume-actions a[download]').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Resume downloaded:', this.href);
                // You can add analytics tracking here
            });
        });

        // Initialize resume update checking
        checkResumeUpdates();
        setInterval(checkResumeUpdates, 30000); // Check every 30 seconds
    });

    // Function to handle resume file validation
    function validateResumeFile(file) {
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            alert('Please upload a PDF or Word document.');
            return false;
        }

        if (file.size > maxSize) {
            alert('File size must be less than 5MB.');
            return false;
        }

        return true;
    }

    // Enhanced error handling for resume links
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.resume-actions a').forEach(link => {
            link.addEventListener('error', function() {
                console.warn('Resume file not found:', this.href);
                this.style.opacity = '0.5';
                this.title = 'File not accessible';
            });
        });
    });


    </script>
</body>
</html> 