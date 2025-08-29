<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="sidebar-logo">
            <h3>Job Seeker Panel</h3>
        </div>
        <button class="toggle-btn">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="profile.php" <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </li>
        <li>
            <a href="jobs.php" <?php echo basename($_SERVER['PHP_SELF']) == 'jobs.php' || basename($_SERVER['PHP_SELF']) == 'view-job.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-search"></i>
                <span>Find Jobs</span>
            </a>
        </li>
        <li>
            <a href="applications.php" <?php echo basename($_SERVER['PHP_SELF']) == 'applications.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-file-alt"></i>
                <span>My Applications</span>
            </a>
        </li>
        <li>
            <a href="skills.php" <?php echo basename($_SERVER['PHP_SELF']) == 'skills.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-tools"></i>
                <span>My Skills</span>
            </a>
        </li>
        <li>
            <a href="find-matching-jobs.php" <?php echo basename($_SERVER['PHP_SELF']) == 'find-matching-jobs.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-puzzle-piece"></i>
                <span>Skill Matching</span>
                <?php 
                // Show count of skill-matched jobs if available
                if (isset($matching_jobs_count) && $matching_jobs_count > 0): 
                ?>
                <span class="badge bg-success float-end"><?php echo $matching_jobs_count; ?></span>
                <?php endif; ?>
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