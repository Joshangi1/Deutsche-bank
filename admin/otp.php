<?php
$pageTitle = 'SMS OTP Monitoring';
include __DIR__ . '/../includes/admin_header.php';
$rows = db()->query('SELECT o.*, u.email, u.first_name, u.last_name
    FROM otp_verifications o
    LEFT JOIN users u ON u.id=o.user_id
    ORDER BY o.created_at DESC
    LIMIT 150')->fetchAll();
$statusForOtp = static function (array $otp): string {
    if (($otp['send_status'] ?? '') === 'failed') {
        return 'failed';
    }
    if (!empty($otp['verified_at'])) {
        return 'verified';
    }
    if ((int) ($otp['attempts'] ?? 0) >= (int) ($otp['max_attempts'] ?? 5)) {
        return 'failed attempts';
    }
    if (strtotime((string) $otp['expires_at']) < time()) {
        return 'expired';
    }
    return 'sent';
};
?>
<div class="table-card">
    <div class="p-4">
        <h5 class="fw-bold mb-0">SMS OTP activity</h5>
        <p class="small muted mb-0">Codes are hashed and never visible to admins.</p>
    </div>
    <table class="table align-middle mb-0">
        <thead><tr><th>User</th><th>Phone</th><th>Purpose</th><th>Status</th><th>Attempts</th><th>Expires</th><th>Created</th><th>IP</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $o): ?>
                <?php $status = $statusForOtp($o); ?>
                <tr>
                    <td><strong><?= e(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: 'Pending signup') ?></strong><div class="small muted"><?= e($o['email'] ?? 'No email yet') ?></div></td>
                    <td><?= e($o['phone']) ?></td>
                    <td><?= e(strtoupper((string) $o['purpose'])) ?></td>
                    <td><span class="status-pill status-<?= in_array($status, ['verified','sent'], true) ? 'success' : (str_contains($status, 'failed') ? 'danger' : 'warning') ?>"><?= e(strtoupper($status)) ?></span><?php if (!empty($o['last_error'])): ?><div class="small muted"><?= e($o['last_error']) ?></div><?php endif; ?></td>
                    <td><?= (int) $o['attempts'] ?> / <?= (int) $o['max_attempts'] ?></td>
                    <td><?= e($o['expires_at']) ?></td>
                    <td><?= e($o['created_at']) ?></td>
                    <td><span title="<?= e($o['user_agent'] ?? '') ?>"><?= e($o['ip_address'] ?? '') ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center muted py-5">No SMS OTP activity yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
