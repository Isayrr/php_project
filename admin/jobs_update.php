<?php
// This file contains the changes to be applied to admin/jobs.php

// 1. Add approval actions to the job actions handler
if (isset($_POST['action']) && isset($_POST['job_id'])) {
    try {
        $job_id = $_POST['job_id'];
        $action = $_POST['action'];
        
        // Add these conditions
        if ($action === 'approve' || $action === 'reject') {
            $new_approval_status = $action === 'approve' ? 'approved' : 'rejected';
            
            // Update job approval status
            $stmt = $conn->prepare("UPDATE jobs SET approval_status = ? WHERE job_id = ?");
            $stmt->execute([$new_approval_status, $job_id]);
            
            // Get job and employer information
            $stmt = $conn->prepare("
                SELECT j.title, j.company_id, c.employer_id, u.username
                FROM jobs j
                JOIN companies c ON j.company_id = c.company_id
                JOIN users u ON c.employer_id = u.user_id
                WHERE j.job_id = ?
            ");
            $stmt->execute([$job_id]);
            $job_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($action === 'approve') {
                // Set job to active status
                $stmt = $conn->prepare("UPDATE jobs SET status = 'active' WHERE job_id = ?");
                $stmt->execute([$job_id]);
                
                // Notify employer
                $title = "Job Posting Approved";
                $message = "Your job posting '{$job_info['title']}' has been approved and is now visible to job seekers.";
                
                // Notify matching job seekers
                $notified_count = notifyMatchingJobSeekers($conn, $job_id);
            } else {
                $title = "Job Posting Rejected";
                $message = "Your job posting '{$job_info['title']}' has been rejected. Please contact the administrator for more information.";
            }
            
            // Insert notification for employer
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read) VALUES (?, ?, ?, ?, 'job', NOW(), 0)");
            $stmt->execute([$job_info['employer_id'], $title, $message, $job_id]);
            
            $success = "Job {$action}d successfully.";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. Add approval_status filter to the query builder
// Add approval_status filter if provided
$approval_filter = isset($_GET['approval']) ? $_GET['approval'] : '';
if ($approval_filter) {
    $query .= " AND j.approval_status = ?";
    $params[] = $approval_filter;
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
        if ($job['approval_status'] === 'approved') echo 'success';
        elseif ($job['approval_status'] === 'rejected') echo 'danger';
        else echo 'warning';
    ?>">
        <?php echo ucfirst(htmlspecialchars($job['approval_status'])); ?>
    </span>
</td>
*/

// 6. Add approval actions to the dropdown menu
/*
<?php if ($job['approval_status'] === 'pending'): ?>
    <button type="submit" name="action" value="approve" class="dropdown-item text-success">
        <i class="fas fa-check me-2"></i> Approve
    </button>
    <button type="submit" name="action" value="reject" class="dropdown-item text-danger">
        <i class="fas fa-times me-2"></i> Reject
    </button>
<?php endif; ?>
*/
?> 