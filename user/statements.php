<?php
$pageTitle = 'Statements';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    save_setting('paperless_' . $user['id'], isset($_POST['paperless']) ? 'on' : 'off');
    flash('success', 'Document delivery preferences updated.');
    header('Location: statements.php');
    exit;
}
$docs = db()->prepare('SELECT * FROM documents WHERE user_id=? ORDER BY created_at DESC');
$docs->execute([$user['id']]);
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero statement-hero mb-4"><div><div class="eyebrow">Document Center</div><h2>Statements, notices, and tax forms</h2><p>Access monthly PDF statements, account documents, tax forms, and paperless preferences.</p></div><i class="fa-solid fa-file-lines"></i></div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="table-card">
            <div class="p-4"><h5 class="fw-bold mb-0">Available documents</h5></div>
            <table class="table align-middle mb-0">
                <tbody>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td>
                                <div class="tx-merchant">
                                    <span class="tx-icon"><i class="fa-solid <?= $d['document_type']==='Tax Form'?'fa-file-invoice':'fa-file-pdf' ?>"></i></span>
                                    <div><strong><?= e($d['title']) ?></strong><div class="small muted"><?= e($d['document_type']) ?> &middot; <?= e($d['period_label']) ?></div></div>
                                </div>
                            </td>
                            <td><span class="status-pill status-<?= $d['status']==='new'?'warning':'success' ?>"><?= e(strtoupper($d['status'])) ?></span></td>
                            <td class="text-end"><a class="btn btn-sm btn-light border" href="#"><i class="fa-solid fa-download me-1"></i>Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($docs->rowCount() === 0): ?>
                        <tr><td colspan="3" class="text-center muted py-5"><i class="fa-solid fa-file-lines d-block fs-3 mb-2"></i>No statements or account documents are available yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-xl-4"><form class="premium-card p-4" method="post"><?= csrf_field() ?><h5 class="fw-bold">Paperless settings</h5><p class="muted">Receive statements and notices electronically as soon as they are available.</p><label class="form-check form-switch mb-3"><input class="form-check-input" name="paperless" type="checkbox" <?= setting('paperless_' . $user['id'], 'on') === 'on' ? 'checked' : '' ?>> Paperless delivery</label><button class="btn btn-navy">Save preferences</button></form></div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
