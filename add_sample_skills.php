<?php
// Script to add sample skills to the database

try {
    require_once 'config/database.php';
    
    // Sample skills with priorities (1-5)
    $skills = [
        ['PHP', 'PHP programming language', 5],
        ['JavaScript', 'Client-side scripting language', 5],
        ['HTML', 'Hypertext Markup Language', 4],
        ['CSS', 'Cascading Style Sheets', 4],
        ['MySQL', 'Relational database management system', 4],
        ['React', 'JavaScript library for building user interfaces', 3],
        ['Angular', 'JavaScript framework for web applications', 3],
        ['Node.js', 'JavaScript runtime environment', 3],
        ['Python', 'General-purpose programming language', 3],
        ['Java', 'Object-oriented programming language', 2],
        ['C#', 'Programming language for .NET applications', 2],
        ['Ruby', 'Dynamic, object-oriented programming language', 2],
        ['Git', 'Version control system', 2],
        ['Docker', 'Containerization platform', 2],
        ['AWS', 'Amazon Web Services cloud platform', 1],
        ['Azure', 'Microsoft cloud computing service', 1],
        ['Laravel', 'PHP web application framework', 1],
        ['WordPress', 'Content management system', 1],
        ['Bootstrap', 'CSS framework', 1],
        ['jQuery', 'JavaScript library', 1]
    ];
    
    // Clear existing skills
    $conn->exec("DELETE FROM skills");
    // Reset auto increment
    $conn->exec("ALTER TABLE skills AUTO_INCREMENT = 1");
    
    // Insert skills
    $stmt = $conn->prepare("INSERT INTO skills (skill_name, description, priority) VALUES (?, ?, ?)");
    $count = 0;
    
    foreach ($skills as $skill) {
        try {
            $stmt->execute($skill);
            $count++;
        } catch (Exception $e) {
            echo "Error adding skill {$skill[0]}: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>Added $count sample skills to the database</h2>";
    echo "<p>You can now visit <a href='jobseeker/skills.php'>the skills page</a> to manage your skills and see the word cloud visualization.</p>";
    
} catch (Exception $e) {
    echo "<h3>Error:</h3> " . $e->getMessage();
}
?> 