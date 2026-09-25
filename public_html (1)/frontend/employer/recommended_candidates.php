<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Recommended Candidates";
$user_id = getCurrentUserId();

// Get employer ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$stmt->close();

// Get job filter
$job_id = $_GET['job_id'] ?? 0;

// Get recommended candidates based on chatbot answers and job requirements
$query = "
    SELECT DISTINCT
        cr.recommendation_id,
        cr.recommendation_score,
        cr.reason,
        cr.created_at as recommended_at,
        ap.applicant_id,
        ap.employability_score,
        ap.qualifications,
        ap.skills,
        ap.experience_years,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        jp.job_id,
        jp.title as job_title,
        jp.description as job_description,
        jp.skills_required,
        jp.requirements,
        e.company_name,
        CASE WHEN jp.employer_id = ? THEN 1 ELSE 0 END as is_my_job,
        (SELECT COUNT(*) FROM chatbot_answers WHERE applicant_id = ap.applicant_id) as chatbot_answers_count
    FROM candidate_recommendations cr
    JOIN applicants ap ON cr.applicant_id = ap.applicant_id
    JOIN users u ON ap.user_id = u.user_id
    JOIN job_postings jp ON cr.job_id = jp.job_id
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    WHERE cr.employer_id = ?
";

if ($job_id > 0) {
    $query .= " AND jp.job_id = ?";
    $query .= " ORDER BY cr.recommendation_score DESC, ap.employability_score DESC, cr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $employer_id, $employer_id, $job_id);
} else {
    $query .= " ORDER BY cr.recommendation_score DESC, ap.employability_score DESC, cr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $employer_id, $employer_id);
}

