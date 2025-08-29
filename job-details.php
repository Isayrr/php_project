<?php
session_start();
require_once 'config/database.php';

$error = null;
$job = null;
$related_jobs = [];
$job_skills = [];

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: browse-jobs.php");
    exit();
}

$job_id = $_GET['id'];

try {
    // Get job details with company information
    $stmt = $conn->prepare("
        SELECT j.*, c.company_name, c.industry, c.company_website, c.company_description, c.company_logo,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.job_id = ? AND j.status = 'active' AND j.approval_status = 'approved'
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("Job not found or no longer active");
    }

    // Get skills required for this job
    $stmt = $conn->prepare("
        SELECT s.skill_name
        FROM job_skills js
        JOIN skills s ON js.skill_id = s.skill_id
        WHERE js.job_id = ?
    ");
    $stmt->execute([$job_id]);
    $job_skills = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get related jobs (same company or same job type)
    $stmt = $conn->prepare("
        SELECT j.*, c.company_name
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.job_id != ? 
        AND j.status = 'active' 
        AND (j.company_id = ? OR j.job_type = ?)
        ORDER BY j.posted_date DESC
        LIMIT 3
    ");
    $stmt->execute([$job_id, $job['company_id'], $job['job_type']]);
    $related_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $error = $e->getMessage();
}

// Handle application submission if user is logged in as jobseeker
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] === 'jobseeker') {
    try {
        // Check if already applied
        $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND jobseeker_id = ?");
        $stmt->execute([$job_id, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("You have already applied for this job.");
        }

        // Insert application
        $stmt = $conn->prepare("INSERT INTO applications (job_id, jobseeker_id, cover_letter, application_date, status) 
                               VALUES (?, ?, ?, NOW(), 'pending')");
        $stmt->execute([
            $job_id,
            $_SESSION['user_id'],
            $_POST['cover_letter'] ?? null
        ]);

        $success = "Application submitted successfully!";

        // Fire notifications for admin and employer
        try {
            require_once __DIR__ . '/admin/includes/admin_notifications.php';
            require_once __DIR__ . '/employer/includes/employer_notifications.php';
            
            // Get applicant name (best-effort)
            $stmt = $conn->prepare("SELECT up.first_name, up.last_name FROM user_profiles up WHERE up.user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            $applicant_name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
            
            $application_id = $conn->lastInsertId();
            notifyAdminNewApplication($conn, $application_id, $job['title'], $applicant_name);
            notifyEmployerNewApplication($conn, $job_id);
        } catch (Exception $e) {
            error_log('Notification error (job-details): ' . $e->getMessage());
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $job ? htmlspecialchars($job['title']) . ' at ' . htmlspecialchars($job['company_name']) : 'Job Details'; ?> - Job Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            position: relative;
            background-color: #fff !important;
            color: #333;
        }
        /* Clean breadcrumb */
        .breadcrumb-nav {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        .breadcrumb-nav a { color: #6c757d; text-decoration: none; }
        .breadcrumb-nav a:hover { color: #495057; text-decoration: underline; }
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid #f8f9fa;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
            filter: none !important;
        }
        .job-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: none;
            position: relative;
        }
        .job-details {
            line-height: 1.8;
        }
        .related-job-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
        }
        .related-job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .deadline {
            color: #dc3545;
            font-weight: bold;
        }
        .section-title {
            position: relative;
            display: inline-block;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #333;
            text-shadow: none;
            font-size: 2rem;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 70px;
            height: 4px;
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            border-radius: 2px;
        }
        .text-center .section-title::after {
            left: 50%;
            transform: translateX(-50%);
        }
        .main-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }
        .btn {
            font-weight: 600;
            border-radius: 10px;
            padding: 0.6rem 1.5rem;
            transition: all 0.3s ease;
            text-transform: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            color: white;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a8ffe 20%, #9658fe 80%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6);
        }
        .btn-light {
            background: white;
            color: #333;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }
        .btn-light:hover {
            background: #f8f9fa;
            color: #000;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        .btn-outline-light {
            border: 2px solid white;
            background: transparent;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }
        .btn-outline-light:hover {
            background: white;
            color: #000;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        .navbar {
            padding: 1.2rem 0;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: blur(8px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1050;
        }
        .navbar.scrolled {
            background: rgba(0, 0, 0, 0.85) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.95);
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            transition: all 0.3s ease;
            margin: 0 0.1rem;
        }
        .navbar-dark .navbar-nav .nav-link:hover {
            color: #fff;
            transform: translateY(-2px);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            padding-right: 2rem;
        }
        
        .logo-circle {
            width: 40px;
            height: 40px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
        }
        
        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: transparent !important;
            border-radius: 50%;
            padding: 0 !important;
            box-sizing: border-box !important;
            margin: auto !important;
            transform: none !important;
            clip-path: circle(50% at 50% 50%) !important;
        }
        
        .talavera-circle {
            width: 40px;
            height: 40px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
            position: relative;
            padding: 0;
        }
        
        .talavera-logo {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            background: transparent !important;
            border-radius: 50%;
            padding: 0 !important;
            box-sizing: border-box !important;
            margin: auto !important;
            transform: none !important;
            clip-path: circle(50% at 50% 50%) !important;
        }
        
        /* Minimal Auth Buttons (match index/browse) */
        .codepen-button {
            display: inline-block;
            cursor: pointer;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.6rem 1.25rem;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.85);
            transition: background-color .2s ease, border-color .2s ease, transform .2s ease;
        }
        .codepen-button:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: #ffffff;
            color: #ffffff;
            transform: translateY(-1px);
        }
        .codepen-button::before { display: none !important; content: none; }
        .codepen-button span { display: inline; padding: 0; background: transparent; border-radius: 0; color: inherit; }

        /* Header pill for job type */
        .job-type-pill {
            position: absolute;
            top: -12px;
            right: -12px;
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: .02em;
            border: 2px solid #fff;
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);
        }
        
        .social-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        .social-icon:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-3px);
        }
        
        .back-to-top {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .back-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .back-to-top:hover {
            background: linear-gradient(135deg, #9658fe 0%, #3a8ffe 100%);
            transform: translateY(-5px);
            color: white;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: none;
            padding: 1.25rem;
        }
        .card-title {
            margin-bottom: 0;
            font-weight: 600;
            color: #333;
        }
        /* Sticky sidebar for overview/apply */
        .sticky-sidebar { position: sticky; top: 100px; }
        .kv-list { list-style: none; padding-left: 0; margin: 0; }
        .kv-list li { display: flex; align-items: center; gap: .5rem; padding: .35rem 0; color: #495057; }
        .subtle-chip { background: #ffffff; border: 1px solid #e9ecef; border-radius: 999px; padding: .35rem .6rem; font-size: .8rem; }
        
        @media (max-width: 992px) {
            .navbar-collapse {
                background: rgba(0, 0, 0, 0.9);
                border-radius: 0.5rem;
                padding: 1rem;
                margin-top: 1rem;
            }
            .navbar-nav {
                padding: 0.5rem 0;
            }
            .navbar-nav .nav-item {
                margin: 0.25rem 0;
            }
            .d-flex.align-items-center {
                margin-top: 0.5rem;
                padding-top: 0.5rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
                                    <div class="logo-circle me-2">
                        <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                    </div>
                <div class="talavera-circle me-2">
                    <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo">
                </div>
                Job Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="browse-jobs.php">Browse Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about-us.php">About Us</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-light me-2">Dashboard</a>
                        <a href="auth/logout.php" class="btn btn-outline-light">Logout</a>
                    <?php else: ?>
                        <a href="register.php" class="codepen-button me-3">
                            <span>Register</span>
                        </a>
                        <a href="#login" class="codepen-button" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <span>Login</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-5" style="padding-top: 100px;">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <p class="mt-3 mb-0">
                    <a href="browse-jobs.php" class="btn btn-outline-danger">Return to Job Listings</a>
                </p>
            </div>
        <?php elseif ($job): ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Job Header -->
            <div class="job-header shadow-sm">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><?php echo htmlspecialchars($job['title']); ?></h1>
                        <p class="lead mb-1">
                            <i class="fas fa-building text-secondary"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge bg-secondary">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?>
                            </span>
                            <span class="badge bg-info">
                                <i class="fas fa-users"></i> <?php echo $job['application_count']; ?> applicants
                            </span>
                            <?php if (!empty($job['salary_range'])): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($job['salary_range']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <span class="badge bg-primary job-type-pill"><?php echo htmlspecialchars($job['job_type']); ?></span>
                        <div class="company-logo d-inline-flex align-items-center justify-content-center bg-light rounded mx-auto">
                            <?php if (!empty($job['company_logo']) && file_exists('uploads/company_logos/' . $job['company_logo'])): ?>
                                <img src="uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> Logo" class="img-fluid" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">
                            <?php else: ?>
                                <i class="fas fa-building fa-3x text-secondary"></i>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <p class="mb-1">
                                <i class="fas fa-calendar text-muted"></i> Posted <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                            </p>
                            <?php if (!empty($job['deadline_date'])): ?>
                            <p class="deadline mb-0">
                                <i class="fas fa-clock"></i> Apply before <?php echo date('M d, Y', strtotime($job['deadline_date'])); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-4 d-flex justify-content-between">
                    <a href="browse-jobs.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Jobs
                    </a>
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'jobseeker'): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyModal">
                            <i class="fas fa-paper-plane"></i> Apply Now
                        </button>
                    <?php elseif(!isset($_SESSION['user_id'])): ?>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login to Apply
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4">
                <!-- Job Details -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Job Description</h5>
                        </div>
                        <div class="card-body job-details">
                            <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                        </div>
                    </div>

                    <?php if (!empty($job['requirements'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Requirements</h5>
                        </div>
                        <div class="card-body job-details">
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($job_skills)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Skills Required</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($job_skills as $skill): ?>
                                    <span class="badge bg-light text-dark border p-2 rounded-pill">
                                        <i class="fas fa-check-circle text-success me-1"></i><?php echo htmlspecialchars($skill); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Overview / Quick Info -->
                    <div class="card mb-4 sticky-sidebar">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Overview</h5>
                        </div>
                        <div class="card-body">
                            <ul class="kv-list mb-3">
                                <li><i class="fas fa-building text-muted"></i><span><?php echo htmlspecialchars($job['company_name']); ?></span></li>
                                <li><i class="fas fa-map-marker-alt text-muted"></i><span><?php echo htmlspecialchars($job['location']); ?></span></li>
                                <?php if (!empty($job['salary_range'])): ?>
                                <li><i class="fas fa-money-bill-wave text-success"></i><span><?php echo htmlspecialchars($job['salary_range']); ?></span></li>
                                <?php endif; ?>
                                <li><i class="fas fa-users text-muted"></i><span><?php echo (int)$job['application_count']; ?> applicants</span></li>
                                <li><i class="fas fa-calendar-day text-muted"></i><span>Posted <?php echo date('M d, Y', strtotime($job['posted_date'])); ?></span></li>
                                <?php if (!empty($job['deadline_date'])): ?>
                                <li><i class="fas fa-clock text-danger"></i><span>Apply before <?php echo date('M d, Y', strtotime($job['deadline_date'])); ?></span></li>
                                <?php endif; ?>
                            </ul>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="subtle-chip"><i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($job['job_type']); ?></span>
                                <?php if (!empty($job['industry'])): ?>
                                <span class="subtle-chip"><i class="fas fa-industry me-1"></i><?php echo htmlspecialchars($job['industry']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['company_website'])): ?>
                                <a class="subtle-chip text-decoration-none" href="<?php echo htmlspecialchars($job['company_website']); ?>" target="_blank"><i class="fas fa-globe me-1"></i>Website</a>
                                <?php endif; ?>
                            </div>
                            <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'jobseeker'): ?>
                                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#applyModal">
                                    <i class="fas fa-paper-plane me-1"></i>Apply Now
                                </button>
                            <?php elseif(!isset($_SESSION['user_id'])): ?>
                                <a href="index.php" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt me-1"></i>Login to Apply
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Related Jobs -->
                    <?php if (!empty($related_jobs)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Similar Jobs</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($related_jobs as $related_job): ?>
                                <a href="job-details.php?id=<?php echo $related_job['job_id']; ?>" class="text-decoration-none">
                                    <div class="card related-job-card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($related_job['title']); ?></h6>
                                            <p class="text-muted mb-2"><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($related_job['company_name']); ?></p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="subtle-chip"><i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($related_job['job_type']); ?></span>
                                                <span class="subtle-chip"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($related_job['location']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Application Modal -->
            <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'jobseeker'): ?>
            <div class="modal fade" id="applyModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Apply for <?php echo htmlspecialchars($job['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="cover_letter" class="form-label">Cover Letter (Optional)</label>
                                    <textarea class="form-control" id="cover_letter" name="cover_letter" rows="6" 
                                              placeholder="Tell the employer why you're the perfect fit for this role..."></textarea>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Your profile information will be shared with the employer.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Application</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="logo-circle me-3">
                            <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                        </div>
                        <h5 class="mb-0">Job Portal</h5>
                    </div>
                    <p class="text-muted">Connecting talented professionals with their dream careers. Your journey to success starts here.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="social-icon text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon text-white"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-icon text-white"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-lg-2">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-muted text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="browse-jobs.php" class="text-muted text-decoration-none">Browse Jobs</a></li>
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Categories</a></li>
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Companies</a></li>
                    </ul>
                </div>
                <div class="col-lg-2">
                    <h5 class="mb-4">Support</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Contact</a></li>
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5 class="mb-4">Contact Us</h5>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> 123 Job Street, City, Country</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> +1 234 567 890</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> contact@jobportal.com</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> Job Portal. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                                            <div class="d-flex align-items-center">
                            <div class="logo-circle me-3" style="width: 35px; height: 35px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                        <h5 class="modal-title">Login to Your Account</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                                                    <div class="logo-circle mx-auto mb-3" style="width: 70px; height: 70px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                        <h4 class="fw-bold text-primary">Welcome Back</h4>
                        <p class="text-muted small">Please enter your credentials to continue</p>
                    </div>
                    <?php
                    if (isset($_GET['error'])) {
                        $error_message = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : 'Invalid username or password';
                        $error_class = 'danger';
                        $icon = 'exclamation-circle';
                        
                        if ($_GET['error'] == '2') {
                            $error_class = 'warning';
                            $icon = 'clock';
                        }
                        
                        echo '<div class="alert alert-' . $error_class . '">
                            <i class="fas fa-' . $icon . '"></i> ' . htmlspecialchars($error_message) . '
                        </div>';
                        unset($_SESSION['login_error']);
                    }
                    ?>
                    <form action="auth/login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <a href="#" class="back-to-top">
        <i class="fas fa-chevron-up fa-lg"></i>
    </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            once: true
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Back to top button
        const backToTopButton = document.querySelector('.back-to-top');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html> 