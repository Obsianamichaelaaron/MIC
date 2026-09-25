<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "View Chatbot Data";
$applicant_id = intval($_GET['applicant_id'] ?? 0);

if ($applicant_id <= 0) {
    die('Invalid applicant ID');
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
    die('Applicant not found');
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        h1 {
            color: #0056b3;
            margin-bottom: 1rem;
        }
        .applicant-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .applicant-info h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .applicant-info p {
            color: #666;
            margin-bottom: 0.25rem;
        }
        .score-display {
            font-size: 2rem;
            font-weight: bold;
            color: #0056b3;
            margin-top: 0.5rem;
        }
        .answers-section {
            margin-top: 2rem;
        }
        .answer-item {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #0056b3;
        }
        .answer-item h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .answer-item p {
            color: #0056b3;
            margin-left: 1rem;
        }
        .qualification-badge {
            display: inline-block;
            background: #9C27B0;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-robot"></i> Chatbot Assessment Data</h1>
        
        <div class="applicant-info">
            <h2><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></h2>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($applicant['email']); ?></p>
            <p><strong>Employability Score:</strong> <span class="score-display"><?php echo number_format($applicant['employability_score'], 2); ?>%</span></p>
        </div>
        
        <?php if (count($chatbot_answers) > 0): ?>
            <?php if (!empty($chatbot_answers[0]['qualification_name'])): ?>
                <div class="qualification-badge">
                    <i class="fas fa-tag"></i> Category: <?php echo htmlspecialchars($chatbot_answers[0]['qualification_name']); ?>
                </div>
            <?php endif; ?>
            
            <div class="answers-section">
                <h2 style="color: #333; margin-bottom: 1rem;">Assessment Answers:</h2>
                <?php foreach ($chatbot_answers as $answer): ?>
                    <div class="answer-item">
                        <h3>Q<?php echo $answer['question_number'] + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?></h3>
                        <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($answer['answer_text']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 2rem;">No chatbot data available for this applicant.</p>
        <?php endif; ?>
        
        <div style="margin-top: 2rem; text-align: center;">
            <button onclick="window.close()" style="background: #6c757d; color: white; padding: 0.75rem 2rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                Close Window
            </button>
        </div>
    </div>
</body>
</html>

