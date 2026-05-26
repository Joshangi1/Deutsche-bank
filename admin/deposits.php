<?php
$pageTitle = 'Deposit Approvals';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    try {
        banking_review_deposit($id, $status, (string) ($_POST['review_note'] ?? ''), banking_actor('admin', (int) $admin['id']));
        log_admin((int) $admin['id'], 'deposit_review', 'Reviewed deposit through service layer ' . $id, null, null, ['status' => $status, 'note' => $_POST['review_note'] ?? '']);
        flash('success', 'Deposit reviewed successfully.');
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: ' . url('admin/deposits.php'));
    exit;
}

$rows = db()->query('SELECT d.*, u.first_name, u.last_name, u.email FROM deposits d JOIN users u ON u.id=d.user_id ORDER BY FIELD(d.status, "pending", "approved", "rejected"), d.created_at DESC')->fetchAll();
include __DIR__ . '/../includes/admin_header.php';
?>
<section class="admin-deposit-hero mb-4">
    <div><div class="eyebrow">Operations approval</div><h2>Deposit review queue</h2><p>Approve check and Apple Gift Card deposits only after verifying submitted evidence.</p></div>
    <span><i class="fa-solid fa-file-circle-check"></i></span>
</section>
<div class="admin-deposit-grid">
    <?php foreach ($rows as $deposit): ?>
        <?php
        $isGiftCard = ($deposit['deposit_method'] ?? 'check') === 'apple_gift_card';
        $status = (string) $deposit['status'];
        $statusClass = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
        $currency = $deposit['currency'] ?? 'USD';
        ?>
        <article class="admin-deposit-review-card">
            <header>
                <div class="d-flex gap-3 align-items-center">
                    <span class="deposit-type-icon <?= $isGiftCard ? 'gift' : '' ?>"><i class="<?= $isGiftCard ? 'fa-brands fa-apple' : 'fa-solid fa-camera' ?>"></i></span>
                    <div>
                        <h3><?= $isGiftCard ? 'Apple Gift Card Deposit' : 'Mobile Check Deposit' ?></h3>
                        <p><?= e($deposit['first_name'] . ' ' . $deposit['last_name']) ?> <span><?= e($deposit['email']) ?></span></p>
                    </div>
                </div>
                <span class="status-pill status-<?= e($statusClass) ?>"><?= e(strtoupper($status === 'pending' ? 'Pending Review' : $status)) ?></span>
            </header>
            <div class="admin-deposit-metrics">
                <div><small>Amount</small><strong><?= money($deposit['amount'], $currency) ?></strong></div>
                <div><small>Submitted</small><strong><?= e(transaction_display_date($deposit['created_at'])) ?></strong></div>
                <?php if ($isGiftCard && !empty($deposit['card_code_last4'])): ?><div><small>Code retained</small><strong>**** <?= e($deposit['card_code_last4']) ?></strong></div><?php endif; ?>
            </div>
            <?php if (!empty($deposit['memo'])): ?><p class="admin-deposit-memo"><strong>Memo:</strong> <?= e($deposit['memo']) ?></p><?php endif; ?>
            <?php if ($isGiftCard): ?>
                <div class="admin-deposit-files">
                    <a class="btn btn-light border" href="<?= url('admin/deposit_media.php?id=' . (int) $deposit['id'] . '&file=card') ?>" target="_blank" rel="noopener"><i class="fa-solid fa-image me-2"></i>View gift card</a>
                    <?php if (!empty($deposit['proof_file'])): ?><a class="btn btn-light border" href="<?= url('admin/deposit_media.php?id=' . (int) $deposit['id'] . '&file=proof') ?>" target="_blank" rel="noopener"><i class="fa-solid fa-receipt me-2"></i>View receipt</a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="small muted">Existing check-deposit review record.</div>
            <?php endif; ?>
            <?php if ($status === 'pending'): ?>
                <form method="post" class="admin-deposit-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $deposit['id'] ?>">
                    <label class="form-label">Admin note</label>
                    <textarea name="review_note" class="form-control" rows="2" placeholder="Optional review note"></textarea>
                    <div class="d-flex gap-2 mt-3">
                        <button name="status" value="approved" class="btn btn-success"><i class="fa-solid fa-check me-2"></i>Approve</button>
                        <button name="status" value="rejected" class="btn btn-outline-danger"><i class="fa-solid fa-xmark me-2"></i>Reject</button>
                    </div>
                </form>
            <?php elseif (!empty($deposit['review_note'])): ?>
                <p class="admin-deposit-memo"><strong>Review note:</strong> <?= e($deposit['review_note']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="table-card p-5 text-center muted">No deposit submissions are waiting for review.</div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
