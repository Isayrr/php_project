<?php
// Get user details for employer header
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.username, up.first_name, up.last_name, c.company_name 
                       FROM users u 
                       LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                       LEFT JOIN companies c ON u.user_id = c.employer_id
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$user_details = $stmt->fetch(PDO::FETCH_ASSOC);

$display_name = $user_details['company_name'] 
                ? htmlspecialchars($user_details['company_name'])
                : ($user_details['first_name'] && $user_details['last_name'] 
                   ? htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name'])
                   : htmlspecialchars($user_details['username']));

// Get unread notifications count for employer
$unread_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (Exception $e) {
    // Silently fail if notifications table doesn't exist
}
?>
<div class="employer-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="system-title">
                        <h1 class="mb-0">Job Portal - Employer Dashboard</h1>
                    </div>
                    <div class="d-flex align-items-center">
                        <!-- Notification Icon -->
                        <div class="notification-container me-3 position-relative">
                            <button class="notification-btn btn btn-link text-white p-2" id="notificationBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell" style="font-size: 1.2rem;"></i>
                                <?php if ($unread_count > 0): ?>
                                <span class="notification-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                                </span>
                                <?php endif; ?>
                            </button>
                            <!-- Notification Dropdown -->
                            <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationBtn">
                                <div class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span>Notifications</span>
                                    <a href="notifications.php" class="text-primary small">View All</a>
                                </div>
                                <div class="notification-list" id="notificationList">
                                    <!-- Notifications will be loaded via AJAX -->
                                    <div class="p-3 text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Loading...
                                    </div>
                                </div>
                                <div class="dropdown-footer" id="notificationFooter" style="display: none;">
                                    <div class="d-grid gap-2 p-2">
                                        <button class="btn btn-outline-danger btn-sm" id="deleteAllBtn">
                                            <i class="fas fa-trash-alt"></i> Delete All
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- User Info -->
                        <div class="user-info text-white">
                            <i class="fas fa-building"></i>
                            <span class="ms-2"><?php echo $display_name; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.employer-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 1rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-radius: 0 0 1rem 1rem;
    position: sticky;
    top: 0;
    z-index: 1020;
}

.system-title h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    margin: 0;
}

.user-info {
    font-size: 1rem;
    font-weight: 500;
}

.user-info i {
    font-size: 1.2rem;
}

/* Notification Styles */
.notification-btn {
    border: none !important;
    box-shadow: none !important;
    transition: all 0.3s ease;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-btn:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    transform: scale(1.1);
}

.notification-badge {
    font-size: 0.7rem;
    min-width: 18px;
    height: 18px;
    line-height: 1;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.1); }
    100% { transform: translate(-50%, -50%) scale(1); }
}

.notification-dropdown {
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    margin-top: 8px;
}

.notification-dropdown .dropdown-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
    color: #495057;
    border-radius: 12px 12px 0 0;
}

.notification-item {
    padding: 12px 20px;
    border-bottom: 1px solid #f1f3f4;
    cursor: pointer;
    transition: background-color 0.2s ease;
    position: relative;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #e3f2fd;
    border-left: 3px solid #2196f3;
}

.notification-item.unread::after {
    content: '';
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    background: #2196f3;
    border-radius: 50%;
}

.notification-title {
    font-weight: 600;
    color: #212529;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.notification-message {
    color: #6c757d;
    font-size: 0.8rem;
    margin-bottom: 4px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notification-time {
    color: #adb5bd;
    font-size: 0.75rem;
}

.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #6c757d;
}

.notification-empty i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: #dee2e6;
}

