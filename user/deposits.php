<?php
$pageTitle='Check Deposits';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
require_unrestricted_account($user);
include __DIR__ . '/../includes/user_header.php';
if ($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf(); $front=secure_upload($_FILES['front_image'] ?? [], __DIR__.'/../uploads/deposits'); $back=secure_upload($_FILES['back_image'] ?? [], __DIR__.'/../uploads/deposits'); if($front && $back){ banking_submit_deposit((int)$user['id'], (float)$_POST['amount'], $front, $back, banking_actor('customer', (int)$user['id'])); flash('success','Deposit uploaded for review.'); } else flash('danger','Upload front and back check images under 4MB.'); }
$deposits=db()->prepare('SELECT * FROM deposits WHERE user_id=? ORDER BY created_at DESC'); $deposits->execute([$user['id']]);
?>
<div class="row g-4"><div class="col-lg-5"><form class="premium-card p-4" method="post" enctype="multipart/form-data"><?= csrf_field() ?><h5 class="fw-bold">Mobile-style check deposit</h5><input name="amount" type="number" step="0.01" class="form-control mb-3" placeholder="Amount" required><label class="form-label">Front image</label><input name="front_image" type="file" accept="image/*" class="form-control mb-3" required><label class="form-label">Back image</label><input name="back_image" type="file" accept="image/*" class="form-control mb-3" required><button class="btn btn-navy">Submit deposit</button></form></div><div class="col-lg-7"><div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0">Deposit review status</h5></div><table class="table mb-0"><tbody><?php foreach($deposits as $d): ?><tr><td><?= money($d['amount']) ?><div class="small muted"><?= e($d['created_at']) ?></div></td><td><span class="status-pill status-warning"><?= e($d['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
