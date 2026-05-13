<?php
$pageTitle='Audit Logs';
include __DIR__ . '/../includes/admin_header.php';
$rows=db()->query('SELECT l.*, a.name, u.email affected_email FROM admin_logs l JOIN admins a ON a.id=l.admin_id LEFT JOIN users u ON u.id=l.affected_user_id ORDER BY l.created_at DESC LIMIT 100');
$events=db()->query('SELECT * FROM system_events ORDER BY created_at DESC LIMIT 50');
?>
<div class="row g-4">
    <div class="col-xl-8"><div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0">Admin audit trail</h5><p class="muted mb-0">Internal operations, before/after context, affected user, and IP address.</p></div><table class="table align-middle mb-0"><thead><tr><th>Admin</th><th>Action</th><th>Affected user</th><th>Details</th><th>IP</th><th>Date</th></tr></thead><tbody><?php foreach($rows as $l): ?><tr><td><?= e($l['name']) ?></td><td><span class="category-badge"><?= e($l['action']) ?></span></td><td><?= e($l['affected_email'] ?? 'Internal') ?></td><td><?= e($l['details']) ?><details class="small muted mt-1"><summary>Before / after</summary><pre><?= e(($l['before_values'] ?? 'none') . "\n" . ($l['after_values'] ?? 'none')) ?></pre></details></td><td><?= e($l['ip_address']) ?></td><td><?= e($l['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="col-xl-4"><div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0">System events</h5><p class="muted mb-0">Backend-only risk, compliance, and processing events.</p></div><div class="p-3"><?php foreach($events as $event): ?><div class="system-event"><span class="status-pill status-<?= e($event['severity']) ?>"><?= e(strtoupper($event['severity'])) ?></span><strong><?= e($event['event_type']) ?></strong><p><?= e($event['details']) ?></p><small><?= e($event['created_at']) ?></small></div><?php endforeach; ?></div></div></div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
