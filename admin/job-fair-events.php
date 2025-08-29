<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_event':
                    // Handle event photo upload
                    $event_photo = null;
                    if (isset($_FILES['event_photo']) && $_FILES['event_photo']['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        $file_type = $_FILES['event_photo']['type'];
                        
                        if (!in_array($file_type, $allowed_types)) {
                            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
                        }
                        
                        $file_size = $_FILES['event_photo']['size'];
                        if ($file_size > 5242880) { // 5MB limit
                            throw new Exception('File is too large. Maximum size is 5MB.');
                        }
                        
                        // Create upload directory if it doesn't exist
                        $upload_dir = '../uploads/job_fair_events/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['event_photo']['name'], PATHINFO_EXTENSION);
                        $file_name = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['event_photo']['tmp_name'], $upload_path)) {
                            $event_photo = 'uploads/job_fair_events/' . $file_name;
                        } else {
                            throw new Exception('Failed to upload event photo.');
                        }
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO job_fair_events (event_name, event_description, event_photo, event_date, start_time, end_time, location, max_employers, registration_deadline, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['event_name'],
                        $_POST['event_description'],
                        $event_photo,
                        $_POST['event_date'],
                        $_POST['start_time'],
                        $_POST['end_time'],
                        $_POST['location'],
                        $_POST['max_employers'],
                        $_POST['registration_deadline'],
                        $_SESSION['user_id']
                    ]);
                    $_SESSION['success_message'] = "Job fair event created successfully!";
                    break;
                    
                case 'update_status':
                    $stmt = $conn->prepare("UPDATE job_fair_events SET status = ? WHERE event_id = ?");
                    $stmt->execute([$_POST['status'], $_POST['event_id']]);
                    $_SESSION['success_message'] = "Event status updated successfully!";
                    break;
                    
                case 'delete_event':
                    $stmt = $conn->prepare("DELETE FROM job_fair_events WHERE event_id = ?");
                    $stmt->execute([$_POST['event_id']]);
                    $_SESSION['success_message'] = "Event deleted successfully!";
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    header("Location: job-fair-events.php");
    exit();
}

