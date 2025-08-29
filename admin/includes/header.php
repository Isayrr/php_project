<?php
require_once __DIR__ . '/../../config/database.php';

// Get current page name for active navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.username, up.first_name, up.last_name, up.profile_picture
                       FROM users u 
                       LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$user_details = $stmt->fetch(PDO::FETCH_ASSOC);

$display_name = htmlspecialchars($user_details['username']);

// Get profile picture path (supports stored filename or relative path)
$profile_picture_url = null;
if (!empty($user_details['profile_picture'])) {
    $stored = trim($user_details['profile_picture']);
    // Determine relative URL under project root
    if (strpos($stored, '/') !== false) {
        $relative = ltrim($stored, '/');
    } else {
        $relative = 'uploads/profile_pictures/' . $stored;
    }
    $fsPath = __DIR__ . '/../../' . $relative;
    if (file_exists($fsPath)) {
        $profile_picture_url = $relative;
        $profile_picture_mtime = @filemtime($fsPath) ?: time();
    }
}

// Get unread notifications count for admin
$unread_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (Exception $e) {
    // Silently fail if notifications table doesn't exist
}
?>
<div class="admin-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="system-title">
                        <h1 class="mb-0">Job Portal Management System</h1>
                    </div>
                    <div class="header-right-section d-flex align-items-center">
                        <!-- Keep notifications -->
                        <div class="notification-container me-3 position-relative">
                            <button class="notification-btn btn btn-link text-white position-relative" id="notificationBtn" type="button" title="Notifications">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_count > 0): ?>
                                <span class="notification-badge badge rounded-pill bg-danger"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <!-- Username label -->
                        <div class="user-info text-white me-2">
                            <i class="fas fa-user-circle"></i>
                            <span class="ms-2 username-label"><?php echo $display_name; ?></span>
                        </div>
                        <!-- Keep profile avatar only (menu moved to sidebar) -->
                        <a href="profile.php" class="d-flex align-items-center text-decoration-none" title="My Profile">
                            <?php if ($profile_picture_url): ?>
                                <img src="../<?php echo htmlspecialchars($profile_picture_url); ?>?v=<?php echo isset($profile_picture_mtime) ? (int)$profile_picture_mtime : time(); ?>" 
                                     alt="Profile Picture" class="profile-picture-header" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <div class="profile-picture-placeholder"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notification Dropdown - Separate container to avoid header transform issues -->
<div class="notification-dropdown-container">
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

<style>
.admin-header {
    background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
    color: white;
    padding: 0.75rem 0;
    margin-bottom: 2rem;
    /* Anchor to viewport edges to prevent cut edges */
    position: fixed;
    top: 0;
    left: 280px;
    right: 0;
    margin-left: 0;
    width: auto;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-radius: 0;
    z-index: 1020;
    /* Ensure header always stays visible */
    transform: none !important;
    transition: none !important;
    min-height: 60px;
    display: flex;
    align-items: center;
    overflow: visible;
}

/* Use pure flex layout; let title shrink and icons stay visible */
.admin-header .container-fluid { position: static; }
.header-right-section {
    position: static;
    right: auto;
    top: auto;
    transform: none;
    margin-left: auto;
}

.system-title { flex: 1 1 auto; min-width: 0; }

/* Prevent initial flash: disable page transition fade for admin area */
.main-content.page-transition {
    animation: none !important;
    opacity: 1 !important;
    transform: none !important;
}

/* Header will always remain visible - removed auto-hide functionality */

/* Header right section - ensures proper alignment */
.header-right-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
    justify-content: flex-end;
    height: 100%;
    flex-wrap: nowrap; /* keep icons on one line */
    overflow: visible;  /* avoid clipping during layout */
    min-width: 220px; /* reserve space for icons + short name */
}

.notification-container {
    display: flex;
    align-items: center;
    height: 100%;
    flex-shrink: 0; /* prevent shrinking out of view */
}

/* User profile section */
.user-profile-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0; /* prevent shrinking out of view */
}

/* Notification dropdown container - separate from header transform */
.notification-dropdown-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 1030;
    pointer-events: none;
    transition: top 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.notification-dropdown-container .notification-dropdown {
    pointer-events: auto;
    position: relative !important;
    transform: none !important;
    margin: 0 !important;
    display: none;
}

.notification-dropdown-container .notification-dropdown.show {
    display: block;
}

/* When sidebar is collapsed */
.main-content.expanded .admin-header {
    left: 80px;
    right: 0;
    margin-left: 0;
    width: auto;
}

.system-title h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    margin: 0;
    white-space: nowrap;           /* prevent multi-line title pushing icons */
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

/* Let title area flex while keeping icons fixed-size */
.system-title { flex: 1 1 auto; min-width: 0; }
.header-right-section .dropdown { flex-shrink: 0; }

