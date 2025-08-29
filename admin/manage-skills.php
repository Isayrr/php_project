<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$success = null;

try {
    // Handle skill actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add_skill') {
                $skill_name = trim($_POST['skill_name']);
                $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 1;
                
                if (empty($skill_name)) {
                    throw new Exception("Skill name is required");
                }
                
                // Check if skill already exists
                $stmt = $conn->prepare("SELECT COUNT(*) FROM skills WHERE skill_name = ?");
                $stmt->execute([$skill_name]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("This skill already exists");
                }
                
                // Add new skill
                $stmt = $conn->prepare("INSERT INTO skills (skill_name, priority) VALUES (?, ?)");
                $stmt->execute([$skill_name, $priority]);
                
                $success = "Skill added successfully";
            } 
            elseif ($action === 'update_skill' && isset($_POST['skill_id'])) {
                $skill_id = $_POST['skill_id'];
                $skill_name = trim($_POST['skill_name']);
                $priority = (int)$_POST['priority'];
                
                if (empty($skill_name)) {
                    throw new Exception("Skill name is required");
                }
                
                // Update skill
                $stmt = $conn->prepare("UPDATE skills SET skill_name = ?, priority = ? WHERE skill_id = ?");
                $stmt->execute([$skill_name, $priority, $skill_id]);
                
                $success = "Skill updated successfully";
            }
            elseif ($action === 'delete_skill' && isset($_POST['skill_id'])) {
                $skill_id = $_POST['skill_id'];
                
                // Check if skill is in use
                $stmt = $conn->prepare("SELECT COUNT(*) FROM job_skills WHERE skill_id = ?");
                $stmt->execute([$skill_id]);
                $job_count = $stmt->fetchColumn();
                
                $stmt = $conn->prepare("SELECT COUNT(*) FROM jobseeker_skills WHERE skill_id = ?");
                $stmt->execute([$skill_id]);
                $seeker_count = $stmt->fetchColumn();
                
                if ($job_count > 0 || $seeker_count > 0) {
                    throw new Exception("This skill is in use and cannot be deleted. It's used in $job_count jobs and by $seeker_count job seekers.");
                }
                
                // Delete skill
                $stmt = $conn->prepare("DELETE FROM skills WHERE skill_id = ?");
                $stmt->execute([$skill_id]);
                
                $success = "Skill deleted successfully";
            }
        }
    }
    
    // Get all skills
    $stmt = $conn->prepare("SELECT * FROM skills ORDER BY priority DESC, skill_name");
    $stmt->execute();
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get skills usage statistics
    $stmt = $conn->prepare("
        SELECT s.skill_id, s.skill_name,
               COUNT(DISTINCT js.job_id) as job_count,
               COUNT(DISTINCT jss.jobseeker_id) as jobseeker_count
        FROM skills s
        LEFT JOIN job_skills js ON s.skill_id = js.skill_id
        LEFT JOIN jobseeker_skills jss ON s.skill_id = jss.skill_id
        GROUP BY s.skill_id
        ORDER BY job_count DESC
    ");
    $stmt->execute();
    $skill_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Skills - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/header.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                <a href="companies.php">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
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
                <a href="manage-skills.php" class="active">
                    <i class="fas fa-tools"></i>
                    <span>Manage Skills</span>
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
            <h2 class="mb-4">Manage Skills</h2>

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
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add New Skill</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_skill">
                                <div class="mb-3">
                                    <label for="skill_name" class="form-label">Skill Name</label>
                                    <input type="text" class="form-control" id="skill_name" name="skill_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority (1-10)</label>
                                    <input type="number" class="form-control" id="priority" name="priority" min="1" max="10" value="1">
                                    <small class="text-muted">Higher values indicate higher priority in matching</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i> Add Skill
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">All Skills</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Skill Name</th>
                                            <th>Priority</th>
                                            <th>Usage</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($skills as $skill): ?>
                                            <tr>
                                                <td><?php echo $skill['skill_id']; ?></td>
                                                <td><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $skill['priority'] >= 8 ? 'danger' : ($skill['priority'] >= 5 ? 'warning' : 'primary'); ?>">
                                                        <?php echo $skill['priority']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $usage = array_filter($skill_stats, function($s) use ($skill) { 
                                                        return $s['skill_id'] == $skill['skill_id']; 
                                                    });
                                                    $usage = reset($usage) ?: ['job_count' => 0, 'jobseeker_count' => 0];
                                                    ?>
                                                    <small>
                                                        <i class="fas fa-briefcase"></i> <?php echo $usage['job_count']; ?> jobs
                                                        <br>
                                                        <i class="fas fa-user"></i> <?php echo $usage['jobseeker_count']; ?> users
                                                    </small>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editSkill<?php echo $skill['skill_id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteSkill<?php echo $skill['skill_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            
                                            <!-- Edit Skill Modal -->
                                            <div class="modal fade" id="editSkill<?php echo $skill['skill_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Skill</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="update_skill">
                                                                <input type="hidden" name="skill_id" value="<?php echo $skill['skill_id']; ?>">
                                                                <div class="mb-3">
                                                                    <label for="edit_skill_name<?php echo $skill['skill_id']; ?>" class="form-label">Skill Name</label>
                                                                    <input type="text" class="form-control" 
                                                                          id="edit_skill_name<?php echo $skill['skill_id']; ?>" 
                                                                          name="skill_name" value="<?php echo htmlspecialchars($skill['skill_name']); ?>" 
                                                                          required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="edit_priority<?php echo $skill['skill_id']; ?>" class="form-label">Priority (1-10)</label>
                                                                    <input type="number" class="form-control" 
                                                                           id="edit_priority<?php echo $skill['skill_id']; ?>" 
                                                                           name="priority" min="1" max="10" 
                                                                           value="<?php echo $skill['priority']; ?>">
                                                                    <small class="text-muted">Higher values indicate higher priority in matching</small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Update</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Delete Skill Modal -->
                                            <div class="modal fade" id="deleteSkill<?php echo $skill['skill_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Delete Skill</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete the skill <strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong>?</p>
                                                            <p class="text-danger">
                                                                <i class="fas fa-exclamation-triangle"></i>
                                                                This action cannot be undone if the skill is in use by jobs or job seekers.
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <form method="POST">
                                                                <input type="hidden" name="action" value="delete_skill">
                                                                <input type="hidden" name="skill_id" value="<?php echo $skill['skill_id']; ?>">
                                                                <button type="submit" class="btn btn-danger">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html> 