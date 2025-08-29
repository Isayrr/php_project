<?php
// Define directories to create
$directories = [
    'uploads/profile_pictures',
    'uploads/resumes',
    'uploads/cover_letters'
];

// Create directories with proper permissions
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "Created directory: $dir <br>";
        } else {
            echo "Failed to create directory: $dir <br>";
        }
    } else {
        echo "Directory already exists: $dir <br>";
    }
}

echo "Directory setup completed!";
?> 