<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once '../includes/config/session.php';
require_once '../includes/config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$conn   = getDBConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function respond(array $data): void {
    ob_end_clean();
    echo json_encode($data);
    exit();
}

function intParam(string $key, int $default = 0): int {
    return (int)($_POST[$key] ?? $_GET[$key] ?? $default);
}

// ─────────────────────────────────────────────────────────────────────────────
// Built-in SMTP mailer — no PHPMailer / Composer needed
// Gmail SMTP with App Password over STARTTLS (port 587)
// Credentials are loaded from includes/config/email.php (one place to update)
// ─────────────────────────────────────────────────────────────────────────────
require_once '../includes/config/email.php';

function sendReplyEmail(string $toEmail, string $toName, string $subject, string $replyText, string $originalMessage = ''): array
{
    $smtpHost  = SMTP_HOST;       // smtp.gmail.com
    $smtpPort  = SMTP_PORT;       // 587
    $smtpUser  = SMTP_USER;       // Gmail address
    $smtpPass  = SMTP_PASS;       // Gmail App Password (no spaces)
    $fromName  = MAIL_FROM_NAME;  // MultiBiz Global
    $fromEmail = MAIL_FROM;       // Gmail address

    $htmlBody  = _buildReplyBody($toName, $replyText, $originalMessage);
    $plainBody = "Hello {$toName},\r\n\r\nThank you for reaching out.\r\n\r\n{$replyText}\r\n\r\n-- MultiBiz Global";

    $boundary       = '==MB_' . md5(uniqid('', true));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedTo      = '=?UTF-8?B?' . base64_encode($toName) . '?=';

    $headers  = "From: {$encodedFrom} <{$fromEmail}>\r\n";
    $headers .= "To: {$encodedTo} <{$toEmail}>\r\n";
    $headers .= "Subject: {$encodedSubject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: MultiBiz-PHP\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plainBody)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $fullMessage = $headers . "\r\n" . $body;

    $errno = 0; $errstr = '';
    $sock = @fsockopen('tcp://' . $smtpHost, $smtpPort, $errno, $errstr, 15);
    if (!$sock) {
        return ['success' => false, 'error' => "Cannot connect to SMTP: {$errstr} ({$errno})"];
    }

    $read = function() use ($sock) {
        $data = '';
        while ($line = fgets($sock, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $data;
    };

    $send = function(string $cmd) use ($sock) { fputs($sock, $cmd . "\r\n"); };

    $resp = $read();
    if (substr($resp, 0, 3) !== '220') { fclose($sock); return ['success' => false, 'error' => "SMTP greeting: {$resp}"]; }

    $send("EHLO multibiz.global"); $read();

    $send("STARTTLS");
    $resp = $read();
    if (substr($resp, 0, 3) !== '220') { fclose($sock); return ['success' => false, 'error' => "STARTTLS: {$resp}"]; }

    if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        // Retry with relaxed SSL options for shared/local hosting environments
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);
        stream_context_set_option($sock, 'ssl', 'verify_peer',       false);
        stream_context_set_option($sock, 'ssl', 'verify_peer_name',  false);
        stream_context_set_option($sock, 'ssl', 'allow_self_signed', true);
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock);
            return ['success' => false, 'error' => 'TLS handshake failed — check server SSL config'];
        }
    }

    $send("EHLO multibiz.global"); $read();

    $send("AUTH LOGIN");
    $resp = $read();
    if (substr($resp, 0, 3) !== '334') { fclose($sock); return ['success' => false, 'error' => "AUTH LOGIN: {$resp}"]; }

    $send(base64_encode($smtpUser));
    $resp = $read();
    if (substr($resp, 0, 3) !== '334') { fclose($sock); return ['success' => false, 'error' => "Username rejected: {$resp}"]; }

    $send(base64_encode($smtpPass));
    $resp = $read();
    if (substr($resp, 0, 3) !== '235') { fclose($sock); return ['success' => false, 'error' => "Auth failed - check App Password: {$resp}"]; }

    $send("MAIL FROM:<{$fromEmail}>");
    $resp = $read();
    if (substr($resp, 0, 3) !== '250') { fclose($sock); return ['success' => false, 'error' => "MAIL FROM: {$resp}"]; }

    $send("RCPT TO:<{$toEmail}>");
    $resp = $read();
    if (substr($resp, 0, 3) !== '250') { fclose($sock); return ['success' => false, 'error' => "RCPT TO: {$resp}"]; }

    $send("DATA");
    $resp = $read();
    if (substr($resp, 0, 3) !== '354') { fclose($sock); return ['success' => false, 'error' => "DATA: {$resp}"]; }

    $send($fullMessage . "\r\n.");
    $resp = $read();
    if (substr($resp, 0, 3) !== '250') { fclose($sock); return ['success' => false, 'error' => "Message rejected: {$resp}"]; }

    $send("QUIT");
    fclose($sock);
    return ['success' => true, 'error' => null];
}

