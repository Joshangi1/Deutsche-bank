<?php
declare(strict_types=1);

function normalize_brand_region(?string $countryOrRegion): string
{
    $key = strtolower(trim((string) $countryOrRegion));
    $key = str_replace(['.', '_'], ['', '-'], $key);
    $key = preg_replace('/\s+/', ' ', $key) ?: '';

    return match ($key) {
        'united states', 'united states of america', 'usa', 'u s a', 'us' => 'us',
        'canada', 'ca' => 'ca',
        'united kingdom', 'great britain', 'england', 'uk', 'gb' => 'uk',
        'germany', 'deutschland', 'de' => 'de',
        'switzerland', 'swiss', 'ch' => 'ch',
        'france', 'fr' => 'fr',
        'italy', 'it' => 'it',
        'spain', 'es' => 'es',
        'netherlands', 'holland', 'nl' => 'nl',
        'belgium', 'be' => 'be',
        'austria', 'at' => 'at',
        'ireland', 'ie' => 'ie',
        'portugal', 'pt' => 'pt',
        'luxembourg', 'lu' => 'lu',
        'sweden', 'se' => 'se',
        'norway', 'no' => 'no',
        'denmark', 'dk' => 'dk',
        'finland', 'fi' => 'fi',
        'hong kong', 'hong-kong', 'hk' => 'hk',
        'europe', 'eu', 'european union' => 'eu',
        'international', 'global', 'intl', 'other' => 'intl',
        default => in_array($key, ['us','ca','uk','de','ch','fr','it','es','nl','be','at','ie','pt','lu','se','no','dk','fi','hk','eu','intl'], true) ? $key : 'intl',
    };
}

function brand_key_for_region(string $region): string
{
    return in_array(normalize_brand_region($region), ['us', 'ca'], true) ? 'lead_bank' : 'openpayd';
}

function brand_favicon_type(array $brand): string
{
    $path = strtolower((string) ($brand['favicon'] ?? ''));
    return str_ends_with($path, '.svg') ? 'image/svg+xml' : (str_ends_with($path, '.jpg') || str_ends_with($path, '.jpeg') ? 'image/jpeg' : 'image/png');
}

function getBrandConfig(?string $countryOrRegion = null): array
{
    $region = normalize_brand_region($countryOrRegion ?: 'us');
    $isLead = brand_key_for_region($region) === 'lead_bank';
    $lead = [
        'brand_key' => 'lead_bank',
        'brand_name' => 'Lead Bank',
        'brand_short_name' => 'Lead Bank',
        'public_name' => 'Lead Bank',
        'logo' => 'assets/icons/lead-bank-logo.jpg',
        'logo_mark' => 'assets/icons/lead-bank-logo.jpg',
        'favicon' => 'assets/icons/favicon.svg',
        'primary_color' => '#2F5BFF',
        'secondary_color' => '#4DB5FF',
        'dark_color' => '#071B45',
        'surface_color' => '#F6F8FC',
        'text_color' => '#071B45',
        'accent_color' => '#4DB5FF',
        'gradient' => 'linear-gradient(135deg, #071B45 0%, #123A8C 42%, #2F5BFF 100%)',
        'sidebar_theme' => 'lead-sidebar',
        'dashboard_theme' => 'lead-dashboard',
        'login_theme' => 'lead-auth',
        'register_theme' => 'lead-register',
    ];
    $openPayd = [
        'brand_key' => 'openpayd',
        'brand_name' => 'OpenPayd Financial Services Limited',
        'brand_short_name' => 'OpenPayd',
        'public_name' => 'OpenPayd',
        'logo' => 'assets/icons/openpayd-logo.png',
        'logo_mark' => 'assets/icons/openpayd-icon.jpg',
        'favicon' => 'assets/icons/openpayd-icon.jpg',
        'primary_color' => '#0F4C5C',
        'secondary_color' => '#1A7F8E',
        'dark_color' => '#061B2B',
        'surface_color' => '#F5FAFB',
        'text_color' => '#071B2D',
        'accent_color' => '#2AA7B8',
        'gradient' => 'linear-gradient(135deg, #061B2B 0%, #0F4C5C 48%, #1A7F8E 100%)',
        'sidebar_theme' => 'openpayd-sidebar',
        'dashboard_theme' => 'openpayd-dashboard',
        'login_theme' => 'openpayd-auth',
        'register_theme' => 'openpayd-register',
    ];

    $brand = $isLead ? $lead : $openPayd;
    $regionDetails = brand_banking_region_config($region);
    return array_merge($brand, [
        'banking_region' => $regionDetails['region'],
        'country' => $regionDetails['country'],
        'account_labels' => [
            'account' => $regionDetails['account_label'],
            'routing' => $regionDetails['routing_label'],
            'primary' => $regionDetails['primary_detail_label'],
            'secondary' => $regionDetails['secondary_detail_label'],
        ],
        'transfer_labels' => [
            'primary' => $regionDetails['rail_primary'],
            'scheduled' => $regionDetails['rail_scheduled'],
            'bank' => $regionDetails['rail_bank'],
            'wire' => $regionDetails['rail_wire'],
            'transfer' => $regionDetails['transfer'],
        ],
        'payment_rails' => $regionDetails['payment_rails'],
    ]);
}

