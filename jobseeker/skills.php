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
$skills = [];
$all_skills = [];
$matching_jobs_count = 0;

try {
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
        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
            // Handle multiple skills selection (bulk update)
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
                $success = "Skills updated successfully.";
                
                // Refresh skills
                $stmt = $conn->prepare("SELECT s.* FROM skills s 
                                       JOIN jobseeker_skills js ON s.skill_id = js.skill_id 
                                       WHERE js.jobseeker_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Update matching jobs count
                if (!empty($skills)) {
                    $matching_jobs_count = getMatchingJobsCount($conn, $_SESSION['user_id']);
                }
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        } elseif (isset($_POST['add_skill'])) {
            // Handle individual skill addition (existing skill or new skill creation)
            
            if (isset($_POST['new_skill']) && !empty(trim($_POST['new_skill']))) {
                // Creating a new skill
                $new_skill_name = trim($_POST['new_skill']);
                $skill_description = isset($_POST['skill_description']) ? trim($_POST['skill_description']) : '';
                $skill_category = isset($_POST['skill_category']) ? trim($_POST['skill_category']) : null;
                
                $conn->beginTransaction();
                
                try {
                    // Check if skill already exists (case-insensitive)
                    $stmt = $conn->prepare("SELECT skill_id FROM skills WHERE LOWER(skill_name) = LOWER(?)");
                    $stmt->execute([$new_skill_name]);
                    $existing_skill = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_skill) {
                        $skill_id = $existing_skill['skill_id'];
                        $message = "Skill already exists! Added to your profile.";
                    } else {
                        // Create new skill (check which columns exist)
                        $stmt = $conn->query("SHOW COLUMNS FROM skills");
                        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $priority = 3; // Default priority for user-created skills
                        
                        if (in_array('category', $columns) && in_array('created_by_user', $columns)) {
                            // Full new schema
                            $stmt = $conn->prepare("INSERT INTO skills (skill_name, description, category, priority, created_by_user) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$new_skill_name, $skill_description, $skill_category, $priority, true]);
                        } elseif (in_array('category', $columns)) {
                            // Has category but not created_by_user
                            $stmt = $conn->prepare("INSERT INTO skills (skill_name, description, category, priority) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$new_skill_name, $skill_description, $skill_category, $priority]);
                        } else {
                            // Basic schema - only skill_name, description, priority
                            $stmt = $conn->prepare("INSERT INTO skills (skill_name, description, priority) VALUES (?, ?, ?)");
                            $stmt->execute([$new_skill_name, $skill_description, $priority]);
                        }
                        $skill_id = $conn->lastInsertId();
                        $message = "New skill created and added to your profile!";
                    }
                    
                    // Check if user already has this skill
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM jobseeker_skills WHERE jobseeker_id = ? AND skill_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $skill_id]);
                    $has_skill = $stmt->fetchColumn() > 0;
                    
                    if (!$has_skill) {
                        // Add skill to user's profile
                        $stmt = $conn->prepare("INSERT INTO jobseeker_skills (jobseeker_id, skill_id) VALUES (?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $skill_id]);
                    } else {
                        $message = "You already have this skill in your profile.";
                    }
                    
                    $conn->commit();
                    $success = $message;
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
                
            } elseif (isset($_POST['skill_id']) && !empty($_POST['skill_id'])) {
                // Adding existing skill
                $skill_id = $_POST['skill_id'];
                
                // Check if user already has this skill
                $stmt = $conn->prepare("SELECT COUNT(*) FROM jobseeker_skills WHERE jobseeker_id = ? AND skill_id = ?");
                $stmt->execute([$_SESSION['user_id'], $skill_id]);
                $has_skill = $stmt->fetchColumn() > 0;
                
                if (!$has_skill) {
                    $stmt = $conn->prepare("INSERT INTO jobseeker_skills (jobseeker_id, skill_id) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $skill_id]);
                    $success = "Skill added to your profile successfully.";
                } else {
                    throw new Exception("You already have this skill in your profile.");
                }
            } else {
                throw new Exception("Please select a skill or enter a new skill name.");
            }
            
            // Refresh skills after any addition
            $stmt = $conn->prepare("SELECT s.* FROM skills s 
                                   JOIN jobseeker_skills js ON s.skill_id = js.skill_id 
                                   WHERE js.jobseeker_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update matching jobs count
            if (!empty($skills)) {
                $matching_jobs_count = getMatchingJobsCount($conn, $_SESSION['user_id']);
            }
            
        } else {
            throw new Exception("Please select at least one skill or enter a new skill name.");
        }
    }

} catch(Exception $e) {
    $error = $e->getMessage();
}

// Set page title
$page_title = "My Skills - Job Seeker Panel";
?>
<?php include 'includes/header.php'; ?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .skill-badge {
        font-size: 1rem;
        margin: 0.3rem;
        padding: 0.5rem 1rem;
    }
    .skill-card {
        transition: transform 0.2s;
        border-radius: 8px;
        border-left: 5px solid #007bff;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .skill-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .priority-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 0.8rem;
    }
    .match-button {
        background-color: #28a745;
        border-color: #28a745;
        font-weight: 600;
        box-shadow: 0 4px 6px rgba(40, 167, 69, 0.2);
        transition: all 0.3s ease;
    }
    .match-button:hover {
        background-color: #218838;
        border-color: #1e7e34;
        transform: translateY(-2px);
        box-shadow: 0 6px 8px rgba(40, 167, 69, 0.3);
    }
    .match-button .badge {
        background-color: #fff;
        color: #28a745;
        margin-left: 5px;
    }
    /* Word Cloud Styles */
    .word-cloud-container {
        background-color: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
    }
    .word-cloud {
        width: 100%;
        height: 300px;
        position: relative;
    }
    .cloud-skill {
        position: absolute;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        padding: 5px;
        border-radius: 5px;
    }
    .cloud-skill:hover {
        transform: scale(1.1);
        z-index: 10;
    }
    .cloud-priority-1 { font-size: 14px; opacity: 0.7; }
    .cloud-priority-2 { font-size: 18px; opacity: 0.8; }
    .cloud-priority-3 { font-size: 22px; opacity: 0.9; }
    .cloud-priority-4 { font-size: 26px; }
    .cloud-priority-5 { font-size: 30px; }
    .multi-skill-select {
        margin-bottom: 20px;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Skills</h2>
        <div>
            <?php if (!empty($skills) && $matching_jobs_count > 0): ?>
            <a href="find-matching-jobs.php" class="btn btn-success me-2 match-button">
                <i class="fas fa-search"></i> Find Matching Jobs
                <span class="badge rounded-pill"><?php echo $matching_jobs_count; ?></span>
            </a>
            <?php endif; ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
            <i class="fas fa-plus"></i> Add New Skill
        </button>
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

    <!-- Current Skills -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Current Skills</h5>
        </div>
        <div class="card-body">
            <?php if (empty($skills)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You haven't added any skills yet. 
                    Add skills to improve your job matches!
                </div>
            <?php else: ?>
                <!-- Word Cloud Visualization -->
                <div class="word-cloud-container">
                    <h6 class="mb-3"><i class="fas fa-cloud"></i> Skills Word Cloud</h6>
                    
                    <!-- Category Filter -->
                    <div class="mb-3">
                        <select id="cloud-category-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                            <option value="">All Categories</option>
                            <?php
                            // Get unique categories from skills
                            $categories = [];
                            foreach ($skills as $skill) {
                                if (!empty($skill['category']) && !in_array($skill['category'], $categories)) {
                                    $categories[] = $skill['category'];
                                }
                            }
                            sort($categories);
                            
                            // Output category options
                            foreach ($categories as $category) {
                                echo '<option value="' . htmlspecialchars($category) . '">' . htmlspecialchars($category) . '</option>';
                            }
                            ?>
                        </select>
                        <span class="ms-2 text-muted small">Filter by category</span>
                    </div>
                    
                    <div class="word-cloud" id="skills-cloud">
                        <?php 
                        foreach ($skills as $skill): 
                            // Determine priority or use random for visual interest
                            $priority = isset($skill['priority']) ? min($skill['priority'], 5) : rand(1, 5);
                            // Generate random position
                            $left = rand(5, 85);
                            $top = rand(5, 85);
                            // Generate random color in blue/green spectrum
                            $hue = rand(180, 250);
                            $sat = rand(60, 100);
                            $light = rand(40, 70);
                            
                            // Add data-category attribute for filtering
                            $category = isset($skill['category']) ? htmlspecialchars($skill['category']) : '';
                        ?>
                        <a href="find-matching-jobs.php?skill_id=<?php echo $skill['skill_id']; ?>" 
                           class="cloud-skill cloud-priority-<?php echo $priority; ?>" 
                           data-category="<?php echo $category; ?>"
                           style="left: <?php echo $left; ?>%; top: <?php echo $top; ?>%; color: hsl(<?php echo $hue; ?>, <?php echo $sat; ?>%, <?php echo $light; ?>%);">
                            <?php echo htmlspecialchars($skill['skill_name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center text-muted small mt-2">
                        <i class="fas fa-info-circle"></i> Click on a skill to find matching jobs
                    </div>
                </div>

                <div class="row">
                    <?php foreach ($skills as $skill): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card skill-card position-relative">
                                <?php if (isset($skill['priority']) && $skill['priority'] > 1): ?>
                                <div class="priority-indicator">
                                    <span class="badge bg-warning">Priority: <?php echo $skill['priority']; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($skill['skill_name']); ?></h5>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="skill_id" value="<?php echo $skill['skill_id']; ?>">
                                            <button type="submit" name="remove_skill" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure you want to remove this skill?')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <p class="card-text text-muted mt-2">
                                        <?php echo htmlspecialchars($skill['description'] ?? ''); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
        <a href="find-matching-jobs.php" class="btn btn-success btn-lg match-button">
            <i class="fas fa-search"></i> Find Matching Jobs
            <?php if ($matching_jobs_count > 0): ?>
            <span class="badge rounded-pill"><?php echo $matching_jobs_count; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- Add Skill Modal -->
<div class="modal fade" id="addSkillModal" tabindex="-1" aria-labelledby="addSkillModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSkillModalLabel">Add Skills</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="addSkillTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="multiple-tab" data-bs-toggle="tab" data-bs-target="#multiple" type="button" role="tab">
                            Multiple Skills
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="existing-tab" data-bs-toggle="tab" data-bs-target="#existing" type="button" role="tab">
                            Existing Skill
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#new" type="button" role="tab">
                            New Skill
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content pt-3" id="addSkillTabsContent">
                    <!-- Multiple Skills Tab -->
                    <div class="tab-pane fade show active" id="multiple" role="tabpanel">
                        <p class="text-muted">Select multiple skills to add to your profile at once.</p>
                        <form method="POST" id="multiple-skills-form">
                            <div class="mb-3 multi-skill-select">
                                <select id="multiple-skills-select" name="skills[]" multiple class="form-control">
                                    <?php 
                                    $user_skill_ids = array_column($skills, 'skill_id');
                                    foreach ($all_skills as $skill): 
                                        if (!in_array($skill['skill_id'], $user_skill_ids)):
                                    ?>
                                        <option value="<?php echo $skill['skill_id']; ?>">
                                            <?php echo htmlspecialchars($skill['skill_name']); ?>
                                        </option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Selected Skills</button>
                        </form>
                    </div>
                    
                    <!-- Existing Skill Tab -->
                    <div class="tab-pane fade" id="existing" role="tabpanel">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="skill_id" class="form-label">Select Skill</label>
                                <select class="form-select" id="skill_id" name="skill_id" required>
                                    <option value="">Select a skill</option>
                                    <?php 
                                    $user_skill_ids = array_column($skills, 'skill_id');
                                    foreach ($all_skills as $skill): 
                                        if (!in_array($skill['skill_id'], $user_skill_ids)):
                                    ?>
                                    <option value="<?php echo $skill['skill_id']; ?>">
                                            <?php echo htmlspecialchars($skill['skill_name']); ?>
                                    </option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                            <button type="submit" name="add_skill" class="btn btn-primary">Add Skill</button>
                        </form>
                    </div>
                    
                    <!-- New Skill Tab -->
                    <div class="tab-pane fade" id="new" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Create a new skill!</strong> If you can't find a skill in our existing list, you can create a new one here. 
                            It will be added to the system and become available for other users too.
                        </div>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="skill_category" class="form-label">Skill Category (Optional)</label>
                                <select class="form-select" id="skill_category" name="skill_category">
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
                                <div class="form-text">Select a category to help organize your skill</div>
                            </div>
                            <div class="mb-3">
                                <label for="new_skill" class="form-label">Skill Name *</label>
                                <input type="text" class="form-control" id="new_skill" name="new_skill" required 
                                       placeholder="e.g., Machine Learning, Customer Service, Project Management" 
                                       autocomplete="off">
                                <div class="form-text">Enter a clear, specific skill name</div>
                            </div>
                            <div class="mb-3">
                                <label for="skill_description" class="form-label">Description (Optional)</label>
                                <textarea class="form-control" id="skill_description" name="skill_description" rows="3" 
                                          placeholder="Brief description of this skill, its applications, or your experience with it"></textarea>
                                <div class="form-text">Help others understand what this skill involves</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="add_skill" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus-circle"></i> Create and Add New Skill
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Skill suggestions by category
        const skillSuggestions = {
            "Engineering": [
                "Civil Engineering", "Mechanical Engineering", "Electrical Engineering", "Structural Analysis",
                "AutoCAD", "Revit", "HVAC", "Plumbing Design", "Power Systems", "Circuit Design",
                "MEP Coordination", "Steel Detailing", "Building Information Modeling (BIM)", "Control Systems",
                "Energy Efficiency", "Thermodynamics", "Fluid Mechanics", "Robotics", "Pneumatics", "CNC Programming",
                "Aerospace Engineering", "Chemical Engineering", "Biomedical Engineering", "Environmental Engineering",
                "Industrial Engineering", "Materials Science", "Nuclear Engineering", "Petroleum Engineering", 
                "Structural Engineering", "Transportation Engineering", "Construction Management", "Project Planning",
                "Quality Control", "Safety Engineering", "Technical Drawing", "Manufacturing Processes",
                "Lean Manufacturing", "Process Optimization", "Systems Engineering", "CAD/CAM", "Geometric Dimensioning"
            ],
            "Finance": [
                "Financial Analysis", "Banking", "Accounting", "Bookkeeping", "Financial Reporting",
                "Financial Modeling", "Budgeting", "Forecasting", "Risk Management", "Tax Preparation",
                "Auditing", "Investment Analysis", "Portfolio Management", "Corporate Finance", "Capital Markets",
                "Financial Planning", "QuickBooks", "Excel Financial Functions", "SAP Finance", "Payroll Processing",
                "Cost Accounting", "Management Accounting", "Treasury Management", "Credit Analysis", "Debt Management",
                "Equity Research", "Merger & Acquisition", "Valuation", "Derivatives Trading", "Foreign Exchange",
                "Insurance Underwriting", "Mortgage Lending", "Personal Banking", "Retirement Planning", "Wealth Management",
                "Compliance", "GAAP", "IFRS", "Financial Regulations", "Asset Management", "Cryptocurrency", "Blockchain Finance"
            ],
            "Healthcare": [
                "Medical Coding", "Nursing", "Pharmacy", "Patient Care", "Electronic Health Records (EHR)",
                "Medical Billing", "Clinical Documentation", "Medical Terminology", "Healthcare Administration",
                "HIPAA Compliance", "CPR", "First Aid", "Medication Administration", "Phlebotomy", "Vital Signs",
                "Medical Device Operation", "Patient Education", "Infection Control", "Telehealth", "Care Coordination",
                "Physical Therapy", "Occupational Therapy", "Radiology", "Laboratory Testing", "Public Health",
                "Mental Health", "Dental Care", "Optometry", "Pediatrics", "Geriatrics", "Oncology", "Cardiology",
                "Emergency Medicine", "Surgery Assistance", "Anesthesia", "Obstetrics", "Nutrition", "Health Information Management",
                "Clinical Research", "Medical Transcription", "Hospital Management", "Ambulatory Care", "Case Management"
            ],
            "Human Resources": [
                "Recruitment", "Talent Acquisition", "HR Management", "Onboarding", "Training & Development",
                "Employee Relations", "Performance Management", "Compensation & Benefits", "HRIS Systems",
                "Workforce Planning", "Conflict Resolution", "Labor Relations", "HR Compliance", "Succession Planning",
                "Employee Engagement", "Diversity & Inclusion", "ATS Software", "HR Policy Development", "Exit Interviews",
                "Organizational Development", "Change Management", "Benefits Administration", "Payroll Management", 
                "Employee Wellness Programs", "Career Development", "Job Analysis", "HRIS Implementation", "HR Analytics",
                "Talent Management", "Employment Law", "Workforce Management", "Employee Retention", "Leadership Development",
                "Team Building", "Employee Counseling", "Salary Negotiation", "International HR", "Human Capital Management"
            ],
            "Information Technology": [
                "Software Development", "Web Development", "Networking", "System Administration", "Cloud Computing",
                "Database Management", "Cybersecurity", "DevOps", "Machine Learning", "Data Analysis",
                "UI/UX Design", "Mobile App Development", "JavaScript", "Python", "Java", "SQL", "AWS", "Azure",
                "Git", "Docker", "Kubernetes", "Linux", "Windows Server", "HTML/CSS", "React", "Node.js",
                "PHP", "C#", ".NET", "Android Development", "iOS Development", "Network Security", "Agile Methodologies",
                "Scrum", "JIRA", "Technical Support", "Troubleshooting", "API Development", "Testing/QA",
                "Full Stack Development", "Frontend Development", "Backend Development", "Data Science", "Artificial Intelligence",
                "Big Data", "Hadoop", "Blockchain", "Game Development", "IoT Development", "Virtual Reality",
                "Augmented Reality", "Natural Language Processing", "Computer Vision", "Embedded Systems", "Microservices",
                "CI/CD", "Infrastructure as Code", "Serverless Architecture", "IT Service Management", "ITIL", 
                "Technical Documentation", "SEO Development", "Business Intelligence", "Data Warehousing", "ETL Processes"
            ],
            "Marketing": [
                "Digital Marketing", "Content Marketing", "Social Media Marketing", "SEO", "SEM",
                "Email Marketing", "Marketing Analytics", "Brand Management", "Public Relations",
                "Advertising", "Campaign Management", "Market Research", "Google Analytics", "Google Ads",
                "Facebook Ads", "Instagram Marketing", "Content Creation", "Copywriting", "CRM Systems",
                "Marketing Automation", "HubSpot", "Mailchimp", "Marketing Strategy", "Lead Generation",
                "Customer Segmentation", "Product Marketing", "Direct Marketing", "Affiliate Marketing", "Mobile Marketing",
                "Influencer Marketing", "Video Marketing", "Event Marketing", "Exhibition Management", "Trade Show Management",
                "Graphic Design", "Media Planning", "Market Analysis", "Competitive Analysis", "Customer Journey Mapping",
                "User Persona Development", "A/B Testing", "Conversion Rate Optimization", "Marketing ROI Analysis",
                "Growth Hacking", "Community Management", "Branding", "Marketing Communications", "Print Media"
            ],
            "Sales": [
                "Business Development", "Account Management", "Retail Sales", "B2B Sales", "B2C Sales",
                "Inside Sales", "Outside Sales", "Sales Management", "CRM Usage", "Cold Calling",
                "Lead Generation", "Sales Negotiation", "Upselling", "Cross-selling", "Consultative Selling",
                "Solution Selling", "Territory Management", "Salesforce", "Customer Relationship Building",
                "Closing Techniques", "Sales Presentations", "Contract Negotiation", "Sales Forecasting",
                "Key Account Management", "Distributor Management", "Channel Sales", "Enterprise Sales", "SMB Sales",
                "Direct Sales", "Indirect Sales", "E-commerce", "Retail Merchandising", "Point of Sale Systems",
                "Product Demonstrations", "Customer Needs Analysis", "Sales Funnel Management", "Proposal Writing",
                "Sales Training", "Quota Management", "Objection Handling", "Value Proposition Development",
                "Customer Success", "Client Retention", "Competitive Selling", "Technical Sales", "Sales Analytics"
            ]
        };

        // Initialize Tom Select for multiple skills selection
        const multipleSkillsSelect = new TomSelect('#multiple-skills-select', {
            plugins: ['remove_button'],
            placeholder: 'Search and select skills...',
            maxItems: null,
            create: false,
            render: {
                option: function(data, escape) {
                    return '<div>' + escape(data.text) + '</div>';
                }
            }
        });
        
        // Initialize Tom Select for single skill selection in existing skills tab
        new TomSelect('#skill_id', {
            placeholder: 'Search for a skill...',
            create: false
        });
        
        // Enhanced new skill input with category-based suggestions
        const newSkillInput = document.getElementById('new_skill');
        const skillCategorySelect = document.getElementById('skill_category');
        
        if (newSkillInput && skillCategorySelect) {
            // Create datalist for suggestions
            const datalistId = 'skill-suggestions';
            const datalist = document.createElement('datalist');
            datalist.id = datalistId;
            
            // Initially add all skill suggestions to the datalist
            for (const category in skillSuggestions) {
                skillSuggestions[category].forEach(skill => {
                    const option = document.createElement('option');
                    option.value = skill;
                    datalist.appendChild(option);
                });
            }
            
            // Add datalist to document and connect it to input
            document.body.appendChild(datalist);
            newSkillInput.setAttribute('list', datalistId);
            
            // Function to update suggestions based on category
            function updateSuggestions(selectedCategory) {
                // Clear existing datalist
                while (datalist.firstChild) {
                    datalist.removeChild(datalist.firstChild);
                }
                
                // Add filtered suggestions
                if (selectedCategory === '') {
                    // Add all categories
                    for (const cat in skillSuggestions) {
                        skillSuggestions[cat].forEach(skill => {
                            const option = document.createElement('option');
                            option.value = skill;
                            datalist.appendChild(option);
                        });
                    }
                } else {
                    // Add only selected category
                    if (skillSuggestions[selectedCategory]) {
                        skillSuggestions[selectedCategory].forEach(skill => {
                            const option = document.createElement('option');
                            option.value = skill;
                            datalist.appendChild(option);
                        });
                    }
                }
            }
            
            // Add event listener to category selector
            skillCategorySelect.addEventListener('change', function() {
                updateSuggestions(this.value);
                newSkillInput.focus(); // Focus on input after category selection
            });
            
            // Add visual feedback for new skill creation
            newSkillInput.addEventListener('input', function() {
                const skillName = this.value.trim();
                const existingSkills = Array.from(datalist.children).map(option => option.value.toLowerCase());
                
                if (skillName && !existingSkills.includes(skillName.toLowerCase())) {
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
                }
            });
        }
        
        // Word cloud dynamic positioning (to avoid overlaps as much as possible)
        function randomizeCloudPositions() {
            const cloudSkills = document.querySelectorAll('.cloud-skill');
            const positions = [];
            
            cloudSkills.forEach(skill => {
                let attempts = 0;
                let valid = false;
                let left, top;
                
                // Try to find non-overlapping position
                while (!valid && attempts < 50) {
                    left = Math.random() * 80 + 5;
                    top = Math.random() * 80 + 5;
                    
                    // Check for overlaps with existing positions
                    valid = true;
                    for (const pos of positions) {
                        const distance = Math.sqrt(
                            Math.pow(left - pos.left, 2) + 
                            Math.pow(top - pos.top, 2)
                        );
                        if (distance < 15) {
                            valid = false;
                            break;
                        }
                    }
                    
                    attempts++;
                }
                
                positions.push({ left, top });
                skill.style.left = left + '%';
                skill.style.top = top + '%';
            });
        }
        
        // Run cloud positioning
        randomizeCloudPositions();
        
        // Add event listener for word cloud category filter
        const categoryFilter = document.getElementById('cloud-category-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                const selectedCategory = this.value;
                const cloudSkills = document.querySelectorAll('.cloud-skill');
                
                cloudSkills.forEach(skill => {
                    if (!selectedCategory || skill.getAttribute('data-category') === selectedCategory) {
                        skill.style.display = 'inline-block';
                    } else {
                        skill.style.display = 'none';
                    }
                });
                
                // If filtering, reposition visible skills
                if (selectedCategory) {
                    // Get visible skills
                    const visibleSkills = document.querySelectorAll('.cloud-skill[style*="display: inline-block"]');
                    const positions = [];
                    
                    visibleSkills.forEach(skill => {
                        let attempts = 0;
                        let valid = false;
                        let left, top;
                        
                        // Try to find non-overlapping position
                        while (!valid && attempts < 50) {
                            left = Math.random() * 80 + 5;
                            top = Math.random() * 80 + 5;
                            
                            // Check for overlaps with existing positions
                            valid = true;
                            for (const pos of positions) {
                                const distance = Math.sqrt(
                                    Math.pow(left - pos.left, 2) + 
                                    Math.pow(top - pos.top, 2)
                                );
                                if (distance < 15) {
                                    valid = false;
                                    break;
                                }
                            }
                            
                            attempts++;
                        }
                        
                        positions.push({ left, top });
                        skill.style.left = left + '%';
                        skill.style.top = top + '%';
                    });
                } else {
                    // If showing all, do a full reposition
                    randomizeCloudPositions();
                }
            });
        }

        // Handle form submission
        document.getElementById('multiple-skills-form').addEventListener('submit', function(e) {
            const selectedSkills = multipleSkillsSelect.getValue();
            if (selectedSkills.length === 0) {
                e.preventDefault();
                alert('Please select at least one skill.');
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?> 