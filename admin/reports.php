<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;

// Initialize variables
$user_stats = [];
$job_stats = [];
$application_stats = [];
$total_companies = 0;
$recent_applications = [];
$top_companies = [];
$monthly_trends = [];
$top_skills = [];
$success_rate = ['total' => 0, 'approved' => 0, 'rate' => 0];

// Get date range from request or default to last 30 days
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate date range
if (strtotime($end_date) < strtotime($start_date)) {
    $error = "End date cannot be earlier than start date";
    $end_date = $start_date;
}

try {
    // Get total users count by role
    $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total jobs count by type
    $stmt = $conn->query("SELECT job_type, COUNT(*) as count FROM jobs GROUP BY job_type");
    $job_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total applications count by status
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
    $application_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total companies count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM companies");
    $total_companies = $stmt->fetchColumn();
    
    // Get recent applications (last 5)
    $stmt = $conn->query("SELECT a.*, 
                         j.title as job_title,
                         c.company_name,
                         CONCAT(up.first_name, ' ', up.last_name) as applicant_name
                         FROM applications a
                         JOIN jobs j ON a.job_id = j.job_id
                         JOIN companies c ON j.company_id = c.company_id
                         JOIN users u ON a.jobseeker_id = u.user_id
                         JOIN user_profiles up ON u.user_id = up.user_id
                         ORDER BY a.application_date DESC
                         LIMIT 5");
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top companies by job count
    $stmt = $conn->query("SELECT c.company_name, COUNT(j.job_id) as job_count
                         FROM companies c
                         LEFT JOIN jobs j ON c.company_id = j.company_id
                         GROUP BY c.company_id
                         ORDER BY job_count DESC
                         LIMIT 5");
    $top_companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly application trends
    $stmt = $conn->query("SELECT 
                         DATE_FORMAT(application_date, '%Y-%m') as month,
                         COUNT(*) as count
                         FROM applications
                         GROUP BY DATE_FORMAT(application_date, '%Y-%m')
                         ORDER BY month DESC
                         LIMIT 6");
    $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top skills in demand
    $stmt = $conn->query("SELECT s.skill_name, COUNT(*) as count 
                         FROM job_skills js 
                         JOIN skills s ON js.skill_id = s.skill_id 
                         GROUP BY s.skill_id 
                         ORDER BY count DESC 
                         LIMIT 5");
    $top_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get application success rate
    $stmt = $conn->query("SELECT 
                         COUNT(*) as total,
                         SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
                         FROM applications");
    $success_rate = $stmt->fetch(PDO::FETCH_ASSOC);
    $success_rate['rate'] = $success_rate['total'] > 0 ? 
        round(($success_rate['approved'] / $success_rate['total']) * 100, 1) : 0;
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Calculate total jobs
$total_jobs = 0;
if (!empty($job_stats)) {
    $total_jobs = array_sum(array_column($job_stats, 'count'));
}

// Format dates for display
$formatted_start_date = date('M d, Y', strtotime($start_date));
$formatted_end_date = date('M d, Y', strtotime($end_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/header.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <!-- Add jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add DataTables CSS and JS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Buttons extension -->
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <!-- DataTables Export Buttons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <!-- DataTables Word Export -->
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.flash.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- ECharts for Recruitment Funnel visualization -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <style>
        .loading {
            position: relative;
            min-height: 200px;
        }
        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .loading::before {
            content: 'Loading...';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1001;
        }
        body.loading::after {
            position: fixed;
            height: 100vh;
        }
        body.loading::before {
            position: fixed;
            z-index: 1001;
        }
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
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
                <a href="reports.php" class="active">
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
                                <i class="fas fa-chart-bar me-3"></i>
                                Reports & Analytics
                            </h1>
                            <p class="page-subtitle">Comprehensive insights and data analytics dashboard</p>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-center">
                                <h3 class="text-white mb-0"><?php echo date('M Y'); ?></h3>
                                <small class="opacity-75">Current Period</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Filter Section -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-body">
                    <form method="GET" class="row g-3" id="filterForm">
                        <input type="hidden" name="report" value="<?php echo isset($_GET['report']) ? htmlspecialchars($_GET['report']) : 'summary'; ?>">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control form-control-modern" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control form-control-modern" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-modern btn-modern-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-modern" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Modern Report Navigation -->
            <div class="modern-nav-tabs" data-aos="fade-up" data-aos-delay="200">
                <ul class="nav nav-tabs nav-modern mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (!isset($_GET['report']) || $_GET['report'] === 'summary') ? 'active' : ''; ?>" 
                           href="?report=summary">
                            <i class="fas fa-chart-pie me-2"></i>Summary
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['report']) && $_GET['report'] === 'jobs') ? 'active' : ''; ?>" 
                           href="?report=jobs">
                            <i class="fas fa-briefcase me-2"></i>Job Listings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['report']) && $_GET['report'] === 'companies') ? 'active' : ''; ?>" 
                           href="?report=companies">
                            <i class="fas fa-building me-2"></i>Companies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['report']) && $_GET['report'] === 'applicants') ? 'active' : ''; ?>" 
                           href="?report=applicants">
                            <i class="fas fa-users me-2"></i>Applicants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['report']) && $_GET['report'] === 'applications') ? 'active' : ''; ?>" 
                           href="?report=applications">
                            <i class="fas fa-file-alt me-2"></i>Applications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['report']) && $_GET['report'] === 'recruitment') ? 'active' : ''; ?>" 
                           href="?report=recruitment">
                            <i class="fas fa-chart-line me-2"></i>Recruitment
                        </a>
                    </li>
                </ul>
            </div>
            
            <?php
            // Determine which report to display
            $report_type = isset($_GET['report']) ? $_GET['report'] : 'summary';
            
            switch ($report_type) {
                case 'jobs':
                    include 'reports/jobs_report.php';
                    break;
                case 'companies':
                    include 'reports/companies_report.php';
                    break;
                case 'applicants':
                    include 'reports/applicants_report.php';
                    break;
                case 'applications':
                    include 'reports/applications_report.php';
                    break;
                case 'recruitment':
                    include 'reports/recruitment_report.php';
                    break;
                default:
                    // Display the summary dashboard
                    include 'reports/summary_report.php';
            }
            ?>
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

    
    </script>

    <script>
    // Initialize any existing chart containers
    const userRoleCtx = document.getElementById('userRoleChart');
    const jobTypeCtx = document.getElementById('jobTypeChart');
    const appStatusCtx = document.getElementById('appStatusChart');
    const trendCtx = document.getElementById('applicationTrendsChart');
    
    // Create charts if containers exist
    if (userRoleCtx) {
        const userRoleLabels = <?php echo json_encode(array_column($user_stats, 'role')); ?>;
        const userRoleCounts = <?php echo json_encode(array_column($user_stats, 'count')); ?>.map(function(v){ return parseInt(v || 0); });
        const userTotal = userRoleCounts.reduce(function(a,b){ return a + b; }, 0);
        const userRolePercentages = userRoleCounts.map(function(c){ return userTotal > 0 ? +((c / userTotal) * 100).toFixed(1) : 0; });

        new Chart(userRoleCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: userRoleLabels,
                datasets: [{
                    data: userRolePercentages,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                const label = context.label || '';
                                const val = context.parsed;
                                return label + ': ' + val + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    
    if (jobTypeCtx) {
        new Chart(jobTypeCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($job_stats, 'job_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($job_stats, 'count')); ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    if (appStatusCtx) {
        const appStatusLabels = <?php echo json_encode(array_column($application_stats, 'status')); ?>;
        const appStatusData = <?php echo json_encode(array_column($application_stats, 'count')); ?>.map(function(v){ return parseInt(v || 0); });
        const appStatusColors = appStatusLabels.map(function(label) {
            const s = String(label).toLowerCase();
            if (s === 'hired' || s === 'approved') return '#1cc88a'; // green
            if (s === 'rejected') return '#e74a3b'; // red
            if (s === 'interviewed') return '#36b9cc'; // cyan
            if (s === 'shortlisted') return '#4e73df'; // blue
            if (s === 'reviewed') return '#6c757d'; // gray (secondary)
            if (s === 'pending') return '#f6c23e'; // yellow
            return '#858796'; // gray default
        });
        const appTotal = appStatusData.reduce(function(a,b){ return a + b; }, 0);
        const appPercentages = appStatusData.map(function(v){ return appTotal > 0 ? +((v / appTotal) * 100).toFixed(1) : 0; });

        new Chart(appStatusCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: appStatusLabels,
                datasets: [{
                    label: 'Percentage',
                    data: appPercentages,
                    backgroundColor: appStatusColors
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                const label = context.dataset.label || '';
                                const val = context.parsed.y;
                                return label + ': ' + val + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 10,
                            callback: function(value){ return value + '%'; }
                        }
                    }
                }
            }
        });
    }
    
    if (trendCtx) {
        const tctx = trendCtx.getContext('2d');
        const labels = <?php echo json_encode(array_column($monthly_trends, 'month')); ?>.map(function(m){
            try { return new Date(m + '-01').toLocaleString(undefined, { month: 'short', year: 'numeric' }); } catch(e) { return m; }
        });
        const dataVals = <?php echo json_encode(array_column($monthly_trends, 'count')); ?>.map(function(v){ return parseInt(v || 0); });

        const movAvg = dataVals.map(function(_, i, arr){
            const start = Math.max(0, i - 2);
            const win = arr.slice(start, i + 1);
            const sum = win.reduce(function(a,b){ return a + b; }, 0);
            return +(sum / win.length).toFixed(2);
        });

        const gradient = tctx.createLinearGradient(0, 0, 0, (trendCtx.height || 200));
        gradient.addColorStop(0, 'rgba(78, 115, 223, 0.6)');
        gradient.addColorStop(1, 'rgba(78, 115, 223, 0.15)');

        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Applications',
                        data: dataVals,
                        backgroundColor: gradient,
                        borderColor: 'rgba(78, 115, 223, 0.9)',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 40
                    },
                    {
                        type: 'line',
                        label: '3-mo Moving Avg',
                        data: movAvg,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.15)',
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: '#1cc88a',
                        fill: false,
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }
    
    // Export Report Function
    function exportReport(type) {
        // Get the current report type from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const currentReport = urlParams.get('report') || 'summary';
        
        // Show a loading indicator
        document.body.classList.add('loading');
        
        // Create a status message element if it doesn't exist
        let statusMsg = document.getElementById('exportStatusMsg');
        if (!statusMsg) {
            statusMsg = document.createElement('div');
            statusMsg.id = 'exportStatusMsg';
            statusMsg.style.position = 'fixed';
            statusMsg.style.top = '20%';
            statusMsg.style.left = '50%';
            statusMsg.style.transform = 'translate(-50%, -50%)';
            statusMsg.style.padding = '15px';
            statusMsg.style.backgroundColor = '#f8f9fa';
            statusMsg.style.border = '1px solid #dee2e6';
            statusMsg.style.borderRadius = '5px';
            statusMsg.style.zIndex = '1050';
            document.body.appendChild(statusMsg);
        }
        statusMsg.innerHTML = 'Generating ' + type.toUpperCase() + ' report...';
        statusMsg.style.display = 'block';
        
        // Generate the export URL
        let exportUrl = 'export_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&report=' + currentReport + '&type=' + type;
        
        // For debugging - add a debug parameter if specified by adding ?debug=1 to the URL
        if (urlParams.get('debug') === '1') {
            exportUrl += '&debug=1';
        }
        
        if (type === 'print') {
            // Open in new window/tab for print view
            window.open(exportUrl, '_blank');
            // Hide the status message
            statusMsg.style.display = 'none';
            document.body.classList.remove('loading');
        } else {
            // For other formats, check if download starts
            try {
                // Log for debugging
                console.log('Starting download:', exportUrl);
                
                // Create an iframe to handle the download
                let downloadFrame = document.createElement('iframe');
                downloadFrame.style.display = 'none';
                document.body.appendChild(downloadFrame);
                
                // Set timeout to check if download started
                setTimeout(function() {
                    statusMsg.innerHTML = 'If download doesn\'t start automatically, <a href="' + exportUrl + '" target="_blank">click here</a> or try the debug version <a href="' + exportUrl + '&debug=1" target="_blank">here</a>.';
                    document.body.classList.remove('loading');
                }, 3000);
                
                // Start the download
                downloadFrame.src = exportUrl;
            } catch (e) {
                // If there's an error, provide a direct link
                statusMsg.innerHTML = 'Error: ' + e.message + '<br><a href="' + exportUrl + '" target="_blank">Try direct download</a> or <a href="debug_export.php">run diagnostic tests</a>';
                document.body.classList.remove('loading');
                console.error('Export error:', e);
            }
        }
        
        // Also log for debugging
        console.log('Export URL:', exportUrl, 'Type:', type);
    }
    
    // Form submission with loading state
    document.getElementById('filterForm').addEventListener('submit', function() {
        document.body.classList.add('loading');
    });
    
    // Initialize DataTables
    $(document).ready(function() {
        // Global DataTables configuration to prevent warnings
        $.fn.dataTable.ext.errMode = 'none'; // Disable DataTables error alerts
        
        // Add a small delay to ensure DOM is fully loaded
        setTimeout(function() {
            try {
                // Initialize all tables with DataTables class
                if ($.fn.DataTable) {
                    // Destroy if already initialized, then initialize
                    if ($.fn.dataTable.isDataTable('.datatable')) {
                        $('.datatable').DataTable().destroy();
                    }
                    
                    $('.datatable').DataTable({
                        responsive: true,
                        dom: 'Bfrtip',
                        buttons: [
                            'copy', 'excel', 'pdf', 'print'
                        ]
                    });
                    
                    // Specific initialization for jobs table if it exists
                    if ($('#jobsTable').length) {
                        if ($.fn.dataTable.isDataTable('#jobsTable')) {
                            $('#jobsTable').DataTable().destroy();
                        }
                        
                        $('#jobsTable').DataTable({
                            responsive: true,
                            ordering: true,
                            dom: 'Bfrtip',
                            buttons: [
                                'copy', 'excel', 'pdf', 'print'
                            ]
                        });
                    }
                }
            } catch(e) {
                console.log('DataTables initialization error:', e);
                // Continue silently without showing errors to user
            }
        }, 100); // 100ms delay
    });
    </script>
</body>
</html> 