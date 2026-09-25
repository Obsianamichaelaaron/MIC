<?php
/**
 * Add resume_file column to applications table
 * This allows applicants to upload a specific resume for each job application
 */

require_once __DIR__ . '/../includes/config/database.php';

$conn = getDBConnection();

// Check if column already exists
$result = $conn->query("SHOW COLUMNS FROM applications LIKE 'resume_file'");
if ($result->num_rows == 0) {
    // Add resume_file column to applications table
    $sql = "ALTER TABLE applications ADD COLUMN resume_file VARCHAR(255) NULL AFTER cover_letter";
    
    if ($conn->query($sql)) {
        echo "✓ Successfully added resume_file column to applications table\n";
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "⊘ Column 'resume_file' already exists in applications table\n";
}

closeDBConnection($conn);
echo "\n✓ Migration completed!\n";
?>

