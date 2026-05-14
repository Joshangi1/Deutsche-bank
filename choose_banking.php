<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$mode = strtolower((string) ($_GET['next'] ?? 'login'));
$mode = $mode === 'register' ? 'register' : 'login';
$pageTitle = $mode === 'register' ? 'Choose account country' : 'Choose sign in country';
$GLOBALS['publicStaticMode'] = true;
$GLOBALS['pageLoginUrl'] = 'choose_banking.php?next=login';

$regions = ['us', 'ca', 'uk', 'ch', 'de'];
$countryCopy = [
    'us' => ['name' => 'United States', 'summary' => 'Checking, ACH, Zelle, bill pay, cards, and wire transfers.'],
    'ca' => ['name' => 'Canada', 'summary' => 'Chequing, Interac e-Transfer, EFT, bill payments, cards, and wires.'],
    'uk' => ['name' => 'United Kingdom', 'summary' => 'Current accounts, Faster Payments, Direct Debits, cards, and CHAPS.'],
    'ch' => ['name' => 'Switzerland', 'summary' => 'Swiss accounts, SIC, QR-bills, cards, IBAN, and international transfers.'],
    'de' => ['name' => 'Germany', 'summary' => 'German account opening, SEPA, IBAN, cards, and local bank details.'],
];

include __DIR__ . '/includes/public_header.php';
?>
<section class="country-choice-shell">
    <div class="container">
        <div class="country-choice-head">
            <span class="eyebrow">Choose your banking country</span>
            <h1><?= $mode === 'register' ? 'Open the right account for your country.' : 'Sign in through your country portal.' ?></h1>
            <p>Each country uses its own banking rails, labels, and account details. Pick your country first so the portal opens the correct experience.</p>
        </div>
        <div class="country-choice-grid">
            <?php foreach ($regions as $region): ?>
                <?php $config = banking_region_config($region); ?>
                <a class="country-choice-card" href="<?= e($mode === 'register' ? $config['register'] : $config['login']) ?>">
                    <span class="country-choice-icon"><i class="fa-solid fa-building-columns"></i></span>
                    <span>
                        <strong><?= e($countryCopy[$region]['name']) ?></strong>
                        <small><?= e($countryCopy[$region]['summary']) ?></small>
                    </span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
