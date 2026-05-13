<?php
$pageTitle = 'Identity Verification';
require_once __DIR__ . '/../config/brevo.php';
include __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $note = trim((string) ($_POST['review_note'] ?? ''));
    if (isset($_POST['review_document'])) {
        $docId  = (int) $_POST['document_id'];
        $status = (string) $_POST['status'];
        banking_review_kyc_document($docId, $status, $note, banking_actor('admin', (int) $admin['id']));
        log_admin((int) $admin['id'], 'kyc_review', 'Reviewed identity verification document', null, null, ['document_id' => $docId, 'status' => $status]);
    }
    if (isset($_POST['review_biometric'])) {
        $verificationId = (int) $_POST['verification_id'];
        $status         = (string) $_POST['status'];
        banking_review_biometric_verification($verificationId, $status, $note, banking_actor('admin', (int) $admin['id']));
        log_admin((int) $admin['id'], 'biometric_review', 'Reviewed biometric liveness verification', null, null, ['verification_id' => $verificationId, 'status' => $status]);
    }
    if (isset($_POST['approve_user'])) {
        $userId = (int) $_POST['user_id'];
        db()->prepare('UPDATE kyc_documents SET status="approved", review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE user_id=? AND status IN ("pending","reupload_requested")')
            ->execute([$note, (int) $admin['id'], $userId]);
        db()->prepare('UPDATE biometric_verifications SET status="verified", review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE user_id=? AND status="pending"')
            ->execute([$note, (int) $admin['id'], $userId]);
        db()->prepare('UPDATE users SET verification_status="approved", risk_status="clear" WHERE id=?')
            ->execute([$userId]);
        banking_emit_event('user.verification_approved', ['note' => $note], banking_actor('admin', (int) $admin['id']), $userId, 'user', $userId);
        log_admin((int) $admin['id'], 'user_verification_approved', 'Approved full user verification', $userId, null, ['note' => $note]);
    }
    flash('success', 'Verification decision recorded.');
    header('Location: verification.php');
    exit;
}

$rows = db()->query('SELECT k.*, u.first_name,u.last_name,u.email,u.verification_status,u.risk_status FROM kyc_documents k JOIN users u ON u.id=k.user_id ORDER BY k.created_at DESC');
$biometrics = db()->query('SELECT b.*, u.first_name,u.last_name,u.email,u.verification_status,u.risk_status FROM biometric_verifications b JOIN users u ON u.id=b.user_id ORDER BY b.created_at DESC');
$pendingUsers = db()->query('SELECT u.id,u.first_name,u.last_name,u.email,u.verification_status,u.risk_status,
    (SELECT COUNT(*) FROM kyc_documents k WHERE k.user_id=u.id) docs,
    (SELECT COUNT(*) FROM biometric_verifications b WHERE b.user_id=u.id) biometrics
    FROM users u
    WHERE u.verification_status IN ("pending","not_started","reupload_requested","rejected")
    ORDER BY u.created_at DESC LIMIT 50');
?>
<div class="banking-hero security-hero mb-4"><div><div class="eyebrow">Identity operations</div><h2>Biometric and document review</h2><p>Compare government ID uploads with liveness captures before approving account verification.</p></div><i class="fa-solid fa-user-shield"></i></div>

<div class="table-card mb-4">
    <div class="p-4"><h5 class="fw-bold mb-0">New user approval</h5><p class="muted mb-0">Use this when the admin has inspected the submitted document and liveness captures and wants to approve the full account.</p></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>User</th><th>Submitted items</th><th>Status</th><th>Admin decision</th></tr></thead><tbody>
        <?php foreach ($pendingUsers as $u): ?>
            <tr>
                <td><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong><div class="small muted"><?= e($u['email']) ?></div></td>
                <td><?= (int) $u['docs'] ?> document(s)<br><?= (int) $u['biometrics'] ?> biometric session(s)</td>
                <td><span class="status-pill status-warning"><?= e(strtoupper(str_replace('_',' ', $u['verification_status']))) ?></span><div class="small muted"><?= e(strtoupper(str_replace('_',' ', $u['risk_status']))) ?></div></td>
                <td>
                    <form method="post" class="d-flex flex-wrap gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <input name="review_note" class="form-control form-control-sm" placeholder="Approval note" style="max-width:260px">
                        <button name="approve_user" value="1" class="btn btn-sm btn-success">Approve account</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pendingUsers->rowCount()): ?><tr><td colspan="4" class="text-center muted py-5">No users awaiting approval.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>

