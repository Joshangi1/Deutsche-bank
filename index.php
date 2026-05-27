<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Deutsche Bank | Programmable Banking';
$GLOBALS['publicStaticMode'] = true;
include __DIR__ . '/includes/public_header.php';
?>
<section class="hero">
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <h1>A partner you can bank on.</h1>
                <p class="mt-4">Programmable financial services built to support any use case, quickly.</p>
                <div class="home-region-router mt-5" id="banking-country">
                    <label class="eyebrow" for="homeRegionSelect">Choose your banking country</label>
                    <div class="home-region-control">
                        <select id="homeRegionSelect" class="form-select form-select-lg" data-home-region>
                            <option value="us" data-login="login_us.php" data-register="register_us.php" data-copy="U.S. checking, ACH, Zelle, bill pay, and wire transfers.">United States</option>
                            <option value="ca" data-login="login_ca.php" data-register="register_ca.php" data-copy="Canadian chequing, Interac e-Transfer, EFT, bill payments, and wires.">Canada</option>
                            <option value="uk" data-login="login_uk.php" data-register="register_uk.php" data-copy="UK current accounts, Faster Payments, Direct Debits, and CHAPS.">United Kingdom</option>
                            <option value="ch" data-login="login_ch.php" data-register="register_ch.php" data-copy="Swiss accounts, SIC, QR-bills, IBAN, and international transfers.">Switzerland</option>
                            <option value="de" data-login="login_de.php" data-register="register_de.php" data-copy="German accounts, SEPA, IBAN, and local banking details.">Germany</option>
                        </select>
                        <p class="muted mb-0" data-home-region-copy>U.S. checking, ACH, Zelle, bill pay, and wire transfers.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a class="btn btn-primary-pill btn-lg" href="login_us.php" data-region-auth="login">Sign in</a>
                        <a class="btn btn-light btn-lg border" href="register_us.php" data-region-auth="register">Open account</a>
                    </div>
                </div>
                <div class="hero-panel">
                    <p><strong>Reinventing Modern Banking.</strong> From regulatory oversight to modern account tools, our role is to resolve friction behind the scenes so you can focus on creating meaningful financial experiences.</p>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-bank-visual" aria-hidden="true">
                    <div class="bank-tower tower-left"></div>
                    <div class="bank-tower tower-main"></div>
                    <div class="bank-tower tower-right"></div>
                    <div class="bank-plinth"></div>
                    <div class="bank-watermark">BANK</div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="section-pad service-band">
    <div class="container">
        <div class="row align-items-end mb-4">
            <div class="col-lg-7"><h2 class="section-title display-6">Banking built around calm confidence.</h2><p class="muted">Modern tools, premium support, and transparent products for personal and business members.</p></div>
            <div class="col-lg-5 text-lg-end"><a href="personal.php" class="btn btn-navy">Explore products</a></div>
        </div>
        <div class="row g-4">
            <?php foreach ([['fa-wallet','Premium Checking','No monthly maintenance fees, early direct deposit, and smart spend insights.'],['fa-piggy-bank','High-Yield Savings','Tiered savings with goal tracking and automatic round-ups.'],['fa-credit-card','Signature Credit Cards','Cash back, travel protections, spend controls, and instant card freeze.'],['fa-house-chimney','Loans & Mortgages','Competitive rates with transparent underwriting and local guidance.']] as $i => $card): ?>
            <div class="col-md-6 col-xl-3"><div class="premium-card h-100 p-4 stat-card" style="animation-delay: <?= $i * .08 ?>s"><div class="icon-chip mb-3"><i class="fa-solid <?= $card[0] ?>"></i></div><h5 class="fw-bold"><?= e($card[1]) ?></h5><p class="muted mb-0"><?= e($card[2]) ?></p></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="section-pad">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6"><h2 class="section-title display-6">A dashboard that keeps money moving.</h2><p class="muted">See balances, verify transfers with a transaction code, manage beneficiaries, monitor cards, upload check deposits, and download statements in a polished member portal.</p>
                <div class="row g-3 mt-2">
                    <div class="col-6"><div class="bank-card p-3"><i class="fa-solid fa-bolt text-primary"></i><div class="metric">90 sec</div><div class="muted">Average transfer setup</div></div></div>
                    <div class="col-6"><div class="bank-card p-3"><i class="fa-solid fa-user-shield text-primary"></i><div class="metric">24/7</div><div class="muted">Fraud monitoring</div></div></div>
                </div>
            </div>
            <div class="col-lg-6"><div class="table-card p-4"><canvas data-chart="line" height="220"></canvas></div></div>
        </div>
    </div>
</section>
<section class="section-pad cta-band">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4"><h2 class="fw-bold">Members trust Deutsche Bank.</h2><p class="text-white-50">Modern banking support without losing a human standard of care.</p></div>
            <?php foreach ([['Maya R.','The dashboard feels clean and fast. Transfers are clear before I confirm them.'],['Jordan P.','Our business accounts, card controls, and alerts finally live in one place.'],['Elena S.','The security notices are plain-English and useful. That matters.']] as $quote): ?>
            <div class="col-lg-4"><div class="p-4 bg-white text-dark h-100"><div class="text-primary mb-2">★★★★★</div><p>"<?= e($quote[1]) ?>"</p><strong><?= e($quote[0]) ?></strong></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="section-pad">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5"><h2 class="section-title">Questions before you join?</h2><p class="muted">Our member care team is available by phone, secure message, or branch appointment.</p><a class="btn btn-navy" href="contact.php">Contact us</a></div>
            <div class="col-lg-7">
                <div class="accordion" id="faq">
                    <?php foreach ([['How do I open an account?','Create an online membership and sign in to access the banking portal.'],['Can I manage support contact options?','Yes. Administrators can set the call number, WhatsApp number, and support email from settings.'],['Does the portal include admin approvals?','Yes. Admins can review deposits, transfers, users, notifications, transaction codes, and audit logs.']] as $idx => $faq): ?>
                    <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button <?= $idx ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#faq<?= $idx ?>"><?= e($faq[0]) ?></button></h2><div id="faq<?= $idx ?>" class="accordion-collapse collapse <?= $idx ? '' : 'show' ?>" data-bs-parent="#faq"><div class="accordion-body"><?= e($faq[1]) ?></div></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
