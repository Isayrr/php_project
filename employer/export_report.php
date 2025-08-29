<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php'; // Add Composer autoload

// Import necessary classes
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

// Get parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$export_type = isset($_GET['type']) ? $_GET['type'] : 'excel';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Start output buffering and turn off error reporting to prevent corrupted output
ob_start();
error_reporting(0);

try {
    // Get employer company information
    $stmt = $conn->prepare("SELECT c.* FROM companies c WHERE c.employer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception("Company profile not found.");
    }
    
    // Get report data
    $data = getReportData($conn, $company['company_id'], $start_date, $end_date, $report_type);
    
    // Clear any output that might have been generated
    ob_end_clean();
    
    // Set appropriate headers based on export type
    $filename = "company_" . $report_type . "_report_" . date('Y-m-d') . ".";
    
    switch ($export_type) {
        case 'excel':
        default:
            $filename .= "xlsx";
            // Clear any previous output and set proper headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            exportExcel($conn, $data, $company, $start_date, $end_date, $report_type);
            break;
            
        case 'pdf':
            $filename .= "pdf";
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            exportPDF($conn, $data, $company, $start_date, $end_date, $report_type);
            break;
            
        case 'word':
            $filename .= "docx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            exportWord($conn, $data, $company, $start_date, $end_date, $report_type);
            break;
            
        case 'print':
            exportPrintable($conn, $data, $company, $start_date, $end_date, $report_type);
            break;
            
        case 'csv':
            $filename .= "csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            exportCSV($conn, $data, $company, $start_date, $end_date, $report_type);
            break;
    }
} catch(PDOException $e) {
    ob_end_clean(); // Clear buffer on error
    header("Location: reports.php?error=1");
    exit();
} catch(Exception $e) {
    ob_end_clean(); // Clear buffer on error
    header("Location: reports.php?error=1");
    exit();
}

/**
 * Get all report data needed for the exports based on report type
 */
