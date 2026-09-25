<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

$pageTitle = "Career Assessment Chatbot";
$user_id = getCurrentUserId();

$flask_chatbot_score = 'https://capstone-api-n66l.onrender.com/chatbot_score';
$flask_upload = 'https://capstone-api-n66l.onrender.com/upload_resume';
$flask_jobs = 'https://capstone-api-n66l.onrender.com/jobs';
$flask_resume_matches_base = 'https://capstone-api-n66l.onrender.com/resume_matches/';

$conn = getDBConnection();

// Get applicant ID
$stmt = $conn->prepare("SELECT applicant_id, employability_score FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$applicant_id = $applicant['applicant_id'];
$current_score = $applicant['employability_score'] ?? 0;
$stmt->close();

// Get qualifications
$stmt = $conn->prepare("SELECT qualification_id, name, description FROM qualifications WHERE status = 'active' ORDER BY name");
$stmt->execute();
$qualifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if user has existing answers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chatbot_answers WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$has_answers = $stmt->get_result()->fetch_assoc()['count'] > 0;
$stmt->close();

// Get existing answers if any
$existing_answers = [];
if ($has_answers) {
    $stmt = $conn->prepare("SELECT * FROM chatbot_answers WHERE applicant_id = ? ORDER BY question_number");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $existing_answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Career Bridge</title>
    <link rel="stylesheet" href="../css/chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        .container {
            padding: 10px;
        }
        
        .chatbot-container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: calc(100vh - 20px);
            display: flex;
            flex-direction: column;
        }

        .chatbot-header {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .bot-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .chatbot-header h1 {
            margin: 0;
            font-size: 1.25rem;
            line-height: 1.2;
        }

        .status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* Qualification Selection */
        .qualification-selection {
            padding: 1rem;
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .qualification-selection h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            flex-shrink: 0;
        }

        .qualification-selection p {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .qualification-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
            min-height: 0;
        }

        .qualification-grid::-webkit-scrollbar {
            width: 4px;
        }

        .qualification-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        .qualification-grid::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }

        .qualification-grid::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .qualification-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .qualification-card:hover {
            border-color: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .qualification-card.selected {
            border-color: #0056b3;
            background: #f0f7ff;
        }

        .qualification-card h3 {
            margin: 0 0 0.25rem 0;
            color: #333;
            font-size: 1rem;
        }

        .qualification-card p {
            margin: 0;
            color: #666;
            font-size: 0.85rem;
        }

        /* Question Section */
        .question-section {
            display: none;
            flex: 1;
            flex-direction: column;
            overflow: hidden;
        }

        .question-section.active {
            display: flex;
        }

        .progress-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin: 0 1rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .chat-messages-container {
            position: relative;
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-height: 100%;
        }

        .chat-message {
            display: flex;
            gap: 0.5rem;
            max-width: 90%;
        }

        .chat-message.bot {
            align-self: flex-start;
        }

        .chat-message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .message-avatar.bot {
            background: #0056b3;
            color: white;
        }

        .message-avatar.user {
            background: #4CAF50;
            color: white;
        }

        .message-bubble {
            padding: 0.75rem 1rem;
            border-radius: 14px;
            position: relative;
            max-width: 100%;
            word-wrap: break-word;
            font-size: 0.9rem;
        }

        .message-bubble.bot {
            background: #f0f7ff;
            border-bottom-left-radius: 4px;
            color: #333;
        }

        .message-bubble.user {
            background: #0056b3;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-bubble h3 {
            margin: 0 0 0.25rem 0;
            font-size: 0.85rem;
            color: #666;
        }

        .message-bubble.bot h3 {
            color: #0056b3;
        }

        .message-bubble.user h3 {
            color: rgba(255, 255, 255, 0.9);
        }

        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
            display: block;
        }

        .answer-options {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .answer-option {
            padding: 0.6rem 0.8rem;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .answer-option:hover {
            border-color: #0056b3;
            background: #f0f7ff;
        }

        .answer-option.selected {
            border-color: #0056b3;
            background: #0056b3;
            color: white;
        }

        .typing-indicator-wrapper {
            align-items: center;
        }

        .typing-indicator {
            display: flex;
            gap: 3px;
            padding: 0.75rem 1rem;
            background: #f0f7ff;
            border-radius: 14px;
            border-bottom-left-radius: 4px;
        }

        .typing-indicator span {
            width: 6px;
            height: 6px;
            background: #0056b3;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        /* Chat Input Area */
        .chat-input-area {
            padding: 1rem;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-shrink: 0;
        }

        .chat-btn {
            padding: 0.6rem 1.2rem;
            background: #0056b3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .chat-btn:hover {
            background: #004494;
            transform: translateY(-1px);
        }

        .chat-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .chat-btn-secondary {
            background: #6c757d;
        }

        .chat-btn-secondary:hover {
            background: #5a6268;
        }

        /* Results Section */
        .results-section {
            display: none;
            padding: 1rem;
            text-align: center;
            flex: 1;
            overflow: hidden;
        }

        .results-section.active {
            display: flex;
            flex-direction: column;
        }

        .score-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4CAF50;
            margin: 0.5rem 0;
        }

        .answers-summary {
            margin-top: 1.5rem;
            text-align: left;
        }

        /* Scrollable Containers */
        .scrollable-container {
            max-height: 300px;
            overflow-y: auto;
            padding: 0.75rem;
        }

        .scrollable-answers-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            background: #f8f9fa;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }

        .answer-item {
            background: white;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 6px;
            border-left: 3px solid #0056b3;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .answer-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        .answer-item:last-child {
            margin-bottom: 0;
        }

        .question-number {
            color: #0056b3;
            font-weight: 600;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        .question-text {
            color: #333;
            font-weight: 500;
            margin-bottom: 0.5rem;
            line-height: 1.3;
            font-size: 0.85rem;
        }

        .answer-text {
            color: #4CAF50;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            border-left: 2px solid #4CAF50;
            font-weight: 500;
            font-size: 0.8rem;
        }

        /* API Status */
        .api-status {
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
        }

        .api-status.connected {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .api-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Scroll to Bottom Button */
        .scroll-to-bottom {
            position: absolute;
            bottom: 60px;
            right: 15px;
            background: #0056b3;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 1px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            font-size: 0.8rem;
        }

        .scroll-to-bottom:hover {
            background: #004494;
            transform: scale(1.05);
        }

        .scroll-to-bottom.hidden {
            opacity: 0;
            visibility: hidden;
            transform: scale(0.8);
        }

        /* Button Group Responsive */
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Retake Confirmation */
        .retake-confirmation {
            display: none;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            border: 2px solid #dc3545;
        }

        .retake-confirmation h3 {
            color: #dc3545;
            margin-top: 0;
            font-size: 1.1rem;
        }

        .retake-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 0.75rem;
            margin: 0.75rem 0;
            color: #856404;
            font-size: 0.85rem;
        }

        .retake-warning h4 {
            margin-top: 0;
            color: #856404;
            font-size: 0.9rem;
        }

        .retake-warning ul {
            margin-bottom: 0;
            padding-left: 1.25rem;
            font-size: 0.8rem;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .container {
                padding: 5px;
            }
            
            .chatbot-container {
                height: calc(100vh - 10px);
                border-radius: 6px;
            }
            
            .chatbot-header {
                padding: 0.75rem;
            }
            
            .bot-avatar {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .chatbot-header h1 {
                font-size: 1.1rem;
            }
            
            .qualification-selection,
            .question-section,
            .results-section {
                padding: 0.75rem;
            }
            
            .chat-messages {
                padding: 0.75rem;
                gap: 0.5rem;
            }
            
            .chat-input-area {
                padding: 0.75rem;
                gap: 0.5rem;
            }
            
            .chat-btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .message-bubble {
                padding: 0.6rem 0.8rem;
                font-size: 0.85rem;
            }
            
            .answer-option {
                padding: 0.5rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .scroll-to-bottom {
                bottom: 50px;
                right: 10px;
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="chatbot-container">
            <div class="chatbot-header">
                <div class="bot-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div style="flex: 1;">
                    <h1>Career Assessment Bot</h1>
                    <div class="status">
                        <span class="status-dot"></span>
                        <span>Online</span>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 0.75rem; border-radius: 6px; margin: 0.75rem; border-left: 3px solid #dc3545; font-size: 0.85rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div id="apiStatus" class="api-status" style="display: none; margin: 0.75rem;">
                <i class="fas fa-circle-notch fa-spin"></i> Checking API connection...
            </div>

            <?php if ($has_answers && $current_score > 0): ?>
                <!-- User has completed assessment - Show Results -->
                <div class="results-section active">
                    <div style="text-align: center; padding: 1rem;">
                        <div style="font-size: 2.5rem; color: #4CAF50; margin-bottom: 0.5rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Assessment Completed</h2>
                        <p style="font-size: 1rem; margin-bottom: 0.5rem;">Your employability score: <span class="score-display" style="font-size: 1.25rem;"><?php echo number_format($current_score, 2); ?>%</span></p>
                        
                        <div class="btn-group" id="mainButtons">
                            <button id="retakeBtn" class="btn btn-danger">
                                <i class="fas fa-redo"></i> Retake Assessment
                           
                        </div>

                        <div id="retakeConfirmation" class="retake-confirmation">
                            <h3><i class="fas fa-exclamation-circle"></i> Confirm Retake</h3>
                            <p style="font-size: 0.9rem; margin-bottom: 1rem;">Are you sure you want to retake the assessment? This will replace your current score and answers.</p>
                            
                            <div class="retake-warning">
                                <h4><i class="fas fa-exclamation-triangle"></i> Please Note:</h4>
                                <ul>
                                    <li>Your current score will be replaced</li>
                                    <li>Your previous answers will be deleted</li>
                                    <li>This action cannot be undone</li>
                                </ul>
                            </div>
                            
                            <div class="btn-group">
                                <button id="confirmRetakeBtn" class="btn btn-danger">
                                    <i class="fas fa-check"></i> Yes, Retake Assessment
                                </button>
                                <button id="cancelRetakeBtn" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;">
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.75rem;">Your Previous Answers</h3>
                        <div class="scrollable-answers-container">
                            <?php foreach ($existing_answers as $answer): ?>
                                <div class="answer-item">
                                    <div class="question-number">Question <?php echo $answer['question_number']; ?></div>
                                    <div class="question-text"><?php echo htmlspecialchars($answer['question_text']); ?></div>
                                    <div class="answer-text"><?php echo htmlspecialchars($answer['answer_text']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- User hasn't completed assessment - Show Assessment Interface -->
                <div class="qualification-selection" id="qualificationSelection">
                    <h2>What category have you chosen?</h2>
                    <p>Please select your qualification category:</p>
                    
                    <div class="qualification-grid">
                        <?php foreach ($qualifications as $qual): ?>
                            <div class="qualification-card" data-qualification-id="<?php echo $qual['qualification_id']; ?>">
                                <h3><?php echo htmlspecialchars($qual['name']); ?></h3>
                                <p><?php echo htmlspecialchars($qual['description'] ?? 'No description available'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-section" id="questionsSection">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                    </div>
                    
                    <div class="chat-messages-container">
                        <div class="chat-messages" id="chatMessages">
                        </div>
                        
                        <button class="scroll-to-bottom hidden" id="scrollToBottomBtn" title="Scroll to bottom">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    
                    <div class="chat-input-area" id="chatInputArea" style="display: none;">
                        <button class="chat-btn chat-btn-secondary" id="prevBtn" onclick="previousQuestion()" style="display: none;">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <div style="flex: 1;"></div>
                        <button class="chat-btn" id="nextBtn" onclick="nextQuestion()" style="display: none;">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button class="chat-btn" id="submitBtn" onclick="submitAnswers()" style="display: none;">
                            <i class="fas fa-paper-plane"></i> Submit
                        </button>
                    </div>
                </div>

                <div class="results-section" id="resultsSection">
                    <div class="scrollable-container">
                        <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Assessment Complete!</h2>
                        <p style="font-size: 0.9rem;">Your employability score:</p>
                        <div class="score-display" id="finalScore">0%</div>
                        <div class="score-breakdown" id="scoreBreakdown" style="display: none;">
                            <h4 style="font-size: 1.1rem;">Score Breakdown</h4>
                            <div id="breakdownContent"></div>
                        </div>
                        <p style="color: #666; margin-top: 0.5rem; font-size: 0.85rem;">Based on your answers, we've calculated your employability score.</p>
                        <div class="answers-summary" id="answersSummary"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($has_answers && $current_score > 0): ?>
    <!-- JavaScript for Retake Functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const retakeBtn = document.getElementById('retakeBtn');
        const confirmRetakeBtn = document.getElementById('confirmRetakeBtn');
        const cancelRetakeBtn = document.getElementById('cancelRetakeBtn');
        const retakeConfirmation = document.getElementById('retakeConfirmation');
        const mainButtons = document.getElementById('mainButtons');

        retakeBtn.addEventListener('click', function() {
            retakeConfirmation.style.display = 'block';
            mainButtons.style.display = 'none';
        });

        cancelRetakeBtn.addEventListener('click', function() {
            retakeConfirmation.style.display = 'none';
            mainButtons.style.display = 'flex';
        });

        confirmRetakeBtn.addEventListener('click', function() {
            confirmRetakeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmRetakeBtn.disabled = true;

            fetch('chatbot_handler.php?action=reset_assessment', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const successMsg = document.createElement('div');
                    successMsg.style.background = '#d4edda';
                    successMsg.style.color = '#155724';
                    successMsg.style.padding = '0.75rem';
                    successMsg.style.borderRadius = '6px';
                    successMsg.style.marginBottom = '0.75rem';
                    successMsg.style.borderLeft = '3px solid #c3e6cb';
                    successMsg.innerHTML = '<i class="fas fa-check-circle"></i> Assessment reset successfully! Redirecting...';
                    
                    document.querySelector('.results-section').insertBefore(successMsg, document.querySelector('.results-section').firstChild);
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert('Error resetting assessment: ' + (data.message || 'Unknown error'));
                    confirmRetakeBtn.innerHTML = '<i class="fas fa-check"></i> Yes, Retake Assessment';
                    confirmRetakeBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error resetting assessment. Please try again.');
                confirmRetakeBtn.innerHTML = '<i class="fas fa-check"></i> Yes, Retake Assessment';
                confirmRetakeBtn.disabled = false;
            });
        });
    });
    </script>
    <?php else: ?>
    <!-- JavaScript for Assessment Functionality -->
    <script>
    let selectedQualificationId = null;
    let currentQuestionIndex = 0;
    let answers = {};
    let questions = [];

    const FLASK_API_URL = 'https://capstone-api-n66l.onrender.com/chatbot_score';

    document.addEventListener('DOMContentLoaded', function() {
        checkAPIConnection();
        initializeScrollHandler();
        initializeQualificationSelection();
    });

    function initializeScrollHandler() {
        const chatMessages = document.getElementById('chatMessages');
        const scrollBtn = document.getElementById('scrollToBottomBtn');

        chatMessages.addEventListener('scroll', function() {
            const isAtBottom = chatMessages.scrollHeight - chatMessages.clientHeight <= chatMessages.scrollTop + 50;
            
            if (isAtBottom) {
                scrollBtn.classList.add('hidden');
            } else {
                scrollBtn.classList.remove('hidden');
            }
        });

        scrollBtn.addEventListener('click', function() {
            scrollToBottom();
        });
    }

    function initializeQualificationSelection() {
        document.querySelectorAll('.qualification-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.qualification-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedQualificationId = this.dataset.qualificationId;
                
                setTimeout(() => {
                    startQuestions();
                }, 500);
            });
        });
    }

    function checkAPIConnection() {
        const apiStatus = document.getElementById('apiStatus');
        apiStatus.style.display = 'block';
        
        fetch(FLASK_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({answers: []})
        })
        .then(response => {
            if (response.ok) {
                apiStatus.className = 'api-status connected';
                apiStatus.innerHTML = '<i class="fas fa-check-circle"></i> AI Assessment API Connected';
            } else {
                throw new Error('API not responding');
            }
        })
        .catch(error => {
            apiStatus.className = 'api-status error';
            apiStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> AI Assessment API Not Available - Using Fallback Scoring';
        });
    }

    const qualificationQuestions = {
        default: [
            { 
                q: "Which best describes your work background?", 
                type: "choice", 
                options: ["No work experience/ fresh graduate", "Entry-level employee", "Experienced employee", "Supervisor ", "Manager/Executive"], 
                values: [10, 15, 25, 35, 45], 
                category: "education" 
            },
            { 
                q: "How many years of professional experience do you have in your field?", 
                type: "choice", 
                options: ["0-1 years", "2-3 years", "4-5 years", "6+ years"], 
                values: [10, 20, 30, 40], 
                category: "experience" 
            },
            { 
                q: "How would you rate your relevant skills for your field?", 
                type: "choice", 
                options: ["Beginner", "Intermediate", "Advanced", "Expert"], 
                values: [10, 20, 30, 40], 
                category: "technical" 
            },
            { 
                q: "Do you have any professional certifications or licenses?", 
                type: "choice", 
                options: ["No", "1-2 certifications", "3-4 certifications", "5+ certifications"], 
                values: [0, 15, 25, 35], 
                category: "certifications" 
            },
            { 
                q: "How comfortable are you with working in a team environment?", 
                type: "choice", 
                options: ["Not comfortable", "Somewhat comfortable", "Comfortable", "Very comfortable"], 
                values: [5, 15, 25, 35], 
                category: "soft_skills" 
            },
            { 
                q: "How would you rate your communication skills?", 
                type: "choice", 
                options: ["Poor", "Fair", "Good", "Excellent"], 
                values: [5, 15, 25, 35], 
                category: "soft_skills" 
            },
            { 
                q: "Are you willing to relocate for a job opportunity?", 
                type: "choice", 
                options: ["No", "Maybe", "Yes"], 
                values: [0, 10, 20], 
                category: "flexibility" 
            },
            { 
                q: "How many relevant tools or software are you proficient in for your field?", 
                type: "choice", 
                options: ["None", "1-2", "3-4", "5+"], 
                values: [0, 10, 20, 30], 
                category: "technical" 
            },
            { 
                q: "How would you rate your ability to adapt to new processes and systems?", 
                type: "choice", 
                options: ["Poor", "Fair", "Good", "Excellent"], 
                values: [5, 15, 25, 35], 
                category: "learning" 
            },
            { 
                q: "How would you rate your problem-solving abilities?", 
                type: "choice", 
                options: ["Poor", "Fair", "Good", "Excellent"], 
                values: [5, 15, 25, 35], 
                category: "soft_skills" 
            },
            { 
                q: "Have you worked on projects or tasks with tight deadlines?", 
                type: "choice", 
                options: ["Never", "Rarely", "Sometimes", "Frequently"], 
                values: [0, 10, 20, 30], 
                category: "experience" 
            },
            { 
                q: "How would you rate your ability to learn new skills quickly?", 
                type: "choice", 
                options: ["Slow", "Average", "Fast", "Very Fast"], 
                values: [5, 15, 25, 35], 
                category: "learning" 
            }
        ]
    };

    function startQuestions() {
        document.getElementById('qualificationSelection').style.display = 'none';
        document.getElementById('questionsSection').classList.add('active');
        
        questions = qualificationQuestions.default;
        
        const chatMessages = document.getElementById('chatMessages');
        const welcomeHTML = `
            <div class="chat-message bot">
                <div class="message-avatar bot">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-bubble bot">
                    <p>Hello! 👋 I'm here to help you assess your career readiness. Let's start with a few questions to understand your background better.</p>
                    <span class="message-time">${getCurrentTime()}</span>
                </div>
            </div>
        `;
        chatMessages.innerHTML = welcomeHTML;
        scrollToBottom();
        
        setTimeout(() => {
            loadQuestion(0);
        }, 1500);
    }

    function loadQuestion(index) {
        if (index < 0 || index >= questions.length) return;
        
        currentQuestionIndex = index;
        const question = questions[index];
        const chatMessages = document.getElementById('chatMessages');
        
        // Remove any existing typing indicator
        const typingIndicator = chatMessages.querySelector('.typing-indicator-wrapper');
        if (typingIndicator) {
            typingIndicator.remove();
        }
        
        showTypingIndicator();
        
        setTimeout(() => {
            const typing = chatMessages.querySelector('.typing-indicator-wrapper');
            if (typing) typing.remove();
            
            const questionHTML = `
                <div class="chat-message bot">
                    <div class="message-avatar bot">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-bubble bot">
                        <h3>Question ${index + 1} of ${questions.length}</h3>
                        <p>${question.q}</p>
                        <span class="message-time">${getCurrentTime()}</span>
                        <div class="answer-options">
                            ${question.options.map((option, optIndex) => `
                                <div class="answer-option" 
                                     data-value="${question.values[optIndex]}" 
                                     data-answer="${option.replace(/'/g, "\\'")}" 
                                     onclick="selectAnswer(${index}, ${optIndex}, '${option.replace(/'/g, "\\'")}', ${question.values[optIndex]}, '${question.category}')">
                                    ${option}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            
            chatMessages.insertAdjacentHTML('beforeend', questionHTML);
            scrollToBottom();
            
            // If user already answered this question, show their selection
            if (answers[index]) {
                const selectedOption = chatMessages.querySelector(`[data-answer="${answers[index].answer.replace(/'/g, "\\'")}"]`);
                if (selectedOption) {
                    selectedOption.classList.add('selected');
                    showUserAnswer(answers[index].answer);
                }
            }
            
            document.getElementById('chatInputArea').style.display = 'flex';
            
            // Update progress and navigation buttons
            updateNavigation();
            
        }, 1000);
    }

    function updateNavigation() {
        const progress = ((currentQuestionIndex + 1) / questions.length) * 100;
        document.getElementById('progressFill').style.width = progress + '%';
        
        document.getElementById('prevBtn').style.display = currentQuestionIndex > 0 ? 'inline-flex' : 'none';
        document.getElementById('nextBtn').style.display = currentQuestionIndex < questions.length - 1 ? 'inline-flex' : 'none';
        document.getElementById('submitBtn').style.display = currentQuestionIndex === questions.length - 1 ? 'inline-flex' : 'none';
    }

    function showTypingIndicator() {
        const chatMessages = document.getElementById('chatMessages');
        const typingHTML = `
            <div class="chat-message bot typing-indicator-wrapper">
                <div class="message-avatar bot">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        chatMessages.insertAdjacentHTML('beforeend', typingHTML);
        scrollToBottom();
    }

    function showUserAnswer(answerText) {
        const chatMessages = document.getElementById('chatMessages');
        const existingAnswer = Array.from(chatMessages.querySelectorAll('.chat-message.user')).find(msg => {
            return msg.querySelector('.message-bubble p').textContent.trim() === answerText.trim();
        });
        
        if (!existingAnswer) {
            const answerHTML = `
                <div class="chat-message user">
                    <div class="message-avatar user">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="message-bubble user">
                        <p>${answerText}</p>
                        <span class="message-time">${getCurrentTime()}</span>
                    </div>
                </div>
            `;
            chatMessages.insertAdjacentHTML('beforeend', answerHTML);
            scrollToBottom();
        }
    }

    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        setTimeout(() => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
            document.getElementById('scrollToBottomBtn').classList.add('hidden');
        }, 100);
    }

    function selectAnswer(questionIndex, optionIndex, answerText, value, category) {
        const question = questions[questionIndex];
        const maxValue = question && Array.isArray(question.values) && question.values.length > 0 
            ? Math.max(...question.values) 
            : 40;

        const chatMessages = document.getElementById('chatMessages');
        const currentQuestionBubble = chatMessages.querySelectorAll('.chat-message.bot');
        if (currentQuestionBubble.length > 0) {
            const lastQuestion = currentQuestionBubble[currentQuestionBubble.length - 1];
            lastQuestion.querySelectorAll('.answer-option').forEach(opt => opt.classList.remove('selected'));
        }
        
        event.target.classList.add('selected');
        
        answers[questionIndex] = {
            question: question.q,
            answer: answerText,
            value: value,
            category: category,
            max_value: maxValue,
            option_index: optionIndex
        };
        
        showUserAnswer(answerText);
    }

    function previousQuestion() {
        if (currentQuestionIndex > 0) {
            loadQuestion(currentQuestionIndex - 1);
        }
    }

    function nextQuestion() {
        if (answers[currentQuestionIndex]) {
            if (currentQuestionIndex < questions.length - 1) {
                loadQuestion(currentQuestionIndex + 1);
            }
        } else {
            alert('Please select an answer before proceeding.');
        }
    }

    function submitAnswers() {
        if (Object.keys(answers).length !== questions.length) {
            alert('Please answer all questions before submitting.');
            return;
        }
        
        if (!selectedQualificationId) {
            alert('Please select a qualification category first.');
            return;
        }
        
        calculateEmployabilityScore();
    }

    function calculateEmployabilityScore() {
        const chatMessages = document.getElementById('chatMessages');
        
        const calculatingHTML = `
            <div class="chat-message bot">
                <div class="message-avatar bot">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-bubble bot">
                    <p>🔍 Analyzing your responses with AI...</p>
                    <p>Calculating your employability score using machine learning...</p>
                    <span class="message-time">${getCurrentTime()}</span>
                </div>
            </div>
        `;
        chatMessages.insertAdjacentHTML('beforeend', calculatingHTML);
        scrollToBottom();
        
        const apiData = {
            answers: Object.values(answers).map(answer => ({
                question: answer.question,
                option_index: answer.option_index,
                option_text: answer.answer,
                category: answer.category
            }))
        };
        
        console.log('Sending data to API:', apiData);
        
        // Try AI API first, fallback to local calculation if it fails
        fetch(FLASK_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(apiData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`API responded with status: ${response.status}`);
            }
            return response.json();
        })
        .then(apiResult => {
            console.log('API Response:', apiResult);
            
            if (apiResult.success && apiResult.total_score !== undefined) {
                handleScoreResult(apiResult.total_score, true, apiResult);
            } else {
                throw new Error('Invalid API response format');
            }
        })
        .catch(error => {
            console.error('API failed:', error);
            const fallbackScore = calculateFallbackScore();
            handleScoreResult(fallbackScore, false, null);
        });
    }

    function calculateFallbackScore() {
        let totalScore = 0;
        let totalWeight = 0;
        
        Object.values(answers).forEach(answer => {
            const weight = getCategoryWeight(answer.category);
            totalScore += answer.value * weight;
            totalWeight += answer.max_value * weight;
        });
        
        const finalScore = totalWeight > 0 ? (totalScore / totalWeight) * 100 : 0;
        return Math.min(100, Math.round(finalScore * 100) / 100);
    }

    function getCategoryWeight(category) {
        const weights = {
            'education': 1.2,
            'experience': 1.3,
            'technical': 1.1,
            'certifications': 1.0,
            'soft_skills': 0.9,
            'flexibility': 0.8,
            'learning': 1.0,
            'general': 0.7
        };
        return weights[category] || 1.0;
    }

    function handleScoreResult(score, fromAPI, apiResult) {
        const chatMessages = document.getElementById('chatMessages');
        
        const calculatingMsg = chatMessages.querySelector('.chat-message.bot:last-child');
        if (calculatingMsg && calculatingMsg.textContent.includes('Analyzing')) {
            calculatingMsg.remove();
        }
        
        const source = fromAPI ? 'AI-powered assessment' : 'standard assessment';
        const resultHTML = `
            <div class="chat-message bot">
                <div class="message-avatar bot">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-bubble bot">
                    <p>🎉 Assessment Complete!</p>
                    <p>Your employability score is <strong>${score}%</strong> (${source})</p>
                    ${!fromAPI ? '<p><small><i class="fas fa-info-circle"></i> Using fallback scoring - AI service temporarily unavailable</small></p>' : ''}
                    <span class="message-time">${getCurrentTime()}</span>
                </div>
            </div>
        `;
        chatMessages.insertAdjacentHTML('beforeend', resultHTML);
        scrollToBottom();
        
        saveResultsToDatabase(score, fromAPI, apiResult);
    }

    function saveResultsToDatabase(score, fromAPI, apiResult) {
        const formData = new FormData();
        formData.append('qualification_id', selectedQualificationId);
        formData.append('score', score);
        
        const answersArray = Object.keys(answers).map(key => {
            const answer = answers[key];
            return {
                question: answer.question,
                answer: answer.answer,
                value: answer.value,
                category: answer.category,
                option_index: answer.option_index
            };
        });
        
        formData.append('answers', JSON.stringify(answersArray));
        
        fetch('chatbot_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('PHP Response:', data);
            if (data.success) {
                setTimeout(() => {
                    showFinalResults(score, fromAPI, apiResult);
                }, 1000);
            } else {
                alert('Error saving results: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error saving results:', error);
            alert('Error saving assessment results. Please try again.');
        });
    }

    function showFinalResults(score, fromAPI, apiResult) {
        document.getElementById('questionsSection').style.display = 'none';
        document.getElementById('qualificationSelection').style.display = 'none';
        document.getElementById('resultsSection').classList.add('active');
        document.getElementById('finalScore').textContent = score + '%';
        
        if (fromAPI && apiResult && apiResult.category_scores) {
            showScoreBreakdown(apiResult.category_scores);
        }
        
        showAnswersSummary();
        
        // Redirect to profile after 5 seconds
        setTimeout(() => {
            window.location.href = 'profile.php?from_chatbot=1&score=' + score;
        }, 5000);
    }

    function showScoreBreakdown(categoryScores) {
        const breakdownContent = document.getElementById('breakdownContent');
        let breakdownHTML = '';
        
        for (const [category, score] of Object.entries(categoryScores)) {
            const categoryName = category.replace('_', ' ').toUpperCase();
            breakdownHTML += `
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                    <span style="font-weight: 500;">${categoryName}</span>
                    <span style="color: #4CAF50; font-weight: bold;">${score}%</span>
                </div>
            `;
        }
        
        breakdownContent.innerHTML = breakdownHTML;
        document.getElementById('scoreBreakdown').style.display = 'block';
    }

    function showAnswersSummary() {
        let summaryHTML = '<h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Your Assessment Answers:</h3>';
        Object.keys(answers).forEach((key, index) => {
            summaryHTML += `
                <div class="answer-item">
                    <div class="question-number">Question ${parseInt(key) + 1}</div>
                    <div class="question-text">${answers[key].question}</div>
                    <div class="answer-text">${answers[key].answer}</div>
                </div>
            `;
        });
        document.getElementById('answersSummary').innerHTML = summaryHTML;
    }
    </script>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</body>
</html>