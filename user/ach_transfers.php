<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/brevo.php';
$user = require_user();
require_unrestricted_account($user);
$account = user_account((int) $user['id']);
$isUsAccount = user_is_us_account($user, $account);
$currency = user_account_currency($user, $account);
$pageTitle = $isUsAccount ? 'ACH Transfers' : 'SEPA Transfers';

$pendingAch = $_SESSION['ach_transfer_review'] ?? null;
$pendingContext = $pendingAch ? hash('sha256', 'ach_transfer|' . (int) $user['id'] . '|' . json_encode($pendingAch)) : '';
if ($pendingAch && (!SMS_OTP_ENABLED || (isset($_GET['otp']) && ($_SESSION['transfer_otp_verified_context'] ?? '') === $pendingContext && (time() - (int) ($_SESSION['transfer_otp_verified_at'] ?? 0)) <= 600))) {
    try {
        $actor = banking_actor('customer', (int) $user['id']);
        if ($isUsAccount) {
            banking_process_ach_transfer((int) $user['id'], trim((string) $pendingAch['institution_name']), (string) $pendingAch['direction'], (float) $pendingAch['amount'], (string) ($pendingAch['scheduled_for'] ?: date('Y-m-d', strtotime('+1 weekday'))), !empty($pendingAch['recurring']), $pendingAch['frequency'] ?? null, $actor);
            flash('success', 'ACH transfer submitted for admin review.');
        } else {
            banking_process_sepa_transfer((int) $user['id'], trim((string) $pendingAch['recipient_name']), (string) $pendingAch['iban'], (string) ($pendingAch['bic'] ?: DEFAULT_BIC), (string) $pendingAch['direction'], (float) $pendingAch['amount'], (string) ($pendingAch['scheduled_for'] ?: date('Y-m-d', strtotime('+1 weekday'))), false, !empty($pendingAch['recurring']), $pendingAch['frequency'] ?? null, $actor);
            flash('success', 'SEPA transfer submitted for processing.');
        }
        unset($_SESSION['ach_transfer_review'], $_SESSION['transfer_otp_verified_at'], $_SESSION['transfer_otp_verified_context']);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: ach_transfers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!verify_transaction_pin($user, (string) ($_POST['transaction_pin'] ?? ''))) {
        flash('danger', 'Invalid 4-digit transaction code.');
        header('Location: ach_transfers.php');
        exit;
    }
    $_SESSION['ach_transfer_review'] = [
        'institution_name' => trim((string) ($_POST['institution_name'] ?? '')),
        'recipient_name' => trim((string) ($_POST['recipient_name'] ?? '')),
        'iban' => (string) ($_POST['iban'] ?? ''),
        'bic' => (string) ($_POST['bic'] ?? DEFAULT_BIC),
        'direction' => (string) ($_POST['direction'] ?? 'outbound'),
        'amount' => (float) ($_POST['amount'] ?? 0),
        'scheduled_for' => (string) ($_POST['scheduled_for'] ?: date('Y-m-d', strtotime('+1 weekday'))),
        'recurring' => isset($_POST['recurring']),
        'frequency' => $_POST['frequency'] ?? null,
    ];
    $otpContext = hash('sha256', 'ach_transfer|' . (int) $user['id'] . '|' . json_encode($_SESSION['ach_transfer_review']));
    if (!SMS_OTP_ENABLED) {
        header('Location: ach_transfers.php?otp=verified');
        exit;
    }
    if (!is_valid_sms_phone((string) ($user['phone'] ?? ''))) {
        flash('danger', 'Add a valid phone number with country code before submitting transfers.');
        header('Location: profile.php');
        exit;
    }
    $sent = sms_otp_create((int) $user['id'], (string) $user['phone'], 'transfer', 10);
    if (($sent['ok'] ?? false) || isset($sent['retry_at'])) {
        $_SESSION['pending_transfer_context'] = $otpContext;
        $_SESSION['pending_transfer_return'] = 'user/ach_transfers.php?otp=verified';
        flash('info', 'Enter the SMS verification code to continue this transfer.');
        header('Location: ../otp_verify.php?purpose=transfer');
        exit;
    }
    flash('danger', (string) ($sent['error'] ?? 'SMS verification could not start. Try again.'));
    header('Location: ach_transfers.php');
    exit;
}

$paymentTypes = $isUsAccount ? ['ach'] : ['sepa', 'sepa_instant'];
$placeholders = implode(',', array_fill(0, count($paymentTypes), '?'));
$payments = db()->prepare('SELECT * FROM banking_payments WHERE user_id=? AND payment_type IN (' . $placeholders . ') ORDER BY created_at DESC LIMIT 12');
$payments->execute(array_merge([$user['id']], $paymentTypes));
$paymentRows = $payments->fetchAll();
$displayIban = format_iban_display($account['iban'] ?? '');
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero ach-hero mb-4">
    <div>
        <div class="eyebrow"><?= $isUsAccount ? 'ACH network' : 'SEPA Credit Transfer' ?></div>
        <h2><?= $isUsAccount ? 'Move money with ACH' : 'Move money with IBAN' ?></h2>
        <p><?= $isUsAccount ? 'Create inbound or outbound U.S. bank transfers using routing and account rails with admin approval.' : 'Create euro bank transfers using the European SEPA network, IBAN validation, BIC/SWIFT, and review controls.' ?></p>
    </div>
    <i class="fa-solid fa-building-columns"></i>
