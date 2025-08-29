<?php
/**
 * Functions for matching jobs with jobseeker skills
 */

/**
 * Get matching jobs for a jobseeker based on their skills
 *
 * @param PDO $conn Database connection
 * @param int $jobseeker_id Jobseeker ID
 * @param int|null $skill_id Optional skill ID to filter by
 * @param int $limit Maximum number of jobs to return (0 for unlimited)
 * @return array Array of matching jobs
 */
function getMatchingJobs($conn, $jobseeker_id, $skill_id = null, $limit = 0) {
    try {
        // First, get all the skills of the jobseeker
        $stmt = $conn->prepare("
            SELECT skill_id FROM jobseeker_skills WHERE jobseeker_id = :jobseeker_id
        ");
        $stmt->bindParam(':jobseeker_id', $jobseeker_id, PDO::PARAM_INT);
        $stmt->execute();
        $jobseeker_skills = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($jobseeker_skills)) {
            return [];
        }
        
        // Get all active jobs
        $query = "
            SELECT j.*, c.company_name, c.company_logo
            FROM jobs j
            JOIN companies c ON j.company_id = c.company_id
            WHERE j.status = 'active' AND j.approval_status = 'approved'
        ";
        
        // Add filter by specific skill if requested
        if ($skill_id !== null) {
            $query .= " AND j.job_id IN (
                SELECT DISTINCT job_id FROM job_skills WHERE skill_id = :skill_id
            )";
        }
        
        $query .= " ORDER BY j.posted_date DESC";
        
        if ($limit > 0) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $conn->prepare($query);
        
        if ($skill_id !== null) {
            $stmt->bindParam(':skill_id', $skill_id, PDO::PARAM_INT);
        }
        
        if ($limit > 0) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $matching_jobs = [];
        
        // For each job, calculate the match percentage
        foreach ($jobs as $job) {
            // Get all skills required for the job
            $stmt = $conn->prepare("
                SELECT js.skill_id, s.skill_name 
                FROM job_skills js
                JOIN skills s ON js.skill_id = s.skill_id
                WHERE js.job_id = :job_id
            ");
            $stmt->bindParam(':job_id', $job['job_id'], PDO::PARAM_INT);
            $stmt->execute();
            $job_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($job_skills)) {
                continue; // Skip jobs with no skills specified
            }
            
            // Calculate matching skills
            $total_skills_required = count($job_skills);
            $matching_skills_count = 0;
            $matching_skills_list = [];
            
            foreach ($job_skills as $skill) {
                if (in_array($skill['skill_id'], $jobseeker_skills)) {
                    $matching_skills_count++;
                    $matching_skills_list[] = $skill['skill_name'];
                }
            }
            
            // Calculate percentage
            $match_percentage = ($matching_skills_count / $total_skills_required) * 100;
            
            // Only include jobs with at least one matching skill
            if ($matching_skills_count > 0) {
                $job['total_skills_required'] = $total_skills_required;
                $job['matching_skills'] = $matching_skills_count;
                $job['match_percentage'] = round($match_percentage);
                $job['matching_skills_list'] = implode(',', $matching_skills_list);
                $matching_jobs[] = $job;
            }
        }
        
        // Sort by match percentage (high to low)
        usort($matching_jobs, function($a, $b) {
            return $b['match_percentage'] - $a['match_percentage'];
        });
        
        return $matching_jobs;
    } catch (Exception $e) {
        // Log error and return empty array
        error_log("Error in getMatchingJobs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of jobs matching jobseeker skills
 *
 * @param PDO $conn Database connection
 * @param int $jobseeker_id Jobseeker ID
 * @return int Count of matching jobs
 */
function getMatchingJobsCount($conn, $jobseeker_id) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT j.job_id) as matching_jobs_count
            FROM jobs j
            JOIN job_skills jsk ON j.job_id = jsk.job_id
            JOIN jobseeker_skills js ON jsk.skill_id = js.skill_id
            WHERE j.status = 'active' AND js.jobseeker_id = ?
        ");
        $stmt->execute([$jobseeker_id]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error in getMatchingJobsCount: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate match score between a job and jobseeker
 *
 * @param PDO $conn Database connection
 * @param int $job_id Job ID
 * @param int $jobseeker_id Jobseeker ID
 * @return array Match data including score, matching skills, and missing skills
 */
function calculateJobMatch($conn, $job_id, $jobseeker_id) {
    try {
        // Get jobseeker skills
        $stmt = $conn->prepare("
            SELECT skill_id FROM jobseeker_skills WHERE jobseeker_id = :jobseeker_id
        ");
        $stmt->bindParam(':jobseeker_id', $jobseeker_id, PDO::PARAM_INT);
        $stmt->execute();
        $jobseeker_skills = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get job skills
        $stmt = $conn->prepare("
            SELECT js.skill_id, s.skill_name 
            FROM job_skills js
            JOIN skills s ON js.skill_id = s.skill_id
            WHERE js.job_id = :job_id
        ");
        $stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
        $stmt->execute();
        $job_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get company logo
        $stmt = $conn->prepare("
            SELECT c.company_logo
            FROM jobs j
            JOIN companies c ON j.company_id = c.company_id
            WHERE j.job_id = :job_id
        ");
        $stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
        $stmt->execute();
        $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate matching and missing skills
        $total_skills = count($job_skills);
        $matching_skills = 0;
        $matching_skills_list = [];
        $missing_skills_list = [];
        
        foreach ($job_skills as $skill) {
            if (in_array($skill['skill_id'], $jobseeker_skills)) {
                $matching_skills++;
                $matching_skills_list[] = $skill['skill_name'];
            } else {
                $missing_skills_list[] = $skill['skill_name'];
            }
        }
        
        // Calculate percentage
        $match_percentage = $total_skills > 0 ? ($matching_skills / $total_skills) * 100 : 0;
        
        return [
            'matching_skills' => $matching_skills,
            'total_skills' => $total_skills,
            'match_percentage' => round($match_percentage),
            'matching_skills_list' => implode(', ', $matching_skills_list),
            'missing_skills_list' => implode(', ', $missing_skills_list),
            'company_logo' => $company_data['company_logo'] ?? ''
        ];
    } catch (Exception $e) {
        error_log("Error in calculateJobMatch: " . $e->getMessage());
        return [
            'matching_skills' => 0,
            'total_skills' => 0,
            'match_percentage' => 0,
            'matching_skills_list' => '',
            'missing_skills_list' => '',
            'company_logo' => ''
        ];
    }
} 