<div class="table-card mb-4">
    <div class="p-4">
        <h5 class="fw-bold mb-0">Biometric liveness review</h5>
        <p class="muted mb-0">Review webcam captures from signup. Media is served through protected admin-only access.</p>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Member</th><th>Liveness captures</th><th>Status</th><th>Review</th></tr></thead>
            <tbody>
            <?php foreach ($biometrics as $row): ?>
                <tr>
                    <td><strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong><div class="small muted"><?= e($row['email']) ?></div><span class="category-badge"><?= e(strtoupper(str_replace('_', ' ', $row['verification_status']))) ?></span></td>
                    <td>
                        <div class="verification-media-grid">
                            <?php foreach (['capture_forward' => 'Forward', 'capture_left' => 'Left', 'capture_right' => 'Right', 'capture_blink' => 'Blink / smile'] as $field => $label): ?>
                                <?php if (!empty($row[$field])): ?>
                                    <a class="verification-media" href="kyc_media.php?type=biometric&id=<?= (int) $row['id'] ?>&field=<?= e($field) ?>" target="_blank" rel="noopener"><img src="kyc_media.php?type=biometric&id=<?= (int) $row['id'] ?>&field=<?= e($field) ?>" alt="<?= e($label) ?> capture"><span><?= e($label) ?></span></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="small muted mt-2">Liveness score <?= (int) $row['liveness_score'] ?> &middot; <?= e($row['created_at']) ?></div>
                    </td>
                    <td><span class="status-pill status-<?= $row['status'] === 'verified' ? 'success' : ($row['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= e(strtoupper($row['status'])) ?></span><div class="small muted"><?= e(strtoupper(str_replace('_', ' ', $row['risk_status']))) ?></div></td>
                    <td>
                        <form method="post" class="d-flex flex-wrap gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="verification_id" value="<?= (int) $row['id'] ?>">
                            <select name="status" class="form-select form-select-sm" style="max-width:180px"><option value="verified">Verify</option><option value="failed">Fail</option><option value="pending">Keep pending</option></select>
                            <input name="review_note" class="form-control form-control-sm" placeholder="Biometric review note" style="max-width:260px">
                            <button name="review_biometric" value="1" class="btn btn-sm btn-gold">Record decision</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$biometrics->rowCount()): ?><tr><td colspan="4" class="text-center muted py-5">No biometric verification captures submitted yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="table-card">
    <div class="p-4">
        <h5 class="fw-bold mb-0">Identity document review</h5>
        <p class="muted mb-0">Review uploaded IDs, request re-upload, or approve verification. Files are stored in a private upload directory.</p>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Member</th><th>Document</th><th>Status</th><th>Review</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong><div class="small muted"><?= e($row['email']) ?></div><span class="category-badge"><?= e(strtoupper(str_replace('_', ' ', $row['verification_status']))) ?></span></td>
                    <td><i class="fa-solid fa-id-card text-warning me-2"></i><?= e(strtoupper(str_replace('_', ' ', $row['document_type']))) ?><div class="small muted"><?= e($row['original_name'] ?: $row['file_name']) ?> &middot; <?= e($row['created_at']) ?></div><a class="btn btn-sm btn-light border mt-2" href="kyc_media.php?type=document&id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">View document</a></td>
                    <td><span class="status-pill status-<?= $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= e(strtoupper(str_replace('_', ' ', $row['status']))) ?></span><div class="small muted"><?= e(strtoupper(str_replace('_', ' ', $row['risk_status']))) ?></div></td>
                    <td>
                        <form method="post" class="d-flex flex-wrap gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= (int) $row['id'] ?>">
                            <select name="status" class="form-select form-select-sm" style="max-width:180px"><option value="approved">Approve</option><option value="reupload_requested">Request re-upload</option><option value="rejected">Reject</option><option value="pending">Keep pending</option></select>
                            <input name="review_note" class="form-control form-control-sm" placeholder="Internal review note" style="max-width:260px">
                            <button name="review_document" value="1" class="btn btn-sm btn-gold">Record decision</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows->rowCount()): ?><tr><td colspan="4" class="text-center muted py-5">No verification documents submitted yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
