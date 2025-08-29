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

// Create sample notifications for testing
$created_notifications = [];

try {
    // 1. Job Match Notification
    if (notifyJobseekerNewMatch($conn, $user_id, 1, 'Senior Software Developer', 'Tech Corp', 85, 'PHP, JavaScript, MySQL')) {
        $created_notifications[] = 'Job Match';
    }
    
    // 2. Application Status Update
    if (notifyJobseekerApplicationUpdate($conn, $user_id, 1, 'Web Developer', 'Digital Solutions Inc', 'shortlisted')) {
        $created_notifications[] = 'Application Update';
    }
    
    // 3. Profile Completion Reminder
    if (notifyJobseekerProfileCompletion($conn, $user_id, ['Profile Picture', 'Bio'])) {
        $created_notifications[] = 'Profile Completion';
    }
    
    // 4. Resume Upload Reminder
    if (notifyJobseekerResumeUpload($conn, $user_id)) {
        $created_notifications[] = 'Resume Upload';
    }
    
    // 5. Skill Recommendations
    if (notifyJobseekerSkillRecommendations($conn, $user_id, ['React', 'Node.js', 'Python'])) {
        $created_notifications[] = 'Skill Recommendations';
    }
    
    // 6. New Jobs Available
    if (notifyJobseekerNewJobsInField($conn, $user_id, 5, 'Information Technology')) {
        $created_notifications[] = 'New Jobs';
    }
    
    // 7. Job Fair Event
    if (notifyJobseekerJobFairEvent($conn, $user_id, 1, 'Virtual Tech Job Fair 2024', '2024-02-15', 'Online Platform')) {
        $created_notifications[] = 'Job Fair Event';
    }
    
    // 8. Deadline Reminder
    if (notifyJobseekerDeadlineReminder($conn, $user_id, 2, 'Frontend Developer', 'Creative Agency', '2024-01-25')) {
        $created_notifications[] = 'Deadline Reminder';
    }
    
    // 9. System Announcement
    if (createJobseekerNotification($conn, $user_id, '🎉 Welcome to the Job Portal!', 'Thank you for joining our job portal. Complete your profile to get the best job recommendations tailored for you!', null, 'announcement')) {
        $created_notifications[] = 'Welcome Message';
    }
    
    // 10. Another Job Match with lower score
    if (notifyJobseekerNewMatch($conn, $user_id, 3, 'Junior Web Developer', 'StartupXYZ', 65, 'HTML, CSS, JavaScript')) {
        $created_notifications[] = 'Another Job Match';
    }
    
    $success_message = "Successfully created " . count($created_notifications) . " test notifications: " . implode(', ', $created_notifications);
    
} catch (Exception $e) {
    $error_message = "Error creating notifications: " . $e->getMessage();
}

// Set page title
$page_title = "Test Notifications - Job Seeker Panel";
?>
<?php include 'includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-flask"></i> Test Notification System
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h6>Sample Notifications Created:</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-bullseye text-success me-3"></i>
                            <strong>Job Matches</strong> - Notifications for jobs matching your skills
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-paper-plane text-primary me-3"></i>
                            <strong>Application Updates</strong> - Status changes for your job applications
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-user-edit text-warning me-3"></i>
                            <strong>Profile Reminders</strong> - Suggestions to complete your profile
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-file-alt text-info me-3"></i>
                            <strong>Resume Reminders</strong> - Prompts to upload your resume
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-lightbulb text-warning me-3"></i>
                            <strong>Skill Recommendations</strong> - Suggestions for in-demand skills
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-briefcase text-success me-3"></i>
                            <strong>New Job Alerts</strong> - Notifications about new jobs in your field
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-calendar-alt text-purple me-3"></i>
                            <strong>Job Fair Events</strong> - Upcoming job fairs and career events
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-clock text-danger me-3"></i>
                            <strong>Deadline Reminders</strong> - Application deadlines approaching
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="fas fa-megaphone text-primary me-3"></i>
                            <strong>System Announcements</strong> - Important updates and welcome messages
                        </li>
                    </ul>
                    
                    <div class="mt-4">
                        <h6>Next Steps:</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="notifications.php" class="btn btn-primary">
                                <i class="fas fa-bell"></i> View All Notifications
                            </a>
                            <a href="dashboard.php" class="btn btn-success">
                                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                            </a>
                            <button class="btn btn-info" onclick="location.reload()">
                                <i class="fas fa-redo"></i> Create More Test Notifications
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> This is a test page for demonstration purposes. 
                        In a real system, notifications would be created automatically based on user actions and system events.
                    </div>
                </div>
            </div>
            
            <!-- Notification Features Info -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-secondary text-white">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-star"></i> Notification System Features
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">🔔 Real-time Notifications</h6>
                            <ul class="list-unstyled">
                                <li>✅ Instant notification badge updates</li>
                                <li>✅ Auto-refresh every 30 seconds</li>
                                <li>✅ Click to view and take action</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">📱 Smart Categorization</h6>
                            <ul class="list-unstyled">
                                <li>✅ Job matches based on skills</li>
                                <li>✅ Application status updates</li>
                                <li>✅ Profile completion reminders</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-warning">⚡ Interactive Actions</h6>
                            <ul class="list-unstyled">
                                <li>✅ Mark as read/unread</li>
                                <li>✅ Delete individual notifications</li>
                                <li>✅ Clear all notifications</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-info">📊 Management Features</h6>
                            <ul class="list-unstyled">
                                <li>✅ Filter by notification type</li>
                                <li>✅ Pagination for large lists</li>
                                <li>✅ Statistics and overview</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.text-purple { color: #9b59b6 !important; }
</style>

<?php include 'includes/footer.php'; ?> 