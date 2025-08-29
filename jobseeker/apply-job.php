<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../auth/login.php?redirect=jobseeker/jobs.php");
    exit();
}

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: jobs.php");
    exit();
}

$job_id = (int)$_GET['id'];
$error = null;
$success = null;
$job = null;
$already_applied = false;
$skill_match_data = null;
$jobseeker_profile_complete = true;

// Store referrer URL for back navigation
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'jobs.php';
$back_url = (strpos($referrer, 'apply-job.php') !== false) ? 'jobs.php' : $referrer;

try {
    // Get job details
    $stmt = $conn->prepare("
        SELECT j.*, c.company_name, c.company_logo
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.job_id = ? AND j.status = 'active'
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        throw new Exception("This job is no longer available or has been removed.");
    }
    
    // Check if user has already applied
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND jobseeker_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $already_applied = $stmt->fetchColumn() > 0;
    
    if ($already_applied) {
        $error = "You have already applied for this job.";
    }
    
    // Check profile completeness
    $stmt = $conn->prepare("
        SELECT up.*, u.email, u.username
        FROM user_profiles up
        JOIN users u ON up.user_id = u.user_id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile || empty($profile['resume']) || !file_exists('../' . $profile['resume'])) {
        $jobseeker_profile_complete = false;
        $error = "Please update your profile and upload a resume before applying for jobs.";
    }
    
    // Get skill match data if we have the necessary tables
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT js.skill_id) as matching_skills,
                COUNT(DISTINCT j.skill_id) as required_skills,
                GROUP_CONCAT(DISTINCT s1.skill_name) as matching_skills_list,
                GROUP_CONCAT(DISTINCT s2.skill_name) as missing_skills_list
            FROM job_skills j
            LEFT JOIN jobseeker_skills js ON js.skill_id = j.skill_id AND js.jobseeker_id = ?
            LEFT JOIN skills s1 ON s1.skill_id = js.skill_id
            LEFT JOIN skills s2 ON s2.skill_id = j.skill_id AND NOT EXISTS (
                SELECT 1 FROM jobseeker_skills js2 
                WHERE js2.jobseeker_id = ? AND js2.skill_id = j.skill_id
            )
            WHERE j.job_id = ?
            GROUP BY j.job_id
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $job_id]);
        $skill_match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($skill_match && isset($skill_match['required_skills']) && $skill_match['required_skills'] > 0) {
            $match_percent = round(($skill_match['matching_skills'] / $skill_match['required_skills']) * 100);
                
            $skill_match_data = [
                'percent' => $match_percent,
                'matching' => !empty($skill_match['matching_skills_list']) ? explode(',', $skill_match['matching_skills_list']) : [],
                'missing' => !empty($skill_match['missing_skills_list']) ? explode(',', $skill_match['missing_skills_list']) : []
            ];
            
            // Remove empty elements
            $skill_match_data['matching'] = array_filter($skill_match_data['matching']);
            $skill_match_data['missing'] = array_filter($skill_match_data['missing']);
        }
    } catch (Exception $e) {
        // If there's an error with skill matching, just continue without it
        $skill_match_data = null;
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_applied) {
        // Validate input
        $cover_letter = trim($_POST['cover_letter'] ?? '');
        
        // Check resume option
        $resume_option = $_POST['resume_option'] ?? '';
        $resume_path = null;
        
        if ($resume_option === 'saved' && !empty($_POST['resume_id'])) {
            // User selected a resume from Resume Maker
            $selected_resume_id = (int)$_POST['resume_id'];
            
            // Verify this resume belongs to the user
            $stmt = $conn->prepare("SELECT r.resume_id FROM resumes r WHERE r.resume_id = ? AND r.user_id = ?");
            $stmt->execute([$selected_resume_id, $_SESSION['user_id']]);
            $resume_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resume_check) {
                throw new Exception("Invalid resume selection.");
            }
            
            // Use the resume ID directly (we'll handle it differently in the application)
            $resume_path = "maker:" . $selected_resume_id;
        } else {
            // Handle resume upload
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
                $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $file_type = $_FILES['resume']['type'];
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("Invalid file type. Please upload PDF or Word documents only.");
                }
                
                if ($_FILES['resume']['size'] > 5000000) { // 5MB limit
                    throw new Exception("File size is too large. Maximum size is 5MB.");
                }
                
                $filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '_' . $_FILES['resume']['name'];
                $upload_dir = '../uploads/resumes/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_path)) {
                    $resume_path = 'resumes/' . $filename;
                } else {
                    throw new Exception("Failed to upload resume file.");
                }
            }
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Insert application
            $stmt = $conn->prepare("
                INSERT INTO applications (
                    job_id, 
                    jobseeker_id, 
                    cover_letter, 
                    resume_path,
                    application_date, 
                    status
                ) VALUES (?, ?, ?, ?, NOW(), 'pending')
            ");
            $stmt->execute([
                $job_id, 
                $_SESSION['user_id'], 
                $cover_letter,
                $resume_path
            ]);
            $application_id = $conn->lastInsertId();
            
            // Add skill match data if available
            if ($skill_match_data) {
                // Check if skill_matches table exists
                $check_table = $conn->query("SHOW TABLES LIKE 'skill_matches'");
                if ($check_table && $check_table->fetch()) {
                    $stmt = $conn->prepare("
                        INSERT INTO skill_matches (
                            application_id, 
                            match_score, 
                            matching_skills, 
                            missing_skills
                        ) VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $application_id,
                        $skill_match_data['percent'],
                        implode(', ', $skill_match_data['matching']),
                        implode(', ', $skill_match_data['missing'])
                    ]);
                }
            }
            
            $conn->commit();
            $success = "Your application has been successfully submitted!";

            // Notifications: admin and employer
            try {
                require_once __DIR__ . '/../admin/includes/admin_notifications.php';
                require_once __DIR__ . '/../employer/includes/employer_notifications.php';

                // Notify admins with application id and details
                $applicant_name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
                notifyAdminNewApplication($conn, $application_id, $job['title'], $applicant_name);

                // Notify employer for this job
                notifyEmployerNewApplication($conn, $job_id);
            } catch (Exception $e) {
                // Fail silently; do not block application submission
                error_log('Notification error (apply-job): ' . $e->getMessage());
            }
            $already_applied = true;
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Set page title
$page_title = $job ? 'Apply for ' . htmlspecialchars($job['title']) : 'Job Application';
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
        
        .close-button:hover {
            color: #212529;
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
        
        .job-title {
            margin: 0;
            color: #2c3e50;
            font-size: 22px;
            font-weight: 600;
        }
        
        .company-name {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 0;
        }
        
        .section-title {
            color: #333;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
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
        
        .skill-badge.missing {
            background-color: #fff4e9;
            color: #f39c12;
            border: 1px solid #f39c12;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .error-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .message-container {
            text-align: center;
            padding: 30px 20px;
        }
        
        .form-check-label {
            font-size: 14px;
            color: #6c757d;
        }
        
        .profile-alert {
            background-color: #fff3cd;
            border-color: #ffeeba;
            color: #856404;
            font-size: 14px;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>

    <div class="modal-container">
        <?php if (!$job): ?>
            <div class="modal-header">
                <h3>Job Application</h3>
            <button type="button" class="close-button" onclick="window.history.back()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="message-container">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Job not found</h3>
                    <p class="text-muted">The job you're trying to apply for doesn't exist or has been removed.</p>
                </div>
            </div>
            <div class="modal-footer">
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-secondary">Back to Jobs</a>
            </div>
        <?php elseif ($success): ?>
            <div class="modal-header">
                <h3>Application Submitted</h3>
            <button type="button" class="close-button" onclick="window.history.back()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="message-container">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                <h3>Application Submitted Successfully!</h3>
                <p class="text-muted">Your application for <strong><?php echo htmlspecialchars($job['title']); ?></strong> has been submitted to <?php echo htmlspecialchars($job['company_name']); ?>.</p>
                <p class="text-muted">You will be notified when the employer reviews your application.</p>
                </div>
            </div>
            <div class="modal-footer">
            <a href="applications.php" class="btn btn-primary">View My Applications</a>
            <a href="jobs.php" class="btn btn-secondary">Browse More Jobs</a>
            </div>
        <?php elseif ($error && !$jobseeker_profile_complete): ?>
            <div class="modal-header">
                <h3>Application Error</h3>
                <button type="button" class="close-button" onclick="window.history.back()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="message-container">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Unable to Submit Application</h3>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="profile.php" class="btn btn-primary">Go to Profile</a>
            </div>
        <?php elseif ($error): ?>
            <div class="modal-header">
                <h3>Application Error</h3>
                <button type="button" class="close-button" onclick="window.history.back()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="message-container">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Unable to Submit Application</h3>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-secondary">Go Back</a>
            </div>
        <?php else: ?>
        <!-- Application Form -->
            <div class="modal-header">
            <div class="d-flex align-items-center">
                    <?php if (!empty($job['company_logo'])): ?>
                        <img src="../uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" 
                         alt="Company Logo" class="company-logo me-3">
                    <?php endif; ?>
                    <div>
                    <h2 class="job-title">Apply for <?php echo htmlspecialchars($job['title']); ?></h2>
                        <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                </div>
            </div>
            <button type="button" class="close-button" onclick="window.history.back()">&times;</button>
            </div>
            
            <div class="modal-body">
                <?php if (!$jobseeker_profile_complete): ?>
                <div class="profile-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Profile Incomplete:</strong> Please complete your profile and upload a resume before applying.
                    <a href="profile.php" class="alert-link">Complete Profile</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($skill_match_data): ?>
                <div class="section-title">Skill Match Analysis</div>
                <div class="mb-4">
                            <div class="progress-label">
                        <span>Your Skills Match</span>
                                <span><?php echo $skill_match_data['percent']; ?>%</span>
                            </div>
                            <div class="progress mb-3">
                        <div class="progress-bar <?php 
                            if ($skill_match_data['percent'] == 100) echo 'bg-success';
                            elseif ($skill_match_data['percent'] >= 80) echo 'bg-warning';
                            else echo 'bg-danger';
                        ?>" role="progressbar" 
                            style="width: <?php echo $skill_match_data['percent']; ?>%"
                            aria-valuenow="<?php echo $skill_match_data['percent']; ?>" 
                            aria-valuemin="0" aria-valuemax="100">
                            <?php echo $skill_match_data['percent']; ?>%
                        </div>
                            </div>
                            
                            <?php if (!empty($skill_match_data['matching'])): ?>
                                <div class="mb-3">
                            <strong class="text-success">Your Matching Skills:</strong><br>
                                    <?php foreach ($skill_match_data['matching'] as $skill): ?>
                                <span class="skill-badge"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($skill_match_data['missing'])): ?>
                        <div class="mb-3">
                            <strong class="text-warning">Skills to Develop:</strong><br>
                                    <?php foreach ($skill_match_data['missing'] as $skill): ?>
                                <span class="skill-badge missing"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <form method="POST" enctype="multipart/form-data" <?php echo !$jobseeker_profile_complete ? 'style="pointer-events: none; opacity: 0.6;"' : ''; ?>>
                <div class="section-title">Application Details</div>
                
                <div class="mb-3">
                    <label for="cover_letter" class="form-label">Cover Letter (Optional)</label>
                    <textarea class="form-control" id="cover_letter" name="cover_letter" rows="6" 
                              placeholder="Write a brief cover letter explaining why you're interested in this position..."></textarea>
                    <div class="form-text">A personalized cover letter can help your application stand out.</div>
                    </div>
                    
                <div class="mb-3">
                        <label class="form-label">Resume</label>
                                <div class="form-check">
                        <input class="form-check-input" type="radio" name="resume_option" id="upload_resume" value="upload" checked>
                        <label class="form-check-label" for="upload_resume">
                            Upload a new resume
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="resume_option" id="saved_resume" value="saved">
                        <label class="form-check-label" for="saved_resume">
                                        Use a resume from Resume Maker
                                    </label>
                        </div>
                        
                    <div id="upload_section">
                        <input type="file" class="form-control" name="resume" accept=".pdf,.doc,.docx">
                        <div class="form-text">Upload your resume (PDF or Word document, max 5MB)</div>
                    </div>
                    
                    <div id="saved_section" style="display: none;">
                        <select class="form-select" name="resume_id">
                            <option value="">Select a resume...</option>
                            <!-- Resume options would be populated here -->
                        </select>
                    </div>
            </div>
            
                <div class="d-flex justify-content-between">
                    <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" <?php echo !$jobseeker_profile_complete ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
                </div>
            </form>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Handle resume option toggle
        document.addEventListener('DOMContentLoaded', function() {
        const uploadRadio = document.getElementById('upload_resume');
        const savedRadio = document.getElementById('saved_resume');
        const uploadSection = document.getElementById('upload_section');
        const savedSection = document.getElementById('saved_section');
        
        function toggleResumeSection() {
            if (uploadRadio.checked) {
                uploadSection.style.display = 'block';
                savedSection.style.display = 'none';
            } else {
                uploadSection.style.display = 'none';
                savedSection.style.display = 'block';
                        }
        }
                    
        uploadRadio.addEventListener('change', toggleResumeSection);
        savedRadio.addEventListener('change', toggleResumeSection);
        });
    </script>

<?php include 'includes/footer.php'; ?> 