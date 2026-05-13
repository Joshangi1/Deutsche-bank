<?php
$pageTitle = 'Transaction Management';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $accountStmt = db()->prepare('SELECT id FROM accounts WHERE user_id = ? LIMIT 1');
    $accountStmt->execute([$userId]);
    $account = $accountStmt->fetch();

    if (!$account) {
        flash('danger', 'Selected user does not have an account.');
        header('Location: transactions.php');
        exit;
    }

    $type = trim((string) ($_POST['transaction_type'] ?? 'adjustment'));
    $description = trim((string) ($_POST['description'] ?? ''));
    $amount = (float) ($_POST['amount'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['pending','completed','failed','rejected'], true) ? $_POST['status'] : 'pending';
    $createdAt = trim((string) ($_POST['created_at'] ?? date('Y-m-d\TH:i')));
    $createdAtSql = date('Y-m-d H:i:s', strtotime($createdAt) ?: time());
    $actor = banking_actor('admin', (int) $admin['id']);

    if (isset($_POST['create_transaction'])) {
        $transactionId = banking_create_transaction([
            'user_id' => $userId,
            'transaction_type' => $type,
            'description' => $description,
            'amount' => $amount,
            'status' => $status,
            'created_at' => $createdAtSql,
            'customer_event' => $status === 'completed' ? 'transfer_completed' : 'transfer_pending',
        ], $actor);
        log_admin((int) $admin['id'], 'transaction_create', 'Created transaction through service layer', $userId, null, ['transaction_id' => $transactionId, 'type' => $type, 'amount' => $amount, 'status' => $status]);
        flash('success', 'Transaction added.');
    }

    if (isset($_POST['edit_transaction'])) {
        $transactionId = (int) $_POST['transaction_id'];
        banking_update_transaction($transactionId, [
            'user_id' => $userId,
            'transaction_type' => $type,
            'description' => $description,
            'amount' => $amount,
            'status' => $status,
            'created_at' => $createdAtSql,
        ], $actor);
        log_admin((int) $admin['id'], 'transaction_edit', 'Edited transaction through service layer', $userId, null, ['transaction_id' => $transactionId, 'type' => $type, 'amount' => $amount, 'status' => $status]);
        flash('success', 'Transaction updated.');
    }

    header('Location: transactions.php');
    exit;
}

$users = db()->query('SELECT id, first_name, last_name, email FROM users ORDER BY first_name')->fetchAll();
$rows = db()->query('SELECT t.*, u.email, u.first_name, u.last_name FROM transactions t JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC LIMIT 100')->fetchAll();
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<form class="premium-card p-4 mb-4" method="post">
    <?= csrf_field() ?>
    <h5 class="fw-bold">Add transaction</h5>
    <div class="row g-3">
        <div class="col-lg-3"><label class="form-label">Member</label><select name="user_id" class="form-select"><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name'] . ' - ' . $u['email']) ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2"><label class="form-label">Type</label><input name="transaction_type" class="form-control" value="admin_adjustment"></div>
        <div class="col-lg-3"><label class="form-label">Description</label><input name="description" class="form-control" placeholder="Courtesy credit, fee, deposit..." required></div>
        <div class="col-lg-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" class="form-control" required></div>
        <div class="col-lg-2"><label class="form-label">Date</label><input name="created_at" type="datetime-local" class="form-control" value="<?= e(date('Y-m-d\TH:i')) ?>"></div>
        <div class="col-lg-1"><label class="form-label">Status</label><select name="status" class="form-select"><option>completed</option><option>pending</option><option>failed</option><option>rejected</option></select></div>
        <div class="col-lg-1 d-grid"><label class="form-label">&nbsp;</label><button name="create_transaction" value="1" class="btn btn-gold">Add</button></div>
    </div>
</form>
<div class="table-card">
    <div class="p-4"><h5 class="fw-bold mb-0">Edit transactions</h5></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Member</th><th>Edit controls</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $t): ?>
                <tr>
                    <td><strong><?= e($t['first_name'] . ' ' . $t['last_name']) ?></strong><div class="small muted"><?= e($t['email']) ?></div></td>
                    <td>
                        <form method="post" class="admin-edit-grid">
                            <?= csrf_field() ?>
                            <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                            <select name="user_id" class="form-select form-select-sm"><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (int)$u['id']===(int)$t['user_id']?'selected':'' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option><?php endforeach; ?></select>
                            <input name="transaction_type" class="form-control form-control-sm" value="<?= e($t['transaction_type']) ?>">
                            <input name="description" class="form-control form-control-sm" value="<?= e($t['description']) ?>">
                            <input name="amount" type="number" step="0.01" class="form-control form-control-sm" value="<?= e($t['amount']) ?>">
                            <input name="created_at" type="datetime-local" class="form-control form-control-sm" value="<?= e(date('Y-m-d\TH:i', strtotime($t['created_at']))) ?>">
                            <select name="status" class="form-select form-select-sm"><?php foreach (['pending','completed','failed','rejected'] as $s): ?><option <?= $t['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
                            <button name="edit_transaction" value="1" class="btn btn-sm btn-navy">Save</button>
                        </form>
                    </td>
                    <td class="small muted"><?= e($t['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
