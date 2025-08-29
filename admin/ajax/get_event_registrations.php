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

// Get event ID
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($event_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

try {
    // Get event registrations with company and user details
    $stmt = $conn->prepare("SELECT 
        er.registration_id,
        er.registration_date,
        er.status,
        er.notes,
        c.company_name,
        c.industry,
        u.email as employer_email,
        CONCAT(up.first_name, ' ', up.last_name) as contact_name
        FROM event_registrations er
        JOIN companies c ON er.company_id = c.company_id
        JOIN users u ON er.employer_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE er.event_id = ?
        ORDER BY er.registration_date ASC");
    
    $stmt->execute([$event_id]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for better display
    foreach ($registrations as &$registration) {
        // Clean up contact name if empty
        if (trim($registration['contact_name']) === '' || $registration['contact_name'] === ' ') {
            $registration['contact_name'] = null;
        }
        
        // Format registration date
        $registration['registration_date'] = date('Y-m-d H:i:s', strtotime($registration['registration_date']));
    }
    
    echo json_encode([
        'success' => true,
        'registrations' => $registrations,
        'count' => count($registrations)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading registrations: ' . $e->getMessage()
    ]);
}
?>