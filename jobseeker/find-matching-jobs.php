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
$skills = [];
$matching_jobs = [];
$skill_filter = null;

try {
    // Get user skills
    $stmt = $conn->prepare("SELECT s.* FROM skills s 
                           JOIN jobseeker_skills js ON s.skill_id = js.skill_id 
                           WHERE js.jobseeker_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($skills)) {
        $error = "You haven't added any skills yet. Please add skills to find matching jobs.";
    } else {
        // Filter by specific skill if requested
        if (isset($_GET['skill_id']) && !empty($_GET['skill_id'])) {
            $skill_filter = $_GET['skill_id'];
            
            // Get the specific skill name
            $stmt = $conn->prepare("SELECT skill_name FROM skills WHERE skill_id = ?");
            $stmt->execute([$skill_filter]);
            $skill_name = $stmt->fetchColumn();
            
            // Get matching jobs using the helper function
            $matching_jobs = getMatchingJobs($conn, $_SESSION['user_id'], $skill_filter);
        } else {
            // Get all matching jobs using the helper function
            $matching_jobs = getMatchingJobs($conn, $_SESSION['user_id']);
        }
        
        // Check which jobs the user has already applied for
        if (!empty($matching_jobs)) {
            foreach ($matching_jobs as &$job) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND jobseeker_id = ?");
                $stmt->execute([$job['job_id'], $_SESSION['user_id']]);
                $job['already_applied'] = $stmt->fetchColumn() > 0;
            }
        }
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Set page title
$page_title = "Matching Jobs - Job Seeker Panel";
?>
<?php include 'includes/header.php'; ?>

<style>
    .job-card {
        transition: transform 0.2s;
        margin-bottom: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .job-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .job-header {
        background-color: #f8f9fa;
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        position: relative;
        display: flex;
        align-items: center;
    }
    .company-logo {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 5px;
        margin-right: 15px;
        background-color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e9ecef;
    }
    .company-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .job-title-container {
        flex: 1;
    }
    .match-percentage {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 1.2rem;
        font-weight: bold;
    }
    .match-high {
        color: #28a745;
    }
    .match-medium {
        color: #ffc107;
    }
    .match-low {
        color: #dc3545;
    }
    .skill-badge {
        margin: 0.2rem;
        background-color: #e9ecef;
        color: #495057;
        font-weight: normal;
    }
    .filter-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .filter-label {
        font-weight: bold;
        margin-right: 10px;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <?php if ($skill_filter): ?>
                Jobs Matching "<?php echo htmlspecialchars($skill_name); ?>"
            <?php else: ?>
                Jobs Matching Your Skills
            <?php endif; ?>
        </h2>
        <a href="skills.php" class="btn btn-primary">
            <i class="fas fa-tools"></i> Manage Skills
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($skills) && empty($matching_jobs)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No matching jobs found based on your skills. 
            Try adding more skills or check back later for new job postings.
        </div>
    <?php endif; ?>

    <?php if (!empty($skills)): ?>
        <!-- Filter by Skill -->
        <div class="filter-container">
            <form method="GET" class="row align-items-center">
                <div class="col-md-3">
                    <span class="filter-label">Filter by skill:</span>
                </div>
                <div class="col-md-7">
                    <select name="skill_id" class="form-select">
                        <option value="">All My Skills</option>
                        <?php foreach ($skills as $skill): ?>
                            <option value="<?php echo $skill['skill_id']; ?>" 
                                    <?php echo ($skill_filter == $skill['skill_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
        
        <!-- Skills Match Explanation Card -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-info-circle text-primary"></i> How Job Matching Works</h5>
                <p class="card-text">
                    We match jobs based on the skills you've added to your profile. The match percentage shows how many of the job's required skills you have:
                </p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <span class="match-high display-6">100%</span>
                            <p class="small">Excellent Match</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <span class="match-medium display-6">80-99%</span>
                            <p class="small">Good Match</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <span class="match-low display-6">Below 80%</span>
                            <p class="small">Partial Match</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matching Jobs -->
        <?php if (!empty($matching_jobs)): ?>
            <div class="row">
                <?php foreach ($matching_jobs as $job): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card job-card">
                            <div class="job-header">
                                <div class="company-logo">
                                    <?php if (!empty($job['company_logo'])): ?>
                                        <img src="../uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" alt="Company Logo">
                                    <?php else: ?>
                                        <i class="fas fa-building fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="job-title-container">
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                </div>
                                <div class="match-percentage <?php 
                                    if ($job['match_percentage'] == 100) echo 'match-high';
                                    elseif ($job['match_percentage'] >= 80) echo 'match-medium';
                                    else echo 'match-low';
                                ?>">
                                    <?php echo $job['match_percentage']; ?>%
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($job['job_type'] ?? ''); ?></span>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($job['location'] ?? ''); ?></span>
                                    <span class="badge bg-success">₱<?php echo htmlspecialchars($job['salary_range'] ?? ''); ?></span>
                                    <span class="badge bg-info">
                                        <i class="fas fa-users"></i> <?php echo htmlspecialchars($job['vacancies'] ?? 1); ?> Vacancies
                                    </span>
                                </div>
                                
                                <p class="card-text">
                                    <?php 
                                    $description = htmlspecialchars($job['description']);
                                    echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                    ?>
                                </p>
                                
                                <?php if (!empty($job['matching_skills'])): ?>
                                    <div class="mb-3">
                                        <small class="text-success"><strong>Your matching skills:</strong></small><br>
                                        <?php 
                                        $matching_skills = explode(',', $job['matching_skills']);
                                        foreach ($matching_skills as $skill): 
                                        ?>
                                            <span class="badge skill-badge bg-success text-white"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['missing_skills'])): ?>
                                    <div class="mb-3">
                                        <small class="text-warning"><strong>Skills to improve:</strong></small><br>
                                        <?php 
                                        $missing_skills = explode(',', $job['missing_skills']);
                                        foreach ($missing_skills as $skill): 
                                        ?>
                                            <span class="badge skill-badge bg-warning text-dark"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Posted: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                    </small>
                                    <div>
                                        <a href="view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($job['already_applied']): ?>
                                            <button class="btn btn-sm btn-success" disabled>
                                                <i class="fas fa-check"></i> Applied
                                            </button>
                                        <?php else: ?>
                                            <a href="apply-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-paper-plane"></i> Apply
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?> 