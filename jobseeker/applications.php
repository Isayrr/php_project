<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;
$applications = [];

try {
    // Get user's applications with job and company details
    $stmt = $conn->prepare("
        SELECT a.*, j.title as job_title, j.salary_range, j.location, j.job_type,
               c.company_name, c.company_logo,
               sm.match_score, sm.matching_skills, sm.missing_skills
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN companies c ON j.company_id = c.company_id
        LEFT JOIN skill_matches sm ON a.application_id = sm.application_id
        WHERE a.jobseeker_id = ?
        ORDER BY a.application_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle application withdrawal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_application'])) {
        $application_id = $_POST['application_id'];
        
        // Verify the application belongs to the current user
        $stmt = $conn->prepare("SELECT status FROM applications WHERE application_id = ? AND jobseeker_id = ?");
        $stmt->execute([$application_id, $_SESSION['user_id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            throw new Exception("Application not found.");
        }
        
        if ($application['status'] !== 'pending') {
            throw new Exception("Only pending applications can be withdrawn.");
        }
        
        // Update application status to withdrawn
        $stmt = $conn->prepare("UPDATE applications SET status = 'withdrawn', updated_at = NOW() WHERE application_id = ?");
        $stmt->execute([$application_id]);
        
        $success = "Application withdrawn successfully.";
        
        // Refresh applications list
        $stmt = $conn->prepare("
            SELECT a.*, j.title as job_title, j.salary_range, j.location, j.job_type,
                   c.company_name, c.company_logo,
                   sm.match_score, sm.matching_skills, sm.missing_skills
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            JOIN companies c ON j.company_id = c.company_id
            LEFT JOIN skill_matches sm ON a.application_id = sm.application_id
            WHERE a.jobseeker_id = ?
            ORDER BY a.application_date DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(Exception $e) {
    $error = $e->getMessage();
}

// Calculate statistics
$total_applications = count($applications);
$pending_count = count(array_filter($applications, function($app) { return $app['status'] === 'pending'; }));
$shortlisted_count = count(array_filter($applications, function($app) { return $app['status'] === 'shortlisted'; }));
$hired_count = count(array_filter($applications, function($app) { return $app['status'] === 'hired'; }));

// Set page title
$page_title = "My Applications - Job Seeker Panel";
?>
<?php include 'includes/header.php'; ?>

<style>
/* Readability and simple Kanban styles */
.dashboard-container { font-size: 16px; line-height: 1.7; }
.page-title { font-size: 1.6rem; }
.modern-card-title { font-size: 1.1rem; }

.kanban-controls { margin-bottom: 1rem; }
.kanban-board { display: none; gap: 1rem; }
.kanban-board.active { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; }
@media (max-width: 1200px) { .kanban-board.active { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .kanban-board.active { grid-template-columns: repeat(1, 1fr); } }
.kanban-col { background: #fff; border: 1px solid var(--border-color, #e9ecef); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,.08)); }
.kanban-header { padding: .85rem 1rem; font-weight: 700; font-size: 1rem; background: #f8f9fa; border-bottom: 1px solid #eee; display:flex; align-items:center; justify-content:space-between; }
.kanban-list { padding: .85rem; max-height: 60vh; overflow: auto; }
.kanban-card { background: #fff; border: 1px solid #f1f3f5; border-radius: 10px; padding: 1rem; margin-bottom: .85rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.kanban-card .title { font-weight: 700; font-size: 1.05rem; }
.kanban-card .meta { font-size: .95rem; color: #495057; display:flex; gap: .75rem; }
.view-toggle .btn { border-radius: 999px; }

/* Table readability */
#applicationsTable { font-size: .98rem; }
#applicationsTable thead th { font-size: 1rem; }
#applicationsTable td, #applicationsTable th { padding: .9rem .8rem; vertical-align: middle; }
.badge { font-size: .85rem; padding: .45rem .6rem; }
.small { font-size: .9rem; }
</style>

<div class="dashboard-container">
<div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">My Applications</h1>
                    <p class="page-subtitle">Track and manage your job applications</p>
                </div>
                <div class="d-flex gap-2">
        <a href="jobs.php" class="btn btn-primary">
            <i class="fas fa-search"></i> Find More Jobs
        </a>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon primary">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-number"><?php echo $total_applications; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon info">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo $shortlisted_count; ?></div>
                <div class="stat-label">Shortlisted</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon success">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-number"><?php echo $hired_count; ?></div>
                <div class="stat-label">Hired</div>
            </div>
        </div>

        <?php if (empty($applications)): ?>
            <!-- Empty State -->
            <div class="modern-card text-center">
                <div class="modern-card-body" style="padding: 4rem 2rem;">
                    <div class="mb-4">
                        <i class="fas fa-search" style="font-size: 4rem; color: var(--text-muted);"></i>
                    </div>
                    <h3 class="mb-3">No Applications Yet</h3>
                    <p class="text-muted mb-4">You haven't applied to any jobs yet. Start exploring opportunities and submit your first application!</p>
                    <a href="jobs.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Browse Available Jobs
                    </a>
                </div>
        </div>
    <?php else: ?>
            <!-- View Toggle -->
            <div class="d-flex justify-content-between align-items-center kanban-controls">
                <div class="view-toggle btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnBoard">Board</button>
                    <button type="button" class="btn btn-sm btn-outline-primary active" id="btnList">List</button>
                </div>
            </div>

            <!-- Applications Board (Kanban) -->
            <?php
                $statusBuckets = [
                    'pending' => [],
                    'reviewed' => [],
                    'interviewed' => [],
                    'shortlisted' => [],
                    'hired' => [],
                    'rejected' => [],
                    'withdrawn' => []
                ];
                foreach ($applications as $app) {
                    $key = strtolower($app['status']);
                    if (!isset($statusBuckets[$key])) { $statusBuckets[$key] = []; }
                    $statusBuckets[$key][] = $app;
                }
                $statusTitles = [
                    'pending' => 'Pending',
                    'reviewed' => 'Reviewed',
                    'interviewed' => 'Interviewed',
                    'shortlisted' => 'Shortlisted',
                    'hired' => 'Hired',
                    'rejected' => 'Rejected',
                    'withdrawn' => 'Withdrawn'
                ];
            ?>

            <div class="kanban-board" id="kanbanBoard">
                <?php foreach ($statusTitles as $statusKey => $label): $bucket = $statusBuckets[$statusKey]; ?>
                <div class="kanban-col">
                    <div class="kanban-header">
                        <span><?php echo $label; ?></span>
                        <span class="badge bg-secondary"><?php echo count($bucket); ?></span>
                    </div>
                    <div class="kanban-list">
                        <?php if (empty($bucket)): ?>
                            <div class="text-muted small p-2">No items</div>
                        <?php else: foreach ($bucket as $application): ?>
                            <div class="kanban-card">
                                <div class="title"><?php echo htmlspecialchars($application['job_title']); ?></div>
                                <div class="meta mb-1">
                                    <span><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($application['company_name']); ?></span>
                                </div>
                                <div class="meta">
                                    <span><i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($application['application_date'])); ?></span>
                                    <a class="text-decoration-none" href="view-job.php?id=<?php echo $application['job_id']; ?>"><i class="fas fa-external-link-alt me-1"></i>View</a>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Applications List -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-list"></i>
                        Application History
                    </h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="statusFilter" style="width: auto;">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="shortlisted">Shortlisted</option>
                            <option value="interviewed">Interviewed</option>
                            <option value="hired">Hired</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modern-card-body">
                    <div class="modern-table">
                        <table class="table" id="applicationsTable">
                        <thead>
                            <tr>
                                    <th>Job Details</th>
                                <th>Company</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                    <th>Skill Match</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $application): ?>
                                    <tr data-status="<?php echo $application['status']; ?>">
                                    <td>
                                            <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($application['company_logo']) && file_exists('../uploads/company_logos/' . $application['company_logo'])): ?>
                                                <img src="../uploads/company_logos/<?php echo htmlspecialchars($application['company_logo']); ?>" 
                                                     alt="<?php echo htmlspecialchars($application['company_name']); ?>" 
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 12px; border: 2px solid var(--border-color);">
                                            <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: var(--bg-tertiary); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-building text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                    <div class="fw-semibold mb-1"><?php echo htmlspecialchars($application['job_title']); ?></div>
                                                    <div class="d-flex gap-3 small text-muted">
                                                        <span><i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($application['job_type']); ?></span>
                                                        <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($application['location']); ?></span>
                                                        <?php if ($application['salary_range']): ?>
                                                            <span><span class="me-1">₱</span><?php echo htmlspecialchars($application['salary_range']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($application['company_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo date('M d, Y', strtotime($application['application_date'])); ?></div>
                                            <div class="small text-muted"><?php echo date('g:i A', strtotime($application['application_date'])); ?></div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                            $status_icon = '';
                                        switch ($application['status']) {
                                            case 'pending':
                                                $status_class = 'bg-warning';
                                                    $status_icon = 'fa-clock';
                                                break;
                                            case 'reviewed':
                                                $status_class = 'bg-info';
                                                    $status_icon = 'fa-eye';
                                                break;
                                            case 'interviewed':
                                                    $status_class = 'bg-secondary';
                                                    $status_icon = 'fa-comments';
                                                break;
                                            case 'shortlisted':
                                                $status_class = 'bg-primary';
                                                    $status_icon = 'fa-star';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-danger';
                                                    $status_icon = 'fa-times';
                                                break;
                                            case 'hired':
                                                $status_class = 'bg-success';
                                                    $status_icon = 'fa-trophy';
                                                    break;
                                                case 'withdrawn':
                                                    $status_class = 'bg-secondary';
                                                    $status_icon = 'fa-undo';
                                                break;
                                                default:
                                                    $status_class = 'bg-secondary';
                                                    $status_icon = 'fa-question';
                                        }
                                        ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                            <?php echo ucfirst(htmlspecialchars($application['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                            <?php if ($application['match_score']): ?>
                                                <div class="progress mb-1" style="height: 6px;">
                                                    <div class="progress-bar" style="width: <?php echo $application['match_score']; ?>%"></div>
                                                </div>
                                                <div class="small text-muted"><?php echo round($application['match_score']); ?>% match</div>
                                            <?php else: ?>
                                                <span class="text-muted small">No data</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                        data-bs-target="#applicationModal<?php echo $application['application_id']; ?>"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="view-job.php?id=<?php echo $application['job_id']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary" 
                                                   title="View Job">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                                <?php if ($application['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="withdrawApplication(<?php echo $application['application_id']; ?>)"
                                                            title="Withdraw Application">
                                                        <i class="fas fa-times"></i>
                                        </button>
                                                <?php endif; ?>
                                            </div>
                                    </td>
                                </tr>

                                <!-- Application Details Modal -->
                                <div class="modal fade" id="applicationModal<?php echo $application['application_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-file-alt me-2"></i>
                                                        Application Details
                                                    </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                    <div class="row g-4">
                                                    <div class="col-md-6">
                                                            <div class="modern-card" style="margin-bottom: 0;">
                                                                <div class="modern-card-header" style="padding: 1rem;">
                                                                    <h6 class="mb-0"><i class="fas fa-briefcase me-2"></i>Job Information</h6>
                                                                </div>
                                                                <div class="modern-card-body" style="padding: 1rem;">
                                                                    <div class="mb-3">
                                                                        <label class="fw-semibold small text-muted">POSITION</label>
                                                                        <div><?php echo htmlspecialchars($application['job_title']); ?></div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-semibold small text-muted">COMPANY</label>
                                                                        <div><?php echo htmlspecialchars($application['company_name']); ?></div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-semibold small text-muted">TYPE</label>
                                                                        <div><?php echo htmlspecialchars($application['job_type']); ?></div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-semibold small text-muted">LOCATION</label>
                                                                        <div><?php echo htmlspecialchars($application['location']); ?></div>
                                                                    </div>
                                                                    <?php if ($application['salary_range']): ?>
                                                                    <div class="mb-0">
                                                                        <label class="fw-semibold small text-muted">SALARY RANGE</label>
                                                                        <div><span class="me-1">₱</span><?php echo htmlspecialchars($application['salary_range']); ?></div>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                            <div class="modern-card" style="margin-bottom: 0;">
                                                                <div class="modern-card-header" style="padding: 1rem;">
                                                                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Application Status</h6>
                                                                </div>
                                                                <div class="modern-card-body" style="padding: 1rem;">
                                                                    <div class="mb-3">
                                                                        <label class="fw-semibold small text-muted">APPLIED DATE</label>
                                                                        <div><?php echo date('M d, Y g:i A', strtotime($application['application_date'])); ?></div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="fw-semibold small text-muted">STATUS</label>
                                                                        <div>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                                <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                                                <?php echo ucfirst(htmlspecialchars($application['status'])); ?>
                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <?php if ($application['match_score']): ?>
                                                                    <div class="mb-3">
                                                                        <label class="fw-semibold small text-muted">SKILL MATCH</label>
                                                                        <div class="progress mb-2" style="height: 8px;">
                                                                            <div class="progress-bar" style="width: <?php echo $application['match_score']; ?>%"></div>
                                                                        </div>
                                                                        <div class="small"><?php echo round($application['match_score']); ?>% compatibility</div>
                                                                    </div>
                                                                    <?php endif; ?>
                                                        <?php if (isset($application['feedback']) && $application['feedback']): ?>
                                                                    <div class="mb-0">
                                                                        <label class="fw-semibold small text-muted">FEEDBACK</label>
                                                                        <div class="small"><?php echo htmlspecialchars($application['feedback']); ?></div>
                                                                    </div>
                                                        <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($application['cover_letter']): ?>
                                                    <div class="mt-4">
                                                        <div class="modern-card" style="margin-bottom: 0;">
                                                            <div class="modern-card-header" style="padding: 1rem;">
                                                                <h6 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Attachments</h6>
                                                </div>
                                                            <div class="modern-card-body" style="padding: 1rem;">
                                                                <a href="<?php echo '../' . htmlspecialchars($application['cover_letter']); ?>" 
                                                                   class="btn btn-outline-primary" target="_blank">
                                                                    <i class="fas fa-download me-2"></i>Download Cover Letter
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                                    <a href="view-job.php?id=<?php echo $application['job_id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-external-link-alt me-1"></i>View Job Posting
                                                    </a>
                                                </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>

<!-- Withdraw Application Form -->
<form id="withdrawForm" method="POST" style="display: none;">
    <input type="hidden" name="withdraw_application" value="1">
    <input type="hidden" name="application_id" id="withdrawApplicationId">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sidebar.js"></script>

<script>
// Status Filter
document.getElementById('statusFilter').addEventListener('change', function() {
    const selectedStatus = this.value;
    const rows = document.querySelectorAll('#applicationsTable tbody tr');
    
    rows.forEach(row => {
        if (selectedStatus === '' || row.dataset.status === selectedStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Toggle between Board and List views
const kanbanBoard = document.getElementById('kanbanBoard');
const btnBoard = document.getElementById('btnBoard');
const btnList = document.getElementById('btnList');
const listCard = document.querySelector('.modern-card');

if (btnBoard && btnList && kanbanBoard && listCard) {
    btnBoard.addEventListener('click', function() {
        kanbanBoard.classList.add('active');
        listCard.style.display = 'none';
        btnBoard.classList.add('active');
        btnList.classList.remove('active');
    });
    btnList.addEventListener('click', function() {
        kanbanBoard.classList.remove('active');
        listCard.style.display = '';
        btnList.classList.add('active');
        btnBoard.classList.remove('active');
    });
}

// Withdraw Application
function withdrawApplication(applicationId) {
    if (confirm('Are you sure you want to withdraw this application? This action cannot be undone.')) {
        document.getElementById('withdrawApplicationId').value = applicationId;
        document.getElementById('withdrawForm').submit();
    }
}

// Add loading states and animations
document.addEventListener('DOMContentLoaded', function() {
    // Add fade-in animation to table rows
    const rows = document.querySelectorAll('#applicationsTable tbody tr');
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        setTimeout(() => {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50);
    });
});
</script>

</body>
</html>