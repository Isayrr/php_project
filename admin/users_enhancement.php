<?php
/**
 * This file contains enhancements for the admin/users.php page
 * to make pending approval requests more visible
 */

// Add this at the beginning of the page, after checking if user is admin
// Count pending approvals
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE approval_status = 'pending'");
$stmt->execute();
$pending_count = $stmt->fetchColumn();

// If there are pending approvals, show a notification at the top
if ($pending_count > 0) {
    echo '
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Attention!</strong> You have ' . $pending_count . ' pending account ' . ($pending_count == 1 ? 'approval' : 'approvals') . ' that require your review.
        <a href="?approval=pending" class="alert-link">Click here to view them</a>.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Add this CSS to highlight pending rows
echo '
<style>
    tr.pending-approval {
        background-color: #fff3cd !important;
    }
    tr.pending-approval:hover {
        background-color: #ffe8b3 !important;
    }
    .badge.bg-pending {
        background-color: #fd7e14;
    }
</style>';

// In the table row generation, add the pending-approval class to rows with pending approval
// Change:
// <tr>
// To:
// <tr class="<?php echo $user['approval_status'] === 'pending' ? 'pending-approval' : ''; ?>">

// Also change the badge color for pending status
// Change:
// <span class="badge bg-<?php 
//     if ($user['approval_status'] === 'approved') echo 'success';
//     elseif ($user['approval_status'] === 'rejected') echo 'danger';
//     else echo 'warning';
// ?>">
// To:
// <span class="badge bg-<?php 
//     if ($user['approval_status'] === 'approved') echo 'success';
//     elseif ($user['approval_status'] === 'rejected') echo 'danger';
//     else echo 'pending';
// ?>">

// Add a "View Details" button to the dropdown for pending approvals
// Add this inside the dropdown menu for pending users:
// <?php if ($user['approval_status'] === 'pending'): ?>
//     <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#userModal<?php echo $user['user_id']; ?>">
//         <i class="fas fa-eye me-2"></i> View Details
//     </button>
//     <div class="dropdown-divider"></div>
// <?php endif; ?>

// Add this at the end of the file, before the closing body tag
// Generate modals for each pending user
foreach ($users as $user) {
    if ($user['approval_status'] === 'pending') {
        echo '
        <div class="modal fade" id="userModal' . $user['user_id'] . '" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">User Registration Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <h6>User Information</h6>
                            <table class="table">
                                <tr>
                                    <th>Username:</th>
                                    <td>' . htmlspecialchars($user['username']) . '</td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td>' . htmlspecialchars($user['email']) . '</td>
                                </tr>
                                <tr>
                                    <th>Role:</th>
                                    <td>' . ucfirst(htmlspecialchars($user['role'])) . '</td>
                                </tr>
                                <tr>
                                    <th>Registration Date:</th>
                                    <td>' . htmlspecialchars($user['created_at']) . '</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Profile Information</h6>
                            <table class="table">
                                <tr>
                                    <th>First Name:</th>
                                    <td>' . htmlspecialchars($user['first_name'] ?? 'Not provided') . '</td>
                                </tr>
                                <tr>
                                    <th>Last Name:</th>
                                    <td>' . htmlspecialchars($user['last_name'] ?? 'Not provided') . '</td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td>' . htmlspecialchars($user['phone'] ?? 'Not provided') . '</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form method="POST">
                            <input type="hidden" name="user_id" value="' . $user['user_id'] . '">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                <i class="fas fa-check me-2"></i> Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">
                                <i class="fas fa-times me-2"></i> Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>';
    }
}
?> 