<?php
require_once 'vendor/autoload.php';

use Mpdf\Mpdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: resume-generator.php');
    exit;
}

// Sanitize and validate input data
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Get form data
$data = [
    'template' => sanitizeInput($_POST['template'] ?? ''),
    'fullName' => sanitizeInput($_POST['fullName'] ?? ''),
    'phone' => sanitizeInput($_POST['phone'] ?? ''),
    'email' => sanitizeInput($_POST['email'] ?? ''),
    'linkedin' => sanitizeInput($_POST['linkedin'] ?? ''),
    'address' => sanitizeInput($_POST['address'] ?? ''),
    'careerLevel' => sanitizeInput($_POST['careerLevel'] ?? ''),
    'summary' => sanitizeInput($_POST['summary'] ?? ''),
    'objective' => sanitizeInput($_POST['objective'] ?? ''),
    'technicalSkills' => sanitizeInput($_POST['technicalSkills'] ?? ''),
    'softSkills' => sanitizeInput($_POST['softSkills'] ?? ''),
    'languages' => sanitizeInput($_POST['languages'] ?? ''),
    'certifications' => sanitizeInput($_POST['certifications'] ?? ''),
    'projects' => sanitizeInput($_POST['projects'] ?? ''),
    'awards' => sanitizeInput($_POST['awards'] ?? ''),
    'volunteer' => sanitizeInput($_POST['volunteer'] ?? ''),
    'publications' => sanitizeInput($_POST['publications'] ?? ''),
    'format' => sanitizeInput($_POST['format'] ?? 'pdf')
];

// Process arrays
$experience = [];
if (isset($_POST['jobTitle']) && is_array($_POST['jobTitle'])) {
    for ($i = 0; $i < count($_POST['jobTitle']); $i++) {
        if (!empty($_POST['jobTitle'][$i])) {
            $experience[] = [
                'jobTitle' => sanitizeInput($_POST['jobTitle'][$i]),
                'company' => sanitizeInput($_POST['company'][$i] ?? ''),
                'location' => sanitizeInput($_POST['location'][$i] ?? ''),
                'startDate' => sanitizeInput($_POST['startDate'][$i] ?? ''),
                'endDate' => sanitizeInput($_POST['endDate'][$i] ?? ''),
                'description' => sanitizeInput($_POST['jobDescription'][$i] ?? '')
            ];
        }
    }
}

$education = [];
if (isset($_POST['degree']) && is_array($_POST['degree'])) {
    for ($i = 0; $i < count($_POST['degree']); $i++) {
        if (!empty($_POST['degree'][$i])) {
            $education[] = [
                'degree' => sanitizeInput($_POST['degree'][$i]),
                'school' => sanitizeInput($_POST['school'][$i] ?? ''),
                'gradYear' => sanitizeInput($_POST['gradYear'][$i] ?? ''),
                'honors' => sanitizeInput($_POST['honors'][$i] ?? ''),
                'details' => sanitizeInput($_POST['eduDetails'][$i] ?? '')
            ];
        }
    }
}

// Template configurations
$templates = [
    'modern' => [
        'primary_color' => '#3498db',
        'secondary_color' => '#2c3e50',
        'accent_color' => '#ecf0f1',
        'font_family' => 'Helvetica, Arial, sans-serif'
    ],
    'minimalist' => [
        'primary_color' => '#2c3e50',
        'secondary_color' => '#95a5a6',
        'accent_color' => '#bdc3c7',
        'font_family' => 'Arial, sans-serif'
    ],
    'elegant' => [
        'primary_color' => '#e74c3c',
        'secondary_color' => '#34495e',
        'accent_color' => '#f8f9fa',
        'font_family' => 'Georgia, serif'
    ],
    'creative' => [
        'primary_color' => '#9b59b6',
        'secondary_color' => '#1abc9c',
        'accent_color' => '#f4f4f4',
        'font_family' => 'Calibri, sans-serif'
    ]
];

$template = $templates[$data['template']] ?? $templates['modern'];

