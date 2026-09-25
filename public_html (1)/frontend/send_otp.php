<?php
/**
 * send_otp.php
 * Generates a 6-digit OTP, stores it in the session, and emails it to the user.
 * Called via AJAX from the registration page before the account is created.
 */

require_once 'includes/config/session.php';
require_once 'includes/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email      = trim($_POST['email'] ?? '');
$first_name = trim($_POST['first_name'] ?? 'User');

// Basic email check
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit;
}

// Check if email is already registered
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'This email is already registered. Please log in instead.']);
    exit;
}
$stmt->close();
$conn->close();

// Rate limiting: max 3 OTP requests per 10 minutes per email
$rate_key = 'otp_rate_' . md5($email);
if (isset($_SESSION[$rate_key])) {
    $rate_data = $_SESSION[$rate_key];
    if ($rate_data['count'] >= 3 && (time() - $rate_data['first_request']) < 600) {
        $wait = 600 - (time() - $rate_data['first_request']);
        echo json_encode(['success' => false, 'message' => "Too many OTP requests. Please wait {$wait} seconds before trying again."]);
        exit;
    }
    if ((time() - $rate_data['first_request']) >= 600) {
        $_SESSION[$rate_key] = ['count' => 1, 'first_request' => time()];
    } else {
        $_SESSION[$rate_key]['count']++;
    }
} else {
    $_SESSION[$rate_key] = ['count' => 1, 'first_request' => time()];
}

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Store OTP in session (expires in 10 minutes)
$_SESSION['email_otp'] = [
    'code'     => $otp,
    'email'    => $email,
    'expires'  => time() + 600,
    'verified' => false,
    'attempts' => 0,
];

// Build the HTML email body
$site_name = 'MULTIBIZ INTERNATIONAL CORPORATION';