</div>
<div class="row g-4">
    <div class="col-xl-5">
        <form class="premium-card p-4" method="post">
            <?= csrf_field() ?><h5 class="fw-bold"><?= $isUsAccount ? 'Create ACH transfer' : 'Create SEPA transfer' ?></h5>
            <select name="direction" class="form-select mb-3">
                <option value="outbound"><?= $isUsAccount ? 'Outbound ACH debit' : 'Outbound SEPA transfer' ?></option>
                <option value="inbound"><?= $isUsAccount ? 'Inbound ACH credit' : 'Inbound SEPA funding' ?></option>
            </select>
            <?php if ($isUsAccount): ?>
                <input name="institution_name" class="form-control mb-3" placeholder="External bank or institution name" required>
            <?php else: ?>
                <input name="recipient_name" class="form-control mb-3" placeholder="Account holder name" required>
                <input name="iban" class="form-control mb-3 text-uppercase" placeholder="DE89 3704 0044 0532 0130 00" data-format-iban required>
                <input name="bic" class="form-control mb-3 text-uppercase" placeholder="BIC/SWIFT, e.g. DEUTDEFFXXX" value="<?= e(DEFAULT_BIC) ?>">
            <?php endif; ?>
            <input name="amount" type="number" step="0.01" min="1" class="form-control mb-3" placeholder="Amount in <?= e($currency) ?>" required>
            <input name="scheduled_for" type="date" class="form-control mb-3" value="<?= e(date('Y-m-d', strtotime('+1 weekday'))) ?>">
            <label class="form-check mb-2"><input class="form-check-input" name="recurring" type="checkbox"> <?= $isUsAccount ? 'Recurring ACH transfer' : 'Recurring SEPA transfer' ?></label>
            <select name="frequency" class="form-select mb-3"><option>Monthly</option><option>Weekly</option><option>Biweekly</option><option>Quarterly</option></select>
            <input name="transaction_pin" type="password" inputmode="numeric" minlength="4" maxlength="4" pattern="\d{4}" class="form-control mb-3" placeholder="4-digit transaction code" required>
            <button class="btn btn-gold w-100">Submit for verification</button>
        </form>
    </div>
    <div class="col-xl-7">
        <div class="timeline-card mb-4">
            <?php if ($isUsAccount): ?>
                <div><strong>Day 0</strong><span>Submitted</span></div><div><strong>1-2 days</strong><span>ACH review</span></div><div><strong>Settlement</strong><span>Admin release</span></div>
            <?php else: ?>
                <div><strong>Day 0</strong><span>Submitted</span></div><div><strong>Day 0-1</strong><span>SEPA review</span></div><div><strong>Day 1</strong><span>Funds settle</span></div>
            <?php endif; ?>
        </div>
        <div class="premium-card p-4 mb-4">
            <h5 class="fw-bold"><?= $isUsAccount ? 'Your U.S. account details' : 'Your SEPA details' ?></h5>
            <?php if ($isUsAccount): ?>
                <div class="review-panel mb-0"><span>Account</span><strong><?= e(mask_account($account['account_number'] ?? '')) ?></strong><span>Routing</span><strong><?= e($account['routing_number'] ?? US_ROUTING_NUMBER) ?></strong><span>Account holder</span><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong></div>
            <?php else: ?>
                <div class="review-panel mb-0"><span>IBAN</span><strong><?= e($displayIban ?: 'Pending') ?></strong><span>BIC/SWIFT</span><strong><?= e($account['bic'] ?? DEFAULT_BIC) ?></strong><span>Account holder</span><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong></div>
            <?php endif; ?>
        </div>
        <div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0"><?= $isUsAccount ? 'ACH history' : 'SEPA history' ?></h5></div><table class="table align-middle mb-0"><tbody><?php foreach ($paymentRows as $p): ?><tr><td><?= e($p['descriptor']) ?><div class="small muted"><?= e($p['payee_name']) ?> &middot; <?= e($p['scheduled_for']) ?></div></td><td><span class="status-pill status-<?= $p['status']==='completed'?'success':($p['status']==='failed'?'danger':'warning') ?>"><?= e(strtoupper(str_replace('_', ' ', $p['status']))) ?></span></td><td class="text-end fw-bold <?= $p['amount'] > 0 ? 'tx-credit' : 'tx-debit' ?>"><?= money($p['amount'], $currency) ?></td></tr><?php endforeach; ?><?php if (!$paymentRows): ?><tr><td colspan="3" class="text-center muted py-5"><?= $isUsAccount ? 'No ACH transfer history yet.' : 'No SEPA transfer history yet.' ?></td></tr><?php endif; ?></tbody></table></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
