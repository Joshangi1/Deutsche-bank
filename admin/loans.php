<?php
$pageTitle = 'Loan Review';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_banking_schema();
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $loanId = (int) ($_POST['loan_id'] ?? 0);
    $action = (string) ($_POST['loan_action'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    $stmt = db()->prepare('SELECT * FROM loan_applications WHERE id=? LIMIT 1');
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    if ($loan && in_array($loan['status'], ['pending_review'], true) && in_array($action, ['approved', 'rejected'], true)) {
        db()->prepare('UPDATE loan_applications SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?')
            ->execute([$action, (int) $admin['id'], $note, $loanId]);
        log_admin((int) $admin['id'], 'loan_' . $action, 'Reviewed loan application ' . $loan['reference_code'], (int) $loan['user_id'], $loan, ['status' => $action, 'note' => $note]);
        notify_customer_event((int) $loan['user_id'], $action === 'approved' ? 'transfer_completed' : 'security_alert', [
            'message' => $action === 'approved' ? 'Your loan request has been approved.' : 'Your loan request was not approved.',
        ]);
        flash('success', 'Loan application ' . $action . '.');
    } else {
        flash('danger', 'Loan request could not be reviewed.');
    }
    header('Location: loans.php');
    exit;
}

$status = trim((string) ($_GET['status'] ?? 'pending_review'));
$allowedStatuses = ['pending_review', 'approved', 'rejected', 'cancelled', 'all'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'pending_review';
}
$sql = 'SELECT l.*, u.first_name, u.last_name, u.email, u.verification_status FROM loan_applications l JOIN users u ON u.id=l.user_id';
$params = [];
if ($status !== 'all') {
    $sql .= ' WHERE l.status=?';
    $params[] = $status;
}
$sql .= ' ORDER BY l.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="banking-hero mb-4">
    <div>
        <div class="eyebrow">Credit operations</div>
        <h2>Loan applications</h2>
        <p>Review customer loan requests, approve or reject applications, and keep a full admin action trail.</p>
    </div>
    <i class="fa-solid fa-hand-holding-dollar"></i>
</div>
<div class="table-card">
    <div class="p-4 d-flex flex-wrap justify-content-between gap-3 align-items-center">
        <h5 class="fw-bold mb-0">Applications</h5>
        <form class="d-flex gap-2" method="get">
            <select name="status" class="form-select">
                <?php foreach ($allowedStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $option))) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-light border">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>User</th><th>Loan</th><th>Amount</th><th>Status</th><th>Review</th></tr></thead>
            <tbody>
            <?php foreach ($loans as $loan): ?>
                <tr>
                    <td><strong><?= e($loan['first_name'] . ' ' . $loan['last_name']) ?></strong><div class="small muted"><?= e($loan['email']) ?> &middot; KYC <?= e(strtoupper((string) $loan['verification_status'])) ?></div></td>
                    <td><strong><?= e($loan['loan_type']) ?></strong><div class="small muted">Ref <?= e($loan['reference_code']) ?> &middot; <?= e((string) $loan['term_months']) ?> months</div><div class="small muted"><?= e($loan['purpose'] ?: 'No purpose provided') ?></div></td>
                    <td class="fw-bold"><?= money($loan['amount'], $loan['currency']) ?></td>
                    <td><span class="status-pill status-<?= $loan['status']==='approved'?'success':($loan['status']==='rejected'?'danger':'warning') ?>"><?= e(strtoupper(str_replace('_', ' ', $loan['status']))) ?></span></td>
                    <td>
                        <?php if ($loan['status'] === 'pending_review'): ?>
                            <form method="post" class="d-grid gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
                                <input name="review_note" class="form-control form-control-sm" placeholder="Optional review note">
                                <div class="d-flex gap-2">
                                    <button name="loan_action" value="approved" class="btn btn-sm btn-success">Approve</button>
                                    <button name="loan_action" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <span class="small muted"><?= e($loan['review_note'] ?: 'Reviewed') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$loans): ?><tr><td colspan="5" class="text-center muted py-5">No loan applications match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
