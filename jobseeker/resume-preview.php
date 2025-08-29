<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

$error = null;
$resume = null;

try {
    // Get resume data
    $stmt = $conn->prepare("SELECT * FROM basic_resumes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resume) {
        $resume['personal_info'] = json_decode($resume['personal_info'], true);
        $resume['education'] = json_decode($resume['education'], true);
        $resume['experience'] = json_decode($resume['experience'], true);
        $resume['skills'] = json_decode($resume['skills'], true);
    } else {
        $error = "No resume found. Please create a resume first.";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta name="print-color-adjust" content="exact">
    <style>
        :root {
            --primary-color: #1a4f7c;
            --secondary-color: #2980b9;
            --accent-color: #3498db;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --background-light: #f8f9fa;
            --border-color: #e0e0e0;
        }

        body {
            background-color: var(--background-light);
            color: var(--text-dark);
            line-height: 1.6;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .resume-container {
            max-width: 850px;
            margin: 2rem auto;
            background: white;
            padding: 0;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .resume-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            background-color: var(--primary-color);
            color: white;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .resume-header::after {
            content: '';
            position: absolute;
            bottom: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--accent-color) 0%, transparent 70%);
            opacity: 0.1;
            border-radius: 50%;
        }

        .resume-name {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .resume-subtitle {
            font-size: 1.4rem;
            font-weight: 400;
            margin-bottom: 1rem;
            opacity: 0.9;
            font-style: italic;
        }

        .resume-contact {
            color: rgba(255,255,255,0.9);
            font-size: 1.1rem;
        }

        .resume-contact i {
            width: 24px;
            color: rgba(255,255,255,0.8);
        }

        .resume-body {
            padding: 2.5rem;
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.6rem;
            font-weight: 600;
            margin: 2rem 0 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--accent-color);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background-color: var(--primary-color);
        }

        .education-item, .experience-item {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border-radius: 8px;
            background-color: var(--background-light);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .education-item:hover, .experience-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .education-school, .experience-company {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .education-degree, .experience-position {
            color: var(--text-dark);
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
        }

        .education-year, .experience-year {
            color: var(--text-light);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .experience-description {
            margin-top: 1rem;
            color: var(--text-dark);
            line-height: 1.7;
            font-size: 1rem;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            padding: 1rem;
            background-color: var(--background-light);
            border-radius: 8px;
        }

        .skill-item {
            background: white;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 1rem;
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .skill-level {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .skill-beginner {
            background-color: #ffeaa7;
            color: #d63031;
        }

        .skill-intermediate {
            background-color: #74b9ff;
            color: white;
        }

        .skill-expert {
            background-color: #00b894;
            color: white;
        }

        .summary-content, .objective-content {
            background-color: var(--background-light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            font-size: 1.1rem;
            line-height: 1.7;
        }

        .skill-item:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }

        .profile-photo:hover {
            transform: scale(1.05);
        }

        .print-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s;
        }

        .print-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background-color: white;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .resume-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                max-width: none;
            }

            .resume-header {
                background: var(--primary-color) !important;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                forced-color-adjust: none !important;
                position: relative;
            }

            .resume-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: var(--primary-color);
                z-index: -1;
            }

            .resume-header::after {
                display: none;
            }

            .resume-body {
                padding: 2rem;
            }

            .education-item, .experience-item {
                background-color: var(--background-light) !important;
                box-shadow: none;
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid var(--border-color);
            }

            .skills-list {
                background-color: var(--background-light) !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .skill-item {
                background-color: white !important;
                border: 1px solid var(--border-color);
                color: var(--primary-color) !important;
            }

            .section-title {
                color: var(--primary-color) !important;
                break-after: avoid;
                page-break-after: avoid;
            }

            .section-title::after {
                background-color: var(--primary-color) !important;
            }

            .profile-photo {
                border: 4px solid rgba(255,255,255,0.3) !important;
                print-color-adjust: exact !important;
            }

            .print-button {
                display: none;
            }

            .resume-section {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .resume-name, .resume-contact {
                color: white !important;
            }

            .education-school, .experience-company {
                color: var(--primary-color) !important;
            }

            @page {
                margin: 0.5cm;
                size: A4;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div class="alert alert-danger m-4">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <div class="resume-container">
            <div class="resume-header-wrapper" style="background: var(--primary-color);">
                <div class="resume-header">
                    <div class="d-flex align-items-center gap-4">
                        <?php if (!empty($resume['photo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($resume['photo_path']); ?>" 
                                 alt="Profile Photo" 
                                 class="profile-photo">
                        <?php endif; ?>
                        <div>
                            <div class="resume-name"><?php echo htmlspecialchars($resume['personal_info']['full_name']); ?></div>
                            <?php if (!empty($resume['personal_info']['experience_level'])): ?>
                                <div class="resume-subtitle"><?php echo ucfirst(str_replace('-', ' ', $resume['personal_info']['experience_level'])); ?> Professional</div>
                            <?php endif; ?>
                            <div class="resume-contact">
                                <?php if ($resume['personal_info']['email']): ?>
                                    <div class="mb-2"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($resume['personal_info']['email']); ?></div>
                                <?php endif; ?>
                                <?php if ($resume['personal_info']['phone']): ?>
                                    <div class="mb-2"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($resume['personal_info']['phone']); ?></div>
                                <?php endif; ?>
                                <?php if ($resume['personal_info']['address']): ?>
                                    <div class="mb-2"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($resume['personal_info']['address']); ?></div>
                                <?php endif; ?>
                                <?php if ($resume['personal_info']['date_of_birth']): ?>
                                    <div><i class="fas fa-birthday-cake me-2"></i>Born: <?php echo date('F d, Y', strtotime($resume['personal_info']['date_of_birth'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="resume-body">
                <!-- Professional Summary -->
                <?php if (!empty($resume['personal_info']['bio'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Professional Summary</h2>
                        <div class="summary-content">
                            <p><?php echo nl2br(htmlspecialchars($resume['personal_info']['bio'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Career Objective -->
                <?php if (!empty($resume['personal_info']['objective'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Career Objective</h2>
                        <div class="objective-content">
                            <p><?php echo nl2br(htmlspecialchars($resume['personal_info']['objective'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Education -->
                <?php if (!empty($resume['education'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Education</h2>
                        <?php foreach ($resume['education'] as $edu): ?>
                            <div class="education-item">
                                <div class="education-school"><?php echo htmlspecialchars($edu['school'] ?? ''); ?></div>
                                <?php if (!empty($edu['field_of_study']) || !empty($edu['course'])): ?>
                                <div class="education-degree">Major/Course: <?php echo htmlspecialchars($edu['field_of_study'] ?? ($edu['course'] ?? '')); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($edu['degree'])): ?>
                                <div class="education-degree"><?php echo htmlspecialchars($edu['degree']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($edu['year'])): ?>
                                <div class="education-year"><?php echo htmlspecialchars($edu['year']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Experience -->
                <?php if (!empty($resume['experience'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Work Experience</h2>
                        <?php foreach ($resume['experience'] as $exp): ?>
                            <div class="experience-item">
                                <div class="experience-company"><?php echo htmlspecialchars($exp['company']); ?></div>
                                <div class="experience-position"><?php echo htmlspecialchars($exp['position']); ?></div>
                                <div class="experience-year"><?php echo htmlspecialchars($exp['year']); ?></div>
                                <?php if (!empty($exp['description'])): ?>
                                    <div class="experience-description">
                                        <?php echo nl2br(htmlspecialchars($exp['description'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Skills -->
                <?php if (!empty($resume['skills'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Skills</h2>
                        <div class="skills-list">
                            <?php foreach ($resume['skills'] as $skill_data): ?>
                                <?php 
                                $skill_name = is_array($skill_data) ? ($skill_data['skill'] ?? '') : $skill_data;
                                $skill_proficiency = is_array($skill_data) ? ($skill_data['proficiency'] ?? '') : '';
                                if (!empty($skill_name)):
                                ?>
                                <div class="skill-item-container">
                                    <span class="skill-item">
                                        <?php echo htmlspecialchars($skill_name); ?>
                                        <?php if (!empty($skill_proficiency)): ?>
                                            <span class="skill-level skill-<?php echo $skill_proficiency; ?>">
                                                <?php echo ucfirst($skill_proficiency); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <button class="btn print-button" onclick="printResume()">
            <i class="fas fa-print me-2"></i>Print Resume
        </button>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printResume() {
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    * {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                }
            `;
            document.head.appendChild(style);
            
            window.print();
        }
    </script>
</body>
</html> 