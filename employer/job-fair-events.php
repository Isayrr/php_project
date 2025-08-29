<?php
session_start();
require_once '../config/database.php';
require_once '../includes/event_notifications.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

// Get employer's company
try {
    $stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        $_SESSION['error_message'] = "Please complete your company profile first.";
        header("Location: profile.php");
        exit();
    }
    
    $company_id = $company['company_id'];
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error loading company information.";
    header("Location: dashboard.php");
    exit();
}

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'register') {
            // Check if already registered
            $stmt = $conn->prepare("SELECT registration_id FROM event_registrations WHERE event_id = ? AND employer_id = ?");
            $stmt->execute([$_POST['event_id'], $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                $_SESSION['error_message'] = "You are already registered for this event.";
            } else {
                // Register for the event
                $stmt = $conn->prepare("INSERT INTO event_registrations (event_id, employer_id, company_id, notes) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['event_id'],
                    $_SESSION['user_id'],
                    $company_id,
                    $_POST['notes'] ?? ''
                ]);
                
                // Check if event reached capacity and notify other employers
                $notified_count = 0;
                if (checkEventCapacityAndNotify($conn, $_POST['event_id'])) {
                    $notified_count = notifyEmployersEventFull($conn, $_POST['event_id']);
                }
                
                $success_msg = "Successfully registered for the event!";
                if ($notified_count > 0) {
                    $success_msg .= " Event has reached maximum capacity. $notified_count employers have been notified.";
                }
                $_SESSION['success_message'] = $success_msg;
            }
        } elseif ($_POST['action'] === 'cancel') {
            // Cancel registration
            $stmt = $conn->prepare("DELETE FROM event_registrations WHERE event_id = ? AND employer_id = ?");
            $stmt->execute([$_POST['event_id'], $_SESSION['user_id']]);
            
            // Check if spot became available and notify interested employers
            $notified_count = notifyEmployersSpotAvailable($conn, $_POST['event_id']);
            
            $success_msg = "Registration cancelled successfully.";
            if ($notified_count > 0) {
                $success_msg .= " $notified_count employers have been notified about the available spot.";
            }
            $_SESSION['success_message'] = $success_msg;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header("Location: job-fair-events.php");
    exit();
}

// Get upcoming events with registration status
try {
    $stmt = $conn->prepare("SELECT e.*, 
                           (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.event_id) as registered_count,
                           (SELECT registration_id FROM event_registrations er WHERE er.event_id = e.event_id AND er.employer_id = ?) as user_registration_id,
                           (SELECT status FROM event_registrations er WHERE er.event_id = e.event_id AND er.employer_id = ?) as registration_status
                           FROM job_fair_events e 
                           WHERE e.status IN ('upcoming', 'ongoing') 
                           ORDER BY e.event_date ASC");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get registered events
    $stmt = $conn->prepare("SELECT e.*, er.registration_date, er.status as registration_status
                           FROM job_fair_events e
                           JOIN event_registrations er ON e.event_id = er.event_id
                           WHERE er.employer_id = ?
                           ORDER BY e.event_date ASC");
    $stmt->execute([$_SESSION['user_id']]);
    $registered_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $events = [];
    $registered_events = [];
    $error_message = "Error loading events: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Fair Events - Employer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
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

    <!-- Employer Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="h3 mb-4">Job Fair Events</h1>

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

                    <!-- Registered Events Section -->
                    <?php if (!empty($registered_events)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Your Registered Events</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($registered_events as $event): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-success">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($event['event_name']); ?></h6>
                                                    <p class="card-text">
                                                        <i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($event['event_date'])); ?><br>
                                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['start_time'])) . ' - ' . date('h:i A', strtotime($event['end_time'])); ?><br>
                                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <?php
                                                        $badge_class = 'secondary';
                                                        switch ($event['registration_status']) {
                                                            case 'registered': $badge_class = 'warning'; break;
                                                            case 'confirmed': $badge_class = 'success'; break;
                                                            case 'cancelled': $badge_class = 'danger'; break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($event['registration_status']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Available Events -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Available Job Fair Events</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($events)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No Events Available</h5>
                                    <p class="text-muted">There are no upcoming job fair events at the moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($events as $event): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <?php if (!empty($event['event_photo'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($event['event_photo']); ?>" 
                                                         class="card-img-top" 
                                                         alt="<?php echo htmlspecialchars($event['event_name']); ?>"
                                                         style="height: 200px; object-fit: cover;">
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p>
                                                    
                                                    <div class="mb-3">
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <strong><i class="fas fa-calendar"></i> Date:</strong><br>
                                                                <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                                                            </div>
                                                            <div class="col-6">
                                                                <strong><i class="fas fa-clock"></i> Time:</strong><br>
                                                                <?php echo date('h:i A', strtotime($event['start_time'])) . ' - ' . date('h:i A', strtotime($event['end_time'])); ?>
                                                            </div>
                                                        </div>
                                                        <div class="row mt-2">
                                                            <div class="col-12">
                                                                <strong><i class="fas fa-map-marker-alt"></i> Location:</strong><br>
                                                                <?php echo htmlspecialchars($event['location']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="row mt-2">
                                                            <div class="col-6">
                                                                <strong><i class="fas fa-users"></i> Registered:</strong><br>
                                                                <?php echo $event['registered_count']; ?>/<?php echo $event['max_employers']; ?>
                                                            </div>
                                                            <div class="col-6">
                                                                <strong><i class="fas fa-calendar-times"></i> Deadline:</strong><br>
                                                                <?php echo date('M d, Y', strtotime($event['registration_deadline'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="card-footer">
                                                    <?php if ($event['user_registration_id']): ?>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge bg-success">Registered</span>
                                                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel your registration?');" style="display: inline;">
                                                                <input type="hidden" name="action" value="cancel">
                                                                <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Registration</button>
                                                            </form>
                                                        </div>
                                                    <?php elseif (strtotime($event['registration_deadline']) < time()): ?>
                                                                                                                    <button class="btn btn-secondary" disabled>Joined Closed</button>
                                                    <?php elseif ($event['registered_count'] >= $event['max_employers']): ?>
                                                        <button class="btn btn-warning" disabled>Event Full</button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#registerModal"
                                                                data-event-id="<?php echo $event['event_id']; ?>"
                                                                data-event-name="<?php echo htmlspecialchars($event['event_name']); ?>">
                                                            Join for Event
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Join for Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" name="event_id" id="modalEventId">
                        
                        <div class="mb-3">
                            <label class="form-label">Event:</label>
                            <p class="form-control-plaintext" id="modalEventName"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional information or special requirements..."></textarea>
                        </div>
                        

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Handle registration modal
        document.getElementById('registerModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var eventId = button.getAttribute('data-event-id');
            var eventName = button.getAttribute('data-event-name');
            
            document.getElementById('modalEventId').value = eventId;
            document.getElementById('modalEventName').textContent = eventName;
        });
    </script>
</body>
</html> 
