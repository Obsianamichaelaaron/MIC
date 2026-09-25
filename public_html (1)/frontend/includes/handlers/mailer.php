<?php
/**
 * includes/handlers/mailer.php
 * Fixed: uses fully-qualified class names (no top-level "use" statements),
 * short SMTP timeout, port 465 SSL for Hostinger compatibility.
 */

require_once __DIR__ . '/../config/email.php';

$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
$manualSrc        = __DIR__ . '/../vendor/PHPMailer/src/';

$_phpmailerAvailable = false;

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
    $_phpmailerAvailable = true;
} elseif (file_exists($manualSrc . 'PHPMailer.php')) {
    require_once $manualSrc . 'Exception.php';
    require_once $manualSrc . 'PHPMailer.php';
    require_once $manualSrc . 'SMTP.php';
    $_phpmailerAvailable = true;
}

if (!$_phpmailerAvailable) {
    error_log('[MultiBiz Mailer] PHPMailer not found. Run: composer require phpmailer/phpmailer');
    function sendContactReply($toEmail, $toName, $subject, $bodyText, $originalMessage = '') {
        return ['success' => false, 'error' => 'Mailer library not installed.'];
    }
    return;
}

function sendContactReply(string $toEmail, string $toName, string $subject, string $bodyText, string $originalMessage = ''): array
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 15;
        $mail->CharSet    = 'UTF-8';

        // Disable SMTP keep-alive so connection doesn't hang
        $mail->SMTPKeepAlive = false;

        // Fix SSL certificate issues on shared/local hosting environments
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = buildEmailTemplate($toName, $bodyText, $originalMessage);
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $bodyText));

        $mail->send();
        return ['success' => true, 'error' => null];

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[MultiBiz Mailer] Exception: ' . $e->getMessage());
        error_log('[MultiBiz Mailer] ErrorInfo: ' . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

function buildEmailTemplate(string $recipientName, string $replyText, string $originalMessage = ''): string
{
    $originalBlock = '';
    if (!empty(trim($originalMessage))) {
        $safeOriginal  = nl2br(htmlspecialchars($originalMessage, ENT_QUOTES, 'UTF-8'));
        $originalBlock = "
        <div style='margin-top:28px;padding:16px 20px;background:#f4f6f9;
                    border-left:4px solid #d4af55;border-radius:6px;font-size:13px;color:#555;'>
            <p style='margin:0 0 6px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;font-size:11px;'>
                Your original message
            </p>
            <p style='margin:0;line-height:1.6;'>{$safeOriginal}</p>
        </div>";
    }

    $safeReply = nl2br(htmlspecialchars($replyText, ENT_QUOTES, 'UTF-8'));
    $year      = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:12px;overflow:hidden;
                    box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:600px;width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0a1628 0%,#1a2d4e 100%);padding:28px 36px;text-align:center;">
            <h1 style="margin:0;color:#d4af55;font-size:22px;font-weight:700;letter-spacing:1px;">MultiBiz Global</h1>
            <p style="margin:6px 0 0;color:rgba(255,255,255,.7);font-size:13px;">Connecting Talent with Opportunity</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px;">
            <p style="margin:0 0 16px;font-size:15px;color:#333;">Hello, <strong>{$recipientName}</strong>,</p>
            <p style="margin:0 0 20px;font-size:15px;color:#333;line-height:1.7;">Thank you for reaching out. Here is our response to your inquiry:</p>
            <div style="background:#f8f9fc;border-radius:8px;padding:20px 24px;border:1px solid #e8eaf0;font-size:15px;color:#333;line-height:1.8;">
              {$safeReply}
            </div>
            {$originalBlock}
            <p style="margin:28px 0 0;font-size:13px;color:#666;">
              If you have further questions, simply reply to this e-mail or visit us at
              <a href="https://multibiz.global" style="color:#0056b3;">multibiz.global</a>.
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f8f9fc;padding:20px 36px;text-align:center;border-top:1px solid #e8eaf0;">
            <p style="margin:0;font-size:12px;color:#999;">
              &copy; {$year} MultiBiz Global &middot; inquiry@multibiz.global &middot; +63 917 544 1674
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}