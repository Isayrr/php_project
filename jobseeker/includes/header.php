<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get unread notifications count for jobseeker
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/jobseeker_notifications.php';

$unread_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'jobseeker') {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_count = $stmt->fetchColumn();
        
        // Check and create automatic notifications
        checkAndCreateAutomaticNotifications($conn, $_SESSION['user_id']);
    } catch (Exception $e) {
        // Silently fail if notifications table doesn't exist
        error_log("Notification error in header: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Job Seeker Panel'; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/jobseeker-modern.css" rel="stylesheet">

    <style>
        /* Jobseeker Header Styles */
        .jobseeker-header {
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1020;
            padding: 0;
            backdrop-filter: none !important;
            border-bottom: none !important;
        }
        
        /* Dropdown hover functionality */
        @media (min-width: 992px) {
            .dropdown:hover .dropdown-menu {
                display: block;
                margin-top: 0;
                animation: fadeIn 0.2s ease-in;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: #fff !important;
            text-decoration: none;
        }
        
        .navbar-nav .nav-link {
            color: #b0b8c1 !important;
            font-weight: 500;
            padding: 12px 20px !important;
            margin: 0 5px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar-nav .nav-link:hover {
            color: #fff !important;
            background: rgba(52, 152, 219, 0.2);
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link.active {
            color: #3498db !important;
            background: rgba(52, 152, 219, 0.15);
            font-weight: 600;
        }
        
        .navbar-nav .nav-link i {
            font-size: 1.1rem;
        }
        
        .dropdown-menu {
            background: #2c3e50;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-top: 10px;
        }
        
        .dropdown-item {
            color: #b0b8c1 !important;
            padding: 12px 20px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dropdown-item:hover {
            background: rgba(52, 152, 219, 0.2);
            color: #fff !important;
        }
        
        .dropdown-item.active {
            background: rgba(52, 152, 219, 0.3);
            color: #3498db !important;
        }
        
        .navbar-toggler {
            border: none;
            padding: 8px 12px;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        /* Main content adjustments */
        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            padding-top: 20px;
        }
        
        /* Logo styles */
        .logo-circle, .talavera-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .logo-circle img, .talavera-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* User info in header */
        .user-info {
            color: #b0b8c1;
            font-size: 0.9rem;
            margin-right: 15px;
        }
        
        .user-info strong {
            color: #fff;
        }
        
        /* Dropdown toggle styling */
        .dropdown-toggle::after {
            margin-left: 8px;
        }
        
        /* Notification Styles */
        .notification-btn {
            background: none !important;
            border: none !important;
            color: #b0b8c1 !important;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
            margin-right: 15px;
        }
        
        .notification-btn:hover {
            color: #fff !important;
            background: rgba(52, 152, 219, 0.2) !important;
            transform: scale(1.1);
        }
        
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .notification-dropdown {
            width: 380px;
            max-height: 450px;
            overflow-y: auto;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            margin-top: 8px;
            background: #2c3e50;
        }
        
        .notification-dropdown .dropdown-header {
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-weight: 600;
            color: #fff;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: background-color 0.2s ease;
            position: relative;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        
        .notification-item:hover {
            background: rgba(52, 152, 219, 0.1);
        }
        
        .notification-item.unread {
            background: rgba(52, 152, 219, 0.1);
            border-left: 3px solid #3498db;
        }
        
        .notification-item.unread::after {
            content: '';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: #3498db;
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.6);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(52, 152, 219, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 600;
            color: #fff;
            font-size: 0.9rem;
            margin-bottom: 5px;
            line-height: 1.3;
        }
        
        .notification-message {
            color: #b0b8c1;
            font-size: 0.8rem;
            line-height: 1.4;
            margin-bottom: 5px;
        }
        
        .notification-time {
            color: #7f8c8d;
            font-size: 0.75rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 5px;
            margin-top: 8px;
        }
        
        .notification-actions .btn {
            padding: 2px 8px;
            font-size: 0.7rem;
            border-radius: 4px;
        }
        
        .notification-footer {
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0 0 12px 12px;
            text-align: center;
        }
        
        .notification-footer .btn {
            font-size: 0.8rem;
            padding: 5px 15px;
        }
        
        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: #7f8c8d;
        }
        
        .notification-empty i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Text color utilities */
        .text-purple { color: #9b59b6 !important; }
        
        /* Responsive adjustments */
        @media (max-width: 991px) {
            .navbar-nav {
                background: rgba(44, 62, 80, 0.95);
                border-radius: 12px;
                margin-top: 15px;
                padding: 15px;
            }
            
            .navbar-nav .nav-link {
                margin: 2px 0;
            }
            
            .user-info {
                margin: 10px 0;
                text-align: center;
            }
            
            .dropdown-menu {
                background: rgba(44, 62, 80, 0.98);
                margin-top: 5px;
            }
            
            .notification-dropdown {
                width: 320px;
                max-height: 350px;
            }
            
            .notification-btn {
                margin-right: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Jobseeker Header -->
    <nav class="navbar navbar-expand-lg jobseeker-header">
        <div class="container-fluid px-4">
            <!-- Brand -->
            <div class="d-flex align-items-center">
                <!-- Logos -->
                <div class="d-flex me-3">
                    <div class="logo-circle me-2" style="width: 40px; height: 40px;">
                        <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="img-fluid rounded-circle">
                    </div>
                    <div class="talavera-circle" style="width: 40px; height: 40px;">
                        <img src="../assets/images/talaveralogo.jpg" alt="Talavera Logo" class="img-fluid rounded-circle">
                    </div>
                </div>
                <!-- Brand text -->
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-briefcase me-2"></i>
                Job Seeker Panel
            </a>
            </div>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#jobseekerNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="jobseekerNav">
                <ul class="navbar-nav me-auto">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    
                    <!-- Jobs Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                            <i class="fas fa-search"></i> Jobs
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="jobs.php">
                                    <i class="fas fa-search"></i> Find Jobs
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="applications.php">
                                    <i class="fas fa-file-alt"></i> My Applications
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    
                    
                    <!-- Resume -->
                    <li class="nav-item">
                        <a class="nav-link" href="resume-builder.php">
                            <i class="fas fa-file-pdf"></i> Resume Builder
                        </a>
                    </li>
                </ul>
                
                <!-- User Info & Account -->
                <div class="d-flex align-items-center">
                    <!-- Notification Bell -->
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'jobseeker'): ?>
                    <div class="dropdown">
                        <button class="notification-btn" id="notificationBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                            <span class="notification-badge" id="notificationBadge">
                                <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <!-- Notification Dropdown -->
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationBtn">
                            <div class="dropdown-header">
                                <span>Notifications</span>
                                <small class="text-muted ms-auto">Latest updates</small>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <!-- Notifications will be loaded via AJAX -->
                                <div class="notification-empty">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <div>Loading...</div>
                                </div>
                            </div>
                            <div class="notification-footer" id="notificationFooter" style="display: none;">
                                <button class="btn btn-outline-danger btn-sm" id="deleteAllBtn">
                                    <i class="fas fa-trash-alt"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="user-info d-none d-lg-block">
                            Welcome, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Account Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i>
                            <span class="d-none d-md-inline ms-1">Account</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="applications.php">
                                    <i class="fas fa-file-alt"></i> My Applications
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="skills.php">
                                    <i class="fas fa-tools"></i> My Skills
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="notifications.php">
                                    <i class="fas fa-bell"></i> Notifications
                                    <?php if ($unread_count > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <div class="main-content">

<!-- Notification JavaScript -->
<script>
// Notification functionality
document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationList = document.getElementById('notificationList');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationFooter = document.getElementById('notificationFooter');
    const deleteAllBtn = document.getElementById('deleteAllBtn');
    
    let notificationsLoaded = false;
    
    // Load notifications when dropdown is opened
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            if (!notificationsLoaded) {
                loadNotifications();
            }
        });
    }
    
    // Load notifications function
    function loadNotifications() {
        fetch('ajax/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateNotificationBadge(data.unread_count);
                    notificationsLoaded = true;
                } else {
                    showNotificationError('Failed to load notifications');
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                showNotificationError('Error loading notifications');
            });
    }
    
    // Display notifications in dropdown
    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <div>No notifications yet</div>
                    <small>You'll see job matches and updates here</small>
                </div>
            `;
            notificationFooter.style.display = 'none';
            return;
        }
        
        let html = '';
        notifications.forEach(notification => {
            const unreadClass = notification.is_read == 0 ? 'unread' : '';
            html += `
                <div class="notification-item ${unreadClass}" data-notification-id="${notification.notification_id}">
                    <div class="notification-icon">
                        <i class="${notification.icon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${notification.time_ago}</div>
                        <div class="notification-actions">
                            ${notification.action_url && notification.action_url !== '#' ? 
                                `<button class="btn btn-primary btn-sm" onclick="handleNotificationClick(${notification.notification_id}, '${notification.action_url}')">
                                    <i class="fas fa-eye"></i> View
                                </button>` : ''
                            }
                            <button class="btn btn-outline-secondary btn-sm" onclick="markAsRead(${notification.notification_id})">
                                <i class="fas fa-check"></i> ${notification.is_read == 0 ? 'Mark Read' : 'Read'}
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteNotification(${notification.notification_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        notificationList.innerHTML = html;
        notificationFooter.style.display = 'block';
    }
    
    // Update notification badge
    function updateNotificationBadge(count) {
        if (notificationBadge) {
            if (count > 0) {
                notificationBadge.textContent = count > 99 ? '99+' : count;
                notificationBadge.style.display = 'flex';
            } else {
                notificationBadge.style.display = 'none';
            }
        }
    }
    
    // Show notification error
    function showNotificationError(message) {
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <div>${message}</div>
            </div>
        `;
    }
    
    // Handle notification click
    window.handleNotificationClick = function(notificationId, actionUrl) {
        // Mark as read first
        markAsRead(notificationId, function() {
            // Then navigate to the action URL
            window.location.href = actionUrl;
        });
    };
    
    // Mark notification as read
    window.markAsRead = function(notificationId, callback) {
        const formData = new FormData();
        formData.append('notification_id', notificationId);
        
        fetch('ajax/mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the notification item visually
                const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('unread');
                    const markReadBtn = notificationItem.querySelector('.btn-outline-secondary');
                    if (markReadBtn) {
                        markReadBtn.innerHTML = '<i class="fas fa-check"></i> Read';
                    }
                }
                
                // Update badge
                updateNotificationBadge(data.unread_count);
                
                if (callback) callback();
            } else {
                console.error('Failed to mark notification as read');
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    };
    
    // Delete notification
    window.deleteNotification = function(notificationId) {
        if (!confirm('Are you sure you want to delete this notification?')) return;
        
        const formData = new FormData();
        formData.append('notification_id', notificationId);
        
        fetch('ajax/delete_notification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the notification item
                const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.remove();
                }
                
                // Reload notifications to update count
                notificationsLoaded = false;
                loadNotifications();
            } else {
                alert('Failed to delete notification');
            }
        })
        .catch(error => {
            console.error('Error deleting notification:', error);
            alert('Error deleting notification');
        });
    };
    
    // Delete all notifications
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete all notifications?')) return;
            
            fetch('ajax/delete_all_notifications.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationList.innerHTML = `
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i>
                            <div>No notifications</div>
                            <small>All notifications have been cleared</small>
                        </div>
                    `;
                    updateNotificationBadge(0);
                    notificationFooter.style.display = 'none';
                } else {
                    alert('Failed to delete notifications');
                }
            })
            .catch(error => {
                console.error('Error deleting all notifications:', error);
                alert('Error deleting notifications');
            });
        });
    }
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        if (notificationsLoaded) {
            loadNotifications();
        }
    }, 30000);
});
</script> 