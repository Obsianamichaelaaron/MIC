<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

header('Content-Type: application/json');

// Function to log errors with more details
function logError($message, $conn = null) {
    error_log("Assessment Error: " . $message);
    if ($conn) {
        error_log("MySQL Error: " . $conn->error);
    }
}

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result->num_rows > 0;
}

// Function to ensure database schema compatibility
function ensureChatbotAnswersSchema($conn) {
    $columns = [
        'category' => "ALTER TABLE chatbot_answers ADD COLUMN category VARCHAR(255) DEFAULT 'general'",
        'score_value' => "ALTER TABLE chatbot_answers ADD COLUMN score_value INT DEFAULT 0",
        'question_text' => "ALTER TABLE chatbot_answers ADD COLUMN question_text TEXT",
        'answer_text' => "ALTER TABLE chatbot_answers ADD COLUMN answer_text TEXT",
        'question_number' => "ALTER TABLE chatbot_answers ADD COLUMN question_number INT"
    ];
    
    foreach ($columns as $column => $alterSql) {
        if (!columnExists($conn, 'chatbot_answers', $column)) {
            if (!$conn->query($alterSql)) {
                throw new Exception("Failed to add column $column: " . $conn->error);
            }
            error_log("Added missing column: $column to chatbot_answers table");
        }
    }
}

