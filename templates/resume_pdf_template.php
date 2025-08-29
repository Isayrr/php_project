<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($resume['title']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.5;
        }
        .resume-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .resume-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
        }
        .resume-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .resume-title {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
        }
        .resume-contact {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 15px;
            color: #3498db;
        }
        .item {
            margin-bottom: 20px;
        }
        .item-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .item-subtitle {
            font-style: italic;
            margin-bottom: 5px;
        }
        .item-date {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .item-content {
            font-size: 14px;
        }
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .skill-tag {
            background-color: #f0f0f0;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 14px;
        }
        .personal-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="resume-container">
        <div class="resume-header">
            <?php 
            // Get personal info from metadata
            $personal_metadata = [];
            foreach ($sections as $sec) {
                if ($sec['section_type'] === 'personal' && !empty($sec['metadata'])) {
                    $personal_metadata = json_decode($sec['metadata'], true);
                    break;
                }
            }
            
            $name = !empty($personal_metadata['full_name']) ? $personal_metadata['full_name'] : ($profile['first_name'] . ' ' . $profile['last_name']);
            $title = !empty($personal_metadata['job_title']) ? $personal_metadata['job_title'] : ($profile['headline'] ?? '');
            ?>
            
            <div class="resume-name"><?php echo htmlspecialchars($name); ?></div>
            <?php if (!empty($title)): ?>
                <div class="resume-title"><?php echo htmlspecialchars($title); ?></div>
            <?php endif; ?>
            
            <div class="resume-contact">
                <?php if (!empty($personal_metadata['email'])): ?>
                    <div><?php echo htmlspecialchars($personal_metadata['email']); ?></div>
                <?php elseif (!empty($profile['email'])): ?>
                    <div><?php echo htmlspecialchars($profile['email']); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($personal_metadata['phone'])): ?>
                    <div><?php echo htmlspecialchars($personal_metadata['phone']); ?></div>
                <?php elseif (!empty($profile['phone'])): ?>
                    <div><?php echo htmlspecialchars($profile['phone']); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($personal_metadata['address'])): ?>
                    <div><?php echo htmlspecialchars($personal_metadata['address']); ?></div>
                <?php elseif (!empty($profile['address'])): ?>
<div><?php echo htmlspecialchars($profile['address']); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($personal_metadata['website'])): ?>
                    <div><?php echo htmlspecialchars($personal_metadata['website']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php foreach ($sections as $section): ?>
            <?php if ($section['section_type'] !== 'personal'): // Personal info already displayed in header ?>
                <div class="section">
                    <h2 class="section-title"><?php echo htmlspecialchars($section['section_title']); ?></h2>
                    
                    <?php if ($section['section_type'] === 'summary'): ?>
                        <!-- Summary Section -->
                        <div class="item-content">
                            <?php if (!empty($section['content'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($section['content'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted">No professional summary added yet.</p>
                            <?php endif; ?>
                        </div>
                    
                    <?php elseif ($section['section_type'] === 'education'): ?>
                        <!-- Education Section -->
                        <?php
                        // Get education entries for this section
                        $edu_stmt = $conn->prepare("SELECT * FROM resume_education WHERE section_id = ? ORDER BY order_index ASC");
                        $edu_stmt->execute([$section['section_id']]);
                        $education_entries = $edu_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($education_entries)):
                            foreach($education_entries as $edu):
                        ?>
                        <div class="item">
                            <div class="item-title"><?php echo htmlspecialchars($edu['institution']); ?></div>
                            <div class="item-subtitle">
                                <?php if (!empty($edu['degree']) || !empty($edu['field_of_study'])): ?>
                                    <?php echo htmlspecialchars($edu['degree'] ?? ''); ?>
                                    <?php if (!empty($edu['degree']) && !empty($edu['field_of_study'])) echo ' in '; ?>
                                    <?php echo htmlspecialchars($edu['field_of_study'] ?? ''); ?>
                                <?php endif; ?>
                            </div>
                            <div class="item-date">
                                <?php 
                                $start = !empty($edu['start_date']) ? date('Y', strtotime($edu['start_date'])) : '';
                                $end = $edu['is_current'] ? 'Present' : (!empty($edu['end_date']) ? date('Y', strtotime($edu['end_date'])) : '');
                                
                                if ($start || $end) {
                                    echo $start;
                                    if ($start && $end) echo ' - ';
                                    echo $end;
                                }
                                ?>
                            </div>
                            <?php if (!empty($edu['description'])): ?>
                            <div class="item-content">
                                <p><?php echo nl2br(htmlspecialchars($edu['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php 
                            endforeach;
                        endif; ?>
                        
                    <?php elseif ($section['section_type'] === 'experience'): ?>
                        <!-- Experience Section -->
                        <?php
                        // Get experience entries for this section
                        $exp_stmt = $conn->prepare("SELECT * FROM resume_experience WHERE section_id = ? ORDER BY order_index ASC");
                        $exp_stmt->execute([$section['section_id']]);
                        $experience_entries = $exp_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($experience_entries)):
                            foreach($experience_entries as $exp):
                        ?>
                        <div class="item">
                            <div class="item-title"><?php echo htmlspecialchars($exp['position']); ?></div>
                            <div class="item-subtitle">
                                <?php echo htmlspecialchars($exp['company']); ?>
                                <?php if (!empty($exp['location'])): ?>
                                    <span class="location"> - <?php echo htmlspecialchars($exp['location']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="item-date">
                                <?php 
                                $start = !empty($exp['start_date']) ? date('M Y', strtotime($exp['start_date'])) : '';
                                $end = $exp['is_current'] ? 'Present' : (!empty($exp['end_date']) ? date('M Y', strtotime($exp['end_date'])) : '');
                                
                                if ($start || $end) {
                                    echo $start;
                                    if ($start && $end) echo ' - ';
                                    echo $end;
                                }
                                ?>
                            </div>
                            <?php if (!empty($exp['description']) || !empty($exp['responsibilities'])): ?>
                            <div class="item-content">
                                <?php if (!empty($exp['description'])): ?>
                                    <p><?php echo nl2br(htmlspecialchars($exp['description'])); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($exp['responsibilities'])): ?>
                                    <p><?php echo nl2br(htmlspecialchars($exp['responsibilities'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php 
                            endforeach;
                        endif; ?>
                    
                    <?php elseif ($section['section_type'] === 'skills'): ?>
                        <!-- Skills Section -->
                        <?php
                        // Get skill entries for this section
                        $skill_stmt = $conn->prepare("SELECT * FROM resume_skills WHERE section_id = ? ORDER BY order_index ASC");
                        $skill_stmt->execute([$section['section_id']]);
                        $skill_entries = $skill_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($skill_entries)): ?>
                            <div class="skills-list">
                                <?php foreach($skill_entries as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- Other section types -->
                        <?php if (!empty($section['content'])): ?>
                            <div class="item-content">
                                <p><?php echo nl2br(htmlspecialchars($section['content'])); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</body>
</html> 