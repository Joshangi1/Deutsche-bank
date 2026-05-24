<?php
$pageTitle = 'Member Support';
include __DIR__ . '/../includes/user_header.php';
$callNumber = preg_replace('/[^0-9+]/', '', setting('support_call_number', setting('support_phone', '(800) 417-2049')));
$whatsAppNumber = preg_replace('/\D/', '', setting('support_whatsapp_number', '18004172049'));
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="premium-card p-4">
            <h5 class="fw-bold">Secure support</h5>
            <p class="muted">Send account questions, card disputes, or wire support requests to Lead Bank member care.</p>
            <form><?= csrf_field() ?><textarea class="form-control mb-3" rows="5" placeholder="How can we help?"></textarea><button class="btn btn-navy">Send secure message</button></form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="premium-card p-4 mb-3">
            <i class="fa-solid fa-phone text-warning fa-2x mb-3"></i>
            <h5 class="fw-bold">Call support</h5>
            <p class="muted"><?= e(setting('support_phone', '(800) 417-2049')) ?></p>
            <a class="btn btn-navy" href="tel:<?= e($callNumber) ?>">Call now</a>
        </div>
        <div class="premium-card p-4">
            <i class="fa-brands fa-whatsapp text-warning fa-2x mb-3"></i>
            <h5 class="fw-bold">WhatsApp support</h5>
            <p class="muted">Start a support conversation using the number configured by admin.</p>
            <a class="btn btn-gold" href="https://wa.me/<?= e($whatsAppNumber) ?>" target="_blank" rel="noopener">Open WhatsApp</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
