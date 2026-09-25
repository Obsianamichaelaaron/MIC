<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

$pageTitle = "Apply for Job";
$job_id = $_GET['id'] ?? 0;
$user_id = getCurrentUserId();

// Get job details
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT jp.*, e.company_name FROM job_postings jp LEFT JOIN employers e ON jp.employer_id = e.employer_id WHERE jp.job_id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header('Location: jobs.php');
    exit();
}

// Get applicant ID and profile status
$stmt = $conn->prepare("SELECT applicant_id, skills, qualifications, profile_completed, resume_file, experience_years, employability_score FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$applicant_id = $applicant['applicant_id'];
$profile_completed = $applicant['profile_completed'] ?? 0;
$stmt->close();

// Check if chatbot assessment is completed
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chatbot_answers WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$chatbot_result = $stmt->get_result()->fetch_assoc();
$chatbot_completed = $chatbot_result['count'] > 0;
$stmt->close();

// If profile not completed, redirect with error
if (!$profile_completed || empty($applicant['skills']) || empty($applicant['qualifications'])) {
    $_SESSION['error'] = "You must complete your profile (skills and qualifications) before applying to jobs. Please update your profile first.";
    header('Location: profile.php');
    exit();
}

// Check if already applied
$stmt = $conn->prepare("SELECT * FROM applications WHERE job_id = ? AND applicant_id = ?");
$stmt->bind_param("ii", $job_id, $applicant_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    header('Location: applications.php');
    exit();
}
$stmt->close();

// ========== EXACT SAME CALCULATION AS JOBS.PHP AND JOB_DETAILS.PHP ==========
$match_score = 0;

// Get chatbot answers
$chatbot_answers = [];
$stmt_chat = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
$stmt_chat->bind_param("i", $applicant_id);
$stmt_chat->execute();
$chatbot_answers = $stmt_chat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_chat->close();

// 1. SKILLS MATCHING (40 points) - Most important - SAME AS JOBS.PHP
if (!empty($job['skills_required']) && !empty($applicant['skills'])) {
    $required_skills = array_map('trim', explode(',', $job['skills_required']));
    
    $applicant_skills = !empty($applicant['skills']) ? 
        array_map('trim', explode(',', $applicant['skills'])) : [];
    
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
        $match_score += $skills_score;
    }
}

// 2. EXPERIENCE (25 points) - Second most important - SAME AS JOBS.PHP
$experience_years = !empty($applicant['experience_years']) ? (int)$applicant['experience_years'] : 0;
$exp_score = min(25, $experience_years * 5); // 5 points per year, max 25
$exp_score = round($exp_score, 2);
$match_score += $exp_score;

// 3. QUALIFICATIONS MATCHING (15 points) - Third most important
// Get job qualifications - SAME AS JOBS.PHP
$job_qualifications = [];
$qual_stmt = $conn->prepare("
    SELECT q.name 
    FROM qualifications q 
    INNER JOIN job_qualification_mapping jqm ON q.qualification_id = jqm.qualification_id 
    WHERE jqm.job_id = ?
");
$qual_stmt->bind_param("i", $job_id);
$qual_stmt->execute();
$job_qualifications = $qual_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qual_stmt->close();

if (!empty($job_qualifications)) {
    $applicant_qualifications = !empty($applicant['qualifications']) ? 
        strtolower($applicant['qualifications']) : '';
    
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
        $match_score += $qual_score;
    }
} else {
    // If job has no specific qualifications, award 7.5 points (half of max) - SAME AS JOBS.PHP
    $match_score += 7.5;
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
        $match_score += $chatbot_score;
    }
}

// 5. BONUSES (10 points) - Extra points - SAME AS JOBS.PHP
$bonus_total = 0;

// Chatbot completion bonus (5 points)
if (!empty($chatbot_answers) && count($chatbot_answers) >= 5) {
    $bonus_total += 5;
}

