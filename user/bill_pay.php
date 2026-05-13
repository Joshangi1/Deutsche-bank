<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
require_unrestricted_account($user);
$account = user_account((int) $user['id']);
$isUsAccount = user_is_us_account($user, $account);
$currency = user_account_currency($user, $account);
$pageTitle = $isUsAccount ? 'Bill Pay' : 'Standing Orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $actor = banking_actor('customer', (int) $user['id']);
    if (isset($_POST['add_biller'])) {
        banking_add_biller((int) $user['id'], (string) $_POST['name'], (string) $_POST['category'], (string) $_POST['account_number'], (int) $_POST['due_day'], isset($_POST['autopay']), $actor);
        flash('success', $isUsAccount ? 'Biller added.' : 'SEPA payee added.');
        header('Location: bill_pay.php');
        exit;
    }
    if (isset($_POST['schedule_payment'])) {
        if (!verify_transaction_pin($user, (string) ($_POST['transaction_pin'] ?? ''))) {
            flash('danger', 'Invalid 4-digit transaction code.');
            header('Location: bill_pay.php');
            exit;
        }
        banking_schedule_bill_payment((int) $user['id'], (int) $_POST['biller_id'], (float) $_POST['amount'], (string) ($_POST['scheduled_for'] ?: date('Y-m-d')), isset($_POST['recurring']), $_POST['frequency'] ?? null, $actor);
        flash('success', $isUsAccount ? 'Bill payment submitted for admin review.' : 'Standing order scheduled.');
        header('Location: bill_pay.php');
        exit;
    }
}

$billers = db()->prepare('SELECT * FROM billers WHERE user_id=? ORDER BY due_day, name');
$billers->execute([$user['id']]);
$billerRows = $billers->fetchAll();
$payments = db()->prepare('SELECT * FROM banking_payments WHERE user_id=? AND payment_type IN ("bill_pay","standing_order") ORDER BY created_at DESC LIMIT 10');
$payments->execute([$user['id']]);
$paymentRows = $payments->fetchAll();
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero bill-hero mb-4">
    <div>
        <div class="eyebrow"><?= $isUsAccount ? 'U.S. Bill Pay' : 'SEPA standing orders' ?></div>
        <h2><?= $isUsAccount ? 'Pay billers from your checking account' : 'Schedule recurring euro payments' ?></h2>
        <p><?= $isUsAccount ? 'Manage utilities, rent, insurance, subscriptions, and other U.S. billers with admin approval before release.' : 'Manage rent, utilities, insurance, subscriptions, and IBAN-based recurring payments.' ?></p>
    </div>
    <i class="fa-solid fa-calendar-check"></i>