// Function to get table structure
function getTableStructure($conn, $table) {
    $result = $conn->query("DESCRIBE $table");
    $structure = [];
    while ($row = $result->fetch_assoc()) {
        $structure[] = $row['Field'];
    }
    return $structure;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = getCurrentUserId();
    
    // Get applicant ID
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
    if (!$stmt) {
        logError("Prepare failed: " . $conn->error, $conn);
        echo json_encode([
            'success' => false,
            'message' => 'Database prepare error'
        ]);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applicant = $result->fetch_assoc();
    
    if (!$applicant) {
        echo json_encode([
            'success' => false,
            'message' => 'Applicant not found'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $applicant_id = $applicant['applicant_id'];
    $stmt->close();
    
    // Handle assessment submission
    if (isset($_POST['qualification_id']) && isset($_POST['score'])) {
        $qualification_id = intval($_POST['qualification_id']);
        $score = floatval($_POST['score']);
        $answers = json_decode($_POST['answers'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid answers format'
            ]);
            $conn->close();
            exit;
        }
        
        try {
            $conn->begin_transaction();
            
            // Ensure the table has all required columns
            ensureChatbotAnswersSchema($conn);
            
            // Get current table structure
            $tableStructure = getTableStructure($conn, 'chatbot_answers');
            
            // Delete previous answers
            $stmt = $conn->prepare("DELETE FROM chatbot_answers WHERE applicant_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $applicant_id);
            if (!$stmt->execute()) {
                throw new Exception("Delete answers failed: " . $stmt->error);
            }
            $stmt->close();
            
            // Check which columns exist and build dynamic INSERT query
            $has_category = in_array('category', $tableStructure);
            $has_score_value = in_array('score_value', $tableStructure);
            $has_question_text = in_array('question_text', $tableStructure);
            $has_answer_text = in_array('answer_text', $tableStructure);
            $has_question_number = in_array('question_number', $tableStructure);
            
            // Build dynamic INSERT query based on available columns
            $columns = ['applicant_id'];
            $placeholders = ['?'];
            $types = 'i'; // applicant_id is integer
            
            if ($has_question_number) {
                $columns[] = 'question_number';
                $placeholders[] = '?';
                $types .= 'i';
            }
            
            if ($has_question_text) {
                $columns[] = 'question_text';
                $placeholders[] = '?';
                $types .= 's';
            }
            
            if ($has_answer_text) {
                $columns[] = 'answer_text';
                $placeholders[] = '?';
                $types .= 's';
            }
            
            if ($has_category) {
                $columns[] = 'category';
                $placeholders[] = '?';
                $types .= 's';
            }
            
            if ($has_score_value) {
                $columns[] = 'score_value';
                $placeholders[] = '?';
                $types .= 'i';
            }
            
            // If no additional columns exist, use minimal structure
            if (count($columns) === 1) {
                $columns[] = 'answer_data';
                $placeholders[] = '?';
                $types .= 's';
                
                $stmt = $conn->prepare("INSERT INTO chatbot_answers (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")");
                
                foreach ($answers as $index => $answer) {
                    $answer_data = json_encode([
                        'question_number' => $index + 1,
                        'question' => $answer['question'] ?? '',
                        'answer' => $answer['answer'] ?? '',
                        'category' => $answer['category'] ?? 'general',
                        'value' => intval($answer['value'] ?? 0)
                    ]);
                    
                    $stmt->bind_param("is", $applicant_id, $answer_data);
                    if (!$stmt->execute()) {
                        throw new Exception("Insert answer failed: " . $stmt->error);
                    }
                }
            } else {
                // Prepare the dynamic INSERT statement
                $sql = "INSERT INTO chatbot_answers (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                foreach ($answers as $index => $answer) {
                    $params = [$applicant_id];
                    
                    if ($has_question_number) {
                        $params[] = $index + 1;
                    }
                    
                    if ($has_question_text) {
                        $params[] = $answer['question'] ?? '';
                    }
                    
                    if ($has_answer_text) {
                        $params[] = $answer['answer'] ?? '';
                    }
                    
                    if ($has_category) {
                        $params[] = $answer['category'] ?? 'general';
                    }
                    
                    if ($has_score_value) {
                        $params[] = intval($answer['value'] ?? 0);
                    }
                    
                    $stmt->bind_param($types, ...$params);
                    if (!$stmt->execute()) {
                        throw new Exception("Insert answer failed: " . $stmt->error);
                    }
                }
            }
            $stmt->close();
            
            // Update applicant score - check if qualification_id column exists
            $has_qualification_id = columnExists($conn, 'applicants', 'qualification_id');
            
            if ($has_qualification_id) {
                $stmt = $conn->prepare("UPDATE applicants SET employability_score = ?, qualification_id = ? WHERE applicant_id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("dii", $score, $qualification_id, $applicant_id);
            } else {
                // If qualification_id column doesn't exist, only update the score
                $stmt = $conn->prepare("UPDATE applicants SET employability_score = ? WHERE applicant_id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("di", $score, $applicant_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Update applicant failed: " . $stmt->error);
            }
            $stmt->close();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Assessment results saved successfully',
                'score' => $score
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            logError("Transaction failed: " . $e->getMessage(), $conn);
            echo json_encode([
                'success' => false,
                'message' => 'Error saving assessment: ' . $e->getMessage()
            ]);
        }
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: qualification_id and score are required'
        ]);
    }
    
    $conn->close();
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'reset_assessment') {
    // Handle assessment reset
    $user_id = getCurrentUserId();
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }
    
    // Get applicant ID
    $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
    if (!$stmt) {
        logError("Prepare failed: " . $conn->error, $conn);
        echo json_encode([
            'success' => false,
            'message' => 'Database prepare error: ' . $conn->error
        ]);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        logError("Execute failed: " . $stmt->error, $conn);
        echo json_encode([
            'success' => false,
            'message' => 'Database execute error: ' . $stmt->error
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Applicant not found'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $applicant = $result->fetch_assoc();
    $applicant_id = $applicant['applicant_id'];
    $stmt->close();
    
    try {
        $conn->begin_transaction();
        
        // Delete answers first
        $stmt = $conn->prepare("DELETE FROM chatbot_answers WHERE applicant_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare delete failed: " . $conn->error);
        }
        $stmt->bind_param("i", $applicant_id);
        if (!$stmt->execute()) {
            throw new Exception("Delete answers failed: " . $stmt->error);
        }
        $stmt->close();
        
        // Reset score - check if qualification_id column exists
        $has_qualification_id = columnExists($conn, 'applicants', 'qualification_id');
        
        if ($has_qualification_id) {
            $stmt = $conn->prepare("UPDATE applicants SET employability_score = 0, qualification_id = NULL WHERE applicant_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE applicants SET employability_score = 0 WHERE applicant_id = ?");
        }
        
        if (!$stmt) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $applicant_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Update applicant failed: " . $stmt->error);
        }
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Assessment reset successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        logError("Reset transaction failed: " . $e->getMessage(), $conn);
        echo json_encode([
            'success' => false,
            'message' => 'Error resetting assessment: ' . $e->getMessage()
        ]);
    }
    
    $conn->close();
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method or missing action parameter'
    ]);
}
?>