// Employability score bonus (5 points)
if (!empty($applicant['employability_score']) && $applicant['employability_score'] > 0) {
    $emp_bonus = min(5, ($applicant['employability_score'] / 100) * 5);
    $emp_bonus = round($emp_bonus, 2);
    $bonus_total += $emp_bonus;
}

$match_score += $bonus_total;

// Calculate base match score (max 100) - SAME AS JOBS.PHP
$match_score = min(100, round($match_score, 2));
// ========== END OF EXACT SAME CALCULATION ==========

// Resume quality score (additional for apply job page only)
$resume_quality_score = 0;
$resume_feedback = "";

if (!empty($applicant['resume_file']) && file_exists('../' . $applicant['resume_file'])) {
    require_once '../includes/handlers/resume_parser.php';
    $resume_analysis = getResumeAnalysis($conn, $applicant_id);
    
    if ($resume_analysis) {
        // Calculate resume quality score (0-10 points)
        if (!empty($resume_analysis['skills_extracted'])) $resume_quality_score += 3;
        if (!empty($resume_analysis['education_extracted']) || !empty($resume_analysis['qualifications_extracted'])) $resume_quality_score += 3;
        if (!empty($resume_analysis['experience_extracted'])) $resume_quality_score += 2;
        if (!empty($resume_analysis['extracted_text']) && strlen($resume_analysis['extracted_text']) > 500) $resume_quality_score += 2;
        
        // Generate feedback
        if ($resume_quality_score >= 8) {
            $resume_feedback = "Excellent resume! Your resume is well-structured and comprehensive.";
        } elseif ($resume_quality_score >= 6) {
            $resume_feedback = "Your resume is good and contains relevant information.";
        } elseif ($resume_quality_score >= 4) {
            $resume_feedback = "Your resume is okay, but could be improved with more details.";
        } else {
            $resume_feedback = "Your resume needs improvement. Consider adding more skills, education, and experience details.";
        }
    }
}

// Final score with resume quality (for display only)
$final_match_score = $match_score + $resume_quality_score;
$final_match_score = min(100, round($final_match_score, 2));

// Generate match score feedback message - SAME AS JOBS.PHP
$match_feedback = "";
if ($final_match_score >= 80) {
    $match_feedback = "Excellent Match! Your profile strongly aligns with this job.";
} elseif ($final_match_score >= 60) {
    $match_feedback = "Good Match! Your profile aligns well with this job.";
} elseif ($final_match_score >= 40) {
    $match_feedback = "Fair Match! Your profile has moderate alignment with this job.";
} else {
    $match_feedback = "Needs Improvement. Consider improving your skills and qualifications for better matches.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cover_letter = trim($_POST['cover_letter'] ?? '');
    $resume_file = null;
    
    // Handle resume file upload
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resume'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($file_ext !== 'pdf') {
            $error = "Only PDF files are allowed for resume upload";
        } elseif ($file_size > 5242880) {
            $error = "File size must be less than 5MB";
        } else {
            $upload_dir = __DIR__ . '/../uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = uniqid('application_resume_', true) . '_' . time() . '.pdf';
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $resume_file = 'uploads/resumes/' . $new_filename;
            } else {
                $error = "Failed to upload resume file";
            }
        }
    } elseif (!empty($applicant['resume_file'])) {
        $resume_file = $applicant['resume_file'];
    }
    
    if (!isset($error)) {
        // Use the SAME calculation for application submission - WITHOUT resume quality score
        $application_match_score = $match_score; // Use the base score calculated above (SAME AS JOBS.PHP)
        
        // Insert application - store only the base match score (same as jobs.php)
        if ($resume_file) {
            $stmt = $conn->prepare("INSERT INTO applications (job_id, applicant_id, cover_letter, resume_file, match_score, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iissd", $job_id, $applicant_id, $cover_letter, $resume_file, $application_match_score);
        } else {
            $stmt = $conn->prepare("INSERT INTO applications (job_id, applicant_id, cover_letter, match_score, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iisd", $job_id, $applicant_id, $cover_letter, $application_match_score);
        }
        
        if ($stmt->execute()) {
            // Create notification for employer
            $employer_user_id = getEmployerUserId($conn, $job['employer_id']);
            createNotification($conn, $employer_user_id, "New Application", "A new application has been submitted for: " . $job['title'], 'application');
            
            header('Location: applications.php?success=applied');
            exit();
        } else {
            $error = "Failed to submit application";
        }
        
        $stmt->close();
    }
}

