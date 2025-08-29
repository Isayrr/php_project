<?php
session_start();
require_once '../config/database.php';
require_once '../includes/common_data.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$company = null;
$jobs = [];
$error = null;
$success = null;

// Check if company ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: companies.php");
    exit();
}

$company_id = (int)$_GET['id'];

// Handle company update
if (isset($_POST['update_company'])) {
    try {
        // Get form data
        $company_name = trim($_POST['company_name']);
        $industry = trim($_POST['industry']);
        $company_size = trim($_POST['company_size']);
        $company_website = trim($_POST['company_website']);
        $company_description = trim($_POST['company_description']);
        $location = trim($_POST['location']);
        $phone = trim($_POST['phone']);
        
        // Validate inputs
        if (empty($company_name)) {
            $error = "Company name is required.";
        } else {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update company information
            $stmt = $conn->prepare("UPDATE companies SET 
                company_name = ?, 
                industry = ?, 
                company_size = ?, 
                company_website = ?, 
                company_description = ?,
                location = ?,
                phone = ?
                WHERE company_id = ?");
                
            $stmt->execute([
                $company_name, 
                $industry, 
                $company_size, 
                $company_website, 
                $company_description,
                $location,
                $phone,
                $company_id
            ]);
            
            // Commit transaction
            $conn->commit();
            $success = "Company information updated successfully.";
            
            // Handle logo upload if file is selected
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/company_logos/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'company_' . $company_id . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                // Check file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array(strtolower($file_ext), $allowed_types)) {
                    $error = "Only JPG, JPEG, PNG and GIF files are allowed.";
                } else if ($_FILES['company_logo']['size'] > 2000000) { // 2MB limit
                    $error = "File size must be less than 2MB.";
                } else if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path)) {
                    // Update company logo in database
                    $stmt = $conn->prepare("UPDATE companies SET company_logo = ? WHERE company_id = ?");
                    $stmt->execute([$new_filename, $company_id]);
                    $success .= " Logo updated successfully.";
                } else {
                    $error = "Error uploading logo.";
                }
            }
        }
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

