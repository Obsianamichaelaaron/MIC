<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

// IDEAL MATCH SCORE WEIGHTS - Same as job_details.php
$weights = [
    'skills' => 40,        // Most important - 40%
    'experience' => 25,    // Second most important - 25%
    'qualifications' => 15, // Third - 15%
    'chatbot_assessment' => 10, // Supplemental - 10%
    'bonuses' => 10        // Extra points - 10%
    // TOTAL: 100%
];

// Check if this is an AJAX request
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// If it's an AJAX request, only output the jobs container HTML
if ($isAjax) {
    // Get all the necessary variables from the original code
    $user_id = getCurrentUserId();
    $conn = getDBConnection();
    
    // Get applicant ID
    $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $applicant_id = $applicant['applicant_id'];
    $stmt->close();
    
    // Get search parameters from GET
    $search = $_GET['search'] ?? '';
    $location = $_GET['location'] ?? '';
    $employment_type = $_GET['employment_type'] ?? '';
    $remote_option = $_GET['remote_option'] ?? '';
    $salary_range = $_GET['salary_range'] ?? '';

    // Check if chatbot assessment is completed and get selected qualification
    $selected_qualification_id = null;
    $stmt = $conn->prepare("SELECT qualification_id FROM chatbot_answers WHERE applicant_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $qual_result = $stmt->get_result()->fetch_assoc();
    if ($qual_result && !empty($qual_result['qualification_id'])) {
        $selected_qualification_id = $qual_result['qualification_id'];
    }
    $stmt->close();

    // Build query - show ALL active jobs
    $query = "SELECT jp.*, e.company_name FROM job_postings jp LEFT JOIN employers e ON jp.employer_id = e.employer_id WHERE jp.status = 'active'";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $query .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR jp.skills_required LIKE ? OR e.company_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }

    if (!empty($location)) {
        $query .= " AND jp.location LIKE ?";
        $location_param = "%$location%";
        $params[] = $location_param;
        $types .= "s";
    }

    if (!empty($employment_type) && $employment_type !== 'all') {
        $query .= " AND jp.employment_type = ?";
        $params[] = $employment_type;
        $types .= "s";
    }

    if (!empty($remote_option) && $remote_option !== 'all') {
        if ($remote_option === 'remote') {
            $query .= " AND (jp.location LIKE ? OR jp.location LIKE ? OR jp.location LIKE ?)";
            $params[] = '%Remote%';
            $params[] = '%Work from Home%';
            $params[] = '%WFH%';
            $types .= "sss";
        } elseif ($remote_option === 'hybrid') {
            $query .= " AND (jp.location LIKE ? OR jp.location LIKE ?)";
            $params[] = '%Hybrid%';
            $params[] = '%Partial Remote%';
            $types .= "ss";
        } elseif ($remote_option === 'on-site') {
            $query .= " AND jp.location NOT LIKE ? AND jp.location NOT LIKE ? AND jp.location NOT LIKE ? AND jp.location NOT LIKE ? AND jp.location NOT LIKE ?";
            $params[] = '%Remote%';
            $params[] = '%Work from Home%';
            $params[] = '%WFH%';
            $params[] = '%Hybrid%';
            $params[] = '%Partial Remote%';
            $types .= "sssss";
        }
    }

    if (!empty($salary_range) && $salary_range !== 'any') {
        // Extract minimum salary from range
        $min_salary = 0;
        switch ($salary_range) {
            case '20000':
                $min_salary = 20000;
                break;
            case '30000':
                $min_salary = 30000;
                break;
            case '50000':
                $min_salary = 50000;
                break;
            case '80000':
                $min_salary = 80000;
                break;
        }
        
        if ($min_salary > 0) {
            // This is a simplified approach - in a real system, you'd want to parse salary ranges properly
            $query .= " AND (jp.salary_range LIKE ? OR jp.salary_range LIKE ? OR jp.salary_range LIKE ? OR jp.salary_range LIKE ?)";
            $salary_param1 = "%$min_salary%";
            $salary_param2 = "%" . ($min_salary + 10000) . "%";
            $salary_param3 = "%" . ($min_salary + 20000) . "%";
            $salary_param4 = "%" . ($min_salary + 30000) . "%";
            $params[] = $salary_param1;
            $params[] = $salary_param2;
            $params[] = $salary_param3;
            $params[] = $salary_param4;
            $types .= "ssss";
        }
    }

    // Add ordering - prioritize jobs that match applicant's qualification if available
    if ($selected_qualification_id) {
        $query .= " ORDER BY (
            CASE WHEN EXISTS (
                SELECT 1 FROM job_qualification_mapping jqm 
                WHERE jqm.job_id = jp.job_id 
                AND jqm.qualification_id = ?
            ) THEN 0 ELSE 1 END
        ), jp.posted_at DESC";
        $params[] = $selected_qualification_id;
        $types .= "i";
    } else {
        $query .= " ORDER BY jp.posted_at DESC";
    }

    $stmt = $conn->prepare($query);
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get applied job IDs
    $stmt = $conn->prepare("SELECT job_id FROM applications WHERE applicant_id = ?");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $applied_jobs = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $applied_jobs[] = $row['job_id'];
    }
    $stmt->close();

    // Get saved job IDs
    $stmt = $conn->prepare("SELECT job_id FROM saved_jobs WHERE applicant_id = ?");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $saved_jobs = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $saved_jobs[] = $row['job_id'];
    }
    $stmt->close();

    // Check if profile is completed
    $stmt = $conn->prepare("SELECT profile_completed, skills, qualifications, experience_years, employability_score FROM applicants WHERE applicant_id = ?");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $applicant_data = $stmt->get_result()->fetch_assoc();
    $profile_completed = ($applicant_data['profile_completed'] ?? 0) && !empty($applicant_data['skills']) && !empty($applicant_data['qualifications']);
    $stmt->close();

    // Check if chatbot assessment is completed
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chatbot_answers WHERE applicant_id = ?");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $chatbot_result = $stmt->get_result()->fetch_assoc();
    $chatbot_completed = $chatbot_result['count'] > 0;
    $stmt->close();

    // Get chatbot answers for match score calculation
    $chatbot_answers = [];
    if ($chatbot_completed) {
        $stmt = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $chatbot_answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // CALCULATE MATCH SCORE FOR EACH JOB - CONSISTENT WITH JOB_DETAILS.PHP
    foreach ($jobs as &$job) {
        $score = 0;
        
        // 1. SKILLS MATCHING (40 points) - Most important
        if (!empty($job['skills_required']) && !empty($applicant_data['skills'])) {
            $required_skills = array_map('trim', explode(',', $job['skills_required']));
            
            $applicant_skills = !empty($applicant_data['skills']) ? 
                array_map('trim', explode(',', $applicant_data['skills'])) : [];
            
            // Find matched skills (case-insensitive partial matching) - SAME AS JOB_DETAILS.PHP
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
        
        // 2. EXPERIENCE (25 points) - Second most important - SAME AS JOB_DETAILS.PHP
        $experience_years = !empty($applicant_data['experience_years']) ? (int)$applicant_data['experience_years'] : 0;
        $exp_score = min(25, $experience_years * 5); // 5 points per year, max 25
        $exp_score = round($exp_score, 2);
        $score += $exp_score;
        
        // 3. QUALIFICATIONS MATCHING (15 points) - Third most important
        // Get job qualifications - SAME AS JOB_DETAILS.PHP
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
            // If job has no specific qualifications, award 7.5 points (half of max) - SAME AS JOB_DETAILS.PHP
            $score += 7.5;
        }
        
        // 4. CHATBOT ASSESSMENT (10 points) - Supplemental - UPDATED TO MATCH JOB_DETAILS.PHP
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
        
        // 5. BONUSES (10 points) - Extra points - SAME AS JOB_DETAILS.PHP
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
        
        // Calculate total score (max 100) - SAME AS JOB_DETAILS.PHP
        $job['match_score'] = min(100, round($score, 2));
    }
    unset($job);

    // Pre-check qualification matches for all jobs to avoid connection issues
    $job_qualification_matches = [];
    if ($selected_qualification_id && !empty($jobs)) {
        $job_ids = array_column($jobs, 'job_id');
        if (!empty($job_ids)) {
            $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
            $check_stmt = $conn->prepare("SELECT job_id FROM job_qualification_mapping WHERE job_id IN ($placeholders) AND qualification_id = ?");
            $types = str_repeat('i', count($job_ids)) . 'i';
            $params = array_merge($job_ids, [$selected_qualification_id]);
            $check_stmt->bind_param($types, ...$params);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $job_qualification_matches[$row['job_id']] = true;
            }
            $check_stmt->close();
        }
    }

    $conn->close();

    // Output only the jobs container HTML
    ?>
    <span id="jobsCount"><?php echo count($jobs); ?></span>
    <div id="activeFilters">
        <?php if (!empty($employment_type) && $employment_type !== 'all' || !empty($remote_option) && $remote_option !== 'all' || !empty($salary_range) && $salary_range !== 'any'): ?>
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <i class="fas fa-filter" style="color: #1866a3;"></i>
                    <strong style="color: #333;">Active Filters:</strong>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <?php if (!empty($employment_type) && $employment_type !== 'all'): ?>
                        <span style="background: #1866a3; color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                            <?php echo ucfirst($employment_type); ?>
                            <a href="<?php echo removeFilter('employment_type'); ?>" style="color: white; text-decoration: none;">
                            </a>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($remote_option) && $remote_option !== 'all'): ?>
                        <span style="background: #ff6a00; color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                            <?php echo ucfirst($remote_option); ?>
                            <a href="<?php echo removeFilter('remote_option'); ?>" style="color: white; text-decoration: none;">
                            </a>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($salary_range) && $salary_range !== 'any'): ?>
                        <span style="background: #4CAF50; color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                            <?php echo number_format($salary_range); ?>+
                            <a href="<?php echo removeFilter('salary_range'); ?>" style="color: white; text-decoration: none;">
                            </a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div id="jobsContainer">
        <?php if (count($jobs) > 0): ?>
            <div style="display: grid; gap: 1.5rem;">
                <?php foreach ($jobs as $job): ?>
                    <div class="job-card" style="border: 1px solid #e0e0e0; padding: 1.5rem; border-radius: 8px; transition: all 0.3s ease; background: white;">
                        <!-- Job Header -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                            <div style="flex: 1;">
                                <h2 style="color: #1866a3; margin-bottom: 0.5rem; font-size: 1.4rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </h2>
                                <?php if (!empty($job['company_name'])): ?>
                                <p style="color: #666; margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 500;">
                                    <?php echo htmlspecialchars($job['company_name']); ?>
                                </p>
                                <?php endif; ?>
                                <p style="color: #666; margin-bottom: 0.5rem;">
                                    <i class="fas fa-map-marker-alt" style="color: #ff6a00; margin-right: 0.5rem;"></i>
                                    <?php echo htmlspecialchars($job['location'] ?? 'Location Not Specified'); ?>
                                </p>
                            </div>
                            
                            <!-- Salary and Match Score -->
                            <div style="text-align: right;">
                                <?php if (!empty($job['salary_range'])): ?>
                                    <p style="color: #4CAF50; font-weight: 600; font-size: 1.1rem; margin-bottom: 0.5rem;">
                                        <?php echo htmlspecialchars($job['salary_range']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($chatbot_completed && isset($job['match_score'])): ?>
                                    <?php 
                                    // SAME COLOR CODING AS JOB_DETAILS.PHP
                                    $score_color = $job['match_score'] >= 80 ? '#28a745' : 
                                                  ($job['match_score'] >= 60 ? '#ffc107' : 
                                                  ($job['match_score'] >= 40 ? '#fd7e14' : '#dc3545'));
                                    ?>
                                    <button onclick="showMatchScorePopup(<?php echo $job['job_id']; ?>, '<?php echo htmlspecialchars($job['title'], ENT_QUOTES); ?>', <?php echo $job['match_score']; ?>)" 
                                            style="background: <?php echo $score_color; ?>; color: white; padding: 0.5rem 1rem; border: none; border-radius: 20px; font-weight: 600; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <i class="fas fa-star"></i> <?php echo number_format($job['match_score'], 1); ?>%
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Job Details -->
                        <div style="display: grid; grid-template-columns: auto auto 1fr; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                            <span style="background: #f0f8ff; color: #1866a3; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.85rem; font-weight: 500;">
                                <i class="fas fa-briefcase" style="margin-right: 0.3rem;"></i>
                                <?php echo ucfirst($job['employment_type']); ?>
                            </span>
                            
                            <?php 
                            $qualification_matches = isset($job_qualification_matches[$job['job_id']]) && $job_qualification_matches[$job['job_id']];
                            ?>
                            <?php if ($qualification_matches): ?>
                                <span style="background: #e8f5e8; color: #4CAF50; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.85rem; font-weight: 500;">
                                    <i class="fas fa-check-circle" style="margin-right: 0.3rem;"></i>
                                    Matches Your Qualification
                                </span>
                            <?php endif; ?>
                            
                            <span style="color: #999; font-size: 0.85rem; justify-self: end;">
                                <?php 
                                $posted_date = strtotime($job['posted_at']);
                                $current_date = time();
                                $days_ago = round(($current_date - $posted_date) / (60 * 60 * 24));
                                
                                if ($days_ago == 0) {
                                    echo 'Today';
                                } elseif ($days_ago == 1) {
                                    echo '1 day ago';
                                } else {
                                    echo $days_ago . ' days ago';
                                }
                                ?>
                            </span>
                        </div>
                        
                        <!-- Job Description -->
                        <p style="color: #666; margin-bottom: 1.5rem; line-height: 1.6;">
                            <?php echo substr(htmlspecialchars($job['description']), 0, 200); ?>...
                        </p>
                        
                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <a href="job_details.php?id=<?php echo $job['job_id']; ?>" 
                               style="background: #1866a3; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>
                            
                            <?php if (in_array($job['job_id'], $applied_jobs)): ?>
                                <span style="background: #4CAF50; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-check"></i>
                                    Applied
                                </span>
                            <?php elseif (!$profile_completed): ?>
                                <a href="profile.php" 
                                   style="background: #FF9800; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;"
                                   title="Complete your profile to apply">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Complete Profile to Apply
                                </a>
                            <?php else: ?>
                                <a href="apply_job.php?id=<?php echo $job['job_id']; ?>" 
                                   style="background: #ff6a00; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-paper-plane"></i>
                                    Apply Now
                                </a>
                            <?php endif; ?>
                            
                            <?php if (in_array($job['job_id'], $saved_jobs)): ?>
                                <button onclick="unsaveJob(<?php echo $job['job_id']; ?>, this)" 
                                        style="background: #4CAF50; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-bookmark"></i>
                                    Saved
                                </button>
                            <?php else: ?>
                                <button onclick="saveJob(<?php echo $job['job_id']; ?>, this)" 
                                        style="background: none; border: 1px solid #ddd; color: #666; padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="far fa-bookmark"></i>
                                    Save
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: #666;">
                <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h3 style="color: #333; margin-bottom: 0.5rem;">No jobs found</h3>
                <p>Try adjusting your search criteria or browse all available jobs.</p>
                <a href="browse_jobs.php" style="color: #1866a3; text-decoration: none; font-weight: 600;">
                    Browse all jobs
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    
    exit(); // Stop execution for AJAX request
}

