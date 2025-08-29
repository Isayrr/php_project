<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    $_SESSION['error_message'] = "Invalid event ID.";
    header("Location: job-fair-events.php");
    exit();
}

// Get event details
try {
    $stmt = $conn->prepare("SELECT e.*, u.email as created_by_email
                           FROM job_fair_events e 
                           LEFT JOIN users u ON e.created_by = u.user_id 
                           WHERE e.event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $_SESSION['error_message'] = "Event not found.";
        header("Location: job-fair-events.php");
        exit();
    }
    
    // Get event registrations
    $stmt = $conn->prepare("SELECT er.registration_id, er.registration_date, er.status, er.notes,
                           c.company_name, c.industry, u.email as employer_email, up.first_name, up.last_name
                           FROM event_registrations er
                           JOIN companies c ON er.company_id = c.company_id
                           JOIN users u ON er.employer_id = u.user_id
                           LEFT JOIN user_profiles up ON u.user_id = up.user_id
                           WHERE er.event_id = ?
                           ORDER BY er.registration_date ASC");
    $stmt->execute([$event_id]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error loading event details: " . $e->getMessage();
    header("Location: job-fair-events.php");
    exit();
}

// Handle registration status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_registration_status') {
            $stmt = $conn->prepare("UPDATE event_registrations SET status = ? WHERE registration_id = ?");
            $stmt->execute([$_POST['status'], $_POST['registration_id']]);
            $_SESSION['success_message'] = "Registration status updated successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    header("Location: view-event.php?id=" . $event_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Event - <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
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
                <a href="job-fair-events.php" class="active">
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

    <!-- Main Content -->
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Event Details</h1>
                        <a href="job-fair-events.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Events
                        </a>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Event Information -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Event Information</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($event['event_photo'])): ?>
                                        <div class="mb-3 text-center">
                                            <img src="../<?php echo htmlspecialchars($event['event_photo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($event['event_name']); ?>" 
                                                 class="img-fluid rounded" 
                                                 style="max-height: 300px; object-fit: cover;">
                                        </div>
                                    <?php endif; ?>
                                    <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                    <?php if ($event['event_description']): ?>
                                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-calendar"></i> Date:</strong><br>
                                            <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-clock"></i> Time:</strong><br>
                                            <?php echo date('h:i A', strtotime($event['start_time'])) . ' - ' . date('h:i A', strtotime($event['end_time'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-map-marker-alt"></i> Location:</strong><br>
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-calendar-times"></i> Registration Deadline:</strong><br>
                                            <?php echo date('F d, Y', strtotime($event['registration_deadline'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Event Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-12 mb-3">
                                            <h3 class="text-primary"><?php echo count($registrations); ?>/<?php echo $event['max_employers']; ?></h3>
                                            <p class="mb-0">Registered Employers</p>
                                        </div>
                                                                <div class="col-6">
                            <h4 class="text-success"><?php echo count(array_filter($registrations, fn($r) => $r['status'] === 'registered')); ?></h4>
                            <p class="mb-0">Active</p>
                        </div>
                        <div class="col-6">
                            <h4 class="text-danger"><?php echo count(array_filter($registrations, fn($r) => $r['status'] === 'cancelled')); ?></h4>
                            <p class="mb-0">Cancelled</p>
                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="text-center">
                                        <?php
                                        $badge_class = 'secondary';
                                        switch ($event['status']) {
                                            case 'upcoming': $badge_class = 'primary'; break;
                                            case 'ongoing': $badge_class = 'success'; break;
                                            case 'completed': $badge_class = 'secondary'; break;
                                            case 'cancelled': $badge_class = 'danger'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?> fs-6"><?php echo ucfirst($event['status']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registered Employers -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Registered Employers</h5>

                        </div>
                        <div class="card-body">
                            <?php if (empty($registrations)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5>No Registrations Yet</h5>
                                    <p class="text-muted">No employers have registered for this event yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Company</th>
                                                <th>Employer Contact</th>
                                                <th>Industry</th>
                                                <th>Registration Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($registrations as $registration): ?>
                                                <tr id="registration-<?php echo $registration['registration_id']; ?>">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($registration['company_name']); ?></strong>
                                                                <?php if ($registration['notes']): ?>
                                                                    <br><small class="text-muted">
                                                                        <i class="fas fa-sticky-note"></i> 
                                                                        <?php echo htmlspecialchars(substr($registration['notes'], 0, 50)) . (strlen($registration['notes']) > 50 ? '...' : ''); ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($registration['first_name'] && $registration['last_name']): ?>
                                                            <?php echo htmlspecialchars($registration['first_name'] . ' ' . $registration['last_name']); ?><br>
                                                        <?php endif; ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($registration['employer_email']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($registration['industry']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($registration['registration_date'])); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge_class = $registration['status'] === 'cancelled' ? 'danger' : 'success';
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($registration['status']); ?></span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#updateRegistrationModal"
                                                                data-registration-id="<?php echo $registration['registration_id']; ?>"
                                                                data-status="<?php echo $registration['status']; ?>"
                                                                data-company="<?php echo htmlspecialchars($registration['company_name']); ?>">
                                                            <i class="fas fa-edit"></i> Update
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Registration Modal -->
    <div class="modal fade" id="updateRegistrationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_registration_status">
                        <input type="hidden" name="registration_id" id="modalRegistrationId">
                        
                        <div class="mb-3">
                            <label class="form-label">Company:</label>
                            <p class="form-control-plaintext" id="modalCompanyName"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modalStatus" class="form-label">Status</label>
                            <select class="form-select" id="modalStatus" name="status" required>
                                <option value="registered">Registered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Handle update registration modal
        document.getElementById('updateRegistrationModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var registrationId = button.getAttribute('data-registration-id');
            var status = button.getAttribute('data-status');
            var company = button.getAttribute('data-company');
            
            document.getElementById('modalRegistrationId').value = registrationId;
            document.getElementById('modalStatus').value = status;
            document.getElementById('modalCompanyName').textContent = company;
        });

        // Enhanced quick status update (without page reload)
        function quickStatusUpdate(registrationId, newStatus) {
            fetch('ajax/quick_status_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    registration_id: registrationId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the status badge in the table
                    var row = document.getElementById('registration-' + registrationId);
                    var statusCell = row.cells[3]; // Status column
                    var badgeClass = newStatus === 'cancelled' ? 'danger' : 'success';
                    statusCell.innerHTML = '<span class="badge bg-' + badgeClass + '">' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '</span>';
                    
                    // Show success message
                    showToast('Registration status updated successfully!', 'success');
                } else {
                    alert('Error updating status: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating status');
            });
        }
        
        // Toast notification function
        function showToast(message, type) {
            var toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-' + (type === 'success' ? 'success' : 'danger') + ' border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div></div>';
            
            // Add to page and show
            document.body.appendChild(toast);
            var bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove after hidden
            toast.addEventListener('hidden.bs.toast', function() {
                toast.remove();
            });
        }
    </script>
</body>
</html> 