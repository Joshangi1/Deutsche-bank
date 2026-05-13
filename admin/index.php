<?php $pageTitle='Admin Analytics'; include __DIR__ . '/../includes/admin_header.php';
$counts = [
 'users'=>db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
 'pending_deposits'=>db()->query('SELECT COUNT(*) c FROM deposits WHERE status="pending"')->fetch()['c'],
 'pending_tx'=>db()->query('SELECT COUNT(*) c FROM transactions WHERE status="pending"')->fetch()['c'],
 'volume'=>db()->query('SELECT COALESCE(SUM(ABS(amount)),0) c FROM transactions')->fetch()['c'],
]; ?>
<div class="row g-4"><div class="col-md-3"><div class="premium-card p-4"><i class="fa-solid fa-users text-warning"></i><div class="metric"><?= e($counts['users']) ?></div><div class="muted">Members</div></div></div><div class="col-md-3"><div class="premium-card p-4"><i class="fa-solid fa-file-circle-check text-warning"></i><div class="metric"><?= e($counts['pending_deposits']) ?></div><div class="muted">Pending deposits</div></div></div><div class="col-md-3"><div class="premium-card p-4"><i class="fa-solid fa-right-left text-warning"></i><div class="metric"><?= e($counts['pending_tx']) ?></div><div class="muted">Pending transfers</div></div></div><div class="col-md-3"><div class="premium-card p-4"><i class="fa-solid fa-chart-line text-warning"></i><div class="metric"><?= money($counts['volume']) ?></div><div class="muted">Transaction volume</div></div></div><div class="col-lg-8"><div class="table-card p-4"><h5 class="fw-bold">Liquidity trend</h5><canvas data-chart="line" height="230"></canvas></div></div><div class="col-lg-4"><div class="table-card p-4"><h5 class="fw-bold">Activity mix</h5><canvas data-chart="doughnut"></canvas></div></div></div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
