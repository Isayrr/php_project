<?php
// Get all active and approved jobs
$stmt = $conn->prepare("SELECT j.*, c.company_name 
                       FROM jobs j 
                       JOIN companies c ON j.company_id = c.company_id 
                       WHERE j.status = 'active' 
                       AND j.approval_status = 'approved'
                       ORDER BY j.posted_date DESC");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC); 