<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../vendor/autoload.php'; // Add Composer autoload

// Debug mode - check if debug parameter is set
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// If in debug mode, enable more detailed error reporting
if ($debug_mode) {
    // Set content type to text for readable debug output
    header('Content-Type: text/html');
    echo '<h1>Debug Mode for Export</h1>';
    echo '<p>This is a debug view to help troubleshoot export issues.</p>';
    
    echo '<h2>Server Information</h2>';
    echo '<pre>';
    echo 'PHP Version: ' . phpversion() . "\n";
    echo 'Memory Limit: ' . ini_get('memory_limit') . "\n";
    echo 'Max Execution Time: ' . ini_get('max_execution_time') . " seconds\n\n";
    
    echo 'Request Parameters: ' . "\n";
    echo '- Start Date: ' . (isset($_GET['start_date']) ? $_GET['start_date'] : 'Not set') . "\n";
    echo '- End Date: ' . (isset($_GET['end_date']) ? $_GET['end_date'] : 'Not set') . "\n";
    echo '- Report Type: ' . (isset($_GET['report']) ? $_GET['report'] : 'Not set') . "\n";
    echo '- Export Type: ' . (isset($_GET['type']) ? $_GET['type'] : 'Not set') . "\n\n";
    
    try {
        $ss = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        echo "PhpSpreadsheet loaded: OK\n";
    } catch (Exception $e) {
        echo "PhpSpreadsheet error: " . $e->getMessage() . "\n";
    }
    
    try {
        $pw = new PhpOffice\PhpWord\PhpWord();
        echo "PhpWord loaded: OK\n";
    } catch (Exception $e) {
        echo "PhpWord error: " . $e->getMessage() . "\n";
    }
    echo '</pre>';
    
    // Continue with the export but wrap in try/catch
    echo '<h2>Export Process Log</h2>';
    echo '<pre>';
    
    try {
        // Log that we're starting
        echo "Starting export process...\n";
    } catch (Exception $e) {
        echo "Export process error: " . $e->getMessage() . "\n";
    }
    echo '</pre>';
}

// Import necessary classes
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if ($debug_mode) {
        echo "User authentication failed - not an admin or not logged in.\n";
        exit();
    }
    header("Location: ../index.php");
    exit();
}

// Get parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report']) ? $_GET['report'] : 'summary';
$export_type = isset($_GET['type']) ? $_GET['type'] : 'excel';

if ($debug_mode) {
    echo "Processed Parameters:\n";
    echo "- Start Date: $start_date\n";
    echo "- End Date: $end_date\n";
    echo "- Report Type: $report_type\n";
    echo "- Export Type: $export_type\n\n";
}

// Set appropriate headers based on export type
$filename = "job_portal_" . $report_type . "_report_" . date('Y-m-d') . ".";

try {
    switch ($export_type) {
        case 'excel':
        default:
            $filename .= "xlsx";
            if (!$debug_mode) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                echo "Starting Excel export for $report_type report...\n";
            }
            exportExcel($conn, $report_type, $start_date, $end_date, $debug_mode);
            break;
            
        case 'pdf':
            $filename .= "pdf";
            if (!$debug_mode) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                echo "Starting PDF export for $report_type report...\n";
            }
            exportPDF($conn, $report_type, $start_date, $end_date, $debug_mode);
            break;
            
        case 'word':
            $filename .= "docx";
            if (!$debug_mode) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                echo "Starting Word export for $report_type report...\n";
            }
            exportWord($conn, $report_type, $start_date, $end_date, $debug_mode);
            break;
            
        case 'print':
            if ($debug_mode) {
                echo "Starting printable export for $report_type report...\n";
            }
            exportPrintable($conn, $report_type, $start_date, $end_date, $debug_mode);
            break;
            
        case 'csv':
            $filename .= "csv";
            if (!$debug_mode) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                echo "Starting CSV export for $report_type report...\n";
            }
            exportCSV($conn, $report_type, $start_date, $end_date, $debug_mode);
            break;
    }
    
    if ($debug_mode) {
        echo "Export completed successfully.\n";
    }
} catch (Exception $e) {
    if ($debug_mode) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
        echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    } else {
        // In non-debug mode, return a plain text error
        header('Content-Type: text/plain');
        echo "Error generating export: " . $e->getMessage();
    }
}

/**
 * Export report data as CSV
 */
