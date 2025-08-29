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

try {
    // Get company ID
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new Exception("Please complete your company profile first.");
    }

    // Determine smart default date range covering all employer data
    $default_start_date = null;
    $default_end_date = date('Y-m-d');

    try {
        // Earliest job posted date
        $stmt = $conn->prepare("SELECT MIN(posted_date) AS min_date FROM jobs WHERE company_id = ?");
        $stmt->execute([$company['company_id']]);
        $min_job_date = $stmt->fetchColumn();

        // Earliest application date for this employer's jobs
        $stmt = $conn->prepare("SELECT MIN(a.application_date) AS min_date
                                FROM applications a JOIN jobs j ON a.job_id = j.job_id
                                WHERE j.company_id = ?");
        $stmt->execute([$company['company_id']]);
        $min_app_date = $stmt->fetchColumn();

        // Choose the earliest non-null date
        $candidates = array_filter([$min_job_date, $min_app_date]);
        if (!empty($candidates)) {
            $default_start_date = date('Y-m-d', strtotime(min($candidates)));
        }
    } catch (Exception $e) {
        // Fallback silently
    }

    if (!$default_start_date) {
        // Fallback to last 365 days if no data exists yet
        $default_start_date = date('Y-m-d', strtotime('-365 days'));
    }

    // Get date range from request or fallback to defaults
    $start_date = $_GET['start_date'] ?? $default_start_date;
    $end_date = $_GET['end_date'] ?? $default_end_date;

    // Validate date range
    if (strtotime($end_date) < strtotime($start_date)) {
        throw new Exception("End date cannot be earlier than start date.");
    }

    // Get total jobs
    $stmt = $conn->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ?");
    $stmt->execute([$company['company_id']]);
    $total_jobs = $stmt->fetchColumn();

    // Get total applications
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
                           WHERE j.company_id = ?");
    $stmt->execute([$company['company_id']]);
    $total_applications = $stmt->fetchColumn();

    // Get total hires
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
                           WHERE j.company_id = ? AND a.status = 'hired'");
    $stmt->execute([$company['company_id']]);
    $total_hires = $stmt->fetchColumn();

    // Get job type distribution
    $stmt = $conn->prepare("SELECT job_type, COUNT(*) as count 
                           FROM jobs 
                           WHERE company_id = ? 
                           GROUP BY job_type");
    $stmt->execute([$company['company_id']]);
    $job_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get application status distribution
    $stmt = $conn->prepare("SELECT a.status, COUNT(*) as count 
                           FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
                           WHERE j.company_id = ? 
                           GROUP BY a.status");
    $stmt->execute([$company['company_id']]);
    $application_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly trends
    $stmt = $conn->prepare("SELECT 
                           DATE_FORMAT(j.posted_date, '%Y-%m') as month,
                           COUNT(DISTINCT j.job_id) as jobs_posted,
                           COUNT(DISTINCT a.application_id) as applications_received
                           FROM jobs j 
                           LEFT JOIN applications a ON j.job_id = a.job_id 
                           WHERE j.company_id = ? 
                           AND j.posted_date BETWEEN ? AND ?
                           GROUP BY DATE_FORMAT(j.posted_date, '%Y-%m')
                           ORDER BY month");
    $stmt->execute([$company['company_id'], $start_date, $end_date]);
    $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top skills in demand
    $stmt = $conn->prepare("SELECT s.skill_name, COUNT(*) as count 
                           FROM job_skills js 
                           JOIN skills s ON js.skill_id = s.skill_id 
                           JOIN jobs j ON js.job_id = j.job_id 
                           WHERE j.company_id = ? 
                           GROUP BY s.skill_id 
                           ORDER BY count DESC 
                           LIMIT 5");
    $stmt->execute([$company['company_id']]);
    $top_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate application success rate
    $success_rate = $total_applications > 0 ? 
                   round(($total_hires / $total_applications) * 100, 1) : 0;

} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Employer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body.loading {
            position: relative;
            cursor: wait;
        }
        body.loading::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: rgba(255, 255, 255, 0.8);
            z-index: 1000;
        }
        body.loading::before {
            content: 'Loading...';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1001;
            font-weight: bold;
        }
        /* Chart size presets */
        .chart-wrapper { position: relative; width: 100%; }
        .chart-wrapper.chart-sm { height: 140px; }
        .chart-wrapper.chart-md { height: 200px; }
        .chart-wrapper.chart-lg { height: 280px; }
        .chart-wrapper.chart-xl { height: 360px; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }
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
                <a href="reports.php" class="active">
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
                <h2>Reports & Analytics</h2>
                <div class="d-flex gap-2">
                    <form method="GET" id="date-filter-form" class="d-flex gap-2">
                        <input type="hidden" id="report_type" name="report_type" value="<?php echo $_GET['report_type'] ?? 'summary'; ?>">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="input-group">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </form>
                    <div class="dropdown">
                        <button class="btn btn-success dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                            <li><a class="dropdown-item" href="#" onclick="exportReport('excel')"><i class="fas fa-file-excel text-success me-2"></i>Excel (.xlsx)</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportReport('word')"><i class="fas fa-file-word text-primary me-2"></i>Word (.docx)</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')"><i class="fas fa-file-pdf text-danger me-2"></i>PDF</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="exportReport('print')"><i class="fas fa-print text-dark me-2"></i>Printable View</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Report Type Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo (!isset($_GET['report_type']) || $_GET['report_type'] == 'summary') ? 'active' : ''; ?>" 
                       href="?report_type=summary&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Summary Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'jobs') ? 'active' : ''; ?>" 
                       href="?report_type=jobs&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Job Listings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'applicants') ? 'active' : ''; ?>" 
                       href="?report_type=applicants&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Applicants</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'applications') ? 'active' : ''; ?>" 
                       href="?report_type=applications&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Applications</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'recruitment') ? 'active' : ''; ?>" 
                       href="?report_type=recruitment&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Recruitment Progress</a>
                </li>
            </ul>

            <?php
            // Get the report type from query parameter
            $report_type = $_GET['report_type'] ?? 'summary';
            
            // Display the appropriate report based on type
            switch($report_type) {
                case 'jobs':
                    include 'reports/jobs_report.php';
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
                case 'company': // Redirect to summary if company report is requested
                    echo '<script>window.location.href = "reports.php?report_type=summary";</script>';
                    break;
                case 'summary':
                default:
                    // Show the summary dashboard (original content)
            ?>
            
            <!-- Charts -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Job Type Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrapper chart-xl" id="jobTypeWrapper">
                                <canvas id="jobTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Application Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrapper chart-xl" id="appStatusWrapper">
                                <canvas id="applicationStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Monthly Trends</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrapper chart-xl" id="trendsWrapper">
                                <canvas id="trendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Top Skills in Demand</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrapper chart-xl" id="skillsWrapper">
                                <canvas id="skillsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php } // End of summary report ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Toggle sidebar
        document.querySelector('.toggle-btn').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
        
        // Export report function
        function exportReport(type) {
            const currentReport = document.getElementById('report_type').value;
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            
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
            
            // Create export URL
            let exportUrl = `export_report.php?type=${type}&report_type=${currentReport}`;
            
            // Add date filters if present
            if (startDate) exportUrl += `&start_date=${startDate}`;
            if (endDate) exportUrl += `&end_date=${endDate}`;
            
            if (type === 'print') {
                // Open in new window for print
                window.open(exportUrl, '_blank');
                // Hide the status message
                statusMsg.style.display = 'none';
                document.body.classList.remove('loading');
            } else {
                // For other formats, check if download starts
                try {
                    // Create an iframe to handle the download
                    let downloadFrame = document.createElement('iframe');
                    downloadFrame.style.display = 'none';
                    document.body.appendChild(downloadFrame);
                    
                    // Set timeout to check if download started
                    setTimeout(function() {
                        statusMsg.innerHTML = 'If download doesn\'t start, <a href="' + exportUrl + '" target="_blank">click here</a>';
                        document.body.classList.remove('loading');
                    }, 3000);
                    
                    // Start the download
                    downloadFrame.src = exportUrl;
                } catch (e) {
                    // If there's an error, provide a direct link
                    statusMsg.innerHTML = 'Error: ' + e.message + '<br><a href="' + exportUrl + '" target="_blank">Try direct download</a>';
                    document.body.classList.remove('loading');
                }
            }
            
            // Also log for debugging
            console.log('Export URL:', exportUrl, 'Type:', type);
        }
        
        // Initialize DataTables
        $(document).ready(function() {
            $('.table').each(function() {
                if ($.fn.DataTable.isDataTable(this)) {
                    // Table already initialized
                    return;
                }
                
                // Initialize with default options
                $(this).DataTable({
                    responsive: true,
                    paging: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    language: {
                        emptyTable: "No data available in table",
                        search: "Search table:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)"
                    }
                });
            });
        });
        
        <?php if (!isset($_GET['report_type']) || $_GET['report_type'] == 'summary'): ?>
        // Chart initialization for summary report
        window.addEventListener('DOMContentLoaded', function() {
            // Helper to change wrapper size dynamically
            function setChartSize(wrapperId, size) {
                const wrapper = document.getElementById(wrapperId);
                if (!wrapper) return;
                wrapper.classList.remove('chart-sm', 'chart-md', 'chart-lg');
                wrapper.classList.add(size);
            }

            // Job Type Chart
            const jobTypeCtx = document.getElementById('jobTypeChart').getContext('2d');
            const jobTypeData = {
                labels: <?php echo json_encode(array_map(function($item) { 
                    return ucfirst(str_replace('-', ' ', $item['job_type'])); 
                }, $job_types)); ?>,
                datasets: [{
                    label: 'Jobs',
                    data: <?php echo json_encode(array_column($job_types, 'count')); ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderWidth: 1
                }]
            };
            
            new Chart(jobTypeCtx, {
                type: 'pie',
                data: jobTypeData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
            
            // Application Status Chart
            const appStatusCtx = document.getElementById('applicationStatusChart').getContext('2d');
            const appStatusData = {
                labels: <?php echo json_encode(array_map(function($item) { 
                    return ucfirst($item['status']); 
                }, $application_status)); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_column($application_status, 'count')); ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderWidth: 1
                }]
            };
            
            new Chart(appStatusCtx, {
                type: 'doughnut',
                data: appStatusData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
            
            // Monthly Trends Chart (Auto-update)
            let trendsChartInstance = null;
            function fetchAndUpdateTrendsChart() {
                fetch('ajax/monthly_trends.php')
                    .then(response => response.json())
                    .then(data => {
                        const labels = data.map(item => {
                            const d = new Date(item.month + '-01');
                            return d.toLocaleString('default', { month: 'short', year: 'numeric' });
                        });
                        const jobsPosted = data.map(item => parseInt(item.jobs_posted));
                        const applicationsReceived = data.map(item => parseInt(item.applications_received));

                        const trendsData = {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Jobs Posted',
                                    data: jobsPosted,
                                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1,
                                    barPercentage: 0.7,
                                    categoryPercentage: 0.5
                                },
                                {
                                    label: 'Applications Received',
                                    data: applicationsReceived,
                                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    borderWidth: 1,
                                    barPercentage: 0.7,
                                    categoryPercentage: 0.5
                                }
                            ]
                        };

                        if (trendsChartInstance) {
                            trendsChartInstance.data = trendsData;
                            trendsChartInstance.update();
                        } else {
                            const trendsCtx = document.getElementById('trendsChart').getContext('2d');
                            trendsChartInstance = new Chart(trendsCtx, {
                                type: 'bar',
                                data: trendsData,
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'top' }
                                    },
                                    scales: {
                                        x: {
                                            stacked: false,
                                            grid: { display: false }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            stacked: false
                                        }
                                    }
                                }
                            });
                        }
                    });
            }
            fetchAndUpdateTrendsChart();
            setInterval(fetchAndUpdateTrendsChart, 60000);
            
            // Top Skills Chart
            const skillsCtx = document.getElementById('skillsChart').getContext('2d');
            const skillsData = {
                labels: <?php echo json_encode(array_column($top_skills, 'skill_name')); ?>,
                datasets: [{
                    label: 'Demand Count',
                    data: <?php echo json_encode(array_column($top_skills, 'count')); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            };
            
            new Chart(skillsCtx, {
                type: 'bar',
                data: skillsData,
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Expose simple size controls via keyboard for quick testing (S/M/L)
            document.addEventListener('keydown', function(e) {
                if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
                if (['s','m','l','x'].includes(e.key)) {
                    const sizeMap = { s: 'chart-sm', m: 'chart-md', l: 'chart-lg', x: 'chart-xl' };
                    const size = sizeMap[e.key];
                    setChartSize('jobTypeWrapper', size);
                    setChartSize('appStatusWrapper', size);
                    setChartSize('trendsWrapper', size);
                    setChartSize('skillsWrapper', size);
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html> 
