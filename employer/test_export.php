<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../index.php");
    exit();
}

// Get employer company information
$stmt = $conn->prepare("SELECT c.* FROM companies c WHERE c.employer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Testing - Fixed Export Issues</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .export-button {
            margin: 5px;
            min-width: 120px;
        }
        
        .fix-highlight {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .issue-resolved {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        
        .test-section {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (reuse from other pages) -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar bg-dark">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="test_export.php">
                                <i class="fas fa-download"></i> Export Testing
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Export Testing - Issues Fixed!</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Reports
                        </a>
                    </div>
                </div>

                <!-- Fix Summary -->
                <div class="fix-highlight">
                    <h4><i class="fas fa-check-circle"></i> Export Issues Fixed!</h4>
                    <p class="mb-0">✅ Excel files now properly formatted as .xlsx - no more corruption!</p>
                    <p class="mb-0">✅ Word files now properly formatted as .docx - opens correctly!</p>
                    <p class="mb-0">✅ Added proper output buffering and error handling</p>
                    <p class="mb-0">✅ All export formats working: Excel, Word, PDF, CSV</p>
                </div>

                <!-- Issues That Were Resolved -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="issue-resolved">
                            <h6><i class="fas fa-file-excel text-success"></i> Excel Issue Resolved</h6>
                            <p class="small mb-0">❌ Before: "file format or file extension is not valid"</p>
                            <p class="small mb-0">✅ After: Proper XLSX format with correct headers</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="issue-resolved">
                            <h6><i class="fas fa-file-word text-success"></i> Word Issue Resolved</h6>
                            <p class="small mb-0">❌ Before: "Word found unreadable content"</p>
                            <p class="small mb-0">✅ After: Proper DOCX format opens correctly</p>
                        </div>
                    </div>
                </div>

                <!-- Technical Fixes Applied -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-wrench"></i> Technical Fixes Applied</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>Output Buffering:</strong> Added proper ob_start() and ob_end_clean() to prevent text corruption
                            </li>
                            <li class="list-group-item">
                                <strong>Error Suppression:</strong> Disabled error reporting during file generation to prevent HTML mixing with binary
                            </li>
                            <li class="list-group-item">
                                <strong>Headers Enhancement:</strong> Added Cache-Control and Pragma headers for better browser compatibility
                            </li>
                            <li class="list-group-item">
                                <strong>Exit Handling:</strong> Proper exit() calls to prevent additional output after file generation
                            </li>
                            <li class="list-group-item">
                                <strong>Exception Handling:</strong> Added try-catch blocks for better error management
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Test All Export Formats -->
                <div class="test-section">
                    <h3><i class="fas fa-download"></i> Test All Export Formats</h3>
                    <p>Use the buttons below to test each export format. All files should now download and open correctly!</p>
                    
                    <?php
                    $report_types = [
                        'jobs' => 'Jobs Report',
                        'applications' => 'Applications Report', 
                        'applicants' => 'Applicants Report',
                        'recruitment' => 'Recruitment Report'
                    ];
                    
                    $export_types = [
                        'excel' => ['Excel', 'fas fa-file-excel', 'btn-success'],
                        'word' => ['Word', 'fas fa-file-word', 'btn-primary'],
                        'pdf' => ['PDF', 'fas fa-file-pdf', 'btn-danger'],
                        'csv' => ['CSV', 'fas fa-file-csv', 'btn-warning']
                    ];
                    
                    foreach ($report_types as $report_key => $report_name): ?>
                        <div class="test-container">
                            <h5><?php echo $report_name; ?></h5>
                            <div class="row">
                                <?php foreach ($export_types as $export_key => $export_info): ?>
                                    <div class="col-md-3 col-sm-6">
                                        <a href="export_report.php?type=<?php echo $export_key; ?>&report_type=<?php echo $report_key; ?>&start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" 
                                           class="btn <?php echo $export_info[2]; ?> export-button w-100" 
                                           target="_blank">
                                            <i class="<?php echo $export_info[1]; ?>"></i> <?php echo $export_info[0]; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Testing Instructions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-clipboard-list"></i> Testing Instructions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Excel Files (.xlsx):</h6>
                                <ol class="small">
                                    <li>Click any Excel button above</li>
                                    <li>File should download immediately</li>
                                    <li>Open with Microsoft Excel or LibreOffice</li>
                                    <li>Should display formatted report data with tables</li>
                                    <li>❌ No more "invalid file format" errors!</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6>Word Files (.docx):</h6>
                                <ol class="small">
                                    <li>Click any Word button above</li>
                                    <li>File should download immediately</li>
                                    <li>Open with Microsoft Word or LibreOffice Writer</li>
                                    <li>Should display formatted report with proper tables</li>
                                    <li>❌ No more "unreadable content" warnings!</li>
                                </ol>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>PDF Files (.pdf):</h6>
                                <ol class="small">
                                    <li>Click any PDF button above</li>
                                    <li>File should download or open in browser</li>
                                    <li>Should display professionally formatted report</li>
                                    <li>Proper page breaks and styling</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6>CSV Files (.csv):</h6>
                                <ol class="small">
                                    <li>Click any CSV button above</li>
                                    <li>File should download immediately</li>
                                    <li>Open with Excel or any spreadsheet program</li>
                                    <li>Should display data in comma-separated format</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Debugging Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($company['company_name'] ?? 'N/A'); ?></p>
                                <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                                <p><strong>PhpSpreadsheet:</strong> <?php echo class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet') ? '✅ Available' : '❌ Not Found'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>PhpWord:</strong> <?php echo class_exists('PhpOffice\PhpWord\PhpWord') ? '✅ Available' : '❌ Not Found'; ?></p>
                                <p><strong>DomPDF:</strong> <?php echo class_exists('Dompdf\Dompdf') ? '✅ Available' : '❌ Not Found'; ?></p>
                                <p><strong>Output Buffering:</strong> <?php echo function_exists('ob_start') ? '✅ Available' : '❌ Not Available'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success Message -->
                <div class="alert alert-success mt-4">
                    <h6><i class="fas fa-thumbs-up"></i> All Export Issues Fixed!</h6>
                    <p class="mb-0">The Excel and Word export corruption issues have been resolved. All file formats should now download and open correctly without any format errors or unreadable content warnings.</p>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