function exportCSV($conn, $report_type, $start_date, $end_date, $debug_mode) {
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Get data based on report type
    switch ($report_type) {
        case 'jobs':
            // Write headers
            fputcsv($output, ['Job ID', 'Job Title', 'Employer Name', 'Location', 'Required Skills', 'Posted Date', 'Applicants', 'Status']);
            
            // Get data
            $stmt = $conn->prepare("SELECT j.job_id, j.title, c.company_name, j.location, j.posted_date, j.status,
                                 (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count,
                                 (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') FROM job_skills js 
                           JOIN skills s ON js.skill_id = s.skill_id 
                                  WHERE js.job_id = j.job_id) as required_skills
                                 FROM jobs j
                                 JOIN companies c ON j.company_id = c.company_id
                           WHERE j.posted_date BETWEEN ? AND ? 
                                 ORDER BY j.posted_date DESC");
    $stmt->execute([$start_date, $end_date]);
            
            // Write data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['job_id'],
                    $row['title'],
                    $row['company_name'],
                    $row['location'],
                    $row['required_skills'] ?? 'N/A',
                    $row['posted_date'],
                    $row['applicant_count'],
                    $row['status']
                ]);
            }
            break;
            
        case 'companies':
            // Write headers
            fputcsv($output, ['Company ID', 'Company Name', 'Industry', 'Established Since', 'Jobs Posted', 'Active Jobs', 'Total Hires']);
            
            // Get data - Modified to show all companies but filter job stats by date
            $date_filter = "AND j.posted_date BETWEEN '$start_date' AND '$end_date 23:59:59'";
            
            $stmt = $conn->prepare("SELECT c.company_id, c.company_name, c.industry, 
                                  (SELECT MIN(j.posted_date) FROM jobs j WHERE j.company_id = c.company_id) as registered_on,
                                  (SELECT COUNT(*) FROM jobs j 
                                   WHERE j.company_id = c.company_id $date_filter) as jobs_posted,
                                  (SELECT COUNT(*) FROM jobs j 
                                   WHERE j.company_id = c.company_id AND j.status = 'active' $date_filter) as active_jobs,
                                  (SELECT COUNT(*) FROM applications a 
                                   JOIN jobs j ON a.job_id = j.job_id 
                                   WHERE j.company_id = c.company_id AND a.status = 'hired' $date_filter) as total_hires
                           FROM companies c 
                                  ORDER BY jobs_posted DESC");
            $stmt->execute();
            
            // Write data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['company_id'],
                    $row['company_name'],
                    $row['industry'],
                    $row['registered_on'],
                    $row['jobs_posted'],
                    $row['active_jobs'],
                    $row['total_hires']
                ]);
            }
            break;
            
        case 'applicants':
            // Write headers
            fputcsv($output, ['Applicant ID', 'Full Name', 'Email', 'Registered Date', 'Skills', 'Applications', 'Matches Found']);
            
            // Get data
            $stmt = $conn->prepare("SELECT u.user_id as applicant_id, 
                                  CONCAT(up.first_name, ' ', up.last_name) as full_name,
                                  u.email, u.created_at as registered_date,
                                  (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') 
                                   FROM jobseeker_skills js 
                                   JOIN skills s ON js.skill_id = s.skill_id 
                                   WHERE js.jobseeker_id = u.user_id) as skills,
                                  (SELECT COUNT(*) FROM applications a WHERE a.jobseeker_id = u.user_id) as application_count,
                                  (SELECT COUNT(*) FROM job_skills jsk 
                                   JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                                   JOIN jobs j ON jsk.job_id = j.job_id 
                                   WHERE js.jobseeker_id = u.user_id AND j.status = 'active'
                                   GROUP BY js.jobseeker_id) as matches_found
                                  FROM users u
                                  JOIN user_profiles up ON u.user_id = up.user_id
                                  WHERE u.role = 'jobseeker' AND u.created_at BETWEEN ? AND ?
                                  ORDER BY application_count DESC");
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            
            // Write data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['applicant_id'],
                    $row['full_name'],
                    $row['email'],
                    $row['registered_date'],
                    $row['skills'] ?? 'None',
                    $row['application_count'],
                    $row['matches_found'] ?? 0
                ]);
            }
            break;
            
        case 'applications':
            // Write headers
            fputcsv($output, ['Application ID', 'Job Title', 'Applicant Name', 'Company Name', 'Applied Date', 'Match %', 'Status']);
            
            // Get data
            $stmt = $conn->prepare("SELECT a.application_id, j.title as job_title, 
                                  CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
                                  c.company_name, a.application_date, a.status,
                                  (SELECT ROUND((COUNT(DISTINCT js.skill_id) * 100.0 / 
                                           NULLIF(COUNT(DISTINCT jsk.skill_id), 0)))
                                   FROM job_skills jsk
                                   LEFT JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                                   AND js.jobseeker_id = a.jobseeker_id
                                   WHERE jsk.job_id = j.job_id) as match_percentage
                                  FROM applications a
                                  JOIN jobs j ON a.job_id = j.job_id
                                  JOIN companies c ON j.company_id = c.company_id
                                  JOIN users u ON a.jobseeker_id = u.user_id
                                  JOIN user_profiles up ON u.user_id = up.user_id
                                  WHERE a.application_date BETWEEN ? AND ?
                                  ORDER BY a.application_date DESC");
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            
            // Write data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['application_id'],
                    $row['job_title'],
                    $row['applicant_name'],
                    $row['company_name'],
                    $row['application_date'],
                    $row['match_percentage'] ? $row['match_percentage'] . '%' : 'N/A',
                    $row['status']
                ]);
            }
            break;
            
        case 'recruitment':
            // Write headers
            fputcsv($output, ['Job ID', 'Job Title', 'Company Name', 'Total Applicants', 'Screened', 'Interviewed', 'Offered', 'Hired', 'Rejected', 'Progress %']);
            
            // Get data
            $stmt = $conn->prepare("SELECT j.job_id, j.title as job_title, c.company_name,
                                  COUNT(a.application_id) as total_applicants,
                                  SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as screened,
                                  SUM(CASE WHEN a.status IN ('interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as interviewed,
                                  SUM(CASE WHEN a.status IN ('offered', 'hired') THEN 1 ELSE 0 END) as offered,
                                  SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired,
                                  SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                                  CASE 
                                    WHEN COUNT(a.application_id) > 0 THEN
                                      ROUND((SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) * 100.0 / COUNT(a.application_id)))
                                    ELSE 0
                                  END as progress_percentage
                                  FROM jobs j
                                  JOIN companies c ON j.company_id = c.company_id
                                  LEFT JOIN applications a ON j.job_id = a.job_id
                                  WHERE j.posted_date BETWEEN ? AND ?
                                  GROUP BY j.job_id, j.title, c.company_name
                                  ORDER BY total_applicants DESC");
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            
            // Write data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['job_id'],
                    $row['job_title'],
                    $row['company_name'],
                    $row['total_applicants'],
                    $row['screened'],
                    $row['interviewed'],
                    $row['offered'],
                    $row['hired'],
                    $row['rejected'],
                    $row['progress_percentage'] . '%'
                ]);
            }
            break;
            
        // Default summary report
        default:
            // Write headers
            fputcsv($output, ['Metric', 'Value']);
            
            // Get summary stats
            $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
            fputcsv($output, ['Total Users', $stmt->fetchColumn()]);
            
            $stmt = $conn->query("SELECT COUNT(*) as total_jobs FROM jobs");
            fputcsv($output, ['Total Jobs', $stmt->fetchColumn()]);
            
            $stmt = $conn->query("SELECT COUNT(*) as total_companies FROM companies");
            fputcsv($output, ['Total Companies', $stmt->fetchColumn()]);
            
            $stmt = $conn->query("SELECT COUNT(*) as total_applications FROM applications");
            fputcsv($output, ['Total Applications', $stmt->fetchColumn()]);
            
            $stmt = $conn->query("SELECT COUNT(*) as total_hires FROM applications WHERE status = 'hired'");
            fputcsv($output, ['Total Hires', $stmt->fetchColumn()]);
            
            $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, ['Users - ' . ucfirst($row['role']), $row['count']]);
            }
            
            $stmt = $conn->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, ['Applications - ' . ucfirst($row['status']), $row['count']]);
            }
            break;
    }
    
    // Close output stream
    fclose($output);
}

