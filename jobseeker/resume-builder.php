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
$resume = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle photo upload
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['photo']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
            }
            
            $file_size = $_FILES['photo']['size'];
            if ($file_size > 5242880) { // 5MB limit
                throw new Exception('File is too large. Maximum size is 5MB.');
            }
            
            $file_name = uniqid('resume_') . '_' . basename($_FILES['photo']['name']);
            $upload_path = '../uploads/resume_photos/' . $file_name;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo_path = 'uploads/resume_photos/' . $file_name;
                
                // Delete old photo if exists
                if (is_array($resume) && !empty($resume['photo_path']) && file_exists('../' . $resume['photo_path'])) {
                    @unlink('../' . $resume['photo_path']);
                }
            } else {
                throw new Exception('Failed to upload file.');
            }
        }

        // Get form data
        $personal = [
            'full_name' => $_POST['full_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'bio' => $_POST['bio'] ?? '',
            'experience_level' => $_POST['experience_level'] ?? '',
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'objective' => $_POST['objective'] ?? '',
        ];

        $education = [];
        if (isset($_POST['school']) && is_array($_POST['school'])) {
            foreach ($_POST['school'] as $key => $school) {
                if (!empty($school)) {
                    $education[] = [
                        'school' => $school,
                        'field_of_study' => $_POST['field_of_study'][$key] ?? '',
                        'degree' => $_POST['degree'][$key] ?? '',
                        'year' => $_POST['edu_year'][$key] ?? '',
                    ];
                }
            }
        }

        $experience = [];
        if (isset($_POST['company']) && is_array($_POST['company'])) {
            foreach ($_POST['company'] as $key => $company) {
                if (!empty($company)) {
                    $experience[] = [
                        'company' => $company,
                        'position' => $_POST['position'][$key] ?? '',
                        'year' => $_POST['exp_year'][$key] ?? '',
                        'description' => $_POST['description'][$key] ?? '',
                    ];
                }
            }
        }

        $skills = [];
        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
            foreach ($_POST['skills'] as $key => $skill) {
                if (!empty($skill)) {
                    $skills[] = [
                        'skill' => $skill,
                        'proficiency' => $_POST['skill_proficiency'][$key] ?? 'intermediate',
                    ];
                }
            }
        }

        // Save to database
        $conn->beginTransaction();

        // Check if resume exists
        $stmt = $conn->prepare("SELECT resume_id FROM basic_resumes WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $existing_resume = $stmt->fetch();

        if ($existing_resume) {
            // Update existing resume
            $photo_sql = $photo_path ? ", photo_path = ?" : "";
            $stmt = $conn->prepare("UPDATE basic_resumes SET 
                personal_info = ?, 
                education = ?, 
                experience = ?, 
                skills = ?,
                updated_at = CURRENT_TIMESTAMP" . 
                $photo_sql . " 
                WHERE user_id = ?");
            
            $params = [
                json_encode($personal),
                json_encode($education),
                json_encode($experience),
                json_encode($skills),
                $_SESSION['user_id']
            ];
            
            if ($photo_path) {
                array_splice($params, -1, 0, [$photo_path]);
            }
            
            $stmt->execute($params);
        } else {
            // Create new resume
            $stmt = $conn->prepare("INSERT INTO basic_resumes (user_id, personal_info, education, experience, skills, photo_path) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                json_encode($personal),
                json_encode($education),
                json_encode($experience),
                json_encode($skills),
                $photo_path
            ]);
        }

        $conn->commit();
        $success = "Resume saved successfully!";

    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Get existing resume data
try {
    $stmt = $conn->prepare("SELECT * FROM basic_resumes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resume) {
        $resume['personal_info'] = json_decode($resume['personal_info'], true);
        $resume['education'] = json_decode($resume['education'], true);
        $resume['experience'] = json_decode($resume['experience'], true);
        $resume['skills'] = json_decode($resume['skills'], true);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$page_title = "Resume Builder";
include 'includes/header.php';
?>

<style>
    /* Edge design card styles */
    .card {
        --background: linear-gradient(to left, #4b6cb7 0%, #182848 100%);
        border: none;
        border-radius: 1rem;
        margin-bottom: 20px;
        padding: 5px;
        overflow: visible;
        background: #4b6cb7;
        background: var(--background);
        position: relative;
        z-index: 1;
    }

    .card::after {
        position: absolute;
        content: "";
        top: 30px;
        left: 0;
        right: 0;
        z-index: -1;
        height: 100%;
        width: 100%;
        transform: scale(0.8);
        filter: blur(25px);
        background: #4b6cb7;
        background: var(--background);
        transition: opacity .5s;
    }

    .card:hover::after {
        opacity: 0;
    }

    .card-header {
        background: #ffffff;
        border-radius: .7rem .7rem 0 0 !important;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        padding: 1rem;
    }

    .card-body {
        background: #ffffff;
        border-radius: 0 0 .7rem .7rem;
        padding: 1.5rem;
    }

    .card-header h5 {
        color: #2c3e50;
        margin: 0;
        font-weight: 600;
    }

    /* Form controls */
    .form-control {
        border: 1px solid #e0e0e0;
        border-radius: 0.5rem;
        padding: 0.6rem 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: #4b6cb7;
        box-shadow: 0 0 0 0.2rem rgba(75, 108, 183, 0.25);
    }

    /* Button styles */
    .btn-primary, .btn-success {
        --background: linear-gradient(to left, #4b6cb7 0%, #182848 100%);
        background: var(--background);
        border: none;
        padding: 0.8rem 2rem;
        border-radius: 0.5rem;
        font-size: 1rem;
        font-weight: 500;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        margin: 0 0.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-primary:hover, .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-primary i, .btn-success i {
        margin-right: 0.5rem;
        font-size: 1.1rem;
    }

    .btn-success {
        --background: linear-gradient(to left, #28a745 0%, #1e7e34 100%);
    }

    /* Image thumbnail */
    .img-thumbnail {
        border-radius: 1rem;
        border: 2px solid #e0e0e0;
        padding: 0.5rem;
        transition: all 0.3s ease;
    }

    /* Entry sections */
    .education-entry, .experience-entry {
        border-bottom: 1px solid rgba(0,0,0,0.1) !important;
        padding-bottom: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .education-entry:last-child, .experience-entry:last-child {
        border-bottom: none !important;
        padding-bottom: 0;
        margin-bottom: 0;
    }

    .skill-entry {
        margin-bottom: 1rem;
    }

    /* Back button */
    .btn-outline-primary.back-btn {
        border-width: 2px;
        font-weight: 500;
    }

    .back-btn i {
        margin-right: 0.5rem;
    }

    /* Form labels */
    .form-label {
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    /* Small text */
    .form-text {
        color: #6c757d;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .card {
            padding: 3px;
        }

        .card-body {
            padding: 1rem;
        }

        .btn {
            padding: 0.4rem 1rem;
        }

        .d-grid.gap-2 {
            display: flex !important;
            flex-direction: column;
            align-items: stretch;
            margin: 2rem 1rem;
        }

        .btn-primary, .btn-success {
            margin: 0.5rem 0;
            padding: 0.8rem 1rem;
            width: 100%;
        }
    }
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Resume Builder</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
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

            <form method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
                <!-- Personal Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Profile Photo</label>
                                <div class="d-flex flex-column align-items-center">
                                    <?php if (!empty($resume['photo_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($resume['photo_path']); ?>" 
                                             alt="Profile Photo" 
                                             class="img-thumbnail mb-2" 
                                             style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="img-thumbnail mb-2 d-flex align-items-center justify-content-center" 
                                             style="width: 150px; height: 150px; background-color: #f8f9fa;">
                                            <i class="fas fa-user fa-4x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="photo" accept="image/*">
                                    <small class="form-text text-muted">Max size: 5MB. JPG, PNG or GIF.</small>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name*</label>
                                        <input type="text" class="form-control" name="full_name" 
                                            value="<?php echo htmlspecialchars($resume['personal_info']['full_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email*</label>
                                        <input type="email" class="form-control" name="email" 
                                            value="<?php echo htmlspecialchars($resume['personal_info']['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" 
                                            value="<?php echo htmlspecialchars($resume['personal_info']['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" 
                                            value="<?php echo htmlspecialchars($resume['personal_info']['address'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-control" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($resume['personal_info']['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($resume['personal_info']['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($resume['personal_info']['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" 
                                            value="<?php echo htmlspecialchars($resume['personal_info']['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Experience Level</label>
                                        <select class="form-control" name="experience_level">
                                            <option value="">Select Experience Level</option>
                                            <option value="entry-level" <?php echo ($resume['personal_info']['experience_level'] ?? '') === 'entry-level' ? 'selected' : ''; ?>>Entry Level (0-1 years)</option>
                                            <option value="junior" <?php echo ($resume['personal_info']['experience_level'] ?? '') === 'junior' ? 'selected' : ''; ?>>Junior (1-3 years)</option>
                                            <option value="mid-level" <?php echo ($resume['personal_info']['experience_level'] ?? '') === 'mid-level' ? 'selected' : ''; ?>>Mid Level (3-5 years)</option>
                                            <option value="senior" <?php echo ($resume['personal_info']['experience_level'] ?? '') === 'senior' ? 'selected' : ''; ?>>Senior (5-8 years)</option>
                                            <option value="lead" <?php echo ($resume['personal_info']['experience_level'] ?? '') === 'lead' ? 'selected' : ''; ?>>Lead (8+ years)</option>
                                            <option value="executive" <?php echo ($resume['personal_info']['experience_level'] ?? '') === 'executive' ? 'selected' : ''; ?>>Executive (10+ years)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Professional Summary/Bio</label>
                                        <textarea class="form-control" name="bio" rows="3" 
                                            placeholder="Write a brief professional summary about yourself..."><?php echo htmlspecialchars($resume['personal_info']['bio'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Career Objective</label>
                                        <textarea class="form-control" name="objective" rows="2" 
                                            placeholder="What are your career goals and aspirations?"><?php echo htmlspecialchars($resume['personal_info']['objective'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Education -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Education</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEducation()">
                            <i class="fas fa-plus"></i> Add Education
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="educationContainer">
                            <?php 
                            $education = $resume['education'] ?? [[]];
                            foreach ($education as $index => $edu): 
                            ?>
                            <div class="education-entry border-bottom pb-3 mb-3">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">School/University</label>
                                        <input type="text" class="form-control" name="school[]" 
                                            value="<?php echo htmlspecialchars($edu['school'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Major/Course</label>
                                        <input type="text" class="form-control" name="field_of_study[]" 
                                            value="<?php echo htmlspecialchars($edu['field_of_study'] ?? ($edu['course'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Degree/Certificate</label>
                                        <input type="text" class="form-control" name="degree[]" 
                                            value="<?php echo htmlspecialchars($edu['degree'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-1 mb-3">
                                        <label class="form-label">Year</label>
                                        <input type="text" class="form-control" name="edu_year[]" 
                                            value="<?php echo htmlspecialchars($edu['year'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-1 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEntry(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Experience -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Work Experience</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addExperience()">
                            <i class="fas fa-plus"></i> Add Experience
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="experienceContainer">
                            <?php 
                            $experience = $resume['experience'] ?? [[]];
                            foreach ($experience as $index => $exp): 
                            ?>
                            <div class="experience-entry border-bottom pb-3 mb-3">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Company</label>
                                        <input type="text" class="form-control" name="company[]" 
                                            value="<?php echo htmlspecialchars($exp['company'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Position</label>
                                        <input type="text" class="form-control" name="position[]" 
                                            value="<?php echo htmlspecialchars($exp['position'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Year</label>
                                        <input type="text" class="form-control" name="exp_year[]" 
                                            value="<?php echo htmlspecialchars($exp['year'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-1 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEntry(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <div class="col-md-11 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description[]" rows="2"><?php echo htmlspecialchars($exp['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Skills -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Skills</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSkill()">
                            <i class="fas fa-plus"></i> Add Skill
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="skillsContainer">
                            <?php 
                            $skills = $resume['skills'] ?? [['skill' => '', 'proficiency' => 'intermediate']];
                            if (empty($skills)) {
                                $skills = [['skill' => '', 'proficiency' => 'intermediate']];
                            }
                            foreach ($skills as $skill_data): 
                                $skill_name = is_array($skill_data) ? ($skill_data['skill'] ?? '') : $skill_data;
                                $skill_proficiency = is_array($skill_data) ? ($skill_data['proficiency'] ?? 'intermediate') : 'intermediate';
                            ?>
                            <div class="skill-entry row mb-2">
                                <div class="col-md-7">
                                    <input type="text" class="form-control" name="skills[]" 
                                        value="<?php echo htmlspecialchars($skill_name); ?>" 
                                        placeholder="Enter skill name">
                                </div>
                                <div class="col-md-4">
                                    <select class="form-control" name="skill_proficiency[]">
                                        <option value="beginner" <?php echo $skill_proficiency === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                        <option value="intermediate" <?php echo $skill_proficiency === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                        <option value="expert" <?php echo $skill_proficiency === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEntry(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <button type="submit" class="btn btn-primary" style="min-width: 150px;">
                        <i class="fas fa-save"></i> Save Resume
                    </button>
                    <a href="resume-preview.php" target="_blank" class="btn btn-success" style="min-width: 150px;">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                    <button type="button" class="btn btn-info" onclick="printResume()" style="min-width: 150px;">
                        <i class="fas fa-print"></i> Print Resume
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addEducation() {
    const container = document.getElementById('educationContainer');
    const entry = document.createElement('div');
    entry.className = 'education-entry border-bottom pb-3 mb-3';
    entry.innerHTML = `
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">School/University</label>
                <input type="text" class="form-control" name="school[]">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Major/Course</label>
                <input type="text" class="form-control" name="field_of_study[]">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Degree/Certificate</label>
                <input type="text" class="form-control" name="degree[]">
            </div>
            <div class="col-md-1 mb-3">
                <label class="form-label">Year</label>
                <input type="text" class="form-control" name="edu_year[]">
            </div>
            <div class="col-md-1 mb-3 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEntry(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(entry);
}

function addExperience() {
    const container = document.getElementById('experienceContainer');
    const entry = document.createElement('div');
    entry.className = 'experience-entry border-bottom pb-3 mb-3';
    entry.innerHTML = `
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Company</label>
                <input type="text" class="form-control" name="company[]">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Position</label>
                <input type="text" class="form-control" name="position[]">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Year</label>
                <input type="text" class="form-control" name="exp_year[]">
            </div>
            <div class="col-md-1 mb-3 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEntry(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="col-md-11 mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description[]" rows="2"></textarea>
            </div>
        </div>
    `;
    container.appendChild(entry);
}

function addSkill() {
    const container = document.getElementById('skillsContainer');
    const entry = document.createElement('div');
    entry.className = 'skill-entry row mb-2';
    entry.innerHTML = `
        <div class="col-md-7">
            <input type="text" class="form-control" name="skills[]" placeholder="Enter skill name">
        </div>
        <div class="col-md-4">
            <select class="form-control" name="skill_proficiency[]">
                <option value="beginner">Beginner</option>
                <option value="intermediate" selected>Intermediate</option>
                <option value="expert">Expert</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEntry(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(entry);
}

function removeEntry(button) {
    const entry = button.closest('.education-entry, .experience-entry, .skill-entry');
    entry.remove();
}

// Print Resume Function
function printResume() {
    window.open('resume-preview.php', '_blank');
}

// Form validation
(function () {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
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