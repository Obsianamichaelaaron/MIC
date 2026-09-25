<?php
/**
 * STEP 1 — Run this file ONCE in your browser or via CLI to create the tables.
 * URL: https://yourdomain.com/frontend/database/create_contact_inquiries_table.php
 *
 * Creates:
 *   contact_inquiries  – stores every contact-form submission
 *   contact_replies    – stores admin replies to each inquiry
 */

require_once __DIR__ . '/../includes/config/database.php';

$conn = getDBConnection();

// ── 1. contact_inquiries ──────────────────────────────────────────────────────
$sql1 = "CREATE TABLE IF NOT EXISTS contact_inquiries (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(150)    NOT NULL,
    email       VARCHAR(255)    NOT NULL,
    subject     VARCHAR(255)    NOT NULL DEFAULT 'General Inquiry',
    message     TEXT            NOT NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    status      ENUM('new','open','replied','closed') NOT NULL DEFAULT 'new',
    ip_address  VARCHAR(45)     NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status    (status),
    INDEX idx_is_read   (is_read),
    INDEX idx_email     (email),
    INDEX idx_created   (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql1) === TRUE) {
    echo "✅ contact_inquiries table created (or already exists).<br>";
} else {
    echo "❌ Error creating contact_inquiries: " . $conn->error . "<br>";
}

// ── 2. contact_replies ────────────────────────────────────────────────────────
$sql2 = "CREATE TABLE IF NOT EXISTS contact_replies (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    inquiry_id   INT         NOT NULL,
    admin_id     INT         NULL,
    reply_text   TEXT        NOT NULL,
    sent_at      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    email_sent   TINYINT(1)  NOT NULL DEFAULT 0,
    FOREIGN KEY (inquiry_id) REFERENCES contact_inquiries(id) ON DELETE CASCADE,
    INDEX idx_inquiry (inquiry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql2) === TRUE) {
    echo "✅ contact_replies table created (or already exists).<br>";
} else {
    echo "❌ Error creating contact_replies: " . $conn->error . "<br>";
}

$conn->close();
echo "<br>✅ Database setup complete. You may now delete or restrict access to this file.";
?>