// Get all events
try {
    $stmt = $conn->prepare("SELECT e.*, u.email as created_by_email,
                           (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.event_id) as registered_employers
                           FROM job_fair_events e 
                           LEFT JOIN users u ON e.created_by = u.user_id 
                           ORDER BY e.event_date ASC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
    $error_message = "Error loading events: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Fair Events - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --info-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            --border-radius: 15px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .main-content {
            transition: var(--transition);
            padding: 0;
        }

        .content-wrapper {
            background: transparent;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="25" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="25" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            animation: slideInLeft 0.6s ease;
        }

        .btn-modern {
            background: var(--secondary-gradient);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: var(--transition);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
            animation: slideInRight 0.6s ease;
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .stats-container {
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            border: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--card-hover-shadow);
        }

        .stat-card:hover::before {
            height: 100%;
            opacity: 0.1;
        }

        .stat-card.primary::before { background: var(--primary-gradient); }
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin: 0;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: countUp 1s ease;
        }

        .stat-card.success .stat-number {
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.info .stat-number {
            background: var(--info-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.warning .stat-number {
            background: var(--warning-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @keyframes countUp {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }

        .stat-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #6c757d;
            margin: 0;
            margin-top: 0.5rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            top: 1rem;
            right: 1rem;
            transition: var(--transition);
        }

        .stat-card:hover .stat-icon {
            opacity: 0.4;
            transform: scale(1.1);
        }

                 .events-card {
             background: white;
             border-radius: var(--border-radius);
             box-shadow: var(--card-shadow);
             border: none;
             overflow: visible;
             transition: var(--transition);
         }

        .events-card:hover {
            box-shadow: var(--card-hover-shadow);
        }

        .card-header-modern {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            position: relative;
        }

        .card-title-modern {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

                 .table-modern {
             margin: 0;
         }

         .table-responsive {
             overflow: visible;
         }

                 .table-modern thead th {
             border: none;
             background: #f8f9fa;
             font-weight: 700;
             color: #495057;
             padding: 1.5rem 1rem;
             position: sticky;
             top: 0;
             z-index: 5;
         }

        .table-modern tbody tr {
            transition: var(--transition);
            border: none;
        }

                 .table-modern tbody tr:hover {
             background: linear-gradient(90deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
             transform: none;
         }

        .table-modern td {
            padding: 1.5rem 1rem;
            border-color: #f1f3f4;
            vertical-align: middle;
        }

        .clickable-photo {
            transition: var(--transition);
            border: 3px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }

        .clickable-photo:hover {
            transform: scale(1.15) rotate(2deg);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }

        .clickable-photo::after {
            content: '\f065';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 8px;
            border-radius: 50%;
            font-size: 14px;
            opacity: 0;
            transition: var(--transition);
            pointer-events: none;
        }

        .clickable-photo:hover::after {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1.1);
        }

        .badge-modern {
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
        }

        .badge-modern.bg-primary { background: var(--primary-gradient) !important; }
        .badge-modern.bg-success { background: var(--success-gradient) !important; }
        .badge-modern.bg-info { background: var(--info-gradient) !important; }
        .badge-modern.bg-warning { background: var(--warning-gradient) !important; }
        .badge-modern.bg-danger { background: var(--secondary-gradient) !important; }
        .badge-modern.bg-secondary { background: var(--dark-gradient) !important; }

        .dropdown-modern .dropdown-toggle {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            color: white;
            font-weight: 600;
            transition: var(--transition);
        }

        .dropdown-modern .dropdown-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

                 .dropdown-modern .dropdown-menu {
             border: none;
             border-radius: 12px;
             box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
             animation: fadeInUp 0.3s ease;
             z-index: 9999 !important;
             position: absolute !important;
             background: white !important;
             transform: none !important;
             will-change: auto;
         }

        .dropdown-modern .dropdown-item {
            padding: 12px 20px;
            transition: var(--transition);
            border-radius: 8px;
            margin: 4px 8px;
        }

                 .dropdown-modern .dropdown-item:hover {
             background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
             transform: translateX(5px);
         }

         .dropdown-modern {
             position: relative;
             z-index: auto;
         }

         .table-modern tbody tr {
             position: relative;
             z-index: 1;
         }

         .table-modern tbody tr:has(.dropdown.show) {
             z-index: 10000;
         }

         .dropdown-modern.show {
             z-index: 10001 !important;
         }

         .dropdown-modern .dropdown-item {
             color: #495057;
             text-decoration: none;
             display: block;
             width: 100%;
             background: transparent;
             border: none;
             text-align: left;
         }

         .dropdown-modern .dropdown-item i {
             width: 16px;
             margin-right: 8px;
         }

         .dropdown-modern .dropdown-item.text-danger:hover {
             background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.1));
             color: #dc3545;
         }

         .dropdown-modern .dropdown-header {
             font-size: 0.75rem;
             font-weight: 600;
             color: #6c757d;
             text-transform: uppercase;
             letter-spacing: 0.5px;
         }

         .dropdown-modern .form-select {
             border: 1px solid #e9ecef;
             border-radius: 8px;
             font-size: 0.875rem;
             margin-bottom: 8px;
         }

         .dropdown-modern .form-select:focus {
             border-color: #667eea;
             box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
         }

         /* Additional fixes for dropdown layering */
         .table-modern td:last-child {
             position: relative;
             z-index: auto;
         }

         .dropdown-modern .dropdown-menu.show {
             display: block !important;
             opacity: 1 !important;
             visibility: visible !important;
             transform: translateY(0) !important;
             z-index: 10002 !important;
         }

         .dropdown-modern .dropdown-menu:not(.show) {
             display: none !important;
         }

         /* Ensure table doesn't clip dropdowns */
         .table-responsive {
             overflow: visible !important;
         }

         .events-card .card-body {
             overflow: visible !important;
         }

        .modal-modern .modal-content {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .modal-modern .modal-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            border: none;
        }

        .modal-modern .modal-title {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .modal-modern .modal-body {
            padding: 2rem;
        }

        .modal-modern .modal-footer {
            padding: 1.5rem 2rem;
            border: none;
            background: #f8f9fa;
        }

        .form-control-modern {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            transition: var(--transition);
            background: white;
        }

        .form-control-modern:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: translateY(-2px);
        }

        .form-label-modern {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            animation: slideInRight 0.5s ease;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            color: white;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            z-index: 1000;
            display: none;
        }

        .floating-action:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                border-radius: 12px;
            }
        }

        /* Animation delays for staggered effects */
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        /* Page transition */
        .page-transition {
            animation: pageLoad 0.8s ease;
        }

        @keyframes pageLoad {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                <a href="job-fair-events.php" class="active">
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
    <div class="main-content page-transition">
        <?php include 'includes/header.php'; ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- Modern Page Header -->
                    <div class="page-header" data-aos="fade-down">
                        <div class="container-fluid">
                            <div class="d-flex justify-content-between align-items-center position-relative">
                                <div>
                                    <h1 class="page-title">
                                        <i class="fas fa-calendar-alt me-3"></i>
                                        Job Fair Events
                                    </h1>
                                    <p class="mb-0 opacity-75" style="font-size: 1.1rem;">Manage and oversee all job fair events</p>
                                </div>
                                <button type="button" class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#addEventModal">
                                    <i class="fas fa-plus me-2"></i> Create New Event
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Events Statistics Summary -->
                    <?php
                    try {
                        // Get overall statistics
                        $stats = [
                            'total_events' => $conn->query("SELECT COUNT(*) FROM job_fair_events")->fetchColumn(),
                            'upcoming_events' => $conn->query("SELECT COUNT(*) FROM job_fair_events WHERE status = 'upcoming'")->fetchColumn(),
                            'total_registrations' => $conn->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn(),
                            'active_registrations' => $conn->query("SELECT COUNT(*) FROM event_registrations WHERE status = 'registered'")->fetchColumn()
                        ];
                    } catch (Exception $e) {
                        $stats = ['total_events' => 0, 'upcoming_events' => 0, 'total_registrations' => 0, 'active_registrations' => 0];
                    }
                    ?>
                    <!-- Modern Statistics Cards -->
                    <div class="stats-container">
                        <div class="row">
                            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                                <div class="stat-card primary">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h2 class="stat-number" data-count="<?php echo $stats['total_events']; ?>">0</h2>
                                            <p class="stat-label">Total Events</p>
                                        </div>
                                        <i class="fas fa-calendar-alt stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                                <div class="stat-card success">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h2 class="stat-number" data-count="<?php echo $stats['upcoming_events']; ?>">0</h2>
                                            <p class="stat-label">Upcoming Events</p>
                                        </div>
                                        <i class="fas fa-clock stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                                <div class="stat-card info">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h2 class="stat-number" data-count="<?php echo $stats['total_registrations']; ?>">0</h2>
                                            <p class="stat-label">Total Registrations</p>
                                        </div>
                                        <i class="fas fa-users stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                                <div class="stat-card warning">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h2 class="stat-number" data-count="<?php echo $stats['active_registrations']; ?>">0</h2>
                                            <p class="stat-label">Active Registrations</p>
                                        </div>
                                        <i class="fas fa-user-check stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modern Alert Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-modern alert-dismissible fade show" role="alert" data-aos="slide-left">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-modern alert-dismissible fade show" role="alert" data-aos="slide-left">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Modern Events Table -->
                    <div class="events-card" data-aos="fade-up" data-aos-delay="500">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="fas fa-list me-2"></i>
                                All Job Fair Events
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($events)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <h5>No Events Found</h5>
                                    <p class="text-muted">No job fair events have been created yet. Click "Create New Event" to get started!</p>
                                    <button type="button" class="btn btn-modern mt-3" data-bs-toggle="modal" data-bs-target="#addEventModal">
                                        <i class="fas fa-plus me-2"></i> Create Your First Event
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Event Name</th>
                                                <th>Photo</th>
                                                <th>Date & Time</th>
                                                <th>Location</th>
                                                <th>Registered Employers</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($events as $event): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($event['event_description'], 0, 100)) . '...'; ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($event['event_photo'])): ?>
                                                            <div class="position-relative d-inline-block">
                                                                <img src="../<?php echo htmlspecialchars($event['event_photo']); ?>" 
                                                                     alt="Event Photo" 
                                                                     class="img-thumbnail clickable-photo" 
                                                                     style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                                                                     data-bs-toggle="modal"
                                                                     data-bs-target="#photoViewModal"
                                                                     data-photo-src="../<?php echo htmlspecialchars($event['event_photo']); ?>"
                                                                     data-photo-title="<?php echo htmlspecialchars($event['event_name']); ?>"
                                                                     title="Click to view full size">
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-center text-muted">
                                                                <i class="fas fa-image"></i>
                                                                <br><small>No Photo</small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('h:i A', strtotime($event['start_time'])) . ' - ' . date('h:i A', strtotime($event['end_time'])); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="badge bg-info me-2"><?php echo $event['registered_employers']; ?>/<?php echo $event['max_employers']; ?></span>
                                                            <?php if ($event['registered_employers'] > 0): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#viewRegistrationsModal"
                                                                        data-event-id="<?php echo $event['event_id']; ?>"
                                                                        data-event-name="<?php echo htmlspecialchars($event['event_name']); ?>">
                                                                    <i class="fas fa-users"></i> View
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'secondary';
                                                        switch ($event['status']) {
                                                            case 'upcoming': $badge_class = 'primary'; break;
                                                            case 'ongoing': $badge_class = 'success'; break;
                                                            case 'completed': $badge_class = 'secondary'; break;
                                                            case 'cancelled': $badge_class = 'danger'; break;
                                                        }
                                                        ?>
                                                        <span class="badge badge-modern bg-<?php echo $badge_class; ?>"><?php echo ucfirst($event['status']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown dropdown-modern">
                                                            <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="fas fa-cog me-1"></i> Actions
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item" href="view-event.php?id=<?php echo $event['event_id']; ?>">
                                                                        <i class="fas fa-eye"></i> View Details
                                                                    </a>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <h6 class="dropdown-header">Update Status:</h6>
                                                                    <form method="POST" class="px-3">
                                                                        <input type="hidden" name="action" value="update_status">
                                                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                                        <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
                                                                            <option value="upcoming" <?php echo $event['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                                            <option value="ongoing" <?php echo $event['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                                            <option value="completed" <?php echo $event['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                            <option value="cancelled" <?php echo $event['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                        </select>
                                                                    </form>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                                                        <input type="hidden" name="action" value="delete_event">
                                                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                                        <button type="submit" class="dropdown-item text-danger w-100">
                                                                            <i class="fas fa-trash"></i> Delete Event
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            </ul>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Add Event Modal -->
    <div class="modal fade modal-modern" id="addEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Add New Job Fair Event
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_event">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="event_name" class="form-label-modern">Event Name *</label>
                                <input type="text" class="form-control form-control-modern" id="event_name" name="event_name" required placeholder="Enter event name">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="event_description" class="form-label-modern">Event Description</label>
                                <textarea class="form-control form-control-modern" id="event_description" name="event_description" rows="3" placeholder="Describe your job fair event"></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="event_photo" class="form-label-modern">Event Photo/Banner</label>
                                <input type="file" class="form-control form-control-modern" id="event_photo" name="event_photo" accept="image/*">
                                <small class="form-text text-muted mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Upload an event banner or promotional photo. Max size: 5MB. JPG, PNG or GIF.
                                </small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_date" class="form-label-modern">Event Date *</label>
                                <input type="date" class="form-control form-control-modern" id="event_date" name="event_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="registration_deadline" class="form-label-modern">Registration Deadline *</label>
                                <input type="date" class="form-control form-control-modern" id="registration_deadline" name="registration_deadline" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label-modern">Start Time *</label>
                                <input type="time" class="form-control form-control-modern" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label-modern">End Time *</label>
                                <input type="time" class="form-control form-control-modern" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="location" class="form-label-modern">Location *</label>
                                <input type="text" class="form-control form-control-modern" id="location" name="location" required placeholder="Enter event location">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="max_employers" class="form-label-modern">Max Employers</label>
                                <input type="number" class="form-control form-control-modern" id="max_employers" name="max_employers" value="50" min="1">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-plus me-2"></i>Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modern View Registrations Modal -->
    <div class="modal fade modal-modern" id="viewRegistrationsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-users me-2"></i>
                        Event Registrations - <span id="modalEventTitle"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="registrationsContent">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                            <p class="mt-2">Loading registrations...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="viewFullDetailsBtn" class="btn btn-primary">View Full Details</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Photo View Modal -->
    <div class="modal fade modal-modern" id="photoViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalTitle">
                        <i class="fas fa-image me-2"></i>
                        Event Photo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="photoModalImage" src="" alt="Event Photo" class="img-fluid" style="max-height: 70vh; width: auto;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="photoDownloadBtn" href="" download class="btn btn-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Modern counter animation for statistics
        function animateCounters() {
            const counters = document.querySelectorAll('[data-count]');
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const duration = 1500;
                const step = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 16);
            });
        }

        // Trigger counter animation when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateCounters, 500);
        });

        // Enhanced photo hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const photos = document.querySelectorAll('.clickable-photo');
            photos.forEach(photo => {
                photo.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.15) rotate(2deg)';
                    this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                });
                photo.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                });
            });
        });

        // Modern loading states for modals
        function showLoadingState(element) {
            element.innerHTML = `
                <div class="text-center py-5">
                    <div class="loading-spinner"></div>
                    <p class="mt-3 text-muted">Loading...</p>
                </div>
            `;
        }

                 // Enhanced modal animations
         document.querySelectorAll('.modal').forEach(modal => {
             modal.addEventListener('show.bs.modal', function() {
                 this.style.display = 'block';
                 this.classList.add('fade');
                 setTimeout(() => {
                     this.classList.add('show');
                 }, 10);
             });
         });

         // Fix dropdown layering issues
         document.addEventListener('DOMContentLoaded', function() {
             const dropdowns = document.querySelectorAll('.dropdown-modern');
             
             dropdowns.forEach(dropdown => {
                 const button = dropdown.querySelector('.dropdown-toggle');
                 const menu = dropdown.querySelector('.dropdown-menu');
                 
                 button.addEventListener('click', function(e) {
                     e.stopPropagation();
                     
                     // Close all other dropdowns first
                     dropdowns.forEach(otherDropdown => {
                         if (otherDropdown !== dropdown) {
                             otherDropdown.classList.remove('show');
                             const otherMenu = otherDropdown.querySelector('.dropdown-menu');
                             if (otherMenu) {
                                 otherMenu.classList.remove('show');
                             }
                             // Reset z-index for other rows
                             const otherRow = otherDropdown.closest('tr');
                             if (otherRow) {
                                 otherRow.style.zIndex = '1';
                             }
                         }
                     });
                     
                     // Handle current dropdown
                     const isShowing = dropdown.classList.contains('show');
                     const currentRow = dropdown.closest('tr');
                     
                     if (!isShowing) {
                         // Show current dropdown
                         dropdown.classList.add('show');
                         menu.classList.add('show');
                         if (currentRow) {
                             currentRow.style.zIndex = '10001';
                         }
                     } else {
                         // Hide current dropdown
                         dropdown.classList.remove('show');
                         menu.classList.remove('show');
                         if (currentRow) {
                             currentRow.style.zIndex = '1';
                         }
                     }
                 });
             });
             
             // Close dropdowns when clicking outside
             document.addEventListener('click', function(e) {
                 if (!e.target.closest('.dropdown-modern')) {
                     dropdowns.forEach(dropdown => {
                         dropdown.classList.remove('show');
                         const menu = dropdown.querySelector('.dropdown-menu');
                         if (menu) {
                             menu.classList.remove('show');
                         }
                         // Reset z-index
                         const row = dropdown.closest('tr');
                         if (row) {
                             row.style.zIndex = '1';
                         }
                     });
                 }
             });
         });

        // Floating action button (scroll to top)
        const floatingBtn = document.createElement('button');
        floatingBtn.className = 'floating-action';
        floatingBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        floatingBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
        document.body.appendChild(floatingBtn);

        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                floatingBtn.style.display = 'flex';
                floatingBtn.style.alignItems = 'center';
                floatingBtn.style.justifyContent = 'center';
            } else {
                floatingBtn.style.display = 'none';
            }
        });

        // Enhanced table row animations
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table-modern tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 50}ms`;
                row.classList.add('fade-in-row');
            });
        });

        // Add fade-in animation for table rows
        const style = document.createElement('style');
        style.textContent = `
            .fade-in-row {
                opacity: 0;
                animation: fadeInRow 0.5s ease forwards;
            }
            
            @keyframes fadeInRow {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
        // Handle view registrations modal
        document.getElementById('viewRegistrationsModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var eventId = button.getAttribute('data-event-id');
            var eventName = button.getAttribute('data-event-name');
            
            document.getElementById('modalEventTitle').textContent = eventName;
            document.getElementById('viewFullDetailsBtn').href = 'view-event.php?id=' + eventId;
            
            // Load registrations via AJAX
            loadEventRegistrations(eventId);
        });
        
        function loadEventRegistrations(eventId) {
            fetch('ajax/get_event_registrations.php?event_id=' + eventId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRegistrations(data.registrations);
                    } else {
                        document.getElementById('registrationsContent').innerHTML = 
                            '<div class="alert alert-danger">Error loading registrations: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('registrationsContent').innerHTML = 
                        '<div class="alert alert-danger">Error loading registrations</div>';
                });
        }
        
        function displayRegistrations(registrations) {
            var content = '';
            
            if (registrations.length === 0) {
                content = '<div class="text-center py-4"><i class="fas fa-users fa-3x text-muted mb-3"></i><h5>No Registrations Yet</h5></div>';
            } else {
                content = '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
                                 content += '<th>Company</th><th>Contact Person</th><th>Industry</th><th>Registration Date</th><th>Status</th></tr></thead><tbody>';
                
                                 registrations.forEach(function(reg) {
                     var statusClass = reg.status === 'cancelled' ? 'danger' : 'success';
                     content += '<tr>';
                     content += '<td><strong>' + reg.company_name + '</strong></td>';
                     content += '<td>' + (reg.contact_name || 'N/A') + '<br><small class="text-muted">' + reg.employer_email + '</small></td>';
                     content += '<td>' + (reg.industry || 'N/A') + '</td>';
                     content += '<td>' + new Date(reg.registration_date).toLocaleDateString() + '</td>';
                     content += '<td><span class="badge bg-' + statusClass + '">' + reg.status.charAt(0).toUpperCase() + reg.status.slice(1) + '</span></td>';
                     content += '</tr>';
                 });
                
                content += '</tbody></table></div>';
                
                // Add summary stats
                var active = registrations.filter(r => r.status === 'registered').length;
                var cancelled = registrations.filter(r => r.status === 'cancelled').length;
                
                content = '<div class="row mb-3">' +
                    '<div class="col-md-4"><div class="text-center"><h4 class="text-primary">' + registrations.length + '</h4><small>Total</small></div></div>' +
                    '<div class="col-md-4"><div class="text-center"><h4 class="text-success">' + active + '</h4><small>Active</small></div></div>' +
                    '<div class="col-md-4"><div class="text-center"><h4 class="text-danger">' + cancelled + '</h4><small>Cancelled</small></div></div>' +
                    '</div><hr>' + content;
            }
            
            document.getElementById('registrationsContent').innerHTML = content;
        }

        // Handle photo view modal
        document.getElementById('photoViewModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var photoSrc = button.getAttribute('data-photo-src');
            var photoTitle = button.getAttribute('data-photo-title');
            
            // Update modal content
            document.getElementById('photoModalTitle').textContent = photoTitle + ' - Event Photo';
            
            // Show loading state
            var imgElement = document.getElementById('photoModalImage');
            imgElement.style.opacity = '0.5';
            imgElement.style.filter = 'blur(2px)';
            
            // Load the image
            var newImg = new Image();
            newImg.onload = function() {
                imgElement.src = photoSrc;
                imgElement.style.opacity = '1';
                imgElement.style.filter = 'none';
                imgElement.style.transition = 'opacity 0.3s ease, filter 0.3s ease';
            };
            newImg.onerror = function() {
                imgElement.alt = 'Failed to load image';
                imgElement.style.opacity = '1';
                imgElement.style.filter = 'none';
            };
            newImg.src = photoSrc;
            
            // Set download link
            document.getElementById('photoDownloadBtn').href = photoSrc;
            document.getElementById('photoDownloadBtn').download = 'event-photo-' + photoTitle.toLowerCase().replace(/[^a-z0-9]/g, '-') + '.jpg';
        });

        // Add hover effect for clickable photos
        document.addEventListener('DOMContentLoaded', function() {
            const photos = document.querySelectorAll('.clickable-photo');
            photos.forEach(photo => {
                photo.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                    this.style.transition = 'transform 0.2s ease';
                });
                photo.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html> 