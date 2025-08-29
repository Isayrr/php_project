<?php
session_start();
require_once '../config/database.php';
require_once '../includes/job_utils.php';
require_once 'includes/admin_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$jobs = [];
$error = null;
$success = null;

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle notification action message
if (isset($_SESSION['notification_action'])) {
    $success = $_SESSION['notification_action'];
    unset($_SESSION['notification_action']);
}

// Get highlight parameter for job highlighting
$highlight_job_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;

// Count pending job approvals
$stmt = $conn->prepare("SELECT COUNT(*) FROM jobs WHERE approval_status = 'pending'");
$stmt->execute();
$pending_jobs_count = $stmt->fetchColumn();

// If there are pending approvals, show a notification at the top
if ($pending_jobs_count > 0) {
    echo '
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Attention!</strong> You have ' . $pending_jobs_count . ' pending job ' . ($pending_jobs_count == 1 ? 'posting' : 'postings') . ' that require your review.
        <a href="?approval=pending" class="alert-link">Click here to view them</a>.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}



// Handle job actions
if (isset($_POST['action']) && isset($_POST['job_id'])) {
    try {
        $job_id = $_POST['job_id'];
        $action = $_POST['action'];
        
        if ($action === 'activate' || $action === 'deactivate') {
            $new_status = $action === 'activate' ? 'active' : 'inactive';
            $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE job_id = ?");
            $stmt->execute([$new_status, $job_id]);
            $success = "Job status updated successfully.";
        } elseif ($action === 'approve' || $action === 'reject') {
            $new_approval_status = $action === 'approve' ? 'approved' : 'rejected';
            
            // Update job approval status
            $stmt = $conn->prepare("UPDATE jobs SET approval_status = ? WHERE job_id = ?");
            $stmt->execute([$new_approval_status, $job_id]);
            
            // Get job and employer information
            $stmt = $conn->prepare("
                SELECT j.title, j.company_id, c.employer_id, c.company_name, u.username
                FROM jobs j
                JOIN companies c ON j.company_id = c.company_id
                JOIN users u ON c.employer_id = u.user_id
                WHERE j.job_id = ?
            ");
            $stmt->execute([$job_id]);
            $job_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get current admin username for the notification
            $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $admin_username = $stmt->fetchColumn();
            
            if ($action === 'approve') {
                // Set job to active status
                $stmt = $conn->prepare("UPDATE jobs SET status = 'active' WHERE job_id = ?");
                $stmt->execute([$job_id]);
                
                // Notify employer
                $title = "Job Posting Approved";
                $message = "Your job posting '{$job_info['title']}' has been approved and is now visible to job seekers.";
                
                // Notify matching job seekers
                $notified_count = notifyMatchingJobSeekers($conn, $job_id);
            } else {
                $title = "Job Posting Rejected";
                $message = "Your job posting '{$job_info['title']}' has been rejected. Please contact the administrator for more information.";
            }
            
            // Insert notification for employer
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read) VALUES (?, ?, ?, ?, 'job', NOW(), 0)");
            $stmt->execute([$job_info['employer_id'], $title, $message, $job_id]);
            
            // Notify all admins about the job approval action
            notifyAdminJobApproval($conn, $job_id, $job_info['title'], $job_info['company_name'], $new_approval_status, $admin_username);
            
            $success = "Job {$action}d successfully.";
        } elseif ($action === 'delete') {
            // First delete related records
            $stmt = $conn->prepare("DELETE FROM applications WHERE job_id = ?");
            $stmt->execute([$job_id]);
            
            $stmt = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
            $stmt->execute([$job_id]);
            
            // Then delete the job
            $stmt = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
            $stmt->execute([$job_id]);
            $success = "Job deleted successfully.";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['job_type']) ? $_GET['job_type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$company_filter = isset($_GET['company']) ? $_GET['company'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

try {
    // Get all job categories for filter
    $stmt = $conn->prepare("SELECT * FROM job_categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pagination settings
    $jobs_per_page = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $jobs_per_page;
    
    // Build the query
    $query = "SELECT j.*, c.company_name, jc.category_name,
              (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as application_count
              FROM jobs j 
              LEFT JOIN companies c ON j.company_id = c.company_id
              LEFT JOIN job_categories jc ON j.category_id = jc.category_id";
    $params = [];
    
    if ($search) {
        $query .= " WHERE (j.title LIKE ? OR j.description LIKE ? OR c.company_name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    } else {
        $query .= " WHERE 1=1";
    }
    
    if ($type_filter) {
        $query .= " AND j.job_type = ?";
        $params[] = $type_filter;
    }
    
    if ($status_filter) {
        $query .= " AND j.status = ?";
        $params[] = $status_filter;
    }
    
    // Add approval_status filter if provided
    $approval_filter = isset($_GET['approval']) ? $_GET['approval'] : '';
    if ($approval_filter) {
        $query .= " AND j.approval_status = ?";
        $params[] = $approval_filter;
    }
    
    if ($company_filter) {
        $query .= " AND j.company_id = ?";
        $params[] = $company_filter;
    }
    
    if ($category_filter) {
        $query .= " AND j.category_id = ?";
        $params[] = $category_filter;
    }
    
    // Count total jobs for pagination
    $count_query = str_replace("j.*, c.company_name, jc.category_name,
              (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as application_count", "COUNT(*) as total", $query);
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_jobs / $jobs_per_page);
    
    // Add pagination to main query
    $query .= " ORDER BY j.posted_date DESC LIMIT $offset, $jobs_per_page";
    
    // Execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for filter
    $stmt = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <style>
        /* Jobs-specific modern enhancements */
        .highlighted-job {
            background-color: #e3f2fd !important;
            border: 2px solid #2196f3 !important;
            box-shadow: 0 0 10px rgba(33, 150, 243, 0.3) !important;
            animation: highlightPulse 2s ease-in-out;
        }
        .highlighted-job:hover {
            background-color: #bbdefb !important;
        }
        
        @keyframes highlightPulse {
            0% { box-shadow: 0 0 10px rgba(33, 150, 243, 0.3); }
            50% { box-shadow: 0 0 20px rgba(33, 150, 243, 0.6); }
            100% { box-shadow: 0 0 10px rgba(33, 150, 243, 0.3); }
        }
        
        .pending-approval {
            background-color: #fff3cd !important;
        }
        .pending-approval:hover {
            background-color: #ffe8b3 !important;
        }
        
        .job-info .title {
            white-space: normal;
            word-break: break-word;
            font-weight: 600;
            color: #495057;
        }
        
        .job-info .company {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.25rem;
        }
        
        /* Action buttons styling */
        .btn-group-sm .btn {
            padding: 0.35rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            margin-right: 2px;
        }
        
        .btn-group-sm .btn:last-child {
            margin-right: 0;
        }
        
        .btn-group-sm .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-group-sm .btn i {
            font-size: 0.8rem;
        }
        
        /* Action buttons spacing */
        .btn-group[role="group"] .btn {
            margin-right: 0.25rem;
        }
        
        .btn-group[role="group"] .btn:last-child {
            margin-right: 0;
        }
        
        /* Tooltip styles for better UX */
        .btn[title] {
            position: relative;
        }
        
        /* Responsive action buttons */
        @media (max-width: 768px) {
            .btn-group-sm .btn {
                padding: 0.25rem 0.4rem;
                font-size: 0.8rem;
            }
            
            .btn-group-sm .btn i {
                font-size: 0.75rem;
            }
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
                <a href="jobs.php" class="active">
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
                                <i class="fas fa-briefcase me-3"></i>
                                Job Management
                            </h1>
                            <p class="page-subtitle">Manage and oversee all job postings in the system</p>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-center">
                                <h3 class="text-white mb-0"><?php echo count($jobs); ?></h3>
                                <small class="opacity-75">Total Jobs</small>
                            </div>
                            <a href="add-job.php" class="btn btn-modern">
                                <i class="fas fa-plus me-2"></i> Add Job
                            </a>
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
                                       placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-control-modern" name="job_type">
                                <option value="">All Types</option>
                                <option value="full-time" <?php echo $type_filter === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                <option value="part-time" <?php echo $type_filter === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                                <option value="contract" <?php echo $type_filter === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="internship" <?php echo $type_filter === 'internship' ? 'selected' : ''; ?>>Internship</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-control-modern" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-control-modern" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" 
                                            <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
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
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modern Jobs Table -->
            <div class="modern-table-container" data-aos="fade-up" data-aos-delay="200">
                <table class="table modern-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Company</th>
                            <th>Applications</th>
                            <th>Posted Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jobs)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-briefcase"></i>
                                        <p>No jobs found matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $delay = 0; foreach ($jobs as $job): $delay += 50; ?>
                                <tr class="table-row-animated <?php echo $job['approval_status'] === 'pending' ? 'pending-approval' : ''; ?> <?php echo ($highlight_job_id && $job['job_id'] == $highlight_job_id) ? 'highlighted-job' : ''; ?>" 
                                    data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                                    <td>
                                        <div class="job-info">
                                            <div class="title"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <div class="company">
                                                <i class="fas fa-building me-1"></i>
                                                <?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-modern bg-primary">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $job['application_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- View Details -->
                                            <a href="view-job.php?id=<?php echo $job['job_id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <!-- Activate/Deactivate -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <input type="hidden" name="action" value="<?php echo $job['status'] === 'active' ? 'deactivate' : 'activate'; ?>">
                                                <button type="submit" 
                                                        class="btn btn-outline-<?php echo $job['status'] === 'active' ? 'warning' : 'success'; ?>"
                                                        title="<?php echo $job['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $job['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Approval Actions for Pending Jobs -->
                                            <?php if ($job['approval_status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" 
                                                            class="btn btn-outline-success" 
                                                            title="Approve Job">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" 
                                                            class="btn btn-outline-danger" 
                                                            title="Reject Job">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Delete -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" 
                                                        class="btn btn-outline-danger" 
                                                        title="Delete Job"
                                                        onclick="return confirm('Are you sure you want to delete this job? This will also delete all associated applications.')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="pagination-info">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $jobs_per_page, $total_jobs); ?> of <?php echo $total_jobs; ?> jobs
                    </div>
                    <div class="pagination-buttons">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $type_filter ? '&type=' . urlencode($type_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $company_filter ? '&company=' . urlencode($company_filter) : ''; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="mx-3">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $type_filter ? '&type=' . urlencode($type_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $company_filter ? '&company=' . urlencode($company_filter) : ''; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?>" class="btn btn-outline-primary">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Job Modal -->
    <div class="modal fade" id="addJobModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Add New Job</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="add-job.php">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Job Title</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Company</label>
                                <select class="form-select" name="company_id" required>
                                    <option value="">Select Company</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['company_id']; ?>">
                                            <?php echo htmlspecialchars($company['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Job Type</label>
                                <select class="form-select" name="job_type" required>
                                    <option value="">Select Type</option>
                                    <option value="full-time">Full Time</option>
                                    <option value="part-time">Part Time</option>
                                    <option value="contract">Contract</option>
                                    <option value="internship">Internship</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Salary Range (Per Month)</label>
                                <input type="text" class="form-control" name="salary_range" placeholder="e.g. $2,000 - $3,500">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Number of Vacancies *</label>
                                <input type="number" class="form-control" name="vacancies" min="1" value="1" required>
                                <small class="form-text text-muted">Enter the number of positions available for this job.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Requirements</label>
                            <textarea class="form-control" name="requirements" rows="3" placeholder="Enter requirements, one per line"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Skills Required</label>
                            <select id="skills-select" class="form-select" name="skills[]" multiple>
                                <?php 
                                try {
                                    $stmt = $conn->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name");
                                    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($skills as $skill): 
                                ?>
                                    <option value="<?php echo $skill['skill_id']; ?>">
                                        <?php echo htmlspecialchars($skill['skill_name']); ?>
                                    </option>
                                <?php 
                                    endforeach;
                                } catch(PDOException $e) {
                                    // Silently handle error
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">Search or select multiple skills</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Job</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Generate modals for each pending job -->
    <?php 
    foreach ($jobs as $job) {
        if ($job['approval_status'] === 'pending') {
    ?>
        <div class="modal fade" id="jobModal<?php echo $job['job_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Job Posting Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Job Information</h6>
                                <table class="table">
                                    <tr>
                                        <th>Title:</th>
                                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Company:</th>
                                        <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Category:</th>
                                        <td><?php echo htmlspecialchars($job['category_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Type:</th>
                                        <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Location:</th>
                                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Salary:</th>
                                        <td><?php echo htmlspecialchars($job['salary_range']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Posted:</th>
                                        <td><?php echo htmlspecialchars($job['posted_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Deadline:</th>
                                        <td><?php echo htmlspecialchars($job['deadline_date']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Job Description</h6>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                </div>
                                
                                <?php if (!empty($job['requirements'])): ?>
                                <h6 class="mt-3">Requirements</h6>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form method="POST">
                            <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                <i class="fas fa-check me-2"></i> Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">
                                <i class="fas fa-times me-2"></i> Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php
        }
    }
    ?>

    <!-- Modern JavaScript Stack -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
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

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Tom Select for skills
            new TomSelect('#skills-select', {
                plugins: ['remove_button'],
                placeholder: 'Select required skills...',
                allowEmptyOption: true,
                closeAfterSelect: false
            });
            
            // Auto-scroll to highlighted job
            const highlightedJob = document.querySelector('.highlighted-job');
            if (highlightedJob) {
                setTimeout(function() {
                    highlightedJob.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    highlightedJob.style.animation = 'highlightPulse 3s ease-in-out';
                }, 500);
            }

            // Scroll to top functionality
            window.addEventListener('scroll', function() {
                const scrollBtn = document.getElementById('scrollToTop');
                if (window.pageYOffset > 100) {
                    scrollBtn.classList.add('show');
                } else {
                    scrollBtn.classList.remove('show');
                }
            });
        });

        // Dropdown management for jobs table
        function closeOtherDropdowns(clickedButton) {
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            allDropdowns.forEach(dropdown => {
                if (dropdown !== clickedButton.nextElementSibling) {
                    dropdown.classList.remove('show');
                }
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(dropdown => {
                const toggle = dropdown.previousElementSibling;
                if (!toggle.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });
        });

        // Smooth scroll to top
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>
</body>
</html> 