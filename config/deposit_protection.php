<?php
declare(strict_types=1);

return [
    'default' => [
        'agency' => 'Local Deposit Protection',
        'name' => 'Local Deposit Protection',
        'text' => 'Deposit protection may be available under applicable local banking regulations, subject to eligibility and limits.',
    ],
    'countries' => [
        'us' => [
            'aliases' => ['united states', 'usa', 'us'],
            'agency' => 'FDIC',
            'name' => 'FDIC Insured',
            'text' => 'Backed by the full faith and credit of the United States Government, subject to applicable limits.',
        ],
        'uk' => [
            'aliases' => ['united kingdom', 'uk', 'gb', 'great britain'],
            'agency' => 'FSCS',
            'name' => 'Financial Services Compensation Scheme',
            'text' => 'Eligible deposits are protected by the Financial Services Compensation Scheme, subject to applicable limits and eligibility rules.',
        ],
        'de' => [
            'aliases' => ['germany', 'deutschland', 'de', 'eu', 'european union'],
            'agency' => 'EdB',
            'name' => 'Statutory deposit guarantee',
            'text' => 'Eligible deposits may be protected by the Entschaedigungseinrichtung deutscher Banken under the German statutory deposit guarantee scheme, subject to applicable limits.',
        ],
        'eu' => [
            'aliases' => ['europe', 'european economic area', 'eea'],
            'agency' => 'DGS',
            'name' => 'Local Deposit Protection Scheme',
            'text' => 'Eligible deposits may be protected under the applicable local deposit guarantee scheme, subject to eligibility and limits.',
        ],
        'ca' => [
            'aliases' => ['canada', 'ca'],
            'agency' => 'CDIC',
            'name' => 'Canada Deposit Insurance Corporation',
            'text' => 'Eligible deposits are protected by the Canada Deposit Insurance Corporation, subject to applicable coverage limits and rules.',
        ],
        'au' => [
            'aliases' => ['australia', 'au'],
            'agency' => 'FCS',
            'name' => 'Financial Claims Scheme',
            'text' => 'Eligible deposits may be protected by the Australian Government Financial Claims Scheme, subject to applicable limits.',
        ],
        'fr' => [
            'aliases' => ['france', 'fr'],
            'agency' => 'FGDR',
            'name' => 'Fonds de Garantie des Depots et de Resolution',
            'text' => 'Eligible deposits are protected by the French deposit guarantee scheme, subject to applicable limits.',
        ],
        'nl' => [
            'aliases' => ['netherlands', 'holland', 'nl'],
            'agency' => 'DGS',
            'name' => 'Dutch Deposit Guarantee Scheme',
            'text' => 'Eligible deposits are protected by the Dutch Deposit Guarantee Scheme, subject to applicable limits.',
        ],
        'it' => [
            'aliases' => ['italy', 'it'],
            'agency' => 'FITD',
            'name' => 'Fondo Interbancario di Tutela dei Depositi',
            'text' => 'Eligible deposits are protected by the Italian deposit guarantee scheme, subject to applicable limits.',
        ],
        'es' => [
            'aliases' => ['spain', 'es'],
            'agency' => 'FGD',
            'name' => 'Fondo de Garantia de Depositos',
            'text' => 'Eligible deposits are protected by the Spanish Deposit Guarantee Fund, subject to applicable limits.',
        ],
        'ng' => [
            'aliases' => ['nigeria', 'ng'],
            'agency' => 'NDIC',
            'name' => 'Nigeria Deposit Insurance Corporation',
            'text' => 'Eligible deposits may be protected by the Nigeria Deposit Insurance Corporation, subject to applicable limits and rules.',
        ],
        'ch' => [
            'aliases' => ['switzerland', 'swiss', 'ch'],
            'agency' => 'esisuisse',
            'name' => 'Swiss deposit insurance',
            'text' => 'Eligible deposits may be protected through esisuisse under Swiss depositor protection rules, subject to applicable limits.',
        ],
    ],
];