/**
 * Export report data as Excel using PhpSpreadsheet
 */
function exportExcel($conn, $report_type, $start_date, $end_date, $debug_mode = false) {
    try {
        if ($debug_mode) {
            echo "Initializing Excel export...\n";
        }
        
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set active sheet name
        $sheet->setTitle(ucfirst($report_type));
        
        if ($debug_mode) {
            echo "Spreadsheet created, getting data for report type: $report_type\n";
        }
        
        // Get data based on report type
        switch ($report_type) {
            case 'jobs':
                if ($debug_mode) {
                    echo "Preparing jobs report...\n";
                }
                
                // Set headers
                $sheet->setCellValue('A1', 'Job ID');
                $sheet->setCellValue('B1', 'Job Title');
                $sheet->setCellValue('C1', 'Employer Name');
                $sheet->setCellValue('D1', 'Location');
                $sheet->setCellValue('E1', 'Required Skills');
                $sheet->setCellValue('F1', 'Posted Date');
                $sheet->setCellValue('G1', 'Applicants');
                $sheet->setCellValue('H1', 'Status');
                
                if ($debug_mode) {
                    echo "Headers set, executing SQL query...\n";
                }
                
                // Get data
                $stmt = $conn->prepare("SELECT j.job_id, j.title, c.company_name, j.location, j.posted_date, j.status,
                                     (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count,
                                     (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') FROM job_skills js 
                                      JOIN skills s ON js.skill_id = s.skill_id 
                                      WHERE js.job_id = j.job_id) as required_skills
                                     FROM jobs j
                                     JOIN companies c ON j.company_id = c.company_id
                               WHERE j.posted_date BETWEEN ? AND ? 
                                     ORDER BY j.posted_date DESC");
                $stmt->execute([$start_date, $end_date]);
                
                if ($debug_mode) {
                    echo "Query executed, writing data to spreadsheet...\n";
                }
                
                // Write data
                $row = 2;
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sheet->setCellValue('A'.$row, $data['job_id']);
                    $sheet->setCellValue('B'.$row, $data['title']);
                    $sheet->setCellValue('C'.$row, $data['company_name']);
                    $sheet->setCellValue('D'.$row, $data['location']);
                    $sheet->setCellValue('E'.$row, $data['required_skills'] ?? 'N/A');
                    $sheet->setCellValue('F'.$row, $data['posted_date']);
                    $sheet->setCellValue('G'.$row, $data['applicant_count']);
                    $sheet->setCellValue('H'.$row, $data['status']);
                    $row++;
                }
                
                if ($debug_mode) {
                    echo "Data written successfully (" . ($row - 2) . " rows).\n";
                }
                break;
                
            case 'companies':
                // Set headers
                $sheet->setCellValue('A1', 'Company ID');
                $sheet->setCellValue('B1', 'Company Name');
                $sheet->setCellValue('C1', 'Industry');
                $sheet->setCellValue('D1', 'Established Since');
                $sheet->setCellValue('E1', 'Jobs Posted');
                $sheet->setCellValue('F1', 'Active Jobs');
                $sheet->setCellValue('G1', 'Total Hires');
                
                // Get data - Modified to show all companies but filter job stats by date
                $date_filter = "AND j.posted_date BETWEEN '$start_date' AND '$end_date 23:59:59'";
                
                $stmt = $conn->prepare("SELECT c.company_id, c.company_name, c.industry, 
                                      (SELECT MIN(j.posted_date) FROM jobs j WHERE j.company_id = c.company_id) as registered_on,
                                      (SELECT COUNT(*) FROM jobs j 
                                       WHERE j.company_id = c.company_id $date_filter) as jobs_posted,
                                      (SELECT COUNT(*) FROM jobs j 
                                       WHERE j.company_id = c.company_id AND j.status = 'active' $date_filter) as active_jobs,
                                      (SELECT COUNT(*) FROM applications a 
                                       JOIN jobs j ON a.job_id = j.job_id 
                                       WHERE j.company_id = c.company_id AND a.status = 'hired' $date_filter) as total_hires
                                      FROM companies c 
                                      ORDER BY jobs_posted DESC");
                $stmt->execute();
                
                // Write data
                $row = 2;
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sheet->setCellValue('A'.$row, $data['company_id']);
                    $sheet->setCellValue('B'.$row, $data['company_name']);
                    $sheet->setCellValue('C'.$row, $data['industry']);
                    $sheet->setCellValue('D'.$row, $data['registered_on']);
                    $sheet->setCellValue('E'.$row, $data['jobs_posted']);
                    $sheet->setCellValue('F'.$row, $data['active_jobs']);
                    $sheet->setCellValue('G'.$row, $data['total_hires']);
                    $row++;
                }
                break;
                
            case 'applicants':
                // Set headers
                $sheet->setCellValue('A1', 'Applicant ID');
                $sheet->setCellValue('B1', 'Full Name');
                $sheet->setCellValue('C1', 'Email');
                $sheet->setCellValue('D1', 'Registered Date');
                $sheet->setCellValue('E1', 'Skills');
                $sheet->setCellValue('F1', 'Applications');
                $sheet->setCellValue('G1', 'Matches Found');
                
                // Get data
                $stmt = $conn->prepare("SELECT u.user_id as applicant_id, 
                                      CONCAT(up.first_name, ' ', up.last_name) as full_name,
                                      u.email, u.created_at as registered_date,
                                      (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ') 
                                       FROM jobseeker_skills js 
                                       JOIN skills s ON js.skill_id = s.skill_id 
                                       WHERE js.jobseeker_id = u.user_id) as skills,
                                      (SELECT COUNT(*) FROM applications a WHERE a.jobseeker_id = u.user_id) as application_count,
                                      (SELECT COUNT(*) FROM job_skills jsk 
                                       JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                                       JOIN jobs j ON jsk.job_id = j.job_id 
                                       WHERE js.jobseeker_id = u.user_id AND j.status = 'active'
                                       GROUP BY js.jobseeker_id) as matches_found
                                      FROM users u
                                      JOIN user_profiles up ON u.user_id = up.user_id
                                      WHERE u.role = 'jobseeker' AND u.created_at BETWEEN ? AND ?
                                      ORDER BY application_count DESC");
                $stmt->execute([$start_date, $end_date . ' 23:59:59']);
                
                // Write data
                $row = 2;
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sheet->setCellValue('A'.$row, $data['applicant_id']);
                    $sheet->setCellValue('B'.$row, $data['full_name']);
                    $sheet->setCellValue('C'.$row, $data['email']);
                    $sheet->setCellValue('D'.$row, $data['registered_date']);
                    $sheet->setCellValue('E'.$row, $data['skills'] ?? 'None');
                    $sheet->setCellValue('F'.$row, $data['application_count']);
                    $sheet->setCellValue('G'.$row, $data['matches_found'] ?? 0);
                    $row++;
                }
                break;
                
            case 'applications':
                // Set headers
                $sheet->setCellValue('A1', 'Application ID');
                $sheet->setCellValue('B1', 'Job Title');
                $sheet->setCellValue('C1', 'Applicant Name');
                $sheet->setCellValue('D1', 'Company Name');
                $sheet->setCellValue('E1', 'Applied Date');
                $sheet->setCellValue('F1', 'Match %');
                $sheet->setCellValue('G1', 'Status');
                
                // Get data
                $stmt = $conn->prepare("SELECT a.application_id, j.title as job_title, 
                                      CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
                                      c.company_name, a.application_date, a.status,
                                      (SELECT ROUND((COUNT(DISTINCT js.skill_id) * 100.0 / 
                                               NULLIF(COUNT(DISTINCT jsk.skill_id), 0)))
                                       FROM job_skills jsk
                                       LEFT JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                                       AND js.jobseeker_id = a.jobseeker_id
                                       WHERE jsk.job_id = j.job_id) as match_percentage
                                      FROM applications a
                                      JOIN jobs j ON a.job_id = j.job_id
                                      JOIN companies c ON j.company_id = c.company_id
                                      JOIN users u ON a.jobseeker_id = u.user_id
                                      JOIN user_profiles up ON u.user_id = up.user_id
                                      WHERE a.application_date BETWEEN ? AND ?
                                      ORDER BY a.application_date DESC");
                $stmt->execute([$start_date, $end_date . ' 23:59:59']);
                
                // Write data
                $row = 2;
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sheet->setCellValue('A'.$row, $data['application_id']);
                    $sheet->setCellValue('B'.$row, $data['job_title']);
                    $sheet->setCellValue('C'.$row, $data['applicant_name']);
                    $sheet->setCellValue('D'.$row, $data['company_name']);
                    $sheet->setCellValue('E'.$row, $data['application_date']);
                    $sheet->setCellValue('F'.$row, $data['match_percentage'] ? $data['match_percentage'] . '%' : 'N/A');
                    $sheet->setCellValue('G'.$row, $data['status']);
                    $row++;
                }
                break;
                
            case 'recruitment':
                // Set headers
                $sheet->setCellValue('A1', 'Job ID');
                $sheet->setCellValue('B1', 'Job Title');
                $sheet->setCellValue('C1', 'Company Name');
                $sheet->setCellValue('D1', 'Total Applicants');
                $sheet->setCellValue('E1', 'Screened');
                $sheet->setCellValue('F1', 'Interviewed');
                $sheet->setCellValue('G1', 'Offered');
                $sheet->setCellValue('H1', 'Hired');
                $sheet->setCellValue('I1', 'Rejected');
                $sheet->setCellValue('J1', 'Progress %');
                
                // Get data
                $stmt = $conn->prepare("SELECT j.job_id, j.title as job_title, c.company_name,
                                      COUNT(a.application_id) as total_applicants,
                                      SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as screened,
                                      SUM(CASE WHEN a.status IN ('interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as interviewed,
                                      SUM(CASE WHEN a.status IN ('offered', 'hired') THEN 1 ELSE 0 END) as offered,
                                      SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired,
                                      SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                                      CASE 
                                        WHEN COUNT(a.application_id) > 0 THEN
                                          ROUND((SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) * 100.0 / COUNT(a.application_id)))
                                        ELSE 0
                                      END as progress_percentage
                                      FROM jobs j
                                      JOIN companies c ON j.company_id = c.company_id
                                      LEFT JOIN applications a ON j.job_id = a.job_id
                                      WHERE j.posted_date BETWEEN ? AND ?
                                      GROUP BY j.job_id, j.title, c.company_name
                                      ORDER BY total_applicants DESC");
                $stmt->execute([$start_date, $end_date . ' 23:59:59']);
                
                // Write data
                $row = 2;
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sheet->setCellValue('A'.$row, $data['job_id']);
                    $sheet->setCellValue('B'.$row, $data['job_title']);
                    $sheet->setCellValue('C'.$row, $data['company_name']);
                    $sheet->setCellValue('D'.$row, $data['total_applicants']);
                    $sheet->setCellValue('E'.$row, $data['screened']);
                    $sheet->setCellValue('F'.$row, $data['interviewed']);
                    $sheet->setCellValue('G'.$row, $data['offered']);
                    $sheet->setCellValue('H'.$row, $data['hired']);
                    $sheet->setCellValue('I'.$row, $data['rejected']);
                    $sheet->setCellValue('J'.$row, $data['progress_percentage'] . '%');
                    $row++;
                }
                break;
                
            // Default summary report
            default:
                if ($debug_mode) {
                    echo "Preparing summary report...\n";
                }
                
                // Default summary report
                $sheet->setCellValue('A1', 'Metric');
                $sheet->setCellValue('B1', 'Value');
                
                if ($debug_mode) {
                    echo "Getting summary stats...\n";
                }
                
                // Get summary stats
                $row = 2;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
                $sheet->setCellValue('A'.$row, 'Total Users');
                $sheet->setCellValue('B'.$row, $stmt->fetchColumn());
                $row++;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_jobs FROM jobs");
                $sheet->setCellValue('A'.$row, 'Total Jobs');
                $sheet->setCellValue('B'.$row, $stmt->fetchColumn());
                $row++;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_companies FROM companies");
                $sheet->setCellValue('A'.$row, 'Total Companies');
                $sheet->setCellValue('B'.$row, $stmt->fetchColumn());
                $row++;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_applications FROM applications");
                $sheet->setCellValue('A'.$row, 'Total Applications');
                $sheet->setCellValue('B'.$row, $stmt->fetchColumn());
                $row++;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_hires FROM applications WHERE status = 'hired'");
                $sheet->setCellValue('A'.$row, 'Total Hires');
                $sheet->setCellValue('B'.$row, $stmt->fetchColumn());
                $row++;
                
                $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sheet->setCellValue('A'.$row, 'Users - ' . ucfirst($data['role']));
                    $sheet->setCellValue('B'.$row, $data['count']);
                    $row++;
                }
                
                $stmt = $conn->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sheet->setCellValue('A'.$row, 'Applications - ' . ucfirst($data['status']));
                    $sheet->setCellValue('B'.$row, $data['count']);
                    $row++;
                }
                
                if ($debug_mode) {
                    echo "All summary data written successfully (" . ($row - 2) . " rows).\n";
                }
                break;
        }
        
        // Auto-size columns
        if ($debug_mode) {
            echo "Auto-sizing columns...\n";
        }
        
        $lastColumn = $report_type == 'recruitment' ? 'J' : ($report_type == 'companies' ? 'G' : 'H');
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Create Excel writer and save to output
        if ($debug_mode) {
            echo "Creating Excel writer...\n";
            
            // For debug, we'll save to a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_debug_');
            echo "Saving to temporary file: $tempFile\n";
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);
            
            echo "Excel file saved successfully!\n";
            echo "File size: " . filesize($tempFile) . " bytes\n";
            
            // In debug mode, don't send the file, just report success
            echo "Debug mode completed for Excel export\n";
        } else {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }
    } catch (Exception $e) {
        if ($debug_mode) {
            echo "ERROR in Excel export: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
        }
        throw $e; // Re-throw to be caught by the main try/catch
    }
}