// Get company information
try {
    $stmt = $conn->prepare("
        SELECT c.*, u.email, u.username, u.status as user_status, u.created_at as user_created_at
        FROM companies c 
        JOIN users u ON c.employer_id = u.user_id
        WHERE c.company_id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        header("Location: companies.php");
        exit();
    }
    
    // Get company jobs
    $stmt = $conn->prepare("
        SELECT j.*, 
        (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as application_count,
        0 as view_count
        FROM jobs j
        WHERE j.company_id = ?
        ORDER BY j.job_id DESC
        LIMIT 10");
    $stmt->execute([$company_id]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get list of industries
    $industries = getIndustries($conn);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($company['company_name']) ? htmlspecialchars($company['company_name']) : 'Company Details'; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/header.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .company-logo {
            width: 150px;
            height: 150px;
            object-fit: contain;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        
        .job-card {
            transition: all 0.3s ease;
        }
        
        .job-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .nav-tabs .nav-link {
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid #3498db;
        }
        
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
            <h3>Admin Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <!-- Logo centered below admin panel heading -->
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
                <a href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <span>Jobs</span>
                </a>
            </li>
            <li>
                <a href="categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Job Categories</span>
                </a>
            </li>
            <li>
                <a href="companies.php" class="active">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
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
                <a href="placements.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>Graduate Placements</span>
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

    <!-- Main Content -->
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo isset($company['company_name']) ? htmlspecialchars($company['company_name']) : 'Company Details'; ?></h2>
                <a href="companies.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Companies
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($company): ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <?php if (isset($company['company_logo']) && !empty($company['company_logo'])): ?>
                                <img src="../uploads/company_logos/<?php echo htmlspecialchars($company['company_logo']); ?>" 
                                     alt="<?php echo htmlspecialchars($company['company_name']); ?>" 
                                     class="company-logo mb-3">
                            <?php else: ?>
                                <div class="company-logo mb-3 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-building fa-4x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <h4><?php echo htmlspecialchars($company['company_name']); ?></h4>
                            <p class="text-muted">
                                <?php echo isset($company['industry']) && !empty($company['industry']) ? htmlspecialchars($company['industry']) : 'Industry not specified'; ?>
                            </p>
                            
                            <div class="d-grid gap-2 mt-3">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editCompanyModal">
                                    <i class="fas fa-edit"></i> Edit Company
                                </button>
                                <a href="<?php echo isset($company['company_website']) && !empty($company['company_website']) ? htmlspecialchars($company['company_website']) : '#'; ?>" 
                                   target="_blank" class="btn btn-outline-info <?php echo !isset($company['company_website']) || empty($company['company_website']) ? 'disabled' : ''; ?>">
                                    <i class="fas fa-globe"></i> Visit Website
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Company Information</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-users me-2"></i> Company Size</span>
                                    <span class="badge bg-info rounded-pill">
                                        <?php echo isset($company['company_size']) && !empty($company['company_size']) ? htmlspecialchars($company['company_size']) : 'Not specified'; ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-map-marker-alt me-2"></i> Location</span>
                                    <span>
                                        <?php echo isset($company['location']) && !empty($company['location']) ? htmlspecialchars($company['location']) : 'Not specified'; ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-envelope me-2"></i> Email</span>
                                    <span>
                                        <a href="mailto:<?php echo htmlspecialchars($company['email']); ?>">
                                            <?php echo htmlspecialchars($company['email']); ?>
                                        </a>
                                    </span>
                                </li>
                                <?php if (isset($company['phone']) && !empty($company['phone'])): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-phone me-2"></i> Phone</span>
                                    <span><?php echo htmlspecialchars($company['phone']); ?></span>
                                </li>
                                <?php endif; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-user me-2"></i> Account Status</span>
                                    <span class="badge bg-<?php echo $company['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($company['user_status']); ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-calendar me-2"></i> Joined Date</span>
                                    <span><?php echo date('M d, Y', strtotime($company['user_created_at'])); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <ul class="nav nav-tabs mb-4" id="companyTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" 
                                    type="button" role="tab" aria-controls="description" aria-selected="true">
                                <i class="fas fa-info-circle me-2"></i> Description
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="jobs-tab" data-bs-toggle="tab" data-bs-target="#jobs" 
                                    type="button" role="tab" aria-controls="jobs" aria-selected="false">
                                <i class="fas fa-briefcase me-2"></i> Jobs (<?php echo count($jobs); ?>)
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="companyTabContent">
                        <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
                            <div class="card">
                                <div class="card-body">
                                    <?php if (isset($company['company_description']) && !empty($company['company_description'])): ?>
                                        <div class="company-description">
                                            <?php echo nl2br(htmlspecialchars($company['company_description'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No company description available.</p>
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCompanyModal">
                                                Add Description
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="jobs" role="tabpanel" aria-labelledby="jobs-tab">
                            <?php if (empty($jobs)): ?>
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                        <h5>No Jobs Posted Yet</h5>
                                        <p class="text-muted">This company has not posted any jobs yet.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($jobs as $job): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card job-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo isset($job['title']) ? htmlspecialchars($job['title']) : 'Untitled Job'; ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted">
                                                    <?php echo isset($job['job_type']) ? htmlspecialchars($job['job_type']) : 'Unknown'; ?> | 
                                                    <?php echo isset($job['location']) ? htmlspecialchars($job['location']) : 'No location'; ?>
                                                </h6>
                                                <p class="card-text">
                                                    <?php 
                                                    if (isset($job['description']) && !empty($job['description'])) {
                                                        echo substr(htmlspecialchars($job['description']), 0, 100) . '...'; 
                                                    } else {
                                                        echo 'No description available.';
                                                    }
                                                    ?>
                                                </p>
                                                <div class="d-flex justify-content-between mt-3">
                                                    <div>
                                                        <span class="badge bg-secondary me-2">
                                                            <i class="fas fa-eye"></i> <?php echo $job['view_count']; ?> views
                                                        </span>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-file-alt"></i> <?php echo $job['application_count']; ?> apps
                                                        </span>
                                                    </div>
                                                    <a href="view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="card-footer text-muted">
                                                <small>Job ID: <?php echo $job['job_id']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($jobs) >= 10): ?>
                                <div class="text-center mt-3">
                                    <a href="jobs.php?company=<?php echo $company_id; ?>" class="btn btn-outline-primary">
                                        View All Jobs <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-danger">Company not found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Company Modal -->
    <div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCompanyModalLabel">Edit Company Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="company_name" class="form-label">Company Name *</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                      value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="industry" class="form-label">Industry</label>
                                <?php echo renderIndustryDropdown('industry', 'industry', $company['industry'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="company_size" class="form-label">Company Size</label>
                                <select class="form-select" id="company_size" name="company_size">
                                    <option value="">Select Size</option>
                                    <option value="1-10" <?php echo isset($company['company_size']) && $company['company_size'] === '1-10' ? 'selected' : ''; ?>>1-10 employees</option>
                                    <option value="11-50" <?php echo isset($company['company_size']) && $company['company_size'] === '11-50' ? 'selected' : ''; ?>>11-50 employees</option>
                                    <option value="51-200" <?php echo isset($company['company_size']) && $company['company_size'] === '51-200' ? 'selected' : ''; ?>>51-200 employees</option>
                                    <option value="201-500" <?php echo isset($company['company_size']) && $company['company_size'] === '201-500' ? 'selected' : ''; ?>>201-500 employees</option>
                                    <option value="501-1000" <?php echo isset($company['company_size']) && $company['company_size'] === '501-1000' ? 'selected' : ''; ?>>501-1000 employees</option>
                                    <option value="1001+" <?php echo isset($company['company_size']) && $company['company_size'] === '1001+' ? 'selected' : ''; ?>>1001+ employees</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="company_website" class="form-label">Website</label>
                                <input type="url" class="form-control" id="company_website" name="company_website"
                                       value="<?php echo isset($company['company_website']) ? htmlspecialchars($company['company_website']) : ''; ?>"
                                       placeholder="https://www.example.com">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location"
                                       value="<?php echo isset($company['location']) ? htmlspecialchars($company['location']) : ''; ?>"
                                       placeholder="City, State, Country">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="<?php echo isset($company['phone']) ? htmlspecialchars($company['phone']) : ''; ?>"
                                       placeholder="+1 (123) 456-7890">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="company_description" class="form-label">Company Description</label>
                            <textarea class="form-control" id="company_description" name="company_description" rows="5"
                                      placeholder="Enter company description..."><?php echo isset($company['company_description']) ? htmlspecialchars($company['company_description']) : ''; ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="company_logo" class="form-label">Company Logo</label>
                            <input type="file" class="form-control" id="company_logo" name="company_logo" accept="image/*">
                            <div class="form-text">Recommended size: 400x400 pixels. Max file size: 2MB.</div>
                            <?php if (isset($company['company_logo']) && !empty($company['company_logo'])): ?>
                                <div class="mt-2">
                                    <img src="../uploads/company_logos/<?php echo htmlspecialchars($company['company_logo']); ?>" 
                                         alt="Current Logo" class="img-thumbnail" style="max-height: 100px;">
                                    <span class="ms-2">Current logo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_company" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show success message in modal if present
            <?php if ($success && isset($_POST['update_company'])): ?>
            setTimeout(function() {
                const modal = bootstrap.Modal.getInstance(document.getElementById('editCompanyModal'));
                if (modal) {
                    modal.hide();
                }
                
                // Highlight the success message
                const successAlert = document.querySelector('.alert-success');
                if (successAlert) {
                    successAlert.style.animation = 'highlight 2s';
                }
            }, 1000);
            <?php endif; ?>
            
            // Open modal on load if URL has edit parameter
            <?php if (isset($_GET['edit']) && $_GET['edit'] === 'true'): ?>
            const editModal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
            editModal.show();
            <?php endif; ?>
            
            // Preview image before upload
            const logoInput = document.getElementById('company_logo');
            logoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                
                if (file) {
                    // Check file size
                    if (file.size > 2000000) { // 2MB
                        alert('File size must be less than 2MB');
                        logoInput.value = '';
                        return;
                    }
                    
                    // Check file type
                    const fileType = file.type;
                    if (!fileType.match('image.*')) {
                        alert('Please select an image file');
                        logoInput.value = '';
                        return;
                    }
                    
                    // Preview image
                    const reader = new FileReader();
                    
                    reader.onload = function(event) {
                        // Remove any existing preview
                        const existingPreview = logoInput.parentElement.querySelector('.mt-2');
                        if (existingPreview) {
                            existingPreview.remove();
                        }
                        
                        // Create new preview
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'mt-2';
                        previewDiv.innerHTML = `
                            <img src="${event.target.result}" alt="Logo Preview" class="img-thumbnail" style="max-height: 100px;">
                            <span class="ms-2">Preview</span>
                        `;
                        
                        logoInput.parentElement.appendChild(previewDiv);
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
        });
        
        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes highlight {
                0% { background-color: #d4edda; }
                50% { background-color: #c3e6cb; }
                100% { background-color: #d4edda; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html> 