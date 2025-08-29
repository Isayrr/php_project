<?php
// Get user data if logged in
$user_name = '';
$user_role = '';
$user_avatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0OTYgNTEyIj48cGF0aCBmaWxsPSIjODg4IiBkPSJNMjQ4IDhDMTExIDggMCAxMTkgMCAyNTZzMTExIDI0OCAyNDggMjQ4IDI0OC0xMTEgMjQ4LTI0OFMzODUgOCAyNDggOHptMCA5NmM0OC42IDAgODggMzkuNCA4OCA4OHMtMzkuNCA4OC04OCA4OC04OC0zOS40LTg4LTg4IDM5LjQtODggODgtODh6bTAgMzQ0Yy01OC43IDAtMTExLjMtMjYuNi0xNDYuNS02OC4yIDE4LjgtMzUuNCAxLjMtMTE1LjMgNjIuNC0xNzIuMiA0LjUtNC4xIDEwLjktMy4xIDE0LjggMS42bDk5LjYgMTA3LjljNC40IDQuNyA0LjQgMTEuOS0uMSAxNi42bC00NC45IDQ1LjljLTYuNiA2LjgtMy41IDE3LjggNi43IDIwLjUgMTIuMiAzLjIgMjUuNiA1IDM5LjUgNSA1MyAwIDEwMS44LTE4LjcgMTQwLjYtNTAtLjEgNDguNi01Mi42IDExOS41LTE3Mi40IDExNC45eiIvPjwvc3ZnPg==';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    try {
        // Get user's name and profile picture
        $stmt = $conn->prepare("SELECT CONCAT(up.first_name, ' ', up.last_name) as full_name, u.email, up.profile_picture 
                                FROM users u 
                                LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                                WHERE u.user_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $user_name = $user_data['full_name'] ? $user_data['full_name'] : $user_data['email'];
            
            // Check if user has a profile picture
            if (!empty($user_data['profile_picture'])) {
                // Set the profile picture path
                $user_avatar = '../uploads/profile_pictures/' . $user_data['profile_picture'];
                
                // If file doesn't exist, fallback to default
                if (!file_exists($user_avatar)) {
                    $user_avatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0OTYgNTEyIj48cGF0aCBmaWxsPSIjODg4IiBkPSJNMjQ4IDhDMTExIDggMCAxMTkgMCAyNTZzMTExIDI0OCAyNDggMjQ4IDI0OC0xMTEgMjQ4LTI0OFMzODUgOCAyNDggOHptMCA5NmM0OC42IDAgODggMzkuNCA4OCA4OHMtMzkuNCA4OC04OCA4OC04OC0zOS40LTg4LTg4IDM5LjQtODggODgtODh6bTAgMzQ0Yy01OC43IDAtMTExLjMtMjYuNi0xNDYuNS02OC4yIDE4LjgtMzUuNCAxLjMtMTE1LjMgNjIuNC0xNzIuMiA0LjUtNC4xIDEwLjktMy4xIDE0LjggMS42bDk5LjYgMTA3LjljNC40IDQuNyA0LjQgMTEuOS0uMSAxNi42bC00NC45IDQ1LjljLTYuNiA2LjgtMy41IDE3LjggNi43IDIwLjUgMTIuMiAzLjIgMjUuNiA1IDM5LjUgNSA1MyAwIDEwMS44LTE4LjcgMTQwLjYtNTAtLjEgNDguNi01Mi42IDExOS41LTE3Mi40IDExNC45eiIvPjwvc3ZnPg==';
                }
            }
        }
        
        // Get company name if user is employer
        if ($user_role === 'employer') {
            $stmt = $conn->prepare("SELECT company_name, company_logo FROM companies WHERE employer_id = ?");
            $stmt->execute([$user_id]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($company) {
                $user_name = $company['company_name'];
                
                // Check if company has a logo
                if (!empty($company['company_logo'])) {
                    // Set the company logo path
                    $user_avatar = '../uploads/company_logos/' . $company['company_logo'];
                    
                    // If file doesn't exist, fallback to default
                    if (!file_exists($user_avatar)) {
                        $user_avatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0OTYgNTEyIj48cGF0aCBmaWxsPSIjODg4IiBkPSJNMjQ4IDhDMTExIDggMCAxMTkgMCAyNTZzMTExIDI0OCAyNDggMjQ4IDI0OC0xMTEgMjQ4LTI0OFMzODUgOCAyNDggOHptMCA5NmM0OC42IDAgODggMzkuNCA4OCA4OHMtMzkuNCA4OC04OCA4OC04OC0zOS40LTg4LTg4IDM5LjQtODggODgtODh6bTAgMzQ0Yy01OC43IDAtMTExLjMtMjYuNi0xNDYuNS02OC4yIDE4LjgtMzUuNCAxLjMtMTE1LjMgNjIuNC0xNzIuMiA0LjUtNC4xIDEwLjktMy4xIDE0LjggMS42bDk5LjYgMTA3LjljNC40IDQuNyA0LjQgMTEuOS0uMSAxNi42bC00NC45IDQ1LjljLTYuNiA2LjgtMy41IDE3LjggNi43IDIwLjUgMTIuMiAzLjIgMjUuNiA1IDM5LjUgNSA1MyAwIDEwMS44LTE4LjcgMTQwLjYtNTAtLjEgNDguNi01Mi42IDExOS41LTE3Mi40IDExNC45eiIvPjwvc3ZnPg==';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        // Silent error - just use default values
    }
}
?>

<div class="header-container">
    <div class="header-logo">
        <svg class="logo-img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
            <path fill="#2c3e50" d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM471 143c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/>
        </svg>
        <div class="logo-text">JOB PORTAL SYSTEM</div>
    </div>
    <div class="header-user">
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="user-role"><?php echo ucfirst($user_role); ?></div>
        </div>
        <div class="user-avatar">
            <img src="<?php echo $user_avatar; ?>" alt="User Avatar">
        </div>
    </div>
</div> 