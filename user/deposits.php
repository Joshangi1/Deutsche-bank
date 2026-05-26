<?php
$pageTitle = 'Deposits';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_user();
require_unrestricted_account($user);
ensure_banking_schema();
$account = user_account((int) $user['id']);
$region = banking_region_config(user_banking_region($user, $account));
$accountCurrency = (string) $region['currency'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = (string) ($_POST['deposit_method'] ?? 'check');
    $pendingPrivateUploads = [];
    try {
        if ($method === 'apple_gift_card') {
            $giftCard = secure_review_upload($_FILES['gift_card_image'] ?? [], __DIR__ . '/../uploads/private/gift_cards');
            if ($giftCard) {
                $pendingPrivateUploads[] = $giftCard;
            }
            $proofProvided = (($_FILES['proof_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
            $proof = secure_review_upload($_FILES['proof_file'] ?? [], __DIR__ . '/../uploads/private/gift_cards', true);
            if ($proof) {
                $pendingPrivateUploads[] = $proof;
            }
            if (!$giftCard || ($proofProvided && !$proof)) {
                throw new RuntimeException('Upload JPG, PNG, WEBP, or PDF files up to 5 MB.');
            }
            banking_submit_gift_card_deposit(
                (int) $user['id'],
                (float) ($_POST['amount'] ?? 0),
                $accountCurrency,
                $giftCard,
                $proof,
                (string) ($_POST['card_code'] ?? ''),
                (string) ($_POST['memo'] ?? ''),
                banking_actor('customer', (int) $user['id'])
            );
            $pendingPrivateUploads = [];
            flash('success', 'Apple Gift Card Deposit submitted. It is pending admin review.');
        } else {
            $front = secure_upload($_FILES['front_image'] ?? [], __DIR__ . '/../uploads/deposits');
            $back = secure_upload($_FILES['back_image'] ?? [], __DIR__ . '/../uploads/deposits');
            if (!$front || !$back) {
                throw new RuntimeException('Upload front and back check images under 4 MB.');
            }
            banking_submit_deposit(
                (int) $user['id'],
                (float) ($_POST['amount'] ?? 0),
                $front,
                $back,
                banking_actor('customer', (int) $user['id']),
                $accountCurrency
            );
            flash('success', 'Check deposit uploaded for review.');
        }
    } catch (Throwable $e) {
        foreach ($pendingPrivateUploads as $fileName) {
            $pendingPath = __DIR__ . '/../uploads/private/gift_cards/' . basename($fileName);
            if (is_file($pendingPath)) {
                unlink($pendingPath);
            }
        }
        flash('danger', $e->getMessage());
    }
    header('Location: ' . url('user/deposits.php'));
    exit;
}

$depositStmt = db()->prepare('SELECT * FROM deposits WHERE user_id=? ORDER BY created_at DESC');
$depositStmt->execute([$user['id']]);
$deposits = $depositStmt->fetchAll();
include __DIR__ . '/../includes/user_header.php';
?>
<?= deposit_protection_badge($user, $account, 'mb-4') ?>
<section class="deposit-page-hero mb-4">
    <div>
        <div class="eyebrow">Deposit Center</div>
        <h2>Choose how to fund your account</h2>
        <p>Submit supported deposits securely. Funds become available only after review and approval.</p>
    </div>
    <span class="deposit-hero-icon"><i class="fa-solid fa-shield-halved"></i></span>
</section>

<div class="deposit-workspace">
    <div class="deposit-methods">
        <form class="gift-card-deposit-card" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="deposit_method" value="apple_gift_card">
            <div class="gift-card-deposit-heading">
                <span class="gift-card-brand-icon" aria-hidden="true"><i class="fa-brands fa-apple"></i></span>
                <div>
                    <div class="eyebrow">Premium deposit option</div>
                    <h3>Apple Gift Card Deposit</h3>
                    <p>Upload a gift card for secure manual review.</p>
                </div>
            </div>
            <div class="gift-card-notice">
                <i class="fa-solid fa-circle-info"></i>
                <span>Gift card deposits are reviewed by admin before funds are added to your balance. This is not Apple Pay processing.</span>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-sm-7">
                    <label class="form-label">Gift card value</label>
                    <input name="amount" type="number" min="0.01" step="0.01" class="form-control" placeholder="0.00" required>
                </div>
                <div class="col-sm-5">
                    <label class="form-label">Currency</label>
                    <select name="currency_display" class="form-select" disabled>
                        <option><?= e($accountCurrency) ?></option>
                    </select>
                    <div class="form-note">Account currency</div>
                </div>
                <div class="col-12">
                    <label class="gift-upload-drop">
                        <i class="fa-solid fa-image"></i>
                        <span><strong>Upload front of gift card</strong><small>JPG, PNG, WEBP or PDF, up to 5 MB</small></span>
                        <input name="gift_card_image" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                    </label>
                </div>
                <div class="col-12">
                    <label class="gift-upload-drop optional">
                        <i class="fa-solid fa-receipt"></i>
                        <span><strong>Upload receipt / proof of purchase</strong><small>Optional, up to 5 MB</small></span>
                        <input name="proof_file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Card code / PIN <span class="muted">(optional)</span></label>
                    <input name="card_code" type="text" maxlength="40" class="form-control" autocomplete="off" placeholder="Optional card code">
                    <div class="form-note">Only the final 4 characters are retained.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Memo <span class="muted">(optional)</span></label>
                    <input name="memo" type="text" maxlength="255" class="form-control" placeholder="Add a note">
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-4"><i class="fa-solid fa-arrow-up-from-bracket me-2"></i>Submit for review</button>
        </form>

        <form class="check-deposit-card premium-card" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="deposit_method" value="check">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="icon-chip"><i class="fa-solid fa-camera"></i></span>
                <div><h4 class="mb-1">Mobile check deposit</h4><p class="muted mb-0">Photograph the front and back of your check.</p></div>
            </div>
            <label class="form-label">Amount (<?= e($accountCurrency) ?>)</label>
            <input name="amount" type="number" min="0.01" step="0.01" class="form-control mb-3" placeholder="0.00" required>
            <label class="form-label">Front image</label>
            <input name="front_image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control mb-3" required>
            <label class="form-label">Back image</label>
            <input name="back_image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control mb-3" required>
            <button class="btn btn-outline-primary">Submit check deposit</button>
        </form>
    </div>

    <section class="table-card deposit-history-card">
        <div class="deposit-history-header">
            <div><h3>Deposit history</h3><p>Pending submissions do not affect your available balance.</p></div>
        </div>
        <div class="deposit-history-list">
            <?php foreach ($deposits as $deposit): ?>
                <?php
                $isGiftCard = ($deposit['deposit_method'] ?? 'check') === 'apple_gift_card';
                $status = (string) $deposit['status'];
                $statusClass = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
                ?>
                <article class="deposit-history-row">
                    <span class="deposit-type-icon <?= $isGiftCard ? 'gift' : '' ?>"><i class="<?= $isGiftCard ? 'fa-brands fa-apple' : 'fa-solid fa-camera' ?>"></i></span>
                    <div class="deposit-history-copy">
                        <strong><?= $isGiftCard ? 'Apple Gift Card Deposit' : 'Mobile Check Deposit' ?></strong>
                        <small><?= e(transaction_display_date($deposit['created_at'])) ?><?= !empty($deposit['memo']) ? ' - ' . e($deposit['memo']) : '' ?></small>
                    </div>
                    <div class="deposit-history-amount">
                        <strong><?= money($deposit['amount'], $deposit['currency'] ?? $accountCurrency) ?></strong>
                        <span class="status-pill status-<?= e($statusClass) ?>"><?= e(strtoupper($status === 'pending' ? 'Pending Review' : $status)) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$deposits): ?>
                <div class="empty-mini">No deposits submitted yet.</div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
