<?php
require_once 'config/database.php';

try {
    // Create job_categories table
    $sql = "CREATE TABLE IF NOT EXISTS job_categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($sql);
    echo "Job categories table created successfully.<br>";
    
    // Add category_id column to jobs table if it doesn't exist
    $stmt = $conn->query("SHOW COLUMNS FROM `jobs` LIKE 'category_id'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE jobs ADD COLUMN category_id INT NULL AFTER company_id";
        $conn->exec($sql);
        echo "Category_id column added to jobs table.<br>";
        
        // Add foreign key constraint
        $sql = "ALTER TABLE jobs ADD CONSTRAINT fk_job_category FOREIGN KEY (category_id) REFERENCES job_categories(category_id) ON DELETE SET NULL";
        $conn->exec($sql);
        echo "Foreign key constraint added.<br>";
    } else {
        echo "Category_id column already exists in jobs table.<br>";
    }
    
    // Insert some default categories
    $categories = [
        ['Information Technology', 'Software development, networking, system administration, etc.'],
        ['Healthcare', 'Medical, nursing, pharmacy, and related fields'],
        ['Education', 'Teaching, training, academic research'],
        ['Finance', 'Banking, accounting, financial analysis'],
        ['Marketing', 'Digital marketing, advertising, public relations'],
        ['Sales', 'Business development, account management, retail'],
        ['Customer Service', 'Call center, customer support, client relations'],
        ['Engineering', 'Civil, mechanical, electrical engineering'],
        ['Administrative', 'Office management, clerical work, executive assistance'],
        ['Human Resources', 'Recruiting, HR management, training & development']
    ];
    
    $stmt = $conn->prepare("INSERT INTO job_categories (category_name, description) VALUES (?, ?)");
    foreach ($categories as $category) {
        try {
            $stmt->execute($category);
        } catch (PDOException $e) {
            // Skip if category already exists
            if ($e->getCode() != 23000) { // 23000 is duplicate entry error
                throw $e;
            }
        }
    }
    echo "Default categories added (if they didn't already exist).<br>";
    
    echo "Setup completed successfully!";
    
} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?> 