/**
 * Export report data as PDF using DomPDF
 */
function exportPDF($conn, $report_type, $start_date, $end_date, $debug_mode) {
    // Create HTML for PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Report</title>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #333; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>
    </head>
    <body>
        <h1>' . ucfirst($report_type) . ' Report</h1>
        <p>Period: ' . $start_date . ' to ' . $end_date . '</p>
        <table>
            <thead>
                <tr>';
    
    // Get headers and data based on report type
    switch ($report_type) {
        case 'jobs':
            $html .= '<th>Job ID</th>
                <th>Job Title</th>
                <th>Employer</th>
                <th>Location</th>
                <th>Posted Date</th>
                <th>Applicants</th>
                <th>Status</th>';
            $html .= '</tr></thead><tbody>';
            
            $stmt = $conn->prepare("SELECT j.job_id, j.title, c.company_name, j.location, j.posted_date, j.status,
                                (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count
                                FROM jobs j
                                JOIN companies c ON j.company_id = c.company_id
                                WHERE j.posted_date BETWEEN ? AND ? 
                                ORDER BY j.posted_date DESC");
            $stmt->execute([$start_date, $end_date]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr>
                    <td>' . $row['job_id'] . '</td>
                    <td>' . htmlspecialchars($row['title']) . '</td>
                    <td>' . htmlspecialchars($row['company_name']) . '</td>
                    <td>' . htmlspecialchars($row['location']) . '</td>
                    <td>' . $row['posted_date'] . '</td>
                    <td>' . $row['applicant_count'] . '</td>
                    <td>' . $row['status'] . '</td>
                </tr>';
            }
            break;
            
        case 'companies':
            $html .= '<th>Company ID</th>
                <th>Company Name</th>
                <th>Industry</th>
                <th>Established Since</th>
                <th>Jobs Posted</th>
                <th>Active Jobs</th>
                <th>Total Hires</th>';
            $html .= '</tr></thead><tbody>';
            
            // Get data - Modified to show all companies but filter job stats by date
            $date_filter = "AND j.posted_date BETWEEN '$start_date' AND '$end_date 23:59:59'";
            
            $stmt = $conn->prepare("SELECT c.company_id, c.company_name, c.industry, 
                                  (SELECT MIN(j.posted_date) FROM jobs j WHERE j.company_id = c.company_id) as registered_on,
                                  (SELECT COUNT(*) FROM jobs j 
                                   WHERE j.company_id = c.company_id $date_filter) as jobs_posted,
                                  (SELECT COUNT(*) FROM jobs j 
                                   WHERE j.company_id = c.company_id AND j.status = 'active' $date_filter) as active_jobs,
                                  (SELECT COUNT(*) FROM applications a 
                                   JOIN jobs j ON a.job_id = j.job_id 
                                   WHERE j.company_id = c.company_id AND a.status = 'hired' $date_filter) as total_hires
                                  FROM companies c 
                                  ORDER BY jobs_posted DESC");
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr>
                    <td>' . $row['company_id'] . '</td>
                    <td>' . htmlspecialchars($row['company_name']) . '</td>
                    <td>' . htmlspecialchars($row['industry']) . '</td>
                    <td>' . $row['registered_on'] . '</td>
                    <td>' . $row['jobs_posted'] . '</td>
                    <td>' . $row['active_jobs'] . '</td>
                    <td>' . $row['total_hires'] . '</td>
                </tr>';
            }
            break;
            
        case 'applicants':
            $html .= '<th>Applicant ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Registered Date</th>
                <th>Applications</th>
                <th>Matches Found</th>';
            $html .= '</tr></thead><tbody>';
            
            $stmt = $conn->prepare("SELECT u.user_id as applicant_id, 
                                  CONCAT(up.first_name, ' ', up.last_name) as full_name,
                                  u.email, u.created_at as registered_date,
                                  (SELECT COUNT(*) FROM applications a WHERE a.jobseeker_id = u.user_id) as application_count,
                                  (SELECT COUNT(*) FROM job_skills jsk 
                                   JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                                   JOIN jobs j ON jsk.job_id = j.job_id 
                                   WHERE js.jobseeker_id = u.user_id AND j.status = 'active'
                                   GROUP BY js.jobseeker_id) as matches_found
                                  FROM users u
                                  JOIN user_profiles up ON u.user_id = up.user_id
                                  WHERE u.role = 'jobseeker' AND u.created_at BETWEEN ? AND ?
                                  ORDER BY application_count DESC");
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr>
                    <td>' . $row['applicant_id'] . '</td>
                    <td>' . htmlspecialchars($row['full_name']) . '</td>
                    <td>' . htmlspecialchars($row['email']) . '</td>
                    <td>' . $row['registered_date'] . '</td>
                    <td>' . $row['application_count'] . '</td>
                    <td>' . ($row['matches_found'] ?? 0) . '</td>
                </tr>';
            }
            break;
            
        case 'applications':
            $html .= '<th>Application ID</th>
                <th>Job Title</th>
                <th>Applicant Name</th>
                <th>Company</th>
                <th>Applied Date</th>
                <th>Match %</th>
                <th>Status</th>';
            $html .= '</tr></thead><tbody>';
            
            $stmt = $conn->prepare("SELECT a.application_id, j.title as job_title, 
                                  CONCAT(up.first_name, ' ', up.last_name) as applicant_name,
                                  c.company_name, a.application_date, a.status,
                                  (SELECT ROUND((COUNT(DISTINCT js.skill_id) * 100.0 / 
                                           NULLIF(COUNT(DISTINCT jsk.skill_id), 0)))
                                   FROM job_skills jsk
                                   LEFT JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id 
                                   AND js.jobseeker_id = a.jobseeker_id
                                   WHERE jsk.job_id = j.job_id) as match_percentage
                                  FROM applications a
                                  JOIN jobs j ON a.job_id = j.job_id
                                  JOIN companies c ON j.company_id = c.company_id
                                  JOIN users u ON a.jobseeker_id = u.user_id
                                  JOIN user_profiles up ON u.user_id = up.user_id
                                  WHERE a.application_date BETWEEN ? AND ?
                                  ORDER BY a.application_date DESC");
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr>
                    <td>' . $row['application_id'] . '</td>
                    <td>' . htmlspecialchars($row['job_title']) . '</td>
                    <td>' . htmlspecialchars($row['applicant_name']) . '</td>
                    <td>' . htmlspecialchars($row['company_name']) . '</td>
                    <td>' . $row['application_date'] . '</td>
                    <td>' . ($row['match_percentage'] ? $row['match_percentage'] . '%' : 'N/A') . '</td>
                    <td>' . $row['status'] . '</td>
                </tr>';
            }
            break;
            
        case 'recruitment':
            $html .= '<th>Job ID</th>
                <th>Job Title</th>
                <th>Company</th>
                <th>Applicants</th>
                <th>Screened</th>
                <th>Interviewed</th>
                <th>Offered</th>
                <th>Hired</th>
                <th>Rejected</th>
                <th>Progress</th>';
            $html .= '</tr></thead><tbody>';
            
            $stmt = $conn->prepare("SELECT j.job_id, j.title as job_title, c.company_name,
                                  COUNT(a.application_id) as total_applicants,
                                  SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as screened,
                                  SUM(CASE WHEN a.status IN ('interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) as interviewed,
                                  SUM(CASE WHEN a.status IN ('offered', 'hired') THEN 1 ELSE 0 END) as offered,
                                  SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired,
                                  SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                                  CASE 
                                    WHEN COUNT(a.application_id) > 0 THEN
                                      ROUND((SUM(CASE WHEN a.status IN ('screened', 'interviewed', 'offered', 'hired', 'rejected') THEN 1 ELSE 0 END) * 100.0 / COUNT(a.application_id)))
                                    ELSE 0
                                  END as progress_percentage
                                  FROM jobs j
                                  JOIN companies c ON j.company_id = c.company_id
                                  LEFT JOIN applications a ON j.job_id = a.job_id
                                  WHERE j.posted_date BETWEEN ? AND ?
                                  GROUP BY j.job_id, j.title, c.company_name
                                  ORDER BY total_applicants DESC");
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr>
                    <td>' . $row['job_id'] . '</td>
                    <td>' . htmlspecialchars($row['job_title']) . '</td>
                    <td>' . htmlspecialchars($row['company_name']) . '</td>
                    <td>' . $row['total_applicants'] . '</td>
                    <td>' . $row['screened'] . '</td>
                    <td>' . $row['interviewed'] . '</td>
                    <td>' . $row['offered'] . '</td>
                    <td>' . $row['hired'] . '</td>
                    <td>' . $row['rejected'] . '</td>
                    <td>' . $row['progress_percentage'] . '%</td>
                </tr>';
            }
            break;
        
        default:
            $html .= '<th>Metric</th><th>Value</th>';
            $html .= '</tr></thead><tbody>';
            
            // Get summary stats
            $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
            $html .= '<tr><td>Total Users</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_jobs FROM jobs");
            $html .= '<tr><td>Total Jobs</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_companies FROM companies");
            $html .= '<tr><td>Total Companies</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_applications FROM applications");
            $html .= '<tr><td>Total Applications</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_hires FROM applications WHERE status = 'hired'");
            $html .= '<tr><td>Total Hires</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr><td>Users - ' . ucfirst($row['role']) . '</td><td>' . $row['count'] . '</td></tr>';
            }
            
            $stmt = $conn->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr><td>Applications - ' . ucfirst($row['status']) . '</td><td>' . $row['count'] . '</td></tr>';
            }
            break;
    }
    
    $html .= '</tbody></table></body></html>';
    
    // Configure DomPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    
    // Create DomPDF instance and generate PDF
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape'); // Use landscape for reports with many columns
    $dompdf->render();
    $dompdf->stream(null, ['Attachment' => true]);
}

/**
 * Export report data as Word document using PHPWord
 */
function exportWord($conn, $report_type, $start_date, $end_date, $debug_mode = false) {
    try {
        if ($debug_mode) {
            echo "Initializing Word export...\n";
        }
        
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
        
        if ($debug_mode) {
            echo "Creating document section and styles...\n";
        }
        
        // Add a section
        $section = $phpWord->addSection($sectionStyle);
        
        // Add heading
        $section->addText(ucfirst($report_type) . ' Report', $titleStyle, ['alignment' => 'center']);
        $section->addText('Period: ' . $start_date . ' to ' . $end_date);
        $section->addTextBreak();
        
        if ($debug_mode) {
            echo "Document structure created, preparing report data for type: $report_type\n";
        }
        
        // Get data based on report type
        switch ($report_type) {
            case 'jobs':
                if ($debug_mode) {
                    echo "Creating jobs report...\n";
                }
                
                // Create table
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(800, $headerRowStyle)->addText('Job ID', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Job Title', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Employer', ['bold' => true]);
                $table->addCell(1500, $headerRowStyle)->addText('Location', ['bold' => true]);
                $table->addCell(1500, $headerRowStyle)->addText('Posted Date', ['bold' => true]);
                $table->addCell(1000, $headerRowStyle)->addText('Applicants', ['bold' => true]);
                $table->addCell(1000, $headerRowStyle)->addText('Status', ['bold' => true]);
                
                if ($debug_mode) {
                    echo "Table headers created, executing SQL query...\n";
                }
                
                // Get data
                $stmt = $conn->prepare("SELECT j.job_id, j.title, c.company_name, j.location, j.posted_date, j.status,
                                    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count
                                    FROM jobs j
                                    JOIN companies c ON j.company_id = c.company_id
                                    WHERE j.posted_date BETWEEN ? AND ? 
                                    ORDER BY j.posted_date DESC");
                $stmt->execute([$start_date, $end_date]);
                
                if ($debug_mode) {
                    echo "Query executed, writing data to Word document...\n";
                }
                
                $count = 0;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(800, $rowStyle)->addText($row['job_id']);
                    $table->addCell(2000, $rowStyle)->addText($row['title']);
                    $table->addCell(2000, $rowStyle)->addText($row['company_name']);
                    $table->addCell(1500, $rowStyle)->addText($row['location']);
                    $table->addCell(1500, $rowStyle)->addText($row['posted_date']);
                    $table->addCell(1000, $rowStyle)->addText($row['applicant_count']);
                    $table->addCell(1000, $rowStyle)->addText($row['status']);
                    $count++;
                }
                
                if ($debug_mode) {
                    echo "Data written successfully (" . $count . " rows).\n";
                }
                break;
                
            // Other report types would be similar to the jobs case
            
            default:
                if ($debug_mode) {
                    echo "Creating summary report...\n";
                }
                
                // Create table
                $table = $section->addTable($tableStyle);
                
                // Add header row
                $table->addRow();
                $table->addCell(4000, $headerRowStyle)->addText('Metric', ['bold' => true]);
                $table->addCell(2000, $headerRowStyle)->addText('Value', ['bold' => true]);
                
                if ($debug_mode) {
                    echo "Getting summary stats...\n";
                }
                
                // Get summary stats
                $count = 0;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
                $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                $table->addRow();
                $table->addCell(4000, $rowStyle)->addText('Total Users');
                $table->addCell(2000, $rowStyle)->addText($stmt->fetchColumn());
                $count++;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_jobs FROM jobs");
                $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                $table->addRow();
                $table->addCell(4000, $rowStyle)->addText('Total Jobs');
                $table->addCell(2000, $rowStyle)->addText($stmt->fetchColumn());
                $count++;
                
                if ($debug_mode) {
                    echo "Basic statistics added to the document...\n";
                }
                
                // Add more items
                $stmt = $conn->query("SELECT COUNT(*) as total_companies FROM companies");
                $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                $table->addRow();
                $table->addCell(4000, $rowStyle)->addText('Total Companies');
                $table->addCell(2000, $rowStyle)->addText($stmt->fetchColumn());
                $count++;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_applications FROM applications");
                $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                $table->addRow();
                $table->addCell(4000, $rowStyle)->addText('Total Applications');
                $table->addCell(2000, $rowStyle)->addText($stmt->fetchColumn());
                $count++;
                
                $stmt = $conn->query("SELECT COUNT(*) as total_hires FROM applications WHERE status = 'hired'");
                $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                $table->addRow();
                $table->addCell(4000, $rowStyle)->addText('Total Hires');
                $table->addCell(2000, $rowStyle)->addText($stmt->fetchColumn());
                $count++;
                
                $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(4000, $rowStyle)->addText('Users - ' . ucfirst($row['role']));
                    $table->addCell(2000, $rowStyle)->addText($row['count']);
                    $count++;
                }
                
                $stmt = $conn->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rowStyle = ($count % 2 == 0) ? $evenRowStyle : [];
                    $table->addRow();
                    $table->addCell(4000, $rowStyle)->addText('Applications - ' . ucfirst($row['status']));
                    $table->addCell(2000, $rowStyle)->addText($row['count']);
                    $count++;
                }
                
                if ($debug_mode) {
                    echo "All summary data written (" . $count . " rows total).\n";
                }
                break;
        }
        
        if ($debug_mode) {
            echo "Creating Word writer...\n";
            
            // For debug, save to a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'word_debug_');
            echo "Saving to temporary file: $tempFile\n";
            
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempFile);
            
            echo "Word file saved successfully!\n";
            echo "File size: " . filesize($tempFile) . " bytes\n";
            
            // In debug mode, don't send the file, just report success
            echo "Debug mode completed for Word export\n";
        } else {
            // Save to output
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save('php://output');
        }
    } catch (Exception $e) {
        if ($debug_mode) {
            echo "ERROR in Word export: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
        }
        throw $e; // Re-throw to be caught by the main try/catch
    }
}

