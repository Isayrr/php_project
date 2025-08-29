<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Handle marking all as read
if (isset($_POST['mark_all_read'])) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $success = "All notifications marked as read.";
    } catch (Exception $e) {
        $error = "Error marking notifications as read: " . $e->getMessage();
    }
}

// Handle deleting all notifications
if (isset($_POST['delete_all_notifications'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $deleted_count = $stmt->rowCount();
        $success = "All {$deleted_count} notifications deleted successfully.";
        
        // Reset variables since all notifications are deleted
        $notifications = [];
        $total_notifications = 0;
        $total_pages = 0;
        $unread_count = 0;
    } catch (Exception $e) {
        $error = "Error deleting all notifications: " . $e->getMessage();
    }
}

// Handle marking single notification as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$_POST['notification_id'], $user_id]);
        $success = "Notification marked as read.";
    } catch (Exception $e) {
        $error = "Error marking notification as read: " . $e->getMessage();
    }
}

// Handle deleting notification
if (isset($_POST['delete_notification']) && isset($_POST['notification_id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$_POST['notification_id'], $user_id]);
        $success = "Notification deleted.";
    } catch (Exception $e) {
        $error = "Error deleting notification: " . $e->getMessage();
    }
}

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query conditions
$where_conditions = ["user_id = ?"];
$params = [$user_id];

if ($filter === 'unread') {
    $where_conditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where_conditions[] = "is_read = 1";
} elseif ($filter === 'account_approval') {
    $where_conditions[] = "related_type IN ('account_approval', 'pending_account')";
} elseif ($filter === 'job_approval') {
    $where_conditions[] = "related_type IN ('job_approval', 'pending_job')";
}

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR message LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = "WHERE " . implode(' AND ', $where_conditions);

try {
    // Get total count
    $count_query = "SELECT COUNT(*) FROM notifications $where_clause";
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_notifications = $stmt->fetchColumn();
    
    // Calculate pagination
    $total_pages = ceil($total_notifications / $per_page);
    
    // Get notifications for current page
    $query = "
        SELECT 
            notification_id,
            title,
            message,
            related_id,
            related_type,
            created_at,
            is_read
        FROM notifications 
        $where_clause
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $error = "Error fetching notifications: " . $e->getMessage();
    $notifications = [];
    $total_notifications = 0;
    $total_pages = 0;
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/header.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .notification-card {
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .notification-card.unread {
            border-left-color: #007bff;
            background-color: #f8f9ff;
        }
        .notification-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .notification-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .notification-card:hover .notification-actions {
            opacity: 1;
        }
        .filter-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 2px solid transparent;
        }
        .filter-tabs .nav-link.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: none;
        }
        .search-box {
            max-width: 300px;
        }
        .text-purple {
            color: #6f42c1 !important;
        }
        .filter-tabs .nav-link {
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .filter-tabs .nav-link i {
            margin-right: 0.25rem;
        }
        .notification-card.approval-notification {
            border-left-width: 5px;
        }
        .notification-card.account-approval {
            border-left-color: #28a745;
        }
        .notification-card.job-approval {
            border-left-color: #007bff;
        }
        .notification-card.clickable {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.3s ease;
        }
        .notification-card.clickable:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .notification-card.clickable:active {
            transform: translateY(0);
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
                <a href="notifications.php" class="active">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-2"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
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
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Notifications</h2>
                    <p class="text-muted mb-0">Manage your system notifications</p>
                    <small class="text-info">
                        <i class="fas fa-info-circle"></i> 
                        Tip: Click notifications in the dropdown to automatically delete them
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unread_count > 0): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($total_notifications > 0): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete ALL notifications? This action cannot be undone.');">
                        <button type="submit" name="delete_all_notifications" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-trash-alt"></i> Delete All
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filters and Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <!-- Filter Tabs -->
                            <ul class="nav nav-pills filter-tabs">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                                       href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        All (<?php echo $total_notifications; ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'unread' ? 'active' : ''; ?>" 
                                       href="?filter=unread<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Unread (<?php echo $unread_count; ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'read' ? 'active' : ''; ?>" 
                                       href="?filter=read<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Read
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'account_approval' ? 'active' : ''; ?>" 
                                       href="?filter=account_approval<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-user-check"></i> Account Approvals
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'job_approval' ? 'active' : ''; ?>" 
                                       href="?filter=job_approval<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-briefcase"></i> Job Approvals
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <!-- Search Box -->
                            <form method="GET" class="d-flex justify-content-end">
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                                <div class="input-group search-box">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search notifications..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No notifications found</h5>
                    <p class="text-muted mb-0">
                        <?php if (!empty($search)): ?>
                            No notifications match your search criteria.
                        <?php elseif ($filter === 'unread'): ?>
                            You have no unread notifications.
                        <?php else: ?>
                            You haven't received any notifications yet.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                <div class="card notification-card mb-3 <?php echo $notification['is_read'] ? '' : 'unread'; ?> 
                    <?php 
                    if (in_array($notification['related_type'], ['account_approval', 'pending_account'])) {
                        echo 'approval-notification account-approval';
                    } elseif (in_array($notification['related_type'], ['job_approval', 'pending_job'])) {
                        echo 'approval-notification job-approval';
                    }
                    
                    // Make notification clickable if it has actionable content
                    $is_clickable = !empty($notification['related_id']) || 
                                   in_array($notification['related_type'], ['system', 'event', 'application', 'company']);
                    if ($is_clickable) {
                        echo ' clickable';
                    }
                    ?>"
                    <?php if ($is_clickable): ?>
                    onclick="handleNotificationClick(<?php echo $notification['notification_id']; ?>)"
                    title="Click to view details and take action"
                    <?php endif; ?>>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-2 d-flex align-items-center">
                                    <?php if (!$notification['is_read']): ?>
                                    <span class="badge bg-primary me-2">New</span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Add icons based on notification type
                                    $icon = 'fas fa-bell';
                                    $icon_color = 'text-secondary';
                                    
                                    if (in_array($notification['related_type'], ['account_approval', 'pending_account'])) {
                                        if (strpos($notification['title'], 'Approved') !== false) {
                                            $icon = 'fas fa-user-check';
                                            $icon_color = 'text-success';
                                        } elseif (strpos($notification['title'], 'Rejected') !== false) {
                                            $icon = 'fas fa-user-times';
                                            $icon_color = 'text-danger';
                                        } else {
                                            $icon = 'fas fa-user-clock';
                                            $icon_color = 'text-warning';
                                        }
                                    } elseif (in_array($notification['related_type'], ['job_approval', 'pending_job'])) {
                                        if (strpos($notification['title'], 'Approved') !== false) {
                                            $icon = 'fas fa-briefcase';
                                            $icon_color = 'text-success';
                                        } elseif (strpos($notification['title'], 'Rejected') !== false) {
                                            $icon = 'fas fa-briefcase';
                                            $icon_color = 'text-danger';
                                        } else {
                                            $icon = 'fas fa-clock';
                                            $icon_color = 'text-warning';
                                        }
                                    } elseif ($notification['related_type'] === 'job') {
                                        $icon = 'fas fa-briefcase';
                                        $icon_color = 'text-info';
                                    } elseif ($notification['related_type'] === 'user') {
                                        $icon = 'fas fa-user-plus';
                                        $icon_color = 'text-info';
                                    } elseif ($notification['related_type'] === 'application') {
                                        $icon = 'fas fa-file-alt';
                                        $icon_color = 'text-primary';
                                    } elseif ($notification['related_type'] === 'event') {
                                        $icon = 'fas fa-calendar-alt';
                                        $icon_color = 'text-purple';
                                    } elseif ($notification['related_type'] === 'system') {
                                        $icon = 'fas fa-cog';
                                        $icon_color = 'text-dark';
                                    }
                                    ?>
                                    
                                    <i class="<?php echo $icon; ?> <?php echo $icon_color; ?> me-2"></i>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h6>
                                <p class="card-text mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="notification-meta">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($notification['created_at'])); ?>
                                    <?php if ($notification['related_type']): ?>
                                    <span class="ms-3">
                                        <i class="fas fa-tag"></i>
                                        <?php echo ucfirst($notification['related_type']); ?>
                                        <?php if ($notification['related_id']): ?>
                                        #<?php echo $notification['related_id']; ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_clickable): ?>
                                    <span class="ms-3 text-primary">
                                        <i class="fas fa-mouse-pointer"></i>
                                        <small>Click to view</small>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="notification-actions ms-3">
                                <div class="btn-group" role="group">
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                        <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Notifications pagination">
                <ul class="pagination justify-content-center">
                    <!-- Previous Page -->
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <!-- Next Page -->
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        function handleNotificationClick(notificationId) {
            // Prevent the click from propagating to action buttons
            event.stopPropagation();
            
            // Navigate to the notification router
            window.location.href = 'notification_router.php?id=' + notificationId;
        }
        
        // Prevent notification click when clicking on action buttons
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelectorAll('.notification-actions button, .notification-actions form');
            actionButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });
    </script>
</body>
</html> 