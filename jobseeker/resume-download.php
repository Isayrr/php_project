<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

try {
    // Get resume data
    $stmt = $conn->prepare("SELECT * FROM basic_resumes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resume) {
        throw new Exception("No resume found. Please create a resume first.");
    }
    
    $resume['personal_info'] = json_decode($resume['personal_info'], true);
    $resume['education'] = json_decode($resume['education'], true);
    $resume['experience'] = json_decode($resume['experience'], true);
    $resume['skills'] = json_decode($resume['skills'], true);
    
    // Use MPDF for PDF generation
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_left' => 10,
        'margin_right' => 10
    ]);
    
    // Build HTML content for PDF
    $html = '<!DOCTYPE html>
    <html>
<head>
    <meta charset="UTF-8">
    <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .header { background: #1a4f7c; color: white; padding: 20px; margin-bottom: 20px; }
            .name { font-size: 28px; font-weight: bold; margin-bottom: 5px; }
            .subtitle { font-size: 16px; font-style: italic; margin-bottom: 15px; }
            .contact { font-size: 14px; }
            .section { margin-bottom: 25px; }
            .section-title { color: #1a4f7c; font-size: 18px; font-weight: bold; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-bottom: 15px; }
            .item { margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #1a4f7c; }
            .item-title { font-weight: bold; color: #1a4f7c; font-size: 16px; }
            .item-subtitle { font-size: 14px; margin-bottom: 5px; }
            .item-year { color: #666; font-size: 12px; }
            .skills { display: flex; flex-wrap: wrap; gap: 8px; }
            .skill { background: #e3f2fd; color: #1976d2; padding: 5px 10px; border-radius: 15px; font-size: 12px; display: inline-block; margin: 2px; }
            .skill-expert { background: #c8e6c9; color: #388e3c; }
            .skill-intermediate { background: #bbdefb; color: #1976d2; }
            .skill-beginner { background: #fff3e0; color: #f57c00; }
            .summary { background: #f8f9fa; padding: 15px; border-left: 4px solid #1a4f7c; font-size: 14px; line-height: 1.6; }
    </style>
</head>
    <body>';
    
    // Header section
    $html .= '<div class="header">';
    $html .= '<div class="name">' . htmlspecialchars($resume['personal_info']['full_name']) . '</div>';
    
    if (!empty($resume['personal_info']['experience_level'])) {
        $html .= '<div class="subtitle">' . ucfirst(str_replace('-', ' ', $resume['personal_info']['experience_level'])) . ' Professional</div>';
    }
    
    $html .= '<div class="contact">';
    if ($resume['personal_info']['email']) {
        $html .= '📧 ' . htmlspecialchars($resume['personal_info']['email']) . ' &nbsp;&nbsp;';
    }
    if ($resume['personal_info']['phone']) {
        $html .= '📱 ' . htmlspecialchars($resume['personal_info']['phone']) . ' &nbsp;&nbsp;';
    }
    if ($resume['personal_info']['address']) {
        $html .= '📍 ' . htmlspecialchars($resume['personal_info']['address']);
    }
    $html .= '</div></div>';
    
    // Professional Summary
    if (!empty($resume['personal_info']['bio'])) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Professional Summary</div>';
        $html .= '<div class="summary">' . nl2br(htmlspecialchars($resume['personal_info']['bio'])) . '</div>';
        $html .= '</div>';
    }
    
    // Career Objective
    if (!empty($resume['personal_info']['objective'])) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Career Objective</div>';
        $html .= '<div class="summary">' . nl2br(htmlspecialchars($resume['personal_info']['objective'])) . '</div>';
        $html .= '</div>';
    }
    
    // Education
    if (!empty($resume['education'])) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Education</div>';
        foreach ($resume['education'] as $edu) {
            if (!empty($edu['school'])) {
                $html .= '<div class="item">';
                $html .= '<div class="item-title">' . htmlspecialchars($edu['school']) . '</div>';
                $html .= '<div class="item-subtitle">' . htmlspecialchars($edu['degree']) . '</div>';
                $html .= '<div class="item-year">' . htmlspecialchars($edu['year']) . '</div>';
                $html .= '</div>';
            }
        }
                                $html .= '</div>';
                            }
    
    // Work Experience
    if (!empty($resume['experience'])) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Work Experience</div>';
        foreach ($resume['experience'] as $exp) {
            if (!empty($exp['company'])) {
                $html .= '<div class="item">';
                $html .= '<div class="item-title">' . htmlspecialchars($exp['company']) . '</div>';
                $html .= '<div class="item-subtitle">' . htmlspecialchars($exp['position']) . '</div>';
                $html .= '<div class="item-year">' . htmlspecialchars($exp['year']) . '</div>';
                                if (!empty($exp['description'])) {
                    $html .= '<div style="margin-top: 8px;">' . nl2br(htmlspecialchars($exp['description'])) . '</div>';
                                }
                                $html .= '</div>';
                            }
                                }
                                $html .= '</div>';
                            }
    
    // Skills
    if (!empty($resume['skills'])) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Skills</div>';
        $html .= '<div class="skills">';
        foreach ($resume['skills'] as $skill_data) {
            $skill_name = is_array($skill_data) ? ($skill_data['skill'] ?? '') : $skill_data;
            $skill_proficiency = is_array($skill_data) ? ($skill_data['proficiency'] ?? '') : '';
            
            if (!empty($skill_name)) {
                $skill_class = !empty($skill_proficiency) ? 'skill-' . $skill_proficiency : '';
                $skill_text = $skill_name;
                if (!empty($skill_proficiency)) {
                    $skill_text .= ' (' . ucfirst($skill_proficiency) . ')';
                }
                $html .= '<span class="skill ' . $skill_class . '">' . htmlspecialchars($skill_text) . '</span> ';
            }
        }
        $html .= '</div></div>';
    }
    
    $html .= '</body></html>';
    
    // Generate PDF
    $mpdf->WriteHTML($html);
    
    // Set filename
    $filename = 'Resume_' . str_replace(' ', '_', $resume['personal_info']['full_name']) . '_' . date('Y-m-d') . '.pdf';
    
    // Output PDF for download
    $mpdf->Output($filename, 'D');
    
} catch (Exception $e) {
    // Redirect back with error
    header("Location: resume-builder.php?error=" . urlencode($e->getMessage()));
    exit();
} 
?> 