<?php
$pageTitle = 'Manage Credit Cards';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
require_unrestricted_account($user);

$generatedLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (isset($_POST['create_card_link'])) {
            $token = banking_create_card_link((int) $user['id'], banking_actor('customer', (int) $user['id']));
            $generatedLink = card_link_public_url($token);
            flash('success', 'Secure Add Credit Card link generated.');
        } elseif (isset($_POST['request_card_funding'])) {
            banking_request_credit_card_funding(
                (int) $user['id'],
                (int) ($_POST['card_id'] ?? 0),
                'fund_card',
                (float) ($_POST['amount'] ?? 0),
                (string) ($_POST['note'] ?? ''),
                banking_actor('customer', (int) $user['id'])
            );
            flash('success', 'Credit-card funding request submitted for admin review.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
}

$rows = db()->prepare('SELECT * FROM linked_cards WHERE user_id=? ORDER BY created_at DESC');
$rows->execute([$user['id']]);
$cardRows = $rows->fetchAll();
$approvedCards = array_values(array_filter($cardRows, fn ($card) => ($card['status'] ?? '') === 'approved'));
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero link-hero mb-4"><div><div class="eyebrow">Credit card vault</div><h2>Manage Credit Cards</h2><p>Add credit cards through secure one-time links, track approval status, and submit card funding requests for admin review.</p></div><i class="fa-solid fa-credit-card"></i></div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="row g-3">
            <?php foreach ($cardRows as $card): ?>
                <?php $linkStatus = card_link_effective_status($card); $linkUrl = card_link_public_url((string) $card['token']); ?>
                <div class="col-md-6">
                    <div class="linked-account-card">
                        <span class="bank-logo"><i class="fa-solid fa-credit-card"></i></span>
                        <div class="flex-grow-1">
                            <strong><?= e($card['card_brand'] ? $card['card_brand'] . ' Credit Card' : 'Add Credit Card Link') ?></strong>
                            <div class="muted small"><?= $card['card_last4'] ? '**** ' . e($card['card_last4']) : 'Awaiting card submission' ?></div>
                            <div class="small"><?= e($card['cardholder_name'] ?: 'Secure form generated') ?></div>
                            <div class="small muted">Link <?= e(ucfirst($linkStatus)) ?><?php if (!empty($card['expires_at'])): ?> - Expires <?= e($card['expires_at']) ?><?php endif; ?></div>
                        </div>
                        <span class="status-pill status-<?= $card['status']==='approved'?'success':'warning' ?>"><?= e(strtoupper(str_replace('_', ' ', $card['status']))) ?></span>
                    </div>
                    <?php if ($linkStatus === 'active'): ?>
                        <div class="generated-link-box mt-2" data-copy-wrap>
                            <a href="<?= e($linkUrl) ?>"><?= e($linkUrl) ?></a>
                            <button type="button" data-copy-text="<?= e($linkUrl) ?>" aria-label="Copy Add Credit Card link"><i class="fa-solid fa-copy"></i><span>Copy</span></button>
                        </div>
                    <?php endif; ?>
                    <?php if (($card['status'] ?? '') === 'approved'): ?>
                        <details class="credit-card-action-menu mt-2">
                            <summary><i class="fa-solid fa-sliders me-1"></i>Credit card actions</summary>
                            <div class="credit-card-action-grid">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                                    <input type="hidden" name="funding_direction" value="fund_card">
                                    <strong>Fund credit card from account</strong>
                                    <span>Move available account funds to this approved credit card. The request stays pending until admin review.</span>
                                    <input name="amount" type="number" step="0.01" min="1" class="form-control form-control-sm" placeholder="Amount" required>
                                    <input name="note" class="form-control form-control-sm" placeholder="Optional reference">
                                    <button name="request_card_funding" value="1" class="btn btn-sm btn-navy w-100">Submit pending review</button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$cardRows): ?>
                <div class="col-12"><div class="premium-card p-4 text-center muted">No credit-card requests yet. Generate a secure Add Credit Card link to begin.</div></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <form class="premium-card p-4 h-100" method="post">
            <?= csrf_field() ?>
            <span class="tx-icon mb-3"><i class="fa-solid fa-link"></i></span>
            <h5 class="fw-bold">Add Credit Card</h5>
            <p class="muted">Generate a secure one-time page tied to your account. The link expires automatically and cannot be reused after submission.</p>
            <button name="create_card_link" value="1" class="btn btn-navy w-100">Generate Add Credit Card link</button>
            <?php if ($generatedLink): ?>
                <label class="form-label mt-3">Generated link</label>
                <div class="generated-link-box" data-copy-wrap>
                    <a href="<?= e($generatedLink) ?>"><?= e($generatedLink) ?></a>
                    <button type="button" data-copy-text="<?= e($generatedLink) ?>" aria-label="Copy Add Credit Card link"><i class="fa-solid fa-copy"></i><span>Copy</span></button>
                </div>
                <a class="btn btn-gold w-100 mt-2" href="<?= e($generatedLink) ?>">Open Add Credit Card page</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