.dropdown-footer {
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.dropdown-footer .btn {
    font-size: 0.85rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .employer-header {
        padding: 0.75rem 0;
    }
    
    .system-title h1 {
        font-size: 1.1rem;
    }
    
    .user-info {
        font-size: 0.9rem;
    }
    
    .user-info i {
        font-size: 1.1rem;
    }
    
    .notification-dropdown {
        width: 300px;
    }
    
    .notification-container {
        margin-right: 0.5rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationList = document.getElementById('notificationList');
    const notificationFooter = document.getElementById('notificationFooter');
    const deleteAllBtn = document.getElementById('deleteAllBtn');
    
    // Only proceed if elements exist (to avoid errors on pages without notifications)
    if (!notificationBtn || !notificationList) return;
    
    // Load notifications when dropdown is opened
    notificationBtn.addEventListener('click', function() {
        loadNotifications();
    });
    
    // Delete All button event listener
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
                deleteAllNotifications();
            }
        });
    }
    
    function loadNotifications() {
        // Show loading spinner
        notificationList.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        // Fetch notifications via AJAX
        fetch('ajax/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateNotificationBadge(data.unread_count);
                } else {
                    notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-circle"></i><br>Error loading notifications</div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-circle"></i><br>Error loading notifications</div>';
            });
    }
    
    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><br>No notifications</div>';
            if (notificationFooter) notificationFooter.style.display = 'none';
            return;
        } else {
            if (notificationFooter) notificationFooter.style.display = 'block';
        }
        
        let html = '';
        notifications.forEach(notification => {
            const timeAgo = formatTimeAgo(notification.created_at);
            const unreadClass = notification.is_read == 0 ? 'unread' : '';
            
            html += `
                <div class="notification-item ${unreadClass}" data-id="${notification.notification_id}">
                    <div class="notification-title">${escapeHtml(notification.title)}</div>
                    <div class="notification-message">${escapeHtml(notification.message)}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            `;
        });
        
        notificationList.innerHTML = html;
        
        // Add click handlers to notification items
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                const clickedItem = this;
                
                // Add visual feedback - fade out effect
                clickedItem.style.opacity = '0.5';
                clickedItem.style.pointerEvents = 'none';
                
                deleteNotification(notificationId, clickedItem);
            });
        });
    }
    
    function deleteNotification(notificationId, clickedItem) {
        fetch('ajax/delete_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the badge count with the new unread count from server
                updateNotificationBadge(data.unread_count);
                
                // Add smooth fade-out animation before removal
                clickedItem.style.transition = 'all 0.3s ease';
                clickedItem.style.transform = 'translateX(100%)';
                clickedItem.style.opacity = '0';
                
                setTimeout(() => {
                    // Remove the notification item from the dropdown
                    clickedItem.remove();
                    
                    // Check if dropdown is now empty
                    const remainingItems = document.querySelectorAll('.notification-item');
                    if (remainingItems.length === 0) {
                        notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><br>No notifications</div>';
                        if (notificationFooter) notificationFooter.style.display = 'none';
                    }
                }, 300);
                
            } else {
                console.error('Error deleting notification:', data.message);
                // Restore the item if deletion failed
                clickedItem.style.opacity = '1';
                clickedItem.style.pointerEvents = 'auto';
            }
        })
        .catch(error => {
            console.error('Error deleting notification:', error);
            // Restore the item if deletion failed
            clickedItem.style.opacity = '1';
            clickedItem.style.pointerEvents = 'auto';
        });
    }
    
    function deleteAllNotifications() {
        if (!deleteAllBtn) return;
        
        // Show loading state
        deleteAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        deleteAllBtn.disabled = true;
        
        fetch('ajax/delete_all_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the badge count
                updateNotificationBadge(0);
                
                // Clear notifications list and hide footer
                notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><br>No notifications</div>';
                if (notificationFooter) notificationFooter.style.display = 'none';
                
                // Show success message briefly
                notificationList.innerHTML = '<div class="p-3 text-center text-success"><i class="fas fa-check-circle"></i><br>All notifications deleted</div>';
                
                setTimeout(() => {
                    notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><br>No notifications</div>';
                }, 2000);
                
            } else {
                console.error('Error deleting all notifications:', data.message);
                alert('Error deleting all notifications. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error deleting all notifications:', error);
            alert('Error deleting all notifications. Please try again.');
        })
        .finally(() => {
            // Restore button state
            deleteAllBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete All';
            deleteAllBtn.disabled = false;
        });
    }
    
    function updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 99 ? '99+' : count;
            } else {
                // Create badge if it doesn't exist
                const bellIcon = document.querySelector('.notification-btn i');
                if (bellIcon) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    newBadge.textContent = count > 99 ? '99+' : count;
                    bellIcon.parentNode.appendChild(newBadge);
                }
            }
        } else {
            // Remove badge if count is 0
            if (badge) {
                badge.remove();
            }
        }
    }
    
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        
        return date.toLocaleDateString();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
