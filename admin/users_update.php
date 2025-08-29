<?php
// This file contains the changes to be applied to admin/users.php

// 1. Add approval actions to the user actions handler
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    try {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        
        // Add these conditions
        if ($action === 'approve' || $action === 'reject') {
            $new_approval_status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE user_id = ?");
            $stmt->execute([$new_approval_status, $user_id]);
            
            // Get user information for notification
            $stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification for the user
            if ($action === 'approve') {
                // Set user to active status when approved
                $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Send notification to user
                $title = "Account Approved";
                $message = "Your {$user_info['role']} account has been approved. You can now log in to the system.";
            } else {
                $title = "Account Rejected";
                $message = "Your {$user_info['role']} account registration has been rejected. Please contact the administrator for more information.";
            }
            
            // Insert notification
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
            $stmt->execute([$user_id, $title, $message]);
            
            $success = "User {$action}d successfully.";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. Add approval_status filter to the query builder
// Add approval_status filter if provided
$approval_filter = isset($_GET['approval']) ? $_GET['approval'] : '';
if ($approval_filter) {
    $query .= $where_added ? " AND " : " WHERE ";
    $query .= "u.approval_status = ?";
    $params[] = $approval_filter;
    $where_added = true;
}

// 3. Add approval filter dropdown to the form
/*
<div class="col-md-2">
    <label for="approval" class="form-label">Approval</label>
    <select class="form-select" id="approval" name="approval">
        <option value="">All</option>
        <option value="pending" <?php echo isset($_GET['approval']) && $_GET['approval'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
        <option value="approved" <?php echo isset($_GET['approval']) && $_GET['approval'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
        <option value="rejected" <?php echo isset($_GET['approval']) && $_GET['approval'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
    </select>
</div>
*/

// 4. Add approval status column to the table header
/*
<th>Approval</th>
*/

// 5. Add approval status display in the table row
/*
<td>
    <span class="badge bg-<?php 
        if ($user['approval_status'] === 'approved') echo 'success';
        elseif ($user['approval_status'] === 'rejected') echo 'danger';
        else echo 'warning';
    ?>">
        <?php echo ucfirst(htmlspecialchars($user['approval_status'])); ?>
    </span>
</td>
*/

// 6. Add approval actions to the dropdown menu
/*
<?php if ($user['approval_status'] === 'pending'): ?>
    <button type="submit" name="action" value="approve" class="dropdown-item text-success">
        <i class="fas fa-check me-2"></i> Approve
    </button>
    <button type="submit" name="action" value="reject" class="dropdown-item text-danger">
        <i class="fas fa-times me-2"></i> Reject
    </button>
<?php endif; ?>
*/
?> 