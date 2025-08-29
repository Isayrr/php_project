<?php
// Summary Dashboard Report

// Get summary stats
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
    
    // Get monthly application trends (filtered by date range)
    $monthly_trends = [];
    try {
        $stmt = $conn->prepare("SELECT DATE_FORMAT(application_date, '%Y-%m') as month, COUNT(*) as count FROM applications WHERE application_date BETWEEN :start_date AND :end_date GROUP BY DATE_FORMAT(application_date, '%Y-%m') ORDER BY month ASC");
        $stmt->execute([
            ':start_date' => $start_date . ' 00:00:00',
            ':end_date' => $end_date . ' 23:59:59',
        ]);
        $monthly_trends_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fill in months with zero applications
        $monthly_trends = [];
        $months = [];
        $start = new DateTime(date('Y-m-01', strtotime($start_date)));
        $end = new DateTime(date('Y-m-01', strtotime($end_date)));
        $interval = new DateInterval('P1M');
        $period = new DatePeriod($start, $interval, $end->modify('+1 month'));
        foreach ($period as $dt) {
            $months[$dt->format('Y-m')] = 0;
        }
        foreach ($monthly_trends_raw as $row) {
            $months[$row['month']] = (int)$row['count'];
        }
        foreach ($months as $month => $count) {
            $monthly_trends[] = ['month' => $month, 'count' => $count];
        }
    } catch (PDOException $e) {
        // fallback to previous logic if error
        $monthly_trends = [];
    }
    
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
                         SUM(CASE WHEN status = 'approved' OR status = 'hired' THEN 1 ELSE 0 END) as approved
                         FROM applications");
    $success_rate = $stmt->fetch(PDO::FETCH_ASSOC);
    $success_rate['rate'] = $success_rate['total'] > 0 ? 
        round(($success_rate['approved'] / $success_rate['total']) * 100, 1) : 0;
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
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