// If not AJAX request, continue with normal page load
$pageTitle = "Browse Jobs";
$user_id = getCurrentUserId();

// Get applicant ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$applicant_id = $applicant['applicant_id'];
$stmt->close();

// Get search parameters
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';
$classification = $_GET['classification'] ?? '';
$employment_type = $_GET['employment_type'] ?? '';
$remote_option = $_GET['remote_option'] ?? '';
$salary_range = $_GET['salary_range'] ?? '';

// Check if chatbot assessment is completed and get selected qualification
$selected_qualification_id = null;
$stmt = $conn->prepare("SELECT qualification_id FROM chatbot_answers WHERE applicant_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$qual_result = $stmt->get_result()->fetch_assoc();
if ($qual_result && !empty($qual_result['qualification_id'])) {
    $selected_qualification_id = $qual_result['qualification_id'];
}
$stmt->close();

// Build query - show ALL active jobs
$query = "SELECT jp.*, e.company_name FROM job_postings jp LEFT JOIN employers e ON jp.employer_id = e.employer_id WHERE jp.status = 'active'";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR jp.skills_required LIKE ? OR e.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($location)) {
    $query .= " AND jp.location LIKE ?";
    $location_param = "%$location%";
    $params[] = $location_param;
    $types .= "s";
}

if (!empty($employment_type) && $employment_type !== 'all') {
    $query .= " AND jp.employment_type = ?";
    $params[] = $employment_type;
    $types .= "s";
}

