<?php
/**
 * verify_otp.php
 * Validates the OTP code entered by the user.
 * Called via AJAX. Returns JSON with success/failure.
 */

require_once 'includes/config/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$entered_otp = trim($_POST['otp'] ?? '');
$email       = trim($_POST['email'] ?? '');

// Check session OTP exists
if (!isset($_SESSION['email_otp'])) {
    echo json_encode(['success' => false, 'message' => 'No verification code found. Please request a new one.']);
    exit;
}

$session_otp = $_SESSION['email_otp'];

// Check email match
if ($session_otp['email'] !== $email) {
    echo json_encode(['success' => false, 'message' => 'Email mismatch. Please request a new code.']);
    exit;
}

// Check expiry
if (time() > $session_otp['expires']) {
    unset($_SESSION['email_otp']);
    echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
    exit;
}

// Check max attempts (5 tries)
if ($session_otp['attempts'] >= 5) {
    unset($_SESSION['email_otp']);
    echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.']);
    exit;
}

// Increment attempts
$_SESSION['email_otp']['attempts']++;

// Validate OTP
if ($entered_otp !== $session_otp['code']) {
    $remaining = 5 - $_SESSION['email_otp']['attempts'];
    echo json_encode([
        'success'   => false,
        'message'   => "Incorrect code. You have {$remaining} attempt(s) remaining.",
        'attempts'  => $_SESSION['email_otp']['attempts'],
    ]);
    exit;
}

// OTP is correct — mark as verified
$_SESSION['email_otp']['verified'] = true;
$_SESSION['otp_verified_email']    = $email; // used by registration to confirm verification

echo json_encode([
    'success' => true,
    'message' => 'Email verified successfully! Completing your registration...',
]);