</div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="table-card">
            <div class="p-4 d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0"><?= $isUsAccount ? 'Your billers' : 'Your SEPA payees' ?></h5><span class="category-badge"><?= count($billerRows) ?> active</span></div>
            <div class="row g-0">
                <?php foreach ($billerRows as $b): ?>
                    <div class="col-md-6 border-top"><div class="biller-card"><span class="tx-icon"><i class="fa-solid <?= $b['category']==='Utilities'?'fa-bolt':($b['category']==='Internet'?'fa-wifi':'fa-building') ?>"></i></span><div class="flex-grow-1"><strong><?= e($b['name']) ?></strong><div class="small muted"><?= e($b['category']) ?> &middot; <?= e($b['account_mask']) ?></div><div class="small"><?= $isUsAccount ? 'Due day' : 'Execution day' ?> <?= e((string) $b['due_day']) ?> &middot; <?= $b['autopay'] ? ($isUsAccount ? 'Autopay on' : 'Standing order on') : 'Manual review' ?></div></div><span class="status-pill status-success"><?= e(strtoupper($b['status'])) ?></span></div></div>
                <?php endforeach; ?>
                <?php if (!$billerRows): ?>
                    <div class="col-12 border-top"><div class="empty-mini m-4"><?= $isUsAccount ? 'No billers yet. Add a biller when you are ready to schedule payments.' : 'No SEPA payees yet. Add a recipient when you are ready to schedule payments.' ?></div></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-card mt-4"><div class="p-4"><h5 class="fw-bold mb-0"><?= $isUsAccount ? 'Bill Pay history' : 'Standing order history' ?></h5></div><table class="table align-middle mb-0"><tbody><?php foreach ($paymentRows as $p): ?><tr><td><?= e($p['descriptor']) ?><div class="small muted"><?= e($p['scheduled_for'] ?: $p['created_at']) ?> &middot; <?= $p['recurring'] ? e($p['frequency']) : 'One-time' ?></div></td><td><span class="status-pill status-<?= $p['status']==='completed'?'success':($p['status']==='failed'?'danger':($p['status']==='scheduled'?'warning':'info')) ?>"><?= e(strtoupper(str_replace('_', ' ', $p['status']))) ?></span></td><td class="text-end fw-bold tx-debit"><?= money($p['amount'], $currency) ?></td></tr><?php endforeach; ?><?php if (!$paymentRows): ?><tr><td colspan="3" class="text-center muted py-5"><?= $isUsAccount ? 'No bill payments have been scheduled yet.' : 'No standing orders have been scheduled yet.' ?></td></tr><?php endif; ?></tbody></table></div>
    </div>
    <div class="col-xl-4">
        <form class="premium-card p-4 mb-4" method="post">
            <?= csrf_field() ?><h5 class="fw-bold"><?= $isUsAccount ? 'Schedule bill payment' : 'Schedule standing order' ?></h5>
            <select name="biller_id" class="form-select mb-3" <?= !$billerRows ? 'disabled' : '' ?>><?php foreach ($billerRows as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select>
            <input name="amount" type="number" step="0.01" min="1" class="form-control mb-3" placeholder="Amount in <?= e($currency) ?>" required <?= !$billerRows ? 'disabled' : '' ?>>
            <input name="scheduled_for" type="date" class="form-control mb-3" value="<?= e(date('Y-m-d')) ?>" <?= !$billerRows ? 'disabled' : '' ?>>
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="recurring" <?= !$billerRows ? 'disabled' : '' ?>> <?= $isUsAccount ? 'Recurring bill payment' : 'Recurring SEPA payment' ?></label>
            <select name="frequency" class="form-select mb-3" <?= !$billerRows ? 'disabled' : '' ?>><option>Monthly</option><option>Weekly</option><option>Quarterly</option></select>
            <input name="transaction_pin" type="password" inputmode="numeric" minlength="4" maxlength="4" pattern="\d{4}" class="form-control mb-3" placeholder="4-digit transaction code" required <?= !$billerRows ? 'disabled' : '' ?>>
            <button name="schedule_payment" value="1" class="btn btn-gold w-100" <?= !$billerRows ? 'disabled' : '' ?>><?= $isUsAccount ? 'Submit bill payment' : 'Confirm standing order' ?></button>
        </form>
        <form class="premium-card p-4" method="post">
            <?= csrf_field() ?><h5 class="fw-bold"><?= $isUsAccount ? 'Add biller' : 'Add SEPA payee' ?></h5>
            <input name="name" class="form-control mb-2" placeholder="<?= $isUsAccount ? 'Biller name' : 'Payee name' ?>" required>
            <select name="category" class="form-select mb-2"><?php foreach (['Utilities','Internet','Insurance','Phone','Water','Card','Rent','Subscription'] as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
            <input name="account_number" class="form-control mb-2" placeholder="<?= $isUsAccount ? 'Account or customer reference' : 'IBAN or customer reference' ?>" required>
            <input name="due_day" type="number" min="1" max="28" class="form-control mb-2" placeholder="<?= $isUsAccount ? 'Due day' : 'Execution day' ?>" required>
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="autopay"> <?= $isUsAccount ? 'Enable autopay' : 'Enable standing order' ?></label>
            <button name="add_biller" value="1" class="btn btn-navy w-100"><?= $isUsAccount ? 'Add biller' : 'Add SEPA payee' ?></button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
