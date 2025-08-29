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
    // Mark the notification as read (only if it belongs to the current user)
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE notification_id = ? AND user_id = ?
    ");
    $result = $stmt->execute([$notification_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Get updated unread count
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $unread_count = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read',
            'unread_count' => (int)$unread_count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or already read'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("General error marking notification as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
?> 