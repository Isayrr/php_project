<?php
session_start();
require_once '../../config/database.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$registration_id = $input['registration_id'] ?? 0;
$status = $input['status'] ?? '';

// Validate inputs
if ($registration_id <= 0 || !in_array($status, ['registered', 'cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Update registration status
    $stmt = $conn->prepare("UPDATE event_registrations SET status = ? WHERE registration_id = ?");
    $stmt->execute([$status, $registration_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Registration status updated successfully',
            'new_status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Registration not found or no changes made'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
}
?> 