function getEmployerUserId($conn, $employer_id) {
    $stmt = $conn->prepare("SELECT user_id FROM employers WHERE employer_id = ?");
    $stmt->bind_param("i", $employer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['user_id'];
}

function createNotification($conn, $user_id, $title, $message, $type) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

include '../includes/header.php';
?>

<style>
/* Responsive Styles */
.apply-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 1rem;
}

.apply-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: clamp(1.5rem, 4vw, 2rem);
    margin: 1rem 0;
}

.apply-title {
    color: #0056b3;
    margin-bottom: 1rem;
    font-size: clamp(1.5rem, 4vw, 2rem);
    font-weight: 700;
    text-align: center;
}

.job-preview {
    background: #f8f9fa;
    padding: clamp(1rem, 3vw, 1.5rem);
    border-radius: 10px;
    margin-bottom: 2rem;
}

.job-preview h3 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: clamp(1.2rem, 3vw, 1.5rem);
}

.job-company {
    color: #666;
    font-size: clamp(1rem, 2.5vw, 1.1rem);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border-left: 4px solid #dc3545;
}

.match-score-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: clamp(1.5rem, 4vw, 2rem);
    border-radius: 15px;
    margin-bottom: 2rem;
    color: white;
    text-align: center;
}

.match-score-title {
    color: white;
    margin-bottom: 0.5rem;
    font-size: clamp(1.2rem, 3vw, 1.5rem);
}

.match-score-value {
    font-size: clamp(2.5rem, 8vw, 3rem);
    font-weight: 700;
    margin: 1rem 0;
}

.match-feedback {
    color: rgba(255,255,255,0.9);
    font-size: clamp(0.95rem, 2.5vw, 1.1rem);
    margin-top: 0.5rem;
    line-height: 1.4;
}

.resume-feedback {
    color: rgba(255,255,255,0.85);
    font-size: clamp(0.85rem, 2.2vw, 0.95rem);
    margin-top: 0.75rem;
    font-style: italic;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.score-breakdown {
    background: rgba(255,255,255,0.1);
    padding: clamp(1rem, 3vw, 1.5rem);
    border-radius: 10px;
    margin-top: 1rem;
    text-align: left;
}

.score-breakdown p {
    color: rgba(255,255,255,0.9);
    font-size: clamp(0.8rem, 2vw, 0.9rem);
    margin: 0;
    line-height: 1.5;
}

.form-group {
    margin-bottom: clamp(1.25rem, 3vw, 1.5rem);
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    color: #333;
    font-weight: 500;
    font-size: clamp(0.95rem, 2.5vw, 1rem);
}

.form-input {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-input:focus {
    outline: none;
    border-color: #0056b3;
}

.form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    resize: vertical;
    min-height: 150px;
    font-family: inherit;
}

.file-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.file-info p {
    color: #666;
    margin: 0;
    font-size: clamp(0.85rem, 2vw, 0.9rem);
    line-height: 1.4;
}

.file-warning {
    color: #dc3545;
    font-size: clamp(0.85rem, 2vw, 0.9rem);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.file-help {
    color: #666;
    display: block;
    margin-top: 0.25rem;
    font-size: clamp(0.8rem, 2vw, 0.85rem);
}

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 2rem;
}

@media (min-width: 480px) {
    .form-actions {
        flex-direction: row;
        justify-content: space-between;
    }
}

.submit-button {
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    flex: 1;
}

.submit-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 86, 179, 0.3);
}

.cancel-button {
    padding: 0.75rem 2rem;
    border: 2px solid #0056b3;
    color: #0056b3;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    text-align: center;
    flex: 1;
}

