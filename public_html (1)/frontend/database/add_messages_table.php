<?php
/**
 * Create messages table for chat system
 * This allows communication between applicants, employers, and admins
 */

require_once __DIR__ . '/../includes/config/database.php';

$conn = getDBConnection();

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_created_at (created_at),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Messages table created successfully!\n";
} else {
    echo "Error creating messages table: " . $conn->error . "\n";
}

// Create conversation index for faster queries
$sql_index = "CREATE INDEX idx_conversation ON messages(sender_id, receiver_id, created_at DESC)";
$conn->query($sql_index); // Ignore error if index already exists

closeDBConnection($conn);
echo "Chat system database setup complete!\n";
?>

