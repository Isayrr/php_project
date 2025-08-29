<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Job Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        html, body {
            width: 100%;
            height: auto;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif;
            position: relative;
            background-color: #fff !important;
            color: #333;
        }
        h1, h2, h3, h4, h5 { letter-spacing: .2px; }
        p { line-height: 1.75; }
        :root {
            /* Limited 3–4 color palette: teal + coral + dark + light */
            --primary: #14b8a6;          /* teal */
            --secondary: #fb7185;        /* coral */
            --dark: #1f2937;             /* slate-800 */
            --light: #ffffff;            /* white */
            --accent-gradient: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            --glass-bg: rgba(255,255,255,0.08);
            --text: var(--dark);
            --muted: #4b5563;            /* neutral gray derived */
            --surface: var(--light);
            --border: rgba(31,41,55,0.08); /* dark alpha */
        }
        .gradient-text {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent !important;
        }
        
        .main-wrapper {
            display: block;
            width: 100%;
            position: relative;
        }
        
        .page-header {
            position: relative;
            color: white;
            padding-top: 80px;
            padding-bottom: 30px;
            overflow: visible;
            height: auto;
            width: 100%;
            display: flex;
            align-items: center;
            text-align: center;
            margin-bottom: 0;
        }
        /* Floating shapes for subtle motion in header */
        .header-shapes { position: absolute; inset: 0; z-index: -1; overflow: hidden; }
        .header-shapes .shape { position: absolute; width: 140px; height: 140px; border-radius: 50%; filter: blur(30px); opacity: .45; animation: float 9s ease-in-out infinite; }
        .header-shapes .s1 { background: rgba(20,184,166,.55); top: 10%; left: 8%; animation-delay: 0s; }
        .header-shapes .s2 { background: rgba(251,113,133,.55); bottom: 12%; right: 10%; animation-delay: 1.5s; }
        .header-shapes .s3 { background: rgba(20,184,166,.35); top: 40%; right: 25%; width: 110px; height: 110px; animation-delay: .7s; }
        @keyframes float { 0%{ transform: translateY(0) } 50%{ transform: translateY(-12px) } 100%{ transform: translateY(0) } }
        
        .page-header h1 {
            color: white !important;
            background: none !important;
            background-image: none !important;
            -webkit-background-clip: initial !important;
            background-clip: initial !important;
            -webkit-text-fill-color: white !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7) !important;
        }
        
        .page-header p.lead {
            color: white !important;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.7) !important;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/background-image.jpg') center center / cover no-repeat;
            z-index: -2;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at 20% 10%, rgba(0,0,0,.55), rgba(0,0,0,.75));
            z-index: -1;
        }
        
        .page-content {
            background-color: var(--surface);
            padding: 1rem 0;
            position: relative;
            z-index: 10;
        }
        
        .logo-circle {
            width: 40px;
            height: 40px;
            background: #fff;
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
        }
        
        .navbar {
            padding: 1.2rem 0;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: blur(8px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1050;
            position: fixed;
            width: 100%;
            top: 0;
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
            text-shadow: 0 0 6px rgba(255,255,255,0.25);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            padding-right: 2rem;
            color: #ffffff !important;
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
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 2px;
        }
        
        .text-center .section-title::after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .team-member-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
            color: #fff;
            margin: 0 5px;
        }
        
        .social-icon:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-3px);
        }
        
        .mission-vision-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease;
            background: var(--surface);
        }
        .mission-vision-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 36px rgba(0,0,0,0.12);
            border-color: rgba(58,143,254,0.25);
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
        
        .mission-vision-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 30% 30%, rgba(20,184,166,.18), rgba(251,113,133,.12));
            border-radius: 50%;
            margin-bottom: 1.5rem;
            box-shadow: inset 0 0 0 1px rgba(0,0,0,0.04), 0 8px 18px rgba(0,0,0,0.06);
        }
        
        .mission-vision-icon i {
            font-size: 2rem;
        }
        
        .timeline-year {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        /* Timeline polish */
        .timeline { position: relative; }
        .timeline::before { content:''; position:absolute; left: 28px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg, rgba(0,0,0,0.06), rgba(0,0,0,0.02)); }
        .timeline-item { position: relative; }
        .timeline-item .timeline-year { box-shadow: 0 6px 16px rgba(0,0,0,0.08); border: 1px solid var(--border); }
        .timeline-item .card { transition: transform .2s ease, box-shadow .2s ease; }
        .timeline-item .card:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(0,0,0,0.12); }
        
        footer {
            position: relative;
            z-index: 10;
            margin-top: 0;
        }
        
        /* Compact layout adjustments */
        .page-content .container > .row:first-child {
            margin-top: 0;
        }
        
        .page-content .container > .row:last-child {
            margin-bottom: 0;
        }

        /* New styles for large PESO logo */
        .logo-circle-large {
            width: 150px;
            height: 150px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.7);
            overflow: hidden;
        }

        .logo-img-large {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Map Styles */
        #map {
            border-radius: 0;
            z-index: 1;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .leaflet-popup-content {
            margin: 0;
            padding: 0;
        }

        .custom-marker {
            background: transparent;
            border: none;
        }

        .landmark-marker {
            background: transparent;
            border: none;
        }

        .leaflet-control-attribution {
            font-size: 10px;
        }

        /* Responsive map adjustments */
        @media (max-width: 768px) {
            #map {
                height: 300px !important;
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
                            <a class="nav-link" href="browse-jobs.php">Browse Jobs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="about-us.php">About Us</a>
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

        <!-- Page Header -->
        <header class="page-header">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center" data-aos="fade-up">
                        <h1 class="display-4 fw-bold mb-3 gradient-text">About Us</h1>
                        <p class="lead" style="opacity:.9">Learn more about our mission, vision, and the team behind Job Portal</p>
                    </div>
                </div>
            </div>
            <div class="header-shapes">
                <span class="shape s1"></span>
                <span class="shape s2"></span>
                <span class="shape s3"></span>
            </div>
        </header>

        <!-- PESO Logo Layout -->
        <div class="container my-5">
          <div class="d-flex flex-column align-items-center justify-content-center">
            <div class="logo-circle-large mb-3">
              <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img-large">
            </div>
            <h3 class="fw-bold text-primary mb-0">Public Employment Service Office (PESO)</h3>
          </div>
        </div>

        <!-- About Us Content -->
        <section class="page-content">
            <div class="container">
                <!-- Our Story -->
                <div class="row mb-4 align-items-center">
                    <div class="col-lg-6" data-aos="fade-right">
                        <h2 class="section-title mb-4">Our Story</h2>
                        <p class="mb-4">Founded in 2023 in Talavera, Nueva Ecija, Job Portal emerged from a simple yet powerful vision: to bridge the gap between talented job seekers and forward-thinking employers in the Philippines and beyond.</p>
                        <p class="mb-4">What started as a local initiative has evolved into a comprehensive platform serving thousands of professionals across various industries. We recognized the challenges both job seekers and employers face in today's competitive market and developed innovative solutions to address these pain points.</p>
                        <p class="mb-4">Our platform combines cutting-edge technology with human insight, utilizing advanced matching algorithms, AI-powered recommendations, and personalized career guidance to ensure every connection made is meaningful and productive.</p>
                        <p>Today, we proudly serve as a trusted partner for career advancement, having facilitated countless successful placements and helped reshape the employment landscape in our region.</p>
                    </div>
                    <div class="col-lg-6" data-aos="fade-left">
                        <div class="position-relative">
                            <img src="assets/team-image/about us 1.jpg" alt="Our Story" class="img-fluid rounded-3 shadow" style="border: 1px solid rgba(0,0,0,0.06);">
                            <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill text-bg-light" style="background: var(--glass-bg); backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,0.25); color: #fff;">
                                <i class="fas fa-award me-1"></i>Since 2023
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="row mb-4">
                    <div class="col-12 text-center mb-4" data-aos="fade-up">
                        <h2 class="section-title gradient-text">Our Impact in Numbers</h2>
                        <p class="text-muted">See how we're making a difference in the job market</p>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-users fa-3x text-primary"></i>
                            </div>
                            <h3 class="fw-bold text-primary mb-2">15,000+</h3>
                            <h5 class="mb-2">Active Job Seekers</h5>
                            <p class="text-muted small">Professionals actively searching for career opportunities</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-building fa-3x text-success"></i>
                            </div>
                            <h3 class="fw-bold text-success mb-2">500+</h3>
                            <h5 class="mb-2">Partner Companies</h5>
                            <p class="text-muted small">Trusted employers from various industries</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-handshake fa-3x text-warning"></i>
                            </div>
                            <h3 class="fw-bold text-warning mb-2">8,500+</h3>
                            <h5 class="mb-2">Successful Placements</h5>
                            <p class="text-muted small">Job seekers successfully placed in their dream roles</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-star fa-3x text-info"></i>
                            </div>
                            <h3 class="fw-bold text-info mb-2">96%</h3>
                            <h5 class="mb-2">Satisfaction Rate</h5>
                            <p class="text-muted small">Of users who found jobs through our platform</p>
                        </div>
                    </div>
                </div>

                <!-- Mission & Vision -->
                <div class="row mb-4">
                    <div class="col-12 text-center mb-4" data-aos="fade-up">
                        <h2 class="section-title gradient-text">Mission & Vision</h2>
                    </div>
                    <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                        <div class="mission-vision-card card p-4 text-center">
                            <div class="mission-vision-icon">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <h3 class="h4 mb-3">Our Mission</h3>
                            <p class="text-muted mb-3">To empower individuals and organizations by creating meaningful employment connections through innovative technology, personalized service, and deep understanding of the Filipino job market.</p>
                            <ul class="list-unstyled text-start small text-muted">
                                <li><i class="fas fa-check text-success me-2"></i>Connect talent with opportunity</li>
                                <li><i class="fas fa-check text-success me-2"></i>Support career growth and development</li>
                                <li><i class="fas fa-check text-success me-2"></i>Foster inclusive employment practices</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
                        <div class="mission-vision-card card p-4 text-center">
                            <div class="mission-vision-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <h3 class="h4 mb-3">Our Vision</h3>
                            <p class="text-muted mb-3">To be the Philippines' most trusted job portal, where every Filipino professional can find meaningful work and every company can discover exceptional talent that drives success.</p>
                            <ul class="list-unstyled text-start small text-muted">
                                <li><i class="fas fa-star text-warning me-2"></i>Leading job platform in the Philippines</li>
                                <li><i class="fas fa-star text-warning me-2"></i>Zero unemployment in our served regions</li>
                                <li><i class="fas fa-star text-warning me-2"></i>Recognized excellence in employment services</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Our Values -->
                <div class="row mb-4">
                    <div class="col-12 text-center mb-4" data-aos="fade-up">
                        <h2 class="section-title gradient-text">Our Values</h2>
                    </div>
                    <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                        <div class="card h-100 border-0 shadow-sm p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-handshake fa-3x text-primary"></i>
                            </div>
                            <h4 class="h5">Integrity</h4>
                            <p class="text-muted small">We operate with honesty and transparency in all our interactions.</p>
                        </div>
                    </div>
                    <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                        <div class="card h-100 border-0 shadow-sm p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-users fa-3x text-primary"></i>
                            </div>
                            <h4 class="h5">Community</h4>
                            <p class="text-muted small">We foster a supportive environment for job seekers and employers.</p>
                        </div>
                    </div>
                    <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                        <div class="card h-100 border-0 shadow-sm p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-lightbulb fa-3x text-primary"></i>
                            </div>
                            <h4 class="h5">Innovation</h4>
                            <p class="text-muted small">We continuously improve our platform with cutting-edge technology.</p>
                        </div>
                    </div>
                    <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                        <div class="card h-100 border-0 shadow-sm p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-award fa-3x text-primary"></i>
                            </div>
                            <h4 class="h5">Excellence</h4>
                            <p class="text-muted small">We strive for the highest quality in everything we do.</p>
                        </div>
                    </div>
                </div>

                

                <!-- Company Timeline -->
                <div class="row mb-4">
                    <div class="col-12 text-center mb-4" data-aos="fade-up">
                        <h2 class="section-title gradient-text">Our Journey</h2>
                        <p class="text-muted">Key milestones in our company's evolution</p>
                    </div>
                    <div class="col-lg-8 mx-auto" data-aos="fade-up" data-aos-delay="100">
                        <div class="timeline">
                            <div class="timeline-item mb-4">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center">
                                        <div class="timeline-year bg-primary text-white p-3 rounded-circle d-inline-block">
                                            <strong>2023</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="card border-0 shadow-sm p-4">
                                            <h5 class="fw-bold">Company Founded</h5>
                                            <p class="text-muted mb-0">Launched Job Portal in Talavera, Nueva Ecija with a mission to revolutionize local employment opportunities.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline-item mb-4">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center">
                                        <div class="timeline-year bg-success text-white p-3 rounded-circle d-inline-block">
                                            <strong>2024</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="card border-0 shadow-sm p-4">
                                            <h5 class="fw-bold">Major Platform Upgrade</h5>
                                            <p class="text-muted mb-0">Introduced AI-powered job matching and expanded to serve the entire Nueva Ecija province.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline-item mb-4">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center">
                                        <div class="timeline-year bg-warning text-white p-3 rounded-circle d-inline-block">
                                            <strong>2025</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="card border-0 shadow-sm p-4">
                                            <h5 class="fw-bold">Regional Expansion</h5>
                                            <p class="text-muted mb-0">Expanded services across Central Luzon and reached 15,000+ active users milestone.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Why Choose Us -->
                <div class="row mb-4">
                    <div class="col-12 text-center mb-4" data-aos="fade-up">
                        <h2 class="section-title gradient-text">Why Choose Job Portal?</h2>
                        <p class="text-muted">What sets us apart from other job platforms</p>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="card border-0 shadow-sm h-100 p-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-shield-alt fa-3x text-success"></i>
                            </div>
                            <h5 class="fw-bold text-center mb-3">Verified Companies</h5>
                            <p class="text-muted text-center">All partner companies are thoroughly vetted to ensure legitimate opportunities and protect job seekers from scams.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="card border-0 shadow-sm h-100 p-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-headset fa-3x text-info"></i>
                            </div>
                            <h5 class="fw-bold text-center mb-3">24/7 Support</h5>
                            <p class="text-muted text-center">Our dedicated support team is available round-the-clock to assist with any questions or technical issues.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="card border-0 shadow-sm h-100 p-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-graduation-cap fa-3x text-warning"></i>
                            </div>
                            <h5 class="fw-bold text-center mb-3">Career Resources</h5>
                            <p class="text-muted text-center">Access free resume templates, interview guides, and career development resources to boost your professional growth.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                        <div class="card border-0 shadow-sm h-100 p-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-map-marked-alt fa-3x text-danger"></i>
                            </div>
                            <h5 class="fw-bold text-center mb-3">Local Expertise</h5>
                            <p class="text-muted text-center">Deep understanding of the Philippine job market with strong connections in Central Luzon region.</p>
                        </div>
                    </div>
                </div>

                <!-- Location Map Section -->
                <div class="row mb-4">
                    <div class="col-12 text-center mb-4" data-aos="fade-up">
                        <h2 class="section-title gradient-text">Visit Our Office</h2>
                        <p class="text-muted">Find us at the PESO office in Talavera Municipal Hall</p>
                    </div>
                    <div class="col-lg-8 mx-auto" data-aos="fade-up" data-aos-delay="100">
                        <div class="card border-0 shadow-lg overflow-hidden">
                            <div class="card-body p-0">
                                <div class="row g-0">
                                    <div class="col-lg-8">
                                        <div id="map" style="height: 400px; width: 100%;"></div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="p-4">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="logo-circle me-3" style="width: 50px; height: 50px;">
                                                    <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
                                                </div>
                                                <div>
                                                    <h5 class="fw-bold mb-1">PESO Talavera</h5>
                                                    <p class="text-muted mb-0 small">Public Employment Service Office</p>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-map-marker-alt me-2"></i>Address</h6>
                                                <p class="text-muted small mb-0">Talavera Municipal Hall<br>Talavera, Nueva Ecija<br>Philippines</p>
                                            </div>
                                            <div class="mb-3">
                                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-clock me-2"></i>Office Hours</h6>
                                                <p class="text-muted small mb-0">Monday - Friday<br>8:00 AM - 5:00 PM</p>
                                            </div>
                                            <div class="mb-3">
                                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-phone me-2"></i>Contact</h6>
                                                <p class="text-muted small mb-0">+63 44 XXX XXXX<br>peso.talavera@email.com</p>
                                            </div>
                                            <div class="d-grid">
                                                <a href="https://maps.google.com/?q=Talavera+Municipal+Hall+Talavera+Nueva+Ecija" target="_blank" class="btn btn-primary">
                                                    <i class="fas fa-directions me-2"></i>Get Directions
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="row mb-4">
                    <div class="col-12 text-center" data-aos="fade-up">
                        <div class="card border-0 shadow-lg p-5" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                            <h2 class="text-white fw-bold mb-3">Ready to Find Your Dream Job?</h2>
                            <p class="text-white opacity-75 mb-4 lead">Join thousands of professionals who have already found success through our platform</p>
                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <a href="browse-jobs.php" class="btn btn-light btn-lg px-4 py-3" style="border-radius:12px;">
                                    <i class="fas fa-search me-2"></i>Browse Jobs
                                </a>
                                <a href="register.php" class="btn btn-outline-light btn-lg px-4 py-3" style="border-radius:12px;">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </a>
                            </div>
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
                            <div class="logo-circle me-3">
                                <img src="assets/images/new Peso logo.jpg" alt="PESO Logo" class="logo-img">
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
                            <li class="mb-2"><a href="index.php#categories" class="text-muted text-decoration-none">Categories</a></li>
                            <li class="mb-2"><a href="index.php#companies" class="text-muted text-decoration-none">Companies</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2">
                        <h5 class="mb-4">Support</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="about-us.php" class="text-muted text-decoration-none">About Us</a></li>
                            <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Contact</a></li>
                            <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                            <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-4">
                        <h5 class="mb-4">Our Team</h5>
                        <ul class="list-unstyled mb-3 mx-auto" style="max-width: 420px;">
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
                            <li class="d-flex align-items-center mb-2">
                                <img src="assets/team-image/hannah (2).jpg" class="rounded-circle me-3" style="width:48px;height:48px;object-fit:cover;" alt="Hannah Sophia Agag">
                                <div>
                                    <div class="fw-semibold">Hannah Sophia Agag</div>
                                    <div class="text-muted small">Documentation Editor</div>
                                </div>
                            </li>
                        </ul>
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
    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

        // Initialize Map
        document.addEventListener('DOMContentLoaded', function() {
            // PESO Talavera coordinates (approximate)
            const pesoLat = 15.5833; // Talavera, Nueva Ecija latitude
            const pesoLng = 120.9167; // Talavera, Nueva Ecija longitude
            
            // Create map
            const map = L.map('map').setView([pesoLat, pesoLng], 15);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Custom marker icon
            const customIcon = L.divIcon({
                className: 'custom-marker',
                html: '<div style="background-color: #3a8ffe; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-map-marker-alt" style="color: white; font-size: 16px;"></i></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30],
                popupAnchor: [0, -30]
            });
            
            // Add marker for PESO office
            const marker = L.marker([pesoLat, pesoLng], {icon: customIcon}).addTo(map);
            
            // Add popup with information
            marker.bindPopup(`
                <div style="text-align: center; min-width: 200px;">
                    <div style="background-color: #3a8ffe; color: white; padding: 10px; border-radius: 5px 5px 0 0; margin: -10px -10px 10px -10px;">
                        <h6 style="margin: 0; font-weight: bold;">PESO Talavera</h6>
                        <small>Public Employment Service Office</small>
                    </div>
                    <p style="margin: 10px 0; font-size: 14px;">
                        <i class="fas fa-map-marker-alt text-primary"></i> Talavera Municipal Hall<br>
                        Talavera, Nueva Ecija, Philippines
                    </p>
                    <a href="https://maps.google.com/?q=Talavera+Municipal+Hall+Talavera+Nueva+Ecija" target="_blank" 
                       style="background-color: #3a8ffe; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-size: 12px;">
                        <i class="fas fa-directions"></i> Get Directions
                    </a>
                </div>
            `);
            
            // Add some nearby landmarks for context
            const landmarks = [
                {
                    name: "Talavera Municipal Hall",
                    lat: 15.5833,
                    lng: 120.9167,
                    type: "government"
                },
                {
                    name: "Talavera Public Market",
                    lat: 15.5850,
                    lng: 120.9180,
                    type: "market"
                },
                {
                    name: "Talavera Church",
                    lat: 15.5820,
                    lng: 120.9150,
                    type: "church"
                }
            ];
            
            // Add landmark markers
            landmarks.forEach(landmark => {
                if (landmark.type !== "government") { // Don't duplicate the main marker
                    const landmarkIcon = L.divIcon({
                        className: 'landmark-marker',
                        html: `<div style="background-color: #28a745; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"></div>`,
                        iconSize: [20, 20],
                        iconAnchor: [10, 20]
                    });
                    
                    L.marker([landmark.lat, landmark.lng], {icon: landmarkIcon})
                     .bindPopup(`<strong>${landmark.name}</strong>`)
                     .addTo(map);
                }
            });
        });
    </script>
</body>
</html> 