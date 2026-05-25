<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$mode = strtolower((string) ($_GET['next'] ?? 'login'));
$mode = $mode === 'register' ? 'register' : 'login';
$pageTitle = 'Choose Your Banking Region | Lead Bank';
$GLOBALS['publicStaticMode'] = true;
$GLOBALS['pageLoginUrl'] = 'choose_banking.php?next=login';

$regions = ['us', 'ca', 'uk', 'ch', 'de'];
$countryCopy = [
    'us' => ['flag' => 'us', 'name' => 'United States', 'summary' => 'Checking, ACH, bill pay, cards, and wire transfers.'],
    'ca' => ['flag' => 'ca', 'name' => 'Canada', 'summary' => 'Chequing, Interac e-Transfer, EFT, cards, and wires.'],
    'uk' => ['flag' => 'gb', 'name' => 'United Kingdom', 'summary' => 'Current accounts, Faster Payments, cards, and CHAPS.'],
    'ch' => ['flag' => 'ch', 'name' => 'Switzerland', 'summary' => 'Swiss accounts, QR-bills, IBAN, and international transfers.'],
    'de' => ['flag' => 'de', 'name' => 'Germany', 'summary' => 'German accounts, SEPA, IBAN, cards, and local details.'],
];

include __DIR__ . '/includes/public_header.php';
?>
<section class="country-choice-shell">
    <div class="container">
        <div class="country-choice-portal">
            <div class="country-choice-head">
                <span class="eyebrow">Lead Bank International</span>
                <h1>Choose Your Banking Region</h1>
                <p>Select your region to continue to secure <?= $mode === 'register' ? 'account opening' : 'sign in' ?>.</p>
            </div>
            <div class="country-choice-grid">
                <?php foreach ($regions as $region): ?>
                    <?php $config = banking_region_config($region); ?>
                    <a class="country-choice-card" href="<?= e($mode === 'register' ? $config['register'] : $config['login']) ?>">
                        <span class="country-choice-flag"><img src="https://flagcdn.com/w80/<?= e($countryCopy[$region]['flag']) ?>.png" alt="<?= e($countryCopy[$region]['name']) ?> flag" loading="lazy"></span>
                        <span>
                            <strong><?= e($countryCopy[$region]['name']) ?></strong>
                            <small><?= e($countryCopy[$region]['summary']) ?></small>
                        </span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
