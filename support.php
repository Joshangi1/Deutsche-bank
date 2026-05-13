<?php
$pageTitle = 'Support';
include __DIR__ . '/includes/public_header.php';
$callNumber = preg_replace('/[^0-9+]/', '', setting('support_call_number', setting('support_phone', '(800) 417-2049')));
$whatsAppNumber = preg_replace('/\D/', '', setting('support_whatsapp_number', '18004172049'));
?>
<section class="section-pad service-band">
    <div class="container">
        <h1 class="section-title display-5">Support that respects your time.</h1>
        <p class="lead muted"><?= e(setting('contact_intro', 'Questions about membership, loans, cards, or business banking? Reach our member care team.')) ?></p>
        <div class="row g-4 mt-3">
            <div class="col-md-4">
                <a class="premium-card p-4 h-100 d-block" href="tel:<?= e($callNumber) ?>">
                    <i class="fa-solid fa-phone text-warning fa-2x mb-3"></i>
                    <h5>Call Support</h5>
                    <p><?= e(setting('support_phone', '(800) 417-2049')) ?></p>
                </a>
            </div>
            <div class="col-md-4">
                <a class="premium-card p-4 h-100 d-block" href="https://wa.me/<?= e($whatsAppNumber) ?>" target="_blank" rel="noopener">
                    <i class="fa-brands fa-whatsapp text-warning fa-2x mb-3"></i>
                    <h5>WhatsApp</h5>
                    <p>Start a secure support conversation.</p>
                </a>
            </div>
            <div class="col-md-4">
                <div class="premium-card p-4 h-100">
                    <i class="fa-solid fa-comments text-warning fa-2x mb-3"></i>
                    <h5>Secure Message</h5>
                    <p>Available inside online banking.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
