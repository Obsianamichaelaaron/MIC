<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Review Applicant Chatbot Assessment";
$application_id = $_GET['application_id'] ?? 0;
$user_id = getCurrentUserId();

// Get employer ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$stmt->close();

// Get application and applicant details
$stmt = $conn->prepare("
    SELECT 
        a.*,
        ap.*,
        u.first_name,
        u.last_name,
        u.email,
        u.user_id,
        jp.title as job_title,
        jp.job_id
    FROM applications a
    JOIN applicants ap ON a.applicant_id = ap.applicant_id
    JOIN users u ON ap.user_id = u.user_id
    JOIN job_postings jp ON a.job_id = jp.job_id
    WHERE a.application_id = ?
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application) {
    header('Location: candidates.php');
    exit();
}

// Get chatbot answers
$chatbot_answers = [];
$stmt_chatbot = $conn->prepare("
    SELECT ca.*, q.name as qualification_name 
    FROM chatbot_answers ca 
    LEFT JOIN qualifications q ON ca.qualification_id = q.qualification_id 
    WHERE ca.applicant_id = ? 
    ORDER BY ca.question_number ASC
");
$stmt_chatbot->bind_param("i", $application['applicant_id']);
$stmt_chatbot->execute();
$chatbot_answers = $stmt_chatbot->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_chatbot->close();

// Generate automatic feedback based on employability score
function generateFeedback($employability_score, $applicant_name, $job_title, $status) {
    $score = floatval($employability_score);
    $feedback = "";
    
    if ($score >= 80) {
        $feedback = "🎉 Congratulations! " . htmlspecialchars($applicant_name) . " has achieved an excellent employability score of " . number_format($score, 2) . "%. ";
        $feedback .= "This candidate demonstrates exceptional qualifications and is highly recommended for the position of " . htmlspecialchars($job_title) . ". ";
        $feedback .= "Their strong performance across all assessment categories indicates they are well-prepared and would be a valuable addition to your team. ";
        if ($status === 'pending') {
            $feedback .= "We recommend moving this application forward to the next stage of the hiring process.";
        }
    } elseif ($score >= 70) {
        $feedback = "✅ Great news! " . htmlspecialchars($applicant_name) . " has achieved a strong employability score of " . number_format($score, 2) . "%. ";
        $feedback .= "This candidate shows good alignment with the requirements for " . htmlspecialchars($job_title) . ". ";
        $feedback .= "They demonstrate solid skills and experience that would make them a competitive candidate. ";
        if ($status === 'pending') {
            $feedback .= "Consider reviewing their application in detail and potentially shortlisting them for further consideration.";
        }
    } elseif ($score >= 60) {
        $feedback = "👍 " . htmlspecialchars($applicant_name) . " has achieved a satisfactory employability score of " . number_format($score, 2) . "%. ";
        $feedback .= "This candidate shows moderate alignment with the position of " . htmlspecialchars($job_title) . ". ";
        $feedback .= "While they have some relevant qualifications, there may be areas for growth. ";
        if ($status === 'pending') {
            $feedback .= "A detailed review of their application and potential interview would help assess their fit more accurately.";
        }
    } elseif ($score >= 50) {
        $feedback = "📋 " . htmlspecialchars($applicant_name) . " has achieved an employability score of " . number_format($score, 2) . "%. ";
        $feedback .= "This candidate shows basic qualifications for " . htmlspecialchars($job_title) . ". ";
        $feedback .= "While they may need additional training or experience, they could be a potential candidate with the right support. ";
        if ($status === 'pending') {
            $feedback .= "Consider reviewing their application carefully to identify specific strengths and areas for development.";
        }
    } else {
        $feedback = "📝 " . htmlspecialchars($applicant_name) . " has an employability score of " . number_format($score, 2) . "%. ";
        $feedback .= "This candidate may need significant development to meet the requirements for " . htmlspecialchars($job_title) . ". ";
        $feedback .= "However, they have shown initiative by completing the assessment. ";
        if ($status === 'pending') {
            $feedback .= "A thorough review of their application and potential mentoring opportunities could be beneficial.";
        }
    }
    
    return $feedback;
}

// Get or create feedback
$feedback_message = "";
$existing_feedback = null;

// Check if feedback table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt_feedback = $conn->prepare("
        SELECT * FROM candidate_feedback 
        WHERE application_id = ? AND employer_id = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt_feedback->bind_param("ii", $application_id, $employer_id);
    $stmt_feedback->execute();
    $existing_feedback = $stmt_feedback->get_result()->fetch_assoc();
    $stmt_feedback->close();
}

