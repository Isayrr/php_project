<?php
// Recruitment Progress Report

// Additional filters for this report
$company_filter = isset($_GET['company']) ? $_GET['company'] : '';
$job_filter = isset($_GET['job']) ? $_GET['job'] : '';

try {
    // Get list of companies for filter dropdown
    $stmt = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get job titles if company is selected
    $jobs = [];
    if (!empty($company_filter)) {
        $stmt = $conn->prepare("SELECT job_id, title FROM jobs WHERE company_id = ? ORDER BY title");
        $stmt->execute([$company_filter]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Build the query for recruitment pipeline stats
    $query = "SELECT j.job_id, j.title as job_title, c.company_name,
              COUNT(a.application_id) as total_applicants,
              SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as screened,
              SUM(CASE WHEN a.status IN ('interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as interviewed,
              SUM(CASE WHEN a.status IN ('offered', 'hired') THEN 1 ELSE 0 END) as offered,
              SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired,
              SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
              CASE 
                WHEN COUNT(a.application_id) > 0 THEN
                  ROUND((SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) * 100.0 / COUNT(a.application_id)))
                ELSE 0
              END as progress_percentage
              FROM jobs j
              JOIN companies c ON j.company_id = c.company_id
              LEFT JOIN applications a ON j.job_id = a.job_id
              WHERE 1=1";
              
    $params = [];
    
    // Apply date filters to posted date
    if (!empty($start_date) && !empty($end_date)) {
        $query .= " AND j.posted_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Apply additional filters
    if (!empty($company_filter)) {
        $query .= " AND j.company_id = ?";
        $params[] = $company_filter;
    }
    
    if (!empty($job_filter)) {
        $query .= " AND j.job_id = ?";
        $params[] = $job_filter;
    }
    
    $query .= " GROUP BY j.job_id, j.title, c.company_name ORDER BY total_applicants DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $recruitment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $recruitment_stats = [];
}
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Recruitment Progress Report</h5>
        <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report" value="recruitment">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text">Company</span>
                    <select class="form-select" name="company" id="companySelect">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['company_id']; ?>" 
                                    <?php echo ($company_filter == $company['company_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text">Job</span>
                    <select class="form-select" name="job" id="jobSelect">
                        <option value="">All Jobs</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['job_id']; ?>" 
                                    <?php echo ($job_filter == $job['job_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>
        
        <!-- Recruitment Progress Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="recruitmentTable">
                <thead class="table-dark">
                    <tr>
                        <th>Job ID</th>
                        <th>Job Title</th>
                        <th>Company Name</th>
                        <th>Total Applicants</th>
                        <th>Screened</th>
                        <th>Interviewed</th>
                        <th>Offered</th>
                        <th>Hired</th>
                        <th>Rejected</th>
                        <th>Progress %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recruitment_stats)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No recruitment data found matching your criteria</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recruitment_stats as $stat): ?>
                            <tr>
                                <td><?php echo $stat['job_id']; ?></td>
                                <td><?php echo htmlspecialchars($stat['job_title']); ?></td>
                                <td><?php echo htmlspecialchars($stat['company_name']); ?></td>
                                <td><?php echo $stat['total_applicants']; ?></td>
                                <td><?php echo $stat['screened']; ?></td>
                                <td><?php echo $stat['interviewed']; ?></td>
                                <td><?php echo $stat['offered']; ?></td>
                                <td><?php echo $stat['hired']; ?></td>
                                <td><?php echo $stat['rejected']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo $stat['progress_percentage']; ?>%">
                                            <?php echo $stat['progress_percentage']; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary stats -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Total Applicants</h6>
                        <h3><?php echo array_sum(array_column($recruitment_stats, 'total_applicants')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Interviewed</h6>
                        <h3><?php echo array_sum(array_column($recruitment_stats, 'interviewed')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Hired</h6>
                        <h3><?php echo array_sum(array_column($recruitment_stats, 'hired')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Average Hire Rate</h6>
                        <h3>
                            <?php 
                            $totalApplicants = array_sum(array_column($recruitment_stats, 'total_applicants'));
                            $totalHired = array_sum(array_column($recruitment_stats, 'hired'));
                            echo $totalApplicants > 0 ? round(($totalHired / $totalApplicants) * 100, 1) . '%' : '0%'; 
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Funnel Visualization -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Recruitment Funnel</h5>
            </div>
            <div class="card-body">
                <div id="recruitmentFunnel" style="height: 400px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Dynamic job dropdown based on company selection
        $('#companySelect').on('change', function() {
            var companyId = $(this).val();
            if (companyId) {
                $.ajax({
                    url: 'ajax/get_company_jobs.php',
                    type: 'GET',
                    data: {company_id: companyId},
                    success: function(data) {
                        $('#jobSelect').html(data);
                    }
                });
            } else {
                $('#jobSelect').html('<option value="">All Jobs</option>');
            }
        });
        
        // Initialize DataTable if available
        if ($.fn.DataTable) {
            $('#recruitmentTable').DataTable({
                paging: true,
                ordering: true,
                info: true,
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
            });
        }
        
        // Create recruitment funnel chart
        <?php if (!empty($recruitment_stats)): ?>
        if (typeof echarts !== 'undefined') {
            var funnelChart = echarts.init(document.getElementById('recruitmentFunnel'));
            
            var option = {
                title: {
                    text: 'Recruitment Pipeline',
                    left: 'center'
                },
                tooltip: {
                    trigger: 'item',
                    formatter: '{a} <br/>{b} : {c} ({d}%)'
                },
                legend: {
                    orient: 'vertical',
                    left: 'left',
                    data: ['Total Applicants', 'Screened', 'Interviewed', 'Offered', 'Hired']
                },
                series: [
                    {
                        name: 'Recruitment Funnel',
                        type: 'funnel',
                        width: '80%',
                        left: '10%',
                        top: 60,
                        bottom: 60,
                        minSize: '0%',
                        maxSize: '100%',
                        sort: 'descending',
                        gap: 2,
                        label: {
                            show: true,
                            position: 'inside'
                        },
                        labelLine: {
                            length: 10,
                            lineStyle: {
                                width: 1,
                                type: 'solid'
                            }
                        },
                        itemStyle: {
                            borderColor: '#fff',
                            borderWidth: 1
                        },
                        emphasis: {
                            label: {
                                fontSize: 20
                            }
                        },
                        data: [
                            {value: <?php echo array_sum(array_column($recruitment_stats, 'total_applicants')); ?>, name: 'Total Applicants'},
                            {value: <?php echo array_sum(array_column($recruitment_stats, 'screened')); ?>, name: 'Screened'},
                            {value: <?php echo array_sum(array_column($recruitment_stats, 'interviewed')); ?>, name: 'Interviewed'},
                            {value: <?php echo array_sum(array_column($recruitment_stats, 'offered')); ?>, name: 'Offered'},
                            {value: <?php echo array_sum(array_column($recruitment_stats, 'hired')); ?>, name: 'Hired'}
                        ]
                    }
                ]
            };
            
            funnelChart.setOption(option);
            
            // Responsive chart
            window.addEventListener('resize', function() {
                funnelChart.resize();
            });
        }
        <?php endif; ?>
    });
</script> 