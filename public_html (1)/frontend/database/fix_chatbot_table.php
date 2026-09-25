<?php
/**
 * Quick Fix: Create chatbot_answers table if missing
 * Run this file via browser: http://localhost/system123/database/fix_chatbot_table.php
 */

require_once '../includes/config/database.php';

$conn = getDBConnection();

echo "<h2>Chatbot Answers Table Fix</h2>";
echo "<pre>";

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'chatbot_answers'");
if ($table_check && $table_check->num_rows > 0) {
    echo "✓ chatbot_answers table already exists\n";
} else {
    echo "✗ chatbot_answers table not found. Creating...\n\n";
    
    // Create the table
    $sql = "CREATE TABLE IF NOT EXISTS chatbot_answers (
        answer_id INT PRIMARY KEY AUTO_INCREMENT,
        applicant_id INT NOT NULL,
        qualification_id INT NULL,
        question_number INT NOT NULL,
        question_text TEXT NOT NULL,
        answer_text TEXT NOT NULL,
        answer_value VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (applicant_id) REFERENCES applicants(applicant_id) ON DELETE CASCADE,
        FOREIGN KEY (qualification_id) REFERENCES qualifications(qualification_id) ON DELETE CASCADE,
        INDEX idx_applicant (applicant_id),
        INDEX idx_qualification (qualification_id)
    )";
    
    if ($conn->query($sql)) {
        echo "✓ Successfully created chatbot_answers table!\n";
    } else {
        echo "✗ Error creating table: " . $conn->error . "\n";
    }
}

// Also check and update chatbot_recommendations table
echo "\n--- Checking chatbot_recommendations table ---\n";
$check_sql = "SHOW COLUMNS FROM chatbot_recommendations LIKE 'pattern_matched'";
$result = $conn->query($check_sql);
if ($result && $result->num_rows > 0) {
    echo "Found pattern_matched column. Updating...\n";
    
    // Update existing pattern_match records
    $update_sql = "UPDATE chatbot_recommendations SET recommendation_type = 'qualification' WHERE recommendation_type = 'pattern_match'";
    if ($conn->query($update_sql)) {
        echo "✓ Updated existing pattern_match records\n";
    }
    
    // Update ENUM
    $enum_sql = "ALTER TABLE chatbot_recommendations MODIFY recommendation_type ENUM('job', 'qualification') NOT NULL";
    if ($conn->query($enum_sql)) {
        echo "✓ Updated recommendation_type ENUM\n";
    }
    
    // Remove pattern_matched column
    $drop_sql = "ALTER TABLE chatbot_recommendations DROP COLUMN pattern_matched";
    if ($conn->query($drop_sql)) {
        echo "✓ Removed pattern_matched column\n";
    }
} else {
    echo "✓ chatbot_recommendations table is up to date\n";
}

echo "\n--- Verification ---\n";
$verify = $conn->query("SHOW TABLES LIKE 'chatbot_answers'");
if ($verify && $verify->num_rows > 0) {
    echo "✓ chatbot_answers table exists and is ready to use!\n";
} else {
    echo "✗ chatbot_answers table still missing. Please check database permissions.\n";
}

closeDBConnection($conn);
echo "</pre>";
echo "<p><a href='../applicant/chatbot.php'>Go to Chatbot</a></p>";
?>

