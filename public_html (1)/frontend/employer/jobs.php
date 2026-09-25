<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "My Job Postings";
$user_id = getCurrentUserId();

// Get employer ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$stmt->close();

// Handle status update (only for own jobs)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $job_id = intval($_POST['job_id']);
    $new_status = $_POST['status'];
    
    // Verify the job belongs to this employer before updating
    $stmt = $conn->prepare("UPDATE job_postings SET status = ? WHERE job_id = ? AND employer_id = ?");
    $stmt->bind_param("sii", $new_status, $job_id, $employer_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['success_message'] = "Job status updated successfully!";
    header('Location: jobs.php');
    exit;
}

// Handle regenerate recommendations for ALL jobs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate_all_recommendations'])) {
    // Get all active jobs for this employer
    $stmt = $conn->prepare("SELECT job_id FROM job_postings WHERE employer_id = ? AND status = 'active'");
    $stmt->bind_param("i", $employer_id);
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $total_recommendations_created = 0;
    $jobs_processed = 0;
    
    foreach ($jobs as $job) {
        $job_id = $job['job_id'];
        
        // Get job details
        $stmt_job = $conn->prepare("SELECT * FROM job_postings WHERE job_id = ?");
        $stmt_job->bind_param("i", $job_id);
        $stmt_job->execute();
        $job_details = $stmt_job->get_result()->fetch_assoc();
        $stmt_job->close();
        
        // Get all active applicants who have completed chatbot assessment
        $stmt = $conn->prepare("
            SELECT DISTINCT a.*, u.user_id 
            FROM applicants a 
            JOIN users u ON a.user_id = u.user_id 
            WHERE u.status = 'active'
            AND EXISTS (
                SELECT 1 FROM chatbot_answers ca WHERE ca.applicant_id = a.applicant_id
            )
        ");
        $stmt->execute();
        $applicants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Calculate match scores and create recommendations
        $recommendations_created = 0;
        foreach ($applicants as $applicant) {
            // Calculate match score (simplified version - same logic as post_job.php)
            $score = 0;
            $chatbot_answers = [];
            $stmt_chat = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
            $stmt_chat->bind_param("i", $applicant['applicant_id']);
            $stmt_chat->execute();
            $chatbot_answers = $stmt_chat->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_chat->close();
            
            // Skills matching (0-25 points)
            if (!empty($job_details['skills_required'])) {
                $required_skills = array_map('trim', explode(',', strtolower($job_details['skills_required'])));
                $total_required = count($required_skills);
                if ($total_required > 0) {
                    if (!empty($applicant['skills'])) {
                        $applicant_skills = array_map('trim', explode(',', strtolower($applicant['skills'])));
                        $matched = count(array_intersect($required_skills, $applicant_skills));
                        $score += ($matched / $total_required) * 25;
                    }
                    if (!empty($chatbot_answers)) {
                        $chatbot_skill_matches = 0;
                        foreach ($chatbot_answers as $answer) {
                            $answer_text = strtolower($answer['answer_text'] ?? '');
                            foreach ($required_skills as $req_skill) {
                                if (stripos($answer_text, $req_skill) !== false) {
                                    $chatbot_skill_matches++;
                                    break;
                                }
                            }
                        }
                        if ($chatbot_skill_matches > 0) {
                            $score += min(5, ($chatbot_skill_matches / count($chatbot_answers)) * 5);
                        }
                    }
                }
            }
            
            // Qualifications matching (0-20 points)
            if (!empty($applicant['qualifications'])) {
                $stmt_qual = $conn->prepare("SELECT jqm.qualification_id FROM job_qualification_mapping jqm WHERE jqm.job_id = ?");
                $stmt_qual->bind_param("i", $job_id);
                $stmt_qual->execute();
                $job_qualifications = $stmt_qual->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_qual->close();
                if (!empty($job_qualifications)) {
                    $applicant_qual = strtolower($applicant['qualifications']);
                    foreach ($job_qualifications as $job_qual) {
                        $qual_stmt = $conn->prepare("SELECT name FROM qualifications WHERE qualification_id = ?");
                        $qual_stmt->bind_param("i", $job_qual['qualification_id']);
                        $qual_stmt->execute();
                        $qual_name = $qual_stmt->get_result()->fetch_assoc()['name'] ?? '';
                        $qual_stmt->close();
                        if (stripos($applicant_qual, strtolower($qual_name)) !== false) {
                            $score += 20;
                            break;
                        }
                    }
                } else {
                    $score += 10;
                }
            }
            
            // Chatbot answers analysis (0-45 points)
            if (!empty($chatbot_answers)) {
                $category_scores = ['experience' => 0, 'technical' => 0, 'education' => 0, 'soft_skills' => 0, 'certifications' => 0, 'flexibility' => 0, 'learning' => 0];
                $total_value = 0;
                foreach ($chatbot_answers as $answer) {
                    $answer_text = $answer['answer_text'] ?? '';
                    $answer_value = intval($answer['answer_value'] ?? 0);
                    $total_value += $answer_value;
                    $category = 'general';
                    if (strpos($answer_text, '|CATEGORY:') !== false) {
                        $parts = explode('|CATEGORY:', $answer_text);
                        $category = isset($parts[1]) ? trim($parts[1]) : 'general';
                    }
                    if (isset($category_scores[$category])) {
                        $category_scores[$category] += $answer_value;
                    }
                }
                $max_possible_per_question = 45;
                $max_possible = count($chatbot_answers) * $max_possible_per_question;
                if ($max_possible > 0) {
                    $base_chatbot_score = ($total_value / $max_possible) * 35;
                    $technical_bonus = min(5, ($category_scores['technical'] / max(1, $total_value)) * 5);
                    $experience_bonus = min(5, ($category_scores['experience'] / max(1, $total_value)) * 5);
                    $chatbot_score = $base_chatbot_score + $technical_bonus + $experience_bonus;
                    $score += min(45, $chatbot_score);
                }
            }
            
            // Experience (0-15 points)
            if (!empty($applicant['experience_years'])) {
                $score += min(15, $applicant['experience_years'] * 3);
            }
            
            // Bonus for chatbot completion (0-5 points)
            if (!empty($chatbot_answers) && count($chatbot_answers) >= 8) {
                $score += 5;
            }
            
            // Bonus for employability score (0-5 points)
            if (!empty($applicant['employability_score']) && $applicant['employability_score'] > 0) {
                $score += min(5, ($applicant['employability_score'] / 100) * 5);
            }
            
            $match_score = min(100, round($score, 2));
            $employability_score = floatval($applicant['employability_score'] ?? 0);
            
            // Recommend if: match score >= 50% OR employability score >= 70%
            $should_recommend = false;
            $reason = "";
            $recommendation_score = $match_score;
            
            if ($match_score >= 50) {
                $should_recommend = true;
                $reason = "Strong match based on chatbot assessment answers and job requirements";
                $recommendation_score = $match_score;
            } elseif ($employability_score >= 70) {
                $should_recommend = true;
                $reason = "High employability score (" . number_format($employability_score, 1) . "%) - Strong candidate based on chatbot assessment";
                $recommendation_score = ($match_score * 0.4) + ($employability_score * 0.6);
            }
            
            if ($should_recommend) {
                $check_stmt = $conn->prepare("SELECT recommendation_id FROM candidate_recommendations WHERE employer_id = ? AND job_id = ? AND applicant_id = ?");
                $check_stmt->bind_param("iii", $employer_id, $job_id, $applicant['applicant_id']);
                $check_stmt->execute();
                $exists = $check_stmt->get_result()->num_rows > 0;
                $check_stmt->close();
                
                $recommendation_score = min(100, round($recommendation_score, 2));
                
                if (!$exists) {
                    $stmt = $conn->prepare("INSERT INTO candidate_recommendations (employer_id, job_id, applicant_id, recommendation_score, reason) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiids", $employer_id, $job_id, $applicant['applicant_id'], $recommendation_score, $reason);
                    $stmt->execute();
                    $stmt->close();
                    $recommendations_created++;
                    $total_recommendations_created++;
                } else {
                    $stmt = $conn->prepare("UPDATE candidate_recommendations SET recommendation_score = ?, reason = ? WHERE employer_id = ? AND job_id = ? AND applicant_id = ?");
                    $stmt->bind_param("dsiii", $recommendation_score, $reason, $employer_id, $job_id, $applicant['applicant_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        $jobs_processed++;
    }
    
    $regenerate_success = "Recommendations regenerated successfully for all $jobs_processed active jobs! Total $total_recommendations_created candidate(s) recommended.";
}

// Get all jobs from all employers (public to all employers)
$stmt = $conn->prepare("
    SELECT jp.*, 
           e.company_name,
           e.employer_id as job_employer_id,
           COUNT(DISTINCT a.application_id) as application_count,
           COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.application_id END) as pending_count,
           CASE WHEN jp.employer_id = ? THEN 1 ELSE 0 END as is_my_job
    FROM job_postings jp
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN applications a ON jp.job_id = a.job_id
    GROUP BY jp.job_id
    ORDER BY is_my_job DESC, jp.posted_at DESC
");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unread message count for the floating icon
$unread_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $unread_count = $row['unread_count'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Silently fail - don't break the page if message count fails
    error_log("Error getting unread count: " . $e->getMessage());
}

$conn->close();

include '../includes/header.php';
?>

<!-- Full Width Header Section -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 1.5rem 0; margin: 0 -8px 1rem -8px;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.3rem; font-size: 1.5rem;">
                <i class="fas fa-briefcase"></i> My Job Postings
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                Manage and track all your job postings in one place
            </p>
        </div>
    </div>
</div>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0;">
        
        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; align-items: stretch;">
            <!-- Total Jobs Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#1866a3';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-briefcase" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php echo count(array_filter($jobs, function($job) { return $job['is_my_job']; })); ?>
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">My Jobs</p>
            </div>
            
            <!-- Active Jobs Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#4CAF50';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php echo count(array_filter($jobs, function($job) { return $job['is_my_job'] && $job['status'] === 'active'; })); ?>
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Active Jobs</p>
            </div>
            
            <!-- Total Applications Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#ff6b00';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #ff6b00 0%, #e55a00 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-paper-plane" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php echo array_sum(array_column(array_filter($jobs, function($job) { return $job['is_my_job']; }), 'application_count')); ?>
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Total Applications</p>
            </div>
            
            <!-- Pending Review Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#2196F3';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-clock" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php echo array_sum(array_column(array_filter($jobs, function($job) { return $job['is_my_job']; }), 'pending_count')); ?>
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Pending Review</p>
            </div>
        </div>
        
        <!-- AI Recommendations Section -->
        <div style="margin-bottom: 1.5rem;">
            <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #9C27B0;">
                <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 600;">
                    <i class="fas fa-robot"></i> AI-Powered Candidate Recommendations
                </h3>
                <p style="color: #666; margin-bottom: 1rem; line-height: 1.5; font-size: 0.9rem;">
                    Refresh candidate recommendations for all your active jobs based on the latest chatbot assessment data.
                </p>
                <div style="display: flex; gap: 0.8rem; flex-wrap: wrap;">
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="regenerate_all_recommendations" 
                                style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; padding: 0.6rem 1.2rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(156, 39, 176, 0.3)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';"
                                onclick="return confirm('This will refresh candidate recommendations for ALL your active jobs. Continue?')">
                            <i class="fas fa-sync-alt"></i> Refresh All Recommendations
                        </button>
                    </form>
                    <a href="post_job.php" 
                       style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(24, 102, 163, 0.3)';"
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-plus"></i> Post New Job
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #4CAF50;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($regenerate_success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #4CAF50;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($regenerate_success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Job Postings Section -->
        <div>
            <h2 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                <i class="fas fa-list"></i> Job Postings
            </h2>
            <?php if (count($jobs) > 0): ?>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach ($jobs as $job): ?>
                        <div style="border: 1px solid #e0e0e0; padding: 1rem; border-radius: 6px; transition: all 0.3s ease; background: white;"
                             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#1866a3';"
                             onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                            
                            <!-- Job Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.8rem;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                                        <h4 style="color: #1f1e1eff; margin: 0; font-size: 1.1rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </h4>
                                        <?php if ($job['is_my_job']): ?>
                                            
                                        <?php else: ?>
                                            <span style="background: #2196F3; color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                                PUBLIC
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($job['company_name'])): ?>
                                    <p style="color: #666; margin-bottom: 0.3rem; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.8rem; margin-bottom: 0.5rem;">
                                        <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location'] ?? 'Location Not Specified'); ?>
                                        </p>
                                        <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-briefcase"></i> <?php echo ucfirst($job['employment_type']); ?>
                                        </p>
                                        <?php if (!empty($job['salary_range'])): ?>
                                        <p style="color: #4CAF50; font-weight: 600; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($job['salary_range']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p style="color: #666; margin: 0; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-calendar"></i> Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?>
                                    </p>
                                    
                                    <!-- Job Stats -->
                                    <div style="display: flex; gap: 1.5rem; margin-top: 0.8rem;">
                                        <span style="color: #2196F3; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-file-alt"></i> <?php echo $job['application_count']; ?> Applications
                                        </span>
                                        <span style="color: #FF9800; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-clock"></i> <?php echo $job['pending_count']; ?> Pending
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <div style="text-align: right;">
                                    <?php
                                    $status_colors = [
                                        'active' => '#4CAF50',
                                        'closed' => '#F44336',
                                        'draft' => '#9E9E9E'
                                    ];
                                    $color = $status_colors[$job['status']] ?? '#666';
                                    ?>
                                    <span style="background: <?php echo $color; ?>; color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-weight: 600; text-transform: uppercase; font-size: 0.7rem;">
                                        <?php echo $job['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.8rem;">
                                <?php if ($job['is_my_job']): ?>
                                    <!-- Management Actions -->
                                    <a href="edit_job.php?id=<?php echo $job['job_id']; ?>" 
                                       style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                                       onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(255, 152, 0, 0.3)';"
                                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php endif; ?>
                                
                                <!-- View Candidates -->
                                <a href="recommended_candidates.php?job_id=<?php echo $job['job_id']; ?>" 
                                   style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                                   onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(76, 175, 80, 0.3)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-star"></i> Recommended
                                </a>
                                
                                <a href="candidates.php?job_id=<?php echo $job['job_id']; ?>" 
                                   style="background: #2196F3; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                                   onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(33, 150, 243, 0.3)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-users"></i> All Candidates
                                </a>
                            </div>
                            
                            <!-- Quick Status Update Form -->
                            <?php if ($job['is_my_job']): ?>
                                <form method="POST" style="margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid #e0e0e0;">
                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                    <div style="display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                                        <span style="font-weight: 600; color: #666; font-size: 0.8rem;">Quick Status Update:</span>
                                        <select name="status" 
                                                style="padding: 0.5rem; border: 2px solid #e0e0e0; border-radius: 4px; background: white; font-size: 0.8rem; min-width: 100px;"
                                                onfocus="this.style.borderColor='#1866a3'; this.style.boxShadow='0 0 0 3px rgba(24, 102, 163, 0.1)';"
                                                onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                                            <option value="active" <?php echo ($job['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="closed" <?php echo ($job['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                                            <option value="draft" <?php echo ($job['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                        </select>
                                        <button type="submit" name="update_status" 
                                                style="background: #0056b3; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.8rem; transition: all 0.3s ease;"
                                                onmouseover="this.style.background='#004494'; this.style.transform='translateY(-1px)';">
                                            Update Status
                                        </button>
                                    </div>
                                    <small style="color: #666; margin-top: 0.5rem; display: block; font-size: 0.75rem;">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Active:</strong> Visible to applicants | 
                                        <strong>Draft:</strong> Only visible to you | 
                                        <strong>Closed:</strong> No longer accepting applications
                                    </small>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-briefcase" style="font-size: 2rem; color: #ddd; margin-bottom: 0.5rem;"></i>
                    <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1.1rem;">No jobs posted yet</h3>
                    <p style="font-size: 0.9rem;">Start by posting your first job to attract qualified candidates.</p>
                    <a href="post_job.php" 
                       style="background: #1866a3; color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.8rem; font-size: 0.85rem;">
                        <i class="fas fa-plus-circle"></i>
                        Post a Job
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Floating Message Icon -->
<style>
.floating-message-container {
    position: fixed;
    bottom: 25px;
    right: 25px;
    z-index: 10000;
    transition: all 0.3s ease;
}

.floating-message-btn {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(0, 86, 179, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    border: none;
    font-size: 1.4rem;
    position: relative;
}

.floating-message-btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 86, 179, 0.5);
    color: white;
    text-decoration: none;
}

.floating-message-btn:active {
    transform: translateY(-1px) scale(1.02);
}

.message-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: #F44336;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border: 3px solid white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { 
        transform: scale(1); 
        box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7);
    }
    50% { 
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(244, 67, 54, 0);
    }
    100% { 
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(244, 67, 54, 0);
    }
}

.message-tooltip {
    position: absolute;
    right: 75px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.message-tooltip::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 100%;
    transform: translateY(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: transparent transparent transparent rgba(0, 0, 0, 0.8);
}

.floating-message-btn:hover .message-tooltip {
    opacity: 1;
    visibility: visible;
    right: 80px;
}

/* Ensure it stays above all other content */
.floating-message-container * {
    box-sizing: border-box;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .floating-message-container {
        bottom: 20px;
        right: 20px;
    }
    
    .floating-message-btn {
        width: 60px;
        height: 60px;
        font-size: 1.3rem;
    }
    
    .message-badge {
        width: 22px;
        height: 22px;
        font-size: 0.7rem;
        border-width: 2px;
    }
    
    .message-tooltip {
        display: none; /* Hide tooltip on mobile */
    }
}

@media (max-width: 480px) {
    .floating-message-container {
        bottom: 15px;
        right: 15px;
    }
    
    .floating-message-btn {
        width: 55px;
        height: 55px;
        font-size: 1.2rem;
        box-shadow: 0 3px 15px rgba(0, 86, 179, 0.4);
    }
    
    .message-badge {
        width: 20px;
        height: 20px;
        font-size: 0.65rem;
        top: -2px;
        right: -2px;
    }
}

/* Print styles - hide when printing */
@media print {
    .floating-message-container {
        display: none !important;
    }
}
</style>

<div class="floating-message-container">
    <a href="chat.php" class="floating-message-btn" title="Chat">
        <i class="fas fa-comments"></i>
        <?php if ($unread_count > 0): ?>
            <span class="message-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
        <?php endif; ?>
        <span class="message-tooltip">
            <?php echo $unread_count > 0 ? "You have $unread_count unread message(s)" : "Go to Chat"; ?>
        </span>
    </a>
</div>

<script>
// Remove loading class if it exists
document.addEventListener('DOMContentLoaded', function() {
    const content = document.getElementById('jobPostingsContent');
    if (content) {
        content.classList.remove('loading');
    }

    // Optional: Add interactive effects to floating message button
    const floatingBtn = document.querySelector('.floating-message-btn');
    
    if (floatingBtn) {
        // Add click animation
        floatingBtn.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: translate(-50%, -50%);
                animation: ripple 0.6s ease-out;
            `;
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        width: 200%;
                        height: 200%;
                        opacity: 0;
                    }
                }
            `;
            
            document.head.appendChild(style);
            this.appendChild(ripple);
            
            setTimeout(() => {
                if (ripple.parentNode) {
                    ripple.parentNode.removeChild(ripple);
                }
                if (style.parentNode) {
                    style.parentNode.removeChild(style);
                }
            }, 600);
        });
        
        // Prevent hiding on scroll
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const container = document.querySelector('.floating-message-container');
            
            if (container) {
                if (scrollTop > lastScrollTop) {
                    // Scrolling down
                    container.style.transform = 'translateY(0)';
                } else {
                    // Scrolling up
                    container.style.transform = 'translateY(0)';
                }
            }
            lastScrollTop = scrollTop;
        });
    }

    // Auto-update message count every 30 seconds
    function updateFloatingMessageCount() {
        fetch('../includes/handlers/message_handler.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateMessageBadge(data.count);
                }
            })
            .catch(error => console.error('Error updating message count:', error));
    }

    function updateMessageBadge(count) {
        const badge = document.querySelector('.message-badge');
        const tooltip = document.querySelector('.message-tooltip');
        const btn = document.querySelector('.floating-message-btn');
        
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 9 ? '9+' : count;
            } else {
                // Create badge if it doesn't exist
                const newBadge = document.createElement('span');
                newBadge.className = 'message-badge';
                newBadge.textContent = count > 9 ? '9+' : count;
                btn.appendChild(newBadge);
            }
            
            // Update tooltip
            if (tooltip) {
                tooltip.textContent = `You have ${count} unread message(s)`;
            }
        } else {
            // Remove badge if no unread messages
            if (badge) {
                badge.remove();
            }
            if (tooltip) {
                tooltip.textContent = 'Go to Chat';
            }
        }
    }

    // Start auto-refresh (only if user is logged in)
    if (document.querySelector('.floating-message-btn')) {
        setInterval(updateFloatingMessageCount, 30000); // Every 30 seconds
    }
});
</script>

<?php include '../includes/footer.php'; ?>