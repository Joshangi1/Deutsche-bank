<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/sms.php';
$user = require_user();
require_unrestricted_account($user);
$account = user_account((int) $user['id']);
$isUsAccount = user_is_us_account($user, $account);
$currency = user_account_currency($user, $account);
$pageTitle = $isUsAccount ? 'Wire Transfers' : 'Transfer Review';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pin = (string) ($_POST['transaction_pin'] ?? '');

    if (!empty($_POST['review'])) {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($isUsAccount) {
            $routing = preg_replace('/\D+/', '', (string) ($_POST['routing_number'] ?? ''));
            $accountNumber = preg_replace('/\D+/', '', (string) ($_POST['account_number'] ?? ''));
            if (!preg_match('/^\d{9}$/', $routing) || !preg_match('/^\d{4,17}$/', $accountNumber) || $amount <= 0) {
                flash('danger', 'Please enter a valid 9-digit routing number, account number, and amount.');
                header('Location: transfers.php');
                exit;
            }
            $_SESSION['transfer_review'] = [
                'region' => 'us',
                'recipient_name' => trim((string) $_POST['recipient_name']),
                'routing_number' => $routing,
                'account_number' => $accountNumber,
                'amount' => $amount,
                'memo' => trim((string) ($_POST['memo'] ?? '')),
            ];
        } else {
            $iban = normalize_iban((string) ($_POST['iban'] ?? ''));
            $bic = normalize_bic((string) ($_POST['bic'] ?? DEFAULT_BIC));
            if (!is_valid_german_iban($iban) || !is_valid_bic($bic) || $amount <= 0) {
                flash('danger', 'Please enter a valid German IBAN, BIC/SWIFT, and amount.');
                header('Location: transfers.php');
                exit;
            }
            $_SESSION['transfer_review'] = [
                'region' => 'eu',
                'recipient_name' => trim((string) $_POST['recipient_name']),
                'iban' => $iban,
                'bic' => $bic,
                'amount' => $amount,
                'memo' => trim((string) ($_POST['memo'] ?? '')),
            ];
        }
        header('Location: transfers.php?review=1');
        exit;
    }

    $review = $_SESSION['transfer_review'] ?? null;

    if ($review && verify_transaction_pin($user, $pin)) {
        $otpContext = hash('sha256', 'transfer|' . (int) $user['id'] . '|' . json_encode($review));
        $otpVerified = ($_SESSION['transfer_otp_verified_context'] ?? '') === $otpContext && (time() - (int) ($_SESSION['transfer_otp_verified_at'] ?? 0)) <= 600;
        if (!$otpVerified && SMS_OTP_ENABLED) {
            if (!is_valid_sms_phone((string) ($user['phone'] ?? ''))) {
                flash('danger', 'Add a valid phone number with country code before submitting transfers.');
                header('Location: profile.php');
                exit;
            }
            $sent = sms_otp_create((int) $user['id'], (string) $user['phone'], 'transfer');
            if (($sent['ok'] ?? false) || isset($sent['retry_at'])) {
                $_SESSION['pending_transfer_context'] = $otpContext;
                $_SESSION['pending_transfer_return'] = 'user/transfers.php?review=1';
                flash('info', 'Enter the SMS verification code to continue this transfer.');
                header('Location: ../otp_verify.php?purpose=transfer');
                exit;
            }
            flash('danger', (string) ($sent['error'] ?? 'SMS verification could not start. Try again.'));
            header('Location: transfers.php?review=1');
            exit;
        }
        $actor = banking_actor('customer', (int) $user['id']);
        if (($review['region'] ?? '') === 'us') {
            $descriptor = 'WIRE TRANSFER TO ' . strtoupper((string) $review['recipient_name']) . ' ROUTING ' . (string) $review['routing_number'];
            $transactionId = banking_create_transaction([
                'user_id' => $user['id'],
                'transaction_type' => 'external_transfer',
                'description' => $descriptor,
                'amount' => -abs((float) $review['amount']),
                'status' => 'pending',
                'customer_event' => 'transfer_pending',
            ], $actor);
            $paymentId = banking_create_payment([
                'user_id' => $user['id'],
                'payment_type' => 'transfer',
                'payee_name' => (string) $review['recipient_name'],
                'descriptor' => $descriptor . ' ACCT ' . mask_account((string) $review['account_number']),
                'amount' => -abs((float) $review['amount']),
                'direction' => 'outbound',
                'status' => 'pending_review',
                'scheduled_for' => date('Y-m-d'),
                'confirmation_code' => 'WIRE' . random_int(100000, 999999),
            ], $actor);
            db()->prepare('UPDATE banking_payments SET transaction_id=? WHERE id=?')->execute([$transactionId, $paymentId]);
            banking_emit_event('transfer.pending_review', ['system_detail' => 'US external transfer entered admin review workflow.'], $actor, (int) $user['id'], 'transfer', null);
            flash('success', 'Transfer confirmation complete. Status: pending admin review.');
        } else {
            banking_process_sepa_transfer((int) $user['id'], (string) $review['recipient_name'], (string) $review['iban'], (string) $review['bic'], 'outbound', (float) $review['amount'], date('Y-m-d'), false, false, null, $actor);
            flash('success', 'SEPA transfer confirmation complete. Status: pending admin review.');
        }
        unset($_SESSION['transfer_review']);
        unset($_SESSION['transfer_otp_verified_at'], $_SESSION['transfer_otp_verified_context']);
        header('Location: transfers.php');
        exit;
    }
    flash('danger', 'Invalid 4-digit transaction code.');
}
$review = $_SESSION['transfer_review'] ?? null;
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<?= deposit_protection_badge($user, $account, 'mb-4') ?>
<div class="row g-4">
    <div class="col-lg-7">
        <form class="premium-card p-4" method="post">
            <?= csrf_field() ?>
            <h5 class="fw-bold"><?= $isUsAccount ? 'New U.S. bank transfer' : 'New SEPA transfer' ?></h5>
            <?php if (!isset($_GET['review'])): ?>
                <label class="form-label">Account holder name</label>
                <input name="recipient_name" class="form-control mb-3" required>
                <?php if ($isUsAccount): ?>
                    <label class="form-label">Routing number</label>
                    <input name="routing_number" class="form-control mb-3" inputmode="numeric" maxlength="9" required>
                    <label class="form-label">Account number</label>
                    <input name="account_number" class="form-control mb-3" inputmode="numeric" maxlength="17" required>
                    <label class="form-label">Amount in <?= e($currency) ?></label>
                <?php else: ?>
                    <label class="form-label">IBAN</label>
                    <input name="iban" class="form-control mb-3 text-uppercase" placeholder="DE89 3704 0044 0532 0130 00" data-format-iban required>
                    <label class="form-label">BIC/SWIFT</label>
                    <input name="bic" class="form-control mb-3 text-uppercase" value="<?= e(DEFAULT_BIC) ?>" required>
                    <label class="form-label">Amount in <?= e($currency) ?></label>
                <?php endif; ?>
                <input name="amount" type="number" step="0.01" min="1" class="form-control mb-3" required>
                <input name="memo" class="form-control mb-3" placeholder="Payment reference optional">
                <button name="review" value="1" class="btn btn-navy">Review transfer</button>
            <?php else: ?>
                <div class="review-panel mb-3">
                    <span>Recipient</span><strong><?= e($review['recipient_name'] ?? '') ?></strong>
                    <?php if (($review['region'] ?? '') === 'us'): ?>
                        <span>Routing</span><strong><?= e($review['routing_number'] ?? '') ?></strong>
                        <span>Account</span><strong><?= e(mask_account((string) ($review['account_number'] ?? ''))) ?></strong>
                    <?php else: ?>
                        <span>IBAN</span><strong><?= e(format_iban_display($review['iban'] ?? '')) ?></strong>
                        <span>BIC/SWIFT</span><strong><?= e($review['bic'] ?? '') ?></strong>
                    <?php endif; ?>
                    <span>Amount</span><strong><?= money($review['amount'] ?? 0, $currency) ?></strong>
                </div>
                <div class="otp-panel mb-3"><div class="d-flex justify-content-between gap-3 flex-wrap"><span>Enter your 4-digit transaction code. This transfer will require admin approval before completion.</span></div></div>
                <input name="transaction_pin" type="password" inputmode="numeric" minlength="4" maxlength="4" pattern="\d{4}" class="form-control mb-3" placeholder="4-digit transaction code" required>
                <button class="btn btn-gold">Confirm transfer</button>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="table-card p-4">
            <h5 class="fw-bold"><?= $isUsAccount ? 'Your U.S. account details' : 'Your SEPA account details' ?></h5>
            <p class="muted"><?= $isUsAccount ? 'Use these values when another bank requests your account and routing details.' : 'Use these values when another bank requests your German/European transfer details.' ?></p>
            <?php if ($isUsAccount): ?>
                <div class="review-panel mb-0"><span>Account</span><strong><?= e(mask_account($account['account_number'] ?? '')) ?></strong><span>Routing</span><strong><?= e($account['routing_number'] ?? US_ROUTING_NUMBER) ?></strong><span>Account holder</span><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong></div>
            <?php else: ?>
                <div class="review-panel mb-0"><span>IBAN</span><strong><?= e(format_iban_display($account['iban'] ?? '')) ?></strong><span>BIC/SWIFT</span><strong><?= e($account['bic'] ?? DEFAULT_BIC) ?></strong><span>Account holder</span><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
