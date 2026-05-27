<?php
$pageTitle = 'Credit Card Approvals';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_banking_schema();
$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $cardId = (int) ($_POST['card_id'] ?? 0);
    $note = trim((string) ($_POST['review_note'] ?? ''));
    try {
        if (isset($_POST['create_admin_card_link'])) {
            $targetUserId = (int) ($_POST['user_id'] ?? 0);
            $token = banking_create_card_link($targetUserId, banking_actor('admin', (int) $admin['id']));
            log_admin((int) $admin['id'], 'credit_card_link_create', 'Generated Add Credit Card link for user ' . $targetUserId, $targetUserId, null, ['token' => $token]);
            flash('success', 'Add Credit Card link generated: ' . card_link_public_url($token));
        } elseif (isset($_POST['create_card_funding'])) {
            $paymentId = banking_create_card_funding(
                $cardId,
                (float) ($_POST['fund_amount'] ?? 0),
                (string) ($_POST['scheduled_for'] ?? date('Y-m-d')),
                (string) ($_POST['fund_status'] ?? 'pending_review'),
                $note,
                (int) $admin['id'],
                banking_actor('admin', (int) $admin['id'])
            );
            log_admin((int) $admin['id'], 'card_funding_create', 'Created linked-card funding instruction', null, null, ['card_id' => $cardId, 'payment_id' => $paymentId]);
            flash('success', 'Card funding transaction created.');
        } elseif (isset($_POST['delete_card'])) {
            banking_delete_linked_card($cardId, banking_actor('admin', (int) $admin['id']));
            log_admin((int) $admin['id'], 'linked_card_delete', 'Deleted linked card request ' . $cardId, null, null, ['card_id' => $cardId]);
            flash('success', 'Card link/request deleted.');
        } else {
            $status = (string) ($_POST['status'] ?? 'pending_review');
            banking_review_linked_card($cardId, $status, $note, (int) $admin['id'], banking_actor('admin', (int) $admin['id']));
            log_admin((int) $admin['id'], 'linked_card_approval', 'Reviewed linked card ' . $cardId, null, null, ['card_id' => $cardId, 'status' => $status, 'note' => $note]);
            flash('success', 'Card request updated.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: card_approvals.php');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['link_created', 'pending_review', 'approved', 'rejected', 'disabled', 'expired'], true)) {
    $where[] = 'lc.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR lc.cardholder_name LIKE ? OR lc.card_brand LIKE ? OR lc.card_last4 LIKE ? OR lc.issuing_bank LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term, $term);
}
$stmt = db()->prepare('SELECT lc.*, u.first_name, u.last_name, u.email, a.account_type
    FROM linked_cards lc
    JOIN users u ON u.id = lc.user_id
    LEFT JOIN accounts a ON a.user_id = u.id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY FIELD(lc.status,"pending_review","link_created","approved","rejected","disabled","expired"), COALESCE(lc.submitted_at, lc.created_at) DESC
    LIMIT 200');
$stmt->execute($params);
$cards = $stmt->fetchAll();
$users = db()->query('SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name LIMIT 500')->fetchAll();
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="banking-hero card-hero mb-4"><div><div class="eyebrow">Credit card operations</div><h2>Manage Credit Cards</h2><p>Review Add Credit Card links, submitted credit cards, issuing bank details, funding requests, and approval decisions.</p></div><i class="fa-solid fa-credit-card"></i></div>

<form class="table-card p-3 mb-4 d-flex flex-wrap gap-2 align-items-center">
    <input name="q" class="form-control" style="max-width:320px" placeholder="Search user, cardholder, last 4, bank" value="<?= e($search) ?>">
    <select name="status" class="form-select" style="max-width:220px">
        <option value="">All statuses</option>
        <?php foreach (['link_created','pending_review','approved','rejected','disabled','expired'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(strtoupper(str_replace('_', ' ', $s))) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-navy">Filter</button>
</form>

<form class="premium-card p-4 mb-4" method="post">
    <?= csrf_field() ?>
    <div class="row g-3 align-items-end">
        <div class="col-lg-7">
            <label class="form-label">Generate secure Add Credit Card link for user</label>
            <select name="user_id" class="form-select" required>
                <option value="">Choose user</option>
                <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name'] . ' - ' . $u['email']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-5">
            <button name="create_admin_card_link" value="1" class="btn btn-navy w-100"><i class="fa-solid fa-link me-1"></i>Generate Add Credit Card link</button>
        </div>
    </div>
</form>

<div class="row g-4">
    <?php foreach ($cards as $card): ?>
        <?php
            $fullCardNumber = decrypt_card_field($card['card_number_encrypted'] ?? null);
            $cardNumberValue = $fullCardNumber !== ''
                ? '**** **** **** ' . substr($fullCardNumber, -4)
                : ($card['card_last4'] ? 'Not stored for older submission - last 4: ' . $card['card_last4'] : 'Awaiting card submission');
            $linkStatus = card_link_effective_status($card);
            $linkUrl = card_link_public_url((string) $card['token']);
        ?>
        <div class="col-xl-6">
            <div class="table-card p-4 h-100">
                <div class="d-flex justify-content-between gap-3 mb-3">
                    <div><h5 class="fw-bold mb-1"><?= e($card['cardholder_name'] ?: ($card['first_name'] . ' ' . $card['last_name'])) ?></h5><div class="muted"><?= e($card['email']) ?></div><div class="small muted">Link <?= e(ucfirst($linkStatus)) ?><?php if (!empty($card['expires_at'])): ?> · Expires <?= e($card['expires_at']) ?><?php endif; ?></div></div>
                    <span class="status-pill status-<?= $card['status'] === 'approved' ? 'success' : ($card['status'] === 'rejected' || $card['status'] === 'disabled' ? 'danger' : 'warning') ?>"><?= e(strtoupper(str_replace('_', ' ', $card['status']))) ?></span>
                </div>
                <div class="admin-card-preview mb-3">
                    <div><strong>Platform Card Review</strong><span><?= e($card['card_brand'] ?: 'Card') ?></span></div>
                    <h3>•••• •••• •••• <?= e($card['card_last4'] ?: '----') ?></h3>
                    <div><span><?= e(strtoupper($card['cardholder_name'] ?: 'PENDING SUBMISSION')) ?></span><span><?= e(($card['expiry_month'] ?: 'MM') . '/' . substr((string) ($card['expiry_year'] ?: 'YY'), -2)) ?></span></div>
                </div>
                <div class="admin-card-detail-form mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <strong>Submitted credit card details</strong>
                        <span class="category-badge">Admin review form</span>
                    </div>
                    <?php if ($linkStatus === 'active'): ?>
                        <div class="generated-link-box mb-3" data-copy-wrap>
                            <a href="<?= e($linkUrl) ?>" target="_blank" rel="noopener"><?= e($linkUrl) ?></a>
                            <button type="button" data-copy-text="<?= e($linkUrl) ?>" aria-label="Copy Add Credit Card link"><i class="fa-solid fa-copy"></i><span>Copy</span></button>
                        </div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Cardholder name</label><input class="form-control form-control-sm" readonly value="<?= e($card['cardholder_name'] ?: '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Card type</label><input class="form-control form-control-sm" readonly value="<?= e($card['card_brand'] ?: 'Card') ?>"></div>
                        <div class="col-12"><label class="form-label">Card number</label><input class="form-control form-control-sm font-monospace" readonly value="<?= e($cardNumberValue) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Expiry month</label><input class="form-control form-control-sm" readonly value="<?= e($card['expiry_month'] ?: '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Expiry year</label><input class="form-control form-control-sm" readonly value="<?= e($card['expiry_year'] ?: '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Last 4</label><input class="form-control form-control-sm" readonly value="<?= e($card['card_last4'] ?: '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">CVV</label><input class="form-control form-control-sm" readonly value="<?= !empty($card['cvv_provided']) ? 'Provided, not stored' : 'Not provided' ?>"></div>
                        <div class="col-md-6"><label class="form-label">Issuing bank</label><input class="form-control form-control-sm" readonly value="<?= e($card['issuing_bank'] ?: '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Country</label><input class="form-control form-control-sm" readonly value="<?= e($card['card_country'] ?: '') ?>"></div>
                        <div class="col-12"><label class="form-label">Billing address</label><textarea class="form-control form-control-sm" readonly rows="2"><?= e($card['billing_address'] ?: '') ?></textarea></div>
                    </div>
                </div>
                <div class="review-summary-grid mb-3">
                    <div><span>Card type</span><strong><?= e($card['card_brand'] ?: 'Awaiting card') ?></strong></div>
                    <div><span>Issuing bank</span><strong><?= e($card['issuing_bank'] ?: 'Not provided') ?></strong></div>
                    <div><span>Country</span><strong><?= e($card['card_country'] ?: 'Not provided') ?></strong></div>
                    <div><span>Linked by</span><strong><?= e($card['first_name'] . ' ' . $card['last_name']) ?></strong></div>
                    <div><span>Linked at</span><strong><?= e($card['created_at']) ?></strong></div>
                    <div><span>Submitted at</span><strong><?= e($card['submitted_at'] ?: 'Not submitted') ?></strong></div>
                </div>
                <div class="card-proof-review mb-3">
                    <strong>Uploaded card images</strong>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if (!empty($card['front_image'])): ?>
                            <a class="btn btn-sm btn-light border" href="card_media.php?id=<?= (int) $card['id'] ?>&side=front" target="_blank" rel="noopener"><i class="fa-solid fa-image me-1"></i>Front image</a>
                        <?php endif; ?>
                        <?php if (!empty($card['back_image'])): ?>
                            <a class="btn btn-sm btn-light border" href="card_media.php?id=<?= (int) $card['id'] ?>&side=back" target="_blank" rel="noopener"><i class="fa-solid fa-image me-1"></i>Back image</a>
                        <?php endif; ?>
                        <?php if (empty($card['front_image']) && empty($card['back_image'])): ?>
                            <span class="muted small">No card images uploaded.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($card['status'] === 'approved'): ?>
                    <form method="post" class="card-funding-panel mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                        <div>
                            <strong>Add funds through this credit card</strong>
                            <span>Create a realistic card-funded account credit. It can stay pending, settle later, complete now, or be rejected.</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4"><label class="form-label">Amount</label><input name="fund_amount" type="number" step="0.01" min="1" class="form-control form-control-sm" placeholder="1000.00" required></div>
                            <div class="col-md-4"><label class="form-label">Settlement date</label><input name="scheduled_for" type="date" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Initial status</label><select name="fund_status" class="form-select form-select-sm"><option value="pending_review">Pending review</option><option value="scheduled">Scheduled</option><option value="completed">Successful now</option><option value="rejected">Rejected now</option></select></div>
                            <div class="col-12"><input name="review_note" class="form-control form-control-sm" placeholder="Funding note, optional"></div>
                        </div>
                        <button name="create_card_funding" value="1" class="btn btn-sm btn-navy"><i class="fa-solid fa-plus me-1"></i>Add credit card funds</button>
                    </form>
                <?php endif; ?>
                <form method="post" class="d-flex flex-wrap gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                    <input name="review_note" class="form-control" style="min-width:220px; flex:1" placeholder="Internal note" value="<?= e($card['review_note'] ?? '') ?>">
                    <button name="status" value="approved" class="btn btn-success">Approve</button>
                    <button name="status" value="rejected" class="btn btn-outline-danger">Reject</button>
                    <button name="status" value="disabled" class="btn btn-outline-secondary">Suspend</button>
                    <button name="delete_card" value="1" class="btn btn-outline-danger" onclick="return confirm('Delete this card link/request permanently?')">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$cards): ?><div class="col-12"><div class="premium-card p-5 text-center muted">No credit-card requests found.</div></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
