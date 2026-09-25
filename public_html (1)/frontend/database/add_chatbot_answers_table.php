<?php
require_once '../includes/config/database.php';

$conn = getDBConnection();

// Create chatbot_answers table to store Q&A responses
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
    echo "Created chatbot_answers table<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Update chatbot_recommendations table to remove pattern_match type
// First, update existing pattern_match records
$sql = "UPDATE chatbot_recommendations SET recommendation_type = 'qualification' WHERE recommendation_type = 'pattern_match'";
if ($conn->query($sql)) {
    echo "Updated existing pattern_match records<br>";
} else {
    echo "Error updating records: " . $conn->error . "<br>";
}

// Note: We cannot directly modify ENUM in MySQL without ALTER TABLE
// This will be handled in the migration script
echo "<br>Note: To remove 'pattern_match' from ENUM, run the following SQL manually:<br>";
echo "ALTER TABLE chatbot_recommendations MODIFY recommendation_type ENUM('job', 'qualification') NOT NULL;<br>";

closeDBConnection($conn);
echo "<br>Database setup completed!";
?>

