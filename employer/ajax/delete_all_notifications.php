<?php
session_start();
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Delete all notifications for the user
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $result = $stmt->execute([$user_id]);
    
    if ($result) {
        $deleted_count = $stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'message' => "All notifications deleted successfully",
            'deleted_count' => $deleted_count,
            'unread_count' => 0,
            'total_count' => 0
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete notifications'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error deleting all notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("General error deleting all notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
?> 
