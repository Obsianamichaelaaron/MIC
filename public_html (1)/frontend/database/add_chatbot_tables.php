<?php
require_once '../includes/config/database.php';

$conn = getDBConnection();

// Add target_qualifications column to job_postings table (check if it exists first)
$column_check = $conn->query("SHOW COLUMNS FROM job_postings LIKE 'target_qualifications'");
if ($column_check && $column_check->num_rows == 0) {
    $sql = "ALTER TABLE job_postings ADD COLUMN target_qualifications TEXT NULL COMMENT 'Comma-separated qualification IDs'";
    if ($conn->query($sql)) {
        echo "Added target_qualifications column to job_postings table<br>";
    } else {
        echo "Error: " . $conn->error . "<br>";
    }
} else {
    echo "target_qualifications column already exists<br>";
}

// Create resume_analysis table
$sql = "CREATE TABLE IF NOT EXISTS resume_analysis (
    analysis_id INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    resume_file VARCHAR(255) NOT NULL,
    extracted_text TEXT,
    skills_extracted TEXT,
    education_extracted TEXT,
    experience_extracted TEXT,
    qualifications_extracted TEXT,
    analysis_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(applicant_id) ON DELETE CASCADE,
    INDEX idx_applicant (applicant_id)
)";
if ($conn->query($sql)) {
    echo "Created resume_analysis table<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Create chatbot_recommendations table
$sql = "CREATE TABLE IF NOT EXISTS chatbot_recommendations (
    recommendation_id INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    job_id INT NULL,
    qualification_id INT NULL,
    recommendation_type ENUM('job', 'qualification') NOT NULL,
    recommendation_text TEXT,
    match_score DECIMAL(5,2) DEFAULT 0.00,
    is_selected BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(applicant_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_postings(job_id) ON DELETE CASCADE,
    FOREIGN KEY (qualification_id) REFERENCES qualifications(qualification_id) ON DELETE CASCADE,
    INDEX idx_applicant (applicant_id),
    INDEX idx_job (job_id)
)";
if ($conn->query($sql)) {
    echo "Created chatbot_recommendations table<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Update existing table if pattern_match exists (for existing databases)
$check_sql = "SHOW COLUMNS FROM chatbot_recommendations LIKE 'pattern_matched'";
$result = $conn->query($check_sql);
if ($result && $result->num_rows > 0) {
    // Table exists, try to update ENUM and remove pattern_matched column
    $update_sql = "ALTER TABLE chatbot_recommendations MODIFY recommendation_type ENUM('job', 'qualification') NOT NULL";
    if ($conn->query($update_sql)) {
        echo "Updated recommendation_type ENUM<br>";
    }
    
    $drop_sql = "ALTER TABLE chatbot_recommendations DROP COLUMN pattern_matched";
    if ($conn->query($drop_sql)) {
        echo "Removed pattern_matched column<br>";
    }
}

// Create chatbot_answers table
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

// Create job_qualification_mapping table
$sql = "CREATE TABLE IF NOT EXISTS job_qualification_mapping (
    mapping_id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    qualification_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_postings(job_id) ON DELETE CASCADE,
    FOREIGN KEY (qualification_id) REFERENCES qualifications(qualification_id) ON DELETE CASCADE,
    UNIQUE KEY unique_mapping (job_id, qualification_id),
    INDEX idx_job (job_id),
    INDEX idx_qualification (qualification_id)
)";
if ($conn->query($sql)) {
    echo "Created job_qualification_mapping table<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

closeDBConnection($conn);
echo "<br>Database setup completed!";
?>

