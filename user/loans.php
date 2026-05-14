<?php
$pageTitle = 'Loans';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_banking_schema();
$user = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        banking_create_loan_application(
            (int) $user['id'],
            (string) ($_POST['loan_type'] ?? 'Personal Loan'),
            (float) ($_POST['amount'] ?? 0),
            (int) ($_POST['term_months'] ?? 12),
            (string) ($_POST['purpose'] ?? ''),
            banking_actor('customer', (int) $user['id'])
        );
        flash('success', 'Loan request submitted for review.');
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: loans.php');
    exit;
}

$account = user_account((int) $user['id']);
$currency = user_account_currency($user, $account);
$stmt = db()->prepare('SELECT * FROM loan_applications WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$loans = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="banking-hero mb-4">
    <div>
        <div class="eyebrow">Lending center</div>
        <h2>Loans and credit review</h2>
        <p>Submit loan requests for admin review and track every decision from the same banking login.</p>
    </div>
    <i class="fa-solid fa-hand-holding-dollar"></i>
</div>
<div class="row g-4">
    <div class="col-xl-7">
        <div class="table-card">
            <div class="p-4"><h5 class="fw-bold mb-0">Loan requests</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td>
                                <strong><?= e($loan['loan_type']) ?></strong>
                                <div class="small muted">Ref <?= e($loan['reference_code']) ?> &middot; <?= e(date('M j, Y g:i A', strtotime((string) $loan['created_at']))) ?></div>
                                <?php if (trim((string) $loan['purpose']) !== ''): ?><div class="small muted"><?= e($loan['purpose']) ?></div><?php endif; ?>
                            </td>
                            <td><?= e((string) $loan['term_months']) ?> months</td>
                            <td><span class="status-pill status-<?= $loan['status']==='approved'?'success':($loan['status']==='rejected'?'danger':'warning') ?>"><?= e(strtoupper(str_replace('_', ' ', $loan['status']))) ?></span></td>
                            <td class="text-end fw-bold"><?= money($loan['amount'], $loan['currency'] ?: $currency) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$loans): ?><tr><td colspan="4" class="text-center muted py-5">No loan requests yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <form class="premium-card p-4" method="post">
            <?= csrf_field() ?>
            <span class="tx-icon mb-3"><i class="fa-solid fa-file-signature"></i></span>
            <h5 class="fw-bold">Request a loan</h5>
            <p class="muted">Applications stay pending until an admin reviews them.</p>
            <label class="form-label">Loan type</label>
            <select name="loan_type" class="form-select mb-3">
                <option>Personal Loan</option>
                <option>Auto Loan</option>
                <option>Home Improvement Loan</option>
                <option>Business Line of Credit</option>
                <option>Mortgage Review</option>
            </select>
            <label class="form-label">Amount</label>
            <input name="amount" type="number" step="0.01" min="1" class="form-control mb-3" placeholder="0.00" required>
            <label class="form-label">Term</label>
            <select name="term_months" class="form-select mb-3">
                <?php foreach ([6, 12, 24, 36, 48, 60, 84, 120, 180, 240, 360] as $months): ?>
                    <option value="<?= $months ?>"><?= $months ?> months</option>
                <?php endforeach; ?>
            </select>
            <label class="form-label">Purpose</label>
            <textarea name="purpose" class="form-control mb-3" rows="3" placeholder="Tell operations what this loan is for"></textarea>
            <button class="btn btn-navy w-100">Submit for review</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
