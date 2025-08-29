<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "Only JPG, PNG and GIF images are allowed.";
        header("Location: dashboard.php");
        exit();
    }

    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "File size must be less than 5MB.";
        header("Location: dashboard.php");
        exit();
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $upload_dir = '../uploads/profile_pictures/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $upload_path = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Update database
        try {
            // Delete old profile picture if exists
            $stmt = $conn->prepare("SELECT profile_picture FROM user_profiles WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $old_picture = $stmt->fetchColumn();
            
            if ($old_picture && file_exists('../' . $old_picture)) {
                unlink('../' . $old_picture);
            }
            
            // Update database with new picture
            $stmt = $conn->prepare("UPDATE user_profiles SET profile_picture = ? WHERE user_id = ?");
            $stmt->execute(['uploads/profile_pictures/' . $filename, $_SESSION['user_id']]);
            $_SESSION['success'] = "Profile picture updated successfully.";
        } catch(Exception $e) {
            $_SESSION['error'] = "Failed to update profile picture.";
        }
    } else {
        $_SESSION['error'] = "Failed to upload file.";
    }

    header("Location: dashboard.php");
    exit();
}
?> 