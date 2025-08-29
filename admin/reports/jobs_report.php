<?php
// Job Listings Report

// Additional filters for this report
$company_filter = isset($_GET['company']) ? $_GET['company'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$title_filter = isset($_GET['title']) ? $_GET['title'] : '';

try {
    // Get list of companies for filter dropdown
    $stmt = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build the query
    $query = "SELECT j.job_id, j.title, c.company_name, j.location, j.posted_date, j.status, j.deadline_date,
             (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count,
             (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') FROM job_skills js 
              JOIN skills s ON js.skill_id = s.skill_id 
              WHERE js.job_id = j.job_id) as required_skills
             FROM jobs j
             JOIN companies c ON j.company_id = c.company_id
             WHERE 1=1";
             
    $params = [];
    
    // Apply date filters
    if (!empty($start_date) && !empty($end_date)) {
        $query .= " AND j.posted_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59'; // Include all jobs on the end date
    }
    
    // Apply additional filters
    if (!empty($company_filter)) {
        $query .= " AND j.company_id = ?";
        $params[] = $company_filter;
    }
    
    if (!empty($status_filter)) {
        $query .= " AND j.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($title_filter)) {
        $query .= " AND j.title LIKE ?";
        $params[] = "%$title_filter%";
    }
    
    $query .= " ORDER BY j.posted_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $jobs = [];
}
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Job Listings Report</h5>
        <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report" value="jobs">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">Title</span>
                    <input type="text" class="form-control" name="title" placeholder="Search by title" 
                           value="<?php echo htmlspecialchars($title_filter); ?>">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">Company</span>
                    <select class="form-select" name="company">
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
                    <span class="input-group-text">Status</span>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="closed" <?php echo ($status_filter === 'closed') ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>
        
        <!-- Jobs Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="jobsTable">
                <thead class="table-dark">
                    <tr>
                        <th>Job ID</th>
                        <th>Job Title</th>
                        <th>Employer Name</th>
                        <th>Location</th>
                        <th>Required Skills</th>
                        <th>Posted Date</th>
                        <th>No. of Applicants</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No job listings found matching your criteria</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><?php echo $job['job_id']; ?></td>
                                <td><?php echo htmlspecialchars($job['title']); ?></td>
                                <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td><?php echo htmlspecialchars($job['required_skills'] ?? 'None specified'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($job['posted_date'])); ?></td>
                                <td><?php echo $job['applicant_count']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($job['status'] === 'active') ? 'success' : 
                                        (($job['status'] === 'inactive') ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($job['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary stats -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Total Jobs</h6>
                        <h3><?php echo count($jobs); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Active Jobs</h6>
                        <h3><?php echo count(array_filter($jobs, function($job) { return $job['status'] === 'active'; })); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Total Applicants</h6>
                        <h3><?php echo array_sum(array_column($jobs, 'applicant_count')); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 