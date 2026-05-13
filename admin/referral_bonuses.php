<?php
$pageTitle = 'Signup Bonus Approvals';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_banking_schema();
$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $bonusId = (int) ($_POST['bonus_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? 'rejected');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    try {
        banking_review_referral_signup_bonus($bonusId, $status, $note, (int) $admin['id'], banking_actor('admin', (int) $admin['id']));
        log_admin((int) $admin['id'], 'signup_bonus_review', 'Reviewed signup bonus ' . $bonusId, null, null, ['bonus_id' => $bonusId, 'status' => $status, 'note' => $note]);
        flash('success', 'Signup bonus reviewed.');
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: referral_bonuses.php');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'completed', 'rejected'], true)) {
    $where[] = 'rb.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR rb.referral_code LIKE ? OR rb.reference_code LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
$stmt = db()->prepare('SELECT rb.*, u.first_name, u.last_name, u.email, u.verification_status, u.created_at signup_date
    FROM referral_signup_bonuses rb
    JOIN users u ON u.id = rb.user_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY FIELD(rb.status,"pending","completed","rejected"), rb.created_at DESC
    LIMIT 200');
$stmt->execute($params);
$bonuses = $stmt->fetchAll();

include __DIR__ . '/../includes/admin_header.php';
?>
<div class="banking-hero security-hero mb-4"><div><div class="eyebrow">Bonus operations</div><h2>Signup bonus approval queue</h2><p>Approve or reject pending signup bonuses before they become available to the customer.</p></div><i class="fa-solid fa-gift"></i></div>

<form class="table-card p-3 mb-4 d-flex flex-wrap gap-2 align-items-center">
    <input name="q" class="form-control" style="max-width:320px" placeholder="Search user, bonus code, reference" value="<?= e($search) ?>">
    <select name="status" class="form-select" style="max-width:220px">
        <option value="">All statuses</option>
        <?php foreach (['pending','completed','rejected'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(strtoupper($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-navy">Filter</button>
</form>

<div class="table-card">
    <div class="p-4"><h5 class="fw-bold mb-0">Pending signup bonuses</h5><p class="muted mb-0">Each bonus is unique per user and linked to one pending transaction.</p></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>User</th><th>Bonus code</th><th>Bonus</th><th>Signup / KYC</th><th>Reference</th><th>Status</th><th>Decision</th></tr></thead>
            <tbody>
            <?php foreach ($bonuses as $bonus): ?>
                <tr>
                    <td><strong><?= e($bonus['first_name'] . ' ' . $bonus['last_name']) ?></strong><div class="small muted"><?= e($bonus['email']) ?></div></td>
                    <td><span class="category-badge"><?= e($bonus['referral_code']) ?></span></td>
                    <td class="fw-bold"><?= money((float) $bonus['amount'], $bonus['currency']) ?><div class="small muted"><?= e($bonus['currency']) ?></div></td>
                    <td><div><?= e($bonus['signup_date']) ?></div><span class="status-pill status-<?= $bonus['verification_status'] === 'approved' ? 'success' : 'warning' ?>"><?= e(strtoupper(str_replace('_', ' ', $bonus['verification_status']))) ?></span></td>
                    <td><strong><?= e($bonus['reference_code']) ?></strong><div class="small muted">TX #<?= (int) $bonus['transaction_id'] ?></div></td>
                    <td><span class="status-pill status-<?= $bonus['status'] === 'completed' ? 'success' : ($bonus['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= e(strtoupper($bonus['status'])) ?></span></td>
                    <td>
                        <?php if ($bonus['status'] === 'pending'): ?>
                            <form method="post" class="d-grid gap-2" style="min-width:220px">
                                <?= csrf_field() ?>
                                <input type="hidden" name="bonus_id" value="<?= (int) $bonus['id'] ?>">
                                <input name="review_note" class="form-control form-control-sm" placeholder="Internal note">
                                <div class="d-flex gap-2">
                                    <button name="status" value="completed" class="btn btn-sm btn-success">Approve</button>
                                    <button name="status" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <span class="small muted">Reviewed <?= e($bonus['reviewed_at'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$bonuses): ?><tr><td colspan="7" class="text-center muted py-5">No signup bonus requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
