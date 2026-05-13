<?php
$pageTitle = 'Security Center';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brevo.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['password_change']) && strlen((string) $_POST['new_password']) >= 8) {
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash((string) $_POST['new_password'], PASSWORD_BCRYPT), $user['id']]);
        db()->prepare('INSERT INTO security_events (user_id, event_type, title, details, device, ip_address, severity) VALUES (?, "password", "Password changed", "Your online banking password was updated.", ?, ?, "success")')
            ->execute([$user['id'], $_SERVER['HTTP_USER_AGENT'] ?? 'Current device', $_SERVER['REMOTE_ADDR'] ?? 'local']);
        notify_customer_event((int) $user['id'], 'security_alert', ['message' => 'Your online banking password was changed.']);
        flash('success', 'Password updated.');
        header('Location: security_center.php');
        exit;
    }
    save_setting('two_factor_' . $user['id'], isset($_POST['two_factor']) ? 'on' : 'off');
    save_setting('login_alerts_' . $user['id'], isset($_POST['login_alerts']) ? 'on' : 'off');
    flash('success', 'Security settings updated.');
    header('Location: security_center.php');
    exit;
}
$events = db()->prepare('SELECT * FROM security_events WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
$events->execute([$user['id']]);
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero security-hero mb-4"><div><div class="eyebrow">Security Center</div><h2>Protect your digital banking</h2><p>Manage trusted devices, alerts, OTP settings, sessions, and password security.</p></div><i class="fa-solid fa-shield-halved"></i></div>
<div class="row g-4">
    <div class="col-xl-4"><form class="premium-card p-4" method="post"><?= csrf_field() ?><h5 class="fw-bold">Security settings</h5><label class="form-check form-switch mb-3"><input class="form-check-input" name="two_factor" type="checkbox" <?= setting('two_factor_' . $user['id'], 'on') === 'on' ? 'checked' : '' ?>> Two-factor verification</label><label class="form-check form-switch mb-3"><input class="form-check-input" name="login_alerts" type="checkbox" <?= setting('login_alerts_' . $user['id'], 'on') === 'on' ? 'checked' : '' ?>> Login alerts</label><label class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" checked disabled> Biometric sign-in ready</label><button class="btn btn-navy">Update settings</button></form></div>
    <div class="col-xl-4"><form class="premium-card p-4" method="post"><?= csrf_field() ?><h5 class="fw-bold">Change password</h5><input name="new_password" type="password" minlength="8" class="form-control mb-3" placeholder="New password"><button name="password_change" value="1" class="btn btn-gold">Change password</button></form></div>
    <div class="col-xl-4"><div class="warning-card"><i class="fa-solid fa-triangle-exclamation"></i><strong>Suspicious activity review</strong><p>We monitor unusual payment, SEPA, and login behavior and may temporarily limit sensitive actions while activity is verified.</p></div></div>
</div>
<div class="table-card mt-4"><div class="p-4"><h5 class="fw-bold mb-0">Recent security activity</h5></div><table class="table align-middle mb-0"><tbody><?php foreach ($events as $event): ?><tr><td><div class="tx-merchant"><span class="tx-icon"><i class="fa-solid fa-shield-halved"></i></span><div><strong><?= e($event['title']) ?></strong><div class="small muted"><?= e($event['details']) ?></div></div></div></td><td><?= e($event['device'] ?? 'Device') ?><div class="small muted"><?= e($event['ip_address'] ?? '') ?></div></td><td><span class="status-pill status-<?= e($event['severity']) ?>"><?= e(strtoupper($event['severity'])) ?></span></td><td><?= e(transaction_display_date($event['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
