<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

// IDEAL MATCH SCORE WEIGHTS
$weights = [
    'skills' => 40,        // Most important - 40%
    'experience' => 25,    // Second most important - 25%
    'qualifications' => 15, // Third - 15%
    'chatbot_assessment' => 10, // Supplemental - 10%
    'bonuses' => 10        // Extra points - 10%
    // TOTAL: 100%
];

$pageTitle = "Job Details";
$job_id = $_GET['id'] ?? 0;

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT jp.*, e.company_name, e.company_address, e.company_website, u.email as employer_email
    FROM job_postings jp 
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN users u ON e.user_id = u.user_id
    WHERE jp.job_id = ?
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header('Location: jobs.php');
    exit();
}

$stmt = $conn->prepare("
    SELECT q.name
    FROM qualifications q 
    INNER JOIN job_qualification_mapping jqm ON q.qualification_id = jqm.qualification_id 
    WHERE jqm.job_id = ?
    ORDER BY q.name ASC
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job_qualifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if already applied
$user_id = getCurrentUserId();
$stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$applicant_id = $applicant['applicant_id'];
$stmt->close();

// Get application details including remarks and match score from database
$application_details = null;
$applicant_remarks = [];
$has_application = false;
$database_match_score = 0;

$stmt = $conn->prepare("
    SELECT a.*, a.applicant_remarks_history 
    FROM applications a 
    WHERE a.job_id = ? AND a.applicant_id = ?
");
$stmt->bind_param("ii", $job_id, $applicant_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $has_application = true;
    $application_details = $result->fetch_assoc();
    $database_match_score = $application_details['match_score'] ?? 0;
    
    if (!empty($application_details['applicant_remarks_history'])) {
        $applicant_remarks = json_decode($application_details['applicant_remarks_history'], true);
        if (!is_array($applicant_remarks)) {
            $applicant_remarks = [];
        }
    }
}
$stmt->close();

// Get interview schedules for this application
$interviews = [];
if ($has_application && !empty($application_details['application_id'])) {
    $stmt = $conn->prepare("
        SELECT isch.*, e.company_name 
        FROM interview_schedules isch
        LEFT JOIN employers e ON isch.employer_id = e.employer_id
        WHERE isch.application_id = ? 
        ORDER BY isch.interview_date DESC, isch.start_time DESC
    ");
    $stmt->bind_param("i", $application_details['application_id']);
    $stmt->execute();
    $interviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Check if chatbot assessment is completed
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chatbot_answers WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$chatbot_result = $stmt->get_result()->fetch_assoc();
$chatbot_completed = $chatbot_result['count'] > 0;
$stmt->close();

// Fetch applicant data
$stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$applicant_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if profile is complete
$profile_completed = !empty($applicant_data['skills']) && !empty($applicant_data['qualifications']);

// ==================== TRANSPARENCY CALCULATION ====================
$transparency_data = [
    'total_score' => 0,
    'breakdown' => [],
    'skills_analysis' => [
        'required' => [],
        'matched' => [],
        'missing' => []
    ],
    'qualifications_analysis' => [
        'required' => [],
        'matched' => [],
        'missing' => []
    ],
    'chatbot_analysis' => [
        'completed' => false,
        'score' => 0,
        'questions_answered' => 0,
        'total_value' => 0,
        'max_possible' => 0
    ],
    'experience_analysis' => [
        'years' => 0,
        'score' => 0
    ],
    'bonuses' => []
];

$calculated_match_score = 0;

// Get chatbot answers once
$chatbot_answers = [];
if ($chatbot_completed) {
    $stmt = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $chatbot_answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// If user has already applied, use the match score from database
if ($has_application && $database_match_score > 0) {
    $calculated_match_score = $database_match_score;
    $transparency_data['total_score'] = $database_match_score;
    
    // Still calculate the breakdown for transparency if profile is complete
    if ($profile_completed) {
        $score = 0;
        
        // 1. SKILLS MATCHING (40 points) - Most important
        if (!empty($job['skills_required'])) {
            $required_skills = array_map('trim', explode(',', $job['skills_required']));
            $transparency_data['skills_analysis']['required'] = $required_skills;
            
            $applicant_skills = !empty($applicant_data['skills']) ? 
                array_map('trim', explode(',', $applicant_data['skills'])) : [];
            
            // Find matched and missing skills
            $matched_skills = [];
            $missing_skills = [];
            
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
                if (!$found) {
                    $missing_skills[] = trim($skill);
                }
            }
            
            $transparency_data['skills_analysis']['matched'] = $matched_skills;
            $transparency_data['skills_analysis']['missing'] = $missing_skills;
            
            if (!empty($required_skills)) {
                $skills_score = (count($matched_skills) / count($required_skills)) * 40;
                $skills_score = round($skills_score, 2);
                
                $transparency_data['breakdown'][] = [
                    'category' => 'Skills Match',
                    'score' => $skills_score,
                    'max' => 40,
                    'description' => count($matched_skills) . ' of ' . count($required_skills) . ' required skills matched'
                ];
            }
        }
        
        // 2. EXPERIENCE (25 points) - Second most important
        $experience_years = !empty($applicant_data['experience_years']) ? (int)$applicant_data['experience_years'] : 0;
        $transparency_data['experience_analysis']['years'] = $experience_years;
        
        $exp_score = min(25, $experience_years * 5);
        $exp_score = round($exp_score, 2);
        $transparency_data['experience_analysis']['score'] = $exp_score;
        
        $transparency_data['breakdown'][] = [
            'category' => 'Experience',
            'score' => $exp_score,
            'max' => 25,
            'description' => $experience_years . ' years of experience'
        ];
        
        // 3. QUALIFICATIONS MATCHING (15 points) - Third most important
        if (!empty($job_qualifications)) {
            $transparency_data['qualifications_analysis']['required'] = $job_qualifications;
            
            $applicant_qualifications = !empty($applicant_data['qualifications']) ? 
                strtolower($applicant_data['qualifications']) : '';
            
            $matched_quals = [];
            $missing_quals = [];
            
            foreach ($job_qualifications as $qual) {
                $qual_name = strtolower(trim($qual['name']));
                if (stripos($applicant_qualifications, $qual_name) !== false) {
                    $matched_quals[] = $qual;
                } else {
                    $missing_quals[] = $qual;
                }
            }
            
            $transparency_data['qualifications_analysis']['matched'] = $matched_quals;
            $transparency_data['qualifications_analysis']['missing'] = $missing_quals;
            
            if (!empty($job_qualifications)) {
                $qual_score = (count($matched_quals) / count($job_qualifications)) * 15;
                $qual_score = round($qual_score, 2);
                
                $transparency_data['breakdown'][] = [
                    'category' => 'Qualifications Match',
                    'score' => $qual_score,
                    'max' => 15,
                    'description' => count($matched_quals) . ' of ' . count($job_qualifications) . ' required qualifications matched'
                ];
            }
        } else {
            // If job has no specific qualifications, award 7.5 points (half of max)
            $transparency_data['breakdown'][] = [
                'category' => 'Qualifications Match',
                'score' => 7.5,
                'max' => 15,
                'description' => 'Job has no specific qualification requirements'
            ];
        }
        
        // 4. CHATBOT ASSESSMENT (10 points) - Supplemental
        if (!empty($chatbot_answers)) {
            $transparency_data['chatbot_analysis']['completed'] = true;
            $transparency_data['chatbot_analysis']['questions_answered'] = count($chatbot_answers);
            
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
            
            $transparency_data['chatbot_analysis']['total_value'] = $total_value;
            $transparency_data['chatbot_analysis']['max_possible'] = $max_possible;
            
            if ($max_possible > 0) {
                $percentage = ($total_value / $max_possible) * 100;
                $chatbot_score = ($total_value / $max_possible) * 10;
                $chatbot_score = round($chatbot_score, 2);
                $transparency_data['chatbot_analysis']['score'] = $chatbot_score;
                
                $transparency_data['breakdown'][] = [
                    'category' => 'Chatbot Assessment',
                    'score' => $chatbot_score,
                    'max' => 10,
                    'description' => 'Based on ' . count($chatbot_answers) . ' answered questions (Raw: ' . $total_value . '/' . $max_possible . ' = ' . number_format($percentage, 1) . '%)'
                ];
            }
        } else {
            $transparency_data['breakdown'][] = [
                'category' => 'Chatbot Assessment',
                'score' => 0,
                'max' => 10,
                'description' => 'Not completed - Complete chatbot for up to 10 points'
            ];
        }
        
        // 5. BONUSES (10 points) - Extra points
        $bonus_total = 0;
        
        // Chatbot completion bonus (5 points)
        if (!empty($chatbot_answers) && count($chatbot_answers) >= 5) {
            $bonus_total += 5;
            $transparency_data['bonuses'][] = [
                'name' => 'Chatbot Completion',
                'points' => 5,
                'description' => 'Completed chatbot assessment'
            ];
        }
        
        // Employability score bonus (5 points)
        if (!empty($applicant_data['employability_score']) && $applicant_data['employability_score'] > 0) {
            $emp_bonus = min(5, ($applicant_data['employability_score'] / 100) * 5);
            $emp_bonus = round($emp_bonus, 2);
            $bonus_total += $emp_bonus;
            
            $transparency_data['bonuses'][] = [
                'name' => 'Employability Score',
                'points' => $emp_bonus,
                'description' => 'Based on your employability score of ' . $applicant_data['employability_score'] . '%'
            ];
        }
        
        if ($bonus_total > 0) {
            $transparency_data['breakdown'][] = [
                'category' => 'Bonuses',
                'score' => $bonus_total,
                'max' => 10,
                'description' => 'Additional points earned'
            ];
        }
    }
} 
// If user hasn't applied yet, calculate match score
elseif ($profile_completed) {
    $score = 0;
    
    // 1. SKILLS MATCHING (40 points) - Most important
    if (!empty($job['skills_required'])) {
        $required_skills = array_map('trim', explode(',', $job['skills_required']));
        $transparency_data['skills_analysis']['required'] = $required_skills;
        
        $applicant_skills = !empty($applicant_data['skills']) ? 
            array_map('trim', explode(',', $applicant_data['skills'])) : [];
        
        // Find matched and missing skills
        $matched_skills = [];
        $missing_skills = [];
        
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
            if (!$found) {
                $missing_skills[] = trim($skill);
            }
        }
        
        $transparency_data['skills_analysis']['matched'] = $matched_skills;
        $transparency_data['skills_analysis']['missing'] = $missing_skills;
        
        if (!empty($required_skills)) {
            $skills_score = (count($matched_skills) / count($required_skills)) * 40;
            $skills_score = round($skills_score, 2);
            $score += $skills_score;
            
            $transparency_data['breakdown'][] = [
                'category' => 'Skills Match',
                'score' => $skills_score,
                'max' => 40,
                'description' => count($matched_skills) . ' of ' . count($required_skills) . ' required skills matched'
            ];
        }
    }
    
    // 2. EXPERIENCE (25 points) - Second most important
    $experience_years = !empty($applicant_data['experience_years']) ? (int)$applicant_data['experience_years'] : 0;
    $transparency_data['experience_analysis']['years'] = $experience_years;
    
    $exp_score = min(25, $experience_years * 5);
    $exp_score = round($exp_score, 2);
    $score += $exp_score;
    $transparency_data['experience_analysis']['score'] = $exp_score;
    
    $transparency_data['breakdown'][] = [
        'category' => 'Experience',
        'score' => $exp_score,
        'max' => 25,
        'description' => $experience_years . ' years of experience'
    ];
    
    // 3. QUALIFICATIONS MATCHING (15 points) - Third most important
    if (!empty($job_qualifications)) {
        $transparency_data['qualifications_analysis']['required'] = $job_qualifications;
        
        $applicant_qualifications = !empty($applicant_data['qualifications']) ? 
            strtolower($applicant_data['qualifications']) : '';
        
        $matched_quals = [];
        $missing_quals = [];
        
        foreach ($job_qualifications as $qual) {
            $qual_name = strtolower(trim($qual['name']));
            if (stripos($applicant_qualifications, $qual_name) !== false) {
                $matched_quals[] = $qual;
            } else {
                $missing_quals[] = $qual;
            }
        }
        
        $transparency_data['qualifications_analysis']['matched'] = $matched_quals;
        $transparency_data['qualifications_analysis']['missing'] = $missing_quals;
        
        if (!empty($job_qualifications)) {
            $qual_score = (count($matched_quals) / count($job_qualifications)) * 15;
            $qual_score = round($qual_score, 2);
            $score += $qual_score;
            
            $transparency_data['breakdown'][] = [
                'category' => 'Qualifications Match',
                'score' => $qual_score,
                'max' => 15,
                'description' => count($matched_quals) . ' of ' . count($job_qualifications) . ' required qualifications matched'
            ];
        }
    } else {
        // If job has no specific qualifications, award 7.5 points (half of max)
        $score += 7.5;
        $transparency_data['breakdown'][] = [
            'category' => 'Qualifications Match',
            'score' => 7.5,
            'max' => 15,
            'description' => 'Job has no specific qualification requirements'
        ];
    }
    
    // 4. CHATBOT ASSESSMENT (10 points) - Supplemental
    if (!empty($chatbot_answers)) {
        $transparency_data['chatbot_analysis']['completed'] = true;
        $transparency_data['chatbot_analysis']['questions_answered'] = count($chatbot_answers);
        
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
        
        $transparency_data['chatbot_analysis']['total_value'] = $total_value;
        $transparency_data['chatbot_analysis']['max_possible'] = $max_possible;
        
        if ($max_possible > 0) {
            $percentage = ($total_value / $max_possible) * 100;
            $chatbot_score = ($total_value / $max_possible) * 10;
            $chatbot_score = round($chatbot_score, 2);
            $score += $chatbot_score;
            $transparency_data['chatbot_analysis']['score'] = $chatbot_score;
            
            $transparency_data['breakdown'][] = [
                'category' => 'Chatbot Assessment',
                'score' => $chatbot_score,
                'max' => 10,
                'description' => 'Based on ' . count($chatbot_answers) . ' answered questions (Raw: ' . $total_value . '/' . $max_possible . ' = ' . number_format($percentage, 1) . '%)'
            ];
        }
    } else {
        $transparency_data['breakdown'][] = [
            'category' => 'Chatbot Assessment',
            'score' => 0,
            'max' => 10,
            'description' => 'Not completed - Complete chatbot for up to 10 points'
        ];
    }
    
    // 5. BONUSES (10 points) - Extra points
    $bonus_total = 0;
    
    // Chatbot completion bonus (5 points)
    if (!empty($chatbot_answers) && count($chatbot_answers) >= 5) {
        $bonus_total += 5;
        $transparency_data['bonuses'][] = [
            'name' => 'Chatbot Completion',
            'points' => 5,
            'description' => 'Completed chatbot assessment'
        ];
    }
    
    // Employability score bonus (5 points)
    if (!empty($applicant_data['employability_score']) && $applicant_data['employability_score'] > 0) {
        $emp_bonus = min(5, ($applicant_data['employability_score'] / 100) * 5);
        $emp_bonus = round($emp_bonus, 2);
        $bonus_total += $emp_bonus;
        
        $transparency_data['bonuses'][] = [
            'name' => 'Employability Score',
            'points' => $emp_bonus,
            'description' => 'Based on your employability score of ' . $applicant_data['employability_score'] . '%'
        ];
    }
    
    if ($bonus_total > 0) {
        $score += $bonus_total;
        $transparency_data['breakdown'][] = [
            'category' => 'Bonuses',
            'score' => $bonus_total,
            'max' => 10,
            'description' => 'Additional points earned'
        ];
    }
    
    // Calculate total score
    $calculated_match_score = min(100, round($score, 2));
    $transparency_data['total_score'] = $calculated_match_score;
}

$conn->close();

// Determine which match score to display
$display_match_score = $has_application ? $database_match_score : $calculated_match_score;

include '../includes/header.php';
?>

<div class="container">
    <div class="card" style="margin-top: 10px;">
        <div style="margin-bottom: 2rem;">
            <a href="jobs.php" style="color: #0056b3; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Jobs
            </a>
        </div>

        <h1 style="margin-bottom: 1rem; color: #0056b3;"><?php echo htmlspecialchars($job['title']); ?></h1>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <div>
                <!-- ========== JOB INFORMATION SECTION ========== -->
                <div style="margin-bottom: 2rem;">
                    <h2 style="color: #333; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #0056b3;">
                        <i class="fas fa-info-circle" style="color: #0056b3;"></i> Job Information
                    </h2>

                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #333; margin-bottom: 1rem;">
                            <i class="fas fa-file-alt" style="color: #0056b3;"></i> Job Description
                        </h3>
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #0056b3;">
                            <p style="color: #666; line-height: 1.8; margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($job['description']); ?></p>
                        </div>
                    </div>

                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #333; margin-bottom: 1rem;">
                            <i class="fas fa-tasks" style="color: #0056b3;"></i> Requirements
                        </h3>
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #0056b3;">
                            <p style="color: #666; line-height: 1.8; margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($job['requirements']); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($job_qualifications)): ?>
                        <div style="margin-bottom: 2rem;">
                            <h3 style="color: #333; margin-bottom: 1rem;">
                                <i class="fas fa-graduation-cap" style="color: #0056b3;"></i> Required Qualifications
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php foreach ($job_qualifications as $qualification): ?>
                                    <div style="display: flex; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #0056b3;">
                                        <i class="fas fa-graduation-cap" style="color: #0056b3; margin-right: 1rem; font-size: 1.2rem;"></i>
                                        <div>
                                            <p style="font-weight: 600; color: #333; margin: 0 0 0.25rem 0;">
                                                <?php echo htmlspecialchars($qualification['name']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($job['skills_required'])): ?>
                        <div style="margin-bottom: 2rem;">
                            <h3 style="color: #333; margin-bottom: 1rem;">
                                <i class="fas fa-tools" style="color: #0056b3;"></i> Required Skills
                            </h3>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                <?php 
                                $skills = explode(',', $job['skills_required']);
                                foreach ($skills as $skill): 
                                ?>
                                    <span style="background: #0056b3; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars(trim($skill)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- ========== END JOB INFORMATION SECTION ========== -->

                <!-- ========== INTERVIEW SCHEDULE SECTION ========== -->
                <?php if ($has_application && count($interviews) > 0): ?>
                <div style="margin-bottom: 2rem; border: 2px solid #9C27B0; border-radius: 10px; overflow: hidden;">
                    <div style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); padding: 1.5rem; color: white;">
                        <h3 style="color: white; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-calendar-alt"></i> Interview Schedule
                        </h3>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.95rem; opacity: 0.9;">
                            Your scheduled interviews for this position
                        </p>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 1.5rem;">
                        <?php foreach ($interviews as $interview): ?>
                            <div style="background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #9C27B0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                    <div>
                                        <span style="background: <?php 
                                            switch($interview['status']) {
                                                case 'scheduled': echo '#28a745';
                                                case 'completed': echo '#007bff';
                                                case 'cancelled': echo '#dc3545';
                                                case 'rescheduled': echo '#ffc107';
                                                default: echo '#6c757d';
                                            }
                                        ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem; font-weight: 600; margin-right: 0.5rem;">
                                            <?php echo htmlspecialchars(ucfirst($interview['status'])); ?>
                                        </span>
                                        <span style="color: #333; font-weight: 600; font-size: 1.1rem;">
                                            <?php echo date('F d, Y', strtotime($interview['interview_date'])); ?>
                                        </span>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="color: #666; font-size: 0.9rem; display: block;">
                                            <?php echo date('h:i A', strtotime($interview['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($interview['end_time'])); ?>
                                        </span>
                                        <span style="color: #9C27B0; font-weight: 600; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars(ucfirst($interview['interview_type'])); ?> Interview
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($interview['location'])): ?>
                                    <div style="margin-bottom: 0.75rem; display: flex; align-items: flex-start; gap: 0.5rem;">
                                        <i class="fas fa-map-marker-alt" style="color: #666; margin-top: 0.25rem;"></i>
                                        <div>
                                            <p style="color: #333; font-weight: 600; margin: 0 0 0.25rem 0; font-size: 0.95rem;">
                                                Location
                                            </p>
                                            <p style="color: #666; margin: 0; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($interview['location']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($interview['meeting_link'])): ?>
                                    <div style="margin-bottom: 0.75rem; display: flex; align-items: flex-start; gap: 0.5rem;">
                                        <i class="fas fa-link" style="color: #666; margin-top: 0.25rem;"></i>
                                        <div>
                                            <p style="color: #333; font-weight: 600; margin: 0 0 0.25rem 0; font-size: 0.95rem;">
                                                Meeting Link
                                            </p>
                                            <a href="<?php echo htmlspecialchars($interview['meeting_link']); ?>" 
                                               target="_blank"
                                               style="color: #0056b3; text-decoration: none; font-size: 0.9rem; word-break: break-all;">
                                                <?php echo htmlspecialchars($interview['meeting_link']); ?>
                                            </a>
                                            <p style="color: #666; margin: 0.25rem 0 0 0; font-size: 0.85rem;">
                                                <i class="fas fa-external-link-alt"></i> Click to join
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($interview['notes'])): ?>
                                    <div style="margin-bottom: 0.75rem; padding: 1rem; background: #f0f0f0; border-radius: 6px;">
                                        <p style="color: #333; font-weight: 600; margin: 0 0 0.5rem 0; font-size: 0.95rem;">
                                            <i class="fas fa-sticky-note"></i> Interview Notes
                                        </p>
                                        <p style="color: #666; margin: 0; line-height: 1.6; font-size: 0.9rem; white-space: pre-wrap;">
                                            <?php echo htmlspecialchars($interview['notes']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($interview['status'] === 'scheduled'): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: #e8f4f8; border-radius: 6px; border-left: 3px solid #17a2b8;">
                                        <p style="color: #0c5460; margin: 0; font-size: 0.9rem;">
                                            <i class="fas fa-bell"></i> 
                                            <strong>Reminder:</strong> Please join the interview 5-10 minutes before the scheduled time.
                                            <?php if ($interview['interview_type'] === 'video'): ?>
                                                Ensure your camera and microphone are working properly.
                                            <?php elseif ($interview['interview_type'] === 'phone'): ?>
                                                Ensure you're in a quiet environment with good reception.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php elseif ($interview['status'] === 'completed'): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: #d4edda; border-radius: 6px; border-left: 3px solid #28a745;">
                                        <p style="color: #155724; margin: 0; font-size: 0.9rem;">
                                            <i class="fas fa-check-circle"></i> 
                                            <strong>Completed:</strong> This interview has been completed. The employer will provide feedback soon.
                                        </p>
                                    </div>
                                <?php elseif ($interview['status'] === 'cancelled'): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: #f8d7da; border-radius: 6px; border-left: 3px solid #dc3545;">
                                        <p style="color: #721c24; margin: 0; font-size: 0.9rem;">
                                            <i class="fas fa-times-circle"></i> 
                                            <strong>Cancelled:</strong> This interview has been cancelled. Please wait for the employer to reschedule.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <p style="color: #666; font-size: 0.85rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; text-align: center;">
                                    <i class="fas fa-info-circle"></i> 
                                    Scheduled by: <?php echo htmlspecialchars($interview['company_name'] ?? 'Employer'); ?>
                                    • Created: <?php echo date('F d, Y h:i A', strtotime($interview['created_at'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Add to Calendar Button -->
                        <?php if (!empty($interviews) && $interviews[0]['status'] === 'scheduled'): ?>
                        <div style="text-align: center; margin-top: 1rem;">
                            <button onclick="addToCalendar()" 
                                    style="background: #9C27B0; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-calendar-plus"></i> Add to Calendar
                            </button>
                            <p style="color: #666; font-size: 0.85rem; margin-top: 0.5rem;">
                                Add this interview to your Google Calendar, Outlook, or other calendar apps
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <!-- ========== END INTERVIEW SCHEDULE SECTION ========== -->

                <!-- ========== TRANSPARENCY SECTION ========== -->
                <?php if ($profile_completed): ?>
                <div style="margin-bottom: 2rem; border: 2px solid #e0e0e0; border-radius: 10px; overflow: hidden;">
                    <div style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); padding: 1.5rem; color: white;">
                        <h3 style="color: white; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-search"></i> Your Match Analysis
                        </h3>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.95rem; opacity: 0.9;">
                            See how your profile compares to this job's requirements
                        </p>
                    </div>
                    
                    <div style="padding: 1.5rem;">
                        <!-- Overall Score Display -->
                        <div style="text-align: center; margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
                            <div style="display: inline-block; position: relative;">
                                <?php
                                $score = $display_match_score;
                                $color = '#28a745'; // Default green
                                if ($score < 40) $color = '#dc3545'; // Red
                                elseif ($score < 60) $color = '#fd7e14'; // Orange
                                elseif ($score < 80) $color = '#ffc107'; // Yellow
                                ?>
                                <div style="width: 100px; height: 100px; border-radius: 50%; background: conic-gradient(<?php echo $color; ?> <?php echo $score; ?>%, #e9ecef <?php echo $score; ?>%); display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <div style="width: 80px; height: 80px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center;">
                                        <span style="font-size: 1.5rem; font-weight: bold; color: <?php echo $color; ?>;">
                                            <?php echo number_format($score, 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <p style="margin-top: 1rem; font-weight: 600; color: #333;">
                                <?php
                                if ($score >= 80) echo 'Excellent Match!';
                                elseif ($score >= 60) echo 'Good Match';
                                elseif ($score >= 40) echo 'Fair Match';
                                else echo 'Needs Improvement';
                                ?>
                            </p>
                            <?php if ($has_application): ?>
                                <p style="margin-top: 0.5rem; color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-database"></i> This is your official match score from the database
                                </p>
                            <?php else: ?>
                                <p style="margin-top: 0.5rem; color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-calculator"></i> Estimated match score before application
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Score Breakdown -->
                        <?php if (!empty($transparency_data['breakdown'])): ?>
                        <div style="margin-bottom: 2rem;">
                            <h4 style="color: #333; margin-bottom: 1rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem;">
                                <i class="fas fa-chart-bar"></i> Score Breakdown
                            </h4>
                            
                            <?php foreach ($transparency_data['breakdown'] as $item): ?>
                            <div style="margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <div>
                                        <strong style="color: #333;"><?php echo $item['category']; ?></strong>
                                        <p style="color: #666; font-size: 0.9rem; margin: 0.25rem 0 0 0;">
                                            <?php echo $item['description']; ?>
                                        </p>
                                    </div>
                                    <span style="font-weight: bold; color: #0056b3;">
                                        <?php echo $item['score']; ?>/<?php echo $item['max']; ?>
                                    </span>
                                </div>
                                <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                    <?php $percentage = ($item['score'] / $item['max']) * 100; ?>
                                    <div style="height: 100%; width: <?php echo $percentage; ?>%; background: #0056b3; border-radius: 4px;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Skills Analysis -->
                        <?php if (!empty($transparency_data['skills_analysis']['required'])): ?>
                        <div style="margin-bottom: 2rem;">
                            <h4 style="color: #333; margin-bottom: 1rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem;">
                                <i class="fas fa-tools"></i> Skills Analysis
                            </h4>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div style="background: #d4edda; padding: 1rem; border-radius: 8px;">
                                    <h5 style="color: #155724; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                        <i class="fas fa-check-circle"></i> Matched Skills
                                        (<?php echo count($transparency_data['skills_analysis']['matched']); ?>)
                                    </h5>
                                    <?php if (!empty($transparency_data['skills_analysis']['matched'])): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                        <?php foreach ($transparency_data['skills_analysis']['matched'] as $skill): ?>
                                            <span style="display: inline-block; background: #28a745; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                                <?php echo htmlspecialchars($skill); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="color: #666; font-size: 0.9rem; margin: 0;">No skills matched yet</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="background: #f8d7da; padding: 1rem; border-radius: 8px;">
                                    <h5 style="color: #721c24; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                        <i class="fas fa-exclamation-circle"></i> Missing Skills
                                        (<?php echo count($transparency_data['skills_analysis']['missing']); ?>)
                                    </h5>
                                    <?php if (!empty($transparency_data['skills_analysis']['missing'])): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                        <?php foreach ($transparency_data['skills_analysis']['missing'] as $skill): ?>
                                            <span style="display: inline-block; background: #dc3545; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                                <?php echo htmlspecialchars($skill); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="color: #666; font-size: 0.9rem; margin: 0;">All required skills matched!</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Qualifications Analysis -->
                        <?php if (!empty($transparency_data['qualifications_analysis']['required'])): ?>
                        <div style="margin-bottom: 2rem;">
                            <h4 style="color: #333; margin-bottom: 1rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem;">
                                <i class="fas fa-graduation-cap"></i> Qualifications Analysis
                            </h4>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div style="background: #d4edda; padding: 1rem; border-radius: 8px;">
                                    <h5 style="color: #155724; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                        <i class="fas fa-check-circle"></i> Matched Qualifications
                                        (<?php echo count($transparency_data['qualifications_analysis']['matched']); ?>)
                                    </h5>
                                    <?php if (!empty($transparency_data['qualifications_analysis']['matched'])): ?>
                                        <ul style="color: #155724; font-size: 0.9rem; margin: 0; padding-left: 1.2rem;">
                                            <?php foreach ($transparency_data['qualifications_analysis']['matched'] as $qual): ?>
                                                <li><?php echo htmlspecialchars($qual['name']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p style="color: #666; font-size: 0.9rem; margin: 0;">No qualifications matched</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="background: #f8d7da; padding: 1rem; border-radius: 8px;">
                                    <h5 style="color: #721c24; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                        <i class="fas fa-exclamation-circle"></i> Missing Qualifications
                                        (<?php echo count($transparency_data['qualifications_analysis']['missing']); ?>)
                                    </h5>
                                    <?php if (!empty($transparency_data['qualifications_analysis']['missing'])): ?>
                                        <ul style="color: #721c24; font-size: 0.9rem; margin: 0; padding-left: 1.2rem;">
                                            <?php foreach ($transparency_data['qualifications_analysis']['missing'] as $qual): ?>
                                                <li><?php echo htmlspecialchars($qual['name']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p style="color: #666; font-size: 0.9rem; margin: 0;">All qualifications matched!</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Chatbot Analysis Details -->
                        <?php if ($transparency_data['chatbot_analysis']['completed']): ?>
                        <div style="margin-bottom: 1rem; padding: 1rem; background: #e7f3ff; border-radius: 8px; border-left: 4px solid #2196F3;">
                            <h5 style="color: #0d47a1; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-robot"></i> Chatbot Assessment Details
                            </h5>
                            <p style="color: #666; font-size: 0.9rem; margin: 0.25rem 0;">
                                <strong>Questions Answered:</strong> <?php echo $transparency_data['chatbot_analysis']['questions_answered']; ?>
                            </p>
                            <p style="color: #666; font-size: 0.9rem; margin: 0.25rem 0;">
                                <strong>Raw Score:</strong> <?php echo $transparency_data['chatbot_analysis']['total_value']; ?>/<?php echo $transparency_data['chatbot_analysis']['max_possible']; ?>
                                (<?php echo number_format(($transparency_data['chatbot_analysis']['total_value'] / $transparency_data['chatbot_analysis']['max_possible']) * 100, 1); ?>%)
                            </p>
                            <p style="color: #666; font-size: 0.9rem; margin: 0.25rem 0;">
                                <strong>Weighted Score:</strong> <?php echo number_format($transparency_data['chatbot_analysis']['score'], 2); ?>/10 points
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Improvement Suggestions -->
                        <?php if ($display_match_score < 80): ?>
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; border-radius: 0 8px 8px 0;">
                            <h5 style="color: #856404; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-lightbulb"></i> How to Improve Your Score
                            </h5>
                            <ul style="color: #856404; font-size: 0.9rem; margin: 0; padding-left: 1.2rem;">
                                <?php if (!empty($transparency_data['skills_analysis']['missing'])): ?>
                                    <li>Add these skills to your profile: 
                                        <strong><?php echo implode(', ', $transparency_data['skills_analysis']['missing']); ?></strong>
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($transparency_data['qualifications_analysis']['missing'])): ?>
                                    <li>Consider obtaining these qualifications:
                                        <?php foreach ($transparency_data['qualifications_analysis']['missing'] as $qual): ?>
                                            <strong><?php echo $qual['name']; ?></strong><?php echo !$loop ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    </li>
                                <?php endif; ?>
                                <?php if (!$transparency_data['chatbot_analysis']['completed']): ?>
                                    <li>Complete the chatbot assessment for additional points</li>
                                <?php endif; ?>
                                <?php if ($transparency_data['experience_analysis']['years'] < 2): ?>
                                    <li>Gain more work experience in related fields</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif (!$profile_completed): ?>
                <div style="margin-bottom: 2rem; background: #fff3cd; padding: 1.5rem; border-radius: 10px; border-left: 4px solid #ffc107;">
                    <h3 style="color: #856404; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> Profile Incomplete
                    </h3>
                    <p style="color: #856404; margin-bottom: 1rem;">
                        Complete your profile (skills and qualifications) to see your match analysis and improve your application chances.
                    </p>
                    <a href="profile.php" style="display: inline-block; background: #ffc107; color: #212529; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-user-edit"></i> Complete Profile Now
                    </a>
                </div>
                <?php endif; ?>
                <!-- ========== END TRANSPARENCY SECTION ========== -->
                
                <!-- Employer Remarks/Justification Section (Only if applied) -->
                <?php if ($has_application && !empty($applicant_remarks)): ?>
                    <div style="margin-bottom: 2rem; border: 2px solid #28a745; border-radius: 10px; overflow: hidden;">
                        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 1.5rem; color: white;">
                            <h3 style="color: white; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                                <i class="fas fa-comment-dots"></i> Employer Feedback & Remarks
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.95rem; opacity: 0.9;">
                                Comments and justification from the employer regarding your application.
                            </p>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 1.5rem; border-top: 1px solid #e0e0e0;">
                            <?php foreach (array_reverse($applicant_remarks) as $index => $entry): ?>
                                <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 2px dashed #e0e0e0; <?php echo $index === count($applicant_remarks) - 1 ? 'border-bottom: none; margin-bottom: 0; padding-bottom: 0;' : ''; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                        <div>
                                            <?php 
                                            $status_colors = [
                                                'pending' => '#6c757d',
                                                'reviewed' => '#17a2b8',
                                                'shortlisted' => '#28a745',
                                                'scheduled_for_interview' => '#ffc107',
                                                'accepted' => '#007bff',
                                                'rejected' => '#dc3545'
                                            ];
                                            $status_color = $status_colors[$entry['status']] ?? '#6c757d';
                                            ?>
                                            <span style="background: <?php echo $status_color; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem; font-weight: 600; margin-right: 0.5rem;">
                                                <?php 
                                                $status_display = $entry['status'];
                                                if ($status_display === 'scheduled_for_interview') {
                                                    echo 'Scheduled for Interview';
                                                } else {
                                                    echo htmlspecialchars(ucfirst($status_display));
                                                }
                                                ?>
                                            </span>
                                            <?php if (!empty($entry['reviewed_by'])): ?>
                                                <span style="color: #666; font-size: 0.85rem;">
                                                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($entry['reviewed_by']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span style="color: #666; font-size: 0.85rem;">
                                            <i class="fas fa-clock"></i> <?php echo date('F d, Y h:i A', strtotime($entry['timestamp'])); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="background: white; padding: 1.25rem; border-radius: 8px; border-left: 4px solid #28a745; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <p style="color: #333; line-height: 1.6; margin: 0; white-space: pre-wrap;">
                                            <strong style="color: #28a745;">Remarks:</strong><br>
                                            <?php echo htmlspecialchars($entry['remarks']); ?>
                                        </p>
                                    </div>
                                    
                                    <?php if ($entry['status'] === 'accepted'): ?>
                                        <div style="margin-top: 1rem; padding: 1rem; background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(32, 201, 151, 0.1) 100%); border-radius: 8px; border-left: 4px solid #28a745;">
                                            <p style="color: #28a745; margin: 0; font-weight: 600;">
                                                <i class="fas fa-check-circle"></i> Congratulations! Your application has been accepted.
                                            </p>
                                            <?php if (!empty($job['employer_email'])): ?>
                                                <p style="color: #666; margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                                                    <i class="fas fa-info-circle"></i> Next steps will be communicated by the employer via email.
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($entry['status'] === 'rejected'): ?>
                                        <div style="margin-top: 1rem; padding: 1rem; background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(253, 126, 20, 0.1) 100%); border-radius: 8px; border-left: 4px solid #dc3545;">
                                            <p style="color: #dc3545; margin: 0; font-weight: 600;">
                                                <i class="fas fa-exclamation-circle"></i> Application Status: Not Selected
                                            </p>
                                            <p style="color: #666; margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                                                <i class="fas fa-lightbulb"></i> Consider this feedback for future applications and continue exploring other opportunities.
                                            </p>
                                        </div>
                                    <?php elseif ($entry['status'] === 'shortlisted'): ?>
                                        <div style="margin-top: 1rem; padding: 1rem; background: linear-gradient(135deg, rgba(23, 162, 184, 0.1) 0%, rgba(0, 123, 255, 0.1) 100%); border-radius: 8px; border-left: 3px solid #17a2b8;">
                                            <p style="color: #17a2b8; margin: 0; font-weight: 600;">
                                                <i class="fas fa-star"></i> Great news! You've been shortlisted.
                                            </p>
                                            <p style="color: #666; margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                                                <i class="fas fa-info-circle"></i> The employer will contact you for the next steps in the hiring process.
                                            </p>
                                        </div>
                                    <?php elseif ($entry['status'] === 'scheduled_for_interview'): ?>
                                        <div style="margin-top: 1rem; padding: 1rem; background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(253, 126, 20, 0.1) 100%); border-radius: 8px; border-left: 4px solid #ffc107;">
                                            <p style="color: #ffc107; margin: 0; font-weight: 600;">
                                                <i class="fas fa-handshake"></i> Scheduled for Interview
                                            </p>
                                            <p style="color: #666; margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                                                <i class="fas fa-info-circle"></i> You have been scheduled for an interview. Please check the Interview Schedule section for details.
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <div style="background: #f8f9fa; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">Job Details</h3>

                    <?php if (!empty($job['company_name'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Company:</strong>
                        <p style="color: #333; margin-top: 0.25rem;"><?php echo htmlspecialchars($job['company_name']); ?></p>
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Location:</strong>
                        <p style="color: #333; margin-top: 0.25rem;">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location'] ?? 'Location Not Specified'); ?>
                        </p>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Employment Type:</strong>
                        <p style="color: #333; margin-top: 0.25rem;"><?php echo ucfirst($job['employment_type']); ?></p>
                    </div>

                    <?php if (!empty($job['salary_range'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong style="color: #666;">Salary Range:</strong>
                            <p style="color: #4CAF50; font-weight: 600; margin-top: 0.25rem;">
                                <?php echo htmlspecialchars($job['salary_range']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Posted:</strong>
                        <p style="color: #333; margin-top: 0.25rem;">
                            <?php echo date('F d, Y', strtotime($job['posted_at'])); ?>
                        </p>
                    </div>

                    <?php if (!empty($job_qualifications)): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong style="color: #666;">Qualifications Required:</strong>
                            <p style="color: #333; margin-top: 0.25rem;">
                                <?php echo count($job_qualifications); ?> qualification(s) required
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($job['skills_required'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong style="color: #666;">Skills Required:</strong>
                            <p style="color: #333; margin-top: 0.25rem;">
                                <?php echo count(explode(',', $job['skills_required'])); ?> skill(s) required
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Interview Summary (if interviews exist) -->
                    <?php if ($has_application && count($interviews) > 0): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0;">
                            <h4 style="color: #333; margin-bottom: 1rem;">
                                <i class="fas fa-calendar-check"></i> Interview Summary
                            </h4>
                            
                            <?php 
                            $scheduled_interviews = 0;
                            $completed_interviews = 0;
                            $cancelled_interviews = 0;
                            $upcoming_interview = null;
                            
                            foreach ($interviews as $interview) {
                                switch($interview['status']) {
                                    case 'scheduled':
                                        $scheduled_interviews++;
                                        if (!$upcoming_interview) {
                                            $upcoming_interview = $interview;
                                        }
                                        break;
                                    case 'completed':
                                        $completed_interviews++;
                                        break;
                                    case 'cancelled':
                                        $cancelled_interviews++;
                                        break;
                                }
                            }
                            ?>
                            
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 1rem;">
                                <?php if ($scheduled_interviews > 0): ?>
                                <div style="text-align: center; background: #d4edda; padding: 0.5rem; border-radius: 6px;">
                                    <p style="font-size: 1.5rem; font-weight: bold; color: #28a745; margin: 0;"><?php echo $scheduled_interviews; ?></p>
                                    <p style="font-size: 0.8rem; color: #155724; margin: 0;">Scheduled</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($completed_interviews > 0): ?>
                                <div style="text-align: center; background: #d1ecf1; padding: 0.5rem; border-radius: 6px;">
                                    <p style="font-size: 1.5rem; font-weight: bold; color: #17a2b8; margin: 0;"><?php echo $completed_interviews; ?></p>
                                    <p style="font-size: 0.8rem; color: #0c5460; margin: 0;">Completed</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cancelled_interviews > 0): ?>
                                <div style="text-align: center; background: #f8d7da; padding: 0.5rem; border-radius: 6px;">
                                    <p style="font-size: 1.5rem; font-weight: bold; color: #dc3545; margin: 0;"><?php echo $cancelled_interviews; ?></p>
                                    <p style="font-size: 0.8rem; color: #721c24; margin: 0;">Cancelled</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($upcoming_interview): ?>
                                <div style="margin-top: 1rem; padding: 0.75rem; background: #e7f3fe; border-radius: 8px; border-left: 3px solid #2196F3;">
                                    <p style="color: #0d47a1; font-weight: 600; margin: 0 0 0.5rem 0; font-size: 0.9rem;">
                                        <i class="fas fa-clock"></i> Next Interview
                                    </p>
                                    <p style="color: #333; margin: 0 0 0.25rem 0; font-size: 0.9rem;">
                                        <?php echo date('F d, Y', strtotime($upcoming_interview['interview_date'])); ?>
                                    </p>
                                    <p style="color: #666; margin: 0; font-size: 0.85rem;">
                                        <?php echo date('h:i A', strtotime($upcoming_interview['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($upcoming_interview['end_time'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                 <!-- Application Status (if applied) -->
<!-- Application Status (if applied) -->
<?php if ($has_application && $application_details): ?>
    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0;">
        <h4 style="color: #333; margin-bottom: 1rem;">
            <i class="fas fa-clipboard-check"></i> Your Application Status
        </h4>
        
        <?php 
        $status = $application_details['status'] ?? 'pending';
        
        // Check if there are scheduled interviews - if yes, override status to "Scheduled for Interview"
        $has_scheduled_interviews = false;
        foreach ($interviews as $interview) {
            if ($interview['status'] === 'scheduled') {
                $has_scheduled_interviews = true;
                break;
            }
        }
        
        if ($has_scheduled_interviews) {
            $status = 'scheduled_for_interview';
        }
        
        $status_colors = [
            'pending' => '#6c757d',
            'reviewed' => '#17a2b8',
            'shortlisted' => '#28a745',
            'scheduled_for_interview' => '#ffc107',
            'accepted' => '#007bff',
            'rejected' => '#dc3545'
        ];
        $color = $status_colors[$status] ?? '#6c757d';
        
        // Define status display names
        $status_display_names = [
            'pending' => 'Pending',
            'reviewed' => 'Reviewed',
            'shortlisted' => 'Shortlisted',
            'scheduled_for_interview' => 'Scheduled for Interview',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected'
        ];
        $status_display = $status_display_names[$status] ?? ucfirst($status);
        ?>
        
        <div style="text-align: center; margin-bottom: 1rem;">
            <span style="background: <?php echo $color; ?>; color: white; padding: 0.5rem 1.5rem; border-radius: 20px; font-size: 1rem; font-weight: 600;">
                <?php echo htmlspecialchars($status_display); ?>
            </span>
        </div>
        
        <?php if (!empty($application_details['reviewed_by_name'])): ?>
            <p style="color: #666; text-align: center; margin-bottom: 0.5rem;">
                <i class="fas fa-user-check"></i> Reviewed by: <?php echo htmlspecialchars($application_details['reviewed_by_name']); ?>
            </p>
        <?php endif; ?>
        
        <?php if (!empty($application_details['match_score'])): ?>
            <p style="color: #4CAF50; font-weight: 600; text-align: center; margin-bottom: 0.5rem;">
                <i class="fas fa-chart-line"></i> Match Score: <?php echo number_format($application_details['match_score'], 2); ?>%
            </p>
        <?php endif; ?>
        
        <p style="color: #666; text-align: center; font-size: 0.9rem; margin-bottom: 0.5rem;">
            <i class="fas fa-calendar-alt"></i> Applied on: <?php echo date('F d, Y', strtotime($application_details['applied_at'])); ?>
        </p>
        
        <?php if (!empty($application_details['cover_letter'])): ?>
            <div style="margin-top: 1rem;">
                <a href="#" onclick="toggleCoverLetter()" style="color: #0056b3; text-decoration: none; font-size: 0.9rem;">
                    <i class="fas fa-file-alt"></i> View Your Cover Letter
                </a>
                <div id="coverLetterContent" style="display: none; margin-top: 1rem; padding: 1rem; background: white; border-radius: 8px; border: 1px solid #e0e0e0;">
                    <p style="color: #666; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($application_details['cover_letter']); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

                    <!-- Score Calculation Info -->
                    <?php if ($profile_completed): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0;">
                        <h4 style="color: #333; margin-bottom: 0.75rem; font-size: 0.95rem;">
                            <i class="fas fa-calculator"></i> How Scores Are Calculated
                        </h4>
                        <div style="font-size: 0.85rem; color: #666;">
                            <p style="margin: 0.25rem 0;"><strong>Skills Match (40 pts):</strong> % of required skills matched</p>
                            <p style="margin: 0.25rem 0;"><strong>Experience (25 pts):</strong> 5 points per year of experience</p>
                            <p style="margin: 0.25rem 0;"><strong>Qualifications (15 pts):</strong> % of required qualifications matched</p>
                            <p style="margin: 0.25rem 0;"><strong>Chatbot (10 pts):</strong> Based on assessment performance</p>
                            <p style="margin: 0.25rem 0;"><strong>Bonuses (10 pts):</strong> Extra points for completion & scores</p>
                            <p style="margin: 0.5rem 0 0 0; font-style: italic; color: #0056b3;">Total possible: 100 points</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($has_application): ?>
                    <div style="background: #d4edda; color: #155724; padding: 1.5rem; border-radius: 10px; text-align: center; border: 1px solid #c3e6cb;">
                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem; color: #28a745;"></i>
                        <p style="font-weight: 600; margin-bottom: 0.5rem;">Application Submitted</p>
                        <p style="font-size: 0.9rem; margin-bottom: 1rem;">Your application is under review by the employer.</p>
                        
                        <?php if (!empty($applicant_remarks)): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(40, 167, 69, 0.1); border-radius: 8px; border-left: 3px solid #28a745;">
                                <p style="color: #155724; margin: 0; font-size: 0.9rem;">
                                    <i class="fas fa-comment"></i> You have <?php echo count($applicant_remarks); ?> feedback message(s) from the employer.
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (count($interviews) > 0): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(156, 39, 176, 0.1); border-radius: 8px; border-left: 3px solid #9C27B0;">
                                <p style="color: #4a148c; margin: 0; font-size: 0.9rem;">
                                    <i class="fas fa-calendar-alt"></i> You have <?php echo count($interviews); ?> scheduled interview(s).
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                            <a href="applications.php" style="color: #155724; text-decoration: underline; font-size: 0.9rem;">
                                <i class="fas fa-list"></i> View All Applications
                            </a>
                            <a href="#" onclick="window.print()" style="color: #155724; text-decoration: underline; font-size: 0.9rem;">
                                <i class="fas fa-print"></i> Print Application Details
                            </a>
                        </div>
                    </div>

                <?php elseif (!$profile_completed): ?>
                    <div style="background: #fff3cd; color: #856404; padding: 1.5rem; border-radius: 10px; text-align: center; border-left: 4px solid #ffc107;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <p style="font-weight: 600; margin-bottom: 0.5rem;">Complete Profile Required</p>
                        <p style="font-size: 0.9rem; margin-bottom: 1rem;">You must complete your profile (skills and qualifications) before applying to jobs.</p>
                        <a href="profile.php" style="display: inline-block; background: #FF9800; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-user-edit"></i> Complete Profile
                        </a>
                    </div>

                <?php else: ?>
                    <a href="apply_job.php?id=<?php echo $job_id; ?>" style="display: block; background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 1rem; border-radius: 10px; text-align: center; text-decoration: none; font-weight: 600; font-size: 1.1rem;">
                        <i class="fas fa-paper-plane"></i> Apply Now
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCoverLetter() {
    var content = document.getElementById('coverLetterContent');
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
    }
    return false;
}

function addToCalendar() {
    <?php if (!empty($interviews) && $interviews[0]['status'] === 'scheduled'): ?>
        const interview = <?php echo json_encode($interviews[0]); ?>;
        
        // Format date for calendar
        const startDate = new Date(interview.interview_date + 'T' + interview.start_time);
        const endDate = new Date(interview.interview_date + 'T' + interview.end_time);
        
        // Create Google Calendar URL
        const title = encodeURIComponent('Interview for <?php echo htmlspecialchars($job['title'], ENT_QUOTES); ?>');
        const details = encodeURIComponent(
            `Job: <?php echo htmlspecialchars($job['title'], ENT_QUOTES); ?>\n` +
            `Company: <?php echo htmlspecialchars($job['company_name'], ENT_QUOTES); ?>\n` +
            `Type: ${interview.interview_type} interview\n` +
            (interview.location ? `Location: ${interview.location}\n` : '') +
            (interview.meeting_link ? `Meeting Link: ${interview.meeting_link}\n` : '') +
            (interview.notes ? `Notes: ${interview.notes}` : '')
        );
        const location = encodeURIComponent(interview.location || interview.meeting_link || '');
        
        const startStr = startDate.toISOString().replace(/-|:|\.\d+/g, '');
        const endStr = endDate.toISOString().replace(/-|:|\.\d+/g, '');
        
        const googleCalendarUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&details=${details}&location=${location}&dates=${startStr}/${endStr}`;
        
        // Create .ics file for download
        const icsContent = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Job Portal//Interview Schedule//EN',
            'BEGIN:VEVENT',
            'UID:' + interview.id + '@jobportal',
            'DTSTAMP:' + new Date().toISOString().replace(/-|:|\.\d+/g, ''),
            'DTSTART:' + startStr,
            'DTEND:' + endStr,
            'SUMMARY:' + title,
            'DESCRIPTION:' + details.replace(/\\n/g, '\\\\n'),
            'LOCATION:' + location,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\n');
        
        // Show options to user
        const choice = confirm(
            'Add interview to calendar:\n\n' +
            '1. Open Google Calendar\n' +
            '2. Download .ics file (for Outlook, Apple Calendar, etc.)\n\n' +
            'Click OK for Google Calendar, Cancel for .ics download.'
        );
        
        if (choice) {
            window.open(googleCalendarUrl, '_blank');
        } else {
            const blob = new Blob([icsContent], { type: 'text/calendar' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'interview.ics';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    <?php endif; ?>
}
</script>

<?php include '../includes/footer.php'; ?>