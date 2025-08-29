<?php
// Companies Report
require_once '../includes/common_data.php';

// Additional filters for this report
$industry_filter = isset($_GET['industry']) ? $_GET['industry'] : '';
$name_filter = isset($_GET['company_name']) ? $_GET['company_name'] : '';

try {
    // Get industries list for filter
    $industries = getIndustries($conn);
    
    // Get list of unique industries for filter dropdown
    $stmt = $conn->query("SELECT DISTINCT industry FROM companies ORDER BY industry");
    $industries = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build the query - Modified to apply date filters only to the subqueries for job stats
    $date_filter = (!empty($start_date) && !empty($end_date)) 
        ? " AND j.posted_date BETWEEN '$start_date' AND '$end_date 23:59:59'" 
        : "";
    
    $query = "SELECT c.company_id, c.company_name, c.industry, 
              (SELECT MIN(j.posted_date) FROM jobs j WHERE j.company_id = c.company_id) as registered_on,
              (SELECT COUNT(*) FROM jobs j 
               WHERE j.company_id = c.company_id $date_filter) as jobs_posted,
              (SELECT COUNT(*) FROM jobs j 
               WHERE j.company_id = c.company_id AND j.status = 'active' $date_filter) as active_jobs,
              (SELECT COUNT(*) FROM applications a 
               JOIN jobs j ON a.job_id = j.job_id 
               WHERE j.company_id = c.company_id AND a.status = 'hired' $date_filter) as total_hires
              FROM companies c
              WHERE 1=1";
              
    $params = [];
    
    // Apply filters
    if (!empty($industry_filter)) {
        $query .= " AND c.industry = ?";
        $params[] = $industry_filter;
    }
    
    if (!empty($name_filter)) {
        $query .= " AND c.company_name LIKE ?";
        $params[] = "%$name_filter%";
    }
    
    // Note: We removed the date filter from the main query to show all companies
    
    $query .= " ORDER BY jobs_posted DESC, c.company_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    $companies = [];
}
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Companies Report</h5>
        <small class="text-muted">Date range for job statistics: <?php echo $formatted_start_date; ?> to <?php echo $formatted_end_date; ?></small>
    </div>
    <div class="card-body">
        <!-- Additional Filters -->
        <form method="GET" class="row g-3 mb-4">
            <input type="hidden" name="report" value="companies">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text">Company Name</span>
                    <input type="text" class="form-control" name="company_name" placeholder="Search by name" 
                           value="<?php echo htmlspecialchars($name_filter); ?>">
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text">Industry</span>
                    <?php echo renderIndustryDropdown('industry', 'industry_filter', $industry_filter, $industries); ?>
                </div>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>
        
        <!-- Companies Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="companiesTable">
                <thead class="table-dark">
                    <tr>
                        <th>Company ID</th>
                        <th>Company Name</th>
                        <th>Industry</th>
                        <th>Established Since</th>
                        <th>No. of Jobs Posted</th>
                        <th>Active Jobs</th>
                        <th>Total Hires</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($companies)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No companies found matching your criteria</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?php echo $company['company_id']; ?></td>
                                <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($company['industry']); ?></td>
                                <td><?php echo $company['registered_on'] ? date('Y-m-d', strtotime($company['registered_on'])) : 'N/A'; ?></td>
                                <td><?php echo $company['jobs_posted']; ?></td>
                                <td><?php echo $company['active_jobs']; ?></td>
                                <td><?php echo $company['total_hires']; ?></td>
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
                        <h6>Total Companies</h6>
                        <h3><?php echo count($companies); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Total Jobs Posted</h6>
                        <h3><?php echo array_sum(array_column($companies, 'jobs_posted')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Total Active Jobs</h6>
                        <h3><?php echo array_sum(array_column($companies, 'active_jobs')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Total Hires</h6>
                        <h3><?php echo array_sum(array_column($companies, 'total_hires')); ?></h3>
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
            $('#companiesTable').DataTable({
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