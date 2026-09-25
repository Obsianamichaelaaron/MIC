<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

$pageTitle = "Applicant Dashboard";
$user_id = getCurrentUserId();

// Get applicant stats
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, u.email FROM applicants a JOIN users u ON a.user_id = u.user_id WHERE a.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get application count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant['applicant_id']);
$stmt->execute();
$app_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get pending applications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE applicant_id = ? AND status = 'pending'");
$stmt->bind_param("i", $applicant['applicant_id']);
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get the LATEST employability score from API result
$latest_score = 0;
$score_source = 'Not assessed';
$stmt = $conn->prepare("SELECT employability_score, skills FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant['applicant_id']);
$stmt->execute();
$score_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($score_result) {
    $latest_score = $score_result['employability_score'] ?? 0;
    $score_source = $score_result['score_source'] ?? 'Not assessed';
    
    // Calculate skill bonus - +3 points for each skill
    $skill_bonus = 0;
    if (!empty($score_result['skills'])) {
        $skills_array = array_map('trim', explode(',', $score_result['skills']));
        $valid_skills = array_filter($skills_array); // Remove empty values
        $skill_bonus = count($valid_skills) * 3;
    }
    
    // Add skill bonus to the base score (cap at 100)
    $latest_score = min(100, $latest_score + $skill_bonus);
}

// Format the score for display
$display_score = number_format($latest_score, 2);
$score_color = '#0056b3'; // Default blue

// Set color based on score
if ($latest_score >= 70) {
    $score_color = '#4CAF50'; // Green for high scores
} elseif ($latest_score >= 50) {
    $score_color = '#FF9800'; // Orange for medium scores
} elseif ($latest_score > 0) {
    $score_color = '#f44336'; // Red for low scores
}

// Get score description
$score_description = "Not assessed";
if ($latest_score > 0) {
    if ($latest_score >= 80) {
        $score_description = "Excellent";
    } elseif ($latest_score >= 70) {
        $score_description = "Very Good";
    } elseif ($latest_score >= 60) {
        $score_description = "Good";
    } elseif ($latest_score >= 50) {
        $score_description = "Average";
    } else {
        $score_description = "Needs Improvement";
    }
}

// Get job recommendations - ONLY SHOW 50%+ MATCH SCORE
$recommendations = [];
$chatbot_completed = false;
$chatbot_answers = [];
$applicant_data = null;

if ($applicant['applicant_id']) {
    // Check if chatbot assessment is completed first
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chatbot_answers WHERE applicant_id = ?");
    $stmt->bind_param("i", $applicant['applicant_id']);
    $stmt->execute();
    $chatbot_result = $stmt->get_result()->fetch_assoc();
    $chatbot_completed = $chatbot_result['count'] > 0;
    $stmt->close();

    if ($chatbot_completed) {
        // Get applicant data for match score calculation - SAME AS JOBS.PHP
        $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
        $stmt->bind_param("i", $applicant['applicant_id']);
        $stmt->execute();
        $applicant_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get chatbot answers
        $stmt = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
        $stmt->bind_param("i", $applicant['applicant_id']);
        $stmt->execute();
        $chatbot_answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get active jobs and calculate match scores
        $stmt = $conn->prepare("SELECT jp.job_id, jp.title, jp.location, jp.description, jp.employment_type, jp.salary_range, jp.skills_required, jp.posted_at, e.company_name 
                               FROM job_postings jp 
                               LEFT JOIN employers e ON jp.employer_id = e.employer_id 
                               WHERE jp.status = 'active' 
                               ORDER BY jp.posted_at DESC");
        $stmt->execute();
        $all_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ========== EXACT SAME CALCULATION AS JOBS.PHP AND JOB_DETAILS.PHP ==========
        // Calculate match scores for each job
        foreach ($all_jobs as $job) {
            $score = 0;
            
            // 1. SKILLS MATCHING (40 points) - Most important - SAME AS JOBS.PHP
            if (!empty($job['skills_required']) && !empty($applicant_data['skills'])) {
                $required_skills = array_map('trim', explode(',', $job['skills_required']));
                
                $applicant_skills = !empty($applicant_data['skills']) ? 
                    array_map('trim', explode(',', $applicant_data['skills'])) : [];
                
                // Find matched skills (case-insensitive partial matching) - SAME AS JOBS.PHP
                $matched_skills = [];
                foreach ($required_skills as $skill) {
                    $skill_lower = strtolower(trim($skill));
                    $found = false;
                    foreach ($applicant_skills as $applicant_skill) {
                        $applicant_skill_lower = strtolower(trim($applicant_skill));
                        if ($applicant_skill_lower == $skill_lower || 
                            stripos($applicant_skill_lower, $skill_lower) !== false ||
                            stripos($skill_lower, $applicant_skill_lower) !== false) {
                            $matched_skills[] = trim($skill);
                            $found = true;
                            break;
                        }
                    }
                }
                
                if (!empty($required_skills)) {
                    $skills_score = (count($matched_skills) / count($required_skills)) * 40;
                    $skills_score = round($skills_score, 2);
                    $score += $skills_score;
                }
            }
            
            // 2. EXPERIENCE (25 points) - Second most important - SAME AS JOBS.PHP
            $experience_years = !empty($applicant_data['experience_years']) ? (int)$applicant_data['experience_years'] : 0;
            $exp_score = min(25, $experience_years * 5); // 5 points per year, max 25
            $exp_score = round($exp_score, 2);
            $score += $exp_score;
            
            // 3. QUALIFICATIONS MATCHING (15 points) - Third most important
            // Get job qualifications - SAME AS JOBS.PHP
            $job_qualifications = [];
            $qual_stmt = $conn->prepare("
                SELECT q.name 
                FROM qualifications q 
                INNER JOIN job_qualification_mapping jqm ON q.qualification_id = jqm.qualification_id 
                WHERE jqm.job_id = ?
            ");
            $qual_stmt->bind_param("i", $job['job_id']);
            $qual_stmt->execute();
            $job_qualifications = $qual_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $qual_stmt->close();
            
            if (!empty($job_qualifications)) {
                $applicant_qualifications = !empty($applicant_data['qualifications']) ? 
                    strtolower($applicant_data['qualifications']) : '';
                
                $matched_quals = [];
                foreach ($job_qualifications as $qual) {
                    $qual_name = strtolower(trim($qual['name']));
                    if (stripos($applicant_qualifications, $qual_name) !== false) {
                        $matched_quals[] = $qual;
                    }
                }
                
                if (!empty($job_qualifications)) {
                    $qual_score = (count($matched_quals) / count($job_qualifications)) * 15;
                    $qual_score = round($qual_score, 2);
                    $score += $qual_score;
                }
            } else {
                // If job has no specific qualifications, award 7.5 points (half of max) - SAME AS JOBS.PHP
                $score += 7.5;
            }
            
            // 4. CHATBOT ASSESSMENT (10 points) - Supplemental - SAME AS JOBS.PHP
            if (!empty($chatbot_answers)) {
                $total_value = 0;
                $max_possible = 0;
                
                foreach ($chatbot_answers as $answer) {
                    // Check for score_value column (your chatbot uses this)
                    if (isset($answer['score_value']) && is_numeric($answer['score_value'])) {
                        $score_value = intval($answer['score_value']);
                        $total_value += $score_value;
                        
                        // Determine max possible value based on score_value ranges
                        // Based on your chatbot values: 10, 15, 25, 35, 45, etc.
                        if ($score_value >= 40) {
                            $max_possible += 45;
                        } elseif ($score_value >= 30) {
                            $max_possible += 35;
                        } elseif ($score_value >= 20) {
                            $max_possible += 25;
                        } elseif ($score_value >= 10) {
                            $max_possible += 15;
                        } else {
                            $max_possible += 10;
                        }
                    }
                    // Fallback to answer_value if score_value doesn't exist
                    elseif (isset($answer['answer_value']) && is_numeric($answer['answer_value'])) {
                        $total_value += intval($answer['answer_value']);
                        $max_possible += 5; // Standard 5-point scale
                    }
                }
                
                if ($max_possible > 0) {
                    $chatbot_score = ($total_value / $max_possible) * 10;
                    $chatbot_score = round($chatbot_score, 2);
                    $score += $chatbot_score;
                }
            }
            
            // 5. BONUSES (10 points) - Extra points - SAME AS JOBS.PHP
            $bonus_total = 0;
            
            // Chatbot completion bonus (5 points)
            if (!empty($chatbot_answers) && count($chatbot_answers) >= 5) {
                $bonus_total += 5;
            }
            
            // Employability score bonus (5 points)
            if (!empty($applicant_data['employability_score']) && $applicant_data['employability_score'] > 0) {
                $emp_bonus = min(5, ($applicant_data['employability_score'] / 100) * 5);
                $emp_bonus = round($emp_bonus, 2);
                $bonus_total += $emp_bonus;
            }
            
            $score += $bonus_total;
            
            $final_score = min(100, round($score, 2));
            // ========== END OF EXACT SAME CALCULATION ==========
            
            // ONLY ADD TO RECOMMENDATIONS IF SCORE IS 50% OR HIGHER
            if ($final_score >= 50) {
                $job['calculated_match_score'] = $final_score;
                $recommendations[] = $job;
            }
        }

        // SORT RECOMMENDATIONS BY HIGHEST MATCH SCORE (DESCENDING ORDER)
        usort($recommendations, function($a, $b) {
            return $b['calculated_match_score'] <=> $a['calculated_match_score'];
        });

        // Limit to top 5 recommendations
        $recommendations = array_slice($recommendations, 0, 5);
    }
}

// Get recent feedback from employers
$feedback_list = [];
$table_check = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt_feedback = $conn->prepare("
        SELECT cf.*, e.company_name, u.first_name as employer_first, u.last_name as employer_last, jp.title as job_title, a.status as application_status
        FROM candidate_feedback cf
        JOIN employers e ON cf.employer_id = e.employer_id
        JOIN users u ON e.user_id = u.user_id
        JOIN applications a ON cf.application_id = a.application_id
        JOIN job_postings jp ON a.job_id = jp.job_id
        WHERE cf.applicant_id = ?
        ORDER BY cf.created_at DESC
        LIMIT 3
    ");
    $stmt_feedback->bind_param("i", $applicant['applicant_id']);
    $stmt_feedback->execute();
    $feedback_list = $stmt_feedback->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_feedback->close();
}

// Get feedback count
$feedback_count = 0;
if ($table_check && $table_check->num_rows > 0) {
    $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM candidate_feedback WHERE applicant_id = ?");
    $stmt_count->bind_param("i", $applicant['applicant_id']);
    $stmt_count->execute();
    $feedback_count = $stmt_count->get_result()->fetch_assoc()['count'];
    $stmt_count->close();
}

// Get unread message count for the floating icon (FOR APPLICANT)
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

<!-- ═══════════════════ DASHBOARD PAGE STYLES (Matching Profile Design) ═══════════════════ -->
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap');

/* ── Base ── */
.db-page { font-family: 'DM Sans', sans-serif; background: #f0f4f9; min-height: 100vh; }

/* ── Hero Banner ── */
.db-hero {
    background: linear-gradient(135deg, #0a3d6b 0%, #1866a3 55%, #2196f3 100%);
    padding: 2.5rem 0 4.5rem;
    position: relative;
    overflow: hidden;
}
.db-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url('../images/bg.jpg') center/cover no-repeat;
    opacity: 1px;
}
.db-hero::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0; right: 0;
    height: 60px;
    background: #f0f4f9;
    clip-path: ellipse(55% 100% at 50% 100%);
}
.db-hero-inner {
    position: relative;
    z-index: 1;
    text-align: center;
    color: #fff;
}
.db-hero-inner h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    font-weight: 400;
    margin: 0 0 .4rem;
    letter-spacing: .3px;
}
.db-hero-inner p {
    font-size: .92rem;
    color: rgba(255,255,255,.8);
    margin: 0;
}

/* ── Page shell - 3 COLUMN LAYOUT (sidebar | center | right) ── */
.db-shell {
    max-width: 1400px;
    margin: -2rem auto 4rem;
    padding: 0 1.25rem;
    display: grid;
    grid-template-columns: 280px 1fr 360px;
    gap: 1.75rem;
    align-items: start;
}

/* ── Sidebar (column 1) ── */
.db-sidebar {
    position: sticky;
    top: 80px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.db-id-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    overflow: hidden;
}
.db-id-top {
    background: linear-gradient(135deg, #1a2f5a, #ab2a85);
    padding: 1.6rem 1.2rem 3.5rem;
    text-align: center;
    position: relative;
}
.db-avatar {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,.18);
    border: 3px solid rgba(255,255,255,.5);
    margin: 0 auto 0;
    display: flex; align-items: center; justify-content: center;
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    color: #fff;
    letter-spacing: 1px;
}
.db-id-body { padding: 2.2rem 1.2rem 1.4rem; text-align: center; }
.db-id-name { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; margin-bottom: .2rem; }
.db-id-email { font-size: .8rem; color: #888; margin-bottom: .8rem; }
.db-id-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    background: #e8f0fe; color: #1866a3;
    font-size: .75rem; font-weight: 600;
    padding: .3rem .75rem; border-radius: 20px;
    margin-bottom: 1rem;
}
.db-id-stats {
    display: grid; grid-template-columns: 1fr 1fr 1fr;
    gap: .6rem; border-top: 1px solid #f0f0f0; padding-top: 1rem;
}
.db-stat { text-align: center; }
.db-stat-num { font-size: 1.3rem; font-weight: 700; color: #1866a3; }
.db-stat-lbl { font-size: .72rem; color: #999; }

/* Sidebar nav */
.db-sidenav {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    overflow: hidden;
}
.db-sidenav-title {
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1.2px; color: #aaa;
    padding: 1rem 1.2rem .5rem;
}
.db-sidenav a {
    display: flex; align-items: center; gap: .75rem;
    padding: .75rem 1.2rem;
    font-size: .87rem; font-weight: 500;
    color: #555; text-decoration: none;
    border-left: 3px solid transparent;
    transition: all .18s;
}
.db-sidenav a:hover, .db-sidenav a.active {
    background: #f0f5ff; color: #1866a3;
    border-left-color: #1866a3;
}
.db-sidenav a i { width: 18px; text-align: center; font-size: .85rem; }

/* ── Center Column (main content) ── */
.db-center {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* ── Right Column (job recommendations) ── */
.db-right {
    position: sticky;
    top: 80px;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Right column custom header */
.right-col-header {
   background: linear-gradient(135deg, #1a2f5a, #ab2a85);
    border-radius: 16px 16px 0 0;
    padding: 1rem 1.2rem;
    color: white;
}
.right-col-header h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.right-col-header p {
    font-size: 0.7rem;
    margin: 4px 0 0;
    opacity: 0.85;
}
.right-content {
    background: #fff;
    border-radius: 0 0 16px 16px;
    padding: 1.2rem;
}

/* Compact job card for right column */
.job-compact-card {
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    padding: 0.9rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
}
.job-compact-card:hover {
    border-color: #1866a3;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.job-compact-title {
    font-weight: 700;
    color: #1866a3;
    font-size: 0.85rem;
    margin-bottom: 4px;
}
.job-compact-company {
    font-size: 0.7rem;
    color: #888;
    margin-bottom: 6px;
}
.job-compact-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.65rem;
    color: #666;
    margin: 6px 0;
}
.match-badge-sm {
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.compact-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}
.compact-btn {
    padding: 4px 10px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.7rem;
    font-weight: 500;
}
.compact-btn-view { background: #1866a3; color: white; }
.compact-btn-apply { background: #ff6a00; color: white; }

/* Alert banners */
.db-alert {
    border-radius: 10px;
    padding: .9rem 1.1rem;
    font-size: .88rem;
    display: flex; align-items: flex-start; gap: .65rem;
    animation: db-fadein .3s ease;
}
@keyframes db-fadein { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.db-alert-success { background: #f0faf0; color: #2e7d32; border: 1px solid #c8e6c9; }
.db-alert-error   { background: #fff5f5; color: #c62828; border: 1px solid #ffcdd2; }
.db-alert-info    { background: #e8f4ff; color: #1565c0; border: 1px solid #bbdefb; }
.db-alert i { margin-top: .15rem; flex-shrink: 0; }

/* ── Section Cards (center) ── */
.db-section {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
    overflow: hidden;
    transition: box-shadow .25s;
}
.db-section:hover { box-shadow: 0 6px 28px rgba(0,0,0,.1); }
.db-section-head {
    padding: 1.4rem 1.75rem;
    border-bottom: 1px solid #f2f4f8;
    display: flex; align-items: center; gap: .7rem;
    margin-top: 20px;
}
.db-section-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: #e8f0fe; color: #1866a3;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; flex-shrink: 0;
}
.db-section-head h2 {
    font-size: 1rem; font-weight: 700; color: #1a1a2e; margin: 0;
}
.db-section-head p {
    font-size: .78rem; color: #999; margin: .1rem 0 0;
}
.db-section-body { padding: 1.5rem 1.75rem; }

/* Stats Grid (3 cards) */
.db-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}
.db-stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.2rem;
    text-align: center;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
    transition: all .25s;
    border: 1px solid #f0f0f0;
}
.db-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(0,0,0,.1);
}
.db-stat-icon {
    width: 48px; height: 48px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto .8rem;
    font-size: 1.2rem;
}
.db-stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: .2rem;
    line-height: 1.2;
}
.db-stat-label {
    font-size: .8rem;
    color: #666;
    font-weight: 500;
}
.db-stat-sub {
    font-size: .7rem;
    color: #999;
    margin-top: .2rem;
}

/* Quick Actions */
.db-quick-actions {
    background: #f8fafd;
    border-radius: 14px;
    padding: 1.2rem;
    border-left: 4px solid #9C27B0;
}
.db-quick-actions h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: .5rem;
}
.db-quick-actions p {
    font-size: .85rem;
    color: #666;
    margin-bottom: 1rem;
    line-height: 1.5;
}
.db-btn-group {
    display: flex;
    gap: .8rem;
    flex-wrap: wrap;
}
.db-btn {
    padding: .6rem 1.2rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    transition: all .2s;
    font-size: .85rem;
}
.db-btn-primary {
    background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
    color: white;
}
.db-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
}
.db-btn-secondary {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
}
.db-btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

/* Score info box */
.db-score-info {
    border-radius: 12px;
    padding: 1rem;
    border-left: 4px solid;
}
.db-score-info h4 {
    font-size: .9rem;
    font-weight: 600;
    margin-bottom: .5rem;
}
.db-score-info p {
    font-size: .85rem;
    line-height: 1.5;
    margin: 0;
}

/* Feedback cards (center) */
.db-feedback-card {
    border: 1.5px solid #f0f0f0;
    border-radius: 12px;
    padding: 1.2rem;
    background: #fff;
    transition: all .2s;
    margin-bottom: 1rem;
}
.db-feedback-card:hover {
    border-color: #1866a3;
    box-shadow: 0 4px 16px rgba(24,102,163,.1);
}
.db-feedback-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: .8rem;
}
.db-job-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1866a3;
    margin-bottom: .2rem;
}
.db-job-desc {
    font-size: .83rem;
    color: #666;
    line-height: 1.55;
    margin-bottom: 1rem;
}
.db-job-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .8rem;
    margin-bottom: .8rem;
}
.db-job-meta span {
    font-size: .8rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: .3rem;
}

/* Empty state */
.db-empty-state {
    text-align: center;
    padding: 2rem;
    color: #666;
}
.db-empty-state i {
    font-size: 2.5rem;
    color: #ddd;
    margin-bottom: .8rem;
}
.db-empty-state h3 {
    font-size: 1rem;
    color: #333;
    margin-bottom: .5rem;
}
.db-empty-state p {
    font-size: .85rem;
    margin-bottom: 1rem;
}

/* Floating chat */
.db-float {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
}
.db-float-btn {
    width: 58px; height: 58px; border-radius: 50%;
    background: linear-gradient(135deg, #1866a3, #0a3d6b);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; text-decoration: none;
    box-shadow: 0 6px 20px rgba(24,102,163,.45);
    transition: all .25s; position: relative;
}
.db-float-btn:hover { transform: translateY(-3px) scale(1.06); color: #fff; }
.db-float-badge {
    position: absolute; top: -3px; right: -3px;
    background: #f44336; color: #fff;
    border-radius: 50%; width: 22px; height: 22px;
    font-size: .7rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    border: 2.5px solid #fff;
    animation: db-pulse 2s infinite;
}
@keyframes db-pulse {
    0%  { box-shadow: 0 0 0 0 rgba(244,67,54,.6); }
    70% { box-shadow: 0 0 0 8px rgba(244,67,54,0); }
    100%{ box-shadow: 0 0 0 0 rgba(244,67,54,0); }
}
.db-float-tip {
    position: absolute; right: 68px; top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,.78); color: #fff;
    padding: .45rem .9rem; border-radius: 7px;
    font-size: .78rem; white-space: nowrap;
    opacity: 0; visibility: hidden; transition: all .2s;
    pointer-events: none;
}
.db-float-tip::after {
    content: ''; position: absolute;
    top: 50%; left: 100%; transform: translateY(-50%);
    border: 5px solid transparent;
    border-left-color: rgba(0,0,0,.78);
}
.db-float-btn:hover .db-float-tip { opacity: 1; visibility: visible; right: 72px; }

/* Dark mode support */
[data-theme="dark"] .db-page             { background: #0f1117; }
[data-theme="dark"] .db-hero::after      { background: #0f1117; }
[data-theme="dark"] .db-id-card,
[data-theme="dark"] .db-sidenav,
[data-theme="dark"] .db-section,
[data-theme="dark"] .db-stat-card,
[data-theme="dark"] .right-content        { background: #1a1d27; box-shadow: 0 2px 16px rgba(0,0,0,.3); }
[data-theme="dark"] .db-section-head     { border-bottom-color: #2d3348; }
[data-theme="dark"] .db-section-head h2,
[data-theme="dark"] .db-id-name,
[data-theme="dark"] .db-stat-value       { color: #e2e8f0; }
[data-theme="dark"] .db-id-email,
[data-theme="dark"] .db-stat-label,
[data-theme="dark"] .db-stat-lbl         { color: #94a3b8; }
[data-theme="dark"] .db-quick-actions    { background: #1e2235; }
[data-theme="dark"] .db-quick-actions h3,
[data-theme="dark"] .db-quick-actions p  { color: #cbd5e1; }
[data-theme="dark"] .job-compact-card,
[data-theme="dark"] .db-feedback-card    { background: #1e2235; border-color: #2d3348; }
[data-theme="dark"] .db-job-title,
[data-theme="dark"] .job-compact-title   { color: #7aa2f7; }
[data-theme="dark"] .db-job-desc,
[data-theme="dark"] .job-compact-company { color: #94a3b8; }
[data-theme="dark"] .db-sidenav a        { color: #94a3b8; }
[data-theme="dark"] .db-sidenav a:hover,
[data-theme="dark"] .db-sidenav a.active { background: rgba(99,179,237,.08); color: #7aa2f7; border-left-color: #7aa2f7; }

/* Responsive */
@media (max-width: 1100px) {
    .db-shell {
        grid-template-columns: 260px 1fr;
    }
    .db-right {
        grid-column: span 2;
        position: static;
    }
}
@media (max-width: 768px) {
    .db-shell {
        grid-template-columns: 1fr;
    }
    .db-right {
        grid-column: span 1;
    }
    .db-stats-grid { grid-template-columns: 1fr; gap: .8rem; }
}
@media (max-width: 480px) {
    .db-hero-inner h1 { font-size: 1.5rem; }
    .db-shell { padding: 0 .75rem; margin-top: -1.5rem; }
    .db-section-body { padding: 1rem; }
    .db-float { bottom: 16px; right: 16px; }
}
</style>

<div class="db-page">

<!-- ── Hero ── -->
<div class="db-hero">
    <div class="db-hero-inner">
        <h1><i class="fas fa-tachometer-alt" style="font-size:1.6rem;vertical-align:middle;margin-right:.4rem;"></i> Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($applicant['first_name']); ?>! Here's your job search overview.</p>
    </div>
</div>

<!-- ── 3-COLUMN SHELL (Sidebar | Center | Right/Recommendations) ── -->
<div class="db-shell">

    <!-- ══ COLUMN 1: SIDEBAR (unchanged design) ══ -->
    <aside class="db-sidebar">

        <!-- Identity card -->
        <div class="db-id-card">
            <div class="db-id-top">
              <div class="db-avatar">
    <?php if (!empty($applicant['profile_pic'])): ?>
        <img src="../<?php echo htmlspecialchars($applicant['profile_pic']); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
    <?php else: ?>
        <?php echo strtoupper(substr($applicant['first_name'] ?? 'U', 0, 1) . substr($applicant['last_name'] ?? '', 0, 1)); ?>
    <?php endif; ?>
</div>
            </div>
            <div class="db-id-body">
                <div class="db-id-name"><?php echo htmlspecialchars(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? '')); ?></div>
                <div class="db-id-email"><?php echo htmlspecialchars($applicant['email'] ?? ''); ?></div>
                <div class="db-id-badge"><i class="fas fa-user-tie"></i> Applicant</div>
                <div class="db-id-stats">
                    <div class="db-stat">
                        <div class="db-stat-num"><?php echo $app_count; ?></div>
                        <div class="db-stat-lbl">Applications</div>
                    </div>
                    <div class="db-stat">
                        <div class="db-stat-num"><?php echo $pending_count; ?></div>
                        <div class="db-stat-lbl">Pending</div>
                    </div>
                    <div class="db-stat">
                        <div class="db-stat-num"><?php echo number_format($latest_score, 0); ?>%</div>
                        <div class="db-stat-lbl">Score</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidenav -->
        <nav class="db-sidenav">
            <div class="db-sidenav-title">Dashboard Sections</div>
            <a href="#sec-stats"><i class="fas fa-chart-line"></i> Statistics</a>
            <a href="#sec-quick"><i class="fas fa-bolt"></i> Quick Actions</a>
            <?php if (!empty($feedback_list)): ?>
                <a href="#sec-feedback"><i class="fas fa-comments"></i> Recent Feedback</a>
            <?php endif; ?>
        </nav>

    </aside>

    <!-- ══ COLUMN 2: CENTER MAIN CONTENT (Stats, Quick Actions, Score, Feedback) ══ -->
    <div class="db-center">

        <!-- Stats Cards Section -->
        <div class="db-section" id="sec-stats">
            <div class="db-section-head">
                <div class="db-section-icon"><i class="fas fa-chart-simple"></i></div>
                <div>
                    <h2>Your Statistics</h2>
                    <p>Overview of your job search activity</p>
                </div>
            </div>
            <div class="db-section-body">
                <div class="db-stats-grid">
                    <!-- Employability Score Card -->
                    <div class="db-stat-card">
                        <div class="db-stat-icon" style="background: linear-gradient(135deg, <?php echo $score_color; ?> 0%, <?php echo $score_color; ?>dd 100%); color: white;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="db-stat-value"><?php echo $display_score; ?>%</div>
                        <div class="db-stat-label">Employability Score</div>
                        <?php if ($latest_score > 0): ?>
                            <div class="db-stat-sub" style="color: <?php echo $score_color; ?>; font-weight: 600;">
                                <?php echo $score_description; ?>
                            </div>
                        <?php else: ?>
                            <div class="db-stat-sub">Take assessment to get score</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Total Applications Card -->
                    <div class="db-stat-card">
                        <div class="db-stat-icon" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white;">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="db-stat-value"><?php echo $app_count; ?></div>
                        <div class="db-stat-label">Total Applications</div>
                    </div>
                    
                    <!-- Pending Applications Card -->
                    <div class="db-stat-card">
                        <div class="db-stat-icon" style="background: linear-gradient(135deg, #ff6b00 0%, #e55a00 100%); color: white;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="db-stat-value"><?php echo $pending_count; ?></div>
                        <div class="db-stat-label">Pending Applications</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Section -->
        <div class="db-section" id="sec-quick">
            <div class="db-section-head">
                <div class="db-section-icon" style="background: #f3e5f5; color: #9C27B0;"><i class="fas fa-bolt"></i></div>
                <div>
                    <h2>Quick Actions</h2>
                    <p>Take action to improve your job search</p>
                </div>
            </div>
            <div class="db-section-body">
                <div class="db-quick-actions">
                    <h3><i class="fas fa-rocket"></i> What's Next?</h3>
                    <p>
                        <?php if ($latest_score == 0): ?>
                            Complete your career assessment to get your employability score and better job recommendations.
                        <?php else: ?>
                            Your current employability score is <?php echo $display_score; ?>%. Improve your profile to increase your score.
                        <?php endif; ?>
                    </p>
                    <div class="db-btn-group">
                        <a href="chatbot.php" class="db-btn db-btn-primary">
                            <i class="fas fa-robot"></i> 
                            <?php echo $latest_score == 0 ? 'Take Assessment' : 'Retake Assessment'; ?>
                        </a>
                        <a href="profile.php" class="db-btn db-btn-secondary">
                            <i class="fas fa-user-edit"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Score Explanation if available -->
        <?php if ($latest_score > 0): ?>
        <div class="db-section">
            <div class="db-section-head">
                <div class="db-section-icon"><i class="fas fa-info-circle"></i></div>
                <div>
                    <h2>Score Breakdown</h2>
                    <p>Understanding your employability score</p>
                </div>
            </div>
            <div class="db-section-body">
                <div class="db-score-info" style="background: <?php echo $score_color; ?>10; border-color: <?php echo $score_color; ?>;">
                    <h4 style="color: #333;"><i class="fas fa-chart-line" style="color: <?php echo $score_color; ?>;"></i> About Your Employability Score</h4>
                    <p style="color: #666;">
                        Your score of <strong><?php echo $display_score; ?>%</strong> is calculated based on your career assessment answers. 
                        <?php if ($latest_score >= 70): ?>
                            This is an excellent score! Employers are likely to view you as a strong candidate.
                        <?php elseif ($latest_score >= 50): ?>
                            This is a good score. Consider updating your skills and experience to improve further.
                        <?php else: ?>
                            Consider taking the assessment again or updating your profile to improve your score.
                        <?php endif; ?>
                        <?php if ($score_source === 'api'): ?>
                            <br><small><i>Calculated using AI assessment</i></small>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Employer Feedback Section -->
        <?php if (!empty($feedback_list)): ?>
        <div class="db-section" id="sec-feedback">
            <div class="db-section-head">
                <div class="db-section-icon"><i class="fas fa-comments"></i></div>
                <div>
                    <h2>Recent Employer Feedback</h2>
                    <p><?php echo $feedback_count; ?> feedback record<?php echo $feedback_count !== 1 ? 's' : ''; ?></p>
                </div>
            </div>
            <div class="db-section-body">
                <?php foreach ($feedback_list as $feedback): ?>
                    <div class="db-feedback-card">
                        <div class="db-feedback-header">
                            <div>
                                <div class="db-job-title"><i class="fas fa-briefcase" style="font-size:.85rem;margin-right:.3rem;"></i> <?php echo htmlspecialchars($feedback['job_title']); ?></div>
                                <div class="db-feedback-company"><i class="fas fa-building" style="margin-right:.3rem;"></i> <?php echo htmlspecialchars($feedback['company_name']); ?></div>
                            </div>
                            <span style="background: #4CAF50; color: white; padding: .25rem .8rem; border-radius: 12px; font-size: .7rem; font-weight: 600;">
                                <?php echo ucfirst($feedback['application_status']); ?>
                            </span>
                        </div>
                        <div class="db-job-desc"><?php echo htmlspecialchars($feedback['feedback_message']); ?></div>
                        <div class="db-job-meta" style="justify-content: space-between; margin-bottom: 0;">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($feedback['employer_first'] . ' ' . $feedback['employer_last']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($feedback_count > 3): ?>
                    <div style="text-align: center; margin-top: .5rem;">
                        <a href="applications.php#feedback" style="color: #1866a3; text-decoration: none; font-weight: 600; font-size: .85rem;">
                            View All (<?php echo $feedback_count; ?>) <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ══ COLUMN 3: JOB RECOMMENDATIONS (RIGHT SIDE) ══ -->
    <div class="db-right">
        <div class="right-col-header">
            <h3><i class="fas fa-star"></i> Recommended for You</h3>
            <p><?php echo $chatbot_completed ? 'Based on your profile & assessment' : 'Complete assessment to see matches'; ?></p>
        </div>
        <div class="right-content">
            <?php if (count($recommendations) > 0): ?>
                <?php foreach ($recommendations as $rec): ?>
                    <div class="job-compact-card">
                        <div class="job-compact-title"><?php echo htmlspecialchars($rec['title']); ?></div>
                        <?php if (!empty($rec['company_name'])): ?>
                            <div class="job-compact-company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($rec['company_name']); ?></div>
                        <?php endif; ?>
                        <div class="job-compact-meta">
                            <?php if (!empty($rec['location'])): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($rec['location']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($rec['employment_type'])): ?>
                                <span><i class="fas fa-briefcase"></i> <?php echo ucfirst($rec['employment_type']); ?></span>
                            <?php endif; ?>
                            <span class="match-badge-sm"><i class="fas fa-chart-line"></i> <?php echo number_format($rec['calculated_match_score'], 1); ?>%</span>
                        </div>
                        <div class="compact-actions">
                            <a href="job_details.php?id=<?php echo $rec['job_id']; ?>" class="compact-btn compact-btn-view"><i class="fas fa-eye"></i> Details</a>
                            <a href="apply_job.php?id=<?php echo $rec['job_id']; ?>" class="compact-btn compact-btn-apply"><i class="fas fa-paper-plane"></i> Apply</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="text-align: center; margin-top: 0.8rem;">
                    <a href="jobs.php" style="color: #1866a3; font-size: 0.75rem; text-decoration: none;">View all jobs →</a>
                </div>
            <?php else: ?>
                <div class="db-empty-state" style="padding: 1.5rem;">
                    <i class="fas fa-search" style="font-size: 2rem; color: #ddd;"></i>
                    <h3 style="font-size: 0.9rem; margin-top: 0.5rem;"><?php echo $chatbot_completed ? 'No 50%+ matches' : 'Assessment required'; ?></h3>
                    <p style="font-size: 0.7rem;"><?php echo $chatbot_completed ? 'Update your profile for better matches' : 'Take assessment to unlock recommendations'; ?></p>
                    <?php if (!$chatbot_completed): ?>
                        <a href="chatbot.php" class="compact-btn compact-btn-view" style="display: inline-block;">Take Assessment</a>
                    <?php else: ?>
                        <a href="profile.php" class="compact-btn compact-btn-view" style="display: inline-block;">Update Profile</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /db-shell -->
</div><!-- /db-page -->

<!-- Match Score Popup -->
<div id="matchScorePopup" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 500px; margin: 2rem auto; background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
            <h2 style="color: #1866a3; margin: 0; font-size: 1.2rem;">
                <i class="fas fa-star"></i> Match Score
            </h2>
            <button onclick="closeMatchScorePopup()" style="background: #f44336; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 1rem;">
                ×
            </button>
        </div>
        
        <div id="matchScoreContent" style="text-align: center;">
            <!-- Content will be inserted here -->
        </div>
        
        <div style="margin-top: 1.2rem; text-align: center;">
            <button onclick="closeMatchScorePopup()" style="background: #1866a3; color: white; padding: 0.6rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ── Floating Chat Button ── -->
<div class="db-float">
    <a href="chat.php" class="db-float-btn" title="Chat with Employers">
        <i class="fas fa-comments"></i>
        <?php if ($unread_count > 0): ?>
            <span class="db-float-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
        <?php endif; ?>
        <span class="db-float-tip">
            <?php echo $unread_count > 0 ? "You have {$unread_count} unread message(s)" : "Chat with Employers"; ?>
        </span>
    </a>
</div>

<script>
function showMatchScorePopup(jobId, jobTitle, matchScore) {
    const popup = document.getElementById('matchScorePopup');
    const content = document.getElementById('matchScoreContent');
    
    const scoreColor = matchScore >= 80 ? '#28a745' : 
                     matchScore >= 60 ? '#ffc107' : 
                     matchScore >= 40 ? '#fd7e14' : '#dc3545';
    const scoreLabel = matchScore >= 80 ? 'Excellent Match!' : 
                      matchScore >= 60 ? 'Good Match' : 
                      matchScore >= 40 ? 'Fair Match' : 'Needs Improvement';
    
    let html = `
        <h3 style="color: #333; margin-bottom: 0.8rem; font-size: 1rem;">${jobTitle}</h3>
        <div style="background: linear-gradient(135deg, ${scoreColor} 0%, ${scoreColor}dd 100%); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.2rem;">
            <p style="color: white; font-size: 0.8rem; margin-bottom: 0.4rem; opacity: 0.9;">Your Match Score</p>
            <p style="color: white; font-size: 2.5rem; font-weight: 700; margin: 0.4rem 0;">
                ${matchScore.toFixed(1)}%
            </p>
            <p style="color: white; font-size: 0.9rem; margin-top: 0.4rem; opacity: 0.9;">
                ${scoreLabel}
            </p>
        </div>
        <div style="text-align: left; background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
            <h4 style="color: #333; margin-bottom: 0.6rem; font-size: 0.85rem;">How this score is calculated:</h4>
            <ul style="color: #666; line-height: 1.6; margin: 0; padding-left: 1.2rem; font-size: 0.8rem;">
                <li><strong>Skills Match (40 pts):</strong> % of required skills matched</li>
                <li><strong>Experience (25 pts):</strong> 5 points per year of experience</li>
                <li><strong>Qualifications (15 pts):</strong> % of required qualifications matched</li>
                <li><strong>Chatbot Assessment (10 pts):</strong> Based on assessment performance</li>
                <li><strong>Bonuses (10 pts):</strong> Extra points for completion & scores</li>
                <li><em style="color: #0056b3;">Total possible: 100 points</em></li>
            </ul>
        </div>
        <div style="display: flex; gap: 0.8rem; justify-content: center;">
            <a href="job_details.php?id=${jobId}" style="background: #1866a3; color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.8rem;">
                View Full Analysis
            </a>
            <a href="apply_job.php?id=${jobId}" style="background: #ff6a00; color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.8rem;">
                Apply Now
            </a>
        </div>
    `;
    
    content.innerHTML = html;
    popup.style.display = 'block';
}

function closeMatchScorePopup() {
    document.getElementById('matchScorePopup').style.display = 'none';
}

// Close popup when clicking outside
document.getElementById('matchScorePopup')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMatchScorePopup();
    }
});

// Active sidenav highlight on scroll
(function() {
    var sections = document.querySelectorAll('#sec-stats, #sec-quick, #sec-feedback');
    var links    = document.querySelectorAll('.db-sidenav a');
    if (!sections.length || !links.length) return;
    function onScroll() {
        var scrollY = window.scrollY + 120;
        var current = '';
        sections.forEach(function(s) { if (s.offsetTop <= scrollY) current = s.id; });
        links.forEach(function(a) {
            a.classList.toggle('active', a.getAttribute('href') === '#' + current);
        });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();

// Auto-refresh unread count
(function() {
    function refresh() {
        fetch('../includes/handlers/message_handler.php?action=get_unread_count')
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.success) return;
                var badge = document.querySelector('.db-float-badge');
                var tip   = document.querySelector('.db-float-tip');
                var btn   = document.querySelector('.db-float-btn');
                if (d.count > 0) {
                    if (badge) { badge.textContent = d.count > 9 ? '9+' : d.count; }
                    else {
                        var b = document.createElement('span');
                        b.className = 'db-float-badge';
                        b.textContent = d.count > 9 ? '9+' : d.count;
                        btn.appendChild(b);
                    }
                    if (tip) tip.textContent = 'You have ' + d.count + ' unread message(s)';
                } else {
                    if (badge) badge.remove();
                    if (tip) tip.textContent = 'Chat with Employers';
                }
            }).catch(function(){});
    }
    if (document.querySelector('.db-float-btn')) setInterval(refresh, 30000);
})();
</script>

<?php include '../includes/footer.php'; ?>