<?php
// Job Applications Report

// Additional filters for this report
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$company_filter = isset($_GET['company']) ? $_GET['company'] : '';
$job_filter = isset($_GET['job']) ? $_GET['job'] : '';

try {
    // Get list of companies for filter dropdown
    $stmt = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get list of unique application statuses
    $stmt = $conn->query("SELECT DISTINCT status FROM applications ORDER BY status");
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get job titles if company is selected
    $jobs = [];
    if (!empty($company_filter)) {
        $stmt = $conn->prepare("SELECT job_id, title FROM jobs WHERE company_id = ? ORDER BY title");
        $stmt->execute([$company_filter]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Build the query
    $query = "SELECT a.application_id, j.title as job_title, 
               CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
               c.company_name, a.application_date, a.status,
               a.cover_letter,
               (SELECT ROUND((COUNT(DISTINCT js.skill_id) * 100.0 / 
                       NULLIF(COUNT(DISTINCT jsk.skill_id), 0)))
                FROM job_skills jsk
                LEFT JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                AND js.jobseeker_id = a.jobseeker_id
                WHERE jsk.job_id = j.job_id) as match_percentage
               FROM applications a
               JOIN jobs j ON a.job_id = j.job_id
               JOIN companies c ON j.company_id = c.company_id
               JOIN users u ON a.jobseeker_id = u.user_id
               JOIN user_profiles up ON u.user_id = up.user_id
               WHERE 1=1";
               
    $params = [];
    
    // Apply date filters to application date
    if (!empty($start_date) && !empty($end_date)) {
        $query .= " AND a.application_date BETWEEN ? AND ?";
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
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY a.application_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $applications = [];
}
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Job Applications Report</h5>
        <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report" value="applications">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-3">
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
            
            <div class="col-md-3">
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
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">Status</span>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" 
                                    <?php echo ($status_filter === $status) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
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
        
        <!-- Applications Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="applicationsTable">
                <thead class="table-dark">
                    <tr>
                        <th>Application ID</th>
                        <th>Job Title</th>
                        <th>Applicant Name</th>
                        <th>Company Name</th>
                        <th>Applied Date</th>
                        <th>Match %</th>
                        <th>Application Status</th>
                        <th>Cover Letter</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No applications found matching your criteria</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td><?php echo $application['application_id']; ?></td>
                                <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                <td><?php echo htmlspecialchars($application['applicant_name']); ?></td>
                                <td><?php echo htmlspecialchars($application['company_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($application['application_date'])); ?></td>
                                <td><?php echo $application['match_percentage'] ? $application['match_percentage'] . '%' : 'N/A'; ?></td>
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
                                <td>
                                    <?php if (!empty($application['cover_letter'])): ?>
                                        <a href="../<?php echo htmlspecialchars($application['cover_letter']); ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-file-alt"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
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
                        <h6>Total Applications</h6>
                        <h3><?php echo count($applications); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Pending</h6>
                        <h3><?php echo count(array_filter($applications, function($app) { 
                            return $app['status'] === 'pending'; 
                        })); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Interviewed</h6>
                        <h3><?php echo count(array_filter($applications, function($app) { 
                            return $app['status'] === 'interviewed'; 
                        })); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Approved/Hired</h6>
                        <h3><?php echo count(array_filter($applications, function($app) { 
                            return $app['status'] === 'approved' || $app['status'] === 'hired'; 
                        })); ?></h3>
                    </div>
                </div>
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
            $('#applicationsTable').DataTable({
                paging: true,
                ordering: true,
                info: true,
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
            });
        }
    });
</script> 