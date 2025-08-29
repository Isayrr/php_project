<?php
session_start();
require_once '../config/database.php';
require_once '../includes/common_data.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$companies = [];
$error = null;
$success = null;

// Handle company actions
if (isset($_POST['action']) && isset($_POST['company_id'])) {
    try {
        $company_id = $_POST['company_id'];
        $action = $_POST['action'];
        
        if ($action === 'activate' || $action === 'deactivate') {
            // Get the employer_id for this company
            $stmt = $conn->prepare("SELECT employer_id FROM companies WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $employer_id = $stmt->fetchColumn();
            
            // Update the employer's status
            $new_status = $action === 'activate' ? 'active' : 'inactive';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$new_status, $employer_id]);
            $success = "Company status updated successfully.";
        } elseif ($action === 'delete') {
            // Start transaction for safe deletion
            $conn->beginTransaction();
            
            try {
                // Get the employer_id for this company
            $stmt = $conn->prepare("SELECT employer_id FROM companies WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $employer_id = $stmt->fetchColumn();
            
                if (!$employer_id) {
                    throw new Exception("Company not found.");
                }
                
                // Delete the employer user account
                // This will automatically cascade delete:
                // 1. companies (via employer_id FK)
                // 2. jobs (via company_id FK from companies)
                // 3. applications (via job_id FK from jobs)
                // 4. job_skills (via job_id FK from jobs)
                // 5. skill_matches (via application_id FK from applications)
                // 6. user_profiles (via user_id FK)
                // 7. jobseeker_skills (if any, via user_id FK)
                // 8. notifications (via user_id FK)
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$employer_id]);
            
                // Commit the transaction
                $conn->commit();
                
                $success = "Employer account and all associated data (company, jobs, applications) deleted successfully.";
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollBack();
                throw $e;
            }
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$industry_filter = isset($_GET['industry']) ? $_GET['industry'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'company_name';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;

try {
    // Build the query
    $query = "SELECT c.*, u.email, u.status as user_status,
              (SELECT COUNT(*) FROM jobs WHERE company_id = c.company_id) as total_jobs,
              (SELECT COUNT(*) FROM applications a 
               JOIN jobs j ON a.job_id = j.job_id 
               WHERE j.company_id = c.company_id) as total_applications
              FROM companies c 
              JOIN users u ON c.employer_id = u.user_id";
    $count_query = "SELECT COUNT(*) FROM companies c JOIN users u ON c.employer_id = u.user_id";
    $params = [];
    
    if ($search) {
        $query .= " WHERE (c.company_name LIKE ? OR c.industry LIKE ? OR u.email LIKE ?)";
        $count_query .= " WHERE (c.company_name LIKE ? OR c.industry LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    } else {
        $query .= " WHERE 1=1";
        $count_query .= " WHERE 1=1";
    }
    
    if ($industry_filter) {
        $query .= " AND c.industry = ?";
        $count_query .= " AND c.industry = ?";
        $params[] = $industry_filter;
    }
    
    if ($status_filter) {
        $query .= " AND u.status = ?";
        $count_query .= " AND u.status = ?";
        $params[] = $status_filter;
    }
    
    // Get total count for pagination
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_companies = $stmt->fetchColumn();
    $total_pages = ceil($total_companies / $per_page);
    
    // Add sorting and pagination
    $valid_sort_columns = ['company_name', 'industry', 'total_jobs', 'total_applications', 'user_status'];
    $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'company_name';
    $sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
    
    $query .= " ORDER BY $sort_by $sort_order";
    $query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page);
    
    // Execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get industries for filter
    $industries = getIndustries($conn);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Function to generate sort URL
function getSortUrl($column) {
    global $sort_by, $sort_order, $search, $industry_filter, $status_filter;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'DESC' : 'ASC';
    $params = [
        'sort' => $column,
        'order' => $new_order,
        'search' => $search,
        'industry' => $industry_filter,
        'status' => $status_filter
    ];
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Companies - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <style>
        /* Companies-specific modern enhancements */
        .company-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .company-card:hover {
            transform: translateY(-2px);
        }
        
        .company-logo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-sm);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            text-align: center;
        }
        
        /* Modern Action Buttons */
        .action-buttons {
            justify-content: flex-end !important;
            align-items: center !important;
        }

        .action-btn {
            width: 32px !important;
            height: 32px !important;
            padding: 0 !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s ease !important;
            border-width: 1px !important;
            font-size: 13px !important;
        }

        .action-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        .action-btn.btn-outline-primary {
            color: #0d6efd !important;
            border-color: #0d6efd !important;
        }

        .action-btn.btn-outline-primary:hover {
            background-color: #0d6efd !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4) !important;
        }

        .action-btn.btn-outline-success {
            color: #198754 !important;
            border-color: #198754 !important;
        }

        .action-btn.btn-outline-success:hover {
            background-color: #198754 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.4) !important;
        }

        .action-btn.btn-outline-warning {
            color: #fd7e14 !important;
            border-color: #fd7e14 !important;
        }

        .action-btn.btn-outline-warning:hover {
            background-color: #fd7e14 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(253, 126, 20, 0.4) !important;
        }

        .action-btn.btn-outline-danger {
            color: #dc3545 !important;
            border-color: #dc3545 !important;
        }

        .action-btn.btn-outline-danger:hover {
            background-color: #dc3545 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4) !important;
        }

        /* Prevent text wrapping in action cells */
        .table-modern td:last-child {
            white-space: nowrap !important;
            min-width: 140px !important;
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
                <a href="companies.php" class="active">
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
                                <i class="fas fa-building me-3"></i>
                                Company Management
                            </h1>
                            <p class="page-subtitle">Manage registered companies and their information</p>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-center">
                                <h3 class="text-white mb-0"><?php echo count($companies); ?></h3>
                                <small class="opacity-75">Total Companies</small>
                            </div>
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
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control form-control-modern" name="search" 
                                       placeholder="Search companies..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <?php 
                            $industry_options = [
                                '' => 'All Industries',
                                'Technology' => 'Technology',
                                'Healthcare' => 'Healthcare',
                                'Finance' => 'Finance',
                                'Education' => 'Education',
                                'Manufacturing' => 'Manufacturing',
                                'Retail' => 'Retail',
                                'Construction' => 'Construction',
                                'Other' => 'Other'
                            ];
                            ?>
                            <select class="form-select form-control-modern" name="industry">
                                <?php foreach ($industry_options as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $industry_filter === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-control-modern" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modern Companies Table -->
            <div class="modern-card" data-aos="fade-up" data-aos-delay="200">
                <div class="table-responsive">
                    <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?php echo getSortUrl('company_name'); ?>" class="text-decoration-none text-dark">
                                    Company Name
                                    <?php if ($sort_by === 'company_name'): ?>
                                        (<?php echo $sort_order === 'ASC' ? 'asc' : 'desc'; ?>)
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortUrl('industry'); ?>" class="text-decoration-none text-dark">
                                    Industry
                                    <?php if ($sort_by === 'industry'): ?>
                                        (<?php echo $sort_order === 'ASC' ? 'asc' : 'desc'; ?>)
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Size</th>
                            <th>Contact</th>
                            <th>
                                <a href="<?php echo getSortUrl('total_jobs'); ?>" class="text-decoration-none text-dark">
                                    Jobs
                                    <?php if ($sort_by === 'total_jobs'): ?>
                                        (<?php echo $sort_order === 'ASC' ? 'asc' : 'desc'; ?>)
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortUrl('total_applications'); ?>" class="text-decoration-none text-dark">
                                    Applications
                                    <?php if ($sort_by === 'total_applications'): ?>
                                        (<?php echo $sort_order === 'ASC' ? 'asc' : 'desc'; ?>)
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortUrl('user_status'); ?>" class="text-decoration-none text-dark">
                                    Status
                                    <?php if ($sort_by === 'user_status'): ?>
                                        (<?php echo $sort_order === 'ASC' ? 'asc' : 'desc'; ?>)
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-building"></i>
                                    <p>No companies found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php $delay = 0; foreach ($companies as $company): $delay += 50; ?>
                            <tr class="table-row-animated" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (isset($company['logo']) && !empty($company['logo'])): ?>
                                            <img src="../uploads/company_logos/<?php echo htmlspecialchars($company['logo']); ?>" 
                                                 alt="Company Logo" class="company-logo me-3">
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($company['company_name']); ?></div>
                                            <?php if (isset($company['company_website']) && !empty($company['company_website'])): ?>
                                                <small class="text-muted">
                                                    <a href="<?php echo htmlspecialchars($company['company_website']); ?>" 
                                                       target="_blank" class="text-decoration-none">
                                                        <i class="fas fa-external-link-alt me-1"></i> Website
                                                    </a>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-modern bg-secondary">
                                        <?php echo isset($company['industry']) && !empty($company['industry']) ? htmlspecialchars($company['industry']) : 'N/A'; ?>
                                    </span>
                                </td>
                                <td><?php echo isset($company['company_size']) && !empty($company['company_size']) ? htmlspecialchars($company['company_size']) : 'N/A'; ?></td>
                                <td>
                                    <?php if (isset($company['email']) && !empty($company['email'])): ?>
                                        <div>
                                            <a href="mailto:<?php echo htmlspecialchars($company['email']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($company['email']); ?>
                                            </a>
                                        </div>
                                        <?php if (isset($company['phone']) && !empty($company['phone'])): ?>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($company['phone']); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No contact information</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-modern bg-info">
                                        <?php echo isset($company['total_jobs']) ? $company['total_jobs'] : 0; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-modern bg-primary">
                                        <?php echo isset($company['total_applications']) ? $company['total_applications'] : 0; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-modern bg-<?php echo isset($company['user_status']) && $company['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo isset($company['user_status']) ? ucfirst($company['user_status']) : 'Unknown'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 action-buttons">
                                        <!-- View Button -->
                                        <a href="view-company.php?id=<?php echo $company['company_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary action-btn" 
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- Activate/Deactivate Button -->
                                        <?php if ($company['user_status'] === 'active'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-warning action-btn" 
                                                        title="Deactivate Company"
                                                        onclick="return confirm('Are you sure you want to deactivate this company? This will prevent them from posting new jobs.')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-success action-btn" 
                                                        title="Activate Company">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Delete Button -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" 
                                                    class="btn btn-sm btn-outline-danger action-btn" 
                                                    title="Delete Company"
                                                    onclick="return confirm('Are you sure you want to delete this company? This will also delete all associated jobs and applications.')">
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
                    </div>
                </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industry_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    Prev
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industry_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industry_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    Next
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

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

        document.addEventListener('DOMContentLoaded', function() {
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

        // Enhanced action buttons with tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips for action buttons
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
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