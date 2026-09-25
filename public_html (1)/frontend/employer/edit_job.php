<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Edit Job";
$user_id = getCurrentUserId();

// Get employer ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$stmt->close();

// Get job ID from URL
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify the job belongs to this employer and exists
$stmt = $conn->prepare("SELECT * FROM job_postings WHERE job_id = ? AND employer_id = ?");
$stmt->bind_param("ii", $job_id, $employer_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header('Location: jobs.php');
    exit;
}

// Get active qualifications for targeting
$qualifications = [];
$qual_result = $conn->query("SELECT * FROM qualifications WHERE status = 'active' ORDER BY name ASC");
if ($qual_result) {
    $qualifications = $qual_result->fetch_all(MYSQLI_ASSOC);
}

// Get currently selected qualifications for this job
$selected_qualifications = [];
$stmt = $conn->prepare("SELECT qualification_id FROM job_qualification_mapping WHERE job_id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$qual_result = $stmt->get_result();
while ($row = $qual_result->fetch_assoc()) {
    $selected_qualifications[] = $row['qualification_id'];
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete job
    if (isset($_POST['delete_job'])) {
        // Delete related records first to maintain referential integrity
        $conn->begin_transaction();
        
        try {
            // Delete candidate recommendations
            $stmt = $conn->prepare("DELETE FROM candidate_recommendations WHERE job_id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete job recommendations
            $stmt = $conn->prepare("DELETE FROM job_recommendations WHERE job_id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete qualification mappings
            $stmt = $conn->prepare("DELETE FROM job_qualification_mapping WHERE job_id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete applications
            $stmt = $conn->prepare("DELETE FROM applications WHERE job_id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $stmt->close();
            
            // Finally delete the job
            $stmt = $conn->prepare("DELETE FROM job_postings WHERE job_id = ? AND employer_id = ?");
            $stmt->bind_param("ii", $job_id, $employer_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $_SESSION['success_message'] = "Job deleted successfully!";
            header('Location: jobs.php');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to delete job: " . $e->getMessage();
        }
    }
    // Handle update job
    else if (!isset($_POST['delete_job'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        $skills_required = trim($_POST['skills_required'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $employment_type = $_POST['employment_type'] ?? 'full-time';
        $salary_range = trim($_POST['salary_range'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $target_qualifications = $_POST['target_qualifications'] ?? [];
        
        if (empty($title) || empty($description)) {
            $error = "Please fill in all required fields";
        } else {
            // Update job posting
            $stmt = $conn->prepare("UPDATE job_postings SET title = ?, description = ?, requirements = ?, skills_required = ?, location = ?, employment_type = ?, salary_range = ?, status = ? WHERE job_id = ? AND employer_id = ?");
            $stmt->bind_param("ssssssssii", $title, $description, $requirements, $skills_required, $location, $employment_type, $salary_range, $status, $job_id, $employer_id);
            
            if ($stmt->execute()) {
                // Update qualification mappings
                // First, remove existing mappings
                $delete_stmt = $conn->prepare("DELETE FROM job_qualification_mapping WHERE job_id = ?");
                $delete_stmt->bind_param("i", $job_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // Add new qualification mappings
                if (!empty($target_qualifications) && is_array($target_qualifications)) {
                    foreach ($target_qualifications as $qual_id) {
                        $qual_id = intval($qual_id);
                        if ($qual_id > 0) {
                            $map_stmt = $conn->prepare("INSERT INTO job_qualification_mapping (job_id, qualification_id) VALUES (?, ?)");
                            $map_stmt->bind_param("ii", $job_id, $qual_id);
                            $map_stmt->execute();
                            $map_stmt->close();
                        }
                    }
                }
                
                // Regenerate recommendations based on updated job details
                analyzeJobAndRecommendCandidates($conn, $job_id, $employer_id);
                
                // Set success message and redirect
                $_SESSION['success_message'] = "Job updated successfully!";
                header('Location: jobs.php');
                exit;
                
            } else {
                $error = "Failed to update job";
            }
            
            $stmt->close();
        }
    }
}

$conn->close();
function analyzeJobAndRecommendCandidates($conn, $job_id, $employer_id) {
    // Get job details
    $stmt = $conn->prepare("SELECT * FROM job_postings WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get job's target qualifications
    $job_qualification_ids = [];
    $stmt = $conn->prepare("SELECT qualification_id FROM job_qualification_mapping WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $qual_result = $stmt->get_result();
    while ($row = $qual_result->fetch_assoc()) {
        $job_qualification_ids[] = $row['qualification_id'];
    }
    $stmt->close();
    
    // Get all active applicants who have completed chatbot assessment
    if (!empty($job_qualification_ids)) {
        $placeholders = implode(',', array_fill(0, count($job_qualification_ids), '?'));
        $stmt = $conn->prepare("
            SELECT DISTINCT a.*, u.user_id 
            FROM applicants a 
            JOIN users u ON a.user_id = u.user_id 
            WHERE u.status = 'active'
            AND EXISTS (
                SELECT 1 FROM chatbot_answers ca 
                WHERE ca.applicant_id = a.applicant_id 
                AND ca.qualification_id IN ($placeholders)
            )
        ");
        $types = str_repeat('i', count($job_qualification_ids));
        $stmt->bind_param($types, ...$job_qualification_ids);
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT a.*, u.user_id 
            FROM applicants a 
            JOIN users u ON a.user_id = u.user_id 
            WHERE u.status = 'active'
            AND EXISTS (
                SELECT 1 FROM chatbot_answers ca WHERE ca.applicant_id = a.applicant_id
            )
        ");
    }
    $stmt->execute();
    $applicants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Match applicants to job and create candidate recommendations
    foreach ($applicants as $applicant) {
        $match_score = calculateMatchScore($job, $applicant, $conn);
        $employability_score = floatval($applicant['employability_score'] ?? 0);
        
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
            } else {
                $stmt = $conn->prepare("UPDATE candidate_recommendations SET recommendation_score = ?, reason = ? WHERE employer_id = ? AND job_id = ? AND applicant_id = ?");
                $stmt->bind_param("dsiii", $recommendation_score, $reason, $employer_id, $job_id, $applicant['applicant_id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

function calculateMatchScore($job, $applicant, $conn = null) {
    $score = 0;
    
    $chatbot_answers = [];
    if ($conn && !empty($applicant['applicant_id'])) {
        $stmt_chat = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
        $stmt_chat->bind_param("i", $applicant['applicant_id']);
        $stmt_chat->execute();
        $chatbot_answers = $stmt_chat->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_chat->close();
    }
    
    // Skills matching (0-25 points)
    if (!empty($job['skills_required'])) {
        $required_skills = array_map('trim', explode(',', strtolower($job['skills_required'])));
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
        $stmt_qual->bind_param("i", $job['job_id']);
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
        $category_scores = [
            'experience' => 0,
            'technical' => 0,
            'education' => 0,
            'soft_skills' => 0,
            'certifications' => 0,
            'flexibility' => 0,
            'learning' => 0
        ];
        
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
    
    return min(100, round($score, 2));
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="../css/post_job.css">

<div class="container">
    <div class="card post-job-card"style="margin-top: 10px;">
        <div class="post-job-header">
            <h1>
                <i class="fas fa-edit"></i> Edit Job
            </h1>
            <div style="margin-top: 0.5rem;">
                
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="post-job-alert post-job-alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="post-job-alert post-job-alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="post-job-form" id="jobForm">
            <div class="form-group">
                <label>Job Title *</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($job['title'] ?? ''); ?>" placeholder="e.g., Senior Web Developer">
            </div>
            
            <div class="form-group">
                <label>Job Description *</label>
                <textarea name="description" rows="6" required placeholder="Provide a detailed description of the job position..."><?php echo htmlspecialchars($job['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Requirements</label>
                <textarea name="requirements" rows="4" placeholder="List the requirements for this position..."><?php echo htmlspecialchars($job['requirements'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Required Skills (comma-separated)</label>
                <input type="text" name="skills_required" value="<?php echo htmlspecialchars($job['skills_required'] ?? ''); ?>" placeholder="e.g., PHP, JavaScript, MySQL, React">
            </div>
            
            <div class="post-job-form-grid">
                <div class="form-group">
                    <label>Location *</label>
                    <input type="text" name="location" required value="<?php echo htmlspecialchars($job['location'] ?? ''); ?>" placeholder="e.g., New York, NY">
                </div>
                
                <div class="form-group">
                    <label>Employment Type</label>
                    <select name="employment_type">
                        <option value="full-time" <?php echo ($job['employment_type'] === 'full-time') ? 'selected' : ''; ?>>Full-time</option>
                        <option value="part-time" <?php echo ($job['employment_type'] === 'part-time') ? 'selected' : ''; ?>>Part-time</option>
                        <option value="contract" <?php echo ($job['employment_type'] === 'contract') ? 'selected' : ''; ?>>Contract</option>
                        <option value="internship" <?php echo ($job['employment_type'] === 'internship') ? 'selected' : ''; ?>>Internship</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Salary Range</label>
                <input type="text" name="salary_range" value="<?php echo htmlspecialchars($job['salary_range'] ?? ''); ?>" placeholder="e.g., ₱50,000 - ₱70,000">
            </div>
            
            <div class="form-group">
                <label>Target Qualifications (Select qualifications this job targets)</label>
                <div class="post-job-qualifications">
                    <?php if (count($qualifications) > 0): ?>
                        <?php foreach ($qualifications as $qual): ?>
                            <div class="post-job-qualification-item">
                                <input type="checkbox" name="target_qualifications[]" value="<?php echo $qual['qualification_id']; ?>" 
                                    id="qual_<?php echo $qual['qualification_id']; ?>"
                                    <?php echo in_array($qual['qualification_id'], $selected_qualifications) ? 'checked' : ''; ?>>
                                <span>
                                    <strong><?php echo htmlspecialchars($qual['name']); ?></strong>
                                    <?php if (!empty($qual['description'])): ?>
                                        <small><?php echo htmlspecialchars($qual['description']); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="post-job-empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No qualifications available. Please contact the administrator.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <small class="post-job-info-text">
                    <i class="fas fa-info-circle"></i> Select qualifications that are relevant to this job posting.
                </small>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?php echo ($job['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="draft" <?php echo ($job['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="closed" <?php echo ($job['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                </select>
                <small class="post-job-info-text">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Active:</strong> Visible to applicants | 
                    <strong>Draft:</strong> Only visible to you | 
                    <strong>Closed:</strong> No longer accepting applications
                </small>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                <button type="submit" class="post-job-submit-btn" style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);">
                    <i class="fas fa-save"></i> Update Job
                </button>
                <a href="jobs.php" class="post-job-cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="button" class="post-job-delete-btn" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete Job
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.post-job-cancel-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 2rem;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.post-job-cancel-btn:hover {
    background: #5a6268;
    transform: translateY(-2px);
    text-decoration: none;
    color: white;
}

.post-job-delete-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 2rem;
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    margin-left: auto;
}

.post-job-delete-btn:hover {
    background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .post-job-delete-btn {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this job? This action cannot be undone and will also delete all associated applications and recommendations.')) {
        // Create a separate form for delete
        const deleteForm = document.createElement('form');
        deleteForm.method = 'POST';
        deleteForm.action = '';
        
        // Add delete_job hidden input
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_job';
        deleteInput.value = '1';
        deleteForm.appendChild(deleteInput);
        
        // Add to document and submit
        document.body.appendChild(deleteForm);
        deleteForm.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>