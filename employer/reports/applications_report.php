<?php
// Job Applications Report

// Additional filters for this report
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$job_filter = isset($_GET['job']) ? $_GET['job'] : '';

try {
    // Get company ID for the employer
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception("Company profile not found. Please complete your profile first.");
    }
    
    // Get list of unique application statuses
    $stmt = $conn->query("SELECT DISTINCT status FROM applications ORDER BY status");
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get jobs for the employer
    $stmt = $conn->prepare("SELECT job_id, title FROM jobs WHERE company_id = ? ORDER BY title");
    $stmt->execute([$company['company_id']]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build the query
    $query = "SELECT a.application_id, j.title as job_title, j.job_id,
               CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
               a.jobseeker_id, a.application_date, a.status,
               a.cover_letter,
               (SELECT ROUND((COUNT(DISTINCT js.skill_id) * 100.0 / 
                       NULLIF(COUNT(DISTINCT jsk.skill_id), 0)))
                FROM job_skills jsk
                LEFT JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                AND js.jobseeker_id = a.jobseeker_id
                WHERE jsk.job_id = j.job_id) as match_percentage
               FROM applications a
               JOIN jobs j ON a.job_id = j.job_id
               JOIN users u ON a.jobseeker_id = u.user_id
               LEFT JOIN user_profiles up ON u.user_id = up.user_id
               WHERE j.company_id = ?";
               
    $params = [$company['company_id']];
    
    // Apply date filters to application date only when user provided dates
    if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] !== '' && $_GET['end_date'] !== '') {
        $query .= " AND a.application_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Apply job filter
    if (!empty($job_filter)) {
        $query .= " AND j.job_id = ?";
        $params[] = $job_filter;
    }
    
    // Apply status filter
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY a.application_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $applications = [];
    $jobs = [];
}

// Format dates for display
$formatted_start_date = date('M d, Y', strtotime($start_date));
$formatted_end_date = date('M d, Y', strtotime($end_date));
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Job Applications Report</h5>
            <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
        </div>
        <a href="reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report_type" value="applications">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-5">
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
            
            <div class="col-md-5">
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
                        <th>Applied Date</th>
                        <th>Match %</th>
                        <th>Status</th>
                        <th>Documents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($applications)): ?>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td><?php echo $application['application_id']; ?></td>
                                <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                <td><?php echo htmlspecialchars($application['applicant_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($application['application_date'])); ?></td>
                                <td>
                                    <?php 
                                    $match = $application['match_percentage'] ? $application['match_percentage'] : 0;
                                    $badge_class = ($match >= 80) ? 'bg-success' : (($match >= 50) ? 'bg-warning' : 'bg-danger');
                                    echo "<span class='badge $badge_class'>$match%</span>"; 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $application['status'] === 'hired' ? 'success' :
                                            ($application['status'] === 'rejected' ? 'danger' :
                                            ($application['status'] === 'shortlisted' ? 'info' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (!empty($application['cover_letter'])): ?>
                                            <a href="../<?php echo htmlspecialchars($application['cover_letter']); ?>" 
                                               class="btn btn-outline-info" target="_blank" title="View Cover Letter">
                                                <i class="fas fa-file-alt"></i> Cover Letter
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No documents</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="../employer/view-applications.php?job_id=<?php echo $application['job_id']; ?>&applicant_id=<?php echo $application['jobseeker_id']; ?>" 
                                       class="btn btn-sm btn-primary" title="View Full Application">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
// Initialize functionality
window.addEventListener('DOMContentLoaded', function() {
    // Charts removed
});
</script> 
