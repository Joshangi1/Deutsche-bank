<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'login.php'));
$authRegion = $GLOBALS['authRegion'] ?? (str_contains($scriptName, '_us') ? 'us' : (str_contains($scriptName, '_de') ? 'de' : 'us'));
$isUsPortal = $authRegion === 'us';
$pageLanguage = $isUsPortal ? 'en' : 'de';
$pageLoginUrl = $isUsPortal ? 'login_us.php' : 'login_de.php';
$pageRegisterUrl = $isUsPortal ? 'register_us.php' : 'register_de.php';
$GLOBALS['pageLanguage'] = $pageLanguage;
$GLOBALS['pageLoginUrl'] = $pageLoginUrl;
$GLOBALS['forcePageLanguage'] = true;
$GLOBALS['disableTranslate'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $demoEmail = $isUsPortal ? 'demo.us@deutsche.local' : 'demo.de@deutsche.local';
    $demoPassword = 'Deutsche123!';
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

    if (!$databaseOnline && hash_equals($email, $demoEmail) && hash_equals($password, $demoPassword)) {
        session_regenerate_id(true);
        unset($_SESSION['user_id']);
        $_SESSION['offline_demo_user'] = [
            'region' => $isUsPortal ? 'us' : 'de',
            'first_name' => $isUsPortal ? 'Lincoln' : 'Lukas',
            'last_name' => $isUsPortal ? 'Martin' : 'Weber',
            'email' => $demoEmail,
        ];
        header('Location: dashboard.php');
        exit;
    } elseif (!$databaseOnline) {
        flash('danger', 'The database is offline. Use the demo login below to preview the dashboard.');
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

$pageTitle = $isUsPortal ? 'U.S. Online Banking Login' : 'Deutscher Online-Banking-Login';
$prefillEmail = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL) ? strtolower((string) $_GET['email']) : '';
include __DIR__ . '/includes/public_header.php';
?>

<section class="auth-shell">
  <div class="auth-suite">
    <aside class="auth-panel">
      <?= lead_logo('light') ?>
      <div>
        <span class="eyebrow"><?= $isUsPortal ? 'U.S. private banking portal' : 'Deutsches Online-Banking' ?></span>
        <h2><?= $isUsPortal ? 'Modern access for checking, cards, ACH, Zelle, and wires.' : 'Moderner Zugang fuer Girokonto, Karten, SEPA und Ueberweisungen.' ?></h2>
        <p><?= $isUsPortal ? 'Sign in with your profile credentials. Transactions use your 4-digit transaction code and are reviewed by admin before completion.' : 'Melden Sie sich mit Ihren Zugangsdaten an. Transaktionen werden mit Ihrem 4-stelligen Code bestaetigt und durch den Admin geprueft.' ?></p>
      </div>
      <div class="auth-assurance"><span><i class="fa-solid fa-key"></i> <?= $isUsPortal ? '4-digit code' : '4-stelliger Code' ?></span><span><i class="fa-solid fa-building-columns"></i> <?= $isUsPortal ? 'Protected dashboard' : 'Geschuetzter Arbeitsbereich' ?></span><span><i class="fa-solid fa-user-shield"></i> <?= $isUsPortal ? 'Admin approval' : 'Admin-Freigabe' ?></span></div>
    </aside>
    <form class="auth-card" method="post">
      <?= csrf_field() ?>
      <div class="mb-4"><?= lead_logo('dark') ?></div>
      <span class="auth-kicker"><?= $isUsPortal ? 'Welcome back' : 'Willkommen zurueck' ?></span>
      <h1 class="h3 fw-bold"><?= $isUsPortal ? 'U.S. online banking sign in' : 'Deutsches Online-Banking anmelden' ?></h1>
      <p class="muted"><?= $isUsPortal ? 'Access your U.S. accounts with secure online banking.' : 'Greifen Sie sicher auf Ihr deutsches Konto zu.' ?></p>
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
      <p class="small muted mt-3 mb-0"><?= $isUsPortal ? 'New here?' : 'Neu hier?' ?> <a class="fw-bold" href="<?= e($pageRegisterUrl) ?>"><?= $isUsPortal ? 'Create U.S. account' : 'Deutsches Konto eroeffnen' ?></a></p>
      <div class="demo-login-note mt-3">
        <strong><?= $isUsPortal ? 'Demo account' : 'Demo-Konto' ?></strong>
        <span><?= e($isUsPortal ? 'demo.us@deutsche.local' : 'demo.de@deutsche.local') ?> / Deutsche123!</span>
      </div>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
