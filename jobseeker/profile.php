<?php
session_start();
require_once '../config/database.php';
require_once '../includes/job_matching.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;
$profile = null;
$skills = [];
$all_skills = [];
$matching_jobs_count = 0;

try {
    // Get user profile
    $stmt = $conn->prepare("SELECT up.*, u.email FROM user_profiles up 
                           JOIN users u ON up.user_id = u.user_id 
                           WHERE up.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get user skills
    $stmt = $conn->prepare("SELECT s.* FROM skills s 
                           JOIN jobseeker_skills js ON s.skill_id = js.skill_id 
                           WHERE js.jobseeker_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all available skills for selection
    $stmt = $conn->prepare("SELECT * FROM skills ORDER BY skill_name");
    $stmt->execute();
    $all_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get count of matching jobs using the helper function
    if (!empty($skills)) {
        $matching_jobs_count = getMatchingJobsCount($conn, $_SESSION['user_id']);
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'phone', 'location', 'experience'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Handle profile picture upload and removal
        $profile_picture = $profile['profile_picture'] ?? null;
        
        // Check if profile picture should be removed
        if (isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] == '1') {
            // Delete old profile picture file if exists
            if ($profile_picture && file_exists('../' . $profile_picture)) {
                unlink('../' . $profile_picture);
            }
            $profile_picture = null;
        } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
                throw new Exception("Invalid file type. Please upload JPG, PNG, or GIF.");
            }

            if ($_FILES['profile_picture']['size'] > $max_size) {
                throw new Exception("File size too large. Maximum size is 5MB.");
            }

            $upload_dir = '../uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if ($profile_picture && file_exists('../' . $profile_picture)) {
                    unlink('../' . $profile_picture);
                }
                $profile_picture = 'uploads/profile_pictures/' . $new_filename;
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }
        
        // Handle resume upload
        $resume = $profile['resume'] ?? null;
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 10 * 1024 * 1024; // 10MB

            if (!in_array($_FILES['resume']['type'], $allowed_types)) {
                throw new Exception("Invalid resume file type. Please upload PDF or DOC/DOCX.");
            }

            if ($_FILES['resume']['size'] > $max_size) {
                throw new Exception("Resume file too large. Maximum size is 10MB.");
            }

            $upload_dir = '../uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
            $new_filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_path)) {
                // Delete old resume if exists
                if ($resume && file_exists('../' . $resume)) {
                    unlink('../' . $resume);
                }
                $resume = 'uploads/resumes/' . $new_filename;
            } else {
                throw new Exception("Failed to upload resume.");
            }
        }
        
        // Handle cover letter template upload
        $cover_letter = $profile['cover_letter'] ?? null;
        if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['cover_letter']['type'], $allowed_types)) {
                throw new Exception("Invalid cover letter file type. Please upload PDF or DOC/DOCX.");
            }

            if ($_FILES['cover_letter']['size'] > $max_size) {
                throw new Exception("Cover letter file too large. Maximum size is 5MB.");
            }

            $upload_dir = '../uploads/cover_letters/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['cover_letter']['name'], PATHINFO_EXTENSION);
            $new_filename = 'cover_letter_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['cover_letter']['tmp_name'], $upload_path)) {
                // Delete old cover letter if exists
                if ($cover_letter && file_exists('../' . $cover_letter)) {
                    unlink('../' . $cover_letter);
                }
                $cover_letter = 'uploads/cover_letters/' . $new_filename;
            } else {
                throw new Exception("Failed to upload cover letter.");
            }
        }

        // Update profile
        $stmt = $conn->prepare("UPDATE user_profiles SET 
            first_name = ?,
            last_name = ?,
            phone = ?,
            address = ?,
            experience = ?,
            gender = ?,
            bio = ?,
            profile_picture = ?,
            resume = ?,
            cover_letter = ?
            WHERE user_id = ?");

        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['phone'],
            $_POST['location'],
            $_POST['experience'],
            $_POST['gender'] ?: null,
            $_POST['bio'],
            $profile_picture,
            $resume,
            $cover_letter,
            $_SESSION['user_id']
        ]);
        
        // Update skills if submitted
        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
            // Start transaction for skill updates
            $conn->beginTransaction();
            
            try {
                // Delete current skills
                $stmt = $conn->prepare("DELETE FROM jobseeker_skills WHERE jobseeker_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                // Add new skills
                $stmt = $conn->prepare("INSERT INTO jobseeker_skills (jobseeker_id, skill_id) VALUES (?, ?)");
                foreach ($_POST['skills'] as $skill_id) {
                    $stmt->execute([$_SESSION['user_id'], $skill_id]);
                }
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        }

        $success = "Profile updated successfully.";
        
        // Refresh profile data
        $stmt = $conn->prepare("SELECT up.*, u.email FROM user_profiles up 
                               JOIN users u ON up.user_id = u.user_id 
                               WHERE up.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Refresh skills
        $stmt = $conn->prepare("SELECT s.* FROM skills s 
                               JOIN jobseeker_skills js ON s.skill_id = js.skill_id 
                               WHERE js.jobseeker_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(Exception $e) {
    $error = $e->getMessage();
}

// Set page title
$page_title = "My Profile - Job Seeker Panel";
?>
<?php include 'includes/header.php'; ?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    /* Card design */
    .card {
        --main-color: #1a232f;
        --submain-color: #78858F;
        --bg-color: #fff;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        position: relative;
        border-radius: 20px;
        background: var(--bg-color);
        border: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .profile-card {
        padding: 20px;
        text-align: center;
    }

    .profile-picture-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 20px;
        border-radius: 100%;
        background: var(--bg-color);
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .profile-picture {
        width: 140px;
        height: 140px;
        object-fit: cover;
        border-radius: 50%;
        border: 5px solid var(--bg-color);
    }

    .profile-name {
        margin-top: 20px;
        font-weight: 600;
        font-size: 24px;
        color: var(--main-color);
    }

    .profile-email {
        margin-top: 10px;
        font-weight: 400;
        font-size: 15px;
        color: var(--submain-color);
    }

    /* Button styles */
    .btn {
        border-radius: 8px;
        font-weight: 600;
        padding: 8px 20px;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: var(--main-color);
        border: 2px solid var(--main-color);
        color: var(--bg-color);
    }

    .btn-primary:hover {
        background: var(--bg-color);
        color: var(--main-color);
    }

    .btn-outline-primary {
        border: 2px solid var(--main-color);
        color: var(--main-color);
    }

    .btn-outline-primary:hover {
        background: var(--main-color);
        color: var(--bg-color);
    }

    /* Skills cloud */
    .word-cloud {
        width: 100%;
        padding: 20px;
        margin-top: 20px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
    }

    .skill-badge {
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .skill-badge:hover {
        transform: scale(1.05);
    }

    /* File info cards */
    .file-info {
        display: flex;
        align-items: center;
        margin-top: 15px;
        padding: 15px;
        border-radius: 12px;
        background-color: #f8f9fa;
        transition: all 0.3s ease;
    }

    .file-info:hover {
        background-color: #e9ecef;
        transform: translateX(5px);
    }

    .file-info i {
        font-size: 24px;
        margin-right: 15px;
        color: var(--main-color);
    }

    /* Form controls */
    .form-control {
        border-radius: 8px;
        border: 2px solid #e0e0e0;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: var(--main-color);
        box-shadow: 0 0 0 0.2rem rgba(26, 35, 47, 0.25);
    }

    /* Tabs */
    .nav-tabs {
        border: none;
        margin-bottom: -1px;
    }

    .nav-tabs .nav-link {
        border: none;
        color: var(--submain-color);
        font-weight: 500;
        padding: 15px 25px;
        border-radius: 12px 12px 0 0;
        transition: all 0.3s ease;
    }

    .nav-tabs .nav-link.active {
        color: var(--main-color);
        background: var(--bg-color);
        font-weight: 600;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .profile-picture-container {
            width: 120px;
            height: 120px;
        }

        .profile-picture {
            width: 110px;
            height: 110px;
        }

        .profile-name {
            font-size: 20px;
        }

        .btn {
            padding: 6px 15px;
            font-size: 14px;
        }
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Profile</h2>
        <div>
            <?php if (!empty($skills) && $matching_jobs_count > 0): ?>
            <a href="find-matching-jobs.php" class="btn btn-success me-2">
                <i class="fas fa-search"></i> Find Matching Jobs
                <span class="badge bg-light text-success"><?php echo $matching_jobs_count; ?></span>
            </a>
            <?php endif; ?>
            <a href="skills.php" class="btn btn-outline-primary">
                <i class="fas fa-tools"></i> Manage Skills
            </a>
        </div>
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

    <div class="row">
        <div class="col-md-4">
            <div class="card profile-card">
                <div class="profile-picture-container">
                    <img src="<?php echo $profile['profile_picture'] ? '../' . htmlspecialchars($profile['profile_picture']) : '../assets/images/default-profile.png'; ?>" 
                         alt="Profile Picture" class="profile-picture">
                </div>
                <h3 class="profile-name"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h3>
                <p class="profile-email"><?php echo htmlspecialchars($profile['email']); ?></p>
                
                <?php if (!empty($skills)): ?>
                    <h6 class="mt-4 mb-3 text-uppercase fw-bold">My Skills</h6>
                    <div class="word-cloud">
                        <?php foreach ($skills as $index => $skill): 
                            $color = 'hsl(' . (rand(200, 240)) . ', ' . (rand(60, 80)) . '%, ' . (rand(45, 65)) . '%)';
                        ?>
                            <span class="skill-badge" style="background-color: <?php echo $color; ?>; color: white;">
                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> You haven't added any skills yet.
                        <a href="skills.php" class="alert-link">Add skills</a> to improve your job matches!
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <?php if(isset($profile['resume']) && $profile['resume']): ?>
                        <div class="file-info">
                            <i class="fas fa-file-pdf"></i>
                            <div>
                                <div class="fw-bold">Resume</div>
                                <a href="../<?php echo htmlspecialchars($profile['resume']); ?>" 
                                   target="_blank" class="text-decoration-none">
                                    View Resume
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($profile['cover_letter']) && $profile['cover_letter']): ?>
                        <div class="file-info">
                            <i class="fas fa-file-word"></i>
                            <div>
                                <div class="fw-bold">Cover Letter Template</div>
                                <?php
                                $cover_letter_url = '../' . htmlspecialchars($profile['cover_letter']);
                                $ext = strtolower(pathinfo($profile['cover_letter'], PATHINFO_EXTENSION));
                                if ($ext === 'pdf') {
                                    $view_url = $cover_letter_url;
                                } else {
                                    $abs_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $cover_letter_url;
                                    $view_url = 'https://docs.google.com/gview?url=' . urlencode($abs_url) . '&embedded=true';
                                }
                                ?>
                                <a href="<?php echo $view_url; ?>" 
                                   target="_blank" class="text-decoration-none">
                                    View Cover Letter
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" 
                                    data-bs-target="#personal" type="button" role="tab" 
                                    aria-controls="personal" aria-selected="true">
                                Personal Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="skills-tab" data-bs-toggle="tab" 
                                    data-bs-target="#skills" type="button" role="tab" 
                                    aria-controls="skills" aria-selected="false">
                                Skills
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="documents-tab" data-bs-toggle="tab" 
                                    data-bs-target="#documents" type="button" role="tab" 
                                    aria-controls="documents" aria-selected="false">
                                Documents
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="tab-content" id="profileTabsContent">
                            <!-- Personal Info Tab -->
                            <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="first_name" 
                                               value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="last_name" 
                                               value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Location <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="location" 
                                               value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Experience <span class="text-danger">*</span></label>
                                        <select class="form-select" name="experience" required>
                                            <option value="">Select Experience</option>
                                            <option value="Entry Level" <?php echo ($profile['experience'] ?? '') === 'Entry Level' ? 'selected' : ''; ?>>Entry Level</option>
                                            <option value="1-3 years" <?php echo ($profile['experience'] ?? '') === '1-3 years' ? 'selected' : ''; ?>>1-3 years</option>
                                            <option value="3-5 years" <?php echo ($profile['experience'] ?? '') === '3-5 years' ? 'selected' : ''; ?>>3-5 years</option>
                                            <option value="5-10 years" <?php echo ($profile['experience'] ?? '') === '5-10 years' ? 'selected' : ''; ?>>5-10 years</option>
                                            <option value="10+ years" <?php echo ($profile['experience'] ?? '') === '10+ years' ? 'selected' : ''; ?>>10+ years</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($profile['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($profile['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($profile['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Profile Picture</label>
                                        <input type="file" class="form-control" name="profile_picture" accept="image/*">
                                        <div class="form-text">Max file size: 5MB. Allowed formats: JPG, PNG, GIF</div>
                                        
                                        <?php if($profile['profile_picture']): ?>
                                            <div class="mt-2 d-flex align-items-center">
                                                <img src="../<?php echo htmlspecialchars($profile['profile_picture']); ?>" 
                                                     alt="Current Profile Picture" 
                                                     class="rounded-circle me-2" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="removeProfilePicture()">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <input type="hidden" name="remove_profile_picture" id="remove_profile_picture" value="0">
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Bio</label>
                                        <textarea class="form-control" name="bio" rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Skills Tab -->
                            <div class="tab-pane fade" id="skills" role="tabpanel" aria-labelledby="skills-tab">
                                <p class="mb-3">Select skills that match your expertise and qualifications. These skills will help employers find you.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Your Skills</label>
                                    <select id="skills-select" name="skills[]" multiple>
                                        <?php 
                                        $user_skill_ids = array_column($skills, 'skill_id');
                                        foreach ($all_skills as $skill): 
                                        ?>
                                            <option value="<?php echo $skill['skill_id']; ?>" 
                                                    <?php echo in_array($skill['skill_id'], $user_skill_ids) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                                                <?php if (isset($skill['priority']) && $skill['priority'] > 1): ?>
                                                    (Priority: <?php echo $skill['priority']; ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select multiple skills that represent your expertise</div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> 
                                    Adding skills that match job requirements increases your chances of being recommended to employers.
                                </div>
                            </div>
                            
                            <!-- Documents Tab -->
                            <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Resume</label>
                                        <input type="file" class="form-control" name="resume" accept=".pdf,.doc,.docx">
                                        <div class="form-text">Max file size: 10MB. Allowed formats: PDF, DOC, DOCX</div>
                                        
                                        <?php if(isset($profile['resume']) && $profile['resume']): ?>
                                            <div class="file-info mt-2">
                                                <i class="fas fa-file-pdf text-danger"></i>
                                                <div>
                                                    <small>Current Resume: 
                                                        <a href="../<?php echo htmlspecialchars($profile['resume']); ?>" target="_blank">
                                                            View
                                                        </a>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cover Letter Template</label>
                                        <input type="file" class="form-control" name="cover_letter" accept=".pdf,.doc,.docx">
                                        <div class="form-text">Max file size: 5MB. Allowed formats: PDF, DOC, DOCX</div>
                                        
                                        <?php if(isset($profile['cover_letter']) && $profile['cover_letter']): ?>
                                            <div class="file-info mt-2">
                                                <i class="fas fa-file-word text-primary"></i>
                                                <div>
                                                    <div>Cover Letter Template</div>
                                                    <small>
                                                        <?php
                                                        $cover_letter_url = '../' . htmlspecialchars($profile['cover_letter']);
                                                        $ext = strtolower(pathinfo($profile['cover_letter'], PATHINFO_EXTENSION));
                                                        if ($ext === 'pdf') {
                                                            $view_url = $cover_letter_url;
                                                        } else {
                                                            $abs_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $cover_letter_url;
                                                            $view_url = 'https://docs.google.com/gview?url=' . urlencode($abs_url) . '&embedded=true';
                                                        }
                                                        ?>
                                                        <a href="<?php echo $view_url; ?>" target="_blank">
                                                            View Cover Letter
                                                        </a>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Documents will be shared with employers</strong> when you apply for jobs.
                                            Make sure they are professional and up-to-date.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    // Initialize Tom Select for skills selection
    document.addEventListener('DOMContentLoaded', function() {
        new TomSelect('#skills-select', {
            plugins: ['remove_button'],
            placeholder: 'Select your skills...',
            render: {
                option: function(data, escape) {
                    return '<div>' +
                        '<span class="title">' + escape(data.text) + '</span>' +
                        '</div>';
                },
                item: function(data, escape) {
                    return '<div>' + escape(data.text) + '</div>';
                }
            }
        });
        
        // Handle tab persistence
        const triggerTabList = document.querySelectorAll('#profileTabs button');
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                // Store the active tab in localStorage
                localStorage.setItem('activeProfileTab', this.getAttribute('id'));
            });
        });
        
        // Restore active tab from localStorage
        const activeTab = localStorage.getItem('activeProfileTab');
        if (activeTab) {
            const tab = document.querySelector('#' + activeTab);
            if (tab) {
                const tabTrigger = new bootstrap.Tab(tab);
                tabTrigger.show();
            }
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
    
    // Function to remove profile picture
    function removeProfilePicture() {
        if (confirm('Are you sure you want to remove your profile picture?')) {
            document.getElementById('remove_profile_picture').value = '1';
            // Hide the current picture preview and remove button
            document.querySelector('.mt-2.d-flex.align-items-center').style.display = 'none';
            // Show feedback message
            const feedback = document.createElement('div');
            feedback.className = 'alert alert-warning mt-2';
            feedback.innerHTML = '<i class="fas fa-info-circle"></i> Profile picture will be removed when you save changes.';
            document.querySelector('input[name="profile_picture"]').parentNode.appendChild(feedback);
        }
    }
</script>

<?php include 'includes/footer.php'; ?> 