function getReportData($conn, $company_id, $start_date, $end_date, $report_type = 'summary') {
    $data = [];

    switch ($report_type) {
        case 'jobs':
            // Get jobs data
            $stmt = $conn->prepare("SELECT j.job_id, j.title, j.location, j.job_type, j.posted_date, j.status, j.deadline_date,
                                   (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count,
                                   (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.status = 'hired') as hired_count
                                   FROM jobs j
                                   WHERE j.company_id = ? AND j.posted_date BETWEEN ? AND ?
                                   ORDER BY j.posted_date DESC");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['jobs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get job types distribution
            $stmt = $conn->prepare("SELECT job_type, COUNT(*) as count 
                                   FROM jobs 
                                   WHERE company_id = ? AND posted_date BETWEEN ? AND ?
                                   GROUP BY job_type");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['job_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'applications':
            // Get applications data
            $stmt = $conn->prepare("SELECT a.application_id, j.title as job_title, 
                                   CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
                                   a.application_date, a.status,
                                   (SELECT ROUND((COUNT(DISTINCT js.skill_id) * 100.0 / 
                                       NULLIF(COUNT(DISTINCT jsk.skill_id), 0)))
                                    FROM job_skills jsk
                                    LEFT JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                                    AND js.jobseeker_id = a.jobseeker_id
                                    WHERE jsk.job_id = j.job_id) as match_percentage
                                    FROM applications a
                                    JOIN jobs j ON a.job_id = j.job_id
                                    JOIN users u ON a.jobseeker_id = u.user_id
                                    JOIN user_profiles up ON u.user_id = up.user_id
                                    WHERE j.company_id = ? AND a.application_date BETWEEN ? AND ?
                                    ORDER BY a.application_date DESC");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['applications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get status distribution
            $stmt = $conn->prepare("SELECT a.status, COUNT(*) as count 
                                   FROM applications a
                                   JOIN jobs j ON a.job_id = j.job_id
                                   WHERE j.company_id = ? AND a.application_date BETWEEN ? AND ?
                                   GROUP BY a.status");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['status_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get applications by job
            $stmt = $conn->prepare("SELECT j.title, COUNT(*) as application_count 
                                   FROM applications a
                                   JOIN jobs j ON a.job_id = j.job_id
                                   WHERE j.company_id = ? AND a.application_date BETWEEN ? AND ?
                                   GROUP BY j.job_id, j.title
                                   ORDER BY application_count DESC");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['applications_by_job'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'applicants':
            // Get applicants data
            $stmt = $conn->prepare("SELECT DISTINCT u.user_id as applicant_id, 
                                   CONCAT(up.first_name, ' ', up.last_name) as full_name,
                                   u.email,
                                   (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') 
                                    FROM jobseeker_skills js 
                                    JOIN skills s ON js.skill_id = s.skill_id 
                                    WHERE js.jobseeker_id = u.user_id) as skills,
                                   (SELECT COUNT(*) FROM applications a 
                                    JOIN jobs j ON a.job_id = j.job_id 
                                    WHERE a.jobseeker_id = u.user_id AND j.company_id = ? AND
                                    a.application_date BETWEEN ? AND ?) as application_count
                                   FROM users u
                                   JOIN user_profiles up ON u.user_id = up.user_id
                                   JOIN applications a ON u.user_id = a.jobseeker_id
                                   JOIN jobs j ON a.job_id = j.job_id
                                   WHERE u.role = 'jobseeker' AND j.company_id = ? 
                                   AND a.application_date BETWEEN ? AND ?
                                   ORDER BY application_count DESC");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59', $company_id, $start_date, $end_date . ' 23:59:59']);
            $data['applicants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get skills distribution
            $stmt = $conn->prepare("SELECT s.skill_name, COUNT(DISTINCT a.jobseeker_id) as applicant_count
                                   FROM jobseeker_skills js
                                   JOIN skills s ON js.skill_id = s.skill_id
                                   JOIN applications a ON js.jobseeker_id = a.jobseeker_id
                                   JOIN jobs j ON a.job_id = j.job_id
                                   WHERE j.company_id = ? AND a.application_date BETWEEN ? AND ?
                                   GROUP BY s.skill_id
                                   ORDER BY applicant_count DESC");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['skills_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'recruitment':
            // Get recruitment progress data
            $stmt = $conn->prepare("SELECT j.job_id, j.title as job_title, 
                                   COUNT(a.application_id) as total_applicants,
                                   SUM(CASE WHEN a.status IN ('reviewed', 'shortlisted', 'hired', 'rejected') THEN 1 ELSE 0 END) as reviewed,
                                   SUM(CASE WHEN a.status IN ('shortlisted', 'hired', 'rejected') THEN 1 ELSE 0 END) as shortlisted,
                                   SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired,
                                   SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected
                                   FROM jobs j
                                   LEFT JOIN applications a ON j.job_id = a.job_id
                                   WHERE j.company_id = ? AND j.posted_date BETWEEN ? AND ?
                                   GROUP BY j.job_id, j.title
                                   ORDER BY total_applicants DESC");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['recruitment_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'company':
            // Get company profile
            $stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $data['company_profile'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get company stats
            $stmt = $conn->prepare("SELECT 
                                   (SELECT COUNT(*) FROM jobs j WHERE j.company_id = ? AND j.posted_date BETWEEN ? AND ?) as jobs_posted,
                                   (SELECT COUNT(*) FROM jobs j WHERE j.company_id = ? AND j.status = 'active' AND j.posted_date BETWEEN ? AND ?) as active_jobs,
                                   (SELECT COUNT(*) FROM applications a 
                                    JOIN jobs j ON a.job_id = j.job_id 
                                    WHERE j.company_id = ? AND a.application_date BETWEEN ? AND ?) as applications_received,
                                   (SELECT COUNT(*) FROM applications a 
                                    JOIN jobs j ON a.job_id = j.job_id 
                                    WHERE j.company_id = ? AND a.status = 'hired' AND a.application_date BETWEEN ? AND ?) as candidates_hired");
            $stmt->execute([
                $company_id, $start_date, $end_date . ' 23:59:59',
                $company_id, $start_date, $end_date . ' 23:59:59',
                $company_id, $start_date, $end_date . ' 23:59:59',
                $company_id, $start_date, $end_date . ' 23:59:59'
            ]);
            $data['company_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get monthly trends
            $stmt = $conn->prepare("SELECT 
                                   DATE_FORMAT(posted_date, '%Y-%m') as month,
                                   COUNT(*) as job_count
                                   FROM jobs 
                                   WHERE company_id = ? AND posted_date BETWEEN ? AND ?
                                   GROUP BY DATE_FORMAT(posted_date, '%Y-%m')
                                   ORDER BY month");
            $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
            $data['monthly_jobs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        default: // 'summary'
            // Get default summary data
    // Application statistics
    $stmt = $conn->prepare("SELECT 
                           COUNT(*) as total_applications,
                           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                           SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
                           SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
                           SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                           SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired
                           FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
                           WHERE j.company_id = ? AND a.application_date BETWEEN ? AND ?");
    $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
    $data['application_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Job type distribution
    $stmt = $conn->prepare("SELECT 
                SUM(CASE WHEN job_type = 'full-time' THEN 1 ELSE 0 END) as full_time,
                SUM(CASE WHEN job_type = 'part-time' THEN 1 ELSE 0 END) as part_time,
                SUM(CASE WHEN job_type = 'contract' THEN 1 ELSE 0 END) as contract,
                SUM(CASE WHEN job_type = 'internship' THEN 1 ELSE 0 END) as internship
        FROM jobs 
        WHERE company_id = ? AND posted_date BETWEEN ? AND ?");
    $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
    $data['job_type_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Job posting trends
    $stmt = $conn->prepare("SELECT DATE(posted_date) as date, COUNT(*) as count 
        FROM jobs 
        WHERE company_id = ? AND posted_date BETWEEN ? AND ?
        GROUP BY DATE(posted_date)
        ORDER BY date ASC");
    $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
    $data['job_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Job performance metrics
    $stmt = $conn->prepare("SELECT j.job_id, j.title, 
                           COUNT(a.application_id) as total_applications,
                                   SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hires
                           FROM jobs j 
                           LEFT JOIN applications a ON j.job_id = a.job_id 
                           WHERE j.company_id = ? AND j.posted_date BETWEEN ? AND ? 
        GROUP BY j.job_id, j.title
                           ORDER BY total_applications DESC");
    $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
    $data['job_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top skills from applications
    $stmt = $conn->prepare("SELECT s.skill_name, COUNT(js.skill_id) as count
                           FROM applications a 
                           JOIN jobs j ON a.job_id = j.job_id 
        JOIN jobseeker_skills js ON a.jobseeker_id = js.jobseeker_id
        JOIN skills s ON js.skill_id = s.skill_id
        WHERE j.company_id = ? AND a.application_date BETWEEN ? AND ?
        GROUP BY s.skill_id, s.skill_name
        ORDER BY count DESC
        LIMIT 10");
    $stmt->execute([$company_id, $start_date, $end_date . ' 23:59:59']);
    $data['top_skills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
    
    return $data;
}

/**
 * Export report data as CSV
 */
function exportCSV($conn, $data, $company, $start_date, $end_date, $report_type = 'summary') {
    // Open output stream
    $output = fopen('php://output', 'w');

    // Write report header
    fputcsv($output, ['Company Report: ' . $company['company_name']]);
    fputcsv($output, ['Report Type: ' . ucfirst($report_type)]);
    fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, []);

    // Export based on report type
    switch ($report_type) {
        case 'jobs':
            // Job listing report export
            fputcsv($output, ['Job Listings Report']);
            fputcsv($output, []);
            
            if (!empty($data['jobs'])) {
                // Jobs table
                fputcsv($output, ['Job ID', 'Title', 'Location', 'Type', 'Posted Date', 'Status', 'Deadline', 'Applicants', 'Hires']);
                foreach ($data['jobs'] as $job) {
                    fputcsv($output, [
                        $job['job_id'],
                        $job['title'],
                        $job['location'],
                        ucfirst(str_replace('-', ' ', $job['job_type'])),
                        $job['posted_date'],
                        ucfirst($job['status']),
                        $job['deadline_date'],
                        $job['applicant_count'],
                        $job['hired_count']
                    ]);
                }
                fputcsv($output, []);
            }
            
            if (!empty($data['job_types'])) {
                // Job type distribution
                fputcsv($output, ['Job Type Distribution']);
                fputcsv($output, ['Type', 'Count']);
                foreach ($data['job_types'] as $type) {
                    fputcsv($output, [
                        ucfirst(str_replace('-', ' ', $type['job_type'])),
                        $type['count']
                    ]);
                }
            }
            break;
            
        case 'applications':
            fputcsv($output, ['Applications Report']);
            fputcsv($output, []);
            
            if (!empty($data['applications'])) {
                fputcsv($output, ['ID', 'Job Title', 'Applicant Name', 'Application Date', 'Status', 'Match %']);
                foreach ($data['applications'] as $app) {
                    fputcsv($output, [
                        $app['application_id'], 
                        $app['job_title'],
                        $app['applicant_name'],
                        $app['application_date'],
                        ucfirst($app['status']),
                        $app['match_percentage'] . '%'
                    ]);
                }
                fputcsv($output, []);
            }
            
            if (!empty($data['status_distribution'])) {
                fputcsv($output, ['Status Distribution']);
                fputcsv($output, ['Status', 'Count']);
                foreach ($data['status_distribution'] as $status) {
                    fputcsv($output, [ucfirst($status['status']), $status['count']]);
                }
                fputcsv($output, []);
            }
            
            if (!empty($data['applications_by_job'])) {
                fputcsv($output, ['Applications by Job']);
                fputcsv($output, ['Job Title', 'Application Count']);
                foreach ($data['applications_by_job'] as $job) {
                    fputcsv($output, [$job['title'], $job['application_count']]);
                }
            }
            break;
            
        case 'applicants':
            fputcsv($output, ['Applicants Report']);
            fputcsv($output, []);
            
            if (!empty($data['applicants'])) {
                fputcsv($output, ['ID', 'Full Name', 'Email', 'Skills', 'Application Count']);
                foreach ($data['applicants'] as $applicant) {
                    fputcsv($output, [
                        $applicant['applicant_id'],
                        $applicant['full_name'],
                        $applicant['email'],
                        $applicant['skills'] ?: 'No skills listed',
                        $applicant['application_count']
                    ]);
                }
                fputcsv($output, []);
            }
            
            if (!empty($data['skills_distribution'])) {
                fputcsv($output, ['Skills Distribution']);
                fputcsv($output, ['Skill Name', 'Applicant Count']);
                foreach ($data['skills_distribution'] as $skill) {
                    fputcsv($output, [$skill['skill_name'], $skill['applicant_count']]);
                }
            }
            break;
            
        case 'recruitment':
            fputcsv($output, ['Recruitment Progress Report']);
            fputcsv($output, []);
            
            if (!empty($data['recruitment_stats'])) {
                fputcsv($output, ['Job Title', 'Total Applicants', 'Reviewed', 'Shortlisted', 'Hired', 'Rejected']);
                foreach ($data['recruitment_stats'] as $stat) {
                    fputcsv($output, [
                        $stat['job_title'],
                        $stat['total_applicants'],
                        $stat['reviewed'],
                        $stat['shortlisted'],
                        $stat['hired'],
                        $stat['rejected']
                    ]);
                }
            }
            break;
        
        default: // 'summary'
            if (isset($data['application_stats']) && is_array($data['application_stats'])) {
    // Application Statistics
    fputcsv($output, ['Application Statistics']);
                fputcsv($output, ['Total Applications', $data['application_stats']['total_applications'] ?? 0]);
                fputcsv($output, ['Pending', $data['application_stats']['pending'] ?? 0]);
                fputcsv($output, ['Reviewed', $data['application_stats']['reviewed'] ?? 0]);
                fputcsv($output, ['Shortlisted', $data['application_stats']['shortlisted'] ?? 0]);
                fputcsv($output, ['Rejected', $data['application_stats']['rejected'] ?? 0]);
                fputcsv($output, ['Hired', $data['application_stats']['hired'] ?? 0]);
    fputcsv($output, []);
            }

            if (isset($data['job_type_stats']) && is_array($data['job_type_stats'])) {
    // Job Type Distribution
    fputcsv($output, ['Job Type Distribution']);
                fputcsv($output, ['Full-time', $data['job_type_stats']['full_time'] ?? 0]);
                fputcsv($output, ['Part-time', $data['job_type_stats']['part_time'] ?? 0]);
                fputcsv($output, ['Contract', $data['job_type_stats']['contract'] ?? 0]);
                fputcsv($output, ['Internship', $data['job_type_stats']['internship'] ?? 0]);
    fputcsv($output, []);
            }

            if (isset($data['job_trends']) && is_array($data['job_trends']) && !empty($data['job_trends'])) {
    // Job Posting Trends
    fputcsv($output, ['Job Posting Trends']);
    fputcsv($output, ['Date', 'New Jobs']);
    foreach ($data['job_trends'] as $trend) {
        fputcsv($output, [$trend['date'], $trend['count']]);
    }
    fputcsv($output, []);
            }

            if (isset($data['job_performance']) && is_array($data['job_performance']) && !empty($data['job_performance'])) {
    // Job Performance
    fputcsv($output, ['Job Performance Metrics']);
                fputcsv($output, ['Job Title', 'Total Applications', 'Hires', 'Hire Rate']);
    foreach ($data['job_performance'] as $job) {
        $hire_rate = $job['total_applications'] > 0 ? 
            ($job['hires'] / $job['total_applications']) * 100 : 0;
        fputcsv($output, [
            $job['title'],
            $job['total_applications'],
            $job['hires'],
            number_format($hire_rate, 1) . '%'
        ]);
    }
    fputcsv($output, []);
            }

            if (isset($data['top_skills']) && is_array($data['top_skills']) && !empty($data['top_skills'])) {
    // Top Skills
    fputcsv($output, ['Top Skills in Applications']);
    fputcsv($output, ['Skill', 'Count']);
    foreach ($data['top_skills'] as $skill) {
        fputcsv($output, [$skill['skill_name'], $skill['count']]);
                }
            }
            break;
    }

    try {
        fclose($output);
        // Ensure no additional output
        exit();
    } catch (Exception $e) {
        // Handle CSV generation exception
        fclose($output);
        throw new Exception("Failed to create CSV file: " . $e->getMessage());
    }
}

/**
 * Export report data as Excel using PhpSpreadsheet
 */
function exportExcel($conn, $data, $company, $start_date, $end_date, $report_type = 'summary') {
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set active sheet name
    $sheet->setTitle('Company Report');
    
    // Report header
    $sheet->setCellValue('A1', 'Company Report: ' . $company['company_name']);
    $sheet->setCellValue('A2', 'Report Type: ' . ucfirst($report_type));
    $sheet->setCellValue('A3', 'Period: ' . $start_date . ' to ' . $end_date);
    
    // Export based on report type
    switch ($report_type) {
        case 'jobs':
            // Job listing report export
            $sheet->setCellValue('A5', 'Job Listings Report');
            
            if (!empty($data['jobs'])) {
                $sheet->setCellValue('A6', 'Job ID');
                $sheet->setCellValue('B6', 'Title');
                $sheet->setCellValue('C6', 'Location');
                $sheet->setCellValue('D6', 'Type');
                $sheet->setCellValue('E6', 'Posted Date');
                $sheet->setCellValue('F6', 'Status');
                $sheet->setCellValue('G6', 'Deadline');
                $sheet->setCellValue('H6', 'Applicants');
                $sheet->setCellValue('I6', 'Hires');
                
                $row = 7;
                foreach ($data['jobs'] as $job) {
                    $sheet->setCellValue('A' . $row, $job['job_id']);
                    $sheet->setCellValue('B' . $row, $job['title']);
                    $sheet->setCellValue('C' . $row, $job['location']);
                    $sheet->setCellValue('D' . $row, ucfirst(str_replace('-', ' ', $job['job_type'])));
                    $sheet->setCellValue('E' . $row, $job['posted_date']);
                    $sheet->setCellValue('F' . $row, ucfirst($job['status']));
                    $sheet->setCellValue('G' . $row, $job['deadline_date']);
                    $sheet->setCellValue('H' . $row, $job['applicant_count']);
                    $sheet->setCellValue('I' . $row, $job['hired_count']);
                    $row++;
                }
                
                // Job type distribution
                $row += 2;  // Add some space
            }
            
            if (!empty($data['job_types'])) {
                $sheet->setCellValue('A' . $row, 'Job Type Distribution');
                $sheet->setCellValue('A' . ($row + 1), 'Type');
                $sheet->setCellValue('B' . ($row + 1), 'Count');
                
                $row += 2;
                foreach ($data['job_types'] as $type) {
                    $sheet->setCellValue('A' . $row, ucfirst(str_replace('-', ' ', $type['job_type'])));
                    $sheet->setCellValue('B' . $row, $type['count']);
                    $row++;
                }
            }
            break;
            
        case 'applications':
            // Applications Report
            $sheet->setCellValue('A5', 'Applications Report');
            
            if (!empty($data['applications'])) {
                $sheet->setCellValue('A6', 'Application ID');
                $sheet->setCellValue('B6', 'Job Title');
                $sheet->setCellValue('C6', 'Applicant Name');
                $sheet->setCellValue('D6', 'Application Date');
                $sheet->setCellValue('E6', 'Status');
                $sheet->setCellValue('F6', 'Match %');
                
                $row = 7;
                foreach ($data['applications'] as $app) {
                    $sheet->setCellValue('A' . $row, $app['application_id']);
                    $sheet->setCellValue('B' . $row, $app['job_title']);
                    $sheet->setCellValue('C' . $row, $app['applicant_name']);
                    $sheet->setCellValue('D' . $row, $app['application_date']);
                    $sheet->setCellValue('E' . $row, ucfirst($app['status']));
                    $sheet->setCellValue('F' . $row, $app['match_percentage'] ? $app['match_percentage'] . '%' : 'N/A');
                    $row++;
                }
                $row += 2;
            }
            
            if (!empty($data['status_distribution'])) {
                $sheet->setCellValue('A' . $row, 'Status Distribution');
                $sheet->setCellValue('A' . ($row + 1), 'Status');
                $sheet->setCellValue('B' . ($row + 1), 'Count');
                
                $row += 2;
                foreach ($data['status_distribution'] as $status) {
                    $sheet->setCellValue('A' . $row, ucfirst($status['status']));
                    $sheet->setCellValue('B' . $row, $status['count']);
                    $row++;
                }
            }
            break;
            
        case 'applicants':
            // Applicants Report
            $sheet->setCellValue('A5', 'Applicants Report');
            
            if (!empty($data['applicants'])) {
                $sheet->setCellValue('A6', 'Applicant ID');
                $sheet->setCellValue('B6', 'Full Name');
                $sheet->setCellValue('C6', 'Email');
                $sheet->setCellValue('D6', 'Skills');
                $sheet->setCellValue('E6', 'Applications Count');
                
                $row = 7;
                foreach ($data['applicants'] as $applicant) {
                    $sheet->setCellValue('A' . $row, $applicant['applicant_id']);
                    $sheet->setCellValue('B' . $row, $applicant['full_name']);
                    $sheet->setCellValue('C' . $row, $applicant['email']);
                    $sheet->setCellValue('D' . $row, $applicant['skills'] ?: 'No skills listed');
                    $sheet->setCellValue('E' . $row, $applicant['application_count']);
                    $row++;
                }
                $row += 2;
            }
            
            if (!empty($data['skills_distribution'])) {
                $sheet->setCellValue('A' . $row, 'Skills Distribution');
                $sheet->setCellValue('A' . ($row + 1), 'Skill Name');
                $sheet->setCellValue('B' . ($row + 1), 'Applicant Count');
                
                $row += 2;
                foreach ($data['skills_distribution'] as $skill) {
                    $sheet->setCellValue('A' . $row, $skill['skill_name']);
                    $sheet->setCellValue('B' . $row, $skill['applicant_count']);
                    $row++;
                }
            }
            break;
            
        case 'recruitment':
            // Recruitment Report
            $sheet->setCellValue('A5', 'Recruitment Progress Report');
            
            if (!empty($data['recruitment_stats'])) {
                $sheet->setCellValue('A6', 'Job Title');
                $sheet->setCellValue('B6', 'Total Applicants');
                $sheet->setCellValue('C6', 'Reviewed');
                $sheet->setCellValue('D6', 'Shortlisted');
                $sheet->setCellValue('E6', 'Hired');
                $sheet->setCellValue('F6', 'Rejected');
                
                $row = 7;
                foreach ($data['recruitment_stats'] as $stat) {
                    $sheet->setCellValue('A' . $row, $stat['job_title']);
                    $sheet->setCellValue('B' . $row, $stat['total_applicants']);
                    $sheet->setCellValue('C' . $row, $stat['reviewed']);
                    $sheet->setCellValue('D' . $row, $stat['shortlisted']);
                    $sheet->setCellValue('E' . $row, $stat['hired']);
                    $sheet->setCellValue('F' . $row, $stat['rejected']);
                    $row++;
                }
            }
            break;
            
        default: // 'summary'
            $row = 5;
    
            // Application Statistics
            if (isset($data['application_stats']) && is_array($data['application_stats'])) {
                $sheet->setCellValue('A' . $row, 'Application Statistics');
                $row++;
                $sheet->setCellValue('A' . $row, 'Total Applications');
                $sheet->setCellValue('B' . $row, $data['application_stats']['total_applications'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Pending');
                $sheet->setCellValue('B' . $row, $data['application_stats']['pending'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Reviewed');
                $sheet->setCellValue('B' . $row, $data['application_stats']['reviewed'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Shortlisted');
                $sheet->setCellValue('B' . $row, $data['application_stats']['shortlisted'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Rejected');
                $sheet->setCellValue('B' . $row, $data['application_stats']['rejected'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Hired');
                $sheet->setCellValue('B' . $row, $data['application_stats']['hired'] ?? 0);
                $row += 2;  // Add some space
            }
    
            // Job Type Distribution
            if (isset($data['job_type_stats']) && is_array($data['job_type_stats'])) {
                $sheet->setCellValue('A' . $row, 'Job Type Distribution');
                $row++;
                $sheet->setCellValue('A' . $row, 'Full-time');
                $sheet->setCellValue('B' . $row, $data['job_type_stats']['full_time'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Part-time');
                $sheet->setCellValue('B' . $row, $data['job_type_stats']['part_time'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Contract');
                $sheet->setCellValue('B' . $row, $data['job_type_stats']['contract'] ?? 0);
                $row++;
                $sheet->setCellValue('A' . $row, 'Internship');
                $sheet->setCellValue('B' . $row, $data['job_type_stats']['internship'] ?? 0);
                $row += 2;  // Add some space
            }
    
            // Job Posting Trends
            if (isset($data['job_trends']) && is_array($data['job_trends']) && !empty($data['job_trends'])) {
                $sheet->setCellValue('A' . $row, 'Job Posting Trends');
                $row++;
                $sheet->setCellValue('A' . $row, 'Date');
                $sheet->setCellValue('B' . $row, 'New Jobs');
                $row++;
                
                foreach ($data['job_trends'] as $trend) {
                    $sheet->setCellValue('A' . $row, $trend['date']);
                    $sheet->setCellValue('B' . $row, $trend['count']);
                    $row++;
                }
                
                $row += 1;  // Add some space
            }
    
            // Job Performance
            if (isset($data['job_performance']) && is_array($data['job_performance']) && !empty($data['job_performance'])) {
                $sheet->setCellValue('A' . $row, 'Job Performance Metrics');
                $row++;
                $sheet->setCellValue('A' . $row, 'Job Title');
                $sheet->setCellValue('B' . $row, 'Total Applications');
                $sheet->setCellValue('C' . $row, 'Hires');
                $sheet->setCellValue('D' . $row, 'Hire Rate');
                $row++;
                
                foreach ($data['job_performance'] as $job) {
                    $hire_rate = $job['total_applications'] > 0 ? 
                        ($job['hires'] / $job['total_applications']) * 100 : 0;
                    
                    $sheet->setCellValue('A' . $row, $job['title']);
                    $sheet->setCellValue('B' . $row, $job['total_applications']);
                    $sheet->setCellValue('C' . $row, $job['hires']);
                    $sheet->setCellValue('D' . $row, number_format($hire_rate, 1) . '%');
                    $row++;
                }
                
                $row += 1;  // Add some space
            }
    
            // Top Skills
            if (isset($data['top_skills']) && is_array($data['top_skills']) && !empty($data['top_skills'])) {
                $sheet->setCellValue('A' . $row, 'Top Skills in Applications');
                $row++;
                $sheet->setCellValue('A' . $row, 'Skill');
                $sheet->setCellValue('B' . $row, 'Count');
                $row++;
                
                foreach ($data['top_skills'] as $skill) {
                    $sheet->setCellValue('A' . $row, $skill['skill_name']);
                    $sheet->setCellValue('B' . $row, $skill['count']);
                    $row++;
                }
            }
            break;
    }
    
    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add styling
    $sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(14);
    if (isset($row)) {
        $sheet->getStyle('A5:A' . $row)->getFont()->setBold(true);
    }
    
    // Create Excel writer and save to output
    try {
        $writer = new Xlsx($spreadsheet);
        
        // Disable output buffering for the file output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output the file directly
        $writer->save('php://output');
        
        // Ensure no additional output
        exit();
    } catch (Exception $e) {
        // Handle writer exception
        throw new Exception("Failed to create Excel file: " . $e->getMessage());
    }
}

/**
 * Export report data as PDF using DomPDF
 */
function exportPDF($conn, $data, $company, $start_date, $end_date, $report_type = 'summary') {
    // Create base HTML for PDF based on report type
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Company Report</title>
        <style>
            body { font-family: Arial, sans-serif; }
            h1, h2 { color: #333; }
            h1 { text-align: center; }
            h2 { font-size: 16px; margin-top: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>
    </head>
    <body>
        <h1>Company Report: ' . htmlspecialchars($company['company_name']) . '</h1>
        <p><strong>Report Type:</strong> ' . ucfirst($report_type) . '</p>
        <p><strong>Period:</strong> ' . $start_date . ' to ' . $end_date . '</p>';
        
    // Add report-specific content
    switch ($report_type) {
        case 'jobs':
            if (!empty($data['jobs'])) {
                $html .= '<h2>Job Listings</h2>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Applicants</th>
                        <th>Hires</th>
                    </tr>';
                
                foreach ($data['jobs'] as $job) {
                    $html .= '<tr>
                        <td>' . $job['job_id'] . '</td>
                        <td>' . htmlspecialchars($job['title']) . '</td>
                        <td>' . ucfirst(str_replace('-', ' ', $job['job_type'])) . '</td>
                        <td>' . ucfirst($job['status']) . '</td>
                        <td>' . $job['applicant_count'] . '</td>
                        <td>' . $job['hired_count'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            }
            
            if (!empty($data['job_types'])) {
                $html .= '<h2>Job Type Distribution</h2>
                <table>
                    <tr><th>Type</th><th>Count</th></tr>';
                
                foreach ($data['job_types'] as $type) {
                    $html .= '<tr>
                        <td>' . ucfirst(str_replace('-', ' ', $type['job_type'])) . '</td>
                        <td>' . $type['count'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            }
            break;
            
        case 'applications':
            if (!empty($data['applications'])) {
                $html .= '<h2>Applications</h2>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Job</th>
                        <th>Applicant</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>';
                
                foreach ($data['applications'] as $app) {
                    $html .= '<tr>
                        <td>' . $app['application_id'] . '</td>
                        <td>' . htmlspecialchars($app['job_title']) . '</td>
                        <td>' . htmlspecialchars($app['applicant_name']) . '</td>
                        <td>' . date('Y-m-d', strtotime($app['application_date'])) . '</td>
                        <td>' . ucfirst($app['status']) . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            }
            
            if (!empty($data['status_distribution'])) {
                $html .= '<h2>Status Distribution</h2>
                <table>
                    <tr><th>Status</th><th>Count</th></tr>';
                
                foreach ($data['status_distribution'] as $status) {
                    $html .= '<tr>
                        <td>' . ucfirst($status['status']) . '</td>
                        <td>' . $status['count'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            }
            break;
            
        case 'applicants':
            if (!empty($data['applicants'])) {
                $html .= '<h2>Applicants Report</h2>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Skills</th>
                        <th>Applications</th>
                    </tr>';
                
                foreach ($data['applicants'] as $applicant) {
                    $html .= '<tr>
                        <td>' . $applicant['applicant_id'] . '</td>
                        <td>' . htmlspecialchars($applicant['full_name']) . '</td>
                        <td>' . htmlspecialchars($applicant['email']) . '</td>
                        <td>' . htmlspecialchars($applicant['skills'] ?: 'No skills listed') . '</td>
                        <td>' . $applicant['application_count'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            }
            
            if (!empty($data['skills_distribution'])) {
                $html .= '<h2>Skills Distribution</h2>
                <table>
                    <tr><th>Skill Name</th><th>Applicant Count</th></tr>';
                
                foreach ($data['skills_distribution'] as $skill) {
                    $html .= '<tr>
                        <td>' . htmlspecialchars($skill['skill_name']) . '</td>
                        <td>' . $skill['applicant_count'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            }
            break;
            
        case 'recruitment':
            if (!empty($data['recruitment_stats'])) {
                $html .= '<h2>Recruitment Progress Report</h2>
                <table>
                    <tr>
                        <th>Job Title</th>
                        <th>Total Applicants</th>
                        <th>Reviewed</th>
                        <th>Shortlisted</th>
                        <th>Hired</th>
                        <th>Rejected</th>
                    </tr>';
                
                foreach ($data['recruitment_stats'] as $stat) {
                    $html .= '<tr>
                        <td>' . htmlspecialchars($stat['job_title']) . '</td>
                        <td>' . $stat['total_applicants'] . '</td>
                        <td>' . $stat['reviewed'] . '</td>
                        <td>' . $stat['shortlisted'] . '</td>
                        <td>' . $stat['hired'] . '</td>
                        <td>' . $stat['rejected'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            }
            break;
        
        default: // 'summary'
            // Application Statistics
            if (isset($data['application_stats']) && is_array($data['application_stats'])) {
                $html .= '<h2>Application Statistics</h2>
        <table>
            <tr><th>Metric</th><th>Value</th></tr>
                    <tr><td>Total Applications</td><td>' . ($data['application_stats']['total_applications'] ?? 0) . '</td></tr>
                    <tr><td>Pending</td><td>' . ($data['application_stats']['pending'] ?? 0) . '</td></tr>
                    <tr><td>Reviewed</td><td>' . ($data['application_stats']['reviewed'] ?? 0) . '</td></tr>
                    <tr><td>Shortlisted</td><td>' . ($data['application_stats']['shortlisted'] ?? 0) . '</td></tr>
                    <tr><td>Rejected</td><td>' . ($data['application_stats']['rejected'] ?? 0) . '</td></tr>
                    <tr><td>Hired</td><td>' . ($data['application_stats']['hired'] ?? 0) . '</td></tr>
                </table>';
            }
            
            // Job Type Distribution
            if (isset($data['job_type_stats']) && is_array($data['job_type_stats'])) {
                $html .= '<h2>Job Type Distribution</h2>
        <table>
            <tr><th>Job Type</th><th>Count</th></tr>
                    <tr><td>Full-time</td><td>' . ($data['job_type_stats']['full_time'] ?? 0) . '</td></tr>
                    <tr><td>Part-time</td><td>' . ($data['job_type_stats']['part_time'] ?? 0) . '</td></tr>
                    <tr><td>Contract</td><td>' . ($data['job_type_stats']['contract'] ?? 0) . '</td></tr>
                    <tr><td>Internship</td><td>' . ($data['job_type_stats']['internship'] ?? 0) . '</td></tr>
                </table>';
            }
            
            // Job Posting Trends
            if (isset($data['job_trends']) && is_array($data['job_trends']) && !empty($data['job_trends'])) {
                $html .= '<h2>Job Posting Trends</h2>
        <table>
            <tr><th>Date</th><th>New Jobs</th></tr>';
    
    foreach ($data['job_trends'] as $trend) {
        $html .= '<tr><td>' . $trend['date'] . '</td><td>' . $trend['count'] . '</td></tr>';
    }
    
                $html .= '</table>';
            }
        
            // Job Performance
            if (isset($data['job_performance']) && is_array($data['job_performance']) && !empty($data['job_performance'])) {
                $html .= '<h2>Job Performance Metrics</h2>
        <table>
                    <tr><th>Job Title</th><th>Total Applications</th><th>Hires</th><th>Hire Rate</th></tr>';
    
    foreach ($data['job_performance'] as $job) {
        $hire_rate = $job['total_applications'] > 0 ? 
            ($job['hires'] / $job['total_applications']) * 100 : 0;
        
        $html .= '<tr>
            <td>' . htmlspecialchars($job['title']) . '</td>
            <td>' . $job['total_applications'] . '</td>
            <td>' . $job['hires'] . '</td>
            <td>' . number_format($hire_rate, 1) . '%</td>
        </tr>';
    }
    
                $html .= '</table>';
            }
        
            // Top Skills
            if (isset($data['top_skills']) && is_array($data['top_skills']) && !empty($data['top_skills'])) {
                $html .= '<h2>Top Skills in Applications</h2>
        <table>
            <tr><th>Skill</th><th>Count</th></tr>';
    
    foreach ($data['top_skills'] as $skill) {
        $html .= '<tr><td>' . htmlspecialchars($skill['skill_name']) . '</td><td>' . $skill['count'] . '</td></tr>';
    }
    
                $html .= '</table>';
            }
            break;
    }
    
    $html .= '</body></html>';
    
    try {
        // Configure DomPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        // Create DomPDF instance and generate PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Disable output buffering for the file output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output the PDF
        $dompdf->stream(null, ['Attachment' => true]);
        
        // Ensure no additional output
        exit();
    } catch (Exception $e) {
        // Handle PDF generation exception
        throw new Exception("Failed to create PDF file: " . $e->getMessage());
    }
}

/**
 * Export report data as Word document using PHPWord
 */
function exportWord($conn, $data, $company, $start_date, $end_date, $report_type = 'summary') {
    // Create new Word document
    $phpWord = new PhpWord();
    
    // Add styles
    $sectionStyle = [
        'orientation' => 'portrait',
        'marginTop' => 1000,
        'marginRight' => 1000,
        'marginBottom' => 1000,
        'marginLeft' => 1000
    ];
    
    $titleStyle = ['size' => 16, 'bold' => true, 'color' => '333333'];
    $headingStyle = ['size' => 14, 'bold' => true];
    $tableStyle = ['borderSize' => 6, 'borderColor' => '999999'];
    $headerRowStyle = ['bgColor' => 'EEEEEE'];
    $evenRowStyle = ['bgColor' => 'F9F9F9'];
    
    // Add a section
    $section = $phpWord->addSection($sectionStyle);
    
    // Add title
    $section->addText('Company Report: ' . $company['company_name'], $titleStyle, ['alignment' => 'center']);
    $section->addText('Report Type: ' . ucfirst($report_type));
    $section->addText('Period: ' . $start_date . ' to ' . $end_date);
    $section->addTextBreak();
    
    // Export based on report type
    switch ($report_type) {
        case 'jobs':
            if (!empty($data['jobs'])) {
                // Job listing report export
                $section->addText('Job Listings Report', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(1200, $headerRowStyle)->addText('Job ID', ['bold' => true]);
                $table->addCell(2500, $headerRowStyle)->addText('Title', ['bold' => true]);
                $table->addCell(1500, $headerRowStyle)->addText('Type', ['bold' => true]);
                $table->addCell(1500, $headerRowStyle)->addText('Status', ['bold' => true]);
                $table->addCell(1500, $headerRowStyle)->addText('Applications', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Hires', ['bold' => true]);
                
                $count = 0;
                foreach ($data['jobs'] as $job) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(1200, $rowStyle)->addText($job['job_id']);
                    $table->addCell(2500, $rowStyle)->addText($job['title']);
                    $table->addCell(1500, $rowStyle)->addText(ucfirst(str_replace('-', ' ', $job['job_type'])));
                    $table->addCell(1500, $rowStyle)->addText(ucfirst($job['status']));
                    $table->addCell(1500, $rowStyle)->addText($job['applicant_count']);
                    $table->addCell(1200, $rowStyle)->addText($job['hired_count']);
                    $count++;
                }
                
                $section->addTextBreak();
            }
            
            if (!empty($data['job_types'])) {
                // Job type distribution
                $section->addText('Job Type Distribution', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(3000, $headerRowStyle)->addText('Job Type', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Count', ['bold' => true]);
                
                // Add data rows
                $count = 0;
                foreach ($data['job_types'] as $type) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(3000, $rowStyle)->addText(ucfirst(str_replace('-', ' ', $type['job_type'])));
                    $table->addCell(2000, $rowStyle)->addText((string)$type['count']);
                    $count++;
                }
            }
            break;
            
        case 'applications':
            // Applications Report
            if (!empty($data['applications'])) {
                $section->addText('Applications Report', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(1200, $headerRowStyle)->addText('App ID', ['bold' => true]);
                $table->addCell(2500, $headerRowStyle)->addText('Job Title', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Applicant', ['bold' => true]);
                $table->addCell(1500, $headerRowStyle)->addText('Date', ['bold' => true]);
                $table->addCell(1500, $headerRowStyle)->addText('Status', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Match %', ['bold' => true]);
                
                $count = 0;
                foreach ($data['applications'] as $app) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(1200, $rowStyle)->addText($app['application_id']);
                    $table->addCell(2500, $rowStyle)->addText($app['job_title']);
                    $table->addCell(2000, $rowStyle)->addText($app['applicant_name']);
                    $table->addCell(1500, $rowStyle)->addText(date('M j, Y', strtotime($app['application_date'])));
                    $table->addCell(1500, $rowStyle)->addText(ucfirst($app['status']));
                    $table->addCell(1200, $rowStyle)->addText($app['match_percentage'] ? $app['match_percentage'] . '%' : 'N/A');
                    $count++;
                }
                
                $section->addTextBreak();
            }
            
            if (!empty($data['status_distribution'])) {
                $section->addText('Status Distribution', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(3000, $headerRowStyle)->addText('Status', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Count', ['bold' => true]);
                
                // Add data rows
                $count = 0;
                foreach ($data['status_distribution'] as $status) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(3000, $rowStyle)->addText(ucfirst($status['status']));
                    $table->addCell(2000, $rowStyle)->addText((string)$status['count']);
                    $count++;
                }
            }
            break;
            
        case 'applicants':
            // Applicants Report
            if (!empty($data['applicants'])) {
                $section->addText('Applicants Report', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(1200, $headerRowStyle)->addText('ID', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Full Name', ['bold' => true]);
                $table->addCell(2500, $headerRowStyle)->addText('Email', ['bold' => true]);
                $table->addCell(3000, $headerRowStyle)->addText('Skills', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Apps', ['bold' => true]);
                
                $count = 0;
                foreach ($data['applicants'] as $applicant) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(1200, $rowStyle)->addText($applicant['applicant_id']);
                    $table->addCell(2000, $rowStyle)->addText($applicant['full_name']);
                    $table->addCell(2500, $rowStyle)->addText($applicant['email']);
                    $table->addCell(3000, $rowStyle)->addText($applicant['skills'] ?: 'No skills listed');
                    $table->addCell(1200, $rowStyle)->addText($applicant['application_count']);
                    $count++;
                }
                
                $section->addTextBreak();
            }
            
            if (!empty($data['skills_distribution'])) {
                $section->addText('Skills Distribution', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(3000, $headerRowStyle)->addText('Skill Name', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Applicant Count', ['bold' => true]);
                
                // Add data rows
                $count = 0;
                foreach ($data['skills_distribution'] as $skill) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(3000, $rowStyle)->addText($skill['skill_name']);
                    $table->addCell(2000, $rowStyle)->addText((string)$skill['applicant_count']);
                    $count++;
                }
            }
            break;
            
        case 'recruitment':
            // Recruitment Report
            if (!empty($data['recruitment_stats'])) {
                $section->addText('Recruitment Progress Report', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(2500, $headerRowStyle)->addText('Job Title', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Total', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Reviewed', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Shortlisted', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Hired', ['bold' => true]);
                $table->addCell(1200, $headerRowStyle)->addText('Rejected', ['bold' => true]);
                
                $count = 0;
                foreach ($data['recruitment_stats'] as $stat) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(2500, $rowStyle)->addText($stat['job_title']);
                    $table->addCell(1200, $rowStyle)->addText($stat['total_applicants']);
                    $table->addCell(1200, $rowStyle)->addText($stat['reviewed']);
                    $table->addCell(1200, $rowStyle)->addText($stat['shortlisted']);
                    $table->addCell(1200, $rowStyle)->addText($stat['hired']);
                    $table->addCell(1200, $rowStyle)->addText($stat['rejected']);
                    $count++;
                }
            }
            break;
            
        default: // 'summary'
            // Application Statistics (only if available)
            if (isset($data['application_stats']) && is_array($data['application_stats'])) {
                $section->addText('Application Statistics', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(3000, $headerRowStyle)->addText('Metric', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Value', ['bold' => true]);
                
                // Add data rows
                $metrics = [
                    'Total Applications' => $data['application_stats']['total_applications'] ?? 0,
                    'Pending' => $data['application_stats']['pending'] ?? 0,
                    'Reviewed' => $data['application_stats']['reviewed'] ?? 0,
                    'Shortlisted' => $data['application_stats']['shortlisted'] ?? 0,
                    'Rejected' => $data['application_stats']['rejected'] ?? 0,
                    'Hired' => $data['application_stats']['hired'] ?? 0
                ];
                
                $count = 0;
                foreach ($metrics as $metric => $value) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(3000, $rowStyle)->addText($metric);
                    $table->addCell(2000, $rowStyle)->addText((string)$value);
                    $count++;
                }
                
                $section->addTextBreak();
            }
            
            // Job Type Distribution (only if available)
            if (isset($data['job_type_stats']) && is_array($data['job_type_stats'])) {
                $section->addText('Job Type Distribution', $headingStyle);
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(3000, $headerRowStyle)->addText('Job Type', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Count', ['bold' => true]);
                
                // Add data rows
                $jobTypes = [
                    'Full-time' => $data['job_type_stats']['full_time'] ?? 0,
                    'Part-time' => $data['job_type_stats']['part_time'] ?? 0,
                    'Contract' => $data['job_type_stats']['contract'] ?? 0,
                    'Internship' => $data['job_type_stats']['internship'] ?? 0
                ];
                
                $count = 0;
                foreach ($jobTypes as $type => $value) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(3000, $rowStyle)->addText($type);
                    $table->addCell(2000, $rowStyle)->addText((string)$value);
                    $count++;
                }
                
                $section->addTextBreak();
            }
            
            // Add more report sections as needed
            break;
    }
    
    // Save to output
    try {
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        
        // Disable output buffering for the file output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output the file directly
        $objWriter->save('php://output');
        
        // Ensure no additional output
        exit();
    } catch (Exception $e) {
        // Handle writer exception
        throw new Exception("Failed to create Word file: " . $e->getMessage());
    }
}

/**
 * Export printable HTML format
 */
function exportPrintable($conn, $data, $company, $start_date, $end_date, $report_type = 'summary') {
    // Common header HTML
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Company Report: ' . htmlspecialchars($company['company_name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1, h2 { color: #333; }
            h1 { text-align: center; }
            h2 { font-size: 16px; margin-top: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 30px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .print-button { display: block; margin: 20px auto; padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
            .btn-back { display: inline-block; margin: 20px 0; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
            .btn-back:hover { background: #5a6268; color: white; text-decoration: none; }
            .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            @media print {
                .print-button, .btn-back { display: none; }
                @page { margin: 1cm; }
                h2 { page-break-after: avoid; }
                table { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <div class="report-header">
            <a href="../employer/reports.php?report_type=' . urlencode($report_type) . '" class="btn-back">← Back to Reports</a>
        <button class="print-button" onclick="window.print()">Print Report</button>
        </div>
        <h1>Company Report: ' . htmlspecialchars($company['company_name']) . '</h1>
        <p><strong>Report Type:</strong> ' . ucfirst($report_type) . '</p>
        <p><strong>Period:</strong> ' . $start_date . ' to ' . $end_date . '</p>';
    
    // Add report content based on type
    switch ($report_type) {
        case 'jobs':
            // Jobs Report
            $html .= '<h2>Job Listings Report</h2>';
            
            if (!empty($data['jobs'])) {
                $html .= '<table>
                    <tr>
                        <th>Job ID</th>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Posted Date</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Applicants</th>
                        <th>Hires</th>
                    </tr>';
                
                foreach ($data['jobs'] as $job) {
                    $html .= '<tr>
                        <td>' . $job['job_id'] . '</td>
                        <td>' . htmlspecialchars($job['title']) . '</td>
                        <td>' . htmlspecialchars($job['location']) . '</td>
                        <td>' . ucfirst(str_replace('-', ' ', $job['job_type'])) . '</td>
                        <td>' . date('Y-m-d', strtotime($job['posted_date'])) . '</td>
                        <td>' . ucfirst($job['status']) . '</td>
                        <td>' . date('Y-m-d', strtotime($job['deadline_date'])) . '</td>
                        <td>' . $job['applicant_count'] . '</td>
                        <td>' . $job['hired_count'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            } else {
                $html .= '<p>No job listing data available for the selected period.</p>';
            }
            
            // Job Type Distribution section removed
            
            break;
            
        case 'applications':
            // Applications Report
            $html .= '<h2>Applications Report</h2>';
            
            if (!empty($data['applications'])) {
                $html .= '<table>
                    <tr>
                        <th>ID</th>
                        <th>Job</th>
                        <th>Applicant</th>
                        <th>Date</th>
                        <th>Match %</th>
                        <th>Status</th>
                    </tr>';
                
                foreach ($data['applications'] as $app) {
                    $html .= '<tr>
                        <td>' . $app['application_id'] . '</td>
                        <td>' . htmlspecialchars($app['job_title']) . '</td>
                        <td>' . htmlspecialchars($app['applicant_name']) . '</td>
                        <td>' . date('Y-m-d', strtotime($app['application_date'])) . '</td>
                        <td>' . $app['match_percentage'] . '%</td>
                        <td>' . ucfirst($app['status']) . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            } else {
                $html .= '<p>No application data available for the selected period.</p>';
            }
            
            // Status Distribution and Applications by Job sections removed
            
            break;
            
        case 'applicants':
            // Applicants Report
            $html .= '<h2>Applicants Report</h2>';
            
            if (!empty($data['applicants'])) {
                $html .= '<table>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Skills</th>
                        <th>Applications</th>
                    </tr>';
                
                foreach ($data['applicants'] as $applicant) {
                    $html .= '<tr>
                        <td>' . $applicant['applicant_id'] . '</td>
                        <td>' . htmlspecialchars($applicant['full_name']) . '</td>
                        <td>' . htmlspecialchars($applicant['email']) . '</td>
                        <td>' . htmlspecialchars($applicant['skills'] ?? 'N/A') . '</td>
                        <td>' . $applicant['application_count'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            } else {
                $html .= '<p>No applicant data available for the selected period.</p>';
            }
            
            // Skills Distribution section removed
            
            break;
            
        case 'recruitment':
            // Recruitment Report
            $html .= '<h2>Recruitment Progress Report</h2>';
            
            if (!empty($data['recruitment_stats'])) {
                $html .= '<table>
                    <tr>
                        <th>Job Title</th>
                        <th>Total Applicants</th>
                        <th>Reviewed</th>
                        <th>Shortlisted</th>
                        <th>Hired</th>
                        <th>Rejected</th>
                    </tr>';
                
                foreach ($data['recruitment_stats'] as $stat) {
                    $html .= '<tr>
                        <td>' . htmlspecialchars($stat['job_title']) . '</td>
                        <td>' . $stat['total_applicants'] . '</td>
                        <td>' . $stat['reviewed'] . '</td>
                        <td>' . $stat['shortlisted'] . '</td>
                        <td>' . $stat['hired'] . '</td>
                        <td>' . $stat['rejected'] . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            } else {
                $html .= '<p>No recruitment data available for the selected period.</p>';
            }
            break;
            
        case 'company':
            // Company Report
            $html .= '<h2>Company Performance Report</h2>';
            
            if (!empty($data['company_profile'])) {
                $html .= '<h3>Company Information</h3>
                <table>
                    <tr><th>Name</th><td>' . htmlspecialchars($data['company_profile']['company_name']) . '</td></tr>
                    <tr><th>Industry</th><td>' . htmlspecialchars($data['company_profile']['industry']) . '</td></tr>
                    <tr><th>Size</th><td>' . htmlspecialchars($data['company_profile']['company_size']) . '</td></tr>
                    <tr><th>Email</th><td>' . htmlspecialchars($data['company_profile']['company_email'] ?? 'N/A') . '</td></tr>
                    <tr><th>Phone</th><td>' . htmlspecialchars($data['company_profile']['company_phone'] ?? 'N/A') . '</td></tr>
                </table>';
            }
            
            if (!empty($data['company_stats'])) {
                $html .= '<h3>Company Statistics</h3>
        <table>
            <tr><th>Metric</th><th>Value</th></tr>
                    <tr><td>Total Jobs Posted</td><td>' . $data['company_stats']['jobs_posted'] . '</td></tr>
                    <tr><td>Active Jobs</td><td>' . $data['company_stats']['active_jobs'] . '</td></tr>
                    <tr><td>Applications Received</td><td>' . $data['company_stats']['applications_received'] . '</td></tr>
                    <tr><td>Candidates Hired</td><td>' . $data['company_stats']['candidates_hired'] . '</td></tr>
                </table>';
            }
            
            if (!empty($data['monthly_jobs'])) {
                $html .= '<h3>Monthly Job Posting Trends</h3>
                <table>
                    <tr><th>Month</th><th>Jobs Posted</th></tr>';
                    
                foreach ($data['monthly_jobs'] as $job) {
                    $month = date('F Y', strtotime($job['month'] . '-01'));
                    $html .= '<tr><td>' . $month . '</td><td>' . $job['job_count'] . '</td></tr>';
                }
                
                $html .= '</table>';
            }
            break;
            
        default: // 'summary'
            // Application Statistics (only if data is available)
            if (isset($data['application_stats']) && is_array($data['application_stats'])) {
                $html .= '<h2>Application Statistics</h2>
                <table>
                    <tr><th>Metric</th><th>Value</th></tr>
                    <tr><td>Total Applications</td><td>' . ($data['application_stats']['total_applications'] ?? 0) . '</td></tr>
                    <tr><td>Pending</td><td>' . ($data['application_stats']['pending'] ?? 0) . '</td></tr>
                    <tr><td>Reviewed</td><td>' . ($data['application_stats']['reviewed'] ?? 0) . '</td></tr>
                    <tr><td>Shortlisted</td><td>' . ($data['application_stats']['shortlisted'] ?? 0) . '</td></tr>
                    <tr><td>Rejected</td><td>' . ($data['application_stats']['rejected'] ?? 0) . '</td></tr>
                    <tr><td>Hired</td><td>' . ($data['application_stats']['hired'] ?? 0) . '</td></tr>
                </table>';
            }
            
            // Job Type Distribution (only if data is available)
            if (isset($data['job_type_stats']) && is_array($data['job_type_stats'])) {
                $html .= '<h2>Job Type Distribution</h2>
        <table>
            <tr><th>Job Type</th><th>Count</th></tr>
                    <tr><td>Full-time</td><td>' . ($data['job_type_stats']['full_time'] ?? 0) . '</td></tr>
                    <tr><td>Part-time</td><td>' . ($data['job_type_stats']['part_time'] ?? 0) . '</td></tr>
                    <tr><td>Contract</td><td>' . ($data['job_type_stats']['contract'] ?? 0) . '</td></tr>
                    <tr><td>Internship</td><td>' . ($data['job_type_stats']['internship'] ?? 0) . '</td></tr>
                </table>';
            }
            
            // Job Posting Trends (only if data is available)
            if (isset($data['job_trends']) && is_array($data['job_trends']) && !empty($data['job_trends'])) {
                $html .= '<h2>Job Posting Trends</h2>
        <table>
            <tr><th>Date</th><th>New Jobs</th></tr>';
    
    foreach ($data['job_trends'] as $trend) {
        $html .= '<tr><td>' . $trend['date'] . '</td><td>' . $trend['count'] . '</td></tr>';
    }
    
                $html .= '</table>';
            }
        
            // Job Performance Metrics (only if data is available)
            if (isset($data['job_performance']) && is_array($data['job_performance']) && !empty($data['job_performance'])) {
                $html .= '<h2>Job Performance Metrics</h2>
        <table>
                    <tr><th>Job Title</th><th>Total Applications</th><th>Hires</th><th>Hire Rate</th></tr>';
    
    foreach ($data['job_performance'] as $job) {
        $hire_rate = $job['total_applications'] > 0 ? 
            ($job['hires'] / $job['total_applications']) * 100 : 0;
        
        $html .= '<tr>
            <td>' . htmlspecialchars($job['title']) . '</td>
            <td>' . $job['total_applications'] . '</td>
            <td>' . $job['hires'] . '</td>
            <td>' . number_format($hire_rate, 1) . '%</td>
        </tr>';
    }
    
                $html .= '</table>';
            }
        
            // Top Skills (only if data is available)
            if (isset($data['top_skills']) && is_array($data['top_skills']) && !empty($data['top_skills'])) {
                $html .= '<h2>Top Skills in Applications</h2>
        <table>
            <tr><th>Skill</th><th>Count</th></tr>';
    
    foreach ($data['top_skills'] as $skill) {
        $html .= '<tr><td>' . htmlspecialchars($skill['skill_name']) . '</td><td>' . $skill['count'] . '</td></tr>';
    }
    
                $html .= '</table>';
            }
            break;
    }
    
    // Close HTML document
    $html .= '<script>
            // Auto-print when page loads (uncomment if you want this)
            // window.onload = function() { window.print(); }
        </script>
    </body>
    </html>';
    
    echo $html;
    exit();
}
?> 
