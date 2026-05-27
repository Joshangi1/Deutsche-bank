</main>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="mb-3"><?= brand_logo('light') ?></div>
                <p><?= e(!empty($GLOBALS['publicStaticMode']) ? 'Programmable banking services, modern security, and financial tools designed for everyday momentum.' : setting('footer_summary', 'Programmable banking services, modern security, and financial tools designed for everyday momentum.')) ?></p>
                <div class="trust-row"><span>NCUA insured</span><span>Equal Housing Lender</span><span>256-bit SSL</span></div>
            </div>
            <div class="col-6 col-lg-2"><h6>Banking</h6><a href="<?= url('personal.php') ?>">Personal</a><a href="<?= url('business.php') ?>">Business</a><a href="<?= url('cards.php') ?>">Cards</a><a href="<?= url('loans.php') ?>">Loans</a></div>
            <div class="col-6 col-lg-2"><h6>Company</h6><a href="<?= url('about.php') ?>">About</a><a href="<?= url('security.php') ?>">Security</a><a href="<?= url('support.php') ?>">Support</a><a href="<?= url('contact.php') ?>">Contact</a></div>
            <?php
                $staticFooter = !empty($GLOBALS['publicStaticMode']);
                $supportPhone = $staticFooter ? '(800) 417-2049' : setting('support_phone', '(800) 417-2049');
                $supportCall = $staticFooter ? $supportPhone : setting('support_call_number', $supportPhone);
                $supportWhatsapp = $staticFooter ? '18004172049' : setting('support_whatsapp_number', '18004172049');
                $supportEmail = $staticFooter ? 'care@example.com' : setting('support_email', 'care@example.com');
            ?>
            <div class="col-lg-4"><h6>Member Care</h6><p class="mb-2"><i class="fa-solid fa-phone me-2"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $supportCall)) ?>"><?= e($supportPhone) ?></a></p><p class="mb-2"><i class="fa-brands fa-whatsapp me-2"></i><a href="https://wa.me/<?= e(preg_replace('/\D/', '', $supportWhatsapp)) ?>" target="_blank" rel="noopener">WhatsApp support</a></p><p class="mb-2"><i class="fa-solid fa-envelope me-2"></i><?= e($supportEmail) ?></p></div>
        </div>
        <div class="footer-bottom">&copy; <?= date('Y') ?> <?= e(UI_BRAND_NAME) ?>. All rights reserved.</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php if (empty($GLOBALS['disableTranslate'])): ?>
    <?= google_translate_script() ?>
<?php endif; ?>
</body>
</html>

