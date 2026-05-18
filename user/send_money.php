<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/brevo.php';
$user = require_user();
require_unrestricted_account($user);
$account = user_account((int) $user['id']);
$isUsAccount = user_is_us_account($user, $account);
$currency = user_account_currency($user, $account);
$pageTitle = $isUsAccount ? 'Zelle' : 'SEPA Instant';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $actor = banking_actor('customer', (int) $user['id']);
    if (isset($_POST['add_recipient'])) {
        try {
            if ($isUsAccount) {
                banking_add_payment_recipient((int) $user['id'], (string) $_POST['name'], (string) ($_POST['email'] ?? ''), (string) ($_POST['phone'] ?? ''), (string) $_POST['nickname'], $actor);
                flash('success', 'Zelle recipient added.');
            } else {
                banking_add_payment_recipient((int) $user['id'], (string) $_POST['name'], '', '', (string) $_POST['nickname'], $actor, (string) $_POST['iban'], (string) $_POST['bic']);
                flash('success', 'SEPA recipient added.');
            }
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        header('Location: send_money.php');
        exit;
    }

    if (isset($_POST['review_payment'])) {
        $amount = max(0, (float) $_POST['amount']);
        $recipientId = (int) $_POST['recipient_id'];
        $recipientStmt = db()->prepare('SELECT * FROM payment_recipients WHERE user_id=? AND id=?');
        $recipientStmt->execute([$user['id'], $recipientId]);
        $recipient = $recipientStmt->fetch();
        if ($recipient && $amount > 0) {
            $_SESSION['instant_payment_review'] = ['recipient_id' => $recipientId, 'amount' => $amount, 'memo' => trim((string) ($_POST['memo'] ?? ''))];
            header('Location: send_money.php?review=1');
            exit;
        }
        flash('danger', 'Choose a recipient and amount to continue.');
    }

    if (isset($_POST['confirm_payment'])) {
        $review = $_SESSION['instant_payment_review'] ?? null;
        if ($review && verify_transaction_pin($user, (string) $_POST['transaction_pin'])) {
            $otpContext = hash('sha256', 'send_money|' . (int) $user['id'] . '|' . json_encode($review));
            $otpVerified = ($_SESSION['transfer_otp_verified_context'] ?? '') === $otpContext && (time() - (int) ($_SESSION['transfer_otp_verified_at'] ?? 0)) <= 600;
            if (!$otpVerified) {
                if (!is_valid_sms_phone((string) ($user['phone'] ?? ''))) {
                    flash('danger', 'Add a valid phone number with country code before sending money.');
                    header('Location: profile.php');
                    exit;
                }
                $sent = sms_otp_create((int) $user['id'], (string) $user['phone'], 'transfer', 10);
                if (($sent['ok'] ?? false) || isset($sent['retry_at'])) {
                    $_SESSION['pending_transfer_context'] = $otpContext;
                    $_SESSION['pending_transfer_return'] = 'user/send_money.php?review=1';
                    flash('info', 'Enter the SMS verification code to continue this payment.');
                    header('Location: ../otp_verify.php?purpose=transfer');
                    exit;
                }
                flash('danger', (string) ($sent['error'] ?? 'SMS verification could not start. Try again.'));
                header('Location: send_money.php?review=1');
                exit;
            }
            $recipientStmt = db()->prepare('SELECT * FROM payment_recipients WHERE user_id=? AND id=?');
            $recipientStmt->execute([$user['id'], (int) $review['recipient_id']]);
            $recipient = $recipientStmt->fetch();
            if (!$recipient) {
                flash('danger', 'Recipient not found.');
                header('Location: send_money.php');
                exit;
            }
            try {
                if ($isUsAccount) {
                    $result = banking_process_instant_payment((int) $user['id'], (int) $recipient['id'], (float) $review['amount'], (string) $review['memo'], $actor);
                    $reference = $result['confirmation'] ?? ('ZL' . $result['payment_id']);
                    flash('success', 'Zelle payment submitted for admin review. Reference ' . $reference . '.');
                } else {
                    $paymentId = banking_process_sepa_transfer((int) $user['id'], (string) $recipient['name'], (string) $recipient['iban'], (string) ($recipient['bic'] ?: DEFAULT_BIC), 'outbound', (float) $review['amount'], date('Y-m-d'), true, false, null, $actor);
                    flash('success', 'SEPA Instant transfer submitted. Reference SCTI' . $paymentId . '.');
                }
            } catch (Throwable $e) {
                flash('danger', $e->getMessage());
                header('Location: send_money.php');
                exit;
            }
            unset($_SESSION['instant_payment_review']);
            unset($_SESSION['transfer_otp_verified_at'], $_SESSION['transfer_otp_verified_context']);
            header('Location: send_money.php');
            exit;
        }
        flash('danger', 'Verification failed. Check your 4-digit transaction code.');
    }
}

