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

if (!$input || !isset($input['action']) || $input['action'] !== 'bulk_update_status') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$registration_ids = $input['registration_ids'] ?? [];
$status = $input['status'] ?? '';

// Validate inputs
if (empty($registration_ids) || !in_array($status, ['registered', 'cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Create placeholders for IN clause
    $placeholders = str_repeat('?,', count($registration_ids) - 1) . '?';
    
    // Update multiple registrations
    $stmt = $conn->prepare("UPDATE event_registrations SET status = ? WHERE registration_id IN ($placeholders)");
    $params = array_merge([$status], $registration_ids);
    $stmt->execute($params);
    
    $updated_count = $stmt->rowCount();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully updated $updated_count registration(s)",
        'updated_count' => $updated_count
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating registrations: ' . $e->getMessage()
    ]);
}
?> 