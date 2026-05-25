<?php $pageTitle='Admin Analytics'; include __DIR__ . '/../includes/admin_header.php';
$counts = [
 'users'=>db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
 'pending_deposits'=>db()->query('SELECT COUNT(*) c FROM deposits WHERE status="pending"')->fetch()['c'],
 'pending_tx'=>db()->query('SELECT COUNT(*) c FROM transactions WHERE status="pending"')->fetch()['c'],
 'volume'=>db()->query('SELECT COALESCE(SUM(ABS(amount)),0) c FROM transactions')->fetch()['c'],
]; ?>
<section class="admin-overview-intro">
    <div>
        <span>Executive Overview</span>
        <h2>Banking operations at a glance</h2>
        <p>Monitor members, approvals, transfer activity, and total processed volume from one secure workspace.</p>
    </div>
    <div class="admin-security-pill"><i class="fa-solid fa-shield-halved"></i> Secure operations</div>
</section>
<div class="admin-analytics-grid">
    <section class="admin-stat-card">
        <span class="admin-stat-icon icon-members"><i class="fa-solid fa-users"></i></span>
        <div class="admin-stat-copy"><small>Members</small><strong><?= e($counts['users']) ?></strong><span>Registered clients</span></div>
    </section>
    <section class="admin-stat-card">
        <span class="admin-stat-icon icon-deposits"><i class="fa-solid fa-file-circle-check"></i></span>
        <div class="admin-stat-copy"><small>Pending Deposits</small><strong><?= e($counts['pending_deposits']) ?></strong><span>Awaiting review</span></div>
    </section>
    <section class="admin-stat-card">
        <span class="admin-stat-icon icon-transfers"><i class="fa-solid fa-right-left"></i></span>
        <div class="admin-stat-copy"><small>Pending Transfers</small><strong><?= e($counts['pending_tx']) ?></strong><span>Approval queue</span></div>
    </section>
    <section class="admin-stat-card">
        <span class="admin-stat-icon icon-volume"><i class="fa-solid fa-chart-line"></i></span>
        <div class="admin-stat-copy"><small>Transaction Volume</small><strong><?= money($counts['volume']) ?></strong><span>Total processed</span></div>
    </section>
    <section class="admin-chart-card admin-liquidity-chart">
        <div class="admin-panel-heading">
            <div><small>Portfolio Performance</small><h3>Liquidity trend</h3></div>
            <span class="admin-panel-badge">6 months</span>
        </div>
        <canvas data-chart="line" data-chart-theme="admin" height="230"></canvas>
    </section>
    <section class="admin-chart-card admin-activity-chart">
        <div class="admin-panel-heading">
            <div><small>Operations Mix</small><h3>Activity mix</h3></div>
        </div>
        <canvas data-chart="doughnut" data-chart-theme="admin"></canvas>
    </section>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