$recipients = db()->prepare('SELECT * FROM payment_recipients WHERE user_id=? ORDER BY COALESCE(last_used_at, created_at) DESC');
$recipients->execute([$user['id']]);
$recipientRows = $recipients->fetchAll();
$historyType = $isUsAccount ? 'zelle' : 'sepa_instant';
$history = db()->prepare('SELECT * FROM banking_payments WHERE user_id=? AND payment_type=? ORDER BY created_at DESC LIMIT 8');
$history->execute([$user['id'], $historyType]);
$review = $_SESSION['instant_payment_review'] ?? null;
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero send-hero mb-4">
    <div>
        <div class="eyebrow"><?= $isUsAccount ? 'Zelle' : 'SEPA Instant' ?></div>
        <h2><?= $isUsAccount ? 'Send money by email or phone' : 'Send euros by IBAN' ?></h2>
        <p><?= $isUsAccount ? 'Use a recipient email or mobile number, your 4-digit transaction code, and admin review before release.' : 'Use account holder name, IBAN, BIC/SWIFT, and your 4-digit transaction code. Admin approval is required before completion.' ?></p>
    </div>
    <i class="fa-solid fa-bolt"></i>
</div>
<?= deposit_protection_badge($user, $account, 'mb-4') ?>
<div class="row g-4">
    <div class="col-xl-7">
        <form class="premium-card p-4" method="post">
            <?= csrf_field() ?>
            <?php if (!isset($_GET['review'])): ?>
                <h5 class="fw-bold"><?= $isUsAccount ? 'New Zelle payment' : 'New SEPA Instant transfer' ?></h5>
                <label class="form-label"><?= $isUsAccount ? 'Recipient' : 'Transfer recipient' ?></label>
                <select name="recipient_id" class="form-select mb-3" <?= !$recipientRows ? 'disabled' : '' ?>>
                    <?php foreach ($recipientRows as $r): ?>
                        <?php $recipientDetail = $isUsAccount ? ($r['email'] ?: $r['phone']) : format_iban_display($r['iban'] ?? ''); ?>
                        <option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?><?= $recipientDetail ? ' &middot; ' . e($recipientDetail) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Amount in <?= e($currency) ?></label>
                <input name="amount" type="number" step="0.01" min="1" class="form-control mb-3" required <?= !$recipientRows ? 'disabled' : '' ?>>
                <div class="quick-amounts mb-3"><?php foreach ([25,50,100,250] as $amount): ?><button type="button" class="btn btn-light border" data-quick-amount="<?= $amount ?>"><?= e($currency) ?> <?= $amount ?></button><?php endforeach; ?></div>
                <input name="memo" class="form-control mb-3" placeholder="<?= $isUsAccount ? 'Memo optional' : 'Payment reference optional' ?>" <?= !$recipientRows ? 'disabled' : '' ?>>
                <button name="review_payment" value="1" class="btn btn-gold" <?= !$recipientRows ? 'disabled' : '' ?>><i class="fa-solid fa-paper-plane me-1"></i>Review payment</button>
                <?php if (!$recipientRows): ?><div class="small muted mt-3"><?= $isUsAccount ? 'Add a Zelle recipient before sending money.' : 'Add a SEPA recipient before sending an instant transfer.' ?></div><?php endif; ?>
            <?php else: ?>
                <h5 class="fw-bold">Review and verify</h5>
                <div class="review-panel mb-3">
                    <span>Amount</span><strong><?= money($review['amount'] ?? 0, $currency) ?></strong>
                    <span>Delivery</span><strong><?= $isUsAccount ? 'Zelle admin review' : 'SEPA Instant review' ?></strong>
                    <span>Status</span><strong>Admin approval required</strong>
                </div>
                <div class="otp-panel mb-3"><div class="d-flex justify-content-between gap-3 flex-wrap"><span>Enter your 4-digit transaction code. This payment will be sent to admin review.</span></div></div>
                <input name="transaction_pin" type="password" inputmode="numeric" minlength="4" maxlength="4" pattern="\d{4}" class="form-control mb-3" placeholder="4-digit transaction code" required>
                <button name="confirm_payment" value="1" class="btn btn-gold"><?= $isUsAccount ? 'Submit Zelle payment' : 'Submit SEPA Instant' ?></button>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-xl-5">
        <form class="premium-card p-4 mb-4" method="post">
            <?= csrf_field() ?>
            <h5 class="fw-bold"><?= $isUsAccount ? 'Add Zelle recipient' : 'Add SEPA recipient' ?></h5>
            <input name="name" class="form-control mb-2" placeholder="<?= $isUsAccount ? 'Recipient name' : 'Account holder name' ?>" required>
            <?php if ($isUsAccount): ?>
                <input name="email" type="email" class="form-control mb-2" placeholder="Email address">
                <input name="phone" class="form-control mb-2" placeholder="Mobile number">
            <?php else: ?>
                <input name="iban" class="form-control mb-2 text-uppercase" placeholder="IBAN" data-format-iban required>
                <input name="bic" class="form-control mb-2 text-uppercase" placeholder="BIC/SWIFT" value="<?= e(DEFAULT_BIC) ?>">
            <?php endif; ?>
            <input name="nickname" class="form-control mb-3" placeholder="Nickname">
            <button name="add_recipient" value="1" class="btn btn-navy">Save recipient</button>
        </form>
        <div class="table-card p-4"><h5 class="fw-bold">Recent recipients</h5><?php foreach ($recipientRows as $r): ?><?php $detail = $isUsAccount ? ($r['email'] ?: $r['phone']) : format_iban_display($r['iban'] ?? ''); ?><div class="recipient-row"><span class="tx-icon"><i class="fa-solid fa-user"></i></span><div><strong><?= e($r['name']) ?></strong><div class="small muted"><?= e($detail ?: 'No contact detail') ?></div></div></div><?php endforeach; ?><?php if (!$recipientRows): ?><div class="empty-mini mt-3"><?= $isUsAccount ? 'No Zelle recipients yet.' : 'No SEPA recipients yet.' ?></div><?php endif; ?></div>
    </div>
</div>
<div class="table-card mt-4"><div class="p-4"><h5 class="fw-bold mb-0"><?= $isUsAccount ? 'Zelle history' : 'SEPA Instant history' ?></h5></div><table class="table mb-0 align-middle"><tbody><?php foreach ($history as $p): ?><tr><td><?= e($p['descriptor']) ?><div class="small muted"><?= e($p['created_at']) ?></div></td><td><span class="status-pill status-<?= $p['status']==='completed'?'success':($p['status']==='failed'?'danger':'warning') ?>"><?= e(strtoupper(str_replace('_', ' ', $p['status']))) ?></span></td><td class="text-end fw-bold tx-debit"><?= money($p['amount'], $currency) ?></td></tr><?php endforeach; ?><?php if ($history->rowCount() === 0): ?><tr><td colspan="3" class="text-center muted py-5"><?= $isUsAccount ? 'No Zelle history yet.' : 'No SEPA Instant history yet.' ?></td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
