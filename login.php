<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'login.php'));
$authRegion = $GLOBALS['authRegion'] ?? (str_contains($scriptName, '_us') ? 'us' : (str_contains($scriptName, '_ca') ? 'ca' : (str_contains($scriptName, '_uk') ? 'uk' : (str_contains($scriptName, '_ch') ? 'ch' : (str_contains($scriptName, '_de') ? 'de' : 'us')))));
$regionConfig = banking_region_config($authRegion);
$isUsPortal = $authRegion === 'us';
$pageLanguage = $regionConfig['language'];
$pageLoginUrl = $regionConfig['login'];
$pageRegisterUrl = $regionConfig['register'];
$GLOBALS['pageLanguage'] = $pageLanguage;
$GLOBALS['pageLoginUrl'] = $pageLoginUrl;
$GLOBALS['forcePageLanguage'] = true;
$GLOBALS['disableTranslate'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = null;
    $databaseOnline = true;

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
    } elseif ($user && password_verify($password, $user['password_hash']) && in_array($user['status'], ['active', 'frozen', 'suspended'], true)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        db()->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?')->execute([$user['id']]);
        if (($user['status'] ?? 'active') !== 'active') {
            notify_customer_event((int) $user['id'], 'account_restricted');
        }
        header('Location: dashboard.php');
        exit;
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
        flash('danger', 'Invalid email, password, or unavailable account.');
    }
}

$pageTitle = match ($regionConfig['region']) {
    'us' => 'U.S. Online Banking Login',
    'ca' => 'Canada Online Banking Login',
    'uk' => 'UK Online Banking Login',
    'ch' => 'Swiss Online Banking Login',
    default => 'Deutscher Online-Banking-Login',
};
$prefillEmail = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL) ? strtolower((string) $_GET['email']) : '';
$loginRailTitle = match ($regionConfig['region']) {
    'us' => 'Modern access for checking, cards, ACH, Zelle, and wires.',
    'ca' => 'Modern access for chequing, cards, Interac, EFT, and wires.',
    'uk' => 'Modern access for current accounts, cards, Faster Payments, and CHAPS.',
    'ch' => 'Modern access for Swiss accounts, cards, SIC, QR-bills, and international transfers.',
    default => 'Moderner Zugang fuer Girokonto, Karten, SEPA und Ueberweisungen.',
};
$loginHeading = match ($regionConfig['region']) {
    'us' => 'U.S. online banking sign in',
    'ca' => 'Canadian online banking sign in',
    'uk' => 'UK online banking sign in',
    'ch' => 'Swiss online banking sign in',
    default => 'Deutsches Online-Banking anmelden',
};
$createAccountLabel = match ($regionConfig['region']) {
    'us' => 'Create U.S. account',
    'ca' => 'Create Canadian account',
    'uk' => 'Create UK account',
    'ch' => 'Create Swiss account',
    default => 'Deutsches Konto eroeffnen',
};
include __DIR__ . '/includes/public_header.php';
?>

<section class="auth-shell">
  <div class="auth-suite">
    <aside class="auth-panel">
      <?= lead_logo('light') ?>
      <div>
        <span class="eyebrow"><?= e($regionConfig['workspace']) ?></span>
        <h2><?= e($loginRailTitle) ?></h2>
        <p><?= $regionConfig['language'] === 'de' ? 'Melden Sie sich mit Ihren Zugangsdaten an. Transaktionen werden mit Ihrem 4-stelligen Code bestaetigt und durch den Admin geprueft.' : 'Sign in with your profile credentials. Transactions use your 4-digit code and are reviewed by admin before completion.' ?></p>
      </div>
      <div class="auth-assurance"><span><i class="fa-solid fa-key"></i> <?= $isUsPortal ? '4-digit code' : '4-stelliger Code' ?></span><span><i class="fa-solid fa-building-columns"></i> <?= $isUsPortal ? 'Protected dashboard' : 'Geschuetzter Arbeitsbereich' ?></span><span><i class="fa-solid fa-user-shield"></i> <?= $isUsPortal ? 'Admin approval' : 'Admin-Freigabe' ?></span></div>
    </aside>
    <form class="auth-card" method="post">
      <?= csrf_field() ?>
      <div class="mb-4"><?= lead_logo('dark') ?></div>
      <span class="auth-kicker"><?= $regionConfig['language'] === 'de' ? 'Willkommen zurueck' : 'Welcome back' ?></span>
      <h1 class="h3 fw-bold"><?= e($loginHeading) ?></h1>
      <p class="muted"><?= $regionConfig['language'] === 'de' ? 'Greifen Sie sicher auf Ihr deutsches Konto zu.' : 'Access your accounts with secure online banking.' ?></p>
      <label class="form-label"><?= $isUsPortal ? 'Email' : 'E-Mail' ?></label>
      <input name="email" type="text" inputmode="email" autocomplete="email" class="form-control mb-3" value="<?= e($prefillEmail) ?>" required>
      <label class="form-label"><?= $isUsPortal ? 'Password' : 'Passwort' ?></label>
      <div class="secure-input mb-3">
        <input name="password" type="password" class="form-control" required>
        <button type="button" data-visibility-toggle aria-label="<?= $isUsPortal ? 'Show password' : 'Passwort anzeigen' ?>"><i class="fa-solid fa-eye"></i></button>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <label class="small"><input type="checkbox" name="remember"> <?= $isUsPortal ? 'Remember me' : 'Angemeldet bleiben' ?></label>
        <a class="small fw-bold" href="forgot_password.php"><?= $isUsPortal ? 'Forgot password?' : 'Passwort vergessen?' ?></a>
      </div>
      <button class="btn btn-gold w-100"><?= $isUsPortal ? 'Sign in securely' : 'Sicher anmelden' ?></button>
      <p class="small muted mt-3 mb-0"><?= $regionConfig['language'] === 'de' ? 'Neu hier?' : 'New here?' ?> <a class="fw-bold" href="<?= e($pageRegisterUrl) ?>"><?= e($createAccountLabel) ?></a></p>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
