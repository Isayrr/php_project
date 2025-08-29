<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notifications.php';
require_once 'includes/jobseeker_notifications.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

// Check for new job matches and create notifications
checkNewJobMatches($conn, $_SESSION['user_id']);

// Initialize variables
$error = null;
$profile = null;
$skills = [];
$recent_applications = [];
$recommended_jobs = [];
$notifications = [];
$matching_jobs_count = 0;

try {
    // Get user profile
    $stmt = $conn->prepare("SELECT up.* FROM user_profiles up WHERE up.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user skills
    $stmt = $conn->prepare("SELECT s.* FROM skills s 
                           JOIN jobseeker_skills js ON s.skill_id = js.skill_id 
                           WHERE js.jobseeker_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get count of all matching jobs for the badge
    if (!empty($skills)) {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT j.job_id) as matching_jobs_count
            FROM jobs j
            JOIN job_skills jsk ON j.job_id = jsk.job_id
            JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id
            WHERE j.status = 'active' AND js.jobseeker_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $matching_jobs_count = $stmt->fetchColumn();
    }
    
    // Get recommended jobs based on skills
    $stmt = $conn->prepare("
        SELECT j.*, c.company_name,
               COUNT(DISTINCT js.skill_id) as matching_skills,
               GROUP_CONCAT(DISTINCT s.skill_name) as matching_skills_list,
               GROUP_CONCAT(DISTINCT js2.skill_name) as missing_skills
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        LEFT JOIN job_skills jsk ON j.job_id = jsk.job_id
        LEFT JOIN jobseeker_skills js ON js.skill_id = jsk.skill_id AND js.jobseeker_id = ?
        LEFT JOIN skills s ON s.skill_id = js.skill_id
        LEFT JOIN skills js2 ON js2.skill_id = jsk.skill_id AND js.skill_id IS NULL
        WHERE j.status = 'active'
        GROUP BY j.job_id
        HAVING matching_skills > 0
        ORDER BY matching_skills DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recommended_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent applications
    $stmt = $conn->prepare("
        SELECT a.*, j.title as job_title, c.company_name, sm.match_score
                           FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
                           JOIN companies c ON j.company_id = c.company_id 
        LEFT JOIN skill_matches sm ON a.application_id = sm.application_id
                           WHERE a.jobseeker_id = ? 
        ORDER BY a.application_date DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread notifications
    $notifications = getUnreadNotifications($conn, $_SESSION['user_id']);
    
    // Mark notification as read if requested
    if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
        markNotificationAsRead($conn, $_GET['mark_read'], $_SESSION['user_id']);
        header("Location: dashboard.php");
        exit();
    }
    
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Set page title
$page_title = "Jobseeker Dashboard - Job Portal";
?>
<?php include 'includes/header.php'; ?>

<style>
/* Modern Dashboard Design */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --info-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
    
    --bg-primary: #f8fafc;
    --bg-secondary: #ffffff;
    --text-primary: #2d3748;
    --text-secondary: #718096;
    --border-color: #e2e8f0;
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

body {
    background: var(--bg-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: var(--text-primary);
    line-height: 1.6;
}

/* Container and Layout */
.dashboard-container {
    background: var(--bg-primary);
    min-height: 100vh;
    padding: 2rem 0;
}

.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

/* Welcome Section */
.welcome-section {
    background: var(--bg-secondary);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}

.welcome-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    border-radius: 24px 24px 0 0;
}

.profile-avatar {
    position: relative;
    display: inline-block;
}

.profile-avatar img {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    object-fit: cover;
    border: 4px solid var(--bg-secondary);
    box-shadow: var(--shadow-lg);
    transition: all 0.3s ease;
}

.profile-avatar:hover img {
    transform: scale(1.05);
    box-shadow: var(--shadow-xl);
}

.camera-btn {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-gradient);
    border: 3px solid var(--bg-secondary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.camera-btn:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-md);
}

.welcome-text h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.welcome-text p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin: 0;
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-secondary);
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    border-radius: 20px 20px 0 0;
}

.stat-card.primary::before { background: var(--primary-gradient); }
.stat-card.success::before { background: var(--success-gradient); }
.stat-card.warning::before { background: var(--warning-gradient); }
.stat-card.info::before { background: var(--info-gradient); }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-bottom: 1rem;
}

