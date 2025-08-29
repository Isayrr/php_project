<?php
// Job Listings Report
// Get filter values
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$title_filter = isset($_GET['title']) ? $_GET['title'] : '';

try {
    // Get company ID for the employer
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception("Company profile not found. Please complete your profile first.");
    }
    
    // Build the query
    $query = "SELECT j.job_id, j.title, j.location, j.posted_date, j.status, j.deadline_date,
             (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count,
             (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.status = 'hired') as hired_count,
             (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') FROM job_skills js 
              JOIN skills s ON js.skill_id = s.skill_id 
              WHERE js.job_id = j.job_id) as required_skills
             FROM jobs j
             WHERE j.company_id = ?";
             
    $params = [$company['company_id']];
    
    // Apply date filters only when user provided dates
    if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] !== '' && $_GET['end_date'] !== '') {
        $query .= " AND j.posted_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59'; // Include all jobs on the end date
    }
    
    // Apply additional filters
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
    
    // Get job types distribution
    if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] !== '' && $_GET['end_date'] !== '') {
        $stmt = $conn->prepare("SELECT job_type, COUNT(*) as count 
                              FROM jobs 
                              WHERE company_id = ? AND posted_date BETWEEN ? AND ?
                              GROUP BY job_type");
        $stmt->execute([$company['company_id'], $start_date, $end_date . ' 23:59:59']);
    } else {
        $stmt = $conn->prepare("SELECT job_type, COUNT(*) as count FROM jobs WHERE company_id = ? GROUP BY job_type");
        $stmt->execute([$company['company_id']]);
    }
    $job_types_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $jobs = [];
    $job_types_data = [];
}

// Format dates for display
$formatted_start_date = date('M d, Y', strtotime($start_date));
$formatted_end_date = date('M d, Y', strtotime($end_date));
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Job Listings Report</h5>
            <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
        </div>
        <a href="reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report_type" value="jobs">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text">Job Title</span>
                    <input type="text" class="form-control" name="title" placeholder="Search by title" 
                           value="<?php echo htmlspecialchars($title_filter); ?>">
                </div>
            </div>
            
            <div class="col-md-5">
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
            <table class="table table-striped table-bordered" id="jobsReportTable">
                <thead class="table-dark">
                    <tr>
                        <th>Job ID</th>
                        <th>Job Title</th>
                        <th>Location</th>
                        <th>Required Skills</th>
                        <th>Posted Date</th>
                        <th>Deadline</th>
                        <th>Applicants</th>
                        <th>Hires</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($jobs)): ?>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><?php echo $job['job_id']; ?></td>
                                <td><?php echo htmlspecialchars($job['title']); ?></td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td><?php echo htmlspecialchars($job['required_skills'] ?? 'None specified'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($job['posted_date'])); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($job['deadline_date'])); ?></td>
                                <td><?php echo $job['applicant_count']; ?></td>
                                <td><?php echo $job['hired_count']; ?></td>
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
    </div>
</div>

<script>
// Job Types Chart - Removed
</script> 
