<?php
session_start();
require_once '../config/database.php';

// Restrict to logged-in admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$error = null;
$success = null;

$userId = $_SESSION['user_id'];

// Load current admin user and profile
try {
    $stmt = $conn->prepare("SELECT u.user_id, u.username, u.email, u.password, up.first_name, up.last_name, up.phone, up.profile_picture
                             FROM users u
                             LEFT JOIN user_profiles up ON u.user_id = up.user_id
                             WHERE u.user_id = ?");
    $stmt->execute([$userId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        throw new Exception('User not found.');
    }
} catch (Exception $e) {
    $error = 'Failed to load profile: ' . $e->getMessage();
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        $conn->beginTransaction();

        // Basic fields
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($username === '' || $email === '') {
            throw new Exception('Username and email are required.');
        }

        // Ensure username/email unique (excluding current user)
        $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND user_id != ?');
        $stmt->execute([$username, $email, $userId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Username or email already in use.');
        }

        // Update users table (without password first)
        $stmt = $conn->prepare('UPDATE users SET username = ?, email = ? WHERE user_id = ?');
        $stmt->execute([$username, $email, $userId]);

        // Password change is optional; only proceed when new and confirm provided
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
            if ($newPassword !== '' && $confirmPassword !== '') {
                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New password and confirmation do not match.');
                }
                // If current password provided, verify; otherwise allow update without requiring it
                if ($currentPassword !== '' && !password_verify($currentPassword, $current['password'])) {
                    throw new Exception('Current password is incorrect.');
                }
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $stmt->execute([$hashed, $userId]);
            } // else: ignore partial input
        }

        // Handle profile picture upload/removal
        $removeProfilePicture = isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] === '1';
        $profilePicturePath = null; // null means unchanged, 'REMOVE' means remove

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['profile_picture']['type'], $allowedTypes)) {
                throw new Exception('Invalid profile picture type. Allowed: JPG, PNG, GIF.');
            }
            if ($_FILES['profile_picture']['size'] > $maxSize) {
                throw new Exception('Profile picture too large. Max 5MB.');
            }

            $uploadDir = '../uploads/profile_pictures/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $fileName = 'profile_' . $userId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                throw new Exception('Failed to upload profile picture.');
            }

            // Delete old picture if exists
            if (!empty($current['profile_picture'])) {
                $old = '../' . $current['profile_picture'];
                if (file_exists($old)) {
                    @unlink($old);
                }
            }

            $profilePicturePath = 'uploads/profile_pictures/' . $fileName;
        } elseif ($removeProfilePicture) {
            if (!empty($current['profile_picture'])) {
                $old = '../' . $current['profile_picture'];
                if (file_exists($old)) {
                    @unlink($old);
                }
            }
            $profilePicturePath = 'REMOVE';
        }

        // Upsert into user_profiles
        if ($profilePicturePath === 'REMOVE') {
            $stmt = $conn->prepare(
                'INSERT INTO user_profiles (user_id, first_name, last_name, phone, profile_picture)
                 VALUES (?, ?, ?, ?, NULL)
                 ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), phone = VALUES(phone), profile_picture = NULL'
            );
            $stmt->execute([$userId, $firstName, $lastName, $phone]);
        } elseif ($profilePicturePath) {
            $stmt = $conn->prepare(
                'INSERT INTO user_profiles (user_id, first_name, last_name, phone, profile_picture)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), phone = VALUES(phone), profile_picture = VALUES(profile_picture)'
            );
            $stmt->execute([$userId, $firstName, $lastName, $phone, $profilePicturePath]);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO user_profiles (user_id, first_name, last_name, phone)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), phone = VALUES(phone)'
            );
            $stmt->execute([$userId, $firstName, $lastName, $phone]);
        }

        $conn->commit();
        $success = 'Profile updated successfully.';

        // Reload current data for re-render
        $stmt = $conn->prepare("SELECT u.user_id, u.username, u.email, u.password, up.first_name, up.last_name, up.phone, up.profile_picture
                                 FROM users u
                                 LEFT JOIN user_profiles up ON u.user_id = up.user_id
                                 WHERE u.user_id = ?");
        $stmt->execute([$userId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="text-center mb-2 mt-1">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <span>Jobs</span>
                </a>
            </li>
            <li>
                <a href="categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Job Categories</span>
                </a>
            </li>
            <li>
                <a href="companies.php">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
                </a>
            </li>
            <li>
                <a href="applications.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li>
                <a href="job-fair-events.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Job Fair Events</span>
                </a>
            </li>
            <li>
                <a href="placements.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>Graduate Placements</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="active">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content page-transition">
        <?php include 'includes/header.php'; ?>

        <div class="container-fluid">
            <div class="page-header" data-aos="fade-down">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center position-relative">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-user-cog me-3"></i>
                                My Profile
                            </h1>
                            <p class="page-subtitle">Update your account information</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="modern-card p-4 mb-4">
                        <h5 class="mb-3">Profile Information</h5>
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-modern"><?php echo htmlspecialchars($error); ?></div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success alert-modern"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-modern">First Name</label>
                                    <input type="text" name="first_name" class="form-control form-control-modern" value="<?php echo htmlspecialchars($current['first_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-modern">Last Name</label>
                                    <input type="text" name="last_name" class="form-control form-control-modern" value="<?php echo htmlspecialchars($current['last_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-modern">Phone</label>
                                    <input type="text" name="phone" class="form-control form-control-modern" value="<?php echo htmlspecialchars($current['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-modern">Username</label>
                                    <input type="text" name="username" class="form-control form-control-modern" required value="<?php echo htmlspecialchars($current['username']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-modern">Email</label>
                                    <input type="email" name="email" class="form-control form-control-modern" required value="<?php echo htmlspecialchars($current['email']); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label-modern d-block">Profile Picture</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <div>
                                            <?php if (!empty($current['profile_picture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($current['profile_picture']); ?>" alt="Profile Picture" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid #e9ecef;">
                                            <?php else: ?>
                                                <div style="width:60px;height:60px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;color:#6c757d;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="file" name="profile_picture" accept="image/*" class="form-control form-control-modern">
                                            <div class="form-text">JPG, PNG or GIF. Max 5MB.</div>
                                            <?php if (!empty($current['profile_picture'])): ?>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="remove_profile_picture" value="1" id="removePic">
                                                <label class="form-check-label" for="removePic">Remove current picture</label>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Change Password</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label-modern">Current Password</label>
                                    <input type="password" name="current_password" class="form-control form-control-modern" autocomplete="current-password" placeholder="••••••••">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-modern">New Password</label>
                                    <input type="password" name="new_password" class="form-control form-control-modern" autocomplete="new-password" placeholder="••••••••">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-modern">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control form-control-modern" autocomplete="new-password" placeholder="••••••••">
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-modern btn-modern-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="modern-card p-4">
                        <h5 class="mb-3">Account Summary</h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><strong>Username:</strong> <?php echo htmlspecialchars($current['username']); ?></li>
                            <li class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($current['email']); ?></li>
                            <li class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars(($current['first_name'] ?? '') . ' ' . ($current['last_name'] ?? '')); ?></li>
                            <li class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($current['phone'] ?? ''); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        AOS.init({ duration: 800, easing: 'ease-in-out', once: true, offset: 100 });
    </script>
</body>
</html>