.stat-icon.primary { background: var(--primary-gradient); }
.stat-icon.success { background: var(--success-gradient); }
.stat-icon.warning { background: var(--warning-gradient); }
.stat-icon.info { background: var(--info-gradient); }

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Modern Cards */
.modern-card {
    background: var(--bg-secondary);
    border-radius: 24px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    margin-bottom: 2rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

.modern-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.modern-card-header {
    padding: 1.5rem 2rem;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modern-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modern-card-title i {
    font-size: 1.1rem;
    color: #667eea;
}

.modern-card-body {
    padding: 2rem;
}

/* Enhanced Buttons */
.btn {
    border-radius: 12px;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-size: 0.9rem;
    letter-spacing: 0.025em;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-success {
    background: var(--success-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(79, 172, 254, 0.4);
    color: white;
}

.btn-outline-primary {
    background: transparent;
    border: 2px solid #667eea;
    color: #667eea;
}

.btn-outline-primary:hover {
    background: var(--primary-gradient);
    border-color: transparent;
    color: white;
    transform: translateY(-2px);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

/* Enhanced Tables */
.modern-table {
    background: var(--bg-secondary);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.table {
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.table thead th {
    background: #f8fafc;
    color: var(--text-primary);
    font-weight: 600;
    border: none;
    padding: 1rem 1.5rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.table tbody td {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #f8fafc;
}

/* Enhanced Badges */
.badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.badge.bg-warning { background: var(--warning-gradient) !important; }
.badge.bg-success { background: var(--success-gradient) !important; }
.badge.bg-primary { background: var(--primary-gradient) !important; }
.badge.bg-info { background: var(--info-gradient) !important; }
.badge.bg-danger { background: var(--secondary-gradient) !important; }

/* Progress Bars */
.progress {
    height: 8px;
    border-radius: 50px;
    background: #e2e8f0;
    overflow: hidden;
}

.progress-bar {
    border-radius: 50px;
    background: var(--success-gradient);
    transition: width 0.6s ease;
}

/* Notifications */
.notification-item {
    background: var(--bg-secondary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.notification-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--primary-gradient);
}

.notification-item:hover {
    transform: translateX(8px);
    box-shadow: var(--shadow-md);
}

/* Alerts */
.alert {
    border-radius: 16px;
    border: 1px solid var(--border-color);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}

.alert-info {
    background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
    border-color: #90cdf4;
    color: #2c5282;
}

.alert-success {
    background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
    border-color: #9ae6b4;
    color: #276749;
}

.alert-warning {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border-color: #fcd34d;
    color: #92400e;
}

.alert-danger {
    background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
    border-color: #f87171;
    color: #991b1b;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 1rem;
    }
    
    .dashboard-container {
        padding: 1rem 0;
    }
    
    .welcome-section {
        padding: 1.5rem;
        border-radius: 16px;
    }
    
    .welcome-text h1 {
        font-size: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1.25rem;
        border-radius: 16px;
    }
    
    .modern-card {
        border-radius: 16px;
    }
    
    .modern-card-header,
    .modern-card-body {
        padding: 1.5rem;
    }
    
    .btn {
        padding: 0.625rem 1.25rem;
        font-size: 0.875rem;
    }
    
    .table thead th,
    .table tbody td {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .welcome-section .d-flex {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .profile-avatar {
        align-self: center;
    }
    
    .welcome-text {
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .modern-card-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}

/* Loading Animation */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Glassmorphism Effect */
.glass-card {
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.18);
}
</style>

<div class="dashboard-container">
    <div class="container-fluid">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="profile-avatar">
                        <?php
                        $profile_picture = !empty($profile['profile_picture']) ? '../' . $profile['profile_picture'] : '../assets/images/default-profile.png';
                        ?>
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                        <div class="camera-btn" data-bs-toggle="modal" data-bs-target="#uploadProfilePicModal">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div class="welcome-text">
                        <h1>Welcome back, <?php echo htmlspecialchars($profile['first_name'] ?? 'User'); ?>!</h1>
                        <p>Ready to find your next opportunity? Let's make it happen.</p>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <?php if (!empty($skills) && $matching_jobs_count > 0): ?>
                    <a href="find-matching-jobs.php" class="btn btn-success">
                        <i class="fas fa-puzzle-piece"></i> 
                        Find Matching Jobs
                        <span class="badge bg-light text-dark ms-1"><?php echo $matching_jobs_count; ?></span>
                    </a>
                    <?php else: ?>
                    <a href="skills.php" class="btn btn-primary">
                        <i class="fas fa-tools"></i> Update Your Skills
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upload Profile Picture Modal -->
        <div class="modal fade" id="uploadProfilePicModal" tabindex="-1" aria-labelledby="uploadProfilePicModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 20px; border: none;">
                    <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                        <h5 class="modal-title" id="uploadProfilePicModalLabel">Update Profile Picture</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="upload-profile-image.php" method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Choose Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" required style="border-radius: 12px;">
                                <div class="form-text">Maximum file size: 5MB. Supported formats: JPG, PNG, GIF</div>
                            </div>
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                            <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <!-- Account Status Notification -->
        <?php if (isset($_SESSION['account_notification'])): ?>
        <div class="alert alert-<?php echo strpos($_SESSION['account_notification']['title'], 'Approved') !== false ? 'success' : 'warning'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo strpos($_SESSION['account_notification']['title'], 'Approved') !== false ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <strong><?php echo htmlspecialchars($_SESSION['account_notification']['title']); ?></strong>
            <p class="mb-0 mt-1"><?php echo htmlspecialchars($_SESSION['account_notification']['message']); ?></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['account_notification']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon primary">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-number"><?php echo count($recent_applications); ?></div>
                <div class="stat-label">Applications</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon success">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-number"><?php echo count($skills); ?></div>
                <div class="stat-label">Skills Added</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon warning">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo count($recommended_jobs); ?></div>
                <div class="stat-label">Recommended</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon info">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-number"><?php echo count($notifications); ?></div>
                <div class="stat-label">Notifications</div>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (!empty($notifications)): ?>
        <div class="modern-card">
            <div class="modern-card-header">
                <h5 class="modern-card-title">
                    <i class="fas fa-bell"></i>
                    Notifications
                </h5>
                <span class="badge bg-danger"><?php echo count($notifications); ?></span>
            </div>
            <div class="modern-card-body">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($notification['title']); ?></h6>
                            <small class="text-muted">
                                <?php echo date('M d, g:i a', strtotime($notification['created_at'])); ?>
                            </small>
                        </div>
                        <p class="mb-3 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($notification['related_type'] === 'job' && $notification['related_id']): ?>
                                <a href="view-job.php?id=<?php echo $notification['related_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View Job
                                </a>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                            <a href="dashboard.php?mark_read=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-check"></i> Mark as Read
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Summary -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h5 class="modern-card-title">
                    <i class="fas fa-user"></i>
                    Profile Summary
                </h5>
                <a href="profile.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
            <div class="modern-card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon primary me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-muted small">Location</div>
                                <div><?php echo htmlspecialchars($profile['address'] ?? 'Not specified'); ?></div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="stat-icon success me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-muted small">Experience</div>
                                <div><?php echo htmlspecialchars($profile['experience'] ?? 'Not specified'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon warning me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-muted small">Skills</div>
                                <div><?php echo count($skills); ?> skills added</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="stat-icon info me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-muted small">Applications</div>
                                <div><?php echo count($recent_applications); ?> recent applications</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommended Jobs -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h5 class="modern-card-title">
                    <i class="fas fa-star"></i>
                    Recommended Jobs
                </h5>
                <?php if (!empty($skills) && $matching_jobs_count > 0): ?>
                <a href="find-matching-jobs.php" class="btn btn-sm btn-success">
                    <i class="fas fa-puzzle-piece"></i> 
                    Find More Jobs
                </a>
                <?php endif; ?>
            </div>
            <div class="modern-card-body">
                <?php if (empty($recommended_jobs)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No recommended jobs found. 
                        <a href="skills.php" class="alert-link">Add your skills</a> to get personalized job recommendations.
                    </div>
                    <?php if (!empty($skills) && $matching_jobs_count > 0): ?>
                    <div class="text-center mt-3">
                        <a href="find-matching-jobs.php" class="btn btn-primary">
                            <i class="fas fa-puzzle-piece"></i> Find Jobs Matching Your Skills
                        </a>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="modern-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Type</th>
                                    <th>Skill Match</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recommended_jobs as $job): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($job['title']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo ucfirst($job['job_type']); ?></span>
                                    </td>
                                    <td>
                                        <div class="progress mb-2">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo count($skills) > 0 ? ($job['matching_skills'] / count($skills)) * 100 : 0; ?>%"
                                                 aria-valuenow="<?php echo count($skills) > 0 ? ($job['matching_skills'] / count($skills)) * 100 : 0; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo $job['matching_skills']; ?> skills match</small>
                                        <?php if ($job['matching_skills_list']): ?>
                                            <div class="small text-success mt-1">
                                                <i class="fas fa-check me-1"></i><?php echo htmlspecialchars($job['matching_skills_list']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="apply-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h5 class="modern-card-title">
                    <i class="fas fa-paper-plane"></i>
                    Recent Applications
                </h5>
                <a href="applications.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list"></i> View All
                </a>
            </div>
            <div class="modern-card-body">
                <?php if (empty($recent_applications)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>You haven't applied to any jobs yet. 
                        <a href="jobs.php" class="alert-link">Browse available jobs</a> to start applying.
                    </div>
                <?php else: ?>
                    <div class="modern-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_applications as $application): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($application['job_title']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($application['company_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($application['application_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($application['status']) {
                                                case 'pending':
                                                    echo 'warning';
                                                    break;
                                                case 'reviewed':
                                                    echo 'info';
                                                    break;
                                                case 'shortlisted':
                                                    echo 'primary';
                                                    break;
                                                case 'rejected':
                                                    echo 'danger';
                                                    break;
                                                case 'hired':
                                                    echo 'success';
                                                    break;
                                                default:
                                                    echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-job.php?id=<?php echo $application['job_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
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

<?php include 'includes/footer.php'; ?> 