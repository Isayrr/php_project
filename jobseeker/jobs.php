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
$jobs = [];
$search = '';
$job_type = '';
$location = '';
$category_id = '';
$has_resume = false;

try {
    // Check if user has uploaded a resume and file exists
    $stmt = $conn->prepare("SELECT resume FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $resume = $stmt->fetchColumn();
    $has_resume = !empty($resume) && file_exists('../' . $resume);

    // Get all job categories for filter
    $stmt = $conn->prepare("SELECT * FROM job_categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all available jobs with company details
    $query = "
        SELECT j.*, c.company_name, c.company_logo, jc.category_name,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.jobseeker_id = ?) as already_applied,
               j.vacancies
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        LEFT JOIN job_categories jc ON j.category_id = jc.category_id
        WHERE j.status = 'active' AND j.approval_status = 'approved'
    ";
    $params = [$_SESSION['user_id']];

    // Apply search filters
    if (!empty($_GET['search'])) {
        $search = $_GET['search'];
        $query .= " AND (j.title LIKE ? OR j.description LIKE ? OR c.company_name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }

    if (!empty($_GET['job_type'])) {
        $job_type = $_GET['job_type'];
        $query .= " AND j.job_type = ?";
        $params[] = $job_type;
    }

    if (!empty($_GET['location'])) {
        $location = $_GET['location'];
        $query .= " AND j.location LIKE ?";
        $params[] = "%$location%";
    }
    
    if (!empty($_GET['category_id'])) {
        $category_id = $_GET['category_id'];
        $query .= " AND j.category_id = ?";
        $params[] = $category_id;
    }

    $query .= " ORDER BY j.posted_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle job application
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
        // Check if user has a resume
        if (!$has_resume) {
            throw new Exception("You must upload your resume before applying to jobs.");
        }

        // Check if already applied
        $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND jobseeker_id = ?");
        $stmt->execute([$_POST['job_id'], $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("You have already applied for this job.");
        }

        // Insert application
        $stmt = $conn->prepare("INSERT INTO applications (job_id, jobseeker_id, application_date, status) 
                               VALUES (?, ?, NOW(), 'pending')");
        $stmt->execute([
            $_POST['job_id'],
            $_SESSION['user_id']
        ]);

        $success = "Application submitted successfully!";
        // Trigger notifications
        try {
            require_once __DIR__ . '/../admin/includes/admin_notifications.php';
            require_once __DIR__ . '/../employer/includes/employer_notifications.php';
            
            // Fetch applicant name
            $stmt = $conn->prepare("SELECT first_name, last_name FROM user_profiles WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $applicant_name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            
            $application_id = $conn->lastInsertId();
            notifyAdminNewApplication($conn, $application_id, (string)($_POST['job_title'] ?? 'Job'), $applicant_name);
            notifyEmployerNewApplication($conn, (int)$_POST['job_id']);
        } catch (Exception $e) {
            error_log('Notification error (jobs list apply): ' . $e->getMessage());
        }
    }

} catch(Exception $e) {
    $error = $e->getMessage();
}

// Calculate statistics
$total_jobs = count($jobs);
$applied_jobs = count(array_filter($jobs, function($job) { return $job['already_applied'] > 0; }));
$new_jobs = count(array_filter($jobs, function($job) { 
    return strtotime($job['posted_date']) > strtotime('-7 days'); 
}));

// Set page title
$page_title = "Find Jobs - Job Seeker Panel";
?>
<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">Find Your Dream Job</h1>
                    <p class="page-subtitle">Explore thousands of opportunities and apply with one click</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="applications.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-alt"></i> My Applications
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
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-number"><?php echo $total_jobs; ?></div>
                <div class="stat-label">Available Jobs</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $applied_jobs; ?></div>
                <div class="stat-label">Already Applied</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon warning">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="stat-number"><?php echo $new_jobs; ?></div>
                <div class="stat-label">New This Week</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon info">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo count($categories); ?></div>
                <div class="stat-label">Categories</div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h5 class="modern-card-title">
                    <i class="fas fa-filter"></i>
                    Filter Jobs
                </h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            </div>
            <div class="modern-card-body">
                <form method="GET" class="row g-3" id="filterForm">
                    <div class="col-md-4">
                        <label class="form-label">Search Jobs</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Job title, company, or keywords" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Job Type</label>
                        <select class="form-select" name="job_type">
                            <option value="">All Types</option>
                            <option value="Full-time" <?php echo $job_type === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                            <option value="Part-time" <?php echo $job_type === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                            <option value="Contract" <?php echo $job_type === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                            <option value="Internship" <?php echo $job_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo $category_id == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location" 
                               placeholder="City or area" 
                               value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$has_resume): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Upload Resume Required!</strong> Please upload your resume before applying to jobs.
                <a href="profile.php#documents" class="alert-link ms-2">
                    <i class="fas fa-upload me-1"></i>Upload Resume Now
                </a>
            </div>
        <?php endif; ?>

        <!-- Jobs Results -->
        <?php if (empty($jobs)): ?>
            <!-- Empty State -->
            <div class="modern-card text-center">
                <div class="modern-card-body" style="padding: 4rem 2rem;">
                    <div class="mb-4">
                        <i class="fas fa-search" style="font-size: 4rem; color: var(--text-muted);"></i>
                    </div>
                    <h3 class="mb-3">No Jobs Found</h3>
                    <p class="text-muted mb-4">We couldn't find any jobs matching your criteria. Try adjusting your filters or search terms.</p>
                    <button onclick="clearFilters()" class="btn btn-primary">
                        <i class="fas fa-refresh"></i> Clear Filters
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Jobs Grid -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-list"></i>
                        Job Listings
                    </h5>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="text-muted small"><?php echo count($jobs); ?> jobs found</span>
                        <select class="form-select form-select-sm" style="width: auto;" onchange="sortJobs(this.value)">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="company">Company A-Z</option>
                        </select>
                    </div>
                </div>
                <div class="modern-card-body">
                    <div class="row g-4" id="jobsContainer">
                        <?php foreach ($jobs as $job): ?>
                            <div class="col-lg-6 col-xl-4 job-item" 
                                 data-posted="<?php echo strtotime($job['posted_date']); ?>" 
                                 data-company="<?php echo htmlspecialchars($job['company_name']); ?>">
                                <div class="modern-card h-100" style="margin-bottom: 0; transition: all 0.3s ease;">
                                    <div class="modern-card-body" style="padding: 1.5rem;">
                                        <!-- Company Header -->
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="me-3" style="width: 50px; height: 50px; border-radius: 12px; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px solid var(--border-color);">
                                                <?php if (!empty($job['company_logo']) && file_exists('../uploads/company_logos/' . $job['company_logo'])): ?>
                                                    <img src="../uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                                         alt="<?php echo htmlspecialchars($job['company_name']); ?>"
                                                         style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-building text-muted"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                                <div class="text-muted small"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                                <?php if ($job['category_name']): ?>
                                                    <span class="badge bg-primary" style="font-size: 0.7rem;">
                                                        <?php echo htmlspecialchars($job['category_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Job Details -->
                                        <div class="mb-3">
                                            <div class="d-flex gap-3 mb-2 small text-muted">
                                                <span><i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($job['job_type']); ?></span>
                                                <span><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                            </div>
                                            <div class="d-flex gap-3 small text-muted">
                                                <span><i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></span>
                                                <span><i class="fas fa-users me-1"></i><?php echo htmlspecialchars($job['vacancies']); ?> openings</span>
                                            </div>
                                            <?php if ($job['salary_range']): ?>
                                                <div class="mt-2">
                                                    <span class="badge bg-success">
                                                        <span class="me-1">₱</span><?php echo htmlspecialchars($job['salary_range']); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Application Count -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="small text-muted">
                                                    <i class="fas fa-user-check me-1"></i>
                                                    <?php echo $job['application_count']; ?> applicants
                                                </span>
                                                <?php if (strtotime($job['posted_date']) > strtotime('-7 days')): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-star me-1"></i>New
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="d-flex gap-2">
                                            <a href="view-job.php?id=<?php echo $job['job_id']; ?>" 
                                               class="btn btn-outline-primary btn-sm flex-grow-1">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                            
                                            <?php if ($job['already_applied']): ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="fas fa-check me-1"></i>Applied
                                                </button>
                                            <?php elseif (!$has_resume): ?>
                                                <a href="profile.php#documents" 
                                                   class="btn btn-warning btn-sm" 
                                                   title="Upload resume first">
                                                    <i class="fas fa-upload me-1"></i>Resume
                                                </a>
                                            <?php else: ?>
                                                <button type="button" 
                                                        class="btn btn-success btn-sm apply-btn"
                                                        data-job-id="<?php echo $job['job_id']; ?>"
                                                        data-job-title="<?php echo htmlspecialchars($job['title']); ?>">
                                                    <i class="fas fa-paper-plane me-1"></i>Apply
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Apply Modal -->
<div class="modal fade" id="quickApplyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane me-2"></i>
                    Quick Apply
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-briefcase" style="font-size: 3rem; color: var(--primary-gradient);"></i>
                </div>
                <h6 class="text-center mb-3" id="modalJobTitle"></h6>
                <p class="text-center text-muted">Are you sure you want to apply for this position? Your resume and profile will be submitted to the employer.</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>What happens next?</strong><br>
                    • Your application will be sent immediately<br>
                   
                    • The employer will review your profile<br>
                    • You can track status in your applications
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApplyBtn">
                    <i class="fas fa-paper-plane me-1"></i>Confirm Application
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                <span id="toastMessage">Application submitted successfully!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sidebar.js"></script>

<script>
// Initialize modals and toasts
const quickApplyModal = new bootstrap.Modal(document.getElementById('quickApplyModal'));
const successToast = new bootstrap.Toast(document.getElementById('successToast'));

let currentJobId = null;

// Function to check resume status and update UI
function checkResumeStatus() {
    fetch('check-resume-status.php')
        .then(response => response.json())
        .then(data => {
            if (data.has_resume) {
                // Remove upload resume warning
                const warning = document.querySelector('.alert-warning');
                if (warning && warning.textContent.includes('Upload Resume Required')) {
                    warning.style.display = 'none';
                }
                
                // Update all upload resume buttons to apply buttons
                document.querySelectorAll('.btn-warning[title="Upload resume first"]').forEach(btn => {
                    const jobId = btn.closest('.modern-card').querySelector('[data-job-id]')?.dataset.jobId;
                    const jobTitle = btn.closest('.modern-card').querySelector('[data-job-title]')?.dataset.jobTitle;
                    
                    if (jobId && jobTitle) {
                        btn.outerHTML = `
                            <button type="button" 
                                    class="btn btn-success btn-sm apply-btn"
                                    data-job-id="${jobId}"
                                    data-job-title="${jobTitle}">
                                <i class="fas fa-paper-plane me-1"></i>Apply
                            </button>
                        `;
                    }
                });
                
                // Re-attach event listeners to new apply buttons
                attachApplyButtonListeners();
            }
        })
        .catch(error => {
            console.log('Resume status check failed:', error);
        });
}

// Attach event listeners to apply buttons
function attachApplyButtonListeners() {
    document.querySelectorAll('.apply-btn').forEach(btn => {
        btn.removeEventListener('click', handleApplyClick); // Remove existing listener
        btn.addEventListener('click', handleApplyClick);
    });
}

// Handle apply button clicks
function handleApplyClick() {
    currentJobId = this.dataset.jobId;
    document.getElementById('modalJobTitle').textContent = this.dataset.jobTitle;
    quickApplyModal.show();
}

// Initial attachment of event listeners
document.querySelectorAll('.apply-btn').forEach(btn => {
    btn.addEventListener('click', handleApplyClick);
});

// Handle confirm application
document.getElementById('confirmApplyBtn').addEventListener('click', function() {
    if (!currentJobId) return;
    
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Applying...';
    
    // Send application
    fetch('apply-job-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'job_id=' + encodeURIComponent(currentJobId)
    })
    .then(response => response.json())
    .then(data => {
        quickApplyModal.hide();
        
        if (data.success) {
            // Update the apply button to "Applied"
            const applyBtn = document.querySelector(`[data-job-id="${currentJobId}"]`);
            if (applyBtn) {
                applyBtn.outerHTML = `
                    <button class="btn btn-secondary btn-sm" disabled>
                        <i class="fas fa-check me-1"></i>Applied
                    </button>
                `;
            }
            
            // Show success message
            document.getElementById('toastMessage').textContent = data.message;
            successToast.show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        quickApplyModal.hide();
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Confirm Application';
    });
});

// Clear filters function
function clearFilters() {
    window.location.href = 'jobs.php';
}

// Sort jobs function
function sortJobs(sortBy) {
    const container = document.getElementById('jobsContainer');
    const jobs = Array.from(container.querySelectorAll('.job-item'));
    
    jobs.sort((a, b) => {
        switch(sortBy) {
            case 'newest':
                return parseInt(b.dataset.posted) - parseInt(a.dataset.posted);
            case 'oldest':
                return parseInt(a.dataset.posted) - parseInt(b.dataset.posted);
            case 'company':
                return a.dataset.company.localeCompare(b.dataset.company);
            default:
                return 0;
        }
    });
    
    // Re-append sorted jobs
    jobs.forEach(job => container.appendChild(job));
}

// Add loading animation to job cards
document.addEventListener('DOMContentLoaded', function() {
    const jobCards = document.querySelectorAll('.job-item .modern-card');
    jobCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add hover effects
    jobCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = 'var(--shadow-lg)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'var(--shadow-sm)';
        });
    });
    
    // Check resume status every 30 seconds
    setInterval(checkResumeStatus, 30000);
});

// Listen for profile updates (if user navigates back from profile page)
window.addEventListener('focus', function() {
    setTimeout(checkResumeStatus, 1000); // Check resume status when window gains focus
});
</script>

</body>
</html> 