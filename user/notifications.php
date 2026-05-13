<?php
$pageTitle='Notifications';
include __DIR__ . '/../includes/user_header.php';
db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
$stmt=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$groups = [];
foreach ($stmt as $n) {
    $groups[date('F j, Y', strtotime($n['created_at']))][] = $n;
}
$icons = ['transfer'=>'fa-right-left','deposit'=>'fa-camera','security'=>'fa-shield-halved','bill_pay'=>'fa-file-invoice-dollar','ach'=>'fa-building-columns','statement'=>'fa-file-lines','zelle'=>'fa-bolt','card'=>'fa-credit-card','account'=>'fa-bell'];
?>
<div class="banking-hero mb-4"><div><div class="eyebrow">Notification Center</div><h2>Account alerts and messages</h2><p>Only customer-facing banking events appear here. Internal operations remain isolated in secure audit logs.</p></div><i class="fa-solid fa-bell"></i></div>
<?php foreach($groups as $date => $items): ?>
    <h6 class="notification-date"><?= e($date) ?></h6>
    <div class="row g-3 mb-4">
        <?php foreach($items as $n): $icon = $icons[$n['category'] ?? 'account'] ?? 'fa-bell'; ?>
            <div class="col-12"><div class="notification-card <?= $n['is_read'] ? '' : 'is-unread' ?>"><span class="tx-icon"><i class="fa-solid <?= e($icon) ?>"></i></span><div class="flex-grow-1"><div class="d-flex flex-wrap gap-2 align-items-center"><h5 class="fw-bold mb-0"><?= e($n['title']) ?></h5><span class="status-pill status-<?= e($n['type']) ?>"><?= e(strtoupper($n['type'])) ?></span><span class="category-badge"><?= e(strtoupper($n['category'] ?? 'account')) ?></span><?php if (($n['priority'] ?? 'normal') === 'high'): ?><span class="status-pill status-danger">PRIORITY</span><?php endif; ?></div><p class="muted mb-1"><?= e($n['message']) ?></p><span class="small muted"><?= e(transaction_display_date($n['created_at'])) ?></span></div></div></div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php if (!$groups): ?><div class="premium-card p-5 text-center muted">No customer notifications yet.</div><?php endif; ?>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
