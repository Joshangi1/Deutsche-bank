<?php
declare(strict_types=1);

return [
    'default' => [
        'enabled' => false,
        'agency' => 'Account Protection',
        'name' => 'Security & Account Disclosures',
        'text' => 'Account protection and regulatory disclosures depend on your selected country and account type. Please review the applicable terms and disclosures.',
    ],
    'countries' => [
        'us' => [
            'enabled' => false,
            'aliases' => ['united states', 'usa', 'us'],
            'agency' => 'FDIC',
            'name' => 'Federal Deposit Insurance Corporation',
            'text' => 'Deposits are insured by the Federal Deposit Insurance Corporation and backed by the full faith and credit of the United States government, subject to applicable limits.',
        ],
        'uk' => [
            'enabled' => false,
            'aliases' => ['united kingdom', 'uk', 'gb', 'great britain'],
            'agency' => 'FSCS',
            'name' => 'Financial Services Compensation Scheme',
            'text' => 'Eligible deposits are protected by the Financial Services Compensation Scheme, subject to applicable limits and eligibility rules.',
        ],
        'de' => [
            'enabled' => false,
            'aliases' => ['germany', 'deutschland', 'de'],
            'agency' => 'EdB',
            'name' => 'German Deposit Guarantee Scheme',
            'text' => 'Eligible deposits are protected by the Entschaedigungseinrichtung deutscher Banken under the German statutory deposit guarantee scheme, subject to applicable limits.',
        ],
        'ca' => [
            'enabled' => false,
            'aliases' => ['canada', 'ca'],
            'agency' => 'CDIC',
            'name' => 'Canada Deposit Insurance Corporation',
            'text' => 'Eligible deposits are protected by the Canada Deposit Insurance Corporation, subject to applicable coverage limits and rules.',
        ],
        'au' => [
            'enabled' => false,
            'aliases' => ['australia', 'au'],
            'agency' => 'FCS',
            'name' => 'Financial Claims Scheme',
            'text' => 'Eligible deposits may be protected by the Australian Government Financial Claims Scheme, subject to applicable limits.',
        ],
        'fr' => [
            'enabled' => false,
            'aliases' => ['france', 'fr'],
            'agency' => 'FGDR',
            'name' => 'Fonds de Garantie des Depots et de Resolution',
            'text' => 'Eligible deposits are protected by the French deposit guarantee scheme, subject to applicable limits.',
        ],
        'nl' => [
            'enabled' => false,
            'aliases' => ['netherlands', 'holland', 'nl'],
            'agency' => 'DGS',
            'name' => 'Dutch Deposit Guarantee Scheme',
            'text' => 'Eligible deposits are protected by the Dutch Deposit Guarantee Scheme, subject to applicable limits.',
        ],
        'it' => [
            'enabled' => false,
            'aliases' => ['italy', 'it'],
            'agency' => 'FITD',
            'name' => 'Fondo Interbancario di Tutela dei Depositi',
            'text' => 'Eligible deposits are protected by the Italian deposit guarantee scheme, subject to applicable limits.',
        ],
        'es' => [
            'enabled' => false,
            'aliases' => ['spain', 'es'],
            'agency' => 'FGD',
            'name' => 'Fondo de Garantia de Depositos',
            'text' => 'Eligible deposits are protected by the Spanish Deposit Guarantee Fund, subject to applicable limits.',
        ],
    ],
];
