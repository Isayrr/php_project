<?php
session_start();
require_once '../config/database.php';
require_once 'includes/jobseeker_notifications.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $success = "All notifications marked as read.";
    } catch (Exception $e) {
        $error = "Error marking notifications as read: " . $e->getMessage();
    }
}

// Handle delete all
if (isset($_POST['delete_all'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $success = "All notifications deleted.";
    } catch (Exception $e) {
        $error = "Error deleting notifications: " . $e->getMessage();
    }
}

// Get filter and pagination parameters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause based on filter
$where_clause = "WHERE user_id = ?";
$params = [$user_id];

switch ($filter) {
    case 'unread':
        $where_clause .= " AND is_read = 0";
        break;
    case 'job_match':
        $where_clause .= " AND related_type = 'job_match'";
        break;
    case 'application':
        $where_clause .= " AND related_type = 'application'";
        break;
    case 'profile':
        $where_clause .= " AND (related_type = 'profile' OR related_type = 'resume')";
        break;
    case 'events':
        $where_clause .= " AND related_type = 'job_fair'";
        break;
}

try {
    // Get total count for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications $where_clause");
    $stmt->execute($params);
    $total_notifications = $stmt->fetchColumn();
    $total_pages = ceil($total_notifications / $per_page);

    // Get notifications for current page
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        $where_clause 
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get notification statistics
    $stats = getJobseekerNotificationStats($conn, $user_id);

} catch (Exception $e) {
    $error = "Error loading notifications: " . $e->getMessage();
    $notifications = [];
    $stats = ['total' => 0, 'unread' => 0, 'today' => 0, 'by_type' => []];
}

// Helper function to get notification icon
function getNotificationIcon($type) {
    $icons = [
        'job_match' => 'fas fa-bullseye text-success',
        'application' => 'fas fa-paper-plane text-primary',
        'profile' => 'fas fa-user-edit text-warning',
        'resume' => 'fas fa-file-alt text-info',
        'skills' => 'fas fa-lightbulb text-warning',
        'job_fair' => 'fas fa-calendar-alt text-purple',
        'deadline' => 'fas fa-clock text-danger',
        'new_jobs' => 'fas fa-briefcase text-success',
        'announcement' => 'fas fa-megaphone text-primary'
    ];
    return $icons[$type] ?? 'fas fa-bell text-muted';
}

// Helper function to get notification action URL
function getNotificationActionUrl($notification) {
    switch ($notification['related_type']) {
        case 'job_match':
        case 'job':
        case 'deadline':
            return $notification['related_id'] ? "view-job.php?id=" . $notification['related_id'] : '#';
        case 'application':
            return 'applications.php';
        case 'profile':
            return 'profile.php';
        case 'resume':
            return 'profile.php#resume-section';
        case 'skills':
            return 'skills.php';
        case 'job_fair':
            return 'job-fair-events.php';
        case 'new_jobs':
            return 'jobs.php';
        default:
            return 'dashboard.php';
    }
}

// Set page title
$page_title = "Notifications - Job Seeker Panel";
?>
<?php include 'includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-bell text-primary"></i>
                    Notifications
                </h1>
                <div class="d-flex gap-2">
                    <form method="post" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm" 
                                <?php echo $stats['unread'] === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete all notifications?')">
                        <button type="submit" name="delete_all" class="btn btn-outline-danger btn-sm"
                                <?php echo $stats['total'] === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-trash-alt"></i> Delete All
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Notification Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-bell fa-2x text-primary mb-2"></i>
                            <h5 class="card-title"><?php echo $stats['total']; ?></h5>
                            <p class="card-text text-muted">Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope fa-2x text-warning mb-2"></i>
                            <h5 class="card-title"><?php echo $stats['unread']; ?></h5>
                            <p class="card-text text-muted">Unread</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-day fa-2x text-success mb-2"></i>
                            <h5 class="card-title"><?php echo $stats['today']; ?></h5>
                            <p class="card-text text-muted">Today</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-bullseye fa-2x text-info mb-2"></i>
                            <h5 class="card-title"><?php echo $stats['by_type']['job_match'] ?? 0; ?></h5>
                            <p class="card-text text-muted">Job Matches</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <ul class="nav nav-pills card-header-pills">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                               href="?filter=all">
                                <i class="fas fa-list"></i> All
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filter === 'unread' ? 'active' : ''; ?>" 
                               href="?filter=unread">
                                <i class="fas fa-envelope"></i> Unread
                                <?php if ($stats['unread'] > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $stats['unread']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filter === 'job_match' ? 'active' : ''; ?>" 
                               href="?filter=job_match">
                                <i class="fas fa-bullseye"></i> Job Matches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filter === 'application' ? 'active' : ''; ?>" 
                               href="?filter=application">
                                <i class="fas fa-paper-plane"></i> Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filter === 'profile' ? 'active' : ''; ?>" 
                               href="?filter=profile">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filter === 'events' ? 'active' : ''; ?>" 
                               href="?filter=events">
                                <i class="fas fa-calendar"></i> Events
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <!-- Notifications List -->
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No notifications found</h5>
                            <p class="text-muted">
                                <?php if ($filter === 'all'): ?>
                                    You don't have any notifications yet. You'll see job matches, application updates, and other important information here.
                                <?php else: ?>
                                    No notifications match the selected filter.
                                <?php endif; ?>
                            </p>
                            <?php if ($filter !== 'all'): ?>
                                <a href="?filter=all" class="btn btn-outline-primary">View All Notifications</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item border-bottom py-3 <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>" 
                                 data-notification-id="<?php echo $notification['notification_id']; ?>">
                                <div class="d-flex align-items-start">
                                    <!-- Icon -->
                                    <div class="notification-icon me-3">
                                        <div class="d-flex align-items-center justify-content-center rounded-circle" 
                                             style="width: 50px; height: 50px; background: rgba(52, 152, 219, 0.1);">
                                            <i class="<?php echo getNotificationIcon($notification['related_type']); ?>"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Content -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="fw-bold mb-0 <?php echo $notification['is_read'] ? 'text-muted' : 'text-dark'; ?>">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-primary ms-2">New</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-2 <?php echo $notification['is_read'] ? 'text-muted' : ''; ?>">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </p>
                                        
                                        <!-- Actions -->
                                        <div class="d-flex gap-2">
                                            <?php 
                                            $actionUrl = getNotificationActionUrl($notification);
                                            if ($actionUrl && $actionUrl !== '#'): 
                                            ?>
                                                <a href="<?php echo htmlspecialchars($actionUrl); ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!$notification['is_read']): ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mark-read-btn"
                                                        data-notification-id="<?php echo $notification['notification_id']; ?>">
                                                    <i class="fas fa-check"></i> Mark Read
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-outline-danger btn-sm delete-btn"
                                                    data-notification-id="<?php echo $notification['notification_id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Notification pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous -->
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=1">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $total_pages; ?>">
                                                <?php echo $total_pages; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Next -->
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.text-purple { color: #9b59b6 !important; }
.notification-item:hover {
    background-color: #f8f9fa !important;
}
.notification-item.bg-light {
    border-left: 3px solid #007bff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle mark as read buttons
    document.querySelectorAll('.mark-read-btn').forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.dataset.notificationId;
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            fetch('ajax/mark_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to mark notification as read');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking notification as read');
            });
        });
    });
    
    // Handle delete buttons
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete this notification?')) return;
            
            const notificationId = this.dataset.notificationId;
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            fetch('ajax/delete_notification.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to delete notification');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting notification');
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?> 