<?php
$pageTitle = 'Banking Operations';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_banking_schema();
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $actor = banking_actor('admin', (int) $admin['id']);
    $userId = (int) ($_POST['user_id'] ?? 0);
    try {
        if (isset($_POST['simulate_payroll'])) {
            banking_create_transaction(['user_id'=>$userId,'transaction_type'=>'sepa_salary_credit','description'=>'SEPA SALARY CREDIT','amount'=>abs((float)$_POST['amount']),'status'=>'completed','customer_event'=>'direct_deposit_received'], $actor);
            flash('success', 'SEPA salary credit simulated.');
        }
        if (isset($_POST['create_statement'])) {
            db()->prepare('INSERT INTO documents (user_id, document_type, title, period_label, file_name, status) VALUES (?, "Statement", ?, ?, ?, "new")')
                ->execute([$userId, trim((string)$_POST['title']), trim((string)$_POST['period_label']), 'statement-' . $userId . '-' . date('YmdHis') . '.pdf']);
            notify_customer_event($userId, 'statement_available');
            banking_emit_event('statement.created', ['title'=>$_POST['title'] ?? 'Statement'], $actor, $userId, 'document', null);
            flash('success', 'Statement added.');
        }
        if (isset($_POST['create_billpay'])) {
            $billerId = banking_add_biller($userId, (string)$_POST['biller_name'], (string)$_POST['category'], (string)$_POST['account_number'], (int)$_POST['due_day'], isset($_POST['autopay']), $actor);
            banking_schedule_bill_payment($userId, $billerId, (float)$_POST['amount'], (string)($_POST['scheduled_for'] ?: date('Y-m-d')), false, null, $actor);
            flash('success', 'SEPA payee and standing order created.');
        }
        if (isset($_POST['create_linked_account'])) {
            $institution = trim((string) ($_POST['institution_name'] ?? ''));
            $jointName = trim((string) ($_POST['joint_owner_name'] ?? ''));
            $mask = substr(preg_replace('/\D+/', '', (string) $_POST['account_number']), -4) ?: (string) random_int(1000, 9999);
            $status = in_array(($_POST['status'] ?? ''), ['connected','pending_verification','review','disabled'], true) ? (string) $_POST['status'] : 'pending_verification';
            $verification = in_array(($_POST['verification_method'] ?? ''), ['instant','micro_deposit'], true) ? (string) $_POST['verification_method'] : 'micro_deposit';
            db()->prepare('INSERT INTO linked_accounts (user_id, institution_name, joint_owner_name, account_type, account_mask, routing_number, verification_method, status, last_synced_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(?="connected", NOW(), NULL))')
                ->execute([$userId, strtoupper($institution ?: 'JOINT ACCOUNT LINKED'), $jointName !== '' ? $jointName : null, trim((string) ($_POST['account_type'] ?? 'Joint Checking')) ?: 'Joint Checking', $mask, trim((string) ($_POST['routing_number'] ?? '')) ?: null, $verification, $status, $status]);
            banking_emit_event('linked_account.created', ['institution' => strtoupper($institution ?: 'JOINT ACCOUNT LINKED'), 'account_type' => $_POST['account_type'] ?? 'Joint Checking'], $actor, $userId, 'linked_account', (int) db()->lastInsertId());
            log_admin((int) $admin['id'], 'linked_account_create', 'Added SEPA bank reference', $userId, null, ['institution_name' => $institution, 'joint_owner_name' => $jointName, 'account_mask' => $mask, 'status' => $status]);
            flash('success', 'SEPA bank reference added.');
        }
        if (isset($_POST['update_linked_account'])) {
            $linkedId = (int) ($_POST['linked_account_id'] ?? 0);
            banking_update_linked_account_details($linkedId, (string) ($_POST['institution_name'] ?? ''), (string) ($_POST['joint_owner_name'] ?? ''), (string) ($_POST['account_number'] ?? ''), (string) ($_POST['routing_number'] ?? ''), (string) ($_POST['account_type'] ?? 'Joint Checking'), (string) ($_POST['verification_method'] ?? 'micro_deposit'), (string) ($_POST['status'] ?? 'pending_verification'), $actor);
            log_admin((int) $admin['id'], 'linked_account_update', 'Updated SEPA bank reference details', $userId, null, ['linked_account_id' => $linkedId, 'institution_name' => trim((string) ($_POST['institution_name'] ?? '')), 'joint_owner_name' => trim((string) ($_POST['joint_owner_name'] ?? ''))]);
            flash('success', 'SEPA bank reference updated.');
        }
        if (isset($_POST['delete_linked_account'])) {
            $linkedId = (int) ($_POST['linked_account_id'] ?? 0);
            banking_delete_pending_linked_account($linkedId, $actor);
            log_admin((int) $admin['id'], 'linked_account_delete', 'Deleted pending linked bank reference', $userId, ['linked_account_id' => $linkedId], null);
            flash('success', 'Pending linked bank reference deleted.');
        }
        if (isset($_POST['issue_card'])) {
            $existing = db()->prepare('SELECT id FROM cards WHERE user_id=? LIMIT 1');
            $existing->execute([$userId]);
            if ($card = $existing->fetch()) {
                db()->prepare('UPDATE cards SET card_last4=?, card_type="Signature Debit", status="active", spending_limit=?, spent_month=0 WHERE id=?')->execute([substr((string)random_int(1000,9999), -4), (float)$_POST['spending_limit'], $card['id']]);
            } else {
                db()->prepare('INSERT INTO cards (user_id, card_last4, card_type, status, spending_limit, spent_month) VALUES (?, ?, "Signature Debit", "active", ?, 0)')->execute([$userId, substr((string)random_int(1000,9999), -4), (float)$_POST['spending_limit']]);
            }
            notify_customer_event($userId, 'card_status_updated', ['message'=>'Your debit card has been issued and is ready for use.']);
            banking_emit_event('card.issued', [], $actor, $userId, 'card', null);
            flash('success', 'Debit card issued.');
        }
        if (isset($_POST['review_linked_card'])) {
            banking_review_linked_card((int) $_POST['linked_card_id'], (string) $_POST['card_status'], (string) ($_POST['review_note'] ?? ''), (int) $admin['id'], $actor);
            flash('success', 'Linked card review updated.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: operations.php');
    exit;
}

$users = db()->query('SELECT id, first_name, last_name, email FROM users ORDER BY first_name')->fetchAll();
$sepaTransfers = db()->query('SELECT p.*, u.email FROM banking_payments p JOIN users u ON u.id=p.user_id WHERE p.payment_type IN ("sepa","sepa_instant") ORDER BY p.created_at DESC LIMIT 20')->fetchAll();
$standingOrders = db()->query('SELECT p.*, u.email FROM banking_payments p JOIN users u ON u.id=p.user_id WHERE p.payment_type IN ("bill_pay","standing_order") ORDER BY p.created_at DESC LIMIT 20')->fetchAll();
$linked = db()->query('SELECT l.*, u.email FROM linked_accounts l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 20')->fetchAll();
$linkedCards = db()->query('SELECT lc.*, u.email FROM linked_cards lc JOIN users u ON u.id=lc.user_id ORDER BY lc.created_at DESC LIMIT 20')->fetchAll();
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="banking-hero mb-4"><div><div class="eyebrow">Operations</div><h2>Workflow command center</h2><p>Each operation type is separated below so SEPA, card, document, and linked-account work stays clear.</p></div><i class="fa-solid fa-sitemap"></i></div>

<div class="operations-section-nav mb-4">
    <a href="#section-account"><i class="fa-solid fa-scale-balanced"></i><span>Account funding</span></a>
    <a href="#section-sepa"><i class="fa-solid fa-building-columns"></i><span>SEPA transfers</span></a>
    <a href="#section-standing"><i class="fa-solid fa-calendar-check"></i><span>Standing orders</span></a>
    <a href="#section-references"><i class="fa-solid fa-link"></i><span>Bank references</span></a>
    <a href="#section-cards"><i class="fa-solid fa-credit-card"></i><span>Credit cards</span></a>
</div>

<section id="section-account" class="operations-section">
    <div class="operations-heading"><div><span class="eyebrow text-primary">Section 1</span><h3>Account Funding, Cards, And Statements</h3><p>Manual admin tools for salary credits, debit card issue, and generated statement records.</p></div></div>
    <div class="row g-4">
        <div class="col-xl-6">
            <form method="post" class="table-card p-4 h-100">
                <?= csrf_field() ?>
                <h5 class="fw-bold">Salary credit / debit card issue</h5>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">User</label><select name="user_id" class="form-select"><?php foreach($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['first_name'].' '.$u['last_name'].' - '.$u['email']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Salary amount</label><input name="amount" type="number" step="0.01" class="form-control" placeholder="Amount"></div>
                    <div class="col-md-6 d-flex align-items-end"><button name="simulate_payroll" value="1" class="btn btn-gold w-100">Create SEPA salary credit</button></div>
                    <div class="col-md-6"><label class="form-label">Card spending limit</label><input name="spending_limit" type="number" step="0.01" class="form-control" placeholder="Card limit"></div>
                    <div class="col-md-6 d-flex align-items-end"><button name="issue_card" value="1" class="btn btn-navy w-100">Issue / refresh debit card</button></div>
                </div>
            </form>
        </div>
        <div class="col-xl-6">
            <form method="post" class="table-card p-4 h-100">
                <?= csrf_field() ?>
                <h5 class="fw-bold">Statement record</h5>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">User</label><select name="user_id" class="form-select"><?php foreach($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['first_name'].' '.$u['last_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Statement title</label><input name="title" class="form-control" placeholder="Monthly statement"></div>
                    <div class="col-md-4"><label class="form-label">Period</label><input name="period_label" class="form-control" placeholder="May 2026"></div>
                    <div class="col-md-2 d-flex align-items-end"><button name="create_statement" value="1" class="btn btn-light border w-100">Add</button></div>
                </div>
            </form>
        </div>
    </div>
</section>

<section id="section-sepa" class="operations-section">
    <div class="operations-heading"><div><span class="eyebrow text-primary">Section 2</span><h3>SEPA Transfer Monitor</h3><p>Read-only snapshot of SEPA and SEPA instant instructions. Use Transfer Approvals for release/rejection decisions.</p></div><a class="btn btn-navy" href="<?= url('admin/transfers.php') ?>">Open transfer approvals</a></div>
    <div class="table-card"><table class="table align-middle mb-0"><thead><tr><th>User</th><th>Descriptor</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        <?php foreach($sepaTransfers as $p): ?><tr><td><?= e($p['email']) ?></td><td><?= e($p['descriptor']) ?><div class="small muted"><?= e($p['confirmation_code'] ?: 'No reference') ?></div></td><td><?= money($p['amount'], 'EUR') ?></td><td><span class="status-pill status-warning"><?= e(strtoupper(str_replace('_',' ', $p['status']))) ?></span></td></tr><?php endforeach; ?>
        <?php if (!$sepaTransfers): ?><tr><td colspan="4" class="text-center muted py-4">No SEPA transfers yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<section id="section-standing" class="operations-section">
    <div class="operations-heading"><div><span class="eyebrow text-primary">Section 3</span><h3>Standing Orders</h3><p>Create scheduled SEPA payees and monitor recurring order instructions.</p></div></div>
    <form method="post" class="table-card p-4 mb-4">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-2"><label class="form-label">User</label><select name="user_id" class="form-select"><?php foreach($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['first_name'].' '.$u['last_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Payee</label><input name="biller_name" class="form-control" placeholder="SEPA payee"></div>
            <div class="col-md-2"><label class="form-label">Category</label><input name="category" class="form-control" placeholder="Utility"></div>
            <div class="col-md-2"><label class="form-label">IBAN / Ref</label><input name="account_number" class="form-control" placeholder="IBAN/ref"></div>
            <div class="col-md-1"><label class="form-label">Due</label><input name="due_day" class="form-control" placeholder="15"></div>
            <div class="col-md-1"><label class="form-label">Amount</label><input name="amount" class="form-control" placeholder="Amt"></div>
            <div class="col-md-2 d-flex align-items-end"><button name="create_billpay" value="1" class="btn btn-light border w-100">Create order</button></div>
        </div>
    </form>
    <div class="table-card"><table class="table align-middle mb-0"><thead><tr><th>Payee</th><th>User</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        <?php foreach($standingOrders as $p): ?><tr><td><?= e($p['payee_name']) ?></td><td><?= e($p['email']) ?></td><td><?= money($p['amount']) ?></td><td><span class="status-pill status-warning"><?= e(strtoupper(str_replace('_',' ', $p['status']))) ?></span></td></tr><?php endforeach; ?>
        <?php if (!$standingOrders): ?><tr><td colspan="4" class="text-center muted py-4">No standing orders yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<section id="section-references" class="operations-section">
    <div class="operations-heading"><div><span class="eyebrow text-primary">Section 4</span><h3>SEPA Bank References</h3><p>Add and maintain external or joint bank references separately from card linking.</p></div></div>
    <form method="post" class="table-card p-4 mb-4">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-2"><label class="form-label">User</label><select name="user_id" class="form-select"><?php foreach($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['first_name'].' '.$u['last_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Bank name</label><input name="institution_name" class="form-control" placeholder="Bank name"></div>
            <div class="col-md-2"><label class="form-label">Account holder</label><input name="joint_owner_name" class="form-control" placeholder="Name on account"></div>
            <div class="col-md-2"><label class="form-label">IBAN / account</label><input name="account_number" class="form-control" placeholder="Account number"></div>
            <div class="col-md-2"><label class="form-label">BIC/SWIFT</label><input name="routing_number" class="form-control" placeholder="BIC/SWIFT"></div>
            <div class="col-md-1"><label class="form-label">Type</label><select name="account_type" class="form-select"><option>Current Account</option><option>Savings Account</option><option>SEPA reference</option></select></div>
            <div class="col-md-1 d-flex align-items-end"><button name="create_linked_account" value="1" class="btn btn-light border w-100">Add</button></div>
            <div class="col-md-2"><select name="verification_method" class="form-select"><option value="micro_deposit">Micro-deposit</option><option value="instant">Instant</option></select></div>
            <div class="col-md-2"><select name="status" class="form-select"><option value="pending_verification">Pending verification</option><option value="connected">Connected</option><option value="review">Review</option><option value="disabled">Disabled</option></select></div>
        </div>
    </form>
    <div class="table-card"><table class="table align-middle mb-0"><tbody><?php foreach($linked as $l): ?><tr><td><strong><?= e($l['institution_name']) ?></strong><div class="small muted"><?= e($l['email']) ?> &middot; <?= trim((string) $l['account_mask']) !== '' ? '****' . e($l['account_mask']) : 'Pending IBAN' ?></div><form method="post" class="row g-2 mt-2"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $l['user_id'] ?>"><input type="hidden" name="linked_account_id" value="<?= (int) $l['id'] ?>"><div class="col-md-3"><input name="institution_name" class="form-control form-control-sm" placeholder="Bank name" value="<?= e($l['institution_name']) ?>"></div><div class="col-md-3"><input name="joint_owner_name" class="form-control form-control-sm" placeholder="Account holder" value="<?= e($l['joint_owner_name'] ?? '') ?>"></div><div class="col-md-2"><input name="account_number" class="form-control form-control-sm" placeholder="New IBAN"></div><div class="col-md-2"><input name="routing_number" class="form-control form-control-sm" placeholder="BIC/SWIFT" value="<?= e($l['routing_number'] ?? '') ?>"></div><div class="col-md-2"><select name="status" class="form-select form-select-sm"><?php foreach(['pending_verification'=>'Pending verification','connected'=>'Connected','review'=>'Review','disabled'=>'Disabled'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $l['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><input type="hidden" name="account_type" value="<?= e($l['account_type'] ?? 'Current Account') ?>"><input type="hidden" name="verification_method" value="<?= e($l['verification_method'] ?? 'instant') ?>"><div class="col-12 d-flex gap-2"><button name="update_linked_account" value="1" class="btn btn-sm btn-light border">Save reference</button><?php if ($l['status']==='pending_verification'): ?><button name="delete_linked_account" value="1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this pending bank reference?')">Delete pending</button><?php endif; ?></div></form></td><td><span class="status-pill status-<?= $l['status']==='connected'?'success':'warning' ?>"><?= e(strtoupper(str_replace('_',' ',$l['status']))) ?></span></td></tr><?php endforeach; ?><?php if (!$linked): ?><tr><td colspan="2" class="text-center muted py-4">No bank references yet.</td></tr><?php endif; ?></tbody></table></div>
</section>

<section id="section-cards" class="operations-section">
    <div class="operations-heading"><div><span class="eyebrow text-primary">Section 5</span><h3>Credit Cards</h3><p>Quick status monitor for Add Credit Card links. Use Manage Credit Cards for card forms, images, funding, and deletion.</p></div><a class="btn btn-navy" href="<?= url('admin/card_approvals.php') ?>">Open credit card approvals</a></div>
    <div class="table-card"><table class="table align-middle mb-0"><thead><tr><th>User</th><th>Card</th><th>Status</th><th>Quick review</th></tr></thead><tbody>
    <?php foreach($linkedCards as $card): ?>
        <tr>
            <td><?= e($card['email']) ?></td>
            <td><?= e($card['card_brand'] ?: 'Card link') ?> <?= $card['card_last4'] ? '****' . e($card['card_last4']) : 'awaiting submission' ?><div class="small muted"><?= e($card['cardholder_name'] ?: 'No cardholder yet') ?></div></td>
            <td><span class="status-pill status-<?= $card['status']==='approved'?'success':'warning' ?>"><?= e(strtoupper(str_replace('_',' ', $card['status']))) ?></span></td>
            <td>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="linked_card_id" value="<?= (int) $card['id'] ?>">
                    <div class="col-md-4"><select name="card_status" class="form-select form-select-sm"><option value="approved">Approve</option><option value="rejected">Reject</option><option value="disabled">Disable</option><option value="pending_review">Pending review</option></select></div>
                    <div class="col-md-5"><input name="review_note" class="form-control form-control-sm" placeholder="Review note"></div>
                    <div class="col-md-3"><button name="review_linked_card" value="1" class="btn btn-sm btn-navy w-100">Update</button></div>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$linkedCards): ?><tr><td colspan="4" class="text-center muted py-4">No credit-card requests yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
