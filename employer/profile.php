<?php
session_start();
require_once '../config/database.php';
require_once '../includes/common_data.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;
$company = null;

try {
    // Get company profile
    $stmt = $conn->prepare("SELECT c.*, u.email FROM companies c 
                           JOIN users u ON c.employer_id = u.user_id 
                           WHERE c.employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get industries list
    $industries = getIndustries($conn);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Validate input
            $company_name = trim($_POST['company_name']);
            $industry = trim($_POST['industry']);
            $company_size = trim($_POST['company_size']);
            $company_description = trim($_POST['company_description']);
            $company_website = trim($_POST['company_website']);

            // Validate required fields
            $required_fields = [
                'company_name' => 'Company name',
                'industry' => 'Industry',
                'company_size' => 'Company size',
                'company_description' => 'Company description'
            ];

            foreach ($required_fields as $field => $label) {
                if (empty($_POST[$field])) {
                    throw new Exception($label . " is required.");
                }
            }

            // Handle logo upload
            $logo_path = $company['company_logo'] ?? null;
            
            // Check if logo should be removed
            if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
                if ($logo_path) {
                    $upload_dir = '../uploads/company_logos/';
                    $full_path = $upload_dir . $logo_path;
                    if (file_exists($full_path)) {
                        unlink($full_path);
                    }
                }
                $logo_path = null;
            }
            // Handle new logo upload
            elseif (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($_FILES['company_logo']['type'], $allowed_types)) {
                    throw new Exception("Invalid file type. Only JPG, PNG and GIF are allowed.");
                }

                if ($_FILES['company_logo']['size'] > $max_size) {
                    throw new Exception("File size too large. Maximum size is 5MB.");
                }

                $upload_dir = '../uploads/company_logos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Delete old logo if exists
                if ($logo_path && file_exists($upload_dir . $logo_path)) {
                    unlink($upload_dir . $logo_path);
                }

                $file_extension = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'company_logo_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path)) {
                    $logo_path = $new_filename;
                } else {
                    throw new Exception("Failed to upload logo. Please try again.");
                }
            }

            // If company doesn't exist yet, create it
            if (!$company) {
                $stmt = $conn->prepare("INSERT INTO companies (employer_id, company_name, industry, company_size, company_description, company_website, company_logo) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $company_name,
                    $industry,
                    $company_size,
                    $company_description,
                    $company_website,
                    $logo_path
                ]);
            } else {
                // Update existing company profile
                $stmt = $conn->prepare("UPDATE companies SET 
                                   company_name = ?, 
                                   industry = ?, 
                                   company_size = ?, 
                                   company_description = ?, 
                                   company_website = ?,
                                   company_logo = ?
                                   WHERE employer_id = ?");
                
                $stmt->execute([
                    $company_name,
                    $industry,
                    $company_size,
                    $company_description,
                    $company_website,
                    $logo_path,
                    $_SESSION['user_id']
                ]);
            }

            $success = "Company profile updated successfully.";
            
            // Refresh company data
            $stmt = $conn->prepare("SELECT c.*, u.email FROM companies c 
                                   JOIN users u ON c.employer_id = u.user_id 
                                   WHERE c.employer_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            // If this was an AJAX request for logo removal, send JSON response
            if (isset($_POST['remove_logo']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Logo removed successfully']);
                exit;
            }

        } catch(Exception $e) {
            $error = $e->getMessage();
            // If this was an AJAX request, send JSON response
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
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
    <title>Company Profile - Employer Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Enhanced modern design for profile page */
        .main-content {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 0;
            margin-bottom: 2.5rem;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
            overflow: hidden;
            position: relative;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .profile-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 2.5rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
        }

        .profile-card-body {
            padding: 2.5rem;
        }

        .profile-logo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .profile-logo:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .profile-logo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.2rem;
            color: rgba(255, 255, 255, 0.8);
            border: 4px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .profile-logo-placeholder:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .profile-card-header h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .profile-card-header .text-white-50 {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .logo-actions .btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .logo-actions .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .profile-form {
            margin-top: 0;
        }

        .profile-form label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .profile-form .form-control, 
        .profile-form .form-select {
            border-radius: 12px;
            font-size: 1rem;
            background: rgba(248, 250, 252, 0.8);
            border: 2px solid #e9ecef;
            color: #2c3e50;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .profile-form .form-control:focus, 
        .profile-form .form-select:focus {
            background: #fff;
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: translateY(-2px);
        }

        .profile-form .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 2px solid #667eea;
            border-radius: 12px 0 0 12px;
            color: white;
            font-weight: 600;
        }

        .profile-form .input-group .form-control,
        .profile-form .input-group .form-select {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .profile-form .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        .profile-form textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            padding: 0.875rem 2.5rem;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }

        .btn-outline-primary {
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-outline-primary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }

        .btn-outline-danger {
            border: 2px solid rgba(220, 53, 69, 0.5);
            color: rgba(220, 53, 69, 0.8);
            background: rgba(220, 53, 69, 0.1);
        }

        .btn-outline-danger:hover {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.7);
            color: #dc3545;
        }

        .form-section {
            background: rgba(248, 250, 252, 0.5);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .form-section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: rgba(102, 126, 234, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #5a6fd8;
        }

        .file-selected {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-top: 1rem;
            color: #28a745;
        }

        /* Modal styling */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            border: none;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Sidebar styling updates */
        .sidebar, .sidebar-menu a {
            background: #1a252f !important;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #2c3e50 !important;
            color: #3498db !important;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .profile-card-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 2rem 1.5rem;
                gap: 1.5rem;
            }

            .profile-card-body {
                padding: 1.5rem;
            }

            .form-section {
                padding: 1.5rem;
            }

            .profile-logo, .profile-logo-placeholder {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .profile-card-header h3 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 576px) {
            .profile-card-header {
                padding: 1.5rem 1rem;
            }

            .profile-card-body {
                padding: 1rem;
            }

            .logo-actions {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }

            .logo-actions .btn {
                font-size: 0.875rem;
                padding: 0.5rem;
            }
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
                <a href="profile.php" class="active">
                    <i class="fas fa-building"></i>
                    <span>Company Profile</span>
                </a>
            </li>
            <li>
                <a href="jobs.php">
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
            <!-- Enhanced Page Header -->
            <div class="page-header">
                <h1 class="page-title">Company Profile</h1>
                <p class="page-subtitle">Manage your company information and branding</p>
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

            <div class="profile-card">
                <div class="profile-card-header">
                    <?php if (isset($company['company_logo']) && !empty($company['company_logo'])): ?>
                        <div class="d-flex flex-column align-items-center me-3">
                            <img src="../uploads/company_logos/<?php echo htmlspecialchars($company['company_logo']); ?>" alt="Company Logo" class="profile-logo mb-3" id="currentLogo">
                            <div class="logo-actions d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('company_logo').click();">
                                    <i class="fas fa-edit me-1"></i> Change
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeLogoModal">
                                    <i class="fas fa-trash-alt me-1"></i> Remove
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="profile-logo-placeholder me-3 d-flex flex-column align-items-center">
                            <i class="fas fa-building mb-3"></i>
                            <div class="logo-actions">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('company_logo').click();">
                                    <i class="fas fa-upload me-1"></i> Upload Logo
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <h3><?php echo htmlspecialchars($company['company_name'] ?? 'Your Company Name'); ?></h3>
                        <div class="text-white-50">
                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($company['email'] ?? ''); ?>
                        </div>
                        <?php if (!empty($company['industry'])): ?>
                            <div class="text-white-50 mt-1">
                                <i class="fas fa-industry me-2"></i><?php echo htmlspecialchars($company['industry']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-card-body">
                    <form method="POST" enctype="multipart/form-data" class="profile-form">
                        <!-- Logo Upload Section -->
                        <div class="form-section">
                            <h4 class="form-section-title">
                                <i class="fas fa-image me-2"></i>Company Logo
                            </h4>
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="upload-area" onclick="document.getElementById('company_logo').click();">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                                        <p class="mb-1"><strong>Click to upload company logo</strong></p>
                                        <p class="text-muted small mb-0">Max file size: 5MB. Allowed formats: JPG, PNG, GIF</p>
                                    </div>
                                    <input type="file" class="form-control d-none" id="company_logo" name="company_logo" accept="image/jpeg,image/png,image/gif">
                                    <div id="file-selected" class="file-selected" style="display: none;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Information Section -->
                        <div class="form-section">
                            <h4 class="form-section-title">
                                <i class="fas fa-building me-2"></i>Company Information
                            </h4>
                            <div class="row g-4">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label">Company Name *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="industry" class="form-label">Industry *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-industry"></i></span>
                                <?php echo renderIndustryDropdown('industry', 'industry', $company['industry'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="company_size" class="form-label">Company Size *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-users"></i></span>
                                <select class="form-select" id="company_size" name="company_size" required>
                                    <option value="">Select size</option>
                                    <option value="1-10" <?php echo ($company['company_size'] ?? '') === '1-10' ? 'selected' : ''; ?>>1-10 employees</option>
                                    <option value="11-50" <?php echo ($company['company_size'] ?? '') === '11-50' ? 'selected' : ''; ?>>11-50 employees</option>
                                    <option value="51-200" <?php echo ($company['company_size'] ?? '') === '51-200' ? 'selected' : ''; ?>>51-200 employees</option>
                                    <option value="201-500" <?php echo ($company['company_size'] ?? '') === '201-500' ? 'selected' : ''; ?>>201-500 employees</option>
                                    <option value="501-1000" <?php echo ($company['company_size'] ?? '') === '501-1000' ? 'selected' : ''; ?>>501-1000 employees</option>
                                    <option value="1000+" <?php echo ($company['company_size'] ?? '') === '1000+' ? 'selected' : ''; ?>>1000+ employees</option>
                                </select>
                            </div>
                        </div>
                                <div class="col-md-6">
                                    <label for="company_website" class="form-label">Company Website</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                        <input type="url" class="form-control" id="company_website" name="company_website" value="<?php echo htmlspecialchars($company['company_website'] ?? ''); ?>">
                                    </div>
                                    <div class="form-text">Include https:// for a valid link</div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Description Section -->
                        <div class="form-section">
                            <h4 class="form-section-title">
                                <i class="fas fa-file-text me-2"></i>Company Description
                            </h4>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label for="company_description" class="form-label">Tell us about your company *</label>
                                    <textarea class="form-control" id="company_description" name="company_description" rows="6" placeholder="Describe your company, mission, values, and what makes you unique..." required><?php echo htmlspecialchars($company['company_description'] ?? ''); ?></textarea>
                                    <div class="form-text">This description will be visible to job seekers when they view your job postings.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Section -->
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Company Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Logo Confirmation Modal -->
    <div class="modal fade" id="removeLogoModal" tabindex="-1" aria-labelledby="removeLogoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeLogoModalLabel">Remove Company Logo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove your company logo? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST">
                        <input type="hidden" name="remove_logo" value="1">
                        <button type="submit" class="btn btn-danger">Remove Logo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Preview logo before upload and show selected filename
        document.getElementById('company_logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileSelectedDiv = document.getElementById('file-selected');
            
            if (file) {
                // Display selected filename with enhanced styling
                if (fileSelectedDiv) {
                    fileSelectedDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i><strong>Selected:</strong> ' + file.name;
                    fileSelectedDiv.style.display = 'block';
                }
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Update the existing logo image if it exists
                    const currentLogo = document.getElementById('currentLogo');
                    if (currentLogo) {
                        currentLogo.src = e.target.result;
                    } else {
                        // If no logo exists, replace the placeholder with an image
                        const logoPlaceholder = document.querySelector('.profile-logo-placeholder');
                        if (logoPlaceholder) {
                            const imgContainer = document.createElement('div');
                            imgContainer.className = 'd-flex flex-column align-items-center me-3';
                            imgContainer.innerHTML = `
                                <img src="${e.target.result}" alt="Company Logo" class="profile-logo mb-3" id="currentLogo">
                                <div class="logo-actions d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('company_logo').click();">
                                        <i class="fas fa-edit me-1"></i> Change
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeLogoModal">
                                        <i class="fas fa-trash-alt me-1"></i> Remove
                                    </button>
                                </div>
                            `;
                            logoPlaceholder.parentNode.replaceChild(imgContainer, logoPlaceholder);
                        }
                    }
                }
                reader.readAsDataURL(file);
            } else {
                // Clear the selected file display if no file is selected
                if (fileSelectedDiv) {
                    fileSelectedDiv.style.display = 'none';
                    fileSelectedDiv.innerHTML = '';
                }
            }
        });
        
        // Handle logo removal via AJAX
        document.querySelector('#removeLogoModal .btn-danger').addEventListener('click', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('remove_logo', '1');
            
            // Add all form fields to maintain company data
            const form = document.querySelector('.profile-form');
            new FormData(form).forEach((value, key) => {
                if (key !== 'company_logo') {
                    formData.append(key, value);
                }
            });

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                                    if (data.success) {
                        // Replace logo with placeholder
                        const logoContainer = document.getElementById('currentLogo').closest('.d-flex');
                        const placeholder = document.createElement('div');
                        placeholder.className = 'profile-logo-placeholder me-3 d-flex flex-column align-items-center';
                        placeholder.innerHTML = `
                            <i class="fas fa-building mb-3"></i>
                            <div class="logo-actions">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('company_logo').click();">
                                    <i class="fas fa-upload me-1"></i> Upload Logo
                                </button>
                            </div>
                        `;
                        logoContainer.parentNode.replaceChild(placeholder, logoContainer);
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('removeLogoModal'));
                    modal.hide();
                    
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="fas fa-check-circle"></i> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.container-fluid').insertBefore(alert, document.querySelector('.profile-card'));
                } else {
                    // Show error message
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while removing the logo. Please try again.');
            });
        });
        
        // Add form validation
        document.querySelector('.profile-form').addEventListener('submit', function(e) {
            const companyName = document.getElementById('company_name').value.trim();
            const industry = document.getElementById('industry').value.trim();
            const companySize = document.getElementById('company_size').value.trim();
            const companyDescription = document.getElementById('company_description').value.trim();
            
            if (!companyName || !industry || !companySize || !companyDescription) {
                e.preventDefault();
                alert('Please fill out all required fields (marked with *)');
                return false;
            }
        });
    </script>
</body>
</html>
