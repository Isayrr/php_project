<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$stats = [
    'total_users' => 0,
    'total_jobs' => 0,
    'total_applications' => 0
];
$recent_users = [];
$recent_jobs = [];
$error = null;

// Get statistics
try {
    $stats = [
        'total_users' => $conn->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn(),
        'total_jobs' => $conn->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
        'total_applications' => $conn->query("SELECT COUNT(*) FROM applications")->fetchColumn()
    ];
    
    // Get recent users
    $stmt = $conn->query("SELECT u.*, up.first_name, up.last_name 
                         FROM users u 
                         LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                         WHERE u.role != 'admin' 
                         ORDER BY u.created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent jobs
    $stmt = $conn->query("SELECT j.*, c.company_name, 
                         (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as application_count,
                         (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id AND status = 'approved') as hired_count,
                         (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id AND status = 'pending') as pending_count
                         FROM jobs j 
                         LEFT JOIN companies c ON j.company_id = c.company_id 
                         ORDER BY j.posted_date DESC LIMIT 5");
    $recent_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Job Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
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
                <a href="dashboard.php" class="active">
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
                                <i class="fas fa-tachometer-alt me-3"></i>
                                Dashboard
                            </h1>
                            <p class="page-subtitle">Welcome to your admin control center</p>
                        </div>
                        <div class="d-flex gap-3">
                            <a href="add-job.php" class="btn btn-modern">
                                <i class="fas fa-plus me-2"></i> Add Job
                            </a>
                            <a href="users.php" class="btn btn-modern btn-modern-primary">
                                <i class="fas fa-users me-2"></i> Manage Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modern Statistics Cards -->
            <div class="stats-container">
                <div class="row">
                    <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                        <div class="stat-card primary">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h2 class="stat-number" data-count="<?php echo $stats['total_users']; ?>">0</h2>
                                    <p class="stat-label">Total Users</p>
                                </div>
                                <i class="fas fa-users stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                        <div class="stat-card success">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h2 class="stat-number" data-count="<?php echo $stats['total_jobs']; ?>">0</h2>
                                    <p class="stat-label">Total Jobs</p>
                                </div>
                                <i class="fas fa-briefcase stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                        <div class="stat-card info">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h2 class="stat-number" data-count="<?php echo $stats['total_applications']; ?>">0</h2>
                                    <p class="stat-label">Total Applications</p>
                                </div>
                                <i class="fas fa-file-alt stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="modern-card" data-aos="fade-up" data-aos-delay="400">
                <div class="card-header-modern">
                    <h5 class="card-title-modern">
                        <i class="fas fa-user-plus me-2"></i>
                        Recent Users
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_users)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-users"></i>
                                                <h6>No recent users</h6>
                                                <p class="text-muted">No new users have registered recently.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_users as $user): ?>
                                    <tr class="fade-in-row">
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge badge-modern bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'employer' ? 'success' : 'primary'); ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-modern bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Jobs Posted -->
            <div class="modern-card" data-aos="fade-up" data-aos-delay="500">
                <div class="card-header-modern">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title-modern">
                            <i class="fas fa-briefcase me-2"></i>
                            Recent Jobs Posted
                        </h5>
                        <a href="jobs.php" class="btn btn-modern btn-modern-success">
                            <i class="fas fa-list me-2"></i> View All Jobs
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Details</th>
                                    <th>Applications</th>
                                    <th>Posted Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_jobs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-briefcase"></i>
                                                <h6>No recent jobs</h6>
                                                <p class="text-muted">No new jobs have been posted recently.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_jobs as $job): ?>
                                    <tr class="fade-in-row">
                                        <td>
                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-briefcase"></i> <?php echo ucfirst($job['job_type'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="fas fa-money-bill-wave text-success"></i> 
                                                <span class="fw-bold"><?php echo htmlspecialchars($job['salary_range'] ?? 'Not specified'); ?></span>
                                            </div>
                                            <?php if (!empty($job['requirements'])): ?>
                                                <small class="text-muted d-block mt-1">
                                                    <i class="fas fa-list-ul"></i> 
                                                    <?php echo htmlspecialchars(substr($job['requirements'], 0, 50)) . '...'; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge badge-modern bg-info">
                                                    <i class="fas fa-users"></i> <?php echo $job['application_count']; ?> Total
                                                </span>
                                                <?php if ($job['pending_count'] > 0): ?>
                                                    <span class="badge badge-modern bg-warning">
                                                        <i class="fas fa-clock"></i> <?php echo $job['pending_count']; ?> Pending
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($job['hired_count'] > 0): ?>
                                                    <span class="badge badge-modern bg-success">
                                                        <i class="fas fa-check-circle"></i> <?php echo $job['hired_count']; ?> Hired
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></div>
                                            <small class="text-muted">
                                                <?php 
                                                $days = floor((time() - strtotime($job['posted_date'])) / (60 * 60 * 24));
                                                echo '<i class="far fa-clock"></i> ' . $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Job">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Modern counter animation for statistics
        function animateCounters() {
            const counters = document.querySelectorAll('[data-count]');
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const duration = 1500;
                const step = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 16);
            });
        }

        // Trigger counter animation when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateCounters, 500);
        });

        // Enhanced table row animations
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table-modern tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 50}ms`;
                row.classList.add('fade-in-row');
            });
        });

        // Floating action button (scroll to top)
        const floatingBtn = document.createElement('button');
        floatingBtn.className = 'floating-action';
        floatingBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        floatingBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
        document.body.appendChild(floatingBtn);

        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                floatingBtn.style.display = 'flex';
                floatingBtn.style.alignItems = 'center';
                floatingBtn.style.justifyContent = 'center';
            } else {
                floatingBtn.style.display = 'none';
            }
        });
    </script>
</body>
</html> 