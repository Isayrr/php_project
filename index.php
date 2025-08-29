<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/dashboard.php");
    exit();
}

// Check for account status notifications to display on main page
$account_notification = null;
if (isset($_GET['user_notification']) && isset($_GET['username'])) {
    $username = $_GET['username'];
    $notification_type = $_GET['user_notification'];
    
    try {
        // Get user information and latest notification
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, u.role, u.approval_status, 
                   n.title, n.message, n.created_at
            FROM users u
            LEFT JOIN notifications n ON u.user_id = n.user_id 
                AND n.related_type IN ('user_approval', 'user_rejection') 
                AND n.is_read = 0
            WHERE u.username = ?
            ORDER BY n.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $notification_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($notification_data && $notification_data['title']) {
            $account_notification = [
                'type' => $notification_type,
                'username' => $notification_data['username'],
                'role' => $notification_data['role'],
                'approval_status' => $notification_data['approval_status'],
                'title' => $notification_data['title'],
                'message' => $notification_data['message'],
                'created_at' => $notification_data['created_at']
            ];
            
            // Mark notification as read since user has seen it
            if ($notification_data['user_id']) {
                $stmt = $conn->prepare("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE user_id = ? AND related_type IN ('user_approval', 'user_rejection') AND is_read = 0
                ");
                $stmt->execute([$notification_data['user_id']]);
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching account notification: " . $e->getMessage());
    }
} elseif (isset($_SESSION['account_status_notification'])) {
    // Alternative: use session-based notifications
    $account_notification = $_SESSION['account_status_notification'];
    unset($_SESSION['account_status_notification']);
}

// Get featured jobs for homepage
$featured_jobs = [];
try {
    $stmt = $conn->prepare("
        SELECT j.*, c.company_name, c.company_logo,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.status = 'active' AND j.approval_status = 'approved'
        ORDER BY j.posted_date DESC, application_count DESC
        LIMIT 4
    ");
    $stmt->execute();
    $featured_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching featured jobs: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Portal - Find Your Dream Job Today</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            position: relative;
            background-color: #fff !important;
            color: #333;
        }
        .container-fluid {
            padding-left: 0;
            padding-right: 0;
        }
        .section-title {
            position: relative;
            display: inline-block;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #333;
            text-shadow: none;
            font-size: 2rem;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 70px;
            height: 4px;
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            border-radius: 2px;
        }
        .text-center .section-title::after {
            left: 50%;
            transform: translateX(-50%);
        }
        .main-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }
        .hero-section {
            position: relative;
            color: white;
            padding-top: 120px;
            padding-bottom: 120px;
            overflow: visible;
            height: auto;
            min-height: 80vh;
            width: 100%;
            display: flex;
            align-items: center;
            text-align: center;
            margin-bottom: 0;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/background-image.jpg') center center / cover no-repeat fixed;
            z-index: -2;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: -1;
        }
        .hero-content {
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9);
            background-color: rgba(0, 0, 0, 0.6);
            padding: 2.5rem;
            border-radius: 15px;
            margin: 0 auto;
            max-width: 800px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 2rem;
        }
        .hero-section h1 {
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 1);
            font-size: 3.2rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            text-align: center;
            line-height: 1.2;
            color: white !important;
            background: none !important;
            background-image: none !important;
            -webkit-background-clip: initial !important;
            background-clip: initial !important;
            -webkit-text-fill-color: white !important;
        }
        .hero-section .lead {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 1);
            font-size: 1.2rem;
            margin-bottom: 2rem;
            text-align: center;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        .search-box {
            backdrop-filter: blur(10px);
            background-color: rgba(0, 0, 0, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            border-radius: 15px !important;
            padding: 1.5rem !important;
            margin: 0 auto;
            margin-bottom: 0 !important;
        }
        .search-box .form-control {
            background-color: rgba(255, 255, 255, 0.95);
            border: none;
            height: 45px;
            font-size: 1rem;
        }
        .search-box .input-group-text {
            background-color: rgba(255, 255, 255, 0.95);
            border: none;
        }
        .navbar-brand, .nav-link {
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 1);
        }
        
        /* Minimal Auth Buttons (Register/Login) */
        .codepen-button {
            display: inline-block;
            cursor: pointer;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.6rem 1.25rem;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.85);
            transition: background-color .2s ease, border-color .2s ease, transform .2s ease;
        }

        .codepen-button:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: #ffffff;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .codepen-button::before { display: none !important; content: none; }

        .codepen-button span {
            display: inline;
            padding: 0;
            background: transparent;
            border-radius: 0;
            color: inherit;
        }
        
        .category-card, .job-card, .testimonial-card, .step-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: all 0.4s ease;
            overflow: hidden;
        }
        .category-card:hover, .job-card:hover, .testimonial-card:hover, .step-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .company-logo {
            height: 60px;
            object-fit: contain;
            filter: grayscale(100%);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .company-logo:hover {
            filter: grayscale(0%);
            transform: scale(1.05);
        }
        .testimonial-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .testimonial-quote {
            color: rgba(58, 143, 254, 0.2);
            margin-bottom: 1rem;
        }
        .testimonial-card .card-text {
            font-style: italic;
            color: #555;
            line-height: 1.6;
        }
        .newsletter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            position: relative;
            overflow: hidden;
        }
        .newsletter-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/pattern.svg') center/cover;
            opacity: 0.05;
            z-index: 0;
        }
        .newsletter-section .container {
            position: relative;
            z-index: 1;
        }
        .newsletter-section .input-group {
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-radius: 50px;
            overflow: hidden;
        }
        .newsletter-section .form-control {
            border: none;
            padding-left: 1.5rem;
            height: 60px;
        }
        .newsletter-section .btn {
            border-radius: 0 50px 50px 0;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .step-card {
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .social-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        .social-icon:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-3px);
        }
        .btn {
            font-weight: 600;
            border-radius: 10px;
            padding: 0.6rem 1.5rem;
            transition: all 0.3s ease;
            text-transform: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            color: white;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a8ffe 20%, #9658fe 80%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6);
        }
        .btn-light {
            background: white;
            color: #333;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }
        .btn-light:hover {
            background: #f8f9fa;
            color: #000;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        .btn-outline-light {
            border: 2px solid white;
            background: transparent;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }
        .btn-outline-light:hover {
            background: white;
            color: #000;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        .navbar {
            padding: 1.2rem 0;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: blur(8px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1050;
        }
        .navbar.scrolled {
            background: rgba(0, 0, 0, 0.85) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        .navbar-dark .navbar-nav .nav-link {
            color: #ffffff !important;
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            transition: all 0.3s ease;
            margin: 0 0.1rem;
        }
        .navbar-dark .navbar-nav .nav-link:hover {
            color: #ffffff !important;
            transform: translateY(-2px);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            padding-right: 2rem;
            color: #ffffff !important;
        }
        
        .logo-circle {
            width: 40px;
            height: 40px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
        }
        
        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: transparent !important;
            border-radius: 50%;
            padding: 0 !important;
            box-sizing: border-box !important;
            margin: auto !important;
            transform: none !important;
            clip-path: circle(50% at 50% 50%) !important;
        }
        
        .logo-circle-large {
            width: 120px;
            height: 120px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
            overflow: hidden;
        }
        
        .logo-img-large {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: transparent !important;
            border-radius: 50%;
            padding: 0 !important;
            box-sizing: border-box !important;
            margin: auto !important;
            transform: none !important;
            clip-path: circle(50% at 50% 50%) !important;
        }
        
        /* Talavera logo specific styles */
        .talavera-circle {
            width: 40px;
            height: 40px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
            position: relative;
            padding: 0;
        }
        
        .talavera-circle-large {
            width: 120px;
            height: 120px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
            overflow: hidden;
            position: relative;
            padding: 0;
        }
        
        .talavera-logo {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            background: transparent !important;
            border-radius: 50%;
            padding: 0 !important;
            box-sizing: border-box !important;
            margin: auto !important;
            transform: none !important;
            clip-path: circle(50% at 50% 50%) !important;
        }
        
        .talavera-logo-large {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            background: transparent !important;
            border-radius: 50%;
            padding: 0 !important;
            box-sizing: border-box !important;
            margin: auto !important;
            transform: none !important;
            clip-path: circle(50% at 50% 50%) !important;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
            }
        }
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }
        .navbar-toggler:focus {
            box-shadow: none;
            outline: none;
        }
        .navbar-collapse {
            padding: 0.5rem 0;
        }
        .back-to-top {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .back-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .back-to-top:hover {
            background: linear-gradient(135deg, #9658fe 0%, #3a8ffe 100%);
            transform: translateY(-5px);
            color: white;
        }
        .job-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
        }
        .category-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(58, 143, 254, 0.1);
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .category-card:hover .category-icon {
            background: rgba(58, 143, 254, 0.2);
            transform: scale(1.1);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(58, 143, 254, 0.1);
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        @media (max-width: 992px) {
            .navbar-collapse {
                background: rgba(0, 0, 0, 0.9);
                border-radius: 0.5rem;
                padding: 1rem;
                margin-top: 1rem;
            }
            .navbar-nav {
                padding: 0.5rem 0;
            }
            .navbar-nav .nav-item {
                margin: 0.25rem 0;
            }
            .d-flex.align-items-center {
                margin-top: 0.5rem;
                padding-top: 0.5rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
        }
        @media (max-width: 768px) {
            .hero-section {
                padding-top: 100px;
                padding-bottom: 70px;
                min-height: 500px;
                width: 100%;
            }
            .hero-section::before {
                background-attachment: scroll;
            }
            .hero-section h1 {
                font-size: 2rem;
                margin-bottom: 1.2rem;
            }
            .hero-content {
                padding: 1.5rem;
                margin-top: 1.5rem;
            }
            .search-box {
                padding: 1rem !important;
            }
            .search-box .row {
                row-gap: 1rem !important;
            }
            .search-box .col-md-5, .search-box .col-md-2 {
                width: 100%;
            }
            .btn-lg {
                padding: 0.5rem 1.5rem !important;
                font-size: 1rem !important;
            }
            .d-flex.gap-3.justify-content-center {
                flex-direction: column;
                gap: 1rem !important;
            }
            .d-flex.gap-3.justify-content-center .btn {
                width: 100%;
            }
            .company-logo {
                height: 40px;
            }
            .job-card .card-body {
                padding: 1rem !important;
            }
            .badge {
                padding: 0.4em 0.8em;
                font-size: 0.75rem;
            }
            .category-icon {
                width: 60px;
                height: 60px;
            }
            .feature-icon {
                width: 70px;
                height: 70px;
            }
            section:not(.hero-section) {
                padding-left: 0;
                padding-right: 0;
            }
        }
        @media (min-width: 1200px) {
            .hero-section {
                min-height: 650px;
            }
        }
        @media screen and (orientation: portrait) {
            .hero-section::before {
                background-size: cover;
            }
        }
        @media screen and (orientation: landscape) {
            .hero-section::before {
                background-size: cover;
            }
        }
        .bg-light {
            background-color: rgba(248, 249, 250, 0.95) !important;
        }
        section:not(.hero-section) {
            position: relative;
            z-index: 5;
            box-shadow: none;
            clear: none;
            margin-bottom: 0;
            margin-top: 0;
        }
        section:not(.hero-section)::after {
            display: none;
        }
        section:first-of-type + section::before {
            display: none;
        }
        section.py-5 {
            padding-top: 3rem !important;
            padding-bottom: 3rem !important;
        }

        section.py-4 {
            padding-top: 2.5rem !important;
            padding-bottom: 2.5rem !important;
        }

        section + section {
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        /* Content sections */
        .content-section {
            position: relative;
            width: 100%;
            background-color: #fff;
            padding: 3rem 0;
            z-index: 10;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .content-section.bg-light {
            background-color: #f8f9fa;
        }
        
        /* Override previous styles */
        section.py-5,
        section.py-4 {
            padding-top: 3rem !important;
            padding-bottom: 3rem !important;
        }
        
        /* Footer style fix */
        footer {
            position: relative;
            z-index: 10;
            margin-top: 0; 
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
            <div class="container">
                <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
                    <div class="logo-circle me-2">
                        <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                    </div>
                    Job Portal
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto mb-0">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="browse-jobs.php">Browse Jobs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about-us.php">About Us</a>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center">
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-light me-2">Dashboard</a>
                            <a href="auth/logout.php" class="btn btn-outline-light">Logout</a>
                        <?php else: ?>
                            <a href="#register" class="codepen-button me-3" data-bs-toggle="modal" data-bs-target="#registerModal">
                                <span>Register</span>
                            </a>
                            <a href="#login" class="codepen-button" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <span>Login</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Account Status Notification Banner -->
        <?php if ($account_notification): ?>
        <div class="position-fixed w-100" style="top: 76px; z-index: 1030;">
            <div class="container">
                <div class="alert alert-<?php echo ($account_notification['type'] === 'approved') ? 'success' : 'warning'; ?> alert-dismissible fade show shadow-lg" 
                     style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: none; border-radius: 15px; margin-top: 10px;">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <?php if ($account_notification['type'] === 'approved'): ?>
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-1">
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($account_notification['title']); ?>
                            </h5>
                            <p class="mb-2"><?php echo htmlspecialchars($account_notification['message']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('F j, Y \a\t g:i A', strtotime($account_notification['created_at'])); ?>
                                </small>
                                <?php if ($account_notification['type'] === 'approved'): ?>
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">
                                        <i class="fas fa-sign-in-alt me-1"></i>Login Now
                                    </button>
                                <?php else: ?>
                                    <a href="check_status.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-info-circle me-1"></i>Check Status
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <style>
            .alert-dismissible .btn-close {
                position: absolute;
                top: 1rem;
                right: 1rem;
            }
            @media (max-width: 768px) {
                .position-fixed[style*="top: 76px"] {
                    position: relative !important;
                    top: 0 !important;
                }
            }
        </style>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="hero-section py-section-xl position-relative overflow-hidden">
            <!-- Animated Background Elements -->
            <div class="position-absolute top-0 start-0 w-100 h-100" style="z-index: 0;">
                <div class="floating-shapes">
                    <div class="shape shape-1"></div>
                    <div class="shape shape-2"></div>
                    <div class="shape shape-3"></div>
                    <div class="shape shape-4"></div>
                    <div class="shape shape-5"></div>
                </div>
            </div>
            
            <div class="container position-relative">
                <div class="row justify-content-center text-center">
                    <div class="col-lg-10">
                        <!-- Logo Section with Enhanced Animation -->
                        <div class="d-flex justify-content-center align-items-center mb-5 gap-4" data-aos="zoom-in" data-aos-duration="800">
                            <div class="logo-circle-large hover-glow">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img-large">
                            </div>
                            <div class="talavera-circle-large hover-glow">
                                <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo-large">
                            </div>
                        </div>
                        
                        <!-- Main Heading with Gradient Text -->
                        <h1 class="display-3 fw-bold mb-4 gradient-text" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
                            Find Your Dream Job Today
                        </h1>
                        
                        <!-- Subtitle -->
                        <p class="lead fs-4 text-muted mb-5 mx-auto" style="max-width: 600px;" data-aos="fade-up" data-aos-delay="400" data-aos-duration="1000">
                            
                        </p>
                        
                        <!-- Enhanced Search Box -->
                        <div class="search-container mb-5" data-aos="fade-up" data-aos-delay="600" data-aos-duration="1000">
                            <div class="search-box glass p-4 rounded-3 hover-glow">
                                <form action="browse-jobs.php" method="GET" class="row g-3 justify-content-center align-items-end">
                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label text-primary fw-semibold mb-2">
                                            <i class="fas fa-search me-2"></i>Job Title or Keyword
                                        </label>
                                        <input type="text" class="form-control form-control-lg" name="search" placeholder="e.g. Web Developer, Nurse, Teacher">
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label text-primary fw-semibold mb-2">
                                            <i class="fas fa-briefcase me-2"></i>Job Type
                                        </label>
                                        <select class="form-select form-select-lg" name="job_type">
                                            <option value="">All Types</option>
                                            <option value="Full-time">Full-time</option>
                                            <option value="Part-time">Part-time</option>
                                            <option value="Contract">Contract</option>
                                            <option value="Internship">Internship</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label text-primary fw-semibold mb-2">
                                            <i class="fas fa-map-marker-alt me-2"></i>Location
                                        </label>
                                        <input type="text" class="form-control form-control-lg" name="location" placeholder="e.g. Manila, Quezon City">
                                    </div>
                                    <div class="col-lg-2 col-md-12">
                                        <button type="submit" class="btn btn-primary btn-lg w-100 search-btn">
                                            <i class="fas fa-search me-2"></i>Search Jobs
                                            <div class="btn-shine"></div>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Call to Action Buttons -->
                        <div class="hero-actions" data-aos="fade-up" data-aos-delay="800" data-aos-duration="1000">
                            <a href="browse-jobs.php" class="btn btn-secondary btn-lg me-3 mb-3">
                                <i class="fas fa-briefcase me-2"></i>Browse All Jobs
                            </a>
                            <a href="#featured-jobs" class="btn btn-glass btn-lg mb-3 smooth-scroll">
                                <i class="fas fa-star me-2"></i>Featured Opportunities
                            </a>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="hero-stats mt-5" data-aos="fade-up" data-aos-delay="1000" data-aos-duration="1000">
                            <div class="row g-4 justify-content-center">
                                <div class="col-lg-3 col-md-6">
                                    <div class="stat-card text-center p-4" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.2);">
                                        <h2 class="text-white fw-bold mb-2 counter" data-target="500" style="font-size: 3rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">0</h2>
                                        <p class="text-white fw-bold mb-0" style="font-size: 1.1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Active Jobs</p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="stat-card text-center p-4" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.2);">
                                        <h2 class="text-white fw-bold mb-2 counter" data-target="1200" style="font-size: 3rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">0</h2>
                                        <p class="text-white fw-bold mb-0" style="font-size: 1.1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Registered Users</p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="stat-card text-center p-4" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.2);">
                                        <h2 class="text-white fw-bold mb-2 counter" data-target="150" style="font-size: 3rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">0</h2>
                                        <p class="text-white fw-bold mb-0" style="font-size: 1.1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Partner Companies</p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="stat-card text-center p-4" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.2);">
                                        <h2 class="text-white fw-bold mb-2 counter" data-target="95" style="font-size: 3rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">0</h2>
                                        <p class="text-white fw-bold mb-0" style="font-size: 1.1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Success Rate %</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Job Categories Section -->
        <section class="py-section" id="categories">
            <div class="container">
                <div class="row mb-5">
                    <div class="col-12 text-center">
                        <h2 class="section-title gradient-text" data-aos="fade-up">Popular Job Categories</h2>
                        <p class="lead text-muted" data-aos="fade-up" data-aos-delay="100">Explore opportunities across different industries and find your perfect match</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                        <div class="category-card card h-100 text-center p-4 hover-glow">
                            <div class="card-body">
                                <div class="category-icon">
                                    <i class="fas fa-code"></i>
                                </div>
                                <h5 class="card-title fw-bold">Technology</h5>
                                <p class="card-text text-muted">Software development, IT support, cybersecurity, and more</p>
                                <span class="badge bg-primary">250+ Jobs</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                        <div class="category-card card h-100 text-center p-4 hover-glow">
                            <div class="card-body">
                                <div class="category-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h5 class="card-title fw-bold">Business</h5>
                                <p class="card-text text-muted">Marketing, sales, consulting, and business development</p>
                                <span class="badge bg-secondary">180+ Jobs</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                        <div class="category-card card h-100 text-center p-4 hover-glow">
                            <div class="card-body">
                                <div class="category-icon">
                                    <i class="fas fa-heartbeat"></i>
                                </div>
                                <h5 class="card-title fw-bold">Healthcare</h5>
                                <p class="card-text text-muted">Medical professionals, nursing, healthcare administration</p>
                                <span class="badge bg-success">120+ Jobs</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                        <div class="category-card card h-100 text-center p-4 hover-glow">
                            <div class="card-body">
                                <div class="category-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <h5 class="card-title fw-bold">Education</h5>
                                <p class="card-text text-muted">Teaching, training, educational administration</p>
                                <span class="badge bg-warning">95+ Jobs</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-5">
                    <div class="col-12 text-center" data-aos="fade-up" data-aos-delay="500">
                        <a href="browse-jobs.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Browse All Categories
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Jobs Section -->
        <section class="py-section bg-light" id="featured-jobs">
            <div class="container">
                <div class="row mb-5">
                    <div class="col-12 text-center">
                        <h2 class="section-title gradient-text" data-aos="fade-up">Featured Jobs</h2>
                        <p class="lead text-muted" data-aos="fade-up" data-aos-delay="100">Discover hand-picked opportunities from top companies</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php if (empty($featured_jobs)): ?>
                        <div class="col-12 text-center">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No featured jobs available at the moment. Check back soon!
                            </div>
                        </div>
                    <?php else: ?>
                        <?php 
                        $delay = 100;
                        $gradients = [
                            'var(--accent-gradient)',
                            'var(--secondary-gradient)', 
                            'var(--success-gradient)',
                            'var(--warning-gradient)'
                        ];
                        $job_type_colors = [
                            'Full-time' => 'bg-success',
                            'Part-time' => 'bg-warning',
                            'Contract' => 'bg-info',
                            'Internship' => 'bg-secondary'
                        ];
                        foreach ($featured_jobs as $index => $job): 
                            $gradient = $gradients[$index % count($gradients)];
                            $badge_color = $job_type_colors[$job['job_type']] ?? 'bg-primary';
                        ?>
                        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                            <div class="job-card card h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($job['company_logo']) && file_exists('uploads/company_logos/' . $job['company_logo'])): ?>
                                                <img src="uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                                     alt="<?php echo htmlspecialchars($job['company_name']); ?>"
                                                     class="company-logo me-3"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="company-logo-placeholder me-3" style="display: none; background: <?php echo $gradient; ?>;">
                                                    <i class="fas fa-building fa-lg"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="company-logo-placeholder me-3" style="background: <?php echo $gradient; ?>;">
                                                    <i class="fas fa-building fa-lg"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                            </div>
                                        </div>
                                        <span class="badge <?php echo $badge_color; ?>"><?php echo htmlspecialchars($job['job_type']); ?></span>
                                    </div>
                                    <p class="card-text text-muted mb-3">
                                        <?php 
                                        $description = strip_tags($job['description']);
                                        echo htmlspecialchars(strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description); 
                                        ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if (!empty($job['salary_range'])): ?>
                                                <span class="text-primary fw-bold fs-5"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Salary negotiable</span>
                                            <?php endif; ?>
                                            <p class="text-muted small mb-0">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                            </p>
                                        </div>
                                        <a href="job-details.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php 
                        $delay += 100;
                        endforeach; 
                        ?>
                    <?php endif; ?>
                </div>
                <div class="row mt-5">
                    <div class="col-12 text-center" data-aos="fade-up" data-aos-delay="500">
                        <a href="browse-jobs.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-briefcase me-2"></i>View All Jobs
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Companies Section -->
        <section class="py-section" id="companies">
            <div class="container">
                <div class="row mb-5">
                    <div class="col-12 text-center">
                        <h2 class="section-title gradient-text" data-aos="fade-up">Trusted by Leading Companies</h2>
                        <p class="lead text-muted" data-aos="fade-up" data-aos-delay="100">Join thousands of professionals working with top employers</p>
                    </div>
                </div>
                <div class="row justify-content-center align-items-center g-4">
                    <div class="col-lg-2 col-md-3 col-6 text-center" data-aos="fade-up" data-aos-delay="100">
                        <div class="company-logo glass p-3 rounded-3 hover-glow" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-building fa-2x text-primary"></i>
                        </div>
                        <small class="text-muted mt-2 d-block">TechCorp</small>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6 text-center" data-aos="fade-up" data-aos-delay="200">
                        <div class="company-logo glass p-3 rounded-3 hover-glow" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-hospital fa-2x text-success"></i>
                        </div>
                        <small class="text-muted mt-2 d-block">HealthCare+</small>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6 text-center" data-aos="fade-up" data-aos-delay="300">
                        <div class="company-logo glass p-3 rounded-3 hover-glow" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-graduation-cap fa-2x text-warning"></i>
                        </div>
                        <small class="text-muted mt-2 d-block">EduTech</small>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6 text-center" data-aos="fade-up" data-aos-delay="400">
                        <div class="company-logo glass p-3 rounded-3 hover-glow" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-chart-bar fa-2x text-info"></i>
                        </div>
                        <small class="text-muted mt-2 d-block">DataCorp</small>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6 text-center" data-aos="fade-up" data-aos-delay="500">
                        <div class="company-logo glass p-3 rounded-3 hover-glow" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-rocket fa-2x text-danger"></i>
                        </div>
                        <small class="text-muted mt-2 d-block">StartupX</small>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6 text-center" data-aos="fade-up" data-aos-delay="600">
                        <div class="company-logo glass p-3 rounded-3 hover-glow" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-cogs fa-2x text-secondary"></i>
                        </div>
                        <small class="text-muted mt-2 d-block">InnovateLab</small>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="py-section bg-light">
            <div class="container">
                <div class="row mb-5">
                    <div class="col-12 text-center">
                        <h2 class="section-title gradient-text" data-aos="fade-up">How It Works</h2>
                        <p class="lead text-muted" data-aos="fade-up" data-aos-delay="100">Get started in just a few simple steps</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-3 col-md-6 text-center" data-aos="fade-up" data-aos-delay="100">
                        <div class="step-card card glass h-100 p-4">
                            <div class="card-body">
                                <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">1</div>
                                <h5 class="card-title fw-bold">Create Account</h5>
                                <p class="card-text text-muted">Sign up and create your professional profile with your skills and experience</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 text-center" data-aos="fade-up" data-aos-delay="200">
                        <div class="step-card card glass h-100 p-4">
                            <div class="card-body">
                                <div class="step-number bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">2</div>
                                <h5 class="card-title fw-bold">Search Jobs</h5>
                                <p class="card-text text-muted">Browse through thousands of job opportunities that match your preferences</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 text-center" data-aos="fade-up" data-aos-delay="300">
                        <div class="step-card card glass h-100 p-4">
                            <div class="card-body">
                                <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">3</div>
                                <h5 class="card-title fw-bold">Apply</h5>
                                <p class="card-text text-muted">Submit your application with one click using your complete profile</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 text-center" data-aos="fade-up" data-aos-delay="400">
                        <div class="step-card card glass h-100 p-4">
                            <div class="card-body">
                                <div class="step-number bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">4</div>
                                <h5 class="card-title fw-bold">Get Hired</h5>
                                <p class="card-text text-muted">Connect with employers and land your dream job with our support</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="py-section" id="testimonials">
            <div class="container">
                <div class="row mb-5">
                    <div class="col-12 text-center">
                        <h2 class="section-title gradient-text" data-aos="fade-up">What Our Users Say</h2>
                        <p class="lead text-muted" data-aos="fade-up" data-aos-delay="100">Success stories from job seekers who found their dream careers</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="testimonial-card card h-100 p-4">
                            <div class="card-body text-center">
                                <div class="testimonial-quote">
                                    <i class="fas fa-quote-left"></i>
                                </div>
                                <div class="star-rating">
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                </div>
                                <p class="card-text">"The job portal made my job search so much easier. I found my current position within just two weeks of signing up!"</p>
                                <div class="d-flex align-items-center justify-content-center mt-4">
                                    <div class="avatar me-3" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.15); border: 2px solid #f8f9fa;">
                                        <img src="assets/team-image/alwina.png" alt="Maria Santos" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div class="text-start">
                                        <h6 class="mb-0 fw-bold">Maria Santos</h6>
                                        <small class="text-muted">Software Developer</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="testimonial-card card h-100 p-4">
                            <div class="card-body text-center">
                                <div class="testimonial-quote">
                                    <i class="fas fa-quote-left"></i>
                                </div>
                                <div class="star-rating">
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star-half-alt star half-filled"></i>
                                </div>
                                <p class="card-text">"Excellent platform with great job matching. The interface is user-friendly and the support team is very helpful."</p>
                                <div class="d-flex align-items-center justify-content-center mt-4">
                                    <div class="avatar me-3" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.15); border: 2px solid #f8f9fa;">
                                        <img src="assets/team-image/hannah (2).jpg" alt="John Rivera" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div class="text-start">
                                        <h6 class="mb-0 fw-bold">John Rivera</h6>
                                        <small class="text-muted">Marketing Manager</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="testimonial-card card h-100 p-4">
                            <div class="card-body text-center">
                                <div class="testimonial-quote">
                                    <i class="fas fa-quote-left"></i>
                                </div>
                                <div class="star-rating">
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                    <i class="fas fa-star star filled"></i>
                                </div>
                                <p class="card-text">"I've recommended this platform to all my friends. It's the best job portal I've ever used. Highly recommended!"</p>
                                <div class="d-flex align-items-center justify-content-center mt-4">
                                    <div class="avatar me-3" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.15); border: 2px solid #f8f9fa;">
                                        <img src="assets/team-image/princes.jpg" alt="Anna Cruz" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div class="text-start">
                                        <h6 class="mb-0 fw-bold">Anna Cruz</h6>
                                        <small class="text-muted">Registered Nurse</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Newsletter Section -->
        <section class="py-section newsletter-section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center" data-aos="fade-up">
                        <h2 class="section-title gradient-text mb-4">Stay Updated</h2>
                        <p class="lead text-muted mb-4">Get the latest job opportunities delivered straight to your inbox. Never miss your dream job again!</p>
                        <div class="newsletter-form" data-aos="fade-up" data-aos-delay="200">
                            <form class="row g-3 justify-content-center">
                                <div class="col-md-8">
                                    <div class="input-group input-group-lg glass">
                                        <span class="input-group-text border-0">
                                            <i class="fas fa-envelope text-primary"></i>
                                        </span>
                                        <input type="email" class="form-control border-0" placeholder="Enter your email address" required>
                                        <button class="btn btn-primary px-4" type="submit">
                                            <i class="fas fa-bell me-2"></i>Subscribe
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <p class="text-muted small mt-3">
                                <i class="fas fa-shield-alt me-1"></i>We respect your privacy. Unsubscribe at any time.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-dark text-white py-5">
            <div class="container">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="logo-circle me-2">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <div class="talavera-circle me-3">
                                <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo">
                            </div>
                            <h5 class="mb-0">Job Portal</h5>
                        </div>
                        <p class="text-muted">Connecting talented professionals with their dream careers. Your journey to success starts here.</p>
                        <div class="d-flex gap-3 mt-4">
                            <a href="#" class="social-icon text-white"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-icon text-white"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-icon text-white"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="social-icon text-white"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <h5 class="mb-4">Quick Links</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="index.php" class="text-muted text-decoration-none">Home</a></li>
                            <li class="mb-2"><a href="browse-jobs.php" class="text-muted text-decoration-none">Browse Jobs</a></li>
                            <li class="mb-2"><a href="#categories" class="text-muted text-decoration-none">Categories</a></li>
                            <li class="mb-2"><a href="#companies" class="text-muted text-decoration-none">Companies</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2">
                        <h5 class="mb-4">Support</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="#" class="text-muted text-decoration-none">About Us</a></li>
                            <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Contact</a></li>
                            <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                            <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-4">
                        <h5 class="mb-4">Our Team</h5>
                        <div class="row g-3 small">
                            <div class="col-6 text-center">
                                <img src="assets/team-image/John Paul.jpg" class="rounded-circle mb-1" style="width:56px;height:56px;object-fit:cover;" alt="John Paul">
                                <div class="fw-semibold">John Paul Aguillon</div>
                                <div class="text-muted">Backend Developer</div>
                            </div>
                            <div class="col-6 text-center">
                                <img src="assets/team-image/alwina.png" class="rounded-circle mb-1" style="width:56px;height:56px;object-fit:cover;" alt="Alwina Mae">
                                <div class="fw-semibold">Alwina Mae Sagurit</div>
                                <div class="text-muted">Frontend Developer</div>
                            </div>
                            <div class="col-6 text-center">
                                <img src="assets/team-image/princes.jpg" class="rounded-circle mb-1" style="width:56px;height:56px;object-fit:cover;" alt="Princes">
                                <div class="fw-semibold">Princes Oriel</div>
                                <div class="text-muted">Documentation Editor</div>
                            </div>
                            <div class="col-6 text-center">
                                <img src="assets/team-image/hannah (2).jpg" class="rounded-circle mb-1" style="width:56px;height:56px;object-fit:cover;" alt="Hannah Sophia">
                                <div class="fw-semibold">Hannah Sophia Agag</div>
                                <div class="text-muted">Documentation Editor</div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <div class="row">
                    <div class="col-md-12 text-center">
                        <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> Job Portal. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Login Modal -->
        <div class="modal fade" id="loginModal" tabindex="-1" style="backdrop-filter: blur(8px);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-modal" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4); position: relative; overflow: hidden;">
                    <div class="modal-header border-0">
                        <div class="d-flex align-items-center">
                            <div class="logo-circle me-2" style="width: 35px; height: 35px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <div class="talavera-circle me-3" style="width: 35px; height: 35px;">
                                <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo">
                            </div>
                            <h5 class="modal-title text-white">Login to Your Account</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <div class="d-flex justify-content-center align-items-center mb-3 gap-2">
                                <div class="logo-circle" style="width: 70px; height: 70px;">
                                    <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                                </div>
                                <div class="talavera-circle" style="width: 70px; height: 70px;">
                                    <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo">
                                </div>
                            </div>
                            <h4 class="fw-bold text-white">Welcome Back</h4>
                            <p class="text-white-50 small">Please enter your credentials to continue</p>
                        </div>
                        <?php
                        if (isset($_GET['error'])) {
                            $error_message = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : 'Invalid username or password';
                            $error_class = 'danger';
                            $icon = 'exclamation-circle';
                            $title = 'Error';
                            
                            if ($_GET['error'] == '2') {
                                $error_class = 'warning';
                                $icon = 'clock';
                                $title = 'Pending Approval';
                            } elseif ($_GET['error'] == '3') {
                                $error_class = 'danger';
                                $icon = 'ban';
                                $title = 'Account Deactivated';
                            } elseif ($_GET['error'] == '4') {
                                $error_class = 'danger';
                                $icon = 'database';
                                $title = 'System Error';
                            }
                            
                            echo '<div class="alert alert-' . $error_class . '" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); border: none;">';
                            echo '<div class="d-flex align-items-center">';
                            echo '<i class="fas fa-' . $icon . ' fa-lg me-2"></i>';
                            echo '<div>';
                            echo '<strong class="d-block">' . $title . '</strong>';
                            echo htmlspecialchars($error_message);
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                            unset($_SESSION['login_error']);
                        }
                        ?>
                        <form action="auth/login.php" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label text-white">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username" required 
                                           placeholder="Enter your username">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label text-white">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-primary"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required
                                           placeholder="Enter your password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="text-end mt-1">
                                    <a href="auth/forgot_password.php" class="small text-white-50 text-decoration-none me-3">
                                        <i class="fas fa-key me-1"></i>Forgot Password?
                                    </a>
                                    <a href="check_status.php" class="small text-white-50 text-decoration-none">
                                        <i class="fas fa-search me-1"></i>Check Account Status
                                    </a>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" 
                                        style="background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%); border: none; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p class="text-white-50 mb-0">
                                <i class="fas fa-user-plus me-1"></i>Don't have an account? 
                                <a href="register.php" class="text-white">Register here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Modal -->
        <div class="modal fade" id="registerModal" tabindex="-1" style="backdrop-filter: blur(8px);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-modal" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4); position: relative; overflow: hidden;">
                    <div class="modal-header border-0">
                        <div class="d-flex align-items-center">
                            <div class="logo-circle me-2" style="width: 35px; height: 35px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <div class="talavera-circle me-3" style="width: 35px; height: 35px;">
                                <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo">
                            </div>
                            <h5 class="modal-title text-white">Create an Account</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <div class="d-flex justify-content-center align-items-center mb-3 gap-2">
                                <div class="logo-circle" style="width: 70px; height: 70px;">
                                    <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                                </div>
                                <div class="talavera-circle" style="width: 70px; height: 70px;">
                                    <img src="assets/images/talaveralogo.jpg" alt="Talavera Logo" class="talavera-logo">
                                </div>
                            </div>
                            <h4 class="fw-bold text-white">Join Us Today</h4>
                            <p class="text-white-50 small">Create your account to get started</p>
                        </div>
                        <div class="alert alert-info mb-4" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); border: none;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-lg me-2"></i>
                                <div>
                                    <strong class="d-block">Important Notice</strong>
                                    All new accounts require administrator approval before activation. You will be notified once your account has been approved.
                                </div>
                            </div>
                        </div>
                        <?php
                        if (isset($_GET['error'])) {
                            if (isset($_SESSION['register_errors']) && is_array($_SESSION['register_errors'])) {
                                echo '<div class="alert alert-danger" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); border: none;">';
                                foreach ($_SESSION['register_errors'] as $error) {
                                    echo '<div class="d-flex align-items-center mb-2">';
                                    echo '<i class="fas fa-exclamation-circle fa-lg me-2"></i>';
                                    echo '<div>' . htmlspecialchars($error) . '</div>';
                                    echo '</div>';
                                }
                                echo '</div>';
                                unset($_SESSION['register_errors']);
                            }
                        }
                        ?>
                        <form action="auth/register.php" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="reg_username" class="form-label text-white">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control" id="reg_username" name="username" required 
                                           placeholder="Choose a username">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_email" class="form-label text-white">
                                    <i class="fas fa-envelope me-2"></i>Email
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope text-primary"></i>
                                    </span>
                                    <input type="email" class="form-control" id="reg_email" name="email" required
                                           placeholder="Enter your email">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_password" class="form-label text-white">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-primary"></i>
                                    </span>
                                    <input type="password" class="form-control" id="reg_password" name="password" required
                                           placeholder="Create a password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleRegPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_confirm_password" class="form-label text-white">
                                    <i class="fas fa-lock me-2"></i>Confirm Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-primary"></i>
                                    </span>
                                    <input type="password" class="form-control" id="reg_confirm_password" name="confirm_password" required
                                           placeholder="Confirm your password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleRegConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_role" class="form-label text-white">
                                    <i class="fas fa-user-tag me-2"></i>Register as
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user-tag text-primary"></i>
                                    </span>
                                    <select class="form-select" id="reg_role" name="role" required>
                                        <option value="">Select role</option>
                                        <option value="jobseeker">Job Seeker</option>
                                        <option value="employer">Employer</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" 
                                        style="background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%); border: none; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p class="text-white-50 mb-2">
                                <i class="fas fa-sign-in-alt me-1"></i>Already have an account? 
                                <a href="#" class="text-white" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login here</a>
                            </p>
                            <p class="text-white-50 mb-0 small">
                                <i class="fas fa-search me-1"></i>Registered but waiting for approval?
                                <a href="check_status.php" class="text-white-50">Check your status</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to Top Button -->
        <a href="#" class="back-to-top">
            <i class="fas fa-chevron-up fa-lg"></i>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/modern.js"></script>
    <script>
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            once: true
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Back to top button
        const backToTopButton = document.querySelector('.back-to-top');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Newsletter form validation
        function validateNewsletter(event) {
            event.preventDefault();
            const form = event.target;
            const email = form.querySelector('#newsletterEmail');
            const button = form.querySelector('button[type="submit"]');
            const spinner = button.querySelector('.spinner-border');
            
            if (!email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                email.classList.add('is-invalid');
                return false;
            }
            
            // Show loading state
            button.disabled = true;
            spinner.classList.remove('d-none');
            
            // Simulate API call
            setTimeout(() => {
                // Reset form
                form.reset();
                email.classList.remove('is-invalid');
                button.disabled = false;
                spinner.classList.add('d-none');
                
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success mt-3';
                alert.innerHTML = '<i class="fas fa-check-circle me-2"></i>Thank you for subscribing!';
                form.parentNode.insertBefore(alert, form.nextSibling);
                
                // Remove alert after 3 seconds
                setTimeout(() => alert.remove(), 3000);
            }, 1500);
            
            return false;
        }

        // Add loading state to all forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    const spinner = button.querySelector('.spinner-border');
                    if (spinner) {
                        button.disabled = true;
                        spinner.classList.remove('d-none');
                    }
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.querySelector('#togglePassword');
            const password = document.querySelector('#password');

            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Form validation with icons
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input[required]');

            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const icon = this.previousElementSibling.querySelector('i');
                    if (this.value.length > 0) {
                        icon.classList.remove('text-primary');
                        icon.classList.add('text-success');
                    } else {
                        icon.classList.remove('text-success');
                        icon.classList.add('text-primary');
                    }
                });
            });
        });

        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Password visibility toggle for registration modal
        document.addEventListener('DOMContentLoaded', function() {
            const toggleRegPassword = document.querySelector('#toggleRegPassword');
            const toggleRegConfirmPassword = document.querySelector('#toggleRegConfirmPassword');
            const regPassword = document.querySelector('#reg_password');
            const regConfirmPassword = document.querySelector('#reg_confirm_password');

            function togglePasswordVisibility(button, input) {
                button.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }

            if (toggleRegPassword && regPassword) {
                togglePasswordVisibility(toggleRegPassword, regPassword);
            }
            if (toggleRegConfirmPassword && regConfirmPassword) {
                togglePasswordVisibility(toggleRegConfirmPassword, regConfirmPassword);
            }

            // Password match validation
            if (regPassword && regConfirmPassword) {
                regConfirmPassword.addEventListener('input', function() {
                    const icon = this.previousElementSibling.querySelector('i');
                    if (this.value === regPassword.value) {
                        icon.classList.remove('text-primary');
                        icon.classList.add('text-success');
                    } else {
                        icon.classList.remove('text-success');
                        icon.classList.add('text-danger');
                    }
                });
            }
        });
    </script>
</body>
</html>