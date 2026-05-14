<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$demoAdminEmail = 'admin@deutsche.local';
$demoAdminPassword = 'Deutsche123!';
function ensure_demo_admin_login(string $email, string $password): void
{
    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if (!$admin) {
        db()->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
            ->execute(['Deutsche Admin', $email, password_hash($password, PASSWORD_BCRYPT), 'Super Admin']);
        return;
    }
    if (!str_starts_with((string) $admin['password_hash'], '$2y$')) {
        db()->prepare('UPDATE admins SET name=?, password_hash=?, role=? WHERE id=?')
            ->execute(['Deutsche Admin', password_hash($password, PASSWORD_BCRYPT), 'Super Admin', $admin['id']]);
    }
}
ensure_demo_admin_login($demoAdminEmail, $demoAdminPassword);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('SELECT * FROM admins WHERE email=? LIMIT 1');
    $stmt->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $admin = $stmt->fetch();
    if ($admin && password_verify((string)$_POST['password'], $admin['password_hash'])) {
        session_regenerate_id(true); $_SESSION['admin_id'] = (int)$admin['id'];
        log_admin((int)$admin['id'], 'login', 'Admin signed in');
        header('Location: index.php'); exit;
    }
    flash('danger', 'Invalid admin credentials.');
}
$GLOBALS['pageLanguage'] = 'en';
$GLOBALS['disableTranslate'] = true;
$pageTitle='Admin Login'; include __DIR__ . '/../includes/public_header.php';
?>
<section class="auth-shell"><form class="auth-card" method="post"><?= csrf_field() ?><div class="mb-4"><?= lead_logo('dark') ?></div><h1 class="h3 fw-bold">Admin portal</h1><p class="muted">Authorized Deutsche operations access only.</p><input name="email" type="email" class="form-control mb-3" required><input name="password" type="password" class="form-control mb-3" required><button class="btn btn-gold w-100">Sign in</button></form></section>
<?php include __DIR__ . '/../includes/public_footer.php'; ?>
