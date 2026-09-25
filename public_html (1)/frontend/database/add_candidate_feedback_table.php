<?php
/**
 * Create candidate_feedback table for storing employer feedback on candidates
 * Run this file via browser: http://localhost/system123/database/add_candidate_feedback_table.php
 */

require_once '../includes/config/database.php';

$conn = getDBConnection();

echo "<h2>Candidate Feedback Table Setup</h2>";
echo "<pre>";

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
if ($table_check && $table_check->num_rows > 0) {
    echo "✓ candidate_feedback table already exists\n";
} else {
    echo "✗ candidate_feedback table not found. Creating...\n\n";
    
    // Create the table
    $sql = "CREATE TABLE IF NOT EXISTS candidate_feedback (
        feedback_id INT PRIMARY KEY AUTO_INCREMENT,
        application_id INT NOT NULL,
        applicant_id INT NOT NULL,
        employer_id INT NOT NULL,
        employability_score DECIMAL(5,2) NOT NULL,
        feedback_message TEXT NOT NULL,
        feedback_type ENUM('automatic', 'manual') DEFAULT 'automatic',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
        FOREIGN KEY (applicant_id) REFERENCES applicants(applicant_id) ON DELETE CASCADE,
        FOREIGN KEY (employer_id) REFERENCES employers(employer_id) ON DELETE CASCADE,
        INDEX idx_application (application_id),
        INDEX idx_applicant (applicant_id),
        INDEX idx_employer (employer_id)
    )";
    
    if ($conn->query($sql)) {
        echo "✓ Successfully created candidate_feedback table!\n";
    } else {
        echo "✗ Error creating table: " . $conn->error . "\n";
    }
}

echo "\n--- Verification ---\n";
$verify = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
if ($verify && $verify->num_rows > 0) {
    echo "✓ Table verified successfully!\n";
    
    // Show table structure
    echo "\n--- Table Structure ---\n";
    $structure = $conn->query("DESCRIBE candidate_feedback");
    while ($row = $structure->fetch_assoc()) {
        echo sprintf("%-20s %-20s %-10s\n", $row['Field'], $row['Type'], $row['Null']);
    }
} else {
    echo "✗ Table verification failed!\n";
}

closeDBConnection($conn);
echo "</pre>";
?>

