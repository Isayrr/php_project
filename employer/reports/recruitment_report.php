<?php
// Recruitment Progress Report

// Additional filters for this report
$job_filter = isset($_GET['job']) ? $_GET['job'] : '';

try {
    // Get company ID for the employer
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception("Company profile not found. Please complete your profile first.");
    }
    
    // Get employer's jobs for filter dropdown
    $stmt = $conn->prepare("SELECT job_id, title FROM jobs WHERE company_id = ? ORDER BY title");
    $stmt->execute([$company['company_id']]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build the query for recruitment pipeline stats
    $query = "SELECT j.job_id, j.title as job_title, 
              COUNT(a.application_id) as total_applicants,
              SUM(CASE WHEN a.status IN ('reviewed', 'shortlisted', 'hired', 'rejected') THEN 1 ELSE 0 END) as reviewed,
              SUM(CASE WHEN a.status IN ('shortlisted', 'hired', 'rejected') THEN 1 ELSE 0 END) as shortlisted,
              SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired,
              SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
              CASE 
                WHEN COUNT(a.application_id) > 0 THEN
                  ROUND((SUM(CASE WHEN a.status IN ('reviewed', 'shortlisted', 'hired', 'rejected') THEN 1 ELSE 0 END) * 100.0 / COUNT(a.application_id)))
                ELSE 0
              END as progress_percentage
              FROM jobs j
              LEFT JOIN applications a ON j.job_id = a.job_id
              WHERE j.company_id = ?";
              
    $params = [$company['company_id']];
    
    // Apply date filters to posted date only when user provided dates
    if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] !== '' && $_GET['end_date'] !== '') {
        $query .= " AND j.posted_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Apply job filter
    if (!empty($job_filter)) {
        $query .= " AND j.job_id = ?";
        $params[] = $job_filter;
    }
    
    $query .= " GROUP BY j.job_id, j.title ORDER BY total_applicants DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $recruitment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall funnel metrics
    $total_applicants = array_sum(array_column($recruitment_stats, 'total_applicants'));
    $total_reviewed = array_sum(array_column($recruitment_stats, 'reviewed'));
    $total_shortlisted = array_sum(array_column($recruitment_stats, 'shortlisted'));
    $total_hired = array_sum(array_column($recruitment_stats, 'hired'));
    $total_rejected = array_sum(array_column($recruitment_stats, 'rejected'));
    
    // Calculate conversion rates
    $review_rate = $total_applicants > 0 ? round(($total_reviewed / $total_applicants) * 100, 1) : 0;
    $shortlist_rate = $total_reviewed > 0 ? round(($total_shortlisted / $total_reviewed) * 100, 1) : 0;
    $hire_rate = $total_shortlisted > 0 ? round(($total_hired / $total_shortlisted) * 100, 1) : 0;
    $overall_hire_rate = $total_applicants > 0 ? round(($total_hired / $total_applicants) * 100, 1) : 0;
    
} catch(Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $recruitment_stats = [];
    $jobs = [];
    $total_applicants = $total_reviewed = $total_shortlisted = $total_hired = $total_rejected = 0;
    $review_rate = $shortlist_rate = $hire_rate = $overall_hire_rate = 0;
}

// Format dates for display
$formatted_start_date = date('M d, Y', strtotime($start_date));
$formatted_end_date = date('M d, Y', strtotime($end_date));
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Recruitment Progress Report</h5>
            <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
        </div>
        <a href="reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report_type" value="recruitment">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text">Job</span>
                    <select class="form-select" name="job">
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
        
        <!-- Recruitment Summary Cards section removed -->
        
        <!-- Recruitment Progress Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="recruitmentTable">
                <thead class="table-dark">
                    <tr>
                        <th>Job ID</th>
                        <th>Job Title</th>
                        <th>Total Applicants</th>
                        <th>Reviewed</th>
                        <th>Shortlisted</th>
                        <th>Hired</th>
                        <th>Rejected</th>
                        <th>Progress %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recruitment_stats)): ?>
                        <?php foreach ($recruitment_stats as $stat): ?>
                            <tr>
                                <td><?php echo $stat['job_id']; ?></td>
                                <td><?php echo htmlspecialchars($stat['job_title']); ?></td>
                                <td><?php echo $stat['total_applicants']; ?></td>
                                <td><?php echo $stat['reviewed']; ?></td>
                                <td><?php echo $stat['shortlisted']; ?></td>
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
    </div>
</div>

<script>
// Recruitment Funnel visualization script removed
</script> 
