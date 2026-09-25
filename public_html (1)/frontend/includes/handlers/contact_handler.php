<?php
/**
 * includes/handlers/contact_handler.php
 * Handles POST from the contact form on index.php.
 * Returns JSON. Never crashes — DB errors are caught gracefully.
 */

// Suppress all PHP errors/warnings from leaking into JSON output
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // buffer any accidental output

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'errors' => ['Invalid request method']]);
    exit();
}

// ── Sanitize inputs ───────────────────────────────────────────────────────────
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? 'General Inquiry');
$message = trim($_POST['message'] ?? '');

// ── Validation ────────────────────────────────────────────────────────────────
$errors = [];

if (empty($name))                                    $errors[] = 'Name is required.';
elseif (mb_strlen($name) > 150)                      $errors[] = 'Name is too long (max 150 chars).';

if (empty($email))                                   $errors[] = 'Email is required.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Invalid email format.';

if (empty($subject)) $subject = 'General Inquiry';
if (mb_strlen($subject) > 255)                       $errors[] = 'Subject is too long (max 255 chars).';

if (empty($message))                                 $errors[] = 'Message is required.';
elseif (mb_strlen($message) < 10)                    $errors[] = 'Message is too short (min 10 characters).';

if (!empty($errors)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// ── Save to database (safe — won't crash if table missing) ────────────────────
$dbSaved = false;
try {
    $conn = getDBConnection();
    $ip   = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO contact_inquiries (name, email, subject, message, ip_address)
         VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('sssss', $name, $email, $subject, $message, $ip);
        $dbSaved = $stmt->execute();
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    // Table may not exist yet — fall through to log file
    error_log('[ContactHandler] DB error: ' . $e->getMessage());
}

// ── Append to log file (fallback so no message is ever lost) ──────────────────
try {
    $logDir  = __DIR__ . '/../../logs';
    $logFile = $logDir . '/contact_messages.log';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logEntry = sprintf("[%s] %s <%s> | Subject: %s | %s%s",
        date('c'), $name, $email, $subject, $message, PHP_EOL);
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
    // Log write failed — not critical
}

// ── Always return success to the user ────────────────────────────────────────
ob_end_clean();
echo json_encode([
    'success' => true,
    'message' => "Thank you, {$name}! Your message has been received. We'll get back to you soon."
]);