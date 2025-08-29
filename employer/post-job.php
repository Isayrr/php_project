<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notifications.php';
require_once '../includes/job_utils.php';
require_once '../includes/common_data.php';
require_once '../admin/includes/admin_notifications.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;

try {
    // Get company ID
    $stmt = $conn->prepare("SELECT company_id, company_name FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new Exception("Please complete your company profile first.");
    }

    // Get all available skills
    $stmt = $conn->prepare("SELECT * FROM skills ORDER BY skill_name");
    $stmt->execute();
    $available_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all job categories
    $stmt = $conn->prepare("SELECT * FROM job_categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate input
        $job_title = trim($_POST['job_title']);
        $job_description = trim($_POST['job_description']);
        $job_type = trim($_POST['job_type']);
        $industry = trim($_POST['industry']);
        $location = trim($_POST['location']);
        $salary = trim($_POST['salary']);
        $requirements = trim($_POST['requirements']);
        $deadline = trim($_POST['deadline']);
        $selected_skills = isset($_POST['skills']) ? $_POST['skills'] : [];
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $vacancies = isset($_POST['vacancies']) ? intval($_POST['vacancies']) : 1;

        // Filter out temporary skill IDs (from JavaScript) and only keep valid numeric IDs
        $valid_skills = [];
        if (!empty($selected_skills)) {
            foreach ($selected_skills as $skill_id) {
                // Only keep numeric skill IDs, filter out temporary ones like 'new_1234567890'
                if (is_numeric($skill_id) && intval($skill_id) > 0) {
                    $valid_skills[] = intval($skill_id);
                }
            }
        }
        $selected_skills = $valid_skills;

        if (empty($job_title) || empty($job_description) || empty($job_type) || empty($location) || empty($industry)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Start transaction
        $conn->beginTransaction();

        try {
            // Insert the job
            $stmt = $conn->prepare("INSERT INTO jobs (company_id, title, description, requirements, location, job_type, industry, salary_range, vacancies, status, approval_status, posted_date) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'inactive', 'pending', NOW())");
            $stmt->execute([
                $company['company_id'],
                $job_title,
                $job_description,
                $requirements,
                $location,
                $job_type,
                $industry,
                $salary,
                $vacancies
            ]);
            
            $job_id = $conn->lastInsertId();
            
            // Notify admins about the pending job approval
            notifyAdminPendingJobApproval($conn, $job_id, $job_title, $company['company_name']);
            
            // Handle new skill creation if provided (inside transaction)
            if (isset($_POST['new_skill_name']) && !empty(trim($_POST['new_skill_name']))) {
                $new_skill_name = trim($_POST['new_skill_name']);
                $new_skill_description = isset($_POST['new_skill_description']) ? trim($_POST['new_skill_description']) : '';
                $new_skill_category = isset($_POST['new_skill_category']) ? trim($_POST['new_skill_category']) : null;
                
                // Check if skill already exists (case-insensitive)
                $stmt = $conn->prepare("SELECT skill_id FROM skills WHERE LOWER(skill_name) = LOWER(?)");
                $stmt->execute([$new_skill_name]);
                $existing_skill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_skill) {
                    // Skill exists, add it to selected skills
                    $selected_skills[] = $existing_skill['skill_id'];
                    $success_message_addon = " (Existing skill '$new_skill_name' was added to requirements)";
                } else {
                    // Create new skill (check which columns exist)
                    $stmt = $conn->query("SHOW COLUMNS FROM skills");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $priority = 3; // Default priority for user-created skills
                    
                    if (in_array('category', $columns) && in_array('created_by_user', $columns)) {
                        // Full new schema
                        $stmt = $conn->prepare("INSERT INTO skills (skill_name, description, category, priority, created_by_user) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$new_skill_name, $new_skill_description, $new_skill_category, $priority, true]);
                    } elseif (in_array('category', $columns)) {
                        // Has category but not created_by_user
                        $stmt = $conn->prepare("INSERT INTO skills (skill_name, description, category, priority) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$new_skill_name, $new_skill_description, $new_skill_category, $priority]);
                    } else {
                        // Basic schema - only skill_name, description, priority
                        $stmt = $conn->prepare("INSERT INTO skills (skill_name, description, priority) VALUES (?, ?, ?)");
                        $stmt->execute([$new_skill_name, $new_skill_description, $priority]);
                    }
                    
                    $new_skill_id = $conn->lastInsertId();
                    $selected_skills[] = $new_skill_id;
                    $success_message_addon = " (New skill '$new_skill_name' was created and added to requirements)";
                }
            }
            
            // Insert required skills with validation
            if (!empty($selected_skills)) {
                $stmt = $conn->prepare("INSERT INTO job_skills (job_id, skill_id, required_level) VALUES (?, ?, 'intermediate')");
                foreach ($selected_skills as $skill_id) {
                    // Ensure skill_id is an integer
                    $skill_id = intval($skill_id);
                    if ($skill_id <= 0) {
                        continue; // Skip invalid IDs
                    }
                    
                    // Validate that skill exists before inserting
                    $check_stmt = $conn->prepare("SELECT skill_id FROM skills WHERE skill_id = ?");
                    $check_stmt->execute([$skill_id]);
                    if ($check_stmt->fetch()) {
                        $stmt->execute([$job_id, $skill_id]);
                    } else {
                        throw new Exception("Skill ID $skill_id does not exist in the database. Available skills: " . implode(', ', array_column($conn->query("SELECT skill_id FROM skills")->fetchAll(), 'skill_id')));
                    }
                }
            }

            $conn->commit();
            $success = "Job posted successfully and is pending admin approval." . (isset($success_message_addon) ? $success_message_addon : "");
            
            // Redirect to jobs page after successful posting
            header("Location: jobs.php?success=" . urlencode($success));
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job - Employer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .sidebar, .sidebar-menu a {
            background: #1a252f !important;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #2c3e50 !important;
            color: #3498db !important;
        }
        .post-job-card {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.12), 0 1.5px 6px rgba(52, 152, 219, 0.10);
            border: 2px solid #e0e0e0;
            padding: 2.5rem 2rem 2rem 2rem;
            margin-bottom: 2.5rem;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        .post-job-card-header {
            background: linear-gradient(90deg, #3498db 0%, #6dd5fa 100%);
            color: #fff;
            border-radius: 1.25rem 1.25rem 0 0;
            padding: 1.5rem 2rem 1.2rem 2rem;
            margin: -2.5rem -2rem 2rem -2rem;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.10);
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }
        .post-job-card-header h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #fff;
            letter-spacing: -1px;
        }
        .post-job-form label {
            font-weight: 600;
            color: #2c3e50;
        }
        .post-job-form .form-control, .post-job-form .form-select {
            border-radius: 0.75rem;
            font-size: 1.08rem;
            background: #f8fafc;
            color: #222;
        }
        .post-job-form .input-group-text {
            background: #f4f8fb;
            border-radius: 0.75rem 0 0 0.75rem;
            color: #3498db;
        }
        .post-job-form .form-text {
            font-size: 0.97rem;
        }
        .post-job-form .btn-primary, .post-job-form .btn-secondary {
            border-radius: 0.75rem;
            font-weight: 700;
            padding: 0.85rem 2.7rem;
            font-size: 1.15rem;
            letter-spacing: 0.5px;
        }
        .post-job-form .row > [class^='col-'] {
            margin-bottom: 1.2rem;
        }
        .post-job-form .form-section {
            padding: 1.2rem 1rem;
            background: #f8fafd;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
        }
        .post-job-form .form-section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .post-job-card {
                padding: 1.2rem 0.5rem;
            }
            .post-job-card-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 1.2rem 1rem 1rem 1rem;
                margin: -1.2rem -0.5rem 2rem -0.5rem;
            }
        }
        .salary-input {
            position: relative;
        }
        .salary-input::before {
            content: '₱';
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .salary-input input {
            padding-left: 25px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Employer Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <!-- Logo centered below employer panel heading -->
        <div class="text-center mb-2 mt-1">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <i class="fas fa-building"></i>
                    <span>Company Profile</span>
                </a>
            </li>
            <li>
                <a href="jobs.php" class="active">
                    <i class="fas fa-briefcase"></i>
                    <span>Manage Jobs</span>
                </a>
            </li>
            <li>
                <a href="applications.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li>
                <a href="job-fair-events.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Job Fair Events</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Employer Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Post New Job</h2>
                <a href="jobs.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Manage Jobs
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="post-job-card mt-4">
                <div class="post-job-card-header">
                    <i class="fas fa-briefcase fa-2x"></i>
                    <h3 class="mb-0">Post a New Job</h3>
                </div>
                <form method="POST" class="needs-validation post-job-form" novalidate>
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-info-circle me-2"></i>Job Details</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="job_title" class="form-label">Job Title *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                    <input type="text" class="form-control" id="job_title" name="job_title" required>
                                </div>
                                <div class="invalid-feedback">Please enter a job title.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="job_type" class="form-label">Job Type *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                    <select class="form-select" id="job_type" name="job_type" required>
                                        <option value="">Select job type</option>
                                        <option value="Full-time">Full-time</option>
                                        <option value="Part-time">Part-time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Internship">Internship</option>
                                        <option value="Temporary">Temporary</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback">Please select a job type.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="industry" class="form-label">Industry *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-industry"></i></span>
                                    <?php echo renderIndustryDropdown('industry', 'industry', '', null, 'form-select', true); ?>
                                </div>
                                <div class="invalid-feedback">Please select an industry.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Job Category *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select job category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback">Please select a job category.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                    <input type="text" class="form-control" id="location" name="location" required>
                                </div>
                                <div class="invalid-feedback">Please enter a location.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="salary" class="form-label">Salary (Per Month)</label>
                                <div class="input-group salary-input">
                                    <input type="text" class="form-control" id="salary" name="salary" placeholder="e.g., 20000" required>
                                </div>
                                <small class="text-muted">Enter the salary in Philippine Peso (₱) per month.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vacancies" class="form-label">Number of Vacancies *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-users"></i></span>
                                    <input type="number" class="form-control" id="vacancies" name="vacancies" min="1" required>
                                </div>
                                <div class="invalid-feedback">Please enter the number of vacancies.</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-tasks me-2"></i>Requirements & Skills</div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="job_description" class="form-label">Job Description *</label>
                                <textarea class="form-control" id="job_description" name="job_description" rows="5" required></textarea>
                                <div class="invalid-feedback">Please enter a job description.</div>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="requirements" class="form-label">Requirements</label>
                                <textarea class="form-control" id="requirements" name="requirements" rows="3" placeholder="List the key requirements for this position"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Required Skills</label>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newSkillModal">
                                        <i class="fas fa-plus"></i> Create New Skill
                                    </button>
                                </div>
                                <select id="skills-select" class="form-select" name="skills[]" multiple>
                                    <?php foreach ($available_skills as $skill): ?>
                                    <option value="<?php echo $skill['skill_id']; ?>">
                                        <?php echo htmlspecialchars($skill['skill_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select required skills for this position. Can't find a skill? Create a new one!</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="deadline" class="form-label">Application Deadline</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" id="deadline" name="deadline" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Post Job
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <!-- New Skill Creation Modal -->
    <div class="modal fade" id="newSkillModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle"></i> Create New Skill
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Create a new skill!</strong> If you can't find a skill in the existing list, create a new one here. 
                        It will be added to the system and automatically included in your job requirements.
                    </div>
                    
                    <form id="newSkillForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_skill_category" class="form-label">Skill Category (Optional)</label>
                                <select class="form-select" id="new_skill_category" name="new_skill_category">
                                    <option value="">Select a category (optional)</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Human Resources">Human Resources</option>
                                    <option value="Information Technology">Information Technology</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Sales">Sales</option>
                                    <option value="Education">Education</option>
                                    <option value="Creative">Creative</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="form-text">Helps organize the skill for easier discovery</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_skill_name" class="form-label">Skill Name *</label>
                                <input type="text" class="form-control" id="new_skill_name" name="new_skill_name" required 
                                       placeholder="e.g., Machine Learning, Customer Service" 
                                       autocomplete="off">
                                <div class="form-text">Enter a clear, specific skill name</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_skill_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="new_skill_description" name="new_skill_description" rows="3" 
                                      placeholder="Brief description of this skill, its applications, or what it involves"></textarea>
                            <div class="form-text">Help others understand what this skill involves</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="createSkillBtn">
                        <i class="fas fa-plus-circle"></i> Create and Add Skill
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Initialize Tom Select for skills
        let skillSelect;
        
        document.addEventListener('DOMContentLoaded', function() {
            skillSelect = new TomSelect('#skills-select', {
                plugins: ['remove_button'],
                placeholder: 'Select required skills...',
                allowEmptyOption: true,
                closeAfterSelect: false
            });
            
            // New skill creation functionality
            const createSkillBtn = document.getElementById('createSkillBtn');
            const newSkillModal = document.getElementById('newSkillModal');
            const newSkillForm = document.getElementById('newSkillForm');
            
            if (createSkillBtn) {
                createSkillBtn.addEventListener('click', function() {
                    const skillName = document.getElementById('new_skill_name').value.trim();
                    const skillCategory = document.getElementById('new_skill_category').value;
                    const skillDescription = document.getElementById('new_skill_description').value.trim();
                    
                    if (!skillName) {
                        alert('Please enter a skill name.');
                        document.getElementById('new_skill_name').focus();
                        return;
                    }
                    
                    // Check if skill already exists in the current list
                    const existingOptions = Object.values(skillSelect.options);
                    const exists = existingOptions.some(option => 
                        option.text.toLowerCase() === skillName.toLowerCase()
                    );
                    
                    if (exists) {
                        alert('This skill already exists in the system. It will be automatically selected.');
                        // Find and select the existing skill
                        const existingOption = existingOptions.find(option => 
                            option.text.toLowerCase() === skillName.toLowerCase()
                        );
                        if (existingOption) {
                            skillSelect.addItem(existingOption.value);
                        }
                    } else {
                        // Add new skill to form as hidden fields
                        addHiddenSkillFields(skillName, skillCategory, skillDescription);
                        
                        // Add to Tom Select as a temporary option
                        const tempId = 'new_' + Date.now();
                        skillSelect.addOption({value: tempId, text: skillName + ' (New)'});
                        skillSelect.addItem(tempId);
                        
                        // Show success message
                        showSkillCreationSuccess(skillName);
                    }
                    
                    // Close modal and reset form
                    bootstrap.Modal.getInstance(newSkillModal).hide();
                    newSkillForm.reset();
                });
            }
            
            // Add visual feedback for skill name input
            const newSkillInput = document.getElementById('new_skill_name');
            if (newSkillInput) {
                newSkillInput.addEventListener('input', function() {
                    const skillName = this.value.trim();
                    const existingOptions = Object.values(skillSelect.options);
                    const exists = existingOptions.some(option => 
                        option.text.toLowerCase() === skillName.toLowerCase()
                    );
                    
                    if (skillName && !exists) {
                        this.style.borderColor = '#28a745';
                        this.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
                        
                        // Add or update helper text
                        let helpText = this.parentNode.querySelector('.new-skill-indicator');
                        if (!helpText) {
                            helpText = document.createElement('div');
                            helpText.className = 'form-text new-skill-indicator text-success';
                            this.parentNode.appendChild(helpText);
                        }
                        helpText.innerHTML = '<i class="fas fa-plus-circle"></i> This will create a new skill!';
                    } else {
                        this.style.borderColor = '';
                        this.style.boxShadow = '';
                        
                        // Remove helper text
                        const helpText = this.parentNode.querySelector('.new-skill-indicator');
                        if (helpText) {
                            helpText.remove();
                        }
                        
                        if (exists) {
                            this.style.borderColor = '#ffc107';
                            this.style.boxShadow = '0 0 0 0.2rem rgba(255, 193, 7, 0.25)';
                            
                            let helpText = this.parentNode.querySelector('.existing-skill-indicator');
                            if (!helpText) {
                                helpText = document.createElement('div');
                                helpText.className = 'form-text existing-skill-indicator text-warning';
                                this.parentNode.appendChild(helpText);
                            }
                            helpText.innerHTML = '<i class="fas fa-info-circle"></i> This skill already exists and will be selected.';
                        } else {
                            const helpText = this.parentNode.querySelector('.existing-skill-indicator');
                            if (helpText) {
                                helpText.remove();
                            }
                        }
                    }
                });
            }
            
            // Helper functions
            function addHiddenSkillFields(name, category, description) {
                // Remove any existing hidden fields for new skills
                const existingFields = document.querySelectorAll('input[name^="new_skill_"]');
                existingFields.forEach(field => field.remove());
                
                // Add hidden fields to main form
                const mainForm = document.querySelector('.post-job-form');
                
                const nameField = document.createElement('input');
                nameField.type = 'hidden';
                nameField.name = 'new_skill_name';
                nameField.value = name;
                mainForm.appendChild(nameField);
                
                const categoryField = document.createElement('input');
                categoryField.type = 'hidden';
                categoryField.name = 'new_skill_category';
                categoryField.value = category;
                mainForm.appendChild(categoryField);
                
                const descriptionField = document.createElement('input');
                descriptionField.type = 'hidden';
                descriptionField.name = 'new_skill_description';
                descriptionField.value = description;
                mainForm.appendChild(descriptionField);
            }
            
            function showSkillCreationSuccess(skillName) {
                // Create and show a temporary success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle"></i> 
                    <strong>Skill "${skillName}" will be created</strong> and added to job requirements when you submit the form.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                // Insert after the form header
                const formHeader = document.querySelector('.post-job-card-header');
                formHeader.parentNode.insertBefore(alertDiv, formHeader.nextSibling);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
            

        });
        
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html> 