if (!empty($remote_option) && $remote_option !== 'all') {
    if ($remote_option === 'remote') {
        $query .= " AND (jp.location LIKE ? OR jp.location LIKE ? OR jp.location LIKE ?)";
        $params[] = '%Remote%';
        $params[] = '%Work from Home%';
        $params[] = '%WFH%';
        $types .= "sss";
    } elseif ($remote_option === 'hybrid') {
        $query .= " AND (jp.location LIKE ? OR jp.location LIKE ?)";
        $params[] = '%Hybrid%';
        $params[] = '%Partial Remote%';
        $types .= "ss";
    } elseif ($remote_option === 'on-site') {
        $query .= " AND jp.location NOT LIKE ? AND jp.location NOT LIKE ? AND jp.location NOT LIKE ? AND jp.location NOT LIKE ? AND jp.location NOT LIKE ?";
        $params[] = '%Remote%';
        $params[] = '%Work from Home%';
        $params[] = '%WFH%';
        $params[] = '%Hybrid%';
        $params[] = '%Partial Remote%';
        $types .= "sssss";
    }
}

if (!empty($salary_range) && $salary_range !== 'any') {
    // Extract minimum salary from range
    $min_salary = 0;
    switch ($salary_range) {
        case '20000':
            $min_salary = 20000;
            break;
        case '30000':
            $min_salary = 30000;
            break;
        case '50000':
            $min_salary = 50000;
            break;
        case '80000':
            $min_salary = 80000;
            break;
    }
    
    if ($min_salary > 0) {
        // This is a simplified approach - in a real system, you'd want to parse salary ranges properly
        $query .= " AND (jp.salary_range LIKE ? OR jp.salary_range LIKE ? OR jp.salary_range LIKE ? OR jp.salary_range LIKE ?)";
        $salary_param1 = "%$min_salary%";
        $salary_param2 = "%" . ($min_salary + 10000) . "%";
        $salary_param3 = "%" . ($min_salary + 20000) . "%";
        $salary_param4 = "%" . ($min_salary + 30000) . "%";
        $params[] = $salary_param1;
        $params[] = $salary_param2;
        $params[] = $salary_param3;
        $params[] = $salary_param4;
        $types .= "ssss";
    }
}

