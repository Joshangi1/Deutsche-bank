<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brevo.php';
require_once __DIR__ . '/../includes/helpers.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $uid = (int) $_POST['user_id'];

    if (isset($_POST['populate_history'])) {
        $count = populate_nurse_parent_transactions($uid, 13);
        banking_emit_event('transaction.seeded', ['count' => $count, 'system_detail' => 'Demo transaction history regenerated through admin workflow.'], banking_actor('admin', (int) $admin['id']), $uid, 'user', $uid);
        log_admin((int) $admin['id'], 'populate_transactions', 'Generated ' . $count . ' realistic banking transactions for user ' . $uid, $uid, null, ['count' => $count]);
        flash('success', $count . ' transactions generated across 13 weeks.');
        header('Location: users.php');
        exit;
    }

    if (isset($_POST['status'])) {
        banking_set_account_status($uid, (string) $_POST['status'], banking_actor('admin', (int) $admin['id']));
        log_admin((int) $admin['id'], 'user_status', 'Changed user ' . $uid . ' to ' . $_POST['status'], $uid, null, ['status' => $_POST['status']]);
    }

    if (isset($_POST['profile'])) {
        $avatar = secure_upload($_FILES['avatar'] ?? [], __DIR__ . '/../uploads/avatars');
        $phone = normalize_sms_phone((string) $_POST['phone']);
        if ($phone === '') {
            flash('danger', 'Enter the member phone number in international format, for example +2348012345678.');
            header('Location: users.php');
            exit;
        }
        db()->prepare('UPDATE users SET first_name=?, last_name=?, email=?, phone=?, avatar=COALESCE(?, avatar) WHERE id=?')->execute([
            trim((string) $_POST['first_name']),
            trim((string) $_POST['last_name']),
            strtolower(trim((string) $_POST['email'])),
            $phone,
            $avatar,
            $uid,
        ]);
        log_admin((int) $admin['id'], 'profile_edit', 'Edited profile and avatar for user ' . $uid);
    }

    if (isset($_POST['balance'])) {
        $beforeAccount = user_account($uid);
        if ($beforeAccount) {
            banking_update_balance($uid, [
                'available_balance' => (float) $_POST['available_balance'] - (float) $beforeAccount['available_balance'],
                'pending_balance' => (float) $_POST['pending_balance'] - (float) $beforeAccount['pending_balance'],
                'savings_balance' => (float) $_POST['savings_balance'] - (float) $beforeAccount['savings_balance'],
            ], banking_actor('admin', (int) $admin['id']), 'admin.balance_edit');
            banking_update_account_type($uid, (string) $_POST['account_type'], banking_actor('admin', (int) $admin['id']));
            log_admin((int) $admin['id'], 'balance_edit', 'Edited balances for user ' . $uid, $uid, $beforeAccount, user_account($uid));
        }
    }

    flash('success', 'User updated.');
    header('Location: users.php');
    exit;
}

$q = '%' . ($_GET['q'] ?? '') . '%';
$stmt = db()->prepare('SELECT u.*, a.available_balance, a.pending_balance, a.savings_balance, a.account_type FROM users u LEFT JOIN accounts a ON a.user_id=u.id WHERE u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? ORDER BY u.created_at DESC');
$stmt->execute([$q, $q, $q]);
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="table-card">
    <div class="p-4 d-flex justify-content-between gap-2 flex-wrap">
        <div>
            <h5 class="fw-bold mb-0">Members</h5>
            <p class="muted mb-0">Edit client profiles, profile pictures, account status, balances, and account type.</p>
        </div>
        <form class="d-flex gap-2"><input name="q" class="form-control" placeholder="Search users" value="<?= e($_GET['q'] ?? '') ?>"><button class="btn btn-navy">Search</button></form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Member</th><th>Status</th><th>Verification</th><th>Balances</th><th>Admin controls</th></tr></thead>
            <tbody>
            <?php foreach ($stmt as $u): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <img class="avatar-sm" src="<?= e(avatar_url($u['avatar'] ?? null)) ?>" alt="Profile picture">
                            <div><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong><div class="small muted"><?= e($u['email']) ?></div></div>
                        </div>
                    </td>
                    <td><span class="status-pill status-<?= $u['status']==='active'?'success':'danger' ?>"><?= e(strtoupper($u['status'])) ?></span></td>
                    <td><span class="status-pill status-<?= ($u['verification_status'] ?? 'not_started')==='approved'?'success':'warning' ?>"><?= e(strtoupper(str_replace('_',' ', $u['verification_status'] ?? 'not started'))) ?></span><div class="small muted"><?= e(strtoupper(str_replace('_',' ', $u['risk_status'] ?? 'clear'))) ?></div></td>
                    <td><?= money($u['available_balance']) ?> checking<br><?= money($u['pending_balance']) ?> pending<br><?= money($u['savings_balance']) ?> savings</td>
                    <td>
                        <form method="post" enctype="multipart/form-data" class="row g-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <div class="col-md-3"><input name="first_name" class="form-control form-control-sm" value="<?= e($u['first_name']) ?>"></div>
                            <div class="col-md-3"><input name="last_name" class="form-control form-control-sm" value="<?= e($u['last_name']) ?>"></div>
                            <div class="col-md-4"><input name="email" type="email" class="form-control form-control-sm" value="<?= e($u['email']) ?>"></div>
                            <div class="col-md-2"><input name="phone" class="form-control form-control-sm" value="<?= e($u['phone']) ?>" placeholder="+2348012345678"></div>
                            <div class="col-md-3"><input name="avatar" type="file" accept="image/*" class="form-control form-control-sm"></div>
                            <div class="col-md-2"><select name="status" class="form-select form-select-sm"><?php foreach (['active','frozen','suspended','disabled'] as $s): ?><option value="<?= $s ?>" <?= $u['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-2"><input name="available_balance" class="form-control form-control-sm" value="<?= e($u['available_balance']) ?>"></div>
                            <div class="col-md-2"><input name="pending_balance" class="form-control form-control-sm" value="<?= e($u['pending_balance']) ?>"></div>
                            <div class="col-md-2"><input name="savings_balance" class="form-control form-control-sm" value="<?= e($u['savings_balance']) ?>"></div>
                            <div class="col-md-3"><input name="account_type" class="form-control form-control-sm" value="<?= e($u['account_type']) ?>"></div>
                            <div class="col-md-2 d-grid"><button name="profile" value="1" class="btn btn-sm btn-gold">Save all</button><input type="hidden" name="balance" value="1"></div>
                        </form>
                        <form method="post" class="mt-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button name="populate_history" value="1" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generate realistic history</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
