<?php
// Script to replace all instances of pesologo.jpg with new Peso logo.jpg

$files = [
    'pending_approval.php',
    'check_status.php',
    'employer/dashboard.php',
    'includes/jobseeker-sidebar.php',
    'jobseeker/includes/header.php',
    'components/jobseeker-sidebar.php',
    'admin/applications.php',
    'admin/categories.php',
    'admin/dashboard.php',
    'admin/companies.php',
    'admin/manage-skills.php',
    'admin/notifications.php',
    'admin/reports.php',
    'admin/skills-report.php',
    'admin/test_application_notifications.php',
    'admin/jobs.php',
    'admin/test_notifications.php',
    'admin/users.php',
    'admin/view-application.php',
    'admin/view-company.php',
    'admin/job-fair-events.php',
    'admin/view-event.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $newContent = str_replace('pesologo.jpg', 'new Peso logo.jpg', $content);
        file_put_contents($file, $newContent);
        echo "Updated: $file\n";
    } else {
        echo "File not found: $file\n";
    }
}

echo "Logo replacement completed!\n";
?> 