// Add ordering - prioritize jobs that match applicant's qualification if available
if ($selected_qualification_id) {
    $query .= " ORDER BY (
        CASE WHEN EXISTS (
            SELECT 1 FROM job_qualification_mapping jqm 
            WHERE jqm.job_id = jp.job_id 
            AND jqm.qualification_id = ?
        ) THEN 0 ELSE 1 END
    ), jp.posted_at DESC";
    $params[] = $selected_qualification_id;
    $types .= "i";
} else {
    $query .= " ORDER BY jp.posted_at DESC";
}

$stmt = $conn->prepare($query);
if (!empty($params) && !empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get applied job IDs
$stmt = $conn->prepare("SELECT job_id FROM applications WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$applied_jobs = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $applied_jobs[] = $row['job_id'];
}
$stmt->close();

// Get saved job IDs
$stmt = $conn->prepare("SELECT job_id FROM saved_jobs WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$saved_jobs = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $saved_jobs[] = $row['job_id'];
}
$stmt->close();

// Check if profile is completed and get applicant data
$stmt = $conn->prepare("SELECT profile_completed, skills, qualifications, experience_years, employability_score FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$applicant_data = $stmt->get_result()->fetch_assoc();
$profile_completed = ($applicant_data['profile_completed'] ?? 0) && !empty($applicant_data['skills']) && !empty($applicant_data['qualifications']);
$stmt->close();

// Check if chatbot assessment is completed
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chatbot_answers WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$chatbot_result = $stmt->get_result()->fetch_assoc();
$chatbot_completed = $chatbot_result['count'] > 0;
$stmt->close();

// Get chatbot answers for match score calculation
$chatbot_answers = [];
if ($chatbot_completed) {
    $stmt = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $chatbot_answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// CALCULATE MATCH SCORE FOR EACH JOB - CONSISTENT WITH JOB_DETAILS.PHP
foreach ($jobs as &$job) {
    $score = 0;
    
    // 1. SKILLS MATCHING (40 points) - Most important
    if (!empty($job['skills_required']) && !empty($applicant_data['skills'])) {
        $required_skills = array_map('trim', explode(',', $job['skills_required']));
        
        $applicant_skills = !empty($applicant_data['skills']) ? 
            array_map('trim', explode(',', $applicant_data['skills'])) : [];
        
        // Find matched skills (case-insensitive partial matching) - SAME AS JOB_DETAILS.PHP
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
    
    // 2. EXPERIENCE (25 points) - Second most important - SAME AS JOB_DETAILS.PHP
    $experience_years = !empty($applicant_data['experience_years']) ? (int)$applicant_data['experience_years'] : 0;
    $exp_score = min(25, $experience_years * 5); // 5 points per year, max 25
    $exp_score = round($exp_score, 2);
    $score += $exp_score;
    
    // 3. QUALIFICATIONS MATCHING (15 points) - Third most important
    // Get job qualifications - SAME AS JOB_DETAILS.PHP
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
        // If job has no specific qualifications, award 7.5 points (half of max) - SAME AS JOB_DETAILS.PHP
        $score += 7.5;
    }
    
    // 4. CHATBOT ASSESSMENT (10 points) - Supplemental - UPDATED TO MATCH JOB_DETAILS.PHP
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
    
    // 5. BONUSES (10 points) - Extra points - SAME AS JOB_DETAILS.PHP
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
    
    // Calculate total score (max 100) - SAME AS JOB_DETAILS.PHP
    $job['match_score'] = min(100, round($score, 2));
}
unset($job);

// Pre-check qualification matches for all jobs to avoid connection issues
$job_qualification_matches = [];
if ($selected_qualification_id && !empty($jobs)) {
    $job_ids = array_column($jobs, 'job_id');
    if (!empty($job_ids)) {
        $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
        $check_stmt = $conn->prepare("SELECT job_id FROM job_qualification_mapping WHERE job_id IN ($placeholders) AND qualification_id = ?");
        $types = str_repeat('i', count($job_ids)) . 'i';
        $params = array_merge($job_ids, [$selected_qualification_id]);
        $check_stmt->bind_param($types, ...$params);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $job_qualification_matches[$row['job_id']] = true;
        }
        $check_stmt->close();
    }
}

$conn->close();

include '../includes/header.php';
?>

<!-- Full Width Search Section -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 2rem 0; margin-top: 0px; margin-right: -8px; margin-bottom: 2rem; margin-left: -8px;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 1rem;">
        <!-- Search Form with Saved Jobs Button -->
        <div style="display: flex; gap: 0.5rem; align-items: center; max-width: 100%;">
            <!-- Search Form -->
            <form method="GET" id="searchForm" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 0.5rem; flex: 1;">
                <!-- What Input -->
                <div style="position: relative;">
                    <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #666; font-weight: 600; font-size: 0.9rem;">
                        What
                    </div>
                    <input type="text" name="search" id="searchInput" placeholder="Enter keywords" value="<?php echo htmlspecialchars($search); ?>" 
                           style="padding: 0.75rem 0.75rem 0.75rem 60px; border: 2px solid #ddd; border-radius: 4px; width: 100%; font-size: 1rem; height: 50px;"
                           oninput="handleSearchInput()">
                    <div id="searchLoading" style="display: none; position: absolute; right: 12px; top: 50%; transform: translateY(-50%);">
                        <i class="fas fa-spinner fa-spin" style="color: #666;"></i>
                    </div>
                </div>
                
                <!-- Where Input -->
                <div style="position: relative;">
                    <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #666; font-weight: 600; font-size: 0.9rem;">
                        Where
                    </div>
                    <input type="text" name="location" id="locationInput" placeholder="Enter suburb, city, or region" value="<?php echo htmlspecialchars($location); ?>" 
                           style="padding: 0.75rem 0.75rem 0.75rem 60px; border: 2px solid #ddd; border-radius: 4px; width: 100%; font-size: 1rem; height: 50px;"
                           oninput="handleLocationInput()">
                </div>
                
                <!-- Search Button -->
                <button type="submit" id="searchButton"
                        style="background: #ff6a00; color: white; padding: 0.75rem 2rem; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 1rem; height: 50px; min-width: 120px;">
                    Search
                </button>
            </form>
            
            <!-- Saved Jobs Button (Logo Only) -->
            <a href="save_job.php" 
               style="background: #1866a3; color: white; padding: 0.75rem; border: none; border-radius: 4px; cursor: pointer; font-size: 1.2rem; height: 50px; width: 50px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease;"
               onmouseover="this.style.background='#004494'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.background='#1866a3'; this.style.transform='translateY(0)';"
               title="View Saved Jobs">
                <i class="fas fa-bookmark"></i>
            </a>
        </div>
        
        <!-- More Options -->
        <div style="text-align: center; margin-top: 1rem;">
            <a href="#" onclick="toggleMoreOptions()" style="color: white; text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                <span>More options</span>
                <i class="fas fa-chevron-down" id="moreOptionsIcon"></i>
            </a>
        </div>
        
        <!-- More Options Panel -->
        <div id="moreOptionsPanel" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.3);">
            <form method="GET" id="moreOptionsForm">
                <!-- Hidden fields to preserve main search parameters -->
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; max-width: 100%;">
                    <!-- Employment Type -->
                    <div>
                        <label style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: block;">Employment Type</label>
                        <select name="employment_type" id="employmentType" style="width: 100%; padding: 0.75rem; border: 2px solid #ddd; border-radius: 4px; font-size: 1rem; height: 45px;"
                                onchange="handleFilterChange()">
                            <option value="all">All employment types</option>
                            <option value="full-time" <?php echo ($employment_type === 'full-time') ? 'selected' : ''; ?>>Full Time</option>
                            <option value="part-time" <?php echo ($employment_type === 'part-time') ? 'selected' : ''; ?>>Part Time</option>
                            <option value="contract" <?php echo ($employment_type === 'contract') ? 'selected' : ''; ?>>Contract</option>
                            <option value="temporary" <?php echo ($employment_type === 'temporary') ? 'selected' : ''; ?>>Temporary</option>
                            <option value="internship" <?php echo ($employment_type === 'internship') ? 'selected' : ''; ?>>Internship</option>
                        </select>
                    </div>
                    
                    <!-- Remote Options -->
                    <div>
                        <label style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: block;">Remote Options</label>
                        <select name="remote_option" id="remoteOption" style="width: 100%; padding: 0.75rem; border: 2px solid #ddd; border-radius: 4px; font-size: 1rem; height: 45px;"
                                onchange="handleFilterChange()">
                            <option value="all">All remote options</option>
                            <option value="remote" <?php echo ($remote_option === 'remote') ? 'selected' : ''; ?>>Remote</option>
                            <option value="hybrid" <?php echo ($remote_option === 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                            <option value="on-site" <?php echo ($remote_option === 'on-site') ? 'selected' : ''; ?>>On-site</option>
                        </select>
                    </div>
                    
                    <!-- Salary Range -->
                    <div>
                        <label style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: block;">Salary Range</label>
                        <select name="salary_range" id="salaryRange" style="width: 100%; padding: 0.75rem; border: 2px solid #ddd; border-radius: 4px; font-size: 1rem; height: 45px;"
                                onchange="handleFilterChange()">
                            <option value="any">Any salary</option>
                            <option value="20000" <?php echo ($salary_range === '20000') ? 'selected' : ''; ?>>₱20,000+</option>
                            <option value="30000" <?php echo ($salary_range === '30000') ? 'selected' : ''; ?>>₱30,000+</option>
                            <option value="50000" <?php echo ($salary_range === '50000') ? 'selected' : ''; ?>>₱50,000+</option>
                            <option value="80000" <?php echo ($salary_range === '80000') ? 'selected' : ''; ?>>₱80,000+</option>
                        </select>
                    </div>
                </div>
                
                <!-- Apply Filters Button -->
                <div style="text-align: center; margin-top: 1.5rem;">
                    <button type="submit" 
                            style="background: #ff6a00; color: white; padding: 0.75rem 2rem; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 1rem;">
                        Apply Filters
                    </button>
                    <button type="button" onclick="clearFilters()"
                            style="background: transparent; color: white; padding: 0.75rem 2rem; border: 1px solid white; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 1rem; margin-left: 0.5rem;">
                        Clear Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="container">
    <!-- Jobs Content Section -->
    <div class="profile-content" id="jobsContent">
        <div class="card" style="margin-top: 10px;">
            <!-- Jobs Count and Active Filters -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 0 0.5rem;">
                <h2 style="color: #333; margin: 0; font-size: 1.3rem;">
                    <span id="jobsCount"><?php echo count($jobs); ?></span> jobs found
                </h2>
                <div style="color: #666; font-size: 0.9rem;">
                    Sorted by: <strong>Most relevant</strong>
                </div>
            </div>
            
            <!-- Active Filters Display -->
            <div id="activeFilters">
                <?php if (!empty($employment_type) && $employment_type !== 'all' || !empty($remote_option) && $remote_option !== 'all' || !empty($salary_range) && $salary_range !== 'any'): ?>
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <i class="fas fa-filter" style="color: #1866a3;"></i>
                            <strong style="color: #333;">Active Filters:</strong>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php if (!empty($employment_type) && $employment_type !== 'all'): ?>
                                <span style="background: #1866a3; color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                    <?php echo ucfirst($employment_type); ?>
                                    <a href="<?php echo removeFilter('employment_type'); ?>" style="color: white; text-decoration: none;">
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($remote_option) && $remote_option !== 'all'): ?>
                                <span style="background: #ff6a00; color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                    <?php echo ucfirst($remote_option); ?>
                                    <a href="<?php echo removeFilter('remote_option'); ?>" style="color: white; text-decoration: none;">
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($salary_range) && $salary_range !== 'any'): ?>
                                <span style="background: #4CAF50; color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                    <?php echo number_format($salary_range); ?>+
                                    <a href="<?php echo removeFilter('salary_range'); ?>" style="color: white; text-decoration: none;">
                                    </a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Jobs Container -->
            <div id="jobsContainer">
                <?php if (count($jobs) > 0): ?>
                    <div style="display: grid; gap: 1.5rem;">
                        <?php foreach ($jobs as $job): ?>
                            <div class="job-card" style="border: 1px solid #e0e0e0; padding: 1.5rem; border-radius: 8px; transition: all 0.3s ease; background: white;"
                                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#1866a3';"
                                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                                
                                <!-- Job Header -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                    <div style="flex: 1;">
                                        <h2 style="color: #1866a3; margin-bottom: 0.5rem; font-size: 1.4rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </h2>
                                        <?php if (!empty($job['company_name'])): ?>
                                        <p style="color: #666; margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 500;">
                                            <?php echo htmlspecialchars($job['company_name']); ?>
                                        </p>
                                        <?php endif; ?>
                                        <p style="color: #666; margin-bottom: 0.5rem;">
                                            <i class="fas fa-map-marker-alt" style="color: #ff6a00; margin-right: 0.5rem;"></i>
                                            <?php echo htmlspecialchars($job['location'] ?? 'Location Not Specified'); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Salary and Match Score -->
                                    <div style="text-align: right;">
                                        <?php if (!empty($job['salary_range'])): ?>
                                            <p style="color: #4CAF50; font-weight: 600; font-size: 1.1rem; margin-bottom: 0.5rem;">
                                                <?php echo htmlspecialchars($job['salary_range']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($chatbot_completed && isset($job['match_score'])): ?>
                                            <?php 
                                            // SAME COLOR CODING AS JOB_DETAILS.PHP
                                            $score_color = $job['match_score'] >= 80 ? '#28a745' : 
                                                          ($job['match_score'] >= 60 ? '#ffc107' : 
                                                          ($job['match_score'] >= 40 ? '#fd7e14' : '#dc3545'));
                                            ?>
                                            <button onclick="showMatchScorePopup(<?php echo $job['job_id']; ?>, '<?php echo htmlspecialchars($job['title'], ENT_QUOTES); ?>', <?php echo $job['match_score']; ?>)" 
                                                    style="background: <?php echo $score_color; ?>; color: white; padding: 0.5rem 1rem; border: none; border-radius: 20px; font-weight: 600; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                                <i class="fas fa-star"></i> <?php echo number_format($job['match_score'], 1); ?>%
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Job Details -->
                                <div style="display: grid; grid-template-columns: auto auto 1fr; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                    <span style="background: #f0f8ff; color: #1866a3; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.85rem; font-weight: 500;">
                                        <i class="fas fa-briefcase" style="margin-right: 0.3rem;"></i>
                                        <?php echo ucfirst($job['employment_type']); ?>
                                    </span>
                                    
                                    <?php 
                                    // Check if job matches applicant's qualification
                                    $qualification_matches = isset($job_qualification_matches[$job['job_id']]) && $job_qualification_matches[$job['job_id']];
                                    ?>
                                    <?php if ($qualification_matches): ?>
                                        <span style="background: #e8f5e8; color: #4CAF50; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.85rem; font-weight: 500;">
                                            <i class="fas fa-check-circle" style="margin-right: 0.3rem;"></i>
                                            Matches Your Qualification
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="color: #999; font-size: 0.85rem; justify-self: end;">
                                        <?php 
                                        $posted_date = strtotime($job['posted_at']);
                                        $current_date = time();
                                        $days_ago = round(($current_date - $posted_date) / (60 * 60 * 24));
                                        
                                        if ($days_ago == 0) {
                                            echo 'Today';
                                        } elseif ($days_ago == 1) {
                                            echo '1 day ago';
                                        } else {
                                            echo $days_ago . ' days ago';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <!-- Job Description -->
                                <p style="color: #666; margin-bottom: 1.5rem; line-height: 1.6;">
                                    <?php echo substr(htmlspecialchars($job['description']), 0, 200); ?>...
                                </p>
                                
                                <!-- Action Buttons -->
                                <div style="display: flex; gap: 1rem; align-items: center;">
                                    <a href="job_details.php?id=<?php echo $job['job_id']; ?>" 
                                       style="background: #1866a3; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </a>
                                    
                                    <?php if (in_array($job['job_id'], $applied_jobs)): ?>
                                        <span style="background: #4CAF50; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-check"></i>
                                            Applied
                                        </span>
                                    <?php elseif (!$profile_completed): ?>
                                        <a href="profile.php" 
                                           style="background: #FF9800; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;"
                                           title="Complete your profile to apply">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Complete Profile to Apply
                                        </a>
                                    <?php else: ?>
                                        <a href="apply_job.php?id=<?php echo $job['job_id']; ?>" 
                                           style="background: #ff6a00; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-paper-plane"></i>
                                            Apply Now
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($job['job_id'], $saved_jobs)): ?>
                                        <button onclick="unsaveJob(<?php echo $job['job_id']; ?>, this)" 
                                                style="background: #4CAF50; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-bookmark"></i>
                                            Saved
                                        </button>
                                    <?php else: ?>
                                        <button onclick="saveJob(<?php echo $job['job_id']; ?>, this)" 
                                                style="background: none; border: 1px solid #ddd; color: #666; padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                            <i class="far fa-bookmark"></i>
                                            Save
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div id="noJobsMessage" style="text-align: center; padding: 3rem; color: #666;">
                        <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <h3 style="color: #333; margin-bottom: 0.5rem;">No jobs found</h3>
                        <p>Try adjusting your search criteria or browse all available jobs.</p>
                        <a href="browse_jobs.php" style="color: #1866a3; text-decoration: none; font-weight: 600;">
                            Browse all jobs
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Match Score Popup -->
<div id="matchScorePopup" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 600px; margin: 2rem auto; background: white; border-radius: 15px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="color: #1866a3; margin: 0;">
                <i class="fas fa-star"></i> Match Score
            </h2>
            <button onclick="closeMatchScorePopup()" style="background: #f44336; color: white; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 1.2rem;">
                ×
            </button>
        </div>
        
        <div id="matchScoreContent" style="text-align: center;">
            <!-- Content will be inserted here -->
        </div>
        
        <div style="margin-top: 1.5rem; text-align: center;">
            <button onclick="closeMatchScorePopup()" style="background: #1866a3; color: white; padding: 0.75rem 2rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Search timer variable
let searchTimer;

function handleSearchInput() {
    const searchInput = document.getElementById('searchInput');
    const searchLoading = document.getElementById('searchLoading');
    
    // Clear previous timer
    clearTimeout(searchTimer);
    
    // Show loading indicator
    searchLoading.style.display = 'block';
    
    // Set new timer (500ms delay)
    searchTimer = setTimeout(() => {
        performSearch();
    }, 500);
}

function handleLocationInput() {
    const locationInput = document.getElementById('locationInput');
    
    // Clear previous timer
    clearTimeout(searchTimer);
    
    // Set new timer (500ms delay)
    searchTimer = setTimeout(() => {
        performSearch();
    }, 500);
}

function handleFilterChange() {
    // Perform search immediately when filter changes
    performSearch();
}

function performSearch() {
    const searchInput = document.getElementById('searchInput');
    const locationInput = document.getElementById('locationInput');
    const employmentType = document.getElementById('employmentType');
    const remoteOption = document.getElementById('remoteOption');
    const salaryRange = document.getElementById('salaryRange');
    const searchLoading = document.getElementById('searchLoading');
    
    // Hide loading indicator
    searchLoading.style.display = 'none';
    
    // Get current URL parameters
    const params = new URLSearchParams(window.location.search);
    
    // Update search parameters
    if (searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    } else {
        params.delete('search');
    }
    
    if (locationInput.value.trim()) {
        params.set('location', locationInput.value.trim());
    } else {
        params.delete('location');
    }
    
    if (employmentType.value !== 'all') {
        params.set('employment_type', employmentType.value);
    } else {
        params.delete('employment_type');
    }
    
    if (remoteOption.value !== 'all') {
        params.set('remote_option', remoteOption.value);
    } else {
        params.delete('remote_option');
    }
    
    if (salaryRange.value !== 'any') {
        params.set('salary_range', salaryRange.value);
    } else {
        params.delete('salary_range');
    }
    
    // Update URL without page reload using History API
    const newUrl = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', newUrl);
    
    // Show loading state on jobs container
    const jobsContainer = document.getElementById('jobsContainer');
    const jobsCount = document.getElementById('jobsCount');
    
    jobsContainer.innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #1866a3; margin-bottom: 1rem;"></i>
            <p style="color: #666;">Searching jobs...</p>
        </div>
    `;
    
    // Add ajax parameter to the URL
    const ajaxUrl = newUrl + '&ajax=1';
    
    // Fetch search results via AJAX
    fetch(ajaxUrl)
        .then(response => response.text())
        .then(html => {
            // Parse the response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Extract content from the parsed document
            const jobsCountElement = doc.body.querySelector('#jobsCount');
            const activeFiltersElement = doc.body.querySelector('#activeFilters');
            const jobsContainerElement = doc.body.querySelector('#jobsContainer');
            
            // Update the page content
            if (jobsCountElement) {
                jobsCount.textContent = jobsCountElement.textContent || '0';
            }
            
            if (activeFiltersElement) {
                const activeFiltersDiv = document.getElementById('activeFilters');
                activeFiltersDiv.innerHTML = activeFiltersElement.innerHTML;
            }
            
            if (jobsContainerElement) {
                jobsContainer.innerHTML = jobsContainerElement.innerHTML;
            }
            
            // Re-initialize event listeners for new content
            initializeEventListeners();
        })
        .catch(error => {
            console.error('Search error:', error);
            jobsContainer.innerHTML = `
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ff6a00; margin-bottom: 1rem;"></i>
                    <h3 style="color: #333; margin-bottom: 0.5rem;">Search Error</h3>
                    <p>There was an error searching for jobs. Please try again.</p>
                </div>
            `;
            jobsCount.textContent = '0';
        });
}

function initializeEventListeners() {
    // Re-attach event listeners to newly loaded content
    const jobCards = document.querySelectorAll('.job-card');
    jobCards.forEach(card => {
        card.addEventListener('mouseover', function() {
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            this.style.borderColor = '#1866a3';
        });
        
        card.addEventListener('mouseout', function() {
            this.style.boxShadow = 'none';
            this.style.borderColor = '#e0e0e0';
        });
    });
}

function toggleMoreOptions() {
    const panel = document.getElementById('moreOptionsPanel');
    const icon = document.getElementById('moreOptionsIcon');
    
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        icon.className = 'fas fa-chevron-up';
    } else {
        panel.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
    }
}

function clearFilters() {
    // Reset all form inputs
    document.getElementById('searchInput').value = '';
    document.getElementById('locationInput').value = '';
    document.getElementById('employmentType').value = 'all';
    document.getElementById('remoteOption').value = 'all';
    document.getElementById('salaryRange').value = 'any';
    
    // Clear URL parameters
    const newUrl = window.location.pathname;
    window.history.replaceState({}, '', newUrl);
    
    // Perform search with empty filters
    performSearch();
}

function showMatchScorePopup(jobId, jobTitle, matchScore) {
    const popup = document.getElementById('matchScorePopup');
    const content = document.getElementById('matchScoreContent');
    
    // SAME COLOR CODING AS JOB_DETAILS.PHP
    const scoreColor = matchScore >= 80 ? '#28a745' : 
                     matchScore >= 60 ? '#ffc107' : 
                     matchScore >= 40 ? '#fd7e14' : '#dc3545';
    const scoreLabel = matchScore >= 80 ? 'Excellent Match!' : 
                      matchScore >= 60 ? 'Good Match' : 
                      matchScore >= 40 ? 'Fair Match' : 'Needs Improvement';
    
    let html = `
        <h3 style="color: #333; margin-bottom: 1rem;">${jobTitle}</h3>
        <div style="background: linear-gradient(135deg, ${scoreColor} 0%, ${scoreColor}dd 100%); padding: 2rem; border-radius: 10px; margin-bottom: 1.5rem;">
            <p style="color: white; font-size: 0.9rem; margin-bottom: 0.5rem; opacity: 0.9;">Your Match Score</p>
            <p style="color: white; font-size: 3rem; font-weight: 700; margin: 0.5rem 0;">
                ${matchScore.toFixed(1)}%
            </p>
            <p style="color: white; font-size: 1rem; margin-top: 0.5rem; opacity: 0.9;">
                ${scoreLabel}
            </p>
        </div>
        <div style="text-align: left; background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
            <h4 style="color: #333; margin-bottom: 0.75rem;">How this score is calculated:</h4>
            <ul style="color: #666; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
                <li><strong>Skills Match (40 pts):</strong> % of required skills matched</li>
                <li><strong>Experience (25 pts):</strong> 5 points per year of experience</li>
                <li><strong>Qualifications (15 pts):</strong> % of required qualifications matched</li>
                <li><strong>Chatbot Assessment (10 pts):</strong> Based on assessment performance</li>
                <li><strong>Bonuses (10 pts):</strong> Extra points for completion & scores</li>
                <li><em style="color: #0056b3;">Total possible: 100 points</em></li>
            </ul>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <a href="job_details.php?id=${jobId}" style="background: #1866a3; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600;">
                View Full Analysis
            </a>
            <a href="apply_job.php?id=${jobId}" style="background: #ff6a00; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600;">
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

function saveJob(jobId, button) {
    // Show loading state
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    button.disabled = true;
    
    // Send AJAX request to save job
    fetch('save_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `job_id=${jobId}&action=save`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button to saved state
            button.innerHTML = '<i class="fas fa-bookmark"></i> Saved';
            button.style.background = '#4CAF50';
            button.style.color = 'white';
            button.style.border = 'none';
            button.onclick = function() { unsaveJob(jobId, this); };
            
            // Show success message
            showNotification('Job saved successfully!', 'success');
        } else {
            showNotification('Failed to save job: ' + data.message, 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving job. Please try again.', 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function unsaveJob(jobId, button) {
    // Show loading state
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
    button.disabled = true;
    
    // Send AJAX request to unsave job
    fetch('save_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `job_id=${jobId}&action=unsave`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button to unsaved state
            button.innerHTML = '<i class="far fa-bookmark"></i> Save';
            button.style.background = 'none';
            button.style.color = '#666';
            button.style.border = '1px solid #ddd';
            button.onclick = function() { saveJob(jobId, this); };
            
            // Show success message
            showNotification('Job removed from saved list!', 'success');
        } else {
            showNotification('Failed to remove job: ' +data.message, 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error removing job. Please try again.', 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 6px;
        color: white;
        font-weight: 600;
        z-index: 10001;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        max-width: 300px;
    `;
    
    if (type === 'success') {
        notification.style.background = '#4CAF50';
    } else {
        notification.style.background = '#f44336';
    }
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Initialize event listeners on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    
    // Auto-open more options panel if filters are active
    <?php if (!empty($employment_type) && $employment_type !== 'all' || !empty($remote_option) && $remote_option !== 'all' || !empty($salary_range) && $salary_range !== 'any'): ?>
        toggleMoreOptions();
    <?php endif; ?>
});

// Close popup when clicking outside
document.getElementById('matchScorePopup')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMatchScorePopup();
    }
});

// Handle Enter key in search inputs
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        performSearch();
    }
});

document.getElementById('locationInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        performSearch();
    }
});
</script>

<?php
// Helper function to remove individual filters
function removeFilter($filter_name) {
    $params = $_GET;
    unset($params[$filter_name]);
    return 'browse_jobs.php?' . http_build_query($params);
}
?>

<?php include '../includes/footer.php'; ?>