function _buildReplyBody(string $name, string $reply, string $original = ''): string
{
    $safeReply = nl2br(htmlspecialchars($reply,   ENT_QUOTES, 'UTF-8'));
    $safeName  = htmlspecialchars($name,          ENT_QUOTES, 'UTF-8');
    $origBlock = '';
    if (!empty(trim($original))) {
        $safeOrig  = nl2br(htmlspecialchars($original, ENT_QUOTES, 'UTF-8'));
        $origBlock = "<div style='margin-top:24px;padding:14px 18px;background:#f4f6f9;border-left:4px solid #d4af55;border-radius:6px;font-size:13px;color:#555;'><p style='margin:0 0 6px;font-weight:600;color:#888;font-size:11px;text-transform:uppercase;'>Your original message</p><p style='margin:0;line-height:1.6;'>{$safeOrig}</p></div>";
    }
    $year = date('Y');
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'><table width='100%' cellpadding='0' cellspacing='0' style='background:#f0f2f5;padding:32px 0;'><tr><td align='center'><table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;max-width:600px;width:100%;'><tr><td style='background:linear-gradient(135deg,#0a1628,#1a2d4e);padding:28px 36px;text-align:center;'><h1 style='margin:0;color:#d4af55;font-size:22px;font-weight:700;'>MultiBiz Global</h1><p style='margin:6px 0 0;color:rgba(255,255,255,.7);font-size:13px;'>Connecting Talent with Opportunity</p></td></tr><tr><td style='padding:36px;'><p style='margin:0 0 16px;font-size:15px;color:#333;'>Hello, <strong>{$safeName}</strong>,</p><p style='margin:0 0 20px;font-size:15px;color:#333;line-height:1.7;'>Thank you for reaching out. Here is our response:</p><div style='background:#f8f9fc;border-radius:8px;padding:20px 24px;border:1px solid #e8eaf0;font-size:15px;color:#333;line-height:1.8;'>{$safeReply}</div>{$origBlock}<p style='margin:28px 0 0;font-size:13px;color:#666;'>If you have further questions reply to this email or visit <a href='https://multibiz.global' style='color:#0056b3;'>multibiz.global</a>.</p></td></tr><tr><td style='background:#f8f9fc;padding:20px 36px;text-align:center;border-top:1px solid #e8eaf0;'><p style='margin:0;font-size:12px;color:#999;'>&copy; {$year} MultiBiz Global</p></td></tr></table></td></tr></table></body></html>";
}

