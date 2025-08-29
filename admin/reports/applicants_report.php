<?php
// Applicants Report

// Additional filters for this report
$skill_filter = isset($_GET['skill']) ? $_GET['skill'] : '';
$name_filter = isset($_GET['applicant_name']) ? $_GET['applicant_name'] : '';

try {
    // Get list of skills for filter dropdown
    $stmt = $conn->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build the query
    $query = "SELECT u.user_id as applicant_id, 
              CONCAT(up.first_name, ' ', up.last_name) as full_name,
              u.email, u.created_at as registered_date,
              (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') 
               FROM jobseeker_skills js 
               JOIN skills s ON js.skill_id = s.skill_id 
               WHERE js.jobseeker_id = u.user_id) as skills,
              (SELECT COUNT(*) FROM applications a WHERE a.jobseeker_id = u.user_id) as application_count,
              (SELECT COUNT(*) FROM job_skills jsk 
               JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
               JOIN jobs j ON jsk.job_id = j.job_id 
               WHERE js.jobseeker_id = u.user_id AND j.status = 'active'
               GROUP BY js.jobseeker_id) as matches_found
              FROM users u
              JOIN user_profiles up ON u.user_id = up.user_id
              WHERE u.role = 'jobseeker'";
              
    $params = [];
    
    // Apply date range filter to registration date
    if (!empty($start_date) && !empty($end_date)) {
        $query .= " AND u.created_at BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Apply name filter
    if (!empty($name_filter)) {
        $query .= " AND (up.first_name LIKE ? OR up.last_name LIKE ?)";
        $params[] = "%$name_filter%";
        $params[] = "%$name_filter%";
    }
    
    // Apply skill filter (more complex - needs subquery)
    if (!empty($skill_filter)) {
        $query .= " AND EXISTS (
                    SELECT 1 FROM jobseeker_skills js 
                    WHERE js.jobseeker_id = u.user_id 
                    AND js.skill_id = ?)";
        $params[] = $skill_filter;
    }
    
    $query .= " ORDER BY application_count DESC, full_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $applicants = [];
}
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Applicants Report</h5>
        <small class="text-muted">Data from <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report" value="applicants">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text">Applicant Name</span>
                    <input type="text" class="form-control" name="applicant_name" placeholder="Search by name" 
                           value="<?php echo htmlspecialchars($name_filter); ?>">
                </div>
            </div>
            
            <div class="col-md-5">
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
            
            <div class="col-md-2">
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
                        <th>Registered Date</th>
                        <th>Skills</th>
                        <th>No. of Applications</th>
                        <th>Matches Found</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applicants)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No applicants found matching your criteria</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applicants as $applicant): ?>
                            <tr>
                                <td><?php echo $applicant['applicant_id']; ?></td>
                                <td><?php echo htmlspecialchars($applicant['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($applicant['registered_date'])); ?></td>
                                <td><?php echo htmlspecialchars($applicant['skills'] ?? 'None'); ?></td>
                                <td><?php echo $applicant['application_count']; ?></td>
                                <td><?php echo $applicant['matches_found'] ?? 0; ?></td>
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
                        <h6>Total Applicants</h6>
                        <h3><?php echo count($applicants); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Total Applications</h6>
                        <h3><?php echo array_sum(array_column($applicants, 'application_count')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Avg. Applications per Applicant</h6>
                        <h3>
                            <?php 
                            $totalApplicants = count($applicants);
                            $totalApplications = array_sum(array_column($applicants, 'application_count'));
                            echo $totalApplicants > 0 ? round($totalApplications / $totalApplicants, 1) : 0; 
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable if available
        if ($.fn.DataTable) {
            $('#applicantsTable').DataTable({
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