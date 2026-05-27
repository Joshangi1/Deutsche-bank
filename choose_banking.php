<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$mode = strtolower((string) ($_GET['next'] ?? 'login'));
$mode = $mode === 'register' ? 'register' : 'login';
$pageTitle = 'Choose Your Banking Region';
$GLOBALS['publicStaticMode'] = true;
$GLOBALS['pageLoginUrl'] = 'choose_banking.php?next=login';
$GLOBALS['brandConfig'] = getBrandConfig('us');

$regions = ['us', 'ca', 'uk', 'de', 'ch', 'fr', 'it', 'es', 'nl', 'be', 'at', 'ie', 'pt', 'lu', 'se', 'no', 'dk', 'fi', 'hk', 'intl'];
$countryCopy = [
    'us' => ['flag' => 'us', 'name' => 'United States', 'summary' => 'Checking, ACH, bill pay, cards, and wire transfers.'],
    'ca' => ['flag' => 'ca', 'name' => 'Canada', 'summary' => 'Chequing, Interac e-Transfer, EFT, cards, and wires.'],
    'uk' => ['flag' => 'gb', 'name' => 'United Kingdom', 'summary' => 'Current accounts, Faster Payments, cards, and CHAPS.'],
    'ch' => ['flag' => 'ch', 'name' => 'Switzerland', 'summary' => 'Swiss accounts, QR-bills, IBAN, and international transfers.'],
    'de' => ['flag' => 'de', 'name' => 'Germany', 'summary' => 'German accounts, SEPA, IBAN, cards, and local details.'],
    'fr' => ['flag' => 'fr', 'name' => 'France', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'it' => ['flag' => 'it', 'name' => 'Italy', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'es' => ['flag' => 'es', 'name' => 'Spain', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'nl' => ['flag' => 'nl', 'name' => 'Netherlands', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'be' => ['flag' => 'be', 'name' => 'Belgium', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'at' => ['flag' => 'at', 'name' => 'Austria', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'ie' => ['flag' => 'ie', 'name' => 'Ireland', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'pt' => ['flag' => 'pt', 'name' => 'Portugal', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'lu' => ['flag' => 'lu', 'name' => 'Luxembourg', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'se' => ['flag' => 'se', 'name' => 'Sweden', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'no' => ['flag' => 'no', 'name' => 'Norway', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'dk' => ['flag' => 'dk', 'name' => 'Denmark', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'fi' => ['flag' => 'fi', 'name' => 'Finland', 'summary' => 'IBAN accounts, SEPA, cards, and transfers.'],
    'hk' => ['flag' => 'hk', 'name' => 'Hong Kong', 'summary' => 'FPS, bank code, SWIFT, and card tools.'],
    'intl' => ['flag' => '', 'name' => 'International', 'summary' => 'International account opening with local banking tools.'],
];

include __DIR__ . '/includes/public_header.php';
?>
<section class="country-choice-shell">
    <div class="container">
        <div class="country-choice-portal">
            <div class="country-choice-head">
                <span class="eyebrow">Private banking gateway</span>
                <h1>Choose Your Banking Region</h1>
                <p>Select your region to continue to secure <?= $mode === 'register' ? 'account opening' : 'sign in' ?>.</p>
            </div>
            <div class="country-choice-grid">
                <?php foreach ($regions as $region): ?>
                    <?php $config = banking_region_config($region); ?>
                    <?php $brand = getBrandConfig($region); ?>
                    <a class="country-choice-card" href="<?= e($mode === 'register' ? $config['register'] : $config['login']) ?>">
                        <span class="country-choice-flag"><?php if ($countryCopy[$region]['flag']): ?><img src="https://flagcdn.com/w80/<?= e($countryCopy[$region]['flag']) ?>.png" alt="<?= e($countryCopy[$region]['name']) ?> flag" loading="lazy"><?php else: ?><i class="fa-solid fa-globe"></i><?php endif; ?></span>
                        <span>
                            <strong><?= e($countryCopy[$region]['name']) ?></strong>
                            <em><?= e((string) $brand['brand_short_name']) ?></em>
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
