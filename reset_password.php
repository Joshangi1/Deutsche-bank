<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/brevo.php';

// Ensure password_resets table exists
db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_user (user_id),
    INDEX idx_pr_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$token = trim($_GET['token'] ?? '');
$tokenHash = $token ? hash('sha256', $token) : '';

// Look up valid token
$row = null;
if ($tokenHash) {
    $stmt = db()->prepare('SELECT pr.*, u.email, u.first_name, u.last_name FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1');
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postToken = trim($_POST['token'] ?? '');
    $postHash  = $postToken ? hash('sha256', $postToken) : '';
    $password  = (string) ($_POST['password'] ?? '');
    $confirm   = (string) ($_POST['confirm_password'] ?? '');

    $stmt2 = db()->prepare('SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1');
    $stmt2->execute([$postHash]);
    $reset = $stmt2->fetch();

    if (!$reset) {
        flash('danger', 'This reset link is invalid or has expired. Please request a new one.');
        header('Location: forgot_password.php');
        exit;
    }

    $strongPassword = strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/\d/', $password);

    if (!$strongPassword) {
        flash('danger', 'Password must be at least 8 characters and include uppercase, lowercase, and a number.');
        header('Location: reset_password.php?token=' . urlencode($postToken));
        exit;
    }

    if (!hash_equals($password, $confirm)) {
        flash('danger', 'Passwords do not match.');
        header('Location: reset_password.php?token=' . urlencode($postToken));
        exit;
    }

    db()->prepare('UPDATE users SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE id=?')
        ->execute([password_hash($password, PASSWORD_BCRYPT), $reset['user_id']]);
    db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE token_hash=?')
        ->execute([$postHash]);

    // Log the security event
    db()->prepare('INSERT INTO security_events (user_id, event_type, title, details, device, ip_address, severity) VALUES (?, "password", "Password reset", "Password was reset via email link.", ?, ?, "success")')
        ->execute([$reset['user_id'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', $_SERVER['REMOTE_ADDR'] ?? 'local']);

    flash('success', 'Your password has been updated. Please sign in.');
    header('Location: login.php?email=' . urlencode($reset['email']));
    exit;
}

$pageTitle = 'Reset Password';
include __DIR__ . '/includes/public_header.php';
?>
<section class="auth-shell">
<?php if (!$row): ?>
  <div class="auth-card">
    <div class="mb-4"><?= lead_logo('dark') ?></div>
    <h1 class="h3 fw-bold">Link expired</h1>
    <p class="muted">This password reset link is invalid or has already been used. Please request a new one.</p>
    <a href="forgot_password.php" class="btn btn-gold w-100">Request new link</a>
  </div>
<?php else: ?>
  <form class="auth-card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-4"><?= lead_logo('dark') ?></div>
    <h1 class="h3 fw-bold">Choose a new password</h1>
    <p class="muted">Hello <?= e($row['first_name']) ?>, enter a new password for your account.</p>
    <div class="secure-input mb-3">
      <input name="password" type="password" minlength="8" class="form-control" placeholder="New password" required>
      <button type="button" data-visibility-toggle aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
    </div>
    <div class="secure-input mb-3">
      <input name="confirm_password" type="password" minlength="8" class="form-control" placeholder="Confirm new password" required>
      <button type="button" data-visibility-toggle aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
    </div>
    <div class="password-rules mb-3">
      <span data-password-rule="length">8+ characters</span>
      <span data-password-rule="upper">Uppercase</span>
      <span data-password-rule="lower">Lowercase</span>
      <span data-password-rule="number">Number</span>
    </div>
    <button class="btn btn-gold w-100">Update password</button>
  </form>
<?php endif; ?>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
