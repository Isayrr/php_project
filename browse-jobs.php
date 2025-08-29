<?php
session_start();
require_once 'config/database.php';

$error = null;
$jobs = [];
$search = '';
$job_type = '';
$location = '';

try {
    // Get all available jobs with company details
    $query = "
        SELECT j.*, c.company_name, c.company_logo,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.status = 'active' AND j.approval_status = 'approved'
    ";
    $params = [];

    // Apply search filters
    if (!empty($_GET['search'])) {
        $search = $_GET['search'];
        $query .= " AND (j.title LIKE ? OR j.description LIKE ? OR c.company_name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }

    if (!empty($_GET['job_type'])) {
        $job_type = $_GET['job_type'];
        $query .= " AND j.job_type = ?";
        $params[] = $job_type;
    }

    if (!empty($_GET['location'])) {
        $location = $_GET['location'];
        $query .= " AND j.location LIKE ?";
        $params[] = "%$location%";
    }

    $query .= " ORDER BY j.posted_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $error = $e->getMessage();
}

// Get unique locations for the location filter dropdown
try {
    $stmt = $conn->query("SELECT DISTINCT location FROM jobs WHERE status = 'active' ORDER BY location");
    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {
    $locations = [];
}

// Get job counts by type for statistics
try {
    $stmt = $conn->query("SELECT job_type, COUNT(*) as count FROM jobs WHERE status = 'active' GROUP BY job_type");
    $job_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $job_counts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs</title>
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
            padding-top: 0;  /* Remove padding as we're using full-screen banner */
        }
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            border: 2px solid #f8f9fa;
            transition: transform 0.3s ease;
        }
        .company-logo:hover {
            transform: scale(1.05);
        }
        .company-logo-placeholder {
            width: 80px;
            height: 80px;
            background-color: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            border: 2px solid #f8f9fa;
            padding: 5px;
        }
        .company-name {
            font-size: 1rem;
            font-weight: 500;
            margin-top: 8px;
        }
        .job-card {
            width: 100%;
            height: 320px;
            border-radius: 20px;
            background: #ffffff;
            position: relative;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            overflow: visible;
            display: flex;
            flex-direction: column;
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }

        .job-card-details {
            color: black;
            height: 100%;
            gap: .5em;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .job-card-button {
            transform: translate(-50%, 125%);
            width: 60%;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            color: #fff;
            font-size: 0.95rem;
            padding: .55rem 1rem;
            position: absolute;
            left: 50%;
            bottom: 0;
            opacity: 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            letter-spacing: .2px;
            box-shadow: 0 8px 18px rgba(58,143,254,0.25);
        }

        .job-card:hover {
            border-color: rgba(58,143,254,0.45);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
            transform: translateY(-6px);
        }

        .job-card:hover .job-card-button {
            transform: translate(-50%, 50%);
            opacity: 1;
        }
        .job-card-button:hover {
            box-shadow: 0 12px 26px rgba(58,143,254,0.35);
        }

        .job-card::before {
            display: none;
        }
        
        .text-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .text-body {
            color: rgb(134, 134, 134);
        }
        .banner {
            position: relative;
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
            height: 100vh;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/background-image.jpg') center center / cover no-repeat fixed;
            z-index: -2;
        }
        .banner::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: -1;
        }
        .banner .container {
            position: relative;
            z-index: 1;
        }
        .stats-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
            height: 100%;
            border: none;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stats-card i {
            font-size: 2rem;
            margin-bottom: 10px;
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
            box-shadow: 0 6px 18px rgba(58, 143, 254, 0.35);
            color: white;
            font-weight: 600;
            border-radius: 12px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a8ffe 20%, #9658fe 80%);
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(58, 143, 254, 0.45);
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
            position: fixed;
            top: 0;
            width: 100%;
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
        /* Main content adjustment */
        .main-content {
            position: relative;
            z-index: 10;
            background: #fff;
            margin-top: -50px;
            border-radius: 15px 15px 0 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
            padding-top: 30px;
        }
        .text-shadow {
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.7);
        }
        .banner h1 {
            color: white !important;
            background: none !important;
            background-image: none !important;
            -webkit-background-clip: initial !important;
            background-clip: initial !important;
            -webkit-text-fill-color: white !important;
        }
        @media (max-width: 767.98px) {
            .card.job-card {
                margin-bottom: 1rem;
            }
            .banner {
                height: 90vh;
            }
        }
        
        @media (max-width: 991.98px) {
            .row-cols-md-2 > .col {
                padding: 0 0.75rem;
            }
        }
        .view-details-btn {
            border-radius: 50px;
            padding: 0.5rem 1.25rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .view-details-btn::after {
            content: '';
            position: absolute;
            width: 0;
            height: 100%;
            top: 0;
            left: 0;
            background: linear-gradient(135deg, #3a8ffe 0%, #9658fe 100%);
            transition: all 0.3s ease;
            z-index: -1;
        }
        .view-details-btn:hover {
            color: white;
            border-color: transparent;
        }
        .view-details-btn:hover::after {
            width: 100%;
        }
        .view-details-btn:hover i {
            transform: translateX(3px);
        }
        .view-details-btn i {
            transition: transform 0.3s ease;
        }
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            font-size: 0.75rem;
            min-width: 80px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50px;
        }
        .badge i {
            margin-right: 4px;
        }
        .job-type-badge {
            position: absolute;
            top: -12px;
            right: -12px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            border: 2px solid #ffffff;
            max-width: 120px;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            font-size: 0.72rem;
            padding: 0.38rem 0.7rem;
            text-transform: uppercase;
            letter-spacing: .02em;
            border-radius: 999px;
            backdrop-filter: blur(6px);
        }
        .applicants-badge {
            font-size: 0.72rem;
            padding: 0.35rem 0.6rem;
            min-width: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            background: #ffffff !important;
            border: 1px solid #e9ecef;
            border-radius: 999px;
        }
        .job-info {
            max-width: calc(100% - 65px);
        }
        /* Minimal container for company logo */
        .company-logo-container {
            width: auto;
            height: auto;
            padding: 0;
            border-radius: 12px;
            background: transparent;
            box-shadow: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .job-title {
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.3;
            color: #333;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 0;
            width: 100%;
        }
        .company-name {
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .search-alert {
            border-left: 4px solid #198754;
            background-color: rgba(25, 135, 84, 0.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .search-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(3px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-in-out;
        }
        .search-overlay::after {
            content: 'Searching...';
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }
        .highlight-results {
            animation: pulse 1.5s ease-in-out;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.5); }
            70% { box-shadow: 0 0 0 15px rgba(25, 135, 84, 0); }
            100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
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
        
        /* Minimal Auth Buttons (match index.php) */
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

        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            padding: 0;
            margin: 0;
        }

        @media (max-width: 576px) {
            .jobs-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        @media (min-width: 577px) and (max-width: 768px) {
            .jobs-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1.5rem;
            }
        }

        @media (min-width: 769px) and (max-width: 992px) {
            .jobs-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.8rem;
            }
        }

        @media (min-width: 993px) and (max-width: 1200px) {
            .jobs-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 2rem;
            }
        }

        @media (min-width: 1201px) {
            .jobs-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: 2.5rem;
            }
        }

        /* Sticky Search Bar Styles */
        .sticky-search-container {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .sticky-search-container:hover {
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        /* Fade-in Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Jobs Grid Animation */
        .jobs-grid .job-card {
            opacity: 0;
            transform: translateY(30px);
            animation: jobCardAppear 0.5s ease forwards;
        }

        .jobs-grid .job-card:nth-child(1) { animation-delay: 0.1s; }
        .jobs-grid .job-card:nth-child(2) { animation-delay: 0.2s; }
        .jobs-grid .job-card:nth-child(3) { animation-delay: 0.3s; }
        .jobs-grid .job-card:nth-child(4) { animation-delay: 0.4s; }
        .jobs-grid .job-card:nth-child(5) { animation-delay: 0.5s; }
        .jobs-grid .job-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes jobCardAppear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading State */
        .search-loading {
            position: relative;
            overflow: hidden;
        }

        .search-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8), transparent);
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            to {
                left: 100%;
            }
        }

        /* Enhanced Search Alert */
        .search-alert {
            border: none;
            border-left: 4px solid #198754;
            background: linear-gradient(135deg, #d1eddb 0%, #c3e6cb 100%);
            box-shadow: 0 2px 10px rgba(25, 135, 84, 0.1);
        }

        /* Responsive adjustments for sticky search */
        @media (max-width: 768px) {
            .sticky-search-container {
                top: 60px !important;
            }
            
            .sticky-search-container .row {
                gap: 0.5rem;
            }
            
            .sticky-search-container .form-label {
                font-size: 0.875rem;
            }
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="browse-jobs.php">Browse Jobs</a>
                    </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about-us.php">About Us</a>
                    </li>
                </ul>
                    <div class="d-flex align-items-center gap-2">
                        <div class="dropdown">
                            <a class="btn btn-outline-light dropdown-toggle" href="#" id="navbarSearchDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-search me-1"></i>Search
                            </a>
                            <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg" aria-labelledby="navbarSearchDropdown" style="min-width: 820px; max-width: 90vw;">
                                <form action="browse-jobs.php" method="GET">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-lg-5 col-md-6 col-12">
                                            <label for="nav-search" class="form-label small mb-1">Keywords</label>
                                            <input type="text" class="form-control" id="nav-search" name="search" placeholder="Job title, keyword or company" value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                        <div class="col-lg-3 col-md-3 col-6">
                                            <label for="nav-job-type" class="form-label small mb-1">Job Type</label>
                                            <select class="form-select" id="nav-job-type" name="job_type">
                                                <option value="">All Types</option>
                                                <option value="Full-time" <?php echo $job_type === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                                <option value="Part-time" <?php echo $job_type === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                                <option value="Contract" <?php echo $job_type === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                                <option value="Internship" <?php echo $job_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-3 col-md-3 col-6">
                                            <label for="nav-location" class="form-label small mb-1">Location</label>
                                            <input type="text" class="form-control" id="nav-location" name="location" placeholder="Enter location" value="<?php echo htmlspecialchars($location); ?>" list="nav-location-options">
                                            <datalist id="nav-location-options">
                                                <?php foreach($locations as $loc): ?>
                                                <option value="<?php echo htmlspecialchars($loc); ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                        <div class="col-lg-2 col-md-12 col-12 d-grid">
                                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
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

    <!-- Banner -->
    <div class="banner">
        <div class="container">
            <div class="row align-items-center justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold text-shadow mb-3">Browse Jobs</h1>
                    <p class="lead text-shadow mb-4" style="color: white !important;">Find your next career opportunity from our extensive job listings.</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <span class="badge bg-light text-dark fs-6 px-3 py-2">
                            <i class="fas fa-briefcase me-2"></i><?php echo count($jobs); ?> Active Jobs
                        </span>
                        <span class="badge bg-light text-dark fs-6 px-3 py-2">
                            <i class="fas fa-building me-2"></i><?php echo count(array_unique(array_column($jobs, 'company_name'))); ?> Companies
                        </span>
                        <span class="badge bg-light text-dark fs-6 px-3 py-2">
                            <i class="fas fa-map-marker-alt me-2"></i><?php echo count($locations); ?> Locations
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Main Content -->
        <div class="container mb-5 main-content">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Search Results Indicator -->
            <?php if (!empty($_GET['search']) || !empty($_GET['job_type']) || !empty($_GET['location'])): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert search-alert d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-search me-2"></i>
                            <strong>Search Results:</strong> Found <strong><?php echo count($jobs); ?></strong> jobs
                            <?php if (!empty($search)): ?>
                                for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php endif; ?>
                            <?php if (!empty($job_type)): ?>
                                in <strong><?php echo htmlspecialchars($job_type); ?></strong> positions
                            <?php endif; ?>
                            <?php if (!empty($location)): ?>
                                in <strong><?php echo htmlspecialchars($location); ?></strong>
                            <?php endif; ?>
                        </div>
                        <a href="browse-jobs.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Simple Stats -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="p-3 bg-light rounded">
                        <p class="mb-0 text-center">
                            <strong><?php echo count($jobs); ?></strong> jobs available across 
                            <strong><?php echo count($job_counts); ?></strong> different job types
                        </p>
                    </div>
                </div>
            </div>

            <div class="row">
            <!-- Job Listings -->
                <div class="col-12">
                <?php if (empty($jobs)): ?>
                    <div class="alert alert-info" id="noResults">
                        <i class="fas fa-info-circle"></i> No jobs found matching your criteria.
                    </div>
                <?php else: ?>
                        <?php if(isset($_GET['search']) || isset($_GET['job_type']) || isset($_GET['location'])): ?>
                        <div class="alert alert-success search-alert mb-4 fade-in" id="searchResults">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-search me-2"></i> 
                                    <strong><?php echo count($jobs); ?> job<?php echo count($jobs) !== 1 ? 's' : ''; ?> found</strong>
                                    <?php if(!empty($_GET['search']) || !empty($_GET['job_type']) || !empty($_GET['location'])): ?>
                                    - Showing results for: 
                                    <?php 
                                    $filters = [];
                                    if(!empty($_GET['search'])) $filters[] = "<span class=\"fw-bold text-primary\">" . htmlspecialchars($_GET['search']) . "</span>";
                                    if(!empty($_GET['job_type'])) $filters[] = "<span class=\"badge bg-primary\">" . htmlspecialchars($_GET['job_type']) . "</span>";
                                    if(!empty($_GET['location'])) $filters[] = "<span class=\"badge bg-info\">" . htmlspecialchars($_GET['location']) . "</span>";
                                    echo implode(' + ', $filters);
                                    ?>
                                    <?php endif; ?>
                                </div>
                                <a href="browse-jobs.php" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-times"></i> Clear All
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4><?php echo count($jobs); ?> Jobs Found</h4>
                        <div class="d-flex gap-2">
                        </div>
                    </div>
                    
                        <div class="jobs-grid mb-4">
                        <?php foreach ($jobs as $job): ?>
                                    <div class="job-card">
                                        <div class="job-card-details">
                                            <div class="position-relative mb-3">
                                                    <span class="badge bg-<?php 
                                                        if($job['job_type'] === 'Full-time') echo 'primary';
                                                        elseif($job['job_type'] === 'Part-time') echo 'success';
                                                        elseif($job['job_type'] === 'Contract') echo 'warning';
                                                        elseif($job['job_type'] === 'Internship') echo 'info';
                                                        else echo 'secondary';
                                                    ?> job-type-badge"><?php echo htmlspecialchars($job['job_type']); ?></span>
                                                </div>
                                                
                                                <div class="d-flex mb-3">
                                                    <div class="company-logo-container me-3 flex-shrink-0">
                                                        <?php if (!empty($job['company_logo']) && file_exists('uploads/company_logos/' . $job['company_logo'])): ?>
                                                            <img src="uploads/company_logos/<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($job['company_name']); ?>" 
                                                                 class="company-logo">
                                                        <?php else: ?>
                                                            <?php 
                                                            $placeholder_num = ($job['job_id'] % 4) + 1;
                                                            $placeholder = "assets/images/company{$placeholder_num}.svg";
                                                            ?>
                                                            <img src="<?php echo $placeholder; ?>" 
                                                                 alt="<?php echo htmlspecialchars($job['company_name']); ?>" 
                                                                 class="company-logo company-logo-placeholder">
                                                        <?php endif; ?>
                                            </div>
                                                    <div class="job-info overflow-hidden">
                                                    <h5 class="text-title mb-1" title="<?php echo htmlspecialchars($job['title']); ?>"><?php echo htmlspecialchars($job['title']); ?></h5>
                                                        <h6 class="mb-0 text-primary company-name"><?php echo htmlspecialchars($job['company_name']); ?></h6>
                                            </div>
                                        </div>
                                        
                                            <p class="text-body mb-2 small">
                                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($job['location']); ?>
                                        </p>
                                        
                                        <?php if (!empty($job['salary_range'])): ?>
                                            <p class="mb-2 text-success small">
                                                    <i class="fas fa-money-bill-wave me-1"></i> <?php echo htmlspecialchars($job['salary_range']); ?>
                                        </p>
                                        <?php endif; ?>

                                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                                <small class="text-body">
                                                        <i class="far fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                            </small>
                                                    <span class="badge bg-light text-dark applicants-badge">
                                                    <i class="fas fa-users me-1"></i> <?php echo $job['application_count']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <a href="job-details.php?id=<?php echo $job['job_id']; ?>" class="job-card-button">
                                                        <span>View Details</span>
                                                        <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center mb-2">
                        <div class="logo-circle me-2" style="width: 30px; height: 30px;">
                            <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                        </div>
                        <h5 class="mb-0">Job Portal</h5>
                    </div>
                    <p class="small text-muted">Connecting talented professionals with their dream careers. Your journey to success starts here.</p>
                </div>
                <div class="col-lg-2">
                    <h6 class="mb-3">Quick Links</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="index.php" class="text-white text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="browse-jobs.php" class="text-white text-decoration-none">Browse Jobs</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Categories</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Companies</a></li>
                    </ul>
                </div>
                <div class="col-lg-2">
                    <h6 class="mb-3">Support</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="about-us.php" class="text-white text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Contact</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Terms</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6 class="mb-3">Our Team</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex align-items-center mb-2">
                            <img src="assets/team-image/John Paul.jpg" class="rounded-circle me-3" style="width:48px;height:48px;object-fit:cover;" alt="John Paul Aguillon">
                            <div>
                                <div class="fw-semibold">John Paul Aguillon</div>
                                <div class="text-muted small">Backend Developer</div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="assets/team-image/alwina.png" class="rounded-circle me-3" style="width:48px;height:48px;object-fit:cover;" alt="Alwina Mae Sagurit">
                            <div>
                                <div class="fw-semibold">Alwina Mae Sagurit</div>
                                <div class="text-muted small">Frontend Developer</div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="assets/team-image/princes.jpg" class="rounded-circle me-3" style="width:48px;height:48px;object-fit:cover;" alt="Princes Oriel">
                            <div>
                                <div class="fw-semibold">Princes Oriel</div>
                                <div class="text-muted small">Documentation Editor</div>
                            </div>
                        </li>
                        <li class="d-flex align-items-center">
                            <img src="assets/team-image/hannah (2).jpg" class="rounded-circle me-3" style="width:48px;height:48px;object-fit:cover;" alt="Hannah Sophia Agag">
                            <div>
                                <div class="fw-semibold">Hannah Sophia Agag</div>
                                <div class="text-muted small">Documentation Editor</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            </div>
        </footer>

        <!-- Login Modal -->
        <div class="modal fade" id="loginModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="d-flex align-items-center">
                            <div class="logo-circle me-3" style="width: 35px; height: 35px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <h5 class="modal-title">Login to Your Account</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <div class="logo-circle mx-auto mb-3" style="width: 70px; height: 70px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <h4 class="fw-bold text-primary">Welcome Back</h4>
                            <p class="text-muted small">Please enter your credentials to continue</p>
                        </div>
                        <?php
                        if (isset($_GET['error'])) {
                            $error_message = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : 'Invalid username or password';
                            $error_class = 'danger';
                            $icon = 'exclamation-circle';
                            
                            if ($_GET['error'] == '2') {
                                $error_class = 'warning';
                                $icon = 'clock';
                            }
                            
                            echo '<div class="alert alert-' . $error_class . '">
                                <i class="fas fa-' . $icon . '"></i> ' . htmlspecialchars($error_message) . '
                            </div>';
                            unset($_SESSION['login_error']);
                        }
                        ?>
                        <form action="auth/login.php" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Modal -->
        <div class="modal fade" id="registerModal" tabindex="-1" style="backdrop-filter: blur(8px);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="d-flex align-items-center">
                            <div class="logo-circle me-2" style="width: 35px; height: 35px;">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                            </div>
                            <h5 class="modal-title">Create an Account</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-lg me-2"></i>
                                <div>
                                    <strong class="d-block">Important Notice</strong>
                                    All new accounts require administrator approval before activation. You will be notified once approved.
                                </div>
                            </div>
                        </div>
                        <form action="auth/register.php" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="reg_username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control" id="reg_username" name="username" required placeholder="Choose a username">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope text-primary"></i>
                                    </span>
                                    <input type="email" class="form-control" id="reg_email" name="email" required placeholder="Enter your email">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-primary"></i>
                                    </span>
                                    <input type="password" class="form-control" id="reg_password" name="password" required placeholder="Create a password">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_confirm_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Confirm Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-primary"></i>
                                    </span>
                                    <input type="password" class="form-control" id="reg_confirm_password" name="confirm_password" required placeholder="Confirm your password">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_role" class="form-label">
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
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p class="mb-2">
                                <i class="fas fa-sign-in-alt me-1"></i>Already have an account?
                                <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login here</a>
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
        
        // Enhanced search experience without auto-scroll
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.querySelector('#stickySearchForm');
            const searchButton = searchForm.querySelector('button[type="submit"]');
            const originalButtonText = searchButton.innerHTML;
            
            // Enhanced form submission with loading state
            searchForm.addEventListener('submit', function() {
                // Show loading spinner
                searchButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Searching...';
                searchButton.disabled = true;
                
                // Add search loading effect to form
                searchForm.classList.add('search-loading');
                
                // Allow form to continue submission
                return true;
            });
            
            // Animate search results and jobs when page loads
            const hasSearchParams = window.location.search.includes('search=') || 
                                   window.location.search.includes('job_type=') ||
                                   window.location.search.includes('location=');
            
            if (hasSearchParams) {
                // Animate search alert
                const searchAlert = document.querySelector('.search-alert');
                if (searchAlert) {
                    searchAlert.style.animationDelay = '0.2s';
                }
                
                // Animate job cards with staggered delays
                const jobCards = document.querySelectorAll('.job-card');
                jobCards.forEach((card, index) => {
                    card.style.animationDelay = `${0.1 + (index * 0.1)}s`;
                });
            }
            
            // Smooth scroll to specific sections when needed (user-initiated only)
            function smoothScrollTo(element, offset = 0) {
                if (element) {
                    const navbar = document.querySelector('.navbar');
                    const stickySearch = document.querySelector('.sticky-search-container');
                    const totalOffset = (navbar?.offsetHeight || 0) + (stickySearch?.offsetHeight || 0) + offset;
                    
                    window.scrollTo({
                        top: element.offsetTop - totalOffset,
                        behavior: 'smooth'
                    });
                }
            }
            
            // Add search suggestions functionality
            const searchInput = document.getElementById('sticky-search');
            if (searchInput) {
                let searchTimeout;
                
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();
                    
                    if (query.length >= 2) {
                        searchTimeout = setTimeout(() => {
                            // Here you could add live search suggestions
                            console.log('Searching for:', query);
                        }, 300);
                    }
                });
            }
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput?.focus();
                }
                
                // Escape to clear search
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                }
            });
            
            // Add visual feedback for interactions
            const jobCards = document.querySelectorAll('.job-card');
            jobCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-5px)';
                });
            });
        });
        
        // Advanced search toggle function
        function toggleAdvancedSearch() {
            // This could expand to show more filters like salary range, experience level, etc.
            alert('Advanced search feature coming soon! Use the current filters for now.');
        }
    </script>
</body>
</html> 