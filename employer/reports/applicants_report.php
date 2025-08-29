<?php
// Applicants Report

// Additional filters for this report
$skill_filter = isset($_GET['skill']) ? $_GET['skill'] : '';
$name_filter = isset($_GET['applicant_name']) ? $_GET['applicant_name'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

try {
    // Get company ID for the employer
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception("Company profile not found. Please complete your profile first.");
    }
    
    // Get list of skills for filter dropdown
    $stmt = $conn->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build the query
    $query = "SELECT DISTINCT u.user_id as applicant_id, 
              CONCAT(up.first_name, ' ', up.last_name) as full_name,
              u.email, u.created_at as registered_date,
              (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') 
               FROM jobseeker_skills js 
               JOIN skills s ON js.skill_id = s.skill_id 
               WHERE js.jobseeker_id = u.user_id) as skills,
              (SELECT COUNT(*) FROM applications a 
               JOIN jobs j ON a.job_id = j.job_id 
               WHERE a.jobseeker_id = u.user_id AND j.company_id = ?) as application_count,
              (SELECT AVG(
                (SELECT COUNT(js.skill_id) 
                 FROM jobseeker_skills js 
                 JOIN job_skills jsk ON js.skill_id = jsk.skill_id 
                 WHERE js.jobseeker_id = u.user_id AND jsk.job_id = j.job_id) * 100.0 / 
                (SELECT COUNT(*) FROM job_skills WHERE job_id = j.job_id)
               )
               FROM applications a 
               JOIN jobs j ON a.job_id = j.job_id 
               WHERE a.jobseeker_id = u.user_id AND j.company_id = ?) as avg_match_score,
              (SELECT MAX(a.application_date) 
               FROM applications a 
               JOIN jobs j ON a.job_id = j.job_id 
               WHERE a.jobseeker_id = u.user_id AND j.company_id = ?) as last_application_date
              FROM users u
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              JOIN applications a ON u.user_id = a.jobseeker_id
              JOIN jobs j ON a.job_id = j.job_id
              WHERE u.role = 'jobseeker' AND j.company_id = ?";
              
    $params = [$company['company_id'], $company['company_id'], $company['company_id'], $company['company_id']];
    
    // Apply date range filter only when user provided dates
    if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] !== '' && $_GET['end_date'] !== '') {
        $query .= " AND a.application_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Apply name filter
    if (!empty($name_filter)) {
        $query .= " AND ((up.first_name LIKE ? OR up.last_name LIKE ?) OR u.username LIKE ?)";
        $params[] = "%$name_filter%";
        $params[] = "%$name_filter%";
        $params[] = "%$name_filter%";
    }
    
    // Apply skill filter
    if (!empty($skill_filter)) {
        $query .= " AND EXISTS (
                    SELECT 1 FROM jobseeker_skills js 
                    WHERE js.jobseeker_id = u.user_id 
                    AND js.skill_id = ?)";
        $params[] = $skill_filter;
    }
    
    // Apply application status filter
    if (!empty($status_filter)) {
        $query .= " AND EXISTS (
                   SELECT 1 FROM applications a2
                   JOIN jobs j2 ON a2.job_id = j2.job_id
                   WHERE a2.jobseeker_id = u.user_id 
                   AND j2.company_id = ? 
                   AND a2.status = ?)";
        $params[] = $company['company_id'];
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY application_count DESC, last_application_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $applicants = [];
}

// Format dates for display
$formatted_start_date = date('M d, Y', strtotime($start_date));
$formatted_end_date = date('M d, Y', strtotime($end_date));
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Applicants Report</h5>
            <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
        </div>
        <a href="reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report_type" value="applicants">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">Applicant Name</span>
                    <input type="text" class="form-control" name="applicant_name" placeholder="Search by name" 
                           value="<?php echo htmlspecialchars($name_filter); ?>">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">Skill</span>
                    <select class="form-select" name="skill">
                        <option value="">All Skills</option>
                        <?php foreach ($skills as $skill): ?>
                            <option value="<?php echo $skill['skill_id']; ?>" 
                                    <?php echo ($skill_filter == $skill['skill_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">Status</span>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewed" <?php echo ($status_filter === 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="shortlisted" <?php echo ($status_filter === 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        <option value="hired" <?php echo ($status_filter === 'hired') ? 'selected' : ''; ?>>Hired</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>
        
        <!-- Applicants Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="applicantsTable">
                <thead class="table-dark">
                    <tr>
                        <th>Applicant ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Skills</th>
                        <th>Applications</th>
                        <th>Avg Match Score</th>
                        <th>Last Applied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($applicants)): ?>
                        <?php foreach ($applicants as $applicant): ?>
                            <tr>
                                <td><?php echo $applicant['applicant_id']; ?></td>
                                <td><?php echo htmlspecialchars($applicant['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['skills'] ?? 'None'); ?></td>
                                <td><?php echo $applicant['application_count']; ?></td>
                                <td>
                                    <?php 
                                    $match_score = $applicant['avg_match_score'] ? round($applicant['avg_match_score'], 1) : 0;
                                    $badge_class = ($match_score >= 80) ? 'bg-success' : (($match_score >= 50) ? 'bg-warning' : 'bg-danger');
                                    echo "<span class='badge $badge_class'>$match_score%</span>"; 
                                    ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($applicant['last_application_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Initialize DataTable functionality only
window.addEventListener('DOMContentLoaded', function() {
    // Any additional initialization can go here
});
</script> 
