<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
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
$companies = [];
$categories = [];
$skills = [];
$selected_skills = [];

try {
    // Get job details
    $stmt = $conn->prepare("
        SELECT j.*, c.company_name 
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.job_id = ?
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        header("Location: jobs.php");
        exit();
    }

    // Get all companies
    $stmt = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all categories
    $stmt = $conn->query("SELECT category_id, category_name FROM job_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all skills
    $stmt = $conn->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get selected skills for this job
    $stmt = $conn->prepare("
        SELECT skill_id, required_level 
        FROM job_skills 
        WHERE job_id = ?
    ");
    $stmt->execute([$job_id]);
    $selected_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate required fields
        $required_fields = ['title', 'company_id', 'description', 'requirements', 'job_type', 'location', 'salary_range'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            $error = "Please fill in all required fields: " . implode(", ", $missing_fields);
        } else {
            try {
                $conn->beginTransaction();

                // Update job details
                $stmt = $conn->prepare("
                    UPDATE jobs SET 
                        title = ?,
                        company_id = ?,
                        category_id = ?,
                        description = ?,
                        requirements = ?,
                        job_type = ?,
                        location = ?,
                        salary_range = ?,
                        industry = ?,
                        deadline_date = ?,
                        status = ?
                    WHERE job_id = ?
                ");

                $stmt->execute([
                    $_POST['title'],
                    $_POST['company_id'],
                    $_POST['category_id'] ?: null,
                    $_POST['description'],
                    $_POST['requirements'],
                    $_POST['job_type'],
                    $_POST['location'],
                    $_POST['salary_range'],
                    $_POST['industry'],
                    $_POST['deadline_date'] ?: null,
                    $_POST['status'],
                    $job_id
                ]);

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
                        $_POST['skills'][$existing_skill['skill_id']] = 'intermediate';
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
                        $_POST['skills'][$new_skill_id] = 'intermediate';
                        $success_message_addon = " (New skill '$new_skill_name' was created and added to requirements)";
                    }
                }

                // Update skills
                // First, remove all existing skills
                $stmt = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
                $stmt->execute([$job_id]);

                // Then, add new skills (filter out temporary JavaScript IDs)
                if (!empty($_POST['skills'])) {
                    $stmt = $conn->prepare("
                        INSERT INTO job_skills (job_id, skill_id, required_level) 
                        VALUES (?, ?, ?)
                    ");

                    foreach ($_POST['skills'] as $skill_id => $level) {
                        // Only process numeric skill IDs, skip temporary ones like 'new_1234567890'
                        if (is_numeric($skill_id) && intval($skill_id) > 0 && !empty($level)) {
                            // Validate that skill exists before inserting
                            $check_stmt = $conn->prepare("SELECT skill_id FROM skills WHERE skill_id = ?");
                            $check_stmt->execute([intval($skill_id)]);
                            if ($check_stmt->fetch()) {
                                $stmt->execute([$job_id, intval($skill_id), $level]);
                            } else {
                                throw new Exception("Skill ID $skill_id does not exist in the database.");
                            }
                        }
                    }
                }

                $conn->commit();
                $success = "Job updated successfully!" . (isset($success_message_addon) ? $success_message_addon : "");
                
                // Refresh job data
                $stmt = $conn->prepare("
                    SELECT j.*, c.company_name 
                    FROM jobs j
                    JOIN companies c ON j.company_id = c.company_id
                    WHERE j.job_id = ?
                ");
                $stmt->execute([$job_id]);
                $job = $stmt->fetch(PDO::FETCH_ASSOC);

                // Refresh selected skills
                $stmt = $conn->prepare("
                    SELECT skill_id, required_level 
                    FROM job_skills 
                    WHERE job_id = ?
                ");
                $stmt->execute([$job_id]);
                $selected_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Error updating job: " . $e->getMessage();
            }
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
    <title>Edit Job - <?php echo htmlspecialchars($job['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <style>
        .required-field::after {
            content: " *";
            color: red;
        }
        .select2-container {
            width: 100% !important;
        }
        .skill-card {
            background: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease-in-out;
        }
        .skill-card:hover { box-shadow: 0 14px 40px rgba(0, 0, 0, 0.08); }
        .skill-card.selected {
            outline: 3px solid rgba(102, 126, 234, 0.25);
            background-color: #f7f9ff;
        }
        .skill-level {
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="main-content page-transition">
        <div class="content-wrapper container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card modern-card">
                        <div class="card-header-modern">
                            <h4 class="mb-0 card-title-modern">Edit Job</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-modern"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-modern"><?php echo $success; ?></div>
                            <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Basic Information</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required-field">Job Title</label>
                                        <input type="text" class="form-control" name="title" 
                                               value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label required-field">Company</label>
                                        <select class="form-select" name="company_id" required>
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $company): ?>
                                                <option value="<?php echo $company['company_id']; ?>" 
                                                        <?php echo $company['company_id'] == $job['company_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category_id">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['category_id']; ?>"
                                                        <?php echo $category['category_id'] == $job['category_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Industry</label>
                                        <input type="text" class="form-control" name="industry" 
                                               value="<?php echo htmlspecialchars($job['industry'] ?? ''); ?>">
                                    </div>
                                </div>

                                <!-- Job Details -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Job Details</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required-field">Job Type</label>
                                        <select class="form-select" name="job_type" required>
                                            <option value="full-time" <?php echo $job['job_type'] == 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                            <option value="part-time" <?php echo $job['job_type'] == 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                                            <option value="contract" <?php echo $job['job_type'] == 'contract' ? 'selected' : ''; ?>>Contract</option>
                                            <option value="internship" <?php echo $job['job_type'] == 'internship' ? 'selected' : ''; ?>>Internship</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label required-field">Location</label>
                                        <input type="text" class="form-control" name="location" 
                                               value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label required-field">Salary Range</label>
                                        <input type="text" class="form-control" name="salary_range" 
                                               value="<?php echo htmlspecialchars($job['salary_range']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Deadline Date</label>
                                        <input type="date" class="form-control" name="deadline_date" 
                                               value="<?php echo $job['deadline_date'] ? date('Y-m-d', strtotime($job['deadline_date'])) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label required-field">Status</label>
                                        <select class="form-select" name="status" required>
                                            <option value="active" <?php echo $job['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $job['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Description and Requirements -->
                                <div class="col-12 mt-4">
                                    <h5 class="mb-3">Description & Requirements</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required-field">Job Description</label>
                                        <textarea class="form-control" name="description" rows="5" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label required-field">Requirements</label>
                                        <textarea class="form-control" name="requirements" rows="5" required><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                                    </div>
                                </div>

                                <!-- Required Skills -->
                                <div class="col-12 mt-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Required Skills</h5>
                                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#newSkillModal">
                                            <i class="fas fa-plus"></i> Create New Skill
                                        </button>
                                    </div>
                                    <div class="row">
                                        <?php foreach ($skills as $skill): 
                                            $is_selected = false;
                                            $selected_level = '';
                                            foreach ($selected_skills as $selected) {
                                                if ($selected['skill_id'] == $skill['skill_id']) {
                                                    $is_selected = true;
                                                    $selected_level = $selected['required_level'];
                                                    break;
                                                }
                                            }
                                        ?>
                                            <div class="col-md-4">
                                                <div class="skill-card <?php echo $is_selected ? 'selected' : ''; ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input skill-checkbox" type="checkbox" 
                                                               name="skills[<?php echo $skill['skill_id']; ?>]" 
                                                               value="1" 
                                                               id="skill_<?php echo $skill['skill_id']; ?>"
                                                               <?php echo $is_selected ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="skill_<?php echo $skill['skill_id']; ?>">
                                                            <?php echo htmlspecialchars($skill['skill_name']); ?>
                                                        </label>
                                                    </div>
                                                    <div class="skill-level">
                                                        <select class="form-select form-select-sm form-control-modern" 
                                                                name="skill_levels[<?php echo $skill['skill_id']; ?>]"
                                                                <?php echo !$is_selected ? 'disabled' : ''; ?>>
                                                            <option value="">Select Level</option>
                                                            <option value="beginner" <?php echo $selected_level == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                            <option value="intermediate" <?php echo $selected_level == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                            <option value="expert" <?php echo $selected_level == 'expert' ? 'selected' : ''; ?>>Expert</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="jobs.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
                        It will be added to the system and automatically included in this job's requirements.
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for better dropdown experience
            $('.form-select').select2({
                theme: 'bootstrap-5'
            });

            // Handle skill checkbox changes
            $('.skill-checkbox').change(function() {
                var skillCard = $(this).closest('.skill-card');
                var levelSelect = skillCard.find('select');
                
                if (this.checked) {
                    skillCard.addClass('selected');
                    levelSelect.prop('disabled', false);
                    levelSelect.val('beginner'); // Default to beginner level
                } else {
                    skillCard.removeClass('selected');
                    levelSelect.prop('disabled', true);
                    levelSelect.val('');
                }
            });

            // Handle skill level changes
            $('.skill-level select').change(function() {
                var skillCard = $(this).closest('.skill-card');
                var checkbox = skillCard.find('.skill-checkbox');
                
                if ($(this).val()) {
                    checkbox.prop('checked', true);
                    skillCard.addClass('selected');
                } else {
                    checkbox.prop('checked', false);
                    skillCard.removeClass('selected');
                }
            });
            
            // New skill creation functionality
            $('#createSkillBtn').click(function() {
                var skillName = $('#new_skill_name').val().trim();
                var skillCategory = $('#new_skill_category').val();
                var skillDescription = $('#new_skill_description').val().trim();
                
                if (!skillName) {
                    alert('Please enter a skill name.');
                    $('#new_skill_name').focus();
                    return;
                }
                
                // Check if skill already exists in the current list
                var exists = false;
                $('.skill-card label').each(function() {
                    if ($(this).text().toLowerCase() === skillName.toLowerCase()) {
                        exists = true;
                        // Auto-select the existing skill
                        var checkbox = $(this).siblings('.form-check-input');
                        checkbox.prop('checked', true);
                        checkbox.trigger('change');
                        return false;
                    }
                });
                
                if (exists) {
                    alert('This skill already exists in the system. It has been automatically selected.');
                } else {
                    // Add new skill to form as hidden fields
                    addHiddenSkillFields(skillName, skillCategory, skillDescription);
                    
                    // Add new skill card to the UI
                    addNewSkillCard(skillName, skillCategory);
                    
                    // Show success message
                    showSkillCreationSuccess(skillName);
                }
                
                // Close modal and reset form
                $('#newSkillModal').modal('hide');
                $('#newSkillForm')[0].reset();
            });
            
            // Add visual feedback for skill name input
            $('#new_skill_name').on('input', function() {
                var skillName = $(this).val().trim();
                var exists = false;
                
                $('.skill-card label').each(function() {
                    if ($(this).text().toLowerCase() === skillName.toLowerCase()) {
                        exists = true;
                        return false;
                    }
                });
                
                if (skillName && !exists) {
                    $(this).css({
                        'border-color': '#28a745',
                        'box-shadow': '0 0 0 0.2rem rgba(40, 167, 69, 0.25)'
                    });
                    
                    var helpText = $(this).parent().find('.new-skill-indicator');
                    if (helpText.length === 0) {
                        $(this).parent().append('<div class="form-text new-skill-indicator text-success"><i class="fas fa-plus-circle"></i> This will create a new skill!</div>');
                    }
                } else {
                    $(this).css({
                        'border-color': '',
                        'box-shadow': ''
                    });
                    
                    $(this).parent().find('.new-skill-indicator').remove();
                    
                    if (exists) {
                        $(this).css({
                            'border-color': '#ffc107',
                            'box-shadow': '0 0 0 0.2rem rgba(255, 193, 7, 0.25)'
                        });
                        
                        var helpText = $(this).parent().find('.existing-skill-indicator');
                        if (helpText.length === 0) {
                            $(this).parent().append('<div class="form-text existing-skill-indicator text-warning"><i class="fas fa-info-circle"></i> This skill already exists and will be selected.</div>');
                        }
                    } else {
                        $(this).parent().find('.existing-skill-indicator').remove();
                    }
                }
            });
            
            // Helper functions
            function addHiddenSkillFields(name, category, description) {
                // Remove any existing hidden fields for new skills
                $('input[name^="new_skill_"]').remove();
                
                // Add hidden fields to main form
                var mainForm = $('form').first();
                
                mainForm.append('<input type="hidden" name="new_skill_name" value="' + name + '">');
                mainForm.append('<input type="hidden" name="new_skill_category" value="' + category + '">');
                mainForm.append('<input type="hidden" name="new_skill_description" value="' + description + '">');
            }
            
            function addNewSkillCard(skillName, category) {
                var newSkillId = 'new_skill_' + Date.now();
                var categoryBadge = category ? '<small class="text-muted">(' + category + ')</small>' : '';
                
                var skillCardHtml = `
                    <div class="col-md-4">
                        <div class="skill-card selected new-skill-card">
                            <div class="form-check">
                                <input class="form-check-input skill-checkbox" type="checkbox" 
                                       name="skills[${newSkillId}]" 
                                       value="1" 
                                       id="skill_${newSkillId}"
                                       checked disabled>
                                <label class="form-check-label" for="skill_${newSkillId}">
                                    ${skillName} <span class="badge bg-success">New</span> ${categoryBadge}
                                </label>
                            </div>
                            <div class="skill-level">
                                <select class="form-select form-select-sm" 
                                        name="skill_levels[${newSkillId}]">
                                    <option value="">Select Level</option>
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate" selected>Intermediate</option>
                                    <option value="expert">Expert</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;
                
                $('.col-12 .row').first().append(skillCardHtml);
            }
            
            function showSkillCreationSuccess(skillName) {
                // Create and show a temporary success message
                var alertHtml = `
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> 
                        <strong>Skill "${skillName}" will be created</strong> and added to job requirements when you save the form.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                // Insert after any existing alerts
                var existingAlert = $('.alert').first();
                if (existingAlert.length > 0) {
                    existingAlert.after(alertHtml);
                } else {
                    $('.card-body').prepend(alertHtml);
                }
                
                // Auto-remove after 5 seconds
                setTimeout(function() {
                    $('.alert-success').last().fadeOut();
                }, 5000);
            }
        });
    </script>
</body>
</html> 