.user-info {
    font-size: 1rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    flex-shrink: 0; /* ensure visible */
}

.user-info i {
    font-size: 1.2rem;
}

.username-label {
    display: inline-block;
    max-width: 220px; /* keep long names from pushing icons */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Profile Picture Styles */
.profile-picture-container {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
}

.profile-picture-container:hover {
    transform: scale(1.1);
}

.profile-picture-header {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
    transition: border-color 0.3s ease;
}

.profile-picture-header:hover {
    border-color: rgba(255, 255, 255, 0.6);
}

.profile-picture-placeholder {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.profile-picture-placeholder:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.6);
}

.profile-picture-placeholder i {
    color: white;
    font-size: 1rem;
}

/* Adjust main content to account for fixed header */
.main-content {
    padding-top: 80px;
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
    position: relative;
    background: transparent !important;
    color: white !important;
    padding: 0;
}

.notification-btn:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    transform: scale(1.1);
}

.notification-btn i {
    font-size: 1.1rem;
    line-height: 1;
    display: block;
    margin: 0;
    padding: 0;
}

.notification-badge {
    font-size: 0.6rem;
    min-width: 14px;
    height: 14px;
    line-height: 1;
    animation: pulse 1.5s infinite;
    z-index: 1;
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    border: 1px solid white;
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
    position: fixed !important;
    top: 80px !important;
    right: 20px !important;
    z-index: 1030 !important;
    transform: none !important;
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
    line-clamp: 2;
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

/* Ensure all icons are visible and properly loaded */
.fas, .far, .fab {
    display: inline-block !important;
    font-style: normal !important;
    font-variant: normal !important;
    text-rendering: auto !important;
    -webkit-font-smoothing: antialiased !important;
}

/* Force icon visibility */
[class^="fas"], [class*=" fas"],
[class^="far"], [class*=" far"],
[class^="fab"], [class*=" fab"] {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Ensure header is full-width on tablets/mobiles to prevent flicker */
@media (max-width: 1024px) {
    .admin-header {
        left: 0;
        right: 0;
        margin-left: 0;
        width: auto;
        border-radius: 0;
    }
    .main-content {
        padding-top: 100px;
    }
    /* Show username on md and up; constrain on small screens */
    .username-label { max-width: 160px; }
}

@media (max-width: 768px) {
    .admin-header {
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
    .username-label { max-width: 120px; }
    
    .notification-dropdown {
        width: 300px;
    }
    
    .notification-container {
        margin-right: 0.5rem !important;
    }
    
    .header-right-section {
        gap: 0.25rem;
    }
    
    .user-profile-section {
        gap: 0.25rem;
    }
    
    /* Adjust main content padding for mobile */
    .main-content {
        padding-top: 70px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationList = document.getElementById('notificationList');
    const notificationFooter = document.getElementById('notificationFooter');
    const deleteAllBtn = document.getElementById('deleteAllBtn');
    
    // Enhanced Header scroll behavior - Always visible
    const adminHeader = document.querySelector('.admin-header');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    const notificationDropdownContainer = document.querySelector('.notification-dropdown-container');
    
    // Function to update dropdown position based on header state
    function updateDropdownPosition() {
        notificationDropdownContainer.style.top = '80px';
    }
    
    // Initialize dropdown position
    updateDropdownPosition();
    
    // Keep header always visible - remove auto-hide functionality
    // The header will now stay fixed at the top at all times
    
    // Load notifications when dropdown is opened
    notificationBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Toggle dropdown visibility
        const isVisible = notificationDropdown.classList.contains('show');
        if (isVisible) {
            notificationDropdown.classList.remove('show');
        } else {
            notificationDropdown.classList.add('show');
            loadNotifications();
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.remove('show');
        }
    });
    
    // Delete All button event listener
    deleteAllBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
            deleteAllNotifications();
        }
    });
    
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
            notificationFooter.style.display = 'none';
            return;
        } else {
            notificationFooter.style.display = 'block';
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
                        notificationFooter.style.display = 'none';
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
                notificationFooter.style.display = 'none';
                
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
                const newBadge = document.createElement('span');
                newBadge.className = 'notification-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                newBadge.textContent = count > 99 ? '99+' : count;
                bellIcon.parentNode.appendChild(newBadge);
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
    
    // Ensure icons are loaded properly
    function ensureIconsLoaded() {
        // Force icon visibility
        const icons = document.querySelectorAll('.fas, .far, .fab');
        icons.forEach(icon => {
            icon.style.display = 'inline-block';
            icon.style.visibility = 'visible';
            icon.style.opacity = '1';
        });
    }
    
    // Call on page load and after a short delay to ensure icons are visible
    ensureIconsLoaded();
    setTimeout(ensureIconsLoaded, 100);
    setTimeout(ensureIconsLoaded, 500);
});
</script> 