<?php
$pageTitle = 'Accounts';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/frontend_components.php';
ensure_banking_schema();
$user = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        banking_create_user_account((int) $user['id'], (string) ($_POST['account_type'] ?? 'Savings Account'), banking_actor('customer', (int) $user['id']));
        flash('success', 'New account opened under your existing login.');
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: accounts.php');
    exit;
}

$accounts = user_accounts((int) $user['id']);
$primary = $accounts[0] ?? null;
$region = user_banking_region($user, $primary);
$regionConfig = banking_region_config($region);
$usesIban = in_array($region, ['de', 'ch'], true);
$currency = user_account_currency($user, $primary);
$statusMeta = account_display_status($user);
$accountOptions = in_array($region, ['us', 'ca'], true)
    ? ['Everyday Checking', 'Savings Account', 'Money Market', 'Business Current Account']
    : ($region === 'uk' ? ['Current Account', 'Savings Account', 'Business Current Account'] : ['Current Account', 'Savings Account', 'Business Current Account']);
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero mb-4">
    <div>
        <div class="eyebrow">Account portfolio</div>
        <h2>Open and manage accounts</h2>
        <p>Keep checking, savings, and business accounts inside the same secure login.</p>
    </div>
    <i class="fa-solid fa-layer-group"></i>
</div>
<?= deposit_protection_badge($user, $primary, 'mb-4') ?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="row g-3">
            <?php foreach ($accounts as $account): ?>
                <?php $details = user_banking_details((int) $user['id'], $user, $account, true); ?>
                <div class="col-md-6">
                    <div class="bank-account-card h-100">
                        <div class="bank-account-card-top">
                            <div>
                                <div class="eyebrow"><?= (int) $account['id'] === (int) ($primary['id'] ?? 0) ? 'Primary account' : 'Additional account' ?></div>
                                <h5><?= e($account['account_type']) ?></h5>
                                <p>Opened <?= e(date('M j, Y', strtotime((string) $account['created_at']))) ?></p>
                            </div>
                            <span class="account-status-pill <?= e($statusMeta['class']) ?>"><i class="fa-solid <?= e($statusMeta['icon']) ?>"></i><?= e($statusMeta['label']) ?></span>
                        </div>
                        <div class="bank-account-balance">
                            <span>Available balance</span>
                            <strong><?= money($account['available_balance'], $currency) ?></strong>
                        </div>
                        <div class="account-detail-grid">
                            <div><span>Pending</span><strong><?= money($account['pending_balance'], $currency) ?></strong></div>
                            <div><span>Savings</span><strong><?= money($account['savings_balance'], $currency) ?></strong></div>
                            <?php foreach ($details as $detail): ?>
                                <div class="copyable-account-detail">
                                    <span><?= e($detail['detail_label']) ?></span>
                                    <strong><?= e($detail['detail_value']) ?></strong>
                                    <?php if (!array_key_exists('is_copyable', $detail) || (int) $detail['is_copyable'] === 1): ?>
                                        <button type="button" class="copy-detail-btn" data-copy-text="<?= e($detail['detail_value']) ?>" aria-label="Copy <?= e($detail['detail_label']) ?>"><i class="fa-regular fa-copy"></i><span>Copy</span></button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="bank-account-actions">
                            <a class="btn btn-sm btn-navy <?= account_is_restricted($user) ? 'disabled' : '' ?>" href="<?= url('user/transfers.php') ?>"><i class="fa-solid fa-right-left me-1"></i>Transfer</a>
                            <a class="btn btn-sm btn-light border" href="<?= url('user/statements.php') ?>"><i class="fa-solid fa-file-lines me-1"></i>Statements</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <form class="premium-card p-4" method="post">
            <?= csrf_field() ?>
            <span class="tx-icon mb-3"><i class="fa-solid fa-plus"></i></span>
            <h5 class="fw-bold">Open another account</h5>
            <p class="muted">Create a separate account number while keeping the same profile, cards, security, and dashboard login.</p>
            <label class="form-label">Account type</label>
            <select name="account_type" class="form-select mb-3">
                <?php foreach ($accountOptions as $option): ?>
                    <option value="<?= e($option) ?>"><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-navy w-100">Open account</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
