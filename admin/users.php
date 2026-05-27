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
        banking_emit_event('transaction.seeded', ['count' => $count, 'system_detail' => 'Transaction history regenerated through admin workflow.'], banking_actor('admin', (int) $admin['id']), $uid, 'user', $uid);
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
            banking_update_account_identity($uid, [
                'account_number' => (string) ($_POST['account_number'] ?? $beforeAccount['account_number']),
                'routing_number' => (string) ($_POST['routing_number'] ?? $beforeAccount['routing_number']),
                'iban' => (string) ($_POST['iban'] ?? ($beforeAccount['iban'] ?? '')),
                'bic' => (string) ($_POST['bic'] ?? ($beforeAccount['bic'] ?? '')),
                'account_type' => (string) ($_POST['account_type'] ?? $beforeAccount['account_type']),
            ]);
            banking_update_balance($uid, [
                'available_balance' => (float) $_POST['available_balance'] - (float) $beforeAccount['available_balance'],
                'pending_balance' => (float) $_POST['pending_balance'] - (float) $beforeAccount['pending_balance'],
                'savings_balance' => (float) $_POST['savings_balance'] - (float) $beforeAccount['savings_balance'],
            ], banking_actor('admin', (int) $admin['id']), 'admin.balance_edit');
            banking_update_account_type($uid, (string) $_POST['account_type'], banking_actor('admin', (int) $admin['id']));
            log_admin((int) $admin['id'], 'balance_edit', 'Edited balances for user ' . $uid, $uid, $beforeAccount, user_account($uid));
        }
    }

    if (isset($_POST['save_banking_details'])) {
        $account = user_account($uid);
        save_user_banking_details(
            $uid,
            (string) ($_POST['banking_country'] ?? ''),
            $account ? (int) $account['id'] : null,
            $_POST['banking_details'] ?? []
        );
        log_admin((int) $admin['id'], 'banking_details_edit', 'Edited banking details for user ' . $uid, $uid);
    }

    flash('success', 'User updated.');
    header('Location: users.php');
    exit;
}

$q = '%' . ($_GET['q'] ?? '') . '%';
$stmt = db()->prepare('SELECT u.*, a.id AS account_id, a.available_balance, a.pending_balance, a.savings_balance, a.account_type, a.account_number, a.routing_number, a.iban, a.bic FROM users u LEFT JOIN accounts a ON a.user_id=u.id WHERE u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? ORDER BY u.created_at DESC');
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
            <thead><tr><th>Member</th><th>Brand</th><th>Status</th><th>Verification</th><th>Balances</th><th>Admin controls</th></tr></thead>
            <tbody>
            <?php foreach ($stmt as $u): ?>
                <?php
                    $userAccount = [
                        'id' => $u['account_id'],
                        'account_number' => $u['account_number'],
                        'routing_number' => $u['routing_number'],
                        'iban' => $u['iban'],
                        'bic' => $u['bic'],
                        'account_type' => $u['account_type'],
                    ];
                    $userRegion = user_banking_region($u, $userAccount);
                    $userBrand = brand_config_for_user($u, $userAccount);
                    $bankingDetails = user_banking_details((int) $u['id'], $u, $userAccount, false);
                    $detailRows = array_merge($bankingDetails, array_fill(0, 3, [
                        'id' => '',
                        'detail_label' => '',
                        'detail_value' => '',
                        'display_order' => count($bankingDetails) + 1,
                        'is_copyable' => 1,
                    ]));
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <img class="avatar-sm" src="<?= e(avatar_url($u['avatar'] ?? null)) ?>" alt="Profile picture">
                            <div><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong><div class="small muted"><?= e($u['email']) ?></div></div>
                        </div>
                    </td>
                    <td><span class="status-pill status-info"><?= e((string) $userBrand['brand_short_name']) ?></span><div class="small muted"><?= e((string) $userBrand['country']) ?></div></td>
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
                            <div class="col-md-3"><input name="account_number" class="form-control form-control-sm" placeholder="Account number" value="<?= e($u['account_number'] ?? '') ?>"></div>
                            <div class="col-md-3"><input name="routing_number" class="form-control form-control-sm" placeholder="Routing / Sort / Transit" value="<?= e($u['routing_number'] ?? '') ?>"></div>
                            <div class="col-md-3"><input name="iban" class="form-control form-control-sm" placeholder="IBAN" value="<?= e(format_iban_display($u['iban'] ?? '')) ?>"></div>
                            <div class="col-md-3"><input name="bic" class="form-control form-control-sm" placeholder="BIC / SWIFT" value="<?= e($u['bic'] ?? '') ?>"></div>
                            <div class="col-md-2 d-grid"><button name="profile" value="1" class="btn btn-sm btn-gold">Save all</button><input type="hidden" name="balance" value="1"></div>
                        </form>
                        <form method="post" class="mt-3 banking-details-admin">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <input type="hidden" name="banking_country" value="<?= e($userRegion) ?>">
                            <div class="small fw-bold mb-2">Payment receiving details</div>
                            <?php foreach ($detailRows as $idx => $detail): ?>
                                <div class="row g-2 align-items-center mb-2">
                                    <input type="hidden" name="banking_details[<?= $idx ?>][id]" value="<?= e((string) ($detail['id'] ?? '')) ?>">
                                    <div class="col-md-3"><input name="banking_details[<?= $idx ?>][detail_label]" class="form-control form-control-sm" placeholder="Label, e.g. Routing Number" value="<?= e($detail['detail_label'] ?? '') ?>"></div>
                                    <div class="col-md-5"><input name="banking_details[<?= $idx ?>][detail_value]" class="form-control form-control-sm" placeholder="Full value users can copy" value="<?= e($detail['detail_value'] ?? '') ?>"></div>
                                    <div class="col-md-1"><input name="banking_details[<?= $idx ?>][display_order]" class="form-control form-control-sm" value="<?= e((string) ($detail['display_order'] ?? ($idx + 1))) ?>"></div>
                                    <div class="col-md-2 form-check small">
                                        <input type="hidden" name="banking_details[<?= $idx ?>][is_copyable]" value="0">
                                        <input class="form-check-input" type="checkbox" name="banking_details[<?= $idx ?>][is_copyable]" value="1" <?= !empty($detail['is_copyable']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Copy</label>
                                    </div>
                                    <div class="col-md-1 form-check small">
                                        <?php if (!empty($detail['id'])): ?>
                                            <input class="form-check-input" type="checkbox" name="banking_details[<?= $idx ?>][delete]" value="1" title="Delete">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button name="save_banking_details" value="1" class="btn btn-sm btn-light border">Save banking details</button>
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
