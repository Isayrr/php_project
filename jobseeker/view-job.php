<?php
session_start();
require_once '../config/database.php';

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}

// Store referrer URL for back navigation
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';
$back_url = (strpos($referrer, 'view-job.php') !== false) ? '../index.php' : $referrer;

$job_id = (int)$_GET['id'];
$error = null;
$job = null;
$job_skills = [];
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'jobseeker';

try {
    // Get job details with company information
    $stmt = $conn->prepare("
        SELECT j.*, c.company_name, c.company_logo, c.industry, 
               jc.category_name, 
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        LEFT JOIN job_categories jc ON j.category_id = jc.category_id
        WHERE j.job_id = ? AND j.status = 'active'
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        // If job not found or inactive, try to get it anyway but show as inactive
        $stmt = $conn->prepare("
            SELECT j.*, c.company_name, c.company_logo, c.industry, 
                   jc.category_name, 
                   (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count
            FROM jobs j
            JOIN companies c ON j.company_id = c.company_id
            LEFT JOIN job_categories jc ON j.category_id = jc.category_id
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            $error = "Job not found or has been removed.";
        } else {
            $error = "This job is no longer active.";
        }
    }
    
    if ($job) {
        // Get skills required for this job
        $stmt = $conn->prepare("
            SELECT s.skill_name 
            FROM job_skills js
            JOIN skills s ON js.skill_id = s.skill_id
            WHERE js.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job_skills = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Check if user already applied (only if logged in)
    $already_applied = 0;
    $has_resume = false;
    if ($is_logged_in && $job) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND jobseeker_id = ?");
        $stmt->execute([$job_id, $_SESSION['user_id']]);
        $already_applied = $stmt->fetchColumn();

        // Check if user has a resume and file exists
        $stmt = $conn->prepare("SELECT resume FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $resume = $stmt->fetchColumn();
        $has_resume = !empty($resume) && file_exists('../' . $resume);
    }
    
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Set page title
$page_title = $job ? htmlspecialchars($job['title']) . ' - Job Details' : 'Job Details';
?>
<?php include 'includes/header.php'; ?>

    <style>
        body {
            background-color: rgba(0, 0, 0, 0.5);
    }
    
    .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
        min-height: calc(100vh - 80px);
            margin: 0;
        padding: 20px;
        }
        
        .modal-container {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 20px;
            position: relative;
        }
        
        .modal-body {
            padding: 20px;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .close-button {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
            padding: 0;
            line-height: 1;
        }
        
        .company-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            background: white;
            border-radius: 8px;
            padding: 5px;
            border: 1px solid #e0e0e0;
        }
        
        .job-description {
            white-space: pre-line;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: #333;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 8px;
            align-items: center;
        }
        
        .detail-icon {
            width: 35px;
            color: #3498db;
            font-size: 16px;
            text-align: center;
        }
        
        .detail-text {
            flex: 1;
            color: #333;
        }
        
        .skill-badge {
            background-color: #e9f7fe;
            color: #3498db;
            border: 1px solid #3498db;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 5px;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
        }
        
        .apply-button {
            background-color: #3498db;
            color: white;
            border: none;
        padding: 12px 24px;
            border-radius: 5px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: background-color 0.3s;
        }
        
        .apply-button:hover {
            background-color: #2980b9;
        color: white;
        }
        
        .apply-button:disabled {
        background-color: #95a5a6;
            cursor: not-allowed;
        }
        
    .back-button {
        background-color: #95a5a6;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 5px;
            font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: background-color 0.3s;
        }
        
    .back-button:hover {
        background-color: #7f8c8d;
        color: white;
    }
    
    .status-inactive {
        background-color: #e74c3c;
        color: white;
        padding: 8px 16px;
        border-radius: 5px;
        font-weight: 600;
            margin-bottom: 15px;
        display: inline-block;
        }
    </style>

    <div class="modal-container">
    <?php if ($error): ?>
            <div class="modal-header">
            <h2>Error</h2>
            <button class="close-button" onclick="window.history.back();">&times;</button>
            </div>
            <div class="modal-body">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
            <div class="modal-footer">
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="back-button">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
            </div>
        <?php else: ?>
            <div class="modal-header">
            <div class="d-flex align-items-center">
                    <?php if (!empty($job['company_logo'])): ?>
                        <img src="../uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" 
                         alt="Company Logo" class="company-logo me-3">
                    <?php endif; ?>
                    <div>
                    <h2 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h2>
                    <h5 class="text-muted mb-0"><?php echo htmlspecialchars($job['company_name']); ?></h5>
                </div>
            </div>
            <button class="close-button" onclick="window.history.back();">&times;</button>
            </div>

            <div class="modal-body">
            <?php if ($job['status'] !== 'active'): ?>
                <div class="status-inactive">
                    <i class="fas fa-exclamation-triangle"></i> This job is no longer active
                    </div>
                <?php endif; ?>
                
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="section-title">Job Details</div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-briefcase"></i></div>
                        <div class="detail-text"><strong>Type:</strong> <?php echo htmlspecialchars($job['job_type']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="detail-text"><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></div>
                </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="detail-text"><strong>Salary:</strong> ₱<?php echo htmlspecialchars($job['salary_range']); ?></div>
                        </div>
                    <?php if (!empty($job['category_name'])): ?>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-tag"></i></div>
                        <div class="detail-text"><strong>Category:</strong> <?php echo htmlspecialchars($job['category_name']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <div class="col-md-6">
                    <div class="section-title">Company Info</div>
                        <div class="detail-row">
                            <div class="detail-icon"><i class="fas fa-building"></i></div>
                        <div class="detail-text"><strong>Company:</strong> <?php echo htmlspecialchars($job['company_name']); ?></div>
                        </div>
                    <?php if (!empty($job['industry'])): ?>
                        <div class="detail-row">
                            <div class="detail-icon"><i class="fas fa-industry"></i></div>
                        <div class="detail-text"><strong>Industry:</strong> <?php echo htmlspecialchars($job['industry']); ?></div>
                        </div>
                    <?php endif; ?>
                        <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-calendar"></i></div>
                        <div class="detail-text"><strong>Posted:</strong> <?php echo date('M d, Y', strtotime($job['posted_date'])); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-icon"><i class="fas fa-users"></i></div>
                        <div class="detail-text"><strong>Applicants:</strong> <?php echo $job['application_count']; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="section-title">Job Description</div>
            <div class="job-description"><?php echo nl2br(htmlspecialchars($job['description'])); ?></div>
            
            <?php if (!empty($job['requirements'])): ?>
                <div class="section-title">Requirements</div>
                <div class="job-description"><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($job_skills)): ?>
                <div class="section-title">Required Skills</div>
                <div class="mb-3">
                    <?php foreach ($job_skills as $skill): ?>
                        <span class="skill-badge"><?php echo htmlspecialchars($skill); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="modal-footer">
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="back-button">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            
            <div>
                <?php if ($is_logged_in): ?>
                    <?php if ($job['status'] === 'active'): ?>
                        <?php if ($already_applied): ?>
                            <button class="apply-button" disabled>
                                <i class="fas fa-check"></i> Already Applied
                            </button>
                        <?php elseif (!$has_resume): ?>
                            <a href="profile.php" class="apply-button" style="background-color: #dc3545;">
                                <i class="fas fa-exclamation-circle"></i> Upload Resume First
                            </a>
                        <?php else: ?>
                            <a href="apply-job.php?id=<?php echo $job['job_id']; ?>" class="apply-button">
                                <i class="fas fa-paper-plane"></i> Apply Now
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="apply-button" disabled>
                            <i class="fas fa-times"></i> Job Inactive
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="../auth/login.php?redirect=jobseeker/view-job.php?id=<?php echo $job_id; ?>" class="apply-button">
                        <i class="fas fa-sign-in-alt"></i> Login to Apply
                    </a>
                <?php endif; ?>
            </div>
            </div>
        <?php endif; ?>
    </div>

<?php include 'includes/footer.php'; ?> 