/**
 * Export printable HTML format
 */
function exportPrintable($conn, $report_type, $start_date, $end_date, $debug_mode) {
    // Generate the same HTML as in exportPDF, but output directly
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . ucfirst($report_type) . ' Report</title>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #333; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
            .print-button { display: block; margin: 20px auto; padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
            @media print {
                .print-button { display: none; }
                @page { margin: 1cm; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>' . ucfirst($report_type) . ' Report</h1>
            <p>Period: ' . $start_date . ' to ' . $end_date . '</p>
            <button class="print-button" onclick="window.print()">Print Report</button>
            <table>
                <thead>
                    <tr>';
    
    // Get headers and data based on report type - same as PDF export
    switch ($report_type) {
        case 'jobs':
            $html .= '<th>Job ID</th>
                <th>Job Title</th>
                <th>Employer</th>
                <th>Location</th>
                <th>Posted Date</th>
                <th>Applicants</th>
                <th>Status</th>';
            $html .= '</tr></thead><tbody>';
            
            $stmt = $conn->prepare("SELECT j.job_id, j.title, c.company_name, j.location, j.posted_date, j.status,
                                (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as applicant_count
                                FROM jobs j
                                JOIN companies c ON j.company_id = c.company_id
                                WHERE j.posted_date BETWEEN ? AND ? 
                                ORDER BY j.posted_date DESC");
            $stmt->execute([$start_date, $end_date]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr>
                    <td>' . $row['job_id'] . '</td>
                    <td>' . $row['title'] . '</td>
                    <td>' . $row['company_name'] . '</td>
                    <td>' . $row['location'] . '</td>
                    <td>' . $row['posted_date'] . '</td>
                    <td>' . $row['applicant_count'] . '</td>
                    <td>' . $row['status'] . '</td>
                </tr>';
            }
            break;
            
        // ... Other report types would be similar to the jobs case
        
        default:
            $html .= '<th>Metric</th><th>Value</th>';
            $html .= '</tr></thead><tbody>';
            
            // Get summary stats
            $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
            $html .= '<tr><td>Total Users</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_jobs FROM jobs");
            $html .= '<tr><td>Total Jobs</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_companies FROM companies");
            $html .= '<tr><td>Total Companies</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_applications FROM applications");
            $html .= '<tr><td>Total Applications</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT COUNT(*) as total_hires FROM applications WHERE status = 'hired'");
            $html .= '<tr><td>Total Hires</td><td>' . $stmt->fetchColumn() . '</td></tr>';
            
            $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr><td>Users - ' . ucfirst($row['role']) . '</td><td>' . $row['count'] . '</td></tr>';
            }
            
            $stmt = $conn->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $html .= '<tr><td>Applications - ' . ucfirst($row['status']) . '</td><td>' . $row['count'] . '</td></tr>';
            }
            break;
    }
    
    $html .= '</tbody></table>
            <script>
                // Auto-print when page loads
                window.onload = function() {
                    // Uncomment this to automatically open print dialog
                    // window.print();
                }
            </script>
        </div>
    </body>
    </html>';
    
    echo $html;
    exit();
}
?> 