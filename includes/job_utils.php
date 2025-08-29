<?php
// Function to notify job seekers with matching skills
function notifyMatchingJobSeekers($conn, $job_id) {
    try {
        // Find job seekers with matching skills
        $stmt = $conn->prepare("
            SELECT 
                js.jobseeker_id as user_id,
                COUNT(DISTINCT js.skill_id) as matching_skills_count,
                COUNT(DISTINCT jsk.skill_id) as total_skills_required,
                SUM(s.priority) as matching_priority_sum,
                SUM(s2.priority) as total_priority_sum,
                GROUP_CONCAT(DISTINCT s.skill_name) as matching_skills_list
            FROM job_skills jsk
            JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id
            JOIN skills s ON js.skill_id = s.skill_id
            JOIN skills s2 ON jsk.skill_id = s2.skill_id
            WHERE jsk.job_id = ?
            GROUP BY js.jobseeker_id
            HAVING matching_skills_count > 0
        ");
        $stmt->execute([$job_id]);
        $matching_jobseekers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get job details
        $stmt = $conn->prepare("
            SELECT j.title, c.company_name
            FROM jobs j
            JOIN companies c ON j.company_id = c.company_id
            WHERE j.job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify each matching job seeker
        $notified_count = 0;
        foreach ($matching_jobseekers as $jobseeker) {
            // Calculate match score (priority-weighted)
            $match_score = 0;
            if ($jobseeker['total_priority_sum'] > 0) {
                $match_score = ($jobseeker['matching_priority_sum'] / $jobseeker['total_priority_sum']) * 100;
            } else {
                $match_score = ($jobseeker['matching_skills_count'] / $jobseeker['total_skills_required']) * 100;
            }
            
            // Only notify for good matches (50% or higher)
            if ($match_score >= 50) {
                // Create notification
                $title = "New Job Match: {$job['title']}";
                $message = "Your skills match a new job posting from {$job['company_name']}. " . 
                          "Match score: " . round($match_score) . "%. " .
                          "Matching skills: {$jobseeker['matching_skills_list']}";
                
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, related_id, related_type, created_at, is_read)
                    VALUES (?, ?, ?, ?, 'job', NOW(), 0)
                ");
                $result = $stmt->execute([
                    $jobseeker['user_id'], 
                    $title, 
                    $message, 
                    $job_id
                ]);
                
                if ($result) {
                    $notified_count++;
                }
            }
        }
        
        return $notified_count;
    } catch (Exception $e) {
        error_log("Error notifying job seekers: " . $e->getMessage());
        return 0;
    }
}
?> 