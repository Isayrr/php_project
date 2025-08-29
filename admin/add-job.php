<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Required fields
        $title = trim($_POST['title']);
        $company_id = $_POST['company_id'];
        $job_type = $_POST['job_type'];
        $location = trim($_POST['location']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        // Optional fields
        $salary_range = isset($_POST['salary_range']) ? trim($_POST['salary_range']) : null;
        $requirements = isset($_POST['requirements']) ? trim($_POST['requirements']) : null;
        $skills = isset($_POST['skills']) ? $_POST['skills'] : [];
        $category_id = isset($_POST['category_id']) && !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        $vacancies = isset($_POST['vacancies']) ? intval($_POST['vacancies']) : 1;
        
        // Validate required fields
        if (empty($title) || empty($company_id) || empty($job_type) || empty($location) || empty($description)) {
            throw new Exception("All required fields must be filled out.");
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Insert job
        $stmt = $conn->prepare("INSERT INTO jobs (title, company_id, job_type, location, description, 
                                salary_range, requirements, status, posted_date, category_id, vacancies) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([$title, $company_id, $job_type, $location, $description, $salary_range, $requirements, $status, $category_id, $vacancies]);
        $job_id = $conn->lastInsertId();
        
        // Insert job skills
        if (!empty($skills)) {
            $stmt = $conn->prepare("INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?)");
            foreach ($skills as $skill_id) {
                $stmt->execute([$job_id, $skill_id]);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message and redirect
        $_SESSION['success_message'] = "Job posted successfully!";
        header("Location: jobs.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Set error message and redirect
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: jobs.php");
        exit();
    }
} else {
    // If not POST request, redirect to jobs page
    header("Location: jobs.php");
    exit();
}