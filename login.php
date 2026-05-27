<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/brevo.php';
ensure_banking_schema();

$scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'login.php'));
$authRegion = $GLOBALS['authRegion'] ?? (str_contains($scriptName, '_us') ? 'us' : (str_contains($scriptName, '_ca') ? 'ca' : (str_contains($scriptName, '_uk') ? 'uk' : (str_contains($scriptName, '_ch') ? 'ch' : (str_contains($scriptName, '_de') ? 'de' : 'us')))));
$regionConfig = banking_region_config($authRegion);
$isUsPortal = $authRegion === 'us';
$isGermanPortal = false;
$pageLanguage = 'en';
$pageLoginUrl = $regionConfig['login'];
$pageRegisterUrl = $regionConfig['register'];
$GLOBALS['pageLanguage'] = $pageLanguage;
$GLOBALS['pageLoginUrl'] = $pageLoginUrl;
$GLOBALS['forcePageLanguage'] = true;
$GLOBALS['disableTranslate'] = true;
$loginErrors = [];
$oldLogin = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = null;
    $databaseOnline = true;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginErrors['email'] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $loginErrors['password'] = 'Enter your password.';
    }

    if (!$loginErrors) {
        try {
            $GLOBALS['DB_SILENT_FAILURE'] = true;
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            $databaseOnline = false;
        } finally {
            unset($GLOBALS['DB_SILENT_FAILURE']);
        }

        if (!$databaseOnline) {
            flash('danger', 'The database is offline. Please contact support or try again later.');
        } elseif ($user && (int) $user['failed_attempts'] >= 5 && strtotime((string) $user['locked_until']) > time()) {
            flash('danger', 'Account temporarily locked after failed attempts. Try again later.');
        } elseif ($user && password_verify($password, $user['password_hash']) && ($user['status'] ?? '') === 'disabled' && (int) ($user['email_verified'] ?? 0) === 0) {
            if (!SMS_OTP_ENABLED) {
                db()->prepare('UPDATE users SET status="active", email_verified=1 WHERE id=? AND status="disabled"')->execute([(int) $user['id']]);
                flash('success', 'SMS verification is temporarily disabled. Your account application is active for testing.');
                header('Location: ' . $pageLoginUrl);
                exit;
            }
            if (!is_valid_sms_phone((string) ($user['phone'] ?? ''))) {
                $loginErrors['email'] = 'A valid phone number is required for SMS verification.';
                flash('danger', 'This signup is not verified and needs a valid phone number. Contact support to update it.');
            } else {
                $sent = sms_otp_create((int) $user['id'], (string) $user['phone'], 'signup', 10);
                if (($sent['ok'] ?? false) || isset($sent['retry_at'])) {
                    $_SESSION['pending_signup_user_id'] = (int) $user['id'];
                    $_SESSION['pending_signup_login_url'] = $pageLoginUrl;
                    flash('info', 'Finish phone verification to activate this account application.');
                    header('Location: otp_verify.php?purpose=signup');
                    exit;
                }
                $loginErrors['password'] = 'We could not send the SMS code.';
                flash('danger', (string) ($sent['error'] ?? 'SMS verification could not start. Please try again.'));
            }
        } elseif ($user && password_verify($password, $user['password_hash']) && in_array($user['status'], ['active', 'frozen', 'suspended'], true)) {
            if (!SMS_OTP_ENABLED) {
                start_authenticated_session('user', (int) $user['id']);
                db()->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?')->execute([(int) $user['id']]);
                if (($user['status'] ?? 'active') !== 'active') {
                    notify_customer_event((int) $user['id'], 'account_restricted');
                }
                header('Location: dashboard.php');
                exit;
            }
            if (!is_valid_sms_phone((string) ($user['phone'] ?? ''))) {
                $loginErrors['email'] = 'A valid phone number is required for SMS sign in.';
                flash('danger', 'Your account needs a valid phone number before SMS verification can continue. Contact support to update it.');
            } else {
                $sent = sms_otp_create((int) $user['id'], (string) $user['phone'], 'login', 10);
                if (($sent['ok'] ?? false) || isset($sent['retry_at'])) {
                    $_SESSION['pending_login_user_id'] = (int) $user['id'];
                    $_SESSION['pending_login_return'] = $pageLoginUrl;
                    if (($user['status'] ?? 'active') !== 'active') {
                        notify_customer_event((int) $user['id'], 'account_restricted');
                    }
                    header('Location: otp_verify.php?purpose=login');
                    exit;
                }
                $loginErrors['password'] = 'We could not send the SMS code.';
                flash('danger', (string) ($sent['error'] ?? 'SMS verification could not start. Please try again.'));
            }
        } else {
            if ($user) {
                $attempts = (int) $user['failed_attempts'] + 1;
                $locked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                db()->prepare('UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?')->execute([$attempts, $locked, $user['id']]);
                if ($attempts >= 3) {
                    db()->prepare('INSERT INTO security_events (user_id, event_type, title, details, device, ip_address, severity) VALUES (?, "login_warning", "Unsuccessful sign-in attempts", "Multiple unsuccessful sign-in attempts were detected.", ?, ?, "warning")')
                        ->execute([$user['id'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device', $_SERVER['REMOTE_ADDR'] ?? 'local']);
                    notify_customer_event((int) $user['id'], 'security_alert', ['message' => 'Multiple unsuccessful sign-in attempts were detected.']);
                }
            }
            $loginErrors['email'] = 'Check the email address.';
            $loginErrors['password'] = 'Check the password for this account.';
            flash('danger', 'Invalid email, password, or unavailable account.');
        }
    } else {
        flash('danger', 'Please review the highlighted fields before continuing.');
    }
}

$pageTitle = match ($regionConfig['region']) {
    'us' => 'U.S. Online Banking Login',
    'ca' => 'Canada Online Banking Login',
    'uk' => 'UK Online Banking Login',
    'ch' => 'Swiss Online Banking Login',
    default => 'Germany Online Banking Login',
};
$prefillEmail = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL) ? strtolower((string) $_GET['email']) : '';
$loginRailTitle = match ($regionConfig['region']) {
    'us' => 'Modern access for checking, cards, ACH, Instant Pay, and wires.',
    'ca' => 'Modern access for chequing, cards, Interac, EFT, and wires.',
    'uk' => 'Modern access for current accounts, cards, Faster Payments, and CHAPS.',
    'ch' => 'Modern access for Swiss accounts, cards, SIC, QR-bills, and international transfers.',
    default => 'Modern access for German accounts, cards, SEPA, and transfers.',
};
$loginHeading = match ($regionConfig['region']) {
    'us' => 'U.S. online banking sign in',
    'ca' => 'Canadian online banking sign in',
    'uk' => 'UK online banking sign in',
    'ch' => 'Swiss online banking sign in',
    default => 'Germany online banking sign in',
};
$createAccountLabel = match ($regionConfig['region']) {
    'us' => 'Create U.S. account',
    'ca' => 'Create Canadian account',
    'uk' => 'Create UK account',
    'ch' => 'Create Swiss account',
    default => 'Create Germany account',
};
$loginFieldClass = static fn (string $name): string => isset($loginErrors[$name]) ? ' is-invalid' : '';
$loginFieldError = static function (string $name) use (&$loginErrors): string {
    return isset($loginErrors[$name]) ? '<div class="field-error">' . e($loginErrors[$name]) . '</div>' : '';
};
include __DIR__ . '/includes/public_header.php';
?>

<section class="auth-shell">
  <div class="auth-suite">
    <aside class="auth-panel">
      <?= brand_logo('light') ?>
      <div>
        <span class="eyebrow"><?= e($regionConfig['workspace']) ?></span>
        <h2><?= e($loginRailTitle) ?></h2>
        <p>Sign in with your profile credentials. Transactions use your 4-digit code and are reviewed by admin before completion.</p>
      </div>
      <div class="auth-assurance"><span><i class="fa-solid fa-key"></i> 4-digit code</span><span><i class="fa-solid fa-building-columns"></i> Protected dashboard</span><span><i class="fa-solid fa-user-shield"></i> Admin approval</span></div>
    </aside>
    <form class="auth-card" method="post" data-auth-validation novalidate>
      <?= csrf_field() ?>
      <div class="mb-4"><?= brand_logo('dark') ?></div>
      <span class="auth-kicker">Welcome back</span>
      <h1 class="h3 fw-bold"><?= e($loginHeading) ?></h1>
      <p class="muted">Access your accounts with secure online banking.</p>
      <label class="form-label">Email</label>
      <input name="email" type="email" inputmode="email" autocomplete="email" class="form-control<?= e($loginFieldClass('email')) ?> mb-3" value="<?= e((string) ($oldLogin['email'] ?? $prefillEmail)) ?>" required>
      <?= $loginFieldError('email') ?>
      <label class="form-label">Password</label>
      <div class="secure-input mb-3">
        <input name="password" type="password" class="form-control<?= e($loginFieldClass('password')) ?>" autocomplete="current-password" required>
        <button type="button" data-visibility-toggle aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
      </div>
      <?= $loginFieldError('password') ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <label class="small"><input type="checkbox" name="remember"> Remember me</label>
        <a class="small fw-bold" href="forgot_password.php">Forgot password?</a>
      </div>
      <button class="btn btn-gold w-100">Sign in securely</button>
      <p class="small muted mt-3 mb-0"><?= $regionConfig['language'] === 'de' ? 'Neu hier?' : 'New here?' ?> <a class="fw-bold" href="<?= e($pageRegisterUrl) ?>"><?= e($createAccountLabel) ?></a></p>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