.cancel-button:hover {
    background: #0056b3;
    color: white;
    transform: translateY(-2px);
}

/* Mobile-specific adjustments */
@media (max-width: 480px) {
    .apply-container {
        padding: 0 0.5rem;
    }
    
    .apply-card {
        padding: 1rem;
        border-radius: 10px;
        margin: 0.5rem 0;
    }
    
    .match-score-section {
        padding: 1.25rem;
        border-radius: 10px;
    }
    
    .form-input,
    .form-textarea {
        padding: 0.65rem;
        font-size: 0.9rem;
    }
    
    .submit-button,
    .cancel-button {
        padding: 0.65rem 1.5rem;
        font-size: 0.9rem;
    }
}

/* Tablet adjustments */
@media (min-width: 768px) {
    .apply-container {
        max-width: 700px;
    }
    
    .form-actions {
        justify-content: flex-start;
    }
    
    .submit-button {
        flex: 2;
    }
    
    .cancel-button {
        flex: 1;
    }
}

/* Desktop enhancements */
@media (min-width: 1024px) {
    .apply-container {
        max-width: 800px;
    }
    
    .apply-card {
        padding: 2.5rem;
    }
}
</style>

<div class="apply-container">
    <div class="apply-card">
        <h1 class="apply-title">
            <i class="fas fa-paper-plane"></i> Apply for Job
        </h1>
        
        <div class="job-preview">
            <h3><?php echo htmlspecialchars($job['title']); ?></h3>
            <p class="job-company">
                <i class="fas fa-building"></i> 
                <?php echo !empty($job['company_name']) ? htmlspecialchars($job['company_name']) : 'Company Not Specified'; ?>
            </p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Match Score Display -->
        <div class="match-score-section">
            <h3 class="match-score-title">
                <i class="fas fa-star"></i> Your Match Score
            </h3>
            <div class="match-score-value">
                <?php echo number_format($final_match_score, 1); ?>%
            </div>
            <p class="match-feedback">
                <?php echo htmlspecialchars($match_feedback); ?>
            </p>
            <?php if (!empty($resume_feedback)): ?>
                <p class="resume-feedback">
                    <i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($resume_feedback); ?>
                </p>
            <?php endif; ?>
            
            <div class="score-breakdown">
                <p>
                    <i class="fas fa-info-circle"></i> <strong>Score Breakdown:</strong> 
                    Base Match Score: <strong><?php echo number_format($match_score, 1); ?>%</strong>
                    <?php if ($resume_quality_score > 0): ?>
                        + Resume Quality: <strong>+<?php echo number_format($resume_quality_score, 1); ?>%</strong>
                    <?php endif; ?>
                    <br><em style="color: rgba(255,255,255,0.8);">Note: Base score matches what will be stored and shown in jobs listing.</em>
                </p>
            </div>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-file-pdf"></i> Resume (PDF, Max 5MB)
                </label>
                <?php if (!empty($applicant['resume_file'])): ?>
                    <div class="file-info">
                        <p>
                            <i class="fas fa-info-circle"></i> Current resume from profile: 
                            <strong><?php echo htmlspecialchars(basename($applicant['resume_file'])); ?></strong>
                        </p>
                        <p style="color: #999; margin-top: 0.25rem;">
                            You can upload a different resume for this specific application, or leave empty to use your profile resume.
                        </p>
                    </div>
                <?php else: ?>
                    <p class="file-warning">
                        <i class="fas fa-exclamation-triangle"></i> No resume in your profile. Please upload a resume for this application.
                    </p>
                <?php endif; ?>
                <input type="file" name="resume" accept=".pdf" class="form-input">
                <small class="file-help">
                    <i class="fas fa-info-circle"></i> Only PDF files are accepted. Maximum file size: 5MB
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-file-alt"></i> Cover Letter (Optional)
                </label>
                <textarea name="cover_letter" class="form-textarea" placeholder="Write your cover letter here..."><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="submit-button">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
                <a href="job_details.php?id=<?php echo $job_id; ?>" class="cancel-button">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>