$message_html = "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
    .container { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .header { background: linear-gradient(135deg, #1a73e8, #0d47a1); padding: 30px 20px; text-align: center; }
    .header h1 { color: #ffffff; margin: 0; font-size: 22px; }
    .body { padding: 35px 30px; }
    .body p { color: #444; font-size: 15px; line-height: 1.6; }
    .otp-box { margin: 28px auto; text-align: center; }
    .otp-code { display: inline-block; background: #f0f4ff; border: 2px dashed #1a73e8; border-radius: 10px; padding: 18px 36px; font-size: 42px; font-weight: 700; letter-spacing: 10px; color: #1a73e8; font-family: 'Courier New', monospace; }
    .expiry { text-align: center; color: #888; font-size: 13px; margin-top: 10px; }
    .footer { background: #f8f9fa; padding: 18px; text-align: center; color: #aaa; font-size: 12px; }
    .warning { background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 4px; margin-top: 20px; font-size: 13px; color: #666; }
  </style>
</head>
<body>
  <div class='container'>
    <div class='header'>
      <h1>&#x2709;&#xFE0F; Email Verification</h1>
    </div>
    <div class='body'>
      <p>Hi <strong>" . htmlspecialchars($first_name) . "</strong>,</p>
      <p>Thank you for registering with <strong>{$site_name}</strong>. Use the verification code below to complete your registration:</p>
      <div class='otp-box'>
        <div class='otp-code'>{$otp}</div>
      </div>
      <p class='expiry'>&#x23F1; This code expires in <strong>10 minutes</strong>.</p>
      <div class='warning'>
        &#x26A0;&#xFE0F; <strong>Never share this code.</strong> Our team will never ask for your verification code.
        If you did not request this, please ignore this email.
      </div>
    </div>
    <div class='footer'>
      &copy; " . date('Y') . " {$site_name}. All rights reserved.
    </div>
  </div>
</body>
</html>
";

// ---------------------------------------------------------------
// Load PHPMailer — check all possible install locations
// ---------------------------------------------------------------
require_once __DIR__ . '/includes/config/email.php';

$phpmailerAvailable = false;

// Option 1: Composer autoload (if composer require phpmailer/phpmailer was run)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $phpmailerAvailable = true;
}

// Option 2: Manually installed via install_phpmailer.php
if (!$phpmailerAvailable && file_exists(__DIR__ . '/includes/vendor/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/includes/vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/includes/vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/includes/vendor/PHPMailer/src/SMTP.php';
    $phpmailerAvailable = true;
}

// Option 3: Manually placed in root vendor folder
if (!$phpmailerAvailable && file_exists(__DIR__ . '/vendor/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';
    $phpmailerAvailable = true;
}

if (!$phpmailerAvailable) {
    // Log which paths were checked to help debug
    error_log('[OTP Mailer] PHPMailer not found. Checked:');
    error_log('  1. ' . __DIR__ . '/vendor/autoload.php');
    error_log('  2. ' . __DIR__ . '/includes/vendor/PHPMailer/src/PHPMailer.php');
    error_log('  3. ' . __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php');
    error_log('  Fix: log in as admin and visit install_phpmailer.php');
    echo json_encode(['success' => false, 'message' => 'Mail system not configured. Please contact support.']);
    exit;
}

// Capture PHPMailer debug output into a variable for error logging
$smtpDebugLog = '';

try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    // Log SMTP conversation to a variable (not output) for debugging
    $mail->SMTPDebug   = 3;
    $mail->Debugoutput = function($str, $level) use (&$smtpDebugLog) {
        $smtpDebugLog .= "[{$level}] {$str}\n";
    };

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;           // smtp.gmail.com
    $mail->SMTPAuth   = SMTP_AUTH;           // true
    $mail->Username   = SMTP_USER;           // your Gmail address
    $mail->Password   = SMTP_PASS;           // Gmail App Password (no spaces)
    $mail->SMTPSecure = SMTP_SECURE;         // 'tls'
    $mail->Port       = SMTP_PORT;           // 587
    $mail->Timeout    = defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 15;
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPKeepAlive = false;

    // Fix SSL certificate verification issues on shared/local hosting
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($email, $first_name);
    $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);

    $mail->isHTML(true);
    $mail->Subject = "Your Verification Code - {$site_name}";
    $mail->Body    = $message_html;
    $mail->AltBody = "Hi {$first_name}, your verification code is: {$otp}. It expires in 10 minutes.";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => "A 6-digit verification code has been sent to {$email}. Please check your inbox (and spam folder).",
    ]);

} catch (\PHPMailer\PHPMailer\Exception $e) {
    // Log full SMTP conversation + exception for diagnosis
    error_log('[OTP Mailer] Exception: ' . $e->getMessage());
    if (!empty($smtpDebugLog)) {
        error_log('[OTP Mailer] SMTP log:' . "\n" . $smtpDebugLog);
    }

    // Return a helpful message; include a sanitized hint in debug environments
    $hint = '';
    $msg  = $e->getMessage();
    if (strpos($msg, '535') !== false || strpos($msg, 'Username and Password') !== false) {
        $hint = ' (SMTP auth failed — check your Gmail App Password in includes/config/email.php)';
    } elseif (strpos($msg, '534') !== false || strpos($msg, 'Application-specific') !== false) {
        $hint = ' (Gmail requires an App Password — enable 2-Step Verification and generate one at myaccount.google.com/apppasswords)';
    } elseif (strpos($msg, 'Could not connect') !== false || strpos($msg, 'Connection refused') !== false) {
        $hint = ' (Could not connect to smtp.gmail.com:587 — check that your host allows outbound SMTP on port 587)';
    } elseif (strpos($msg, 'SSL') !== false || strpos($msg, 'certificate') !== false) {
        $hint = ' (SSL/TLS error — check server SSL config)';
    }

    echo json_encode([
        'success' => false,
        'message' => 'Failed to send verification email. Please try again or contact support.' . $hint,
    ]);
}