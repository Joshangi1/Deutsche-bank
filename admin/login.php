<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    ensure_banking_schema();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $stmt = db()->prepare('SELECT * FROM admins WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && (int) ($admin['failed_attempts'] ?? 0) >= 5 && strtotime((string) ($admin['locked_until'] ?? '')) > time()) {
        flash('danger', 'Admin account temporarily locked after failed attempts. Try again later.');
    } elseif ($admin && ($admin['status'] ?? 'active') !== 'active') {
        flash('danger', 'This admin profile is not active.');
    } elseif ($admin && password_verify((string)$_POST['password'], $admin['password_hash'])) {
        start_authenticated_session('admin', (int) $admin['id']);
        db()->prepare('UPDATE admins SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?')->execute([$admin['id']]);
        log_admin((int)$admin['id'], 'login', 'Admin signed in');
        header('Location: index.php'); exit;
    } else {
        if ($admin) {
            $attempts = (int) ($admin['failed_attempts'] ?? 0) + 1;
            $locked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            db()->prepare('UPDATE admins SET failed_attempts=?, locked_until=? WHERE id=?')->execute([$attempts, $locked, $admin['id']]);
        }
        flash('danger', 'Invalid admin credentials.');
    }
}
$GLOBALS['pageLanguage'] = 'en';
$GLOBALS['disableTranslate'] = true;
$pageTitle='Admin Login'; include __DIR__ . '/../includes/public_header.php';
?>
<section class="auth-shell"><form class="auth-card" method="post" data-auth-validation novalidate><?= csrf_field() ?><div class="mb-4"><?= brand_logo('dark') ?></div><h1 class="h3 fw-bold">Admin portal</h1><p class="muted">Authorized Deutsche Bank operations access only.</p><input name="email" type="email" inputmode="email" autocomplete="email" class="form-control mb-3" required><input name="password" type="password" autocomplete="current-password" class="form-control mb-3" required><button class="btn btn-gold w-100">Sign in</button></form></section>
<?php include __DIR__ . '/../includes/public_footer.php'; ?>
