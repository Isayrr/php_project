<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize variables
$categories = [];
$error = null;
$success = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $name = trim($_POST['category_name']);
                $description = trim($_POST['description']);
                
                if (empty($name)) {
                    throw new Exception("Category name is required.");
                }

                // Check if category already exists
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM job_categories WHERE LOWER(category_name) = LOWER(?)");
                $checkStmt->execute([$name]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("A category with this name already exists.");
                }
                
                $stmt = $conn->prepare("INSERT INTO job_categories (category_name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $success = "Category added successfully.";
                
            } elseif ($_POST['action'] === 'edit') {
                $category_id = $_POST['category_id'];
                $name = trim($_POST['category_name']);
                $description = trim($_POST['description']);
                
                if (empty($name)) {
                    throw new Exception("Category name is required.");
                }

                // Check if category name exists (excluding current category)
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM job_categories WHERE LOWER(category_name) = LOWER(?) AND category_id != ?");
                $checkStmt->execute([$name, $category_id]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("A category with this name already exists.");
                }
                
                $stmt = $conn->prepare("UPDATE job_categories SET category_name = ?, description = ? WHERE category_id = ?");
                $stmt->execute([$name, $description, $category_id]);
                $success = "Category updated successfully.";
                
            } elseif ($_POST['action'] === 'delete') {
                $category_id = $_POST['category_id'];
                
                // Check if category is in use
                $stmt = $conn->prepare("SELECT COUNT(*) FROM jobs WHERE category_id = ?");
                $stmt->execute([$category_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cannot delete category that is in use by job postings.");
                }
                
                $stmt = $conn->prepare("DELETE FROM job_categories WHERE category_id = ?");
                $stmt->execute([$category_id]);
                $success = "Category deleted successfully.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get all categories and their job counts
try {
    $stmt = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.category_id = c.category_id) as job_count FROM job_categories c ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <style>
        /* Fix dropdown layering issues */
        .dropdown-modern {
            position: relative;
            z-index: auto;
        }

        .dropdown-modern .dropdown-menu {
            position: absolute !important;
            z-index: 9999 !important;
            background: white !important;
            border: none !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
            animation: fadeInUp 0.3s ease !important;
            transform: none !important;
            will-change: auto !important;
            min-width: 150px !important;
        }

        .dropdown-modern .dropdown-toggle {
            position: relative;
            z-index: 1000 !important;
            background: var(--primary-gradient) !important;
            border: none !important;
            border-radius: 25px !important;
            padding: 8px 15px !important;
            color: white !important;
            font-weight: 600 !important;
            transition: var(--transition) !important;
        }

        .dropdown-modern .dropdown-toggle:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15) !important;
        }

        .dropdown-modern .dropdown-toggle:focus {
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
        }

        .dropdown-modern .dropdown-item {
            padding: 12px 20px !important;
            transition: var(--transition) !important;
            border-radius: 8px !important;
            margin: 4px 8px !important;
            color: #495057 !important;
            text-decoration: none !important;
            display: block !important;
            width: auto !important;
            background: transparent !important;
            border: none !important;
            text-align: left !important;
            font-size: 0.9rem !important;
        }

        .dropdown-modern .dropdown-item:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)) !important;
            transform: translateX(5px) !important;
            color: #495057 !important;
        }

        .dropdown-modern .dropdown-item i {
            width: 16px !important;
            margin-right: 8px !important;
        }

        .dropdown-modern .dropdown-item.text-danger:hover {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.1)) !important;
            color: #dc3545 !important;
        }

        /* Ensure table rows don't interfere with dropdowns */
        .table-modern tbody tr {
            position: relative;
            z-index: 1;
        }

        .table-modern tbody tr.dropdown-active {
            z-index: 1001 !important;
        }

        /* Override any conflicting styles */
        .table-responsive {
            overflow: visible !important;
            z-index: auto !important;
        }

        .modern-card {
            overflow: visible !important;
            z-index: auto !important;
        }

        /* Additional fixes for badge positioning */
        .badge {
            z-index: 1 !important;
            position: relative !important;
        }

        /* Ensure dropdown shows above everything */
        .dropdown-modern.show .dropdown-menu {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            z-index: 10000 !important;
        }

        /* Additional positioning fixes */
        .dropdown-modern .dropdown-menu {
            top: 100% !important;
            right: 0 !important;
            left: auto !important;
            margin-top: 5px !important;
        }

        /* Ensure container doesn't clip dropdowns */
        .container-fluid,
        .table-responsive,
        .modern-card {
            position: static !important;
        }

        /* Prevent text wrapping in action cells */
        .table-modern td:last-child {
            white-space: nowrap !important;
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
                <a href="categories.php" class="active">
                    <i class="fas fa-tags"></i>
                    <span>Job Categories</span>
                </a>
            </li>
            <li>
                <a href="companies.php">
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
                <a href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
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
    <div class="main-content page-transition">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <!-- Modern Page Header -->
            <div class="page-header" data-aos="fade-down">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center position-relative">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-tags me-3"></i>
                                Job Categories
                            </h1>
                            <p class="page-subtitle">Organize and manage job categories for better classification</p>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-center">
                                <h3 class="text-white mb-0"><?php echo count($categories); ?></h3>
                                <small class="opacity-75">Categories</small>
                            </div>
                            <button type="button" class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-plus me-2"></i> Add Category
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-modern" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-modern" data-aos="fade-up">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Categories Table -->
            <div class="modern-card" data-aos="fade-up" data-aos-delay="200">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Job Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="fas fa-tags"></i>
                                            <h6>No categories found</h6>
                                            <p class="text-muted">Create your first job category to get started.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                <tr class="fade-in-row">
                                    <td>
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($category['category_name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="text-muted"><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-modern bg-<?php echo $category['job_count'] > 0 ? 'success' : 'secondary'; ?>">
                                            <i class="fas fa-briefcase me-1"></i>
                                            <?php echo $category['job_count']; ?> Job<?php echo $category['job_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown dropdown-modern">
                                            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <button class="dropdown-item" onclick="editCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['category_name']); ?>', '<?php echo addslashes($category['description'] ?? ''); ?>')">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </li>
                                                <?php if ($category['job_count'] == 0): ?>
                                                <li>
                                                    <button class="dropdown-item text-danger" onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['category_name']); ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </li>
                                                <?php else: ?>
                                                <li>
                                                    <span class="dropdown-item text-muted">
                                                        <i class="fas fa-info-circle"></i> Cannot delete (in use)
                                                    </span>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content modern-card">
                <div class="modal-header card-header-modern">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Add New Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label form-label-modern">Category Name</label>
                            <input type="text" class="form-control form-control-modern" name="category_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label form-label-modern">Description</label>
                            <textarea class="form-control form-control-modern" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern btn-modern-primary">
                            <i class="fas fa-plus me-2"></i>Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content modern-card">
                <div class="modal-header card-header-modern">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        <div class="mb-3">
                            <label class="form-label form-label-modern">Category Name</label>
                            <input type="text" class="form-control form-control-modern" name="category_name" id="edit_category_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label form-label-modern">Description</label>
                            <textarea class="form-control form-control-modern" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern btn-modern-success">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content modern-card">
                <div class="modal-header card-header-modern bg-danger">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>
                        Delete Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                        <h6>Are you sure you want to delete this category?</h6>
                        <p class="text-muted mb-0" id="delete_category_name"></p>
                        <small class="text-danger">This action cannot be undone.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" id="delete_category_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern btn-modern-danger">
                            <i class="fas fa-trash me-2"></i>Delete Category
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Enhanced table row animations
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table-modern tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 50}ms`;
                row.classList.add('fade-in-row');
            });
        });

        // Enhanced dropdown management
        document.addEventListener('DOMContentLoaded', function() {
            // Close all dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown-modern')) {
                    closeAllDropdowns();
                }
            });

            // Handle dropdown toggle clicks
            document.querySelectorAll('.dropdown-modern .dropdown-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const dropdown = this.closest('.dropdown-modern');
                    const isOpen = dropdown.classList.contains('show');
                    
                    // Close all dropdowns first
                    closeAllDropdowns();
                    
                    // Open this dropdown if it wasn't open
                    if (!isOpen) {
                        openDropdown(dropdown);
                    }
                });
            });

            // Prevent dropdown menu clicks from closing the dropdown
            document.querySelectorAll('.dropdown-modern .dropdown-menu').forEach(menu => {
                menu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });

            // Handle dropdown item clicks (except for buttons that should trigger actions)
            document.querySelectorAll('.dropdown-modern .dropdown-item').forEach(item => {
                if (!item.hasAttribute('onclick')) {
                    item.addEventListener('click', function() {
                        closeAllDropdowns();
                    });
                }
            });
        });

        function openDropdown(dropdown) {
            // Add active class to table row
            const tableRow = dropdown.closest('tr');
            if (tableRow) {
                tableRow.classList.add('dropdown-active');
            }
            
            // Show dropdown
            dropdown.classList.add('show');
            const menu = dropdown.querySelector('.dropdown-menu');
            if (menu) {
                menu.classList.add('show');
                menu.style.zIndex = '10000';
            }
        }

        function closeAllDropdowns() {
            // Remove active class from all table rows
            document.querySelectorAll('.table-modern tbody tr.dropdown-active').forEach(row => {
                row.classList.remove('dropdown-active');
            });
            
            // Close all dropdowns
            document.querySelectorAll('.dropdown-modern.show').forEach(dropdown => {
                dropdown.classList.remove('show');
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    menu.classList.remove('show');
                }
            });
        }

        // Edit category function
        function editCategory(id, name, description) {
            // Close all dropdowns first
            closeAllDropdowns();
            
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_description').value = description;
            
            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }

        // Delete category function
        function deleteCategory(id, name) {
            // Close all dropdowns first
            closeAllDropdowns();
            
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
            modal.show();
        }

        // Floating action button (scroll to top)
        const floatingBtn = document.createElement('button');
        floatingBtn.className = 'floating-action';
        floatingBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        floatingBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
        document.body.appendChild(floatingBtn);

        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                floatingBtn.style.display = 'flex';
                floatingBtn.style.alignItems = 'center';
                floatingBtn.style.justifyContent = 'center';
            } else {
                floatingBtn.style.display = 'none';
            }
        });
    </script>
</body>
</html> 