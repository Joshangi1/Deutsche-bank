<?php
$pageTitle = 'Transfer Approvals';
include __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? 'pending_review');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    try {
        banking_review_payment($paymentId, $status, $note, (int) $admin['id'], banking_actor('admin', (int) $admin['id']), (string) ($_POST['scheduled_for'] ?? ''));
        log_admin((int) $admin['id'], 'transfer_approval', 'Reviewed transfer payment ' . $paymentId, null, null, ['payment_id' => $paymentId, 'status' => $status, 'note' => $note]);
        flash('success', 'Transfer approval updated.');
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: transfers.php');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$where = ['bp.payment_type IN ("transfer","sepa","sepa_instant","standing_order","ach","zelle","bill_pay","card_link","credit_card_fund_account","credit_card_fund_card","referral_bonus")'];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending_review', 'processing', 'scheduled', 'completed', 'failed', 'cancelled', 'rejected'], true)) {
    $where[] = 'bp.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR bp.payee_name LIKE ? OR bp.descriptor LIKE ? OR bp.confirmation_code LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}
$sql = 'SELECT bp.*, u.first_name, u.last_name, u.email, a.account_number, a.routing_number, a.iban, a.bic
        FROM banking_payments bp
        JOIN users u ON u.id = bp.user_id
        LEFT JOIN accounts a ON a.id = bp.account_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY FIELD(bp.status,"pending_review","processing","scheduled","completed","failed","cancelled","rejected"), bp.created_at DESC
        LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<div class="banking-hero security-hero mb-4"><div><div class="eyebrow">Operations approval</div><h2>Transfer and credit-card funding review queue</h2><p>Inspect pending SEPA, wire, standing order, transfer, and credit-card funding requests before releasing or rejecting them.</p></div><i class="fa-solid fa-money-bill-transfer"></i></div>

<form class="table-card p-3 mb-4 d-flex flex-wrap gap-2 align-items-center">
    <input name="q" class="form-control" style="max-width:320px" placeholder="Search sender, receiver, reference" value="<?= e($search) ?>">
    <select name="status" class="form-select" style="max-width:220px">
        <option value="">All statuses</option>
        <?php foreach (['pending_review','processing','scheduled','completed','failed','cancelled','rejected'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(strtoupper(str_replace('_', ' ', $s))) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-navy">Filter</button>
</form>

<div class="table-card">
    <div class="p-4"><h5 class="fw-bold mb-0">Transfer approvals</h5><p class="muted mb-0">Approval decisions are stored in admin logs and banking events.</p></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Sender</th><th>Receiver / Bank</th><th>Destination</th><th>Amount</th><th>Reference</th><th>Status</th><th>Proof</th><th>Decision</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $t): ?>
                <?php
                    $isEuro = in_array($t['payment_type'], ['sepa','sepa_instant','standing_order'], true);
                    $isCardFunding = in_array($t['payment_type'], ['card_link','credit_card_fund_account','credit_card_fund_card'], true);
                    $currency = $isEuro ? 'EUR' : 'USD';
                    $destination = $isCardFunding
                        ? ('Account ' . mask_account((string) ($t['account_number'] ?? '')) . ' / credit card funding')
                        : ($isEuro ? ($t['iban'] ? format_iban_display($t['iban']) : 'IBAN pending') : ('Account ' . mask_account((string) ($t['account_number'] ?? '')) . ' / Routing ' . ($t['routing_number'] ?? '')));
                ?>
                <tr>
                    <td><strong><?= e($t['first_name'] . ' ' . $t['last_name']) ?></strong><div class="small muted"><?= e($t['email']) ?></div></td>
                    <td><strong><?= e($t['payee_name']) ?></strong><div class="small muted"><?= e($isCardFunding ? 'Linked approved card' : ($isEuro ? ($t['bic'] ?: DEFAULT_BIC) : 'External bank / beneficiary')) ?></div></td>
                    <td class="small"><?= e($destination) ?></td>
                    <td class="fw-bold"><?= money(abs((float) $t['amount']), $currency) ?><div class="small muted"><?= e($currency) ?></div></td>
                    <td><strong><?= e($t['confirmation_code'] ?: ('PAY-' . $t['id'])) ?></strong><div class="small muted">Created <?= e($t['created_at']) ?></div><?php if (!empty($t['scheduled_for'])): ?><div class="small muted">Settle <?= e($t['scheduled_for']) ?></div><?php endif; ?><div class="small"><?= e($t['descriptor']) ?></div></td>
                    <td><span class="status-pill status-<?= $t['status'] === 'completed' ? 'success' : ($t['status'] === 'failed' || $t['status'] === 'cancelled' || $t['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= e(strtoupper(str_replace('_', ' ', $t['status']))) ?></span></td>
                    <td><?= $t['proof_file'] ? '<a class="btn btn-sm btn-light border" href="' . e(url('uploads/' . $t['proof_file'])) . '" target="_blank">View proof</a>' : '<span class="muted small">No proof attached</span>' ?></td>
                    <td>
                        <form method="post" class="d-grid gap-2" style="min-width:220px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="payment_id" value="<?= (int) $t['id'] ?>">
                            <input name="review_note" class="form-control form-control-sm" placeholder="Internal note" value="<?= e($t['review_note'] ?? '') ?>">
                            <input name="scheduled_for" type="date" class="form-control form-control-sm" value="<?= e($t['scheduled_for'] ?: date('Y-m-d')) ?>">
                            <div class="d-flex gap-2">
                                <button name="status" value="completed" class="btn btn-sm btn-success">Approve</button>
                                <button name="status" value="scheduled" class="btn btn-sm btn-outline-primary">Schedule</button>
                                <button name="status" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center muted py-5">No transfer requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
