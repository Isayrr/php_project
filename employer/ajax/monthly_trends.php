<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$company_id = null;
$stmt = $conn->prepare("SELECT company_id FROM companies WHERE employer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if ($company) {
    $company_id = $company['company_id'];
} else {
    echo json_encode(['error' => 'No company']);
    exit();
}

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$stmt = $conn->prepare("SELECT 
    DATE_FORMAT(j.posted_date, '%Y-%m') as month,
    COUNT(DISTINCT j.job_id) as jobs_posted,
    COUNT(DISTINCT a.application_id) as applications_received
    FROM jobs j 
    LEFT JOIN applications a ON j.job_id = a.job_id 
    WHERE j.company_id = ? 
    AND j.posted_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(j.posted_date, '%Y-%m')
    ORDER BY month");
$stmt->execute([$company_id, $start_date, $end_date]);
$monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($monthly_trends);