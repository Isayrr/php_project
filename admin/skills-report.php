<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$error = null;

try {
    // Get the most in-demand skills (based on job postings)
    $stmt = $conn->prepare("
        SELECT s.skill_id, s.skill_name, s.priority,
               COUNT(DISTINCT js.job_id) as job_count,
               COUNT(DISTINCT jss.jobseeker_id) as jobseeker_count,
               COUNT(DISTINCT j.company_id) as company_count,
               COUNT(DISTINCT js.job_id) - COUNT(DISTINCT jss.jobseeker_id) as skill_gap
        FROM skills s
        LEFT JOIN job_skills js ON s.skill_id = js.skill_id
        LEFT JOIN jobs j ON js.job_id = j.job_id
        LEFT JOIN jobseeker_skills jss ON s.skill_id = jss.skill_id
        GROUP BY s.skill_id
        ORDER BY job_count DESC
    ");
    $stmt->execute();
    $skill_demand = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get skill match statistics
    $stmt = $conn->prepare("
        SELECT 
            j.job_id, 
            j.title as job_title,
            c.company_name,
            COUNT(DISTINCT a.application_id) as application_count,
            AVG(sm.match_score) as avg_match_score,
            MAX(sm.match_score) as max_match_score,
            MIN(sm.match_score) as min_match_score
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        LEFT JOIN applications a ON j.job_id = a.job_id
        LEFT JOIN skill_matches sm ON a.application_id = sm.application_id
        GROUP BY j.job_id
        HAVING application_count > 0
        ORDER BY avg_match_score DESC
    ");
    $stmt->execute();
    $match_statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overall job statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_jobs,
            COUNT(DISTINCT company_id) as companies_with_jobs
        FROM jobs
    ");
    $stmt->execute();
    $job_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get skill match breakdown by job type
    $stmt = $conn->prepare("
        SELECT 
            j.job_type,
            COUNT(DISTINCT j.job_id) as job_count,
            AVG(sm.match_score) as avg_match_score,
            COUNT(DISTINCT js.skill_id) as avg_required_skills
        FROM jobs j
        LEFT JOIN applications a ON j.job_id = a.job_id
        LEFT JOIN skill_matches sm ON a.application_id = sm.application_id
        LEFT JOIN job_skills js ON j.job_id = js.job_id
        GROUP BY j.job_type
        ORDER BY avg_match_score DESC
    ");
    $stmt->execute();
    $job_type_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills Report - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/header.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="companies.php">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
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
                <a href="manage-skills.php">
                    <i class="fas fa-tools"></i>
                    <span>Manage Skills</span>
                </a>
            </li>
            <li>
                <a href="skills-report.php" class="active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Skills Report</span>
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
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <h2 class="mb-4">Skills Demand Report</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Skills Demand Summary -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Most In-Demand Skills</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="skillDemandChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Skills Gap Analysis</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="skillGapChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Jobs Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Job Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-6 mb-3">
                                    <h3 class="display-4"><?php echo $job_stats['total_jobs']; ?></h3>
                                    <p class="text-muted">Total Jobs</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h3 class="display-4"><?php echo $job_stats['companies_with_jobs']; ?></h3>
                                    <p class="text-muted">Companies</p>
                                </div>
                                <div class="col-md-6">
                                    <h3 class="text-success"><?php echo $job_stats['active_jobs']; ?></h3>
                                    <p class="text-muted">Active Jobs</p>
                                </div>
                                <div class="col-md-6">
                                    <h3 class="text-secondary"><?php echo $job_stats['inactive_jobs']; ?></h3>
                                    <p class="text-muted">Inactive Jobs</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Job Type Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Job Type</th>
                                            <th>Number of Jobs</th>
                                            <th>Avg. Match Score</th>
                                            <th>Avg. Required Skills</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($job_type_stats as $type): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($type['job_type']); ?></td>
                                                <td><?php echo $type['job_count']; ?></td>
                                                <td>
                                                    <?php if ($type['avg_match_score']): ?>
                                                        <div class="progress" style="height: 5px;">
                                                            <div class="progress-bar bg-success" role="progressbar" 
                                                                 style="width: <?php echo $type['avg_match_score']; ?>%"></div>
                                                        </div>
                                                        <small><?php echo number_format($type['avg_match_score'], 1); ?>%</small>
                                                    <?php else: ?>
                                                        <small class="text-muted">No data</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($type['avg_required_skills'], 1); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Skills Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detailed Skills Demand</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Skill</th>
                                    <th>Priority</th>
                                    <th>Jobs Requiring</th>
                                    <th>Job Seekers</th>
                                    <th>Companies</th>
                                    <th>Skills Gap</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skill_demand as $skill): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $skill['priority'] >= 8 ? 'danger' : ($skill['priority'] >= 5 ? 'warning' : 'primary'); ?>">
                                                <?php echo $skill['priority']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $skill['job_count']; ?></td>
                                        <td><?php echo $skill['jobseeker_count']; ?></td>
                                        <td><?php echo $skill['company_count']; ?></td>
                                        <td>
                                            <?php if ($skill['skill_gap'] > 0): ?>
                                                <span class="text-danger">
                                                    <i class="fas fa-arrow-up"></i> <?php echo $skill['skill_gap']; ?>
                                                </span>
                                            <?php elseif ($skill['skill_gap'] < 0): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-arrow-down"></i> <?php echo abs($skill['skill_gap']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Jobs with Match Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Job Match Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Applications</th>
                                    <th>Avg. Match Score</th>
                                    <th>Match Range</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($match_statistics as $job): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($job['job_title']); ?></td>
                                        <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                        <td><?php echo $job['application_count']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $job['avg_match_score']; ?>%"></div>
                                            </div>
                                            <small><?php echo number_format($job['avg_match_score'], 1); ?>%</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                Min: <?php echo number_format($job['min_match_score'], 1); ?>% |
                                                Max: <?php echo number_format($job['max_match_score'], 1); ?>%
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    
    <script>
        // Prepare chart data
        const skillNames = <?php echo json_encode(array_column($skill_demand, 'skill_name')); ?>;
        const jobCounts = <?php echo json_encode(array_column($skill_demand, 'job_count')); ?>;
        const seekerCounts = <?php echo json_encode(array_column($skill_demand, 'jobseeker_count')); ?>;
        const skillGaps = <?php echo json_encode(array_column($skill_demand, 'skill_gap')); ?>;
        
        // Limit to top 10 skills for better visualization
        const top10SkillNames = skillNames.slice(0, 10);
        const top10JobCounts = jobCounts.slice(0, 10);
        const top10SeekerCounts = seekerCounts.slice(0, 10);
        const top10SkillGaps = skillGaps.slice(0, 10);
        
        // Skills Demand Chart
        const ctxSkillDemand = document.getElementById('skillDemandChart').getContext('2d');
        const skillDemandChart = new Chart(ctxSkillDemand, {
            type: 'bar',
            data: {
                labels: top10SkillNames,
                datasets: [
                    {
                        label: 'Jobs Requiring',
                        data: top10JobCounts,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Job Seekers',
                        data: top10SeekerCounts,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Top 10 Skills Demand'
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Skills Gap Chart
        const ctxSkillGap = document.getElementById('skillGapChart').getContext('2d');
        const skillGapChart = new Chart(ctxSkillGap, {
            type: 'bar',
            data: {
                labels: top10SkillNames,
                datasets: [{
                    label: 'Skills Gap (positive = more demand than supply)',
                    data: top10SkillGaps,
                    backgroundColor: top10SkillGaps.map(gap => 
                        gap > 0 ? 'rgba(255, 99, 132, 0.5)' : 
                        gap < 0 ? 'rgba(75, 192, 192, 0.5)' : 'rgba(201, 203, 207, 0.5)'
                    ),
                    borderColor: top10SkillGaps.map(gap => 
                        gap > 0 ? 'rgba(255, 99, 132, 1)' : 
                        gap < 0 ? 'rgba(75, 192, 192, 1)' : 'rgba(201, 203, 207, 1)'
                    ),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Skills Gap Analysis'
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 