function brand_banking_region_config(string $regionOrCountry): array
{
    $key = normalize_brand_region($regionOrCountry);
    $euCountries = ['fr','it','es','nl','be','at','ie','pt','lu','se','no','dk','fi','eu','intl'];
    $countryNames = [
        'fr' => 'France', 'it' => 'Italy', 'es' => 'Spain', 'nl' => 'Netherlands',
        'be' => 'Belgium', 'at' => 'Austria', 'ie' => 'Ireland', 'pt' => 'Portugal',
        'lu' => 'Luxembourg', 'se' => 'Sweden', 'no' => 'Norway', 'dk' => 'Denmark',
        'fi' => 'Finland', 'eu' => 'Europe', 'intl' => 'International',
    ];
    $configs = [
        'us' => ['region' => 'us', 'country' => 'United States', 'language' => 'en', 'currency' => 'USD', 'login' => 'login_us.php', 'register' => 'register_us.php', 'account_type' => 'Premium Checking', 'routing' => US_ROUTING_NUMBER, 'rail_primary' => 'Instant Pay', 'rail_scheduled' => 'Bill Pay', 'rail_bank' => 'ACH Transfers', 'rail_wire' => 'Wire Transfers', 'transfer' => 'Wire transfer', 'workspace' => 'Lead Bank US banking', 'account_label' => 'Account', 'routing_label' => 'Routing', 'primary_detail_label' => 'Account Number', 'secondary_detail_label' => 'Routing Number', 'payment_rails' => ['ACH', 'Wire Transfer', 'Bill Pay', 'Debit Cards']],
        'ca' => ['region' => 'ca', 'country' => 'Canada', 'language' => 'en', 'currency' => 'CAD', 'login' => 'login_ca.php', 'register' => 'register_ca.php', 'account_type' => 'Premium Chequing', 'routing' => '001000002', 'rail_primary' => 'Interac e-Transfer', 'rail_scheduled' => 'Bill Payments', 'rail_bank' => 'EFT Transfers', 'rail_wire' => 'Wire Transfers', 'transfer' => 'Wire transfer', 'workspace' => 'Lead Bank Canada banking', 'account_label' => 'Account', 'routing_label' => 'Institution/Transit', 'primary_detail_label' => 'Account Number', 'secondary_detail_label' => 'Institution / Transit', 'payment_rails' => ['Interac e-Transfer', 'EFT', 'Wire Transfer', 'Debit Cards']],
        'uk' => ['region' => 'uk', 'country' => 'United Kingdom', 'language' => 'en', 'currency' => 'GBP', 'login' => 'login_uk.php', 'register' => 'register_uk.php', 'account_type' => 'Current Account', 'routing' => '040004', 'rail_primary' => 'Faster Payments', 'rail_scheduled' => 'Direct Debits', 'rail_bank' => 'Standing Orders', 'rail_wire' => 'CHAPS Transfers', 'transfer' => 'CHAPS transfer', 'workspace' => 'OpenPayd UK banking', 'account_label' => 'Account', 'routing_label' => 'Sort code', 'primary_detail_label' => 'Account Number', 'secondary_detail_label' => 'Sort Code', 'payment_rails' => ['Faster Payments', 'CHAPS', 'Direct Debits', 'Debit Cards']],
        'de' => ['region' => 'de', 'country' => 'Germany', 'language' => 'en', 'currency' => 'EUR', 'login' => 'login.php?region=de', 'register' => 'register.php?region=de', 'account_type' => 'Current Account', 'routing' => DEFAULT_BIC, 'rail_primary' => 'SEPA Instant', 'rail_scheduled' => 'Standing Orders', 'rail_bank' => 'SEPA Transfers', 'rail_wire' => 'Transfers', 'transfer' => 'SEPA transfer', 'workspace' => 'OpenPayd Germany banking', 'account_label' => 'IBAN', 'routing_label' => 'BIC/SWIFT', 'primary_detail_label' => 'IBAN', 'secondary_detail_label' => 'BIC', 'payment_rails' => ['IBAN', 'BIC', 'SEPA', 'Debit Cards']],
        'ch' => ['region' => 'ch', 'country' => 'Switzerland', 'language' => 'en', 'currency' => 'CHF', 'login' => 'login_ch.php', 'register' => 'register_ch.php', 'account_type' => 'Private Account', 'routing' => 'OPPDCHZZXXX', 'rail_primary' => 'SIC Instant', 'rail_scheduled' => 'QR-Bills', 'rail_bank' => 'Swiss Transfers', 'rail_wire' => 'International Transfers', 'transfer' => 'International transfer', 'workspace' => 'OpenPayd Switzerland banking', 'account_label' => 'IBAN', 'routing_label' => 'BIC/SWIFT', 'primary_detail_label' => 'IBAN', 'secondary_detail_label' => 'SWIFT', 'payment_rails' => ['IBAN', 'SIC', 'QR-Bill', 'SWIFT']],
        'hk' => ['region' => 'hk', 'country' => 'Hong Kong', 'language' => 'en', 'currency' => 'HKD', 'login' => 'login.php?region=hk', 'register' => 'register.php?region=hk', 'account_type' => 'Current Account', 'routing' => '024', 'rail_primary' => 'FPS', 'rail_scheduled' => 'Scheduled Payments', 'rail_bank' => 'Local Transfers', 'rail_wire' => 'SWIFT Transfers', 'transfer' => 'SWIFT transfer', 'workspace' => 'OpenPayd Hong Kong banking', 'account_label' => 'Account', 'routing_label' => 'Bank Code', 'primary_detail_label' => 'Account Number', 'secondary_detail_label' => 'Bank Code', 'payment_rails' => ['FPS', 'Bank Code', 'Account Number', 'SWIFT']],
    ];
    if (in_array($key, $euCountries, true)) {
        $country = $countryNames[$key] ?? 'International';
        $configs[$key] = ['region' => $key, 'country' => $country, 'language' => 'en', 'currency' => $key === 'intl' ? 'EUR' : 'EUR', 'login' => 'login.php?region=' . rawurlencode($key), 'register' => 'register.php?region=' . rawurlencode($key), 'account_type' => 'Current Account', 'routing' => 'OPPDEFFXXX', 'rail_primary' => 'SEPA Instant', 'rail_scheduled' => 'Standing Orders', 'rail_bank' => 'SEPA Transfers', 'rail_wire' => 'International Transfers', 'transfer' => 'SEPA transfer', 'workspace' => 'OpenPayd ' . $country . ' banking', 'account_label' => 'IBAN', 'routing_label' => 'BIC', 'primary_detail_label' => 'IBAN', 'secondary_detail_label' => 'BIC', 'payment_rails' => ['IBAN', 'BIC', 'SEPA', 'Debit Cards']];
    }
    return $configs[$key] ?? $configs['intl'];
}