// ─────────────────────────────────────────────────────────────────────────────
// API Actions
// ─────────────────────────────────────────────────────────────────────────────
switch ($action) {

    case 'list_inquiries': {
        $filter = $_GET['filter'] ?? $_POST['filter'] ?? 'all';
        $page   = max(1, intParam('page', 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where = '';
        if ($filter === 'new')         $where = "WHERE status = 'new'";
        elseif ($filter === 'unread')  $where = "WHERE is_read = 0";
        elseif ($filter === 'replied') $where = "WHERE status = 'replied'";

        $totalRes = $conn->query("SELECT COUNT(*) AS c FROM contact_inquiries $where");
        if (!$totalRes) respond(['success' => false, 'error' => 'Table not found. Run the DB migration first.']);
        $total = (int)$totalRes->fetch_assoc()['c'];

        $rowsRes = $conn->query(
            "SELECT id, name, email, subject, LEFT(message,120) AS excerpt,
                    is_read, status, created_at
             FROM contact_inquiries
             $where
             ORDER BY created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $rows = $rowsRes ? $rowsRes->fetch_all(MYSQLI_ASSOC) : [];
        respond(['success' => true, 'total' => $total, 'page' => $page, 'limit' => $limit, 'rows' => $rows]);
    }

    case 'get_inquiry': {
        $id = intParam('id');
        if (!$id) respond(['success' => false, 'error' => 'Missing id']);

        $stmt = $conn->prepare("SELECT * FROM contact_inquiries WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $inquiry = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$inquiry) respond(['success' => false, 'error' => 'Not found']);

        $stmt2 = $conn->prepare(
            "SELECT cr.*, CONCAT(u.first_name,' ',u.last_name) AS admin_name
             FROM contact_replies cr
             LEFT JOIN users u ON cr.admin_id = u.user_id
             WHERE cr.inquiry_id = ? ORDER BY cr.sent_at ASC"
        );
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $replies = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();
        respond(['success' => true, 'inquiry' => $inquiry, 'replies' => $replies]);
    }

    case 'mark_read': {
        $id = intParam('id');
        if (!$id) respond(['success' => false, 'error' => 'Missing id']);
        $stmt = $conn->prepare("UPDATE contact_inquiries SET is_read = 1, status = IF(status='new','open',status) WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        respond(['success' => $ok]);
    }

    case 'mark_unread': {
        $id = intParam('id');
        if (!$id) respond(['success' => false, 'error' => 'Missing id']);
        $stmt = $conn->prepare("UPDATE contact_inquiries SET is_read = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        respond(['success' => $ok]);
    }

    case 'delete_inquiry': {
        $id = intParam('id');
        if (!$id) respond(['success' => false, 'error' => 'Missing id']);
        $stmt = $conn->prepare("DELETE FROM contact_inquiries WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        respond(['success' => $ok]);
    }

    case 'send_reply': {
        $id        = intParam('id');
        $replyText = trim($_POST['reply_text'] ?? '');
        $adminId   = getCurrentUserId();

        if (!$id)        respond(['success' => false, 'error' => 'Missing id']);
        if (!$replyText) respond(['success' => false, 'error' => 'Reply text is required']);

        $stmt = $conn->prepare("SELECT * FROM contact_inquiries WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $inquiry = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$inquiry) respond(['success' => false, 'error' => 'Inquiry not found']);

        $result    = sendReplyEmail(
            $inquiry['email'],
            $inquiry['name'],
            'Re: ' . $inquiry['subject'],
            $replyText,
            $inquiry['message']
        );
        $emailSent = $result['success'] ? 1 : 0;

        $stmt2 = $conn->prepare(
            "INSERT INTO contact_replies (inquiry_id, admin_id, reply_text, email_sent) VALUES (?, ?, ?, ?)"
        );
        $stmt2->bind_param('iisi', $id, $adminId, $replyText, $emailSent);
        $saved = $stmt2->execute();
        $stmt2->close();

        if ($saved) {
            $stmt3 = $conn->prepare("UPDATE contact_inquiries SET status = 'replied', is_read = 1 WHERE id = ?");
            $stmt3->bind_param('i', $id);
            $stmt3->execute();
            $stmt3->close();
        }

        respond(['success' => $saved, 'email_sent' => $emailSent, 'email_error' => $result['error'] ?? null]);
    }

    case 'get_unread_count': {
        $res   = $conn->query("SELECT COUNT(*) AS c FROM contact_inquiries WHERE is_read = 0");
        $count = $res ? (int)$res->fetch_assoc()['c'] : 0;
        respond(['success' => true, 'count' => $count]);
    }

    default:
        respond(['success' => false, 'error' => 'Unknown action']);
}

$conn->close();