$stmt->execute();
$recommended_candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all jobs for filter (only employer's jobs)
$stmt = $conn->prepare("
    SELECT jp.job_id, jp.title, e.company_name 
    FROM job_postings jp 
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    WHERE jp.employer_id = ? AND jp.status != 'draft'
    ORDER BY jp.posted_at DESC
");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to calculate match score like in view_candidate.php
function calculateMatchScore($conn, $applicant_id, $job_id, $employability_score) {
    // Get job details
    $stmt_job = $conn->prepare("SELECT * FROM job_postings WHERE job_id = ?");
    $stmt_job->bind_param("i", $job_id);
    $stmt_job->execute();
    $job_details = $stmt_job->get_result()->fetch_assoc();
    $stmt_job->close();
    
    // Get applicant details
    $stmt_app = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
    $stmt_app->bind_param("i", $applicant_id);
    $stmt_app->execute();
    $applicant = $stmt_app->get_result()->fetch_assoc();
    $stmt_app->close();
    
    // Get chatbot answers
    $chatbot_answers = [];
    $stmt_chat = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
    $stmt_chat->bind_param("i", $applicant_id);
    $stmt_chat->execute();
    $chatbot_answers = $stmt_chat->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_chat->close();
    
    // Calculate match score
    $score = 0;
    
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
    if (!empty($employability_score) && $employability_score > 0) {
        $score += min(5, ($employability_score / 100) * 5);
    }
    
    $match_score = min(100, round($score, 2));
    
    // Determine recommendation reason
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
    
    return [
        'match_score' => $match_score,
        'recommendation_score' => $recommendation_score,
        'reason' => $reason,
        'should_recommend' => $should_recommend
    ];
}

// Check if candidate has applied
function hasApplied($conn, $applicant_id, $job_id) {
    $stmt = $conn->prepare("SELECT application_id FROM applications WHERE applicant_id = ? AND job_id = ?");
    $stmt->bind_param("ii", $applicant_id, $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_applied = $result->num_rows > 0;
    $stmt->close();
    return $has_applied;
}

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
                <i class="fas fa-star"></i> Recommended Candidates
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                AI-powered candidate matches based on chatbot assessments and employability scores
            </p>
        </div>
    </div>
</div>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0;">
        
        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; align-items: stretch;">
            <!-- Total Candidates Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#9C27B0';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-users" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php echo count($recommended_candidates); ?>
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Total Recommendations</p>
            </div>
            
            <!-- High Score Candidates Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#4CAF50';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-chart-line" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php 
                    $high_score_count = 0;
                    foreach ($recommended_candidates as $candidate) {
                        if ($candidate['recommendation_score'] >= 70) {
                            $high_score_count++;
                        }
                    }
                    echo $high_score_count;
                    ?>
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">High Score (70%+)</p>
            </div>
            
            <!-- Average Match Score Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#2196F3';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-percentage" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php 
                    $avg_score = 0;
                    if (count($recommended_candidates) > 0) {
                        $total_score = 0;
                        foreach ($recommended_candidates as $candidate) {
                            $total_score += $candidate['recommendation_score'];
                        }
                        $avg_score = round($total_score / count($recommended_candidates), 1);
                    }
                    echo $avg_score;
                    ?>%
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Avg Recommendation Score</p>
            </div>
            
            <!-- Chatbot Assessed Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#FF9800';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-robot" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;">
                    <?php 
                    $chatbot_count = 0;
                    foreach ($recommended_candidates as $candidate) {
                        if ($candidate['chatbot_answers_count'] > 0) {
                            $chatbot_count++;
                        }
                    }
                    echo $chatbot_count;
                    ?>
                </h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Chatbot Assessed</p>
            </div>
        </div>
        
        <!-- Job Filter Section -->
        <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #0056b3; margin-bottom: 1.5rem;">
            <h3 style="color: #333; margin-bottom: 0.8rem; font-size: 1.1rem; font-weight: 600;">
                <i class="fas fa-filter"></i> Filter Candidates by Job
            </h3>
            <form method="GET" style="display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap;">
                <select name="job_id" 
                        style="padding: 0.75rem 1rem; border: 2px solid #e0e0e0; border-radius: 6px; min-width: 300px; font-family: inherit; font-size: 0.9rem; background: white;"
                        onfocus="this.style.borderColor='#1866a3'; this.style.boxShadow='0 0 0 3px rgba(24, 102, 163, 0.1)';"
                        onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                    <option value="0">All Jobs</option>
                    <?php foreach ($jobs as $job): ?>
                        <option value="<?php echo $job['job_id']; ?>" <?php echo ($job_id == $job['job_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($job['title']); ?>
                            <?php if ($job['company_name']): ?>
                                - <?php echo htmlspecialchars($job['company_name']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" 
                        style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.9rem;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(24, 102, 163, 0.3)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <i class="fas fa-filter"></i> Filter Candidates
                </button>
                <?php if ($job_id > 0): ?>
                    <a href="recommend_candidates.php" 
                       style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.9rem;"
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(108, 117, 125, 0.3)';"
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-times"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </form>
            <p style="color: #666; margin-top: 0.8rem; line-height: 1.5; font-size: 0.85rem;">
                <i class="fas fa-info-circle"></i> Candidates are recommended if they have: 
                <strong>Match score ≥ 50%</strong> OR <strong>Employability score ≥ 70%</strong>.
                <br>Scores are calculated using the same algorithm as the candidate profile view.
            </p>
        </div>
        
        <!-- Navigation Links -->
        <div style="display: flex; gap: 0.8rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="jobs.php" 
               style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(24, 102, 163, 0.3)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="fas fa-briefcase"></i> My Job Postings
            </a>
            <a href="candidates.php" 
               style="background: #2196F3; color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(33, 150, 243, 0.3)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="fas fa-users"></i> All Candidates
            </a>
            <a href="post_job.php" 
               style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(76, 175, 80, 0.3)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="fas fa-plus"></i> Post New Job
            </a>
        </div>
        
        <!-- Recommended Candidates Section -->
        <div>
            <h2 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                <i class="fas fa-star"></i> Recommended Candidates
                <?php if ($job_id > 0): ?>
                    <span style="font-size: 0.9rem; color: #666; font-weight: normal; margin-left: 0.5rem;">
                        (Filtered by Job)
                    </span>
                <?php endif; ?>
            </h2>
            
            <?php if (count($recommended_candidates) > 0): ?>
                <div style="display: grid; gap: 1.2rem;">
                    <?php foreach ($recommended_candidates as $candidate): 
                        // Re-establish connection for each candidate
                        $conn_temp = getDBConnection();
                        $applied = hasApplied($conn_temp, $candidate['applicant_id'], $candidate['job_id']);
                        
                        // Get chatbot qualification category
                        $stmt_qual = $conn_temp->prepare("
                            SELECT q.name as qualification_name 
                            FROM chatbot_answers ca 
                            LEFT JOIN qualifications q ON ca.qualification_id = q.qualification_id 
                            WHERE ca.applicant_id = ? 
                            LIMIT 1
                        ");
                        $stmt_qual->bind_param("i", $candidate['applicant_id']);
                        $stmt_qual->execute();
                        $qual_result = $stmt_qual->get_result();
                        $qualification_name = $qual_result->num_rows > 0 ? $qual_result->fetch_assoc()['qualification_name'] : '';
                        $stmt_qual->close();
                        
                        // Calculate match score using the same function as view_candidate.php
                        $employability_score_raw = $candidate['employability_score'];
                        $score_data = calculateMatchScore($conn_temp, $candidate['applicant_id'], $candidate['job_id'], $employability_score_raw);
                        
                        $conn_temp->close();
                    ?>
                        <div style="border: 1px solid #e0e0e0; padding: 1.5rem; border-radius: 8px; transition: all 0.3s ease; background: white;"
                             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#1866a3';"
                             onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                            
                            <!-- Candidate Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                                        <h3 style="color: #1f1e1eff; margin: 0; font-size: 1.1rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                        </h3>
                                        <?php if ($applied): ?>
                                            <span style="background: #4CAF50; color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                                <i class="fas fa-check"></i> Applied
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($score_data['recommendation_score'] >= 80): ?>
                                            <span style="background: #FF9800; color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                                <i class="fas fa-fire"></i> Top Match
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-briefcase"></i> Recommended for: <strong><?php echo htmlspecialchars($candidate['job_title']); ?></strong>
                                    </p>
                                    
                                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.8rem;">
                                        <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['email']); ?>
                                        </p>
                                        <?php if (!empty($candidate['phone'])): ?>
                                        <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($candidate['phone']); ?>
                                        </p>
                                        <?php endif; ?>
                                        <?php if (!empty($qualification_name)): ?>
                                        <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($qualification_name); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Removed all score cards -->
                                    
                                    <?php if (!empty($candidate['skills'])): ?>
                                        <div style="margin-bottom: 0.8rem;">
                                            <p style="color: #666; font-size: 0.85rem; margin-bottom: 0.4rem; font-weight: 500;">
                                                <i class="fas fa-code"></i> Skills:
                                            </p>
                                            <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                                                <?php 
                                                $skills = array_map('trim', explode(',', $candidate['skills']));
                                                foreach (array_slice($skills, 0, 5) as $skill): 
                                                ?>
                                                    <span style="background: #e3f2fd; color: #1976d2; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                                                        <?php echo htmlspecialchars($skill); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($skills) > 5): ?>
                                                    <span style="color: #666; font-size: 0.75rem; padding: 0.2rem 0.6rem;">
                                                        +<?php echo count($skills) - 5; ?> more
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($candidate['reason'])): ?>
                                        <div style="background: #f8f9fa; padding: 0.8rem; border-radius: 6px; margin-bottom: 0.8rem;">
                                            <p style="color: #666; font-size: 0.85rem; margin: 0; line-height: 1.4;">
                                                <i class="fas fa-info-circle" style="color: #0056b3;"></i> 
                                                <strong>Recommendation Reason:</strong> <?php echo htmlspecialchars($candidate['reason']); ?>
                                            </p>
                                        </div>
                                    <?php elseif (!empty($score_data['reason'])): ?>
                                        <div style="background: #f8f9fa; padding: 0.8rem; border-radius: 6px; margin-bottom: 0.8rem;">
                                            <p style="color: #666; font-size: 0.85rem; margin: 0; line-height: 1.4;">
                                                <i class="fas fa-info-circle" style="color: #0056b3;"></i> 
                                                <strong>Recommendation Reason:</strong> <?php echo htmlspecialchars($score_data['reason']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Timestamp -->
                                <div style="text-align: right;">
                                    <p style="color: #999; font-size: 0.75rem; margin: 0;">
                                        Recommended: <?php echo date('M d, Y', strtotime($candidate['recommended_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                <?php 
                                // Find application ID if candidate has applied
                                $conn_temp = getDBConnection();
                                $stmt_app = $conn_temp->prepare("SELECT application_id FROM applications WHERE applicant_id = ? AND job_id = ?");
                                $stmt_app->bind_param("ii", $candidate['applicant_id'], $candidate['job_id']);
                                $stmt_app->execute();
                                $app_result = $stmt_app->get_result();
                                $application_id = $app_result->num_rows > 0 ? $app_result->fetch_assoc()['application_id'] : null;
                                $stmt_app->close();
                                $conn_temp->close();
                                
                                if ($application_id):
                                ?>
                                    <a href="view_candidate.php?id=<?php echo $application_id; ?>" 
                                       style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                                       onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(24, 102, 163, 0.3)';"
                                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                        <i class="fas fa-eye"></i> View Application
                                    </a>
                                <?php else: ?>
                                    <a href="view_candidate.php?applicant_id=<?php echo $candidate['applicant_id']; ?>&job_id=<?php echo $candidate['job_id']; ?>" 
                                       style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                                       onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(24, 102, 163, 0.3)';"
                                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                        <i class="fas fa-user"></i> View Profile
                                    </a>
                                <?php endif; ?>
                                
                                <a href="chatbot_review.php?applicant_id=<?php echo $candidate['applicant_id']; ?>&job_id=<?php echo $candidate['job_id']; ?>" 
                                   style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                                   onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(156, 39, 176, 0.3)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-robot"></i> View Assessment
                                </a>
                                
                                <a href="jobs.php" 
                                   style="background: #2196F3; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                                   onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(33, 150, 243, 0.3)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-briefcase"></i> View Job
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem 2rem; background: #f8f9fa; border-radius: 8px; border: 2px dashed #ddd;">
                    <i class="fas fa-star" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <h3 style="color: #666; margin-bottom: 0.5rem; font-size: 1.1rem;">No Recommended Candidates Found</h3>
                    <p style="color: #999; margin-bottom: 1.5rem; font-size: 0.9rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                        <?php if ($job_id > 0): ?>
                            No candidates have been recommended for this specific job yet. 
                            Candidates are recommended if they have a match score ≥ 50% OR employability score ≥ 70%.
                            <br><br>
                            <small>Go to the Jobs page and click "Refresh Recommendations" to regenerate AI-powered matches.</small>
                        <?php else: ?>
                            No candidates have been recommended for any of your jobs yet. 
                            Post a job and use the "Refresh Recommendations" feature to get AI-powered candidate matches.
                        <?php endif; ?>
                    </p>
                    <div style="display: flex; gap: 0.8rem; justify-content: center; flex-wrap: wrap;">
                        <a href="jobs.php" 
                           style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                            <i class="fas fa-briefcase"></i> Go to Jobs
                        </a>
                        <a href="post_job.php" 
                           style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                            <i class="fas fa-plus"></i> Post New Job
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Floating Message Icon (Same as jobs.php) -->
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
        display: none;
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
// Interactive effects for floating message button
document.addEventListener('DOMContentLoaded', function() {
    const floatingBtn = document.querySelector('.floating-message-btn');
    
    if (floatingBtn) {
        floatingBtn.addEventListener('click', function(e) {
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
    }

    // Auto-update message count
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
                const newBadge = document.createElement('span');
                newBadge.className = 'message-badge';
                newBadge.textContent = count > 9 ? '9+' : count;
                btn.appendChild(newBadge);
            }
            
            if (tooltip) {
                tooltip.textContent = `You have ${count} unread message(s)`;
            }
        } else {
            if (badge) {
                badge.remove();
            }
            if (tooltip) {
                tooltip.textContent = 'Go to Chat';
            }
        }
    }

    if (document.querySelector('.floating-message-btn')) {
        setInterval(updateFloatingMessageCount, 30000);
    }
});
</script>

<?php include '../includes/footer.php'; ?>