function brand_config_for_user(?array $user = null, ?array $account = null): array
{
    if (!empty($user['banking_region'])) {
        return getBrandConfig((string) $user['banking_region']);
    }
    if (!empty($user['brand']) && in_array((string) $user['brand'], ['lead_bank', 'openpayd'], true)) {
        return getBrandConfig((string) $user['brand'] === 'lead_bank' ? 'us' : (($user['country'] ?? '') ?: 'intl'));
    }
    if (!empty($user['country'])) {
        return getBrandConfig((string) $user['country']);
    }
    if ($account && !empty($account['iban'])) {
        return getBrandConfig(str_starts_with((string) $account['iban'], 'CH') ? 'ch' : 'de');
    }
    return getBrandConfig('us');
}

function current_brand_config(): array
{
    if (!empty($GLOBALS['brandConfig']) && is_array($GLOBALS['brandConfig'])) {
        return $GLOBALS['brandConfig'];
    }
    $region = $GLOBALS['authRegion'] ?? ($_GET['region'] ?? 'us');
    return getBrandConfig(is_string($region) ? $region : 'us');
}

function brand_body_class(array $brand): string
{
    return 'brand-' . preg_replace('/[^a-z0-9-]+/', '-', str_replace('_', '-', (string) $brand['brand_key']));
}

function brand_css_variables(array $brand): string
{
    $vars = [
        '--navy' => $brand['primary_color'],
        '--gold' => $brand['secondary_color'],
        '--lead-primary' => $brand['primary_color'],
        '--lead-primary-dark' => $brand['dark_color'],
        '--lead-secondary' => $brand['secondary_color'],
        '--lead-accent' => $brand['accent_color'],
        '--lead-bg' => $brand['surface_color'],
        '--lead-text' => $brand['text_color'],
        '--lead-gradient' => $brand['gradient'],
    ];
    $css = ':root{';
    foreach ($vars as $key => $value) {
        $css .= $key . ':' . $value . ';';
    }
    $css .= '}';
    return '<style>' . $css . '</style>';
}
