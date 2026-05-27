<?php
$pageTitle = 'Card Center';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
require_unrestricted_account($user);
$stmt = db()->prepare('SELECT * FROM cards WHERE user_id=? LIMIT 1');
$stmt->execute([$user['id']]);
$card = $stmt->fetch();
db()->prepare('INSERT IGNORE INTO card_controls (card_id, virtual_card_last4) VALUES (?, ?)')->execute([$card['id'], (string) random_int(1000, 9999)]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['toggle_card'])) {
        $newStatus = $card['status'] === 'frozen' ? 'active' : 'frozen';
        db()->prepare('UPDATE cards SET status=? WHERE id=?')->execute([$newStatus, $card['id']]);
        notify_customer_event((int) $user['id'], 'security_alert', ['message' => 'Your card status was updated.']);
        flash('success', 'Card status updated.');
    }
    if (isset($_POST['controls'])) {
        db()->prepare('UPDATE card_controls SET online_enabled=?, international_enabled=?, atm_enabled=?, merchant_restrictions=?, travel_notice=?, virtual_card_last4=COALESCE(virtual_card_last4, ?) WHERE card_id=?')
            ->execute([isset($_POST['online_enabled']) ? 1 : 0, isset($_POST['international_enabled']) ? 1 : 0, isset($_POST['atm_enabled']) ? 1 : 0, trim((string) $_POST['merchant_restrictions']), trim((string) $_POST['travel_notice']), (string) random_int(1000, 9999), $card['id']]);
        db()->prepare('UPDATE cards SET spending_limit=? WHERE id=?')->execute([(float) $_POST['spending_limit'], $card['id']]);
        flash('success', 'Card controls saved.');
    }
    if (isset($_POST['lost_card'])) {
        db()->prepare('UPDATE cards SET status="closed" WHERE id=?')->execute([$card['id']]);
        notify_customer_event((int) $user['id'], 'security_alert', ['message' => 'Your card was reported lost and has been closed.']);
        log_system_event('card_lost_report', 'Customer reported a card lost.', (int) $user['id'], 'warning');
        flash('warning', 'Card reported lost. A replacement request has been simulated.');
    }
    header('Location: cards.php');
    exit;
}

$stmt = db()->prepare('SELECT c.*, cc.* FROM cards c LEFT JOIN card_controls cc ON cc.card_id=c.id WHERE c.user_id=? LIMIT 1');
$stmt->execute([$user['id']]);
$card = $stmt->fetch();
$cardTx = db()->prepare('SELECT * FROM transactions WHERE user_id=? AND transaction_type="debit_card_purchase" ORDER BY created_at DESC LIMIT 6');
$cardTx->execute([$user['id']]);
$account = user_account((int) $user['id']);
$brandConfig = brand_config_for_user($user, $account);
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="virtual-card premium-visa">
            <div class="d-flex justify-content-between"><strong><?= e((string) $brandConfig['brand_short_name']) ?></strong><i class="fa-brands fa-cc-visa fa-2x"></i></div>
            <div class="fs-4 fw-bold">4582 •••• •••• <?= e($card['card_last4']) ?></div>
            <div class="d-flex justify-content-between"><span><?= e(strtoupper($user['first_name'].' '.$user['last_name'])) ?></span><span><?= e(strtoupper($card['status'])) ?></span></div>
        </div>
        <div class="premium-card p-4 mt-4">
            <h5 class="fw-bold">Virtual card</h5>
            <div class="virtual-mini"><span>Online card</span><strong>•••• <?= e($card['virtual_card_last4'] ?? '4829') ?></strong></div>
            <p class="small muted mb-0 mt-2">Use this number for subscriptions and merchant-specific controls.</p>
        </div>
    </div>
    <div class="col-xl-7">
        <form class="premium-card p-4 mb-4" method="post">
            <?= csrf_field() ?>
            <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="fw-bold mb-0">Card controls</h5><button name="toggle_card" value="1" class="btn btn-navy"><i class="fa-solid fa-lock me-1"></i><?= $card['status']==='frozen'?'Unlock card':'Lock card' ?></button></div>
            <p class="muted">Monthly spend <?= money($card['spent_month']) ?> of <?= money($card['spending_limit']) ?></p>
            <div class="progress mb-4"><div class="progress-bar bg-warning" style="width: <?= min(100, ((float)$card['spent_month']/(float)$card['spending_limit'])*100) ?>%"></div></div>
            <label class="form-label">Spending limit</label><input name="spending_limit" type="number" step="0.01" class="form-control mb-3" value="<?= e($card['spending_limit']) ?>">
            <div class="row g-2 mb-3">
                <div class="col-md-4"><label class="form-check control-tile"><input class="form-check-input" name="online_enabled" type="checkbox" <?= $card['online_enabled'] ? 'checked' : '' ?>> Online</label></div>
                <div class="col-md-4"><label class="form-check control-tile"><input class="form-check-input" name="international_enabled" type="checkbox" <?= $card['international_enabled'] ? 'checked' : '' ?>> International</label></div>
                <div class="col-md-4"><label class="form-check control-tile"><input class="form-check-input" name="atm_enabled" type="checkbox" <?= $card['atm_enabled'] ? 'checked' : '' ?>> ATM</label></div>
            </div>
            <input name="merchant_restrictions" class="form-control mb-3" placeholder="Merchant restrictions" value="<?= e($card['merchant_restrictions'] ?? '') ?>">
            <input name="travel_notice" class="form-control mb-3" placeholder="Travel notice" value="<?= e($card['travel_notice'] ?? '') ?>">
            <button name="controls" value="1" class="btn btn-gold">Save controls</button>
            <button name="lost_card" value="1" class="btn btn-outline-danger ms-2">Report lost card</button>
        </form>
        <div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0">Recent card activity</h5></div><table class="table align-middle mb-0"><tbody><?php foreach ($cardTx as $tx): ?><tr><td><?= e($tx['description']) ?><div class="small muted"><?= e(transaction_display_date($tx['created_at'])) ?></div></td><td><span class="category-badge"><?= e(transaction_category($tx)) ?></span></td><td class="text-end fw-bold tx-debit"><?= money($tx['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