<?php if ($start_date && $end_date): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Showing data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Users</h5>
                <h2 class="mb-0"><?php echo array_sum(array_column($user_stats, 'count')); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Total Jobs</h5>
                <h2 class="mb-0"><?php echo $total_jobs; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Total Companies</h5>
                <h2 class="mb-0"><?php echo $total_companies; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">Success Rate</h5>
                <h2 class="mb-0"><?php echo $success_rate['rate']; ?>%</h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- User Statistics -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">User Statistics</h5>
            </div>
            <div class="card-body">
                <?php if (empty($user_stats)): ?>
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <p>No user data available</p>
                    </div>
                <?php else: ?>
                    <canvas id="userChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Job Statistics -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Job Type Distribution</h5>
            </div>
            <div class="card-body">
                <?php if (empty($job_stats)): ?>
                    <div class="no-data">
                        <i class="fas fa-briefcase"></i>
                        <p>No job data available</p>
                    </div>
                <?php else: ?>
                    <canvas id="jobChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Application Statistics -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Application Status</h5>
            </div>
            <div class="card-body">
                <?php if (empty($application_stats)): ?>
                    <div class="no-data">
                        <i class="fas fa-file-alt"></i>
                        <p>No application data available</p>
                    </div>
                <?php else: ?>
                    <canvas id="applicationChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Monthly Trends -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Monthly Application Trends</h5>
            </div>
            <div class="card-body">
                <?php if (empty($monthly_trends)): ?>
                    <div class="no-data">
                        <i class="fas fa-chart-line"></i>
                        <p>No trend data available</p>
                    </div>
                <?php else: ?>
                    <canvas id="trendChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top 5 In-Demand Skills Chart -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Top 5 In-Demand Skills</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_skills)): ?>
                    <div class="no-data">
                        <i class="fas fa-lightbulb"></i>
                        <p>No skill data available</p>
                    </div>
                <?php else: ?>
                    <canvas id="skillsChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top 5 Companies by Job Count Chart -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Top 5 Companies by Job Count</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_companies)): ?>
                    <div class="no-data">
                        <i class="fas fa-building"></i>
                        <p>No company data available</p>
                    </div>
                <?php else: ?>
                    <canvas id="companiesChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Applications -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Applications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_applications)): ?>
                    <div class="no-data">
                        <i class="fas fa-file-alt"></i>
                        <p>No recent applications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Applicant</th>
                                    <th>Job</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_applications as $application): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($application['application_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($application['applicant_name']); ?></td>
                                    <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                    <td><?php echo htmlspecialchars($application['company_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo ($application['status'] === 'approved' || $application['status'] === 'hired') ? 'success' : 
                                                ($application['status'] === 'rejected' ? 'danger' : 
                                                ($application['status'] === 'interviewed' ? 'info' : 
                                                ($application['status'] === 'shortlisted' ? 'primary' :
                                                ($application['status'] === 'reviewed' ? 'secondary' : 'warning')))); 
                                        ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Top Companies -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Top Companies by Job Count</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_companies)): ?>
                    <div class="no-data">
                        <i class="fas fa-building"></i>
                        <p>No company data available</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Total Jobs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_companies as $company): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                    <td><?php echo $company['job_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Chart initialization for summary dashboard
document.addEventListener('DOMContentLoaded', function() {
    // User statistics chart
    if (document.getElementById('userChart')) {
        new Chart(document.getElementById('userChart'), {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($user_stats as $stat): ?>
                    '<?php echo ucfirst($stat['role']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Users by Role',
                    data: [
                        <?php foreach ($user_stats as $stat): ?>
                        <?php echo $stat['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Job type distribution chart
    if (document.getElementById('jobChart')) {
        new Chart(document.getElementById('jobChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($job_stats as $stat): ?>
                    '<?php echo ucfirst($stat['job_type']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Jobs by Type',
                    data: [
                        <?php foreach ($job_stats as $stat): ?>
                        <?php echo $stat['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Application status chart
    if (document.getElementById('applicationChart')) {
        const applicationLabels = [
            <?php foreach ($application_stats as $stat): ?>
            '<?php echo ucfirst($stat['status']); ?>',
            <?php endforeach; ?>
        ];
        const applicationData = [
            <?php foreach ($application_stats as $stat): ?>
            <?php echo $stat['count']; ?>,
            <?php endforeach; ?>
        ];
        const applicationColors = applicationLabels.map(function(label){
            const s = label.toLowerCase();
            if (s === 'hired' || s === 'approved') return 'rgba(28, 200, 138, 0.7)'; // green
            if (s === 'rejected') return 'rgba(231, 74, 59, 0.7)'; // red
            if (s === 'interviewed') return 'rgba(54, 185, 204, 0.7)'; // cyan
            if (s === 'shortlisted') return 'rgba(78, 115, 223, 0.7)'; // blue
            if (s === 'reviewed') return 'rgba(108, 117, 125, 0.7)'; // secondary gray
            if (s === 'pending') return 'rgba(246, 194, 62, 0.7)'; // yellow
            return 'rgba(133, 135, 150, 0.7)'; // gray
        });

        new Chart(document.getElementById('applicationChart'), {
            type: 'pie',
            data: {
                labels: applicationLabels,
                datasets: [{
                    label: 'Applications by Status',
                    data: applicationData,
                    backgroundColor: applicationColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Monthly trend chart
    if (document.getElementById('trendChart')) {
        const trendCanvas = document.getElementById('trendChart');
        const ctx = trendCanvas.getContext('2d');

        const trendLabels = [
            <?php foreach (array_reverse($monthly_trends) as $trend): ?>
            '<?php echo date("M Y", strtotime($trend['month'] . "-01")); ?>',
            <?php endforeach; ?>
        ];
        const trendData = [
            <?php foreach (array_reverse($monthly_trends) as $trend): ?>
            <?php echo (int)$trend['count']; ?>,
            <?php endforeach; ?>
        ];

        // 3-month moving average
        const movingAvg = trendData.map((_, i, arr) => {
            const start = Math.max(0, i - 2);
            const window = arr.slice(start, i + 1);
            const sum = window.reduce((a, b) => a + b, 0);
            return +(sum / window.length).toFixed(2);
        });

        // Gradient for bars
        const barGradient = ctx.createLinearGradient(0, 0, 0, trendCanvas.height || 200);
        barGradient.addColorStop(0, 'rgba(54, 162, 235, 0.6)');
        barGradient.addColorStop(1, 'rgba(54, 162, 235, 0.15)');

        new Chart(trendCanvas, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Applications',
                        data: trendData,
                        backgroundColor: barGradient,
                        borderColor: 'rgba(54, 162, 235, 0.9)',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 40
                    },
                    {
                        type: 'line',
                        label: '3-mo Moving Avg',
                        data: movingAvg,
                        borderColor: 'rgba(28, 200, 138, 1)',
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
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y;
                                return `${label}: ${value}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // Top 5 In-Demand Skills chart
    if (document.getElementById('skillsChart')) {
        new Chart(document.getElementById('skillsChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($top_skills as $skill): ?>
                    '<?php echo addslashes($skill['skill_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Jobs',
                    data: [
                        <?php foreach ($top_skills as $skill): ?>
                        <?php echo $skill['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    // Top 5 Companies by Job Count chart
    if (document.getElementById('companiesChart')) {
        new Chart(document.getElementById('companiesChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($top_companies as $company): ?>
                    '<?php echo addslashes($company['company_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Jobs',
                    data: [
                        <?php foreach ($top_companies as $company): ?>
                        <?php echo $company['job_count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script> 