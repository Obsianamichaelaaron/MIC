<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "Review Applicant Chatbot Assessment";
$applicant_id = $_GET['applicant_id'] ?? 0;
$user_id = getCurrentUserId();

if ($applicant_id <= 0) {
    header('Location: users.php');
    exit();
}

$conn = getDBConnection();

// Get applicant info
$stmt = $conn->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email, a.employability_score 
    FROM applicants a 
    JOIN users u ON a.user_id = u.user_id 
    WHERE a.applicant_id = ?
");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$applicant) {
    header('Location: users.php');
    exit();
}

// Get chatbot answers
$chatbot_answers = [];
$stmt = $conn->prepare("
    SELECT ca.*, q.name as qualification_name 
    FROM chatbot_answers ca 
    LEFT JOIN qualifications q ON ca.qualification_id = q.qualification_id 
    WHERE ca.applicant_id = ? 
    ORDER BY ca.question_number ASC
");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$chatbot_answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all feedback for this applicant
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
    ");
    $stmt_feedback->bind_param("i", $applicant_id);
    $stmt_feedback->execute();
    $feedback_list = $stmt_feedback->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_feedback->close();
}

// Get pending applications count
$stmt_pending = $conn->prepare("
    SELECT COUNT(*) as pending_count 
    FROM applications 
    WHERE applicant_id = ? AND status = 'pending'
");
$stmt_pending->bind_param("i", $applicant_id);
$stmt_pending->execute();
$pending_result = $stmt_pending->get_result()->fetch_assoc();
$pending_count = $pending_result['pending_count'] ?? 0;
$stmt_pending->close();

// Handle employability score update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_score'])) {
    $new_score = floatval($_POST['employability_score']);
    $new_score = max(0, min(100, $new_score)); // Ensure between 0-100
    
    $stmt = $conn->prepare("UPDATE applicants SET employability_score = ? WHERE applicant_id = ?");
    $stmt->bind_param("di", $new_score, $applicant_id);
    if ($stmt->execute()) {
        $success = "Employability score updated successfully!";
        $applicant['employability_score'] = $new_score;
    } else {
        $error = "Failed to update employability score";
    }
    $stmt->close();
}

closeDBConnection($conn);

include '../includes/header.php';
?>

<link rel="stylesheet" href="../css/chatbot.css">

