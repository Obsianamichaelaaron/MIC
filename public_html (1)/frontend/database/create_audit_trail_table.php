<?php
/**
 * Create Audit Trail Table
 * This table tracks all admin actions, especially employer creation
 */

require_once __DIR__ . '/../includes/config/database.php';

$conn = getDBConnection();

// Create audit_trail table
$sql = "CREATE TABLE IF NOT EXISTS audit_trail (
    audit_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_user_id INT NOT NULL,
    admin_name VARCHAR(255) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_description TEXT NOT NULL,
    target_type VARCHAR(50) NULL COMMENT 'Type of entity affected: employer, applicant, user, job, etc.',
    target_id INT NULL COMMENT 'ID of the affected entity',
    target_name VARCHAR(255) NULL COMMENT 'Name/identifier of the affected entity',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_user_id),
    INDEX idx_action (action_type),
    INDEX idx_target (target_type, target_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (admin_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "✓ Successfully created audit_trail table\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

closeDBConnection($conn);
echo "\n✓ Migration completed!\n";
?>

