<?php
/**
 * Audit Trail Handler
 * Functions to log admin actions to the audit trail
 */

/**
 * Log an action to the audit trail
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_user_id Admin user ID who performed the action
 * @param string $action_type Type of action (e.g., 'create_employer', 'update_user')
 * @param string $action_description Description of the action
 * @param string|null $target_type Type of entity affected (e.g., 'employer', 'user')
 * @param int|null $target_id ID of the affected entity
 * @param string|null $target_name Name/identifier of the affected entity
 * @return bool Success status
 */
function logAuditTrail($conn, $admin_user_id, $action_type, $action_description, $target_type = null, $target_id = null, $target_name = null) {
    // Get admin name
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $admin_user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $admin_name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
    if (empty($admin_name)) {
        $admin_name = 'Unknown Admin';
    }
    
    // Get IP address and user agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Check if audit_trail table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'audit_trail'");
    if (!$table_check || $table_check->num_rows == 0) {
        // Create the table
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
        $conn->query($sql);
    }
    
    // Insert audit trail record
    $stmt = $conn->prepare("
        INSERT INTO audit_trail 
        (admin_user_id, admin_name, action_type, action_description, target_type, target_id, target_name, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issssisss", $admin_user_id, $admin_name, $action_type, $action_description, $target_type, $target_id, $target_name, $ip_address, $user_agent);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