if (!$existing_feedback && count($chatbot_answers) > 0) {
    // Generate and save automatic feedback
    $feedback_message = generateFeedback(
        $application['employability_score'],
        $application['first_name'] . ' ' . $application['last_name'],
        $application['job_title'],
        $application['status']
    );
    
    // Save feedback to database (only if table exists)
    $table_check = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt_insert = $conn->prepare("
            INSERT INTO candidate_feedback 
            (application_id, applicant_id, employer_id, employability_score, feedback_message, feedback_type) 
            VALUES (?, ?, ?, ?, ?, 'automatic')
        ");
        $stmt_insert->bind_param("iiids", 
            $application_id,
            $application['applicant_id'],
            $employer_id,
            $application['employability_score'],
            $feedback_message
        );
        if ($stmt_insert->execute()) {
            // Create notification for applicant
            $applicant_user_id = $application['user_id'] ?? null;
            if ($applicant_user_id) {
                // Get employer name
                $stmt_emp = $conn->prepare("SELECT u.first_name, u.last_name FROM users u JOIN employers e ON u.user_id = e.user_id WHERE e.employer_id = ?");
                $stmt_emp->bind_param("i", $employer_id);
                $stmt_emp->execute();
                $emp_user = $stmt_emp->get_result()->fetch_assoc();
                $stmt_emp->close();
                
                $employer_name = ($emp_user ? trim($emp_user['first_name'] . ' ' . $emp_user['last_name']) : 'An employer');
                
                // Create notification
                $notification_title = "New Feedback on Your Application";
                $notification_message = $employer_name . " has provided feedback on your application for " . htmlspecialchars($application['job_title']) . ". Check your dashboard to view it!";
                
                $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'application')");
                $stmt_notif->bind_param("iss", $applicant_user_id, $notification_title, $notification_message);
                $stmt_notif->execute();
                $stmt_notif->close();
            }
        }
        $stmt_insert->close();
    }
} elseif ($existing_feedback) {
    $feedback_message = $existing_feedback['feedback_message'];
}

// Get pending applications count for this candidate
$stmt_pending = $conn->prepare("
    SELECT COUNT(*) as pending_count 
    FROM applications 
    WHERE applicant_id = ? AND status = 'pending'
");
$stmt_pending->bind_param("i", $application['applicant_id']);
$stmt_pending->execute();
$pending_result = $stmt_pending->get_result()->fetch_assoc();
$pending_count = $pending_result['pending_count'] ?? 0;
$stmt_pending->close();

$conn->close();

include '../includes/header.php';
?>

<link rel="stylesheet" href="../css/chatbot.css">

<div class="container">
    <div style="margin-bottom: 2rem;">
        <a href="view_candidate.php?id=<?php echo $application_id; ?>" style="color: #0056b3; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Candidate Details
        </a>
    </div>
    
    <div class="chatbot-container">
        <div class="chatbot-header">
            <div class="bot-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div style="flex: 1;">
                <h1>Applicant Assessment Review</h1>
                <div class="status">
                    <span class="status-dot"></span>
                    <span>Reviewing: <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="question-section active" style="display: block;">
            <div class="chat-messages" id="chatMessages" style="max-height: 600px; overflow-y: auto;">
                <?php if (count($chatbot_answers) > 0): ?>
                    <!-- Welcome Message -->
                    <div class="chat-message bot">
                        <div class="message-avatar bot">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-bubble bot">
                            <p>Hello! 👋 I'm here to help you review this applicant's career assessment.</p>
                            <p style="margin-top: 0.5rem;"><strong>Applicant:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                            <p><strong>Job Applied:</strong> <?php echo htmlspecialchars($application['job_title']); ?></p>
                            <p><strong>Employability Score:</strong> <span style="color: #0056b3; font-weight: 600;"><?php echo number_format($application['employability_score'], 2); ?>%</span></p>
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
                            <p>Final Employability Score: <strong style="color: #0056b3;"><?php echo number_format($application['employability_score'], 2); ?>%</strong></p>
                            <p style="margin-top: 0.5rem; color: #666;">This score is calculated based on the applicant's answers and remains recorded in the system.</p>
                            <span class="message-time"><?php echo date('h:i A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Automatic Feedback Message -->
                    <?php if (!empty($feedback_message)): ?>
                    <div class="chat-message bot" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 1.5rem; margin-top: 1rem;">
                        <div class="message-avatar bot" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-star" style="color: #FFD700;"></i>
                        </div>
                        <div class="message-bubble bot" style="background: transparent; color: white;">
                            <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; color: white;">
                                <i class="fas fa-lightbulb"></i> AI-Generated Feedback
                            </p>
                            <p style="color: white; line-height: 1.8; margin-bottom: 1rem;">
                                <?php echo $feedback_message; ?>
                            </p>
                            <?php if ($pending_count > 0): ?>
                                <p style="color: rgba(255,255,255,0.9); margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.3);">
                                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> This candidate has <?php echo $pending_count; ?> other pending application<?php echo $pending_count > 1 ? 's' : ''; ?> in the system.
                                </p>
                            <?php endif; ?>
                            <span class="message-time" style="color: rgba(255,255,255,0.8);"><?php echo date('h:i A'); ?></span>
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
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

