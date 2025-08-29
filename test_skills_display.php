<?php
require_once 'config/database.php';

try {
    // Get skills
    $stmt = $conn->prepare("SELECT * FROM skills ORDER BY priority DESC, skill_name");
    $stmt->execute();
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count jobseeker skills
    $stmt = $conn->prepare("SELECT COUNT(*) FROM jobseeker_skills");
    $stmt->execute();
    $jobseeker_skills_count = $stmt->fetchColumn();
    
    // Success
    $success = true;
} catch (Exception $e) {
    $error = $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .priority-label {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
            color: white;
            margin-right: 8px;
        }
        .word-cloud-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            position: relative;
            height: 400px;
        }
        .word-cloud {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .cloud-skill {
            position: absolute;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            padding: 5px;
            border-radius: 5px;
        }
        .cloud-skill:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        .cloud-priority-1 { font-size: 14px; opacity: 0.7; }
        .cloud-priority-2 { font-size: 18px; opacity: 0.8; }
        .cloud-priority-3 { font-size: 22px; opacity: 0.9; }
        .cloud-priority-4 { font-size: 26px; }
        .cloud-priority-5 { font-size: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Skills Test</h1>
                
                <?php if (!$success): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <strong>Database connection successful!</strong> Found <?php echo count($skills); ?> skills and <?php echo $jobseeker_skills_count; ?> jobseeker skill associations.
                    </div>
                    
                    <!-- Word Cloud Visualization -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Skills Word Cloud</h3>
                        </div>
                        <div class="card-body">
                            <div class="word-cloud-container">
                                <div class="word-cloud" id="skills-cloud">
                                    <?php 
                                    foreach ($skills as $skill): 
                                        // Determine priority
                                        $priority = isset($skill['priority']) ? min($skill['priority'], 5) : 1;
                                        // Generate random position
                                        $left = rand(5, 85);
                                        $top = rand(5, 85);
                                        // Generate random color in blue/green spectrum
                                        $hue = rand(180, 250);
                                        $sat = rand(60, 100);
                                        $light = rand(40, 70);
                                    ?>
                                    <a href="#" class="cloud-skill cloud-priority-<?php echo $priority; ?>" 
                                       data-skill-id="<?php echo $skill['skill_id']; ?>"
                                       style="left: <?php echo $left; ?>%; top: <?php echo $top; ?>%; color: hsl(<?php echo $hue; ?>, <?php echo $sat; ?>%, <?php echo $light; ?>%);">
                                        <?php echo htmlspecialchars($skill['skill_name']); ?>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Available Skills</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Skill Name</th>
                                            <th>Description</th>
                                            <th>Priority</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($skills as $skill): ?>
                                            <tr>
                                                <td><?php echo $skill['skill_id']; ?></td>
                                                <td>
                                                    <?php 
                                                    // Set color based on priority
                                                    $priorityColor = 'secondary';
                                                    if (isset($skill['priority'])) {
                                                        switch ($skill['priority']) {
                                                            case 5: $priorityColor = 'danger'; break;
                                                            case 4: $priorityColor = 'primary'; break;
                                                            case 3: $priorityColor = 'success'; break;
                                                            case 2: $priorityColor = 'info'; break;
                                                            default: $priorityColor = 'secondary';
                                                        }
                                                    }
                                                    ?>
                                                    <span class="priority-label bg-<?php echo $priorityColor; ?>"><?php echo $skill['priority'] ?? 1; ?></span>
                                                    <?php echo htmlspecialchars($skill['skill_name']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($skill['description'] ?? ''); ?></td>
                                                <td><?php echo $skill['priority'] ?? 1; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="jobseeker/skills.php" class="btn btn-primary">Go to Skills Management Page</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Word cloud dynamic positioning (to avoid overlaps as much as possible)
        function randomizeCloudPositions() {
            const cloudSkills = document.querySelectorAll('.cloud-skill');
            const positions = [];
            
            cloudSkills.forEach(skill => {
                let attempts = 0;
                let valid = false;
                let left, top;
                
                // Try to find non-overlapping position
                while (!valid && attempts < 50) {
                    left = Math.random() * 80 + 5;
                    top = Math.random() * 80 + 5;
                    
                    // Check for overlaps with existing positions
                    valid = true;
                    for (const pos of positions) {
                        const distance = Math.sqrt(
                            Math.pow(left - pos.left, 2) + 
                            Math.pow(top - pos.top, 2)
                        );
                        if (distance < 15) {
                            valid = false;
                            break;
                        }
                    }
                    
                    attempts++;
                }
                
                positions.push({ left, top });
                skill.style.left = left + '%';
                skill.style.top = top + '%';
            });
        }
        
        // Run when the document is loaded
        document.addEventListener('DOMContentLoaded', function() {
            randomizeCloudPositions();
        });
    </script>
</body>
</html> 