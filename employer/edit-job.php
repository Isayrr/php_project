<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;
$job = null;

try {
    // Get company ID
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new Exception("Please complete your company profile first.");
    }

    // Get job ID from URL
    $job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Verify job belongs to company
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_id = ? AND company_id = ?");
    $stmt->execute([$job_id, $company['company_id']]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("Invalid job ID or you don't have permission to edit this job.");
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate required fields
        $required_fields = ['title', 'description', 'job_type', 'location', 'salary_range', 'requirements'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Validate salary range format - allow more flexible formatting with currency symbols and commas
        // This change means we're no longer enforcing a strict format for salary ranges
        /*if (!preg_match('/^\d+-\d+$/', $_POST['salary_range'])) {
            throw new Exception("Salary range must be in format: min-max (e.g., 30000-50000)");
        }*/

        // Validate deadline date
        $deadline = strtotime($_POST['deadline_date']);
        if ($deadline === false || $deadline < time()) {
            throw new Exception("Please select a valid future deadline date.");
        }

        // Update job
        $stmt = $conn->prepare("UPDATE jobs SET 
            title = ?,
            description = ?,
            job_type = ?,
            location = ?,
            salary_range = ?,
            requirements = ?,
            deadline_date = ?,
            status = ?
            WHERE job_id = ? AND company_id = ?");

        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['job_type'],
            $_POST['location'],
            $_POST['salary_range'],
            $_POST['requirements'],
            $_POST['deadline_date'],
            $_POST['status'],
            $job_id,
            $company['company_id']
        ]);

        $success = "Job updated successfully.";
        
        // Refresh job data
        $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_id = ?");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Edit Job - Employer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <h2>Edit Job</h2>
                <a href="jobs.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Jobs
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

            <?php if ($job): ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Job Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" 
                                           value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Job Type <span class="text-danger">*</span></label>
                                    <select class="form-select" name="job_type" required>
                                        <option value="full-time" <?php echo $job['job_type'] === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                        <option value="part-time" <?php echo $job['job_type'] === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                                        <option value="contract" <?php echo $job['job_type'] === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                        <option value="internship" <?php echo $job['job_type'] === 'internship' ? 'selected' : ''; ?>>Internship</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="location" 
                                           value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Salary Range (Per Month) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="salary_range" 
                                           value="<?php echo htmlspecialchars($job['salary_range']); ?>" 
                                           placeholder="e.g., $2,000-$3,500" required>
                                    <div class="form-text">Enter monthly salary range in format: min-max</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Application Deadline <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="deadline_date" 
                                           value="<?php echo date('Y-m-d', strtotime($job['deadline_date'])); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?php echo $job['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $job['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label">Job Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="description" rows="5" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label">Requirements <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="requirements" rows="5" required><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Job
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
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
