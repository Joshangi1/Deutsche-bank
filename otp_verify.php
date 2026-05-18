<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/brevo.php';
ensure_banking_schema();

$purpose = (string) ($_GET['purpose'] ?? $_POST['purpose'] ?? '');
$purpose = in_array($purpose, ['signup', 'login', 'transfer'], true) ? $purpose : '';
$otpErrors = [];
$otpInfo = '';
$phone = '';
$userId = null;
$returnUrl = 'login.php';
$title = 'Verify your phone';
$subtitle = 'Enter the SMS code we sent to your registered phone number.';

if ($purpose === 'login') {
    $userId = (int) ($_SESSION['pending_login_user_id'] ?? 0);
    $stmt = db()->prepare('SELECT id, phone, status FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $pendingUser = $stmt->fetch();
    if (!$pendingUser) {
        flash('danger', 'Start sign in again to request a verification code.');
        header('Location: ' . (string) ($_SESSION['pending_login_return'] ?? 'login.php'));
        exit;
    }
    $phone = (string) $pendingUser['phone'];
    $returnUrl = (string) ($_SESSION['pending_login_return'] ?? 'login.php');
    $title = 'Confirm sign in';
} elseif ($purpose === 'signup') {
    $userId = (int) ($_SESSION['pending_signup_user_id'] ?? 0);
    $stmt = db()->prepare('SELECT id, phone, email, status FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $pendingUser = $stmt->fetch();
    if (!$pendingUser || ($pendingUser['status'] ?? '') !== 'disabled') {
        flash('danger', 'Start registration again to verify your phone.');
        header('Location: register.php');
        exit;
    }
    $phone = (string) $pendingUser['phone'];
    $returnUrl = (string) ($_SESSION['pending_signup_login_url'] ?? 'login.php');
    $title = 'Verify your signup';
    $subtitle = 'Enter the SMS code to activate your account application.';
} elseif ($purpose === 'transfer') {
    $user = require_user();
    $userId = (int) $user['id'];
    $phone = (string) ($user['phone'] ?? '');
    $returnUrl = (string) ($_SESSION['pending_transfer_return'] ?? 'user/transfers.php?review=1');
    $title = 'Verify transfer';
    $subtitle = 'Enter the SMS code before this transfer can be submitted.';
} else {
    flash('danger', 'Unknown verification request.');
    header('Location: login.php');
    exit;
}

if (!is_valid_sms_phone($phone)) {
    flash('danger', 'A valid phone number with country code is required before SMS verification can continue.');
    header('Location: ' . $returnUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['resend_code'])) {
        $sent = sms_otp_create($userId ?: null, $phone, $purpose, 10);
        if ($sent['ok'] ?? false) {
            flash('success', 'A new verification code was sent.');
        } else {
            $otpErrors['code'] = (string) ($sent['error'] ?? 'The code could not be sent.');
        }
    } else {
        $result = sms_otp_verify($userId ?: null, $phone, $purpose, (string) ($_POST['otp_code'] ?? ''));
        if ($result['ok'] ?? false) {
            if ($purpose === 'login') {
                start_authenticated_session('user', $userId);
                db()->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?')->execute([$userId]);
                unset($_SESSION['pending_login_user_id'], $_SESSION['pending_login_return']);
                header('Location: dashboard.php');
                exit;
            }
            if ($purpose === 'signup') {
                db()->prepare('UPDATE users SET status="active", email_verified=1 WHERE id=? AND status="disabled"')->execute([$userId]);
                $loginUrl = (string) ($_SESSION['pending_signup_login_url'] ?? 'login.php');
                unset($_SESSION['pending_signup_user_id'], $_SESSION['pending_signup_login_url']);
                flash('success', 'Phone verified. Your account application is active and awaiting review.');
                header('Location: ' . $loginUrl);
                exit;
            }
            $_SESSION['transfer_otp_verified_at'] = time();
            $_SESSION['transfer_otp_verified_context'] = (string) ($_SESSION['pending_transfer_context'] ?? '');
            unset($_SESSION['pending_transfer_context'], $_SESSION['pending_transfer_return']);
            flash('success', 'SMS code verified. Confirm the transfer to submit it.');
            header('Location: ' . $returnUrl);
            exit;
        }
        $otpErrors['code'] = (string) ($result['error'] ?? 'The verification code is incorrect.');
    }
}

$resendAt = null;
$stmt = db()->prepare('SELECT resend_available_at FROM otp_verifications WHERE phone=? AND purpose=? AND verified_at IS NULL ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$phone, $purpose]);
$latestOtp = $stmt->fetch();
if ($latestOtp) {
    $resendAt = (string) $latestOtp['resend_available_at'];
}

$maskedPhone = preg_replace('/\d(?=\d{4})/', '*', $phone);
$pageTitle = $title;
$GLOBALS['disableTranslate'] = true;
include __DIR__ . '/includes/public_header.php';
?>
<section class="auth-shell">
  <form class="auth-card otp-verify-card" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="purpose" value="<?= e($purpose) ?>">
    <div class="mb-4"><?= lead_logo('dark') ?></div>
    <span class="auth-kicker">SMS verification</span>
    <h1 class="h3 fw-bold"><?= e($title) ?></h1>
    <p class="muted"><?= e($subtitle) ?> <strong><?= e($maskedPhone ?: $phone) ?></strong></p>
    <label class="form-label" for="otp_code">6-digit SMS code</label>
    <input id="otp_code" name="otp_code" class="form-control<?= isset($otpErrors['code']) ? ' is-invalid' : '' ?>" inputmode="numeric" minlength="6" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required autofocus>
    <?php if (isset($otpErrors['code'])): ?><div class="field-error"><?= e($otpErrors['code']) ?></div><?php endif; ?>
    <button class="btn btn-gold w-100 mt-3">Verify code</button>
    <div class="otp-resend-row">
      <button class="btn btn-link p-0" name="resend_code" value="1" type="submit" data-resend-button formnovalidate>Resend code</button>
      <?php if ($resendAt): ?><span class="small muted" data-code-timer="<?= e((string) max(0, strtotime($resendAt) - time())) ?>">Wait before resending</span><?php endif; ?>
    </div>
    <p class="small muted mb-0">Codes expire after 10 minutes. Never share this code with anyone.</p>
  </form>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
