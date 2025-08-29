<?php
session_start();
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if notification_id is provided
if (!isset($_POST['notification_id']) || !is_numeric($_POST['notification_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = (int)$_POST['notification_id'];

try {
    // Delete the notification (only if it belongs to the current user)
    $stmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE notification_id = ? AND user_id = ?
    ");
    $result = $stmt->execute([$notification_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or access denied'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error deleting notification: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("General error deleting notification: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
?> 