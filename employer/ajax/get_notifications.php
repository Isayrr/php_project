<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/notifications.php';

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
    // Get recent notifications (limit to 10 for dropdown)
    $stmt = $conn->prepare("
        SELECT 
            notification_id,
            title,
            message,
            related_id,
            related_type,
            created_at,
            is_read
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
    // Format notifications for frontend
    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        $formatted_notifications[] = [
            'notification_id' => $notification['notification_id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'related_id' => $notification['related_id'],
            'related_type' => $notification['related_type'],
            'created_at' => $notification['created_at'],
            'is_read' => $notification['is_read']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications,
        'unread_count' => (int)$unread_count
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("General error fetching notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
?> 