<div class="container">
    <div style="margin-bottom: 2rem;">
        <a href="users.php" style="color: #0056b3; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
    
    <?php if (isset($success)): ?>
        <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <div class="chatbot-container">
        <div class="chatbot-header">
            <div class="bot-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div style="flex: 1;">
                <h1>Admin Assessment Review</h1>
                <div class="status">
                    <span class="status-dot"></span>
                    <span>Reviewing: <?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="question-section active" style="display: block;">
            <div class="chat-messages" id="chatMessages" style="max-height: 500px; overflow-y: auto;">
                <?php if (count($chatbot_answers) > 0): ?>
                    <!-- Welcome Message -->
                    <div class="chat-message bot">
                        <div class="message-avatar bot">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-bubble bot">
                            <p>Hello! 👋 I'm here to help you review this applicant's career assessment.</p>
                            <p style="margin-top: 0.5rem;"><strong>Applicant:</strong> <?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                            <p><strong>Current Employability Score:</strong> <span style="color: #0056b3; font-weight: 600;"><?php echo number_format($applicant['employability_score'], 2); ?>%</span></p>
                            <?php if (!empty($chatbot_answers[0]['qualification_name'])): ?>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($chatbot_answers[0]['qualification_name']); ?></p>
                            <?php endif; ?>
                            <span class="message-time"><?php echo date('h:i A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Display all Q&A -->
                    <?php foreach ($chatbot_answers as $index => $answer): 
                        // Extract category from answer_text if stored
                        $answer_display = $answer['answer_text'];
                        $category = '';
                        if (strpos($answer_display, '|CATEGORY:') !== false) {
                            $parts = explode('|CATEGORY:', $answer_display);
                            $answer_display = $parts[0];
                            $category = isset($parts[1]) ? trim($parts[1]) : '';
                        }
                    ?>
                        <div class="chat-message bot">
                            <div class="message-avatar bot">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="message-bubble bot">
                                <h3>Question <?php echo $answer['question_number'] + 1; ?></h3>
                                <p><?php echo htmlspecialchars($answer['question_text']); ?></p>
                                <?php if (!empty($category)): ?>
                                    <p style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">
                                        <i class="fas fa-tag"></i> Category: <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $category))); ?></strong>
                                    </p>
                                <?php endif; ?>
                                <span class="message-time"><?php echo date('h:i A', strtotime($answer['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="chat-message user">
                            <div class="message-avatar user">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="message-bubble user">
                                <p><?php echo htmlspecialchars($answer_display); ?></p>
                                <p style="margin-top: 0.25rem; font-size: 0.85rem; color: #666;">Value: <?php echo $answer['answer_value']; ?> points</p>
                                <span class="message-time"><?php echo date('h:i A', strtotime($answer['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Summary Message -->
                    <div class="chat-message bot">
                        <div class="message-avatar bot">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-bubble bot">
                            <p><strong>Assessment Summary:</strong></p>
                            <p style="margin-top: 0.5rem;">Total Questions Answered: <?php echo count($chatbot_answers); ?></p>
                            <p>Current Employability Score: <strong style="color: #0056b3;"><?php echo number_format($applicant['employability_score'], 2); ?>%</strong></p>
                            <?php if ($pending_count > 0): ?>
                                <p style="margin-top: 0.5rem; color: #FF9800;">
                                    <i class="fas fa-clock"></i> <strong>Pending Applications:</strong> <?php echo $pending_count; ?>
                                </p>
                            <?php endif; ?>
                            <span class="message-time"><?php echo date('h:i A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Feedback Section -->
                    <?php if (count($feedback_list) > 0): ?>
                        <div class="chat-message bot" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 1.5rem; margin-top: 1rem;">
                            <div class="message-avatar bot" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-comments" style="color: #FFD700;"></i>
                            </div>
                            <div class="message-bubble bot" style="background: transparent; color: white;">
                                <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; color: white;">
                                    <i class="fas fa-eye"></i> Employer Feedback (<?php echo count($feedback_list); ?>)
                                </p>
                                <?php foreach ($feedback_list as $feedback): ?>
                                    <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                        <p style="color: white; font-weight: 600; margin-bottom: 0.5rem;">
                                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($feedback['job_title']); ?>
                                            <?php if ($feedback['company_name']): ?>
                                                <span style="opacity: 0.8;"> - <?php echo htmlspecialchars($feedback['company_name']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <p style="color: rgba(255,255,255,0.95); line-height: 1.6; margin-bottom: 0.5rem;">
                                            <?php echo htmlspecialchars($feedback['feedback_message']); ?>
                                        </p>
                                        <p style="color: rgba(255,255,255,0.8); font-size: 0.85rem; margin-bottom: 0.25rem;">
                                            <i class="fas fa-user"></i> Feedback by: <?php echo htmlspecialchars($feedback['employer_first'] . ' ' . $feedback['employer_last']); ?>
                                        </p>
                                        <p style="color: rgba(255,255,255,0.8); font-size: 0.85rem; margin-bottom: 0.25rem;">
                                            <i class="fas fa-tag"></i> Type: <?php echo ucfirst($feedback['feedback_type']); ?> | 
                                            <i class="fas fa-chart-line"></i> Score: <?php echo number_format($feedback['employability_score'], 2); ?>% | 
                                            <i class="fas fa-info-circle"></i> Status: <?php echo ucfirst($feedback['application_status']); ?>
                                        </p>
                                        <p style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">
                                            <i class="fas fa-clock"></i> <?php echo date('F d, Y h:i A', strtotime($feedback['created_at'])); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                                <span class="message-time" style="color: rgba(255,255,255,0.8);"><?php echo date('h:i A'); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="chat-message bot">
                            <div class="message-avatar bot">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="message-bubble bot">
                                <p><i class="fas fa-info-circle"></i> No employer feedback has been generated yet for this candidate.</p>
                                <p style="margin-top: 0.5rem; color: #666;">Feedback will be automatically generated when employers review this candidate's assessment.</p>
                                <span class="message-time"><?php echo date('h:i A'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="chat-message bot">
                        <div class="message-avatar bot">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-bubble bot">
                            <p>No chatbot assessment data available for this applicant.</p>
                            <p style="margin-top: 0.5rem;">The applicant may not have completed the career assessment yet.</p>
                            <span class="message-time"><?php echo date('h:i A'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Admin Score Update Section -->
            <?php if (count($chatbot_answers) > 0): ?>
                <div style="background: #f8f9fa; padding: 2rem; border-radius: 10px; margin-top: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">
                        <i class="fas fa-edit"></i> Update Employability Score
                    </h3>
                    <form method="POST" action="">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                What is the employability score of this applicant?
                            </label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <button type="button" class="score-choice-btn" onclick="setScore(25)" style="padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; font-weight: 600;">
                                    25%
                                </button>
                                <button type="button" class="score-choice-btn" onclick="setScore(50)" style="padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; font-weight: 600;">
                                    50%
                                </button>
                                <button type="button" class="score-choice-btn" onclick="setScore(75)" style="padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; font-weight: 600;">
                                    75%
                                </button>
                                <button type="button" class="score-choice-btn" onclick="setScore(100)" style="padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; font-weight: 600;">
                                    100%
                                </button>
                            </div>
                            <input type="number" name="employability_score" id="employability_score" 
                                   value="<?php echo number_format($applicant['employability_score'], 2); ?>" 
                                   min="0" max="100" step="0.01" 
                                   style="width: 100%; padding: 0.75rem; border: 2px solid #ddd; border-radius: 8px; font-size: 1.1rem; font-weight: 600; text-align: center;"
                                   required>
                            <small style="color: #666; display: block; margin-top: 0.5rem; text-align: center;">
                                Or enter a custom score (0-100)
                            </small>
                        </div>
                        <button type="submit" name="update_score" style="width: 100%; background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 1rem; border: none; border-radius: 8px; font-weight: 600; font-size: 1.1rem; cursor: pointer;">
                            <i class="fas fa-save"></i> Update Employability Score
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function setScore(score) {
    document.getElementById('employability_score').value = score;
    // Highlight selected button
    document.querySelectorAll('.score-choice-btn').forEach(btn => {
        btn.style.background = 'white';
        btn.style.borderColor = '#ddd';
    });
    event.target.style.background = '#0056b3';
    event.target.style.color = 'white';
    event.target.style.borderColor = '#0056b3';
}
</script>

<?php include '../includes/footer.php'; ?>

