<?php
declare(strict_types=1);

$smsLocal = [];
$smsLocalLoadedPath = '';
foreach ([__DIR__ . '/sms.local.php', __DIR__ . '/brevo.local.php'] as $smsLocalPath) {
    if (is_file($smsLocalPath)) {
        $smsLocal = require $smsLocalPath;
        $smsLocalLoadedPath = $smsLocalPath;
        break;
    }
}
if (!is_array($smsLocal)) {
    $smsLocal = [];
}

function sms_config(string $key, string $default = ''): string
{
    global $smsLocal;
    $envValue = getenv($key);
    if (is_string($envValue) && trim($envValue) !== '') {
        return trim($envValue);
    }
    if (isset($smsLocal[$key]) && is_scalar($smsLocal[$key]) && trim((string) $smsLocal[$key]) !== '') {
        return trim((string) $smsLocal[$key]);
    }
    return $default;
}

function brevo_config(string $key, string $default = ''): string
{
    return sms_config($key, $default);
}

function sms_local_config_loaded(): bool
{
    global $smsLocalLoadedPath;
    return $smsLocalLoadedPath !== '';
}

define('SMS_API_KEY', sms_config('SMS_API_KEY', sms_config('SENDINC_API_KEY', sms_config('SEND_API_KEY', sms_config('BREVO_API_KEY')))));
define('SMS_API_SECRET', sms_config('SMS_API_SECRET', sms_config('SENDINC_API_SECRET', sms_config('SEND_API_SECRET'))));
define('SMS_SENDER_ID', substr(preg_replace('/[^A-Za-z0-9]/', '', sms_config('SMS_SENDER_ID', sms_config('BREVO_SMS_SENDER', 'Deutsche Bank'))), 0, 11));
define('SMS_BASE_URL', rtrim(sms_config('SMS_BASE_URL', sms_config('SENDINC_BASE_URL', sms_config('SEND_BASE_URL', 'https://api.brevo.com/v3/transactionalSMS/sms'))), '/'));
define('SMS_PROVIDER', strtolower(sms_config('SMS_PROVIDER', 'brevo')));
define('TWILIO_ACCOUNT_SID', sms_config('TWILIO_ACCOUNT_SID'));
define('TWILIO_AUTH_TOKEN', sms_config('TWILIO_AUTH_TOKEN'));
define('TWILIO_API_KEY_SID', sms_config('TWILIO_API_KEY_SID'));
define('TWILIO_API_KEY_SECRET', sms_config('TWILIO_API_KEY_SECRET'));
define('TWILIO_FROM_NUMBER', sms_config('TWILIO_FROM_NUMBER'));
define('TWILIO_MESSAGING_SERVICE_SID', sms_config('TWILIO_MESSAGING_SERVICE_SID'));
define('BREVO_API_KEY', SMS_API_KEY);
define('SMS_FROM_NAME', sms_config('SMS_FROM_NAME', sms_config('BREVO_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'Deutsche Bank')));
define('BREVO_FROM_NAME', SMS_FROM_NAME);
define('BREVO_SMS_SENDER', SMS_SENDER_ID);
defined('OTP_ENABLED') || define('OTP_ENABLED', filter_var(sms_config('OTP_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN));
define('SMS_OTP_ENABLED', OTP_ENABLED && filter_var(sms_config('SMS_OTP_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN));
define('SMS_OTP_TTL_MINUTES', max(1, (int) sms_config('SMS_OTP_TTL_MINUTES', '5')));
$GLOBALS['sms_last_error'] = '';

function sms_set_last_error(string $message): void
{
    $GLOBALS['sms_last_error'] = $message;
}

function sms_last_error(): string
{
    return (string) ($GLOBALS['sms_last_error'] ?? '');
}

function brevo_sms_set_last_error(string $message): void
{
    sms_set_last_error($message);
}

function brevo_sms_last_error(): string
{
    return sms_last_error();
}

function normalize_sms_phone(string $phone): string
{
    $phone = preg_replace('/[\s().-]+/', '', trim($phone));
    if (!is_string($phone)) {
        return '';
    }
    return preg_match('/^\+[1-9]\d{7,14}$/', $phone) ? $phone : '';
}

function is_valid_sms_phone(string $phone): bool
{
    return normalize_sms_phone($phone) !== '';
}

function sms_is_configured(): bool
{
    if (!SMS_OTP_ENABLED) {
        return false;
    }
    if (SMS_PROVIDER === 'twilio') {
        $hasAuth = TWILIO_AUTH_TOKEN !== '' || (TWILIO_API_KEY_SID !== '' && TWILIO_API_KEY_SECRET !== '');
        $hasSender = TWILIO_FROM_NUMBER !== '' || TWILIO_MESSAGING_SERVICE_SID !== '';
        return TWILIO_ACCOUNT_SID !== '' && $hasAuth && $hasSender;
    }
    return SMS_API_KEY !== '' && SMS_SENDER_ID !== '' && SMS_BASE_URL !== '';
}

function brevo_sms_is_configured(): bool
{
    return sms_is_configured();
}

function sms_send_message(string $toPhone, string $message): bool
{
    $recipient = normalize_sms_phone($toPhone);
    if ($recipient === '') {
        sms_set_last_error('The saved phone number is not in international format. Use + country code, for example +2348012345678.');
        error_log(sms_last_error());
        return false;
    }
    if (!sms_is_configured()) {
        if (!sms_local_config_loaded()) {
            sms_set_last_error('SMS config file was not found. Rename config/sms.local.example.php to config/sms.local.php and keep your Twilio values there.');
        } elseif (SMS_PROVIDER === 'twilio') {
            sms_set_last_error('Twilio SMS is missing one of these values: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, or TWILIO_FROM_NUMBER.');
        } else {
            sms_set_last_error('SMS API is not configured. Set SMS_PROVIDER to twilio or add valid provider credentials.');
        }
        error_log(sms_last_error());
        return false;
    }
    if (!function_exists('curl_init')) {
        sms_set_last_error('PHP cURL is unavailable on this server. Enable the cURL extension in hosting PHP settings.');
        error_log(sms_last_error());
        return false;
    }

    if (SMS_PROVIDER === 'twilio') {
        return twilio_send_sms($recipient, $message);
    }

    $payload = [
        'sender' => SMS_SENDER_ID,
        'recipient' => $recipient,
        'phone' => $recipient,
        'to' => $recipient,
        'content' => $message,
        'message' => $message,
        'body' => $message,
        'type' => 'transactional',
    ];
    $headers = [
        'accept: application/json',
        'content-type: application/json',
        'authorization: Bearer ' . SMS_API_KEY,
        'api-key: ' . SMS_API_KEY,
    ];
    if (SMS_API_SECRET !== '') {
        $headers[] = 'x-api-secret: ' . SMS_API_SECRET;
    }

    $ch = curl_init(SMS_BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 12,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        $reason = $error ?: substr((string) $response, 0, 500);
        sms_set_last_error('SMS provider rejected the message. Check API URL, credentials, sender ID, credits, and phone country support.');
        error_log('SMS request failed: HTTP ' . $httpCode . ' ' . $reason);
        return false;
    }

    sms_set_last_error('');
    return true;
}

function twilio_send_sms(string $toPhone, string $message): bool
{
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
    $payload = [
        'To' => $toPhone,
        'Body' => $message,
    ];
    if (TWILIO_MESSAGING_SERVICE_SID !== '') {
        $payload['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
    } else {
        $payload['From'] = TWILIO_FROM_NUMBER;
    }

    $username = TWILIO_API_KEY_SID !== '' ? TWILIO_API_KEY_SID : TWILIO_ACCOUNT_SID;
    $password = TWILIO_API_KEY_SECRET !== '' ? TWILIO_API_KEY_SECRET : TWILIO_AUTH_TOKEN;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 12,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        $reason = $error ?: substr((string) $response, 0, 500);
        $twilioMessage = '';
        $twilioCode = '';
        $decoded = json_decode((string) $response, true);
        if (is_array($decoded)) {
            $twilioCode = isset($decoded['code']) ? (string) $decoded['code'] : '';
            $twilioMessage = isset($decoded['message']) ? trim((string) $decoded['message']) : '';
        }
        if ($twilioMessage !== '') {
            $twilioMessage = substr($twilioMessage, 0, 180);
            sms_set_last_error('Twilio error' . ($twilioCode !== '' ? ' ' . $twilioCode : '') . ': ' . $twilioMessage);
        } else {
            sms_set_last_error('Twilio rejected the SMS. Check credentials, sender number, trial restrictions, credits, and phone country support.');
        }
        error_log('Twilio SMS request failed: HTTP ' . $httpCode . ' ' . $reason);
        return false;
    }

    sms_set_last_error('');
    return true;
}

function brevo_send_sms(string $toPhone, string $message): bool
{
    return sms_send_message($toPhone, $message);
}

function sms_otp_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'local'), 0, 64);
}

function sms_otp_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device'), 0, 255);
}

function sms_otp_create(?int $userId, string $phone, string $purpose, ?int $ttlMinutes = null): array
{
    $phone = normalize_sms_phone($phone);
    $purpose = in_array($purpose, ['signup', 'login', 'transfer'], true) ? $purpose : 'login';
    $ttlMinutes = $ttlMinutes !== null ? max(1, $ttlMinutes) : SMS_OTP_TTL_MINUTES;
    if ($phone === '') {
        return ['ok' => false, 'error' => 'Enter a valid phone number with country code.'];
    }

    $ip = sms_otp_ip();
    $cooldown = db()->prepare('SELECT resend_available_at FROM otp_verifications WHERE phone=? AND purpose=? AND send_status="sent" AND verified_at IS NULL ORDER BY created_at DESC LIMIT 1');
    $cooldown->execute([$phone, $purpose]);
    $latest = $cooldown->fetch();
    if ($latest && strtotime((string) $latest['resend_available_at']) > time()) {
        return ['ok' => false, 'error' => 'Please wait before requesting another code.', 'retry_at' => $latest['resend_available_at']];
    }

    $rate = db()->prepare('SELECT COUNT(*) c FROM otp_verifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND (phone=? OR ip_address=? OR (user_id IS NOT NULL AND user_id=?))');
    $rate->execute([$phone, $ip, $userId ?? 0]);
    if ((int) ($rate->fetch()['c'] ?? 0) >= 5) {
        return ['ok' => false, 'error' => 'Too many verification codes were requested. Please try again later.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
    $resendAt = date('Y-m-d H:i:s', time() + 60);
    $hash = password_hash($code, PASSWORD_BCRYPT);

    db()->prepare('INSERT INTO otp_verifications (user_id, phone, purpose, otp_hash, expires_at, attempts, max_attempts, resend_available_at, ip_address, user_agent, send_status) VALUES (?, ?, ?, ?, ?, 0, 5, ?, ?, ?, "sent")')
        ->execute([$userId, $phone, $purpose, $hash, $expiresAt, $resendAt, $ip, sms_otp_user_agent()]);
    $otpId = (int) db()->lastInsertId();
    $message = BREVO_FROM_NAME . ": Your verification code is {$code}. Valid for {$ttlMinutes} minutes. Never share this code.";

    if (!sms_send_message($phone, $message)) {
        db()->prepare('UPDATE otp_verifications SET send_status="failed", last_error=? WHERE id=?')->execute([sms_last_error(), $otpId]);
        return ['ok' => false, 'error' => sms_last_error() ?: 'SMS could not be sent.'];
    }

    if (function_exists('banking_emit_event')) {
        banking_emit_event('otp.sent', ['purpose' => $purpose, 'phone_tail' => substr($phone, -4), 'system_detail' => 'SMS OTP sent. Code is never logged.'], ['type' => 'system', 'id' => null], $userId, 'otp_verification', $otpId);
    }
    return ['ok' => true, 'id' => $otpId, 'expires_at' => $expiresAt, 'resend_available_at' => $resendAt];
}

function sms_otp_verify(?int $userId, string $phone, string $purpose, string $code): array
{
    $phone = normalize_sms_phone($phone);
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'error' => 'Enter the 6-digit verification code.'];
    }

    $sql = 'SELECT * FROM otp_verifications WHERE phone=? AND purpose=? AND send_status="sent" AND verified_at IS NULL ORDER BY created_at DESC LIMIT 1';
    $params = [$phone, $purpose];
    if ($userId !== null) {
        $sql = 'SELECT * FROM otp_verifications WHERE user_id=? AND phone=? AND purpose=? AND send_status="sent" AND verified_at IS NULL ORDER BY created_at DESC LIMIT 1';
        $params = [$userId, $phone, $purpose];
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $otp = $stmt->fetch();
    if (!$otp) {
        return ['ok' => false, 'error' => 'Request a new verification code.'];
    }
    if (strtotime((string) $otp['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'This code has expired. Request a new one.'];
    }
    if ((int) $otp['attempts'] >= (int) $otp['max_attempts']) {
        return ['ok' => false, 'error' => 'Too many incorrect attempts. Request a new code.'];
    }
    if (!password_verify($code, (string) $otp['otp_hash'])) {
        db()->prepare('UPDATE otp_verifications SET attempts=attempts+1 WHERE id=?')->execute([$otp['id']]);
        if (function_exists('banking_emit_event')) {
            banking_emit_event('otp.failed_attempt', ['purpose' => $purpose, 'phone_tail' => substr($phone, -4)], ['type' => 'system', 'id' => null], $userId, 'otp_verification', (int) $otp['id']);
        }
        return ['ok' => false, 'error' => 'The verification code is incorrect.'];
    }

    db()->prepare('UPDATE otp_verifications SET verified_at=NOW() WHERE id=?')->execute([$otp['id']]);
    if (function_exists('banking_emit_event')) {
        banking_emit_event('otp.verified', ['purpose' => $purpose, 'phone_tail' => substr($phone, -4)], ['type' => 'system', 'id' => null], $userId, 'otp_verification', (int) $otp['id']);
    }
    return ['ok' => true, 'id' => (int) $otp['id']];
}

function generate_otp(int $userId, string $purpose = 'login', ?int $ttlMinutes = null): string
{
    $stmt = db()->prepare('SELECT phone FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $result = sms_otp_create($userId, (string) ($user['phone'] ?? ''), $purpose, $ttlMinutes);
    return $result['ok'] ? 'sent' : '';
}

function verify_otp(int $userId, string $code, string $purpose = 'login'): bool
{
    $stmt = db()->prepare('SELECT phone FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return sms_otp_verify($userId, (string) ($user['phone'] ?? ''), $purpose, $code)['ok'] ?? false;
}

function send_otp_sms(int $userId, string $purpose = 'login', ?int $ttlMinutes = null): bool
{
    $stmt = db()->prepare('SELECT phone FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $result = sms_otp_create($userId, (string) ($user['phone'] ?? ''), $purpose, $ttlMinutes);
    return (bool) ($result['ok'] ?? false);
}

function send_otp_email(int $userId, string $purpose = 'login', ?int $ttlMinutes = null): bool
{
    return false;
}

function send_user_otp(int $userId, string $purpose, ?int $ttlMinutes = null): bool
{
    return send_otp_sms($userId, $purpose, $ttlMinutes);
}

function brevo_send_email(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    return true;
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
<body style="margin:0;padding:0;background:#F7F9FD;font-family:Arial,sans-serif;color:#001E60;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F7F9FD;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;width:100%;border:1px solid #2F7DFF;">
        <tr><td style="background:#ffffff;padding:26px 40px;border-top:5px solid #2F7DFF;border-bottom:1px solid #2F7DFF;">
          <span style="color:#0052FF;font-size:22px;font-weight:bold;letter-spacing:0;">{$brand}</span>
        </td></tr>
        <tr><td style="padding:36px 40px;font-size:15px;line-height:1.7;color:#001E60;">
          <h2 style="color:#001E60;margin-top:0;">{$safeTitle}</h2>
          {$bodyHtml}
        </td></tr>
        <tr><td style="background:#F7F9FD;padding:20px 40px;font-size:12px;color:#001E60;border-top:1px solid #2F7DFF;">
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
