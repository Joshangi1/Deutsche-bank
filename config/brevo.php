<?php
declare(strict_types=1);

$brevoLocal = is_file(__DIR__ . '/brevo.local.php') ? require __DIR__ . '/brevo.local.php' : [];
if (!is_array($brevoLocal)) {
    $brevoLocal = [];
}

function brevo_config(string $key, string $default = ''): string
{
    global $brevoLocal;
    $envValue = getenv($key);
    if (is_string($envValue) && trim($envValue) !== '') {
        return trim($envValue);
    }
    if (isset($brevoLocal[$key]) && is_scalar($brevoLocal[$key]) && trim((string) $brevoLocal[$key]) !== '') {
        return trim((string) $brevoLocal[$key]);
    }
    return $default;
}

define('BREVO_API_KEY', brevo_config('BREVO_API_KEY'));
define('BREVO_FROM_NAME', brevo_config('BREVO_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'Deutsche'));
define('BREVO_SMS_SENDER', substr(preg_replace('/[^A-Za-z0-9]/', '', brevo_config('BREVO_SMS_SENDER', 'Deutsche')), 0, 11));
$GLOBALS['brevo_sms_last_error'] = '';

function brevo_sms_set_last_error(string $message): void
{
    $GLOBALS['brevo_sms_last_error'] = $message;
}

function brevo_sms_last_error(): string
{
    return (string) ($GLOBALS['brevo_sms_last_error'] ?? '');
}

function brevo_sms_is_configured(): bool
{
    return BREVO_API_KEY !== '' && BREVO_SMS_SENDER !== '';
}

function normalize_sms_phone(string $phone): string
{
    $phone = trim($phone);
    $phone = preg_replace('/[\s().-]+/', '', $phone);
    if (!is_string($phone)) {
        return '';
    }
    return preg_match('/^\+[1-9]\d{7,14}$/', $phone) ? $phone : '';
}

function is_valid_sms_phone(string $phone): bool
{
    return normalize_sms_phone($phone) !== '';
}

function brevo_sms_post(string $url, array $payload): bool
{
    if (!brevo_sms_is_configured() || !function_exists('curl_init')) {
        brevo_sms_set_last_error('Brevo SMS is not configured or cURL is unavailable.');
        error_log(brevo_sms_last_error());
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 12,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        $reason = $error ?: (string) $response;
        brevo_sms_set_last_error('Brevo rejected the SMS request. Check SMS credits, country support, sender name, and the API key.');
        error_log('Brevo request failed: HTTP ' . $httpCode . ' ' . $reason);
        return false;
    }

    return true;
}

function brevo_send_email(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    return true;
}

function brevo_send_sms(string $toPhone, string $message): bool
{
    $recipient = normalize_sms_phone($toPhone);
    if ($recipient === '') {
        brevo_sms_set_last_error('The saved phone number is not in international format. Use + country code, for example +2348012345678.');
        error_log(brevo_sms_last_error());
        return false;
    }

    return brevo_sms_post('https://api.brevo.com/v3/transactionalSMS/sms', [
        'sender' => BREVO_SMS_SENDER,
        'recipient' => $recipient,
        'content' => $message,
        'type' => 'transactional',
    ]);
}

function generate_otp(int $userId, string $purpose = 'login', int $ttlMinutes = 10): string
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_BCRYPT);
    $expires = date('Y-m-d H:i:s', time() + $ttlMinutes * 60);

    db()->prepare('DELETE FROM otp_codes WHERE user_id = ? AND purpose = ?')->execute([$userId, $purpose]);
    db()->prepare('INSERT INTO otp_codes (user_id, code_hash, purpose, expires_at) VALUES (?, ?, ?, ?)')->execute([$userId, $hash, $purpose, $expires]);

    return $code;
}

function verify_otp(int $userId, string $code, string $purpose = 'login'): bool
{
    $stmt = db()->prepare('SELECT * FROM otp_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$userId, $purpose]);
    $otp = $stmt->fetch();

    if (!$otp || !password_verify($code, $otp['code_hash'])) {
        return false;
    }

    db()->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = ?')->execute([$otp['id']]);
    return true;
}

function send_otp_sms(int $userId, string $purpose = 'login', int $ttlMinutes = 10): bool
{
    $stmt = db()->prepare('SELECT phone FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !is_valid_sms_phone((string) ($user['phone'] ?? ''))) {
        return false;
    }

    $code = generate_otp($userId, $purpose, $ttlMinutes);
    $message = BREVO_FROM_NAME . ": Your verification code is {$code}. Valid for {$ttlMinutes} minutes. Never share this code.";

    return brevo_send_sms((string) $user['phone'], $message);
}

function send_otp_email(int $userId, string $purpose = 'login', int $ttlMinutes = 10): bool
{
    return false;
}

function send_user_otp(int $userId, string $purpose, int $ttlMinutes = 10): bool
{
    return send_otp_sms($userId, $purpose, $ttlMinutes);
}

function send_password_reset_email(string $email): bool
{
    return true;
}

function send_welcome_email(int $userId): bool
{
    return true;
}

function send_security_alert_email(int $userId, string $alertMessage): bool
{
    return true;
}

function send_kyc_status_email(int $userId, string $status): bool
{
    return true;
}

function email_template(string $title, string $bodyHtml): string
{
    $brand = e(BREVO_FROM_NAME);
    $safeTitle = e($title);
    $year = date('Y');

    return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f2f3f5;font-family:Arial,sans-serif;color:#1a1a1a;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f2f3f5;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;border:1px solid #e4e6ea;">
        <tr><td style="background:#ffffff;padding:26px 40px;border-top:5px solid #0018a8;border-bottom:1px solid #eceef2;">
          <span style="color:#111827;font-size:22px;font-weight:bold;letter-spacing:0;">{$brand}</span>
        </td></tr>
        <tr><td style="padding:36px 40px;font-size:15px;line-height:1.7;color:#333;">
          <h2 style="color:#111827;margin-top:0;">{$safeTitle}</h2>
          {$bodyHtml}
        </td></tr>
        <tr><td style="background:#f8f9fa;padding:20px 40px;font-size:12px;color:#777;border-top:1px solid #e9ecef;">
          <p style="margin:0;">This message was sent by {$brand}. Please do not reply to this email.</p>
          <p style="margin:6px 0 0;">&copy; {$year} {$brand}. All rights reserved.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