// Generate HTML content
function generateResumeHTML($data, $experience, $education, $template) {
    $html = '<html><head><meta charset="UTF-8"><style>';
    
    // CSS Styles
    $html .= "
        body {
            font-family: {$template['font_family']};
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid {$template['primary_color']};
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .name {
            font-size: 28pt;
            font-weight: bold;
            color: {$template['primary_color']};
            margin-bottom: 8px;
        }
        .contact-info {
            font-size: 10pt;
            color: {$template['secondary_color']};
            line-height: 1.3;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: {$template['primary_color']};
            border-bottom: 1px solid {$template['accent_color']};
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .job-entry, .edu-entry {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .job-title, .degree-title {
            font-weight: bold;
            font-size: 12pt;
            color: {$template['secondary_color']};
        }
        .company, .school {
            font-weight: bold;
            color: {$template['primary_color']};
        }
        .date-location {
            font-style: italic;
            color: #666;
            font-size: 10pt;
        }
        .description {
            margin-top: 5px;
            line-height: 1.5;
        }
        .skills-grid {
            display: table;
            width: 100%;
        }
        .skill-category {
            display: table-cell;
            vertical-align: top;
            padding-right: 20px;
            width: 33%;
        }
        .skill-category-title {
            font-weight: bold;
            color: {$template['secondary_color']};
            margin-bottom: 5px;
        }
        .two-column {
            display: table;
            width: 100%;
        }
        .left-column, .right-column {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .left-column {
            padding-right: 15px;
        }
        .right-column {
            padding-left: 15px;
        }
        ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        li {
            margin-bottom: 3px;
        }
        .summary-text {
            font-style: italic;
            color: {$template['secondary_color']};
            line-height: 1.5;
            text-align: justify;
        }
    ";
    
    $html .= '</style></head><body>';
    
    // Header
    $html .= '<div class="header">';
    $html .= '<div class="name">' . $data['fullName'] . '</div>';
    $html .= '<div class="contact-info">';
    
    $contactParts = [];
    if ($data['phone']) $contactParts[] = $data['phone'];
    if ($data['email']) $contactParts[] = $data['email'];
    if ($data['linkedin']) $contactParts[] = $data['linkedin'];
    if ($data['address']) $contactParts[] = $data['address'];
    
    $html .= implode(' • ', $contactParts);
    $html .= '</div></div>';
    
    // Professional Summary
    if ($data['summary']) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Professional Summary</div>';
        $html .= '<div class="summary-text">' . nl2br($data['summary']) . '</div>';
        $html .= '</div>';
    }
    
    // Career Objective
    if ($data['objective']) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Career Objective</div>';
        $html .= '<div class="summary-text">' . nl2br($data['objective']) . '</div>';
        $html .= '</div>';
    }
    
    // Work Experience
    if (!empty($experience)) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Professional Experience</div>';
        
        foreach ($experience as $exp) {
            $html .= '<div class="job-entry">';
            $html .= '<div class="job-title">' . $exp['jobTitle'] . '</div>';
            $html .= '<div class="company">' . $exp['company'];
            if ($exp['location']) $html .= ' • ' . $exp['location'];
            $html .= '</div>';
            
            // Format dates
            $startDate = $exp['startDate'] ? date('M Y', strtotime($exp['startDate'] . '-01')) : '';
            $endDate = $exp['endDate'] ? date('M Y', strtotime($exp['endDate'] . '-01')) : 'Present';
            if ($startDate) {
                $html .= '<div class="date-location">' . $startDate . ' - ' . $endDate . '</div>';
            }
            
            if ($exp['description']) {
                $html .= '<div class="description">' . nl2br($exp['description']) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    // Education
    if (!empty($education)) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Education</div>';
        
        foreach ($education as $edu) {
            $html .= '<div class="edu-entry">';
            $html .= '<div class="degree-title">' . $edu['degree'] . '</div>';
            $html .= '<div class="school">' . $edu['school'];
            if ($edu['gradYear']) $html .= ' • ' . $edu['gradYear'];
            $html .= '</div>';
            
            if ($edu['honors']) {
                $html .= '<div class="date-location">' . $edu['honors'] . '</div>';
            }
            
            if ($edu['details']) {
                $html .= '<div class="description">' . nl2br($edu['details']) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    // Skills Section
    if ($data['technicalSkills'] || $data['softSkills'] || $data['languages']) {
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Skills & Competencies</div>';
        $html .= '<div class="skills-grid">';
        
        if ($data['technicalSkills']) {
            $html .= '<div class="skill-category">';
            $html .= '<div class="skill-category-title">Technical Skills</div>';
            $skills = explode(',', $data['technicalSkills']);
            $html .= '<ul>';
            foreach ($skills as $skill) {
                $html .= '<li>' . trim($skill) . '</li>';
            }
            $html .= '</ul></div>';
        }
        
        if ($data['softSkills']) {
            $html .= '<div class="skill-category">';
            $html .= '<div class="skill-category-title">Soft Skills</div>';
            $skills = explode(',', $data['softSkills']);
            $html .= '<ul>';
            foreach ($skills as $skill) {
                $html .= '<li>' . trim($skill) . '</li>';
            }
            $html .= '</ul></div>';
        }
        
        if ($data['languages']) {
            $html .= '<div class="skill-category">';
            $html .= '<div class="skill-category-title">Languages</div>';
            $html .= '<div>' . nl2br($data['languages']) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div></div>';
    }
    
    // Additional sections in two columns
    $additionalSections = [];
    if ($data['certifications']) $additionalSections['Certifications'] = $data['certifications'];
    if ($data['projects']) $additionalSections['Notable Projects'] = $data['projects'];
    if ($data['awards']) $additionalSections['Awards & Achievements'] = $data['awards'];
    if ($data['volunteer']) $additionalSections['Volunteer Experience'] = $data['volunteer'];
    if ($data['publications']) $additionalSections['Publications'] = $data['publications'];
    
    if (!empty($additionalSections)) {
        $html .= '<div class="two-column">';
        $sections = array_chunk($additionalSections, ceil(count($additionalSections) / 2), true);
        
        foreach ($sections as $columnIndex => $columnSections) {
            $columnClass = $columnIndex === 0 ? 'left-column' : 'right-column';
            $html .= '<div class="' . $columnClass . '">';
            
            foreach ($columnSections as $title => $content) {
                $html .= '<div class="section">';
                $html .= '<div class="section-title">' . $title . '</div>';
                $html .= '<div class="description">' . nl2br($content) . '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</body></html>';
    return $html;
}

// Generate resume based on format
try {
    $html = generateResumeHTML($data, $experience, $education, $template);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['fullName']) . '_Resume';
    
    if ($data['format'] === 'pdf') {
        // Generate PDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 0,
            'margin_footer' => 0
        ]);
        
        $mpdf->WriteHTML($html);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        $mpdf->Output($filename . '.pdf', 'D');
        
    } else if ($data['format'] === 'word') {
        // Generate Word document
        $phpWord = new PhpWord();
        $section = $phpWord->addSection([
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
            'marginTop' => Converter::cmToTwip(2),
            'marginBottom' => Converter::cmToTwip(2)
        ]);
        
        // Header styles
        $nameStyle = ['name' => 'Calibri', 'size' => 20, 'bold' => true, 'color' => str_replace('#', '', $template['primary_color'])];
        $contactStyle = ['name' => 'Calibri', 'size' => 10, 'color' => '666666'];
        $headingStyle = ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => str_replace('#', '', $template['primary_color'])];
        $normalStyle = ['name' => 'Calibri', 'size' => 11];
        $boldStyle = ['name' => 'Calibri', 'size' => 11, 'bold' => true];
        
        // Header
        $header = $section->addTextRun(['alignment' => 'center']);
        $header->addText($data['fullName'], $nameStyle);
        $header->addTextBreak();
        
        $contactInfo = [];
        if ($data['phone']) $contactInfo[] = $data['phone'];
        if ($data['email']) $contactInfo[] = $data['email'];
        if ($data['linkedin']) $contactInfo[] = $data['linkedin'];
        if ($data['address']) $contactInfo[] = $data['address'];
        
        $contact = $section->addTextRun(['alignment' => 'center']);
        $contact->addText(implode(' • ', $contactInfo), $contactStyle);
        $section->addTextBreak(2);
        
        // Professional Summary
        if ($data['summary']) {
            $section->addText('PROFESSIONAL SUMMARY', $headingStyle);
            $section->addTextBreak();
            $section->addText($data['summary'], $normalStyle);
            $section->addTextBreak(2);
        }
        
        // Career Objective
        if ($data['objective']) {
            $section->addText('CAREER OBJECTIVE', $headingStyle);
            $section->addTextBreak();
            $section->addText($data['objective'], $normalStyle);
            $section->addTextBreak(2);
        }
        
        // Work Experience
        if (!empty($experience)) {
            $section->addText('PROFESSIONAL EXPERIENCE', $headingStyle);
            $section->addTextBreak();
            
            foreach ($experience as $exp) {
                $jobRun = $section->addTextRun();
                $jobRun->addText($exp['jobTitle'], $boldStyle);
                $section->addTextBreak();
                
                $companyRun = $section->addTextRun();
                $companyRun->addText($exp['company'], ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => str_replace('#', '', $template['primary_color'])]);
                if ($exp['location']) {
                    $companyRun->addText(' • ' . $exp['location'], $normalStyle);
                }
                $section->addTextBreak();
                
                // Dates
                $startDate = $exp['startDate'] ? date('M Y', strtotime($exp['startDate'] . '-01')) : '';
                $endDate = $exp['endDate'] ? date('M Y', strtotime($exp['endDate'] . '-01')) : 'Present';
                if ($startDate) {
                    $section->addText($startDate . ' - ' . $endDate, ['name' => 'Calibri', 'size' => 10, 'italic' => true, 'color' => '666666']);
                    $section->addTextBreak();
                }
                
                if ($exp['description']) {
                    $lines = explode("\n", $exp['description']);
                    foreach ($lines as $line) {
                        if (trim($line)) {
                            $section->addText(trim($line), $normalStyle);
                            $section->addTextBreak();
                        }
                    }
                }
                $section->addTextBreak();
            }
        }
        
        // Education
        if (!empty($education)) {
            $section->addText('EDUCATION', $headingStyle);
            $section->addTextBreak();
            
            foreach ($education as $edu) {
                $degreeRun = $section->addTextRun();
                $degreeRun->addText($edu['degree'], $boldStyle);
                $section->addTextBreak();
                
                $schoolRun = $section->addTextRun();
                $schoolRun->addText($edu['school'], ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => str_replace('#', '', $template['primary_color'])]);
                if ($edu['gradYear']) {
                    $schoolRun->addText(' • ' . $edu['gradYear'], $normalStyle);
                }
                $section->addTextBreak();
                
                if ($edu['honors']) {
                    $section->addText($edu['honors'], ['name' => 'Calibri', 'size' => 10, 'italic' => true, 'color' => '666666']);
                    $section->addTextBreak();
                }
                
                if ($edu['details']) {
                    $section->addText($edu['details'], $normalStyle);
                    $section->addTextBreak();
                }
                $section->addTextBreak();
            }
        }
        
        // Skills
        if ($data['technicalSkills'] || $data['softSkills'] || $data['languages']) {
            $section->addText('SKILLS & COMPETENCIES', $headingStyle);
            $section->addTextBreak();
            
            if ($data['technicalSkills']) {
                $skillRun = $section->addTextRun();
                $skillRun->addText('Technical Skills: ', $boldStyle);
                $skillRun->addText($data['technicalSkills'], $normalStyle);
                $section->addTextBreak();
            }
            
            if ($data['softSkills']) {
                $skillRun = $section->addTextRun();
                $skillRun->addText('Soft Skills: ', $boldStyle);
                $skillRun->addText($data['softSkills'], $normalStyle);
                $section->addTextBreak();
            }
            
            if ($data['languages']) {
                $skillRun = $section->addTextRun();
                $skillRun->addText('Languages: ', $boldStyle);
                $skillRun->addText($data['languages'], $normalStyle);
                $section->addTextBreak();
            }
            $section->addTextBreak();
        }
        
        // Additional sections
        if ($data['certifications']) {
            $section->addText('CERTIFICATIONS', $headingStyle);
            $section->addTextBreak();
            $section->addText($data['certifications'], $normalStyle);
            $section->addTextBreak(2);
        }
        
        if ($data['projects']) {
            $section->addText('NOTABLE PROJECTS', $headingStyle);
            $section->addTextBreak();
            $section->addText($data['projects'], $normalStyle);
            $section->addTextBreak(2);
        }
        
        if ($data['awards']) {
            $section->addText('AWARDS & ACHIEVEMENTS', $headingStyle);
            $section->addTextBreak();
            $section->addText($data['awards'], $normalStyle);
            $section->addTextBreak(2);
        }
        
        if ($data['volunteer']) {
            $section->addText('VOLUNTEER EXPERIENCE', $headingStyle);
            $section->addTextBreak();
            $section->addText($data['volunteer'], $normalStyle);
            $section->addTextBreak(2);
        }
        
        if ($data['publications']) {
            $section->addText('PUBLICATIONS', $headingStyle);
            $section->addTextBreak();
            $section->addText($data['publications'], $normalStyle);
            $section->addTextBreak(2);
        }
        
        // Save and output
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '.docx"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
    }
    
} catch (Exception $e) {
    echo '<html><body>';
    echo '<h1>Error Generating Resume</h1>';
    echo '<p>There was an error generating your resume: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="resume-generator.php">← Go Back</a></p>';
    echo '</body></html>';
}
?> 