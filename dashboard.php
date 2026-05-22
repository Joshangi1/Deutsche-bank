<?php
$pageTitle = 'Account Overview';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/frontend_components.php';
include __DIR__ . '/includes/user_header.php';

$account = user_account((int) $user['id']);
if (!$account) {
    flash('danger', 'Account record not found. Please contact support.');
    include __DIR__ . '/includes/user_footer.php';
    exit;
}
$allAccounts = user_accounts((int) $user['id']);
$allAccounts = $allAccounts ?: [$account];

$bankingRegion = user_banking_region($user, $account);
$regionConfig = banking_region_config($bankingRegion);
$isUsAccount = $bankingRegion === 'us';
$currency = user_account_currency($user, $account);
$useGermanLabels = $regionConfig['language'] === 'de';
$displayIban = $account['iban'] ?? $user['iban'] ?? '';
$displayIban = $displayIban !== '' ? format_iban_display($displayIban) : '';
$displayBic = $account['bic'] ?? $account['routing_number'] ?? DEFAULT_BIC;

$txStmt = db()->prepare('SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 6');
$txStmt->execute([$user['id']]);
$txRows = $txStmt->fetchAll();

$txCountStmt = db()->prepare('SELECT COUNT(*) c FROM transactions WHERE user_id=?');
$txCountStmt->execute([$user['id']]);
$txCount = (int) $txCountStmt->fetch()['c'];

$cardStmt = db()->prepare('SELECT * FROM cards WHERE user_id=? LIMIT 1');
$cardStmt->execute([$user['id']]);
$card = $cardStmt->fetch() ?: ['card_last4' => substr((string) ($account['account_number'] ?? '0000'), -4), 'status' => 'preparing'];

$upcomingBills = db()->prepare('SELECT * FROM billers WHERE user_id=? ORDER BY due_day LIMIT 4');
$upcomingBills->execute([$user['id']]);
$billRows = $upcomingBills->fetchAll();

$recipients = db()->prepare('SELECT * FROM payment_recipients WHERE user_id=? ORDER BY COALESCE(last_used_at, created_at) DESC LIMIT 4');
$recipients->execute([$user['id']]);
$recipientRows = $recipients->fetchAll();

$monthlyIncome = db()->prepare('SELECT COALESCE(SUM(amount),0) total FROM transactions WHERE user_id=? AND amount > 0 AND created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")');
$monthlyIncome->execute([$user['id']]);
$monthlyExpense = db()->prepare('SELECT COALESCE(SUM(ABS(amount)),0) total FROM transactions WHERE user_id=? AND amount < 0 AND created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")');
$monthlyExpense->execute([$user['id']]);

$isNewAccount = $txCount === 0 && (float) $account['available_balance'] === 0.0 && (float) $account['pending_balance'] === 0.0 && (float) $account['savings_balance'] === 0.0;
$accountStatus = strtolower((string) ($user['status'] ?? 'active'));
$verificationStatus = strtolower((string) ($user['verification_status'] ?? 'not_started'));

if ($bankingRegion === 'us') {
    $ui = [
        'available' => 'Available balance', 'pending' => 'Pending', 'savings' => 'Savings', 'account' => 'Account',
        'transfer' => 'Wire transfer', 'recent' => 'Recent transactions', 'view_all' => 'View all',
        'balance' => 'Balance trend', 'cash' => 'Cash flow', 'income' => 'Income', 'expenses' => 'Expenses',
        'verification' => 'Verification status', 'review' => 'Current account review', 'quick' => 'Quick access',
        'link_card' => 'Add Credit Card', 'statements' => 'Statements', 'security' => 'Security',
        'hero_eyebrow' => 'Welcome to Deutsche', 'hero_title' => 'Your U.S. checking account is ready',
        'hero_copy' => 'Routing, bill pay, Instant Pay, ACH, and wire tools are available after verification.',
        'bill_title' => 'Upcoming bill payments', 'bill_day' => 'Due day', 'bill_active' => 'AUTOPAY', 'bill_manual' => 'MANUAL',
        'bill_empty' => 'No bill payments yet. Add a biller when you are ready.',
        'recipient_title' => 'Instant Pay recipients', 'recipient_action' => 'Send', 'recipient_empty' => 'No Instant Pay recipients yet.',
    ];
} elseif ($bankingRegion === 'ca') {
    $ui = [
        'available' => 'Available balance', 'pending' => 'Pending', 'savings' => 'Savings', 'account' => 'Account',
        'transfer' => 'Wire transfer', 'recent' => 'Recent transactions', 'view_all' => 'View all',
        'balance' => 'Balance trend', 'cash' => 'Cash flow', 'income' => 'Income', 'expenses' => 'Expenses',
        'verification' => 'Verification status', 'review' => 'Current account review', 'quick' => 'Quick access',
        'link_card' => 'Add Credit Card', 'statements' => 'Statements', 'security' => 'Security',
        'hero_eyebrow' => 'Welcome to Deutsche Canada', 'hero_title' => 'Your Canadian chequing account is ready',
        'hero_copy' => 'Interac e-Transfer, EFT, bill payments, and wire tools are available after verification.',
        'bill_title' => 'Upcoming bill payments', 'bill_day' => 'Due day', 'bill_active' => 'AUTOPAY', 'bill_manual' => 'MANUAL',
        'bill_empty' => 'No bill payments yet.', 'recipient_title' => 'Interac recipients', 'recipient_action' => 'Send', 'recipient_empty' => 'No recipients yet.',
    ];
} elseif ($bankingRegion === 'uk') {
    $ui = [
        'available' => 'Available balance', 'pending' => 'Pending', 'savings' => 'Savings', 'account' => 'Account',
        'transfer' => 'CHAPS transfer', 'recent' => 'Recent transactions', 'view_all' => 'View all',
        'balance' => 'Balance trend', 'cash' => 'Cash flow', 'income' => 'Income', 'expenses' => 'Expenses',
        'verification' => 'Verification status', 'review' => 'Current account review', 'quick' => 'Quick access',
        'link_card' => 'Add Credit Card', 'statements' => 'Statements', 'security' => 'Security',
        'hero_eyebrow' => 'Welcome to Deutsche UK', 'hero_title' => 'Your UK current account is ready',
        'hero_copy' => 'Faster Payments, standing orders, direct debits, and CHAPS tools are available after verification.',
        'bill_title' => 'Direct debits and standing orders', 'bill_day' => 'Run day', 'bill_active' => 'ACTIVE', 'bill_manual' => 'MANUAL',
        'bill_empty' => 'No direct debits yet.', 'recipient_title' => 'Faster Payments recipients', 'recipient_action' => 'Send', 'recipient_empty' => 'No recipients yet.',
    ];
} elseif ($bankingRegion === 'ch') {
    $ui = [
        'available' => 'Available balance', 'pending' => 'Pending', 'savings' => 'Savings', 'account' => 'Account',
        'transfer' => 'International transfer', 'recent' => 'Recent transactions', 'view_all' => 'View all',
        'balance' => 'Balance trend', 'cash' => 'Cash flow', 'income' => 'Income', 'expenses' => 'Expenses',
        'verification' => 'Verification status', 'review' => 'Current account review', 'quick' => 'Quick access',
        'link_card' => 'Add Credit Card', 'statements' => 'Statements', 'security' => 'Security',
        'hero_eyebrow' => 'Welcome to Deutsche Switzerland', 'hero_title' => 'Your Swiss private account is ready',
        'hero_copy' => 'Swiss IBAN, BIC/SWIFT, SIC payments, QR-bills, and international transfer tools are available after verification.',
        'bill_title' => 'Scheduled QR-bills', 'bill_day' => 'Run day', 'bill_active' => 'ACTIVE', 'bill_manual' => 'MANUAL',
        'bill_empty' => 'No QR-bills yet.', 'recipient_title' => 'Swiss recipients', 'recipient_action' => 'Send', 'recipient_empty' => 'No recipients yet.',
    ];
} elseif ($useGermanLabels) {
    $ui = [
        'available' => 'Verfuegbarer Saldo', 'pending' => 'Vorgemerkt', 'savings' => 'Tagesgeld', 'account' => 'Konto',
        'transfer' => 'SEPA-Ueberweisung', 'recent' => 'Aktuelle Transaktionen', 'view_all' => 'Alle anzeigen',
        'balance' => 'Ausgewogener Trend', 'cash' => 'Cashflow', 'income' => 'Einkommen', 'expenses' => 'Kosten',
        'verification' => 'Verifizierungsstatus', 'review' => 'Aktuelle Kontoueberpruefung', 'quick' => 'Schnellzugriff',
        'link_card' => 'Kreditkarte hinzufuegen', 'statements' => 'Kontoauszuege', 'security' => 'Sicherheit',
        'hero_eyebrow' => 'Willkommen bei Deutsche', 'hero_title' => 'Ihr neues Girokonto ist bereit',
        'hero_copy' => 'Ihre Kontoinfrastruktur ist aktiv. Schliessen Sie die Verifizierung ab und nutzen Sie SEPA-Zahlungen.',
        'bill_title' => 'Geplante Dauerauftraege', 'bill_day' => 'Ausfuehrungstag', 'bill_active' => 'AKTIV', 'bill_manual' => 'MANUELL',
        'bill_empty' => 'Noch keine Dauerauftraege. Legen Sie einen SEPA-Empfaenger an, wenn Sie bereit sind.',
        'recipient_title' => 'SEPA-Empfaenger', 'recipient_action' => 'Senden', 'recipient_empty' => 'Noch keine Empfaenger. SEPA-Empfaenger erscheinen hier.',
    ];
} else {
    $ui = [
        'available' => 'Available balance', 'pending' => 'Pending', 'savings' => 'Savings', 'account' => 'Account',
        'transfer' => 'SEPA transfer', 'recent' => 'Recent transactions', 'view_all' => 'View all',
        'balance' => 'Balance trend', 'cash' => 'Cash flow', 'income' => 'Income', 'expenses' => 'Expenses',
        'verification' => 'Verification status', 'review' => 'Current account review', 'quick' => 'Quick access',
        'link_card' => 'Add Credit Card', 'statements' => 'Statements', 'security' => 'Security',
        'hero_eyebrow' => 'Welcome to Deutsche', 'hero_title' => 'Your European current account is ready',
        'hero_copy' => 'Your IBAN, BIC/SWIFT, SEPA Instant, and standing order tools are available after verification.',
        'bill_title' => 'Scheduled standing orders', 'bill_day' => 'Execution day', 'bill_active' => 'ACTIVE', 'bill_manual' => 'MANUAL',
        'bill_empty' => 'No standing orders yet. Add a SEPA recipient when you are ready.',
        'recipient_title' => 'SEPA recipients', 'recipient_action' => 'Send', 'recipient_empty' => 'No SEPA recipients yet.',
    ];
}

$statusMeta = match (true) {
    $accountStatus === 'frozen' => ['Frozen', 'status-warning', 'fa-snowflake'],
    $accountStatus === 'suspended' => ['Suspended', 'status-danger', 'fa-ban'],
    $verificationStatus !== 'approved' => ['Unverified', 'status-info', 'fa-id-card'],
    default => ['Active', 'status-success', 'fa-circle'],
};

$newAccountCards = $isUsAccount ? [
    ($user['verification_status'] ?? '') === 'approved'
        ? ['fa-circle-check', 'Identity verified', 'Your identity review is complete and online banking is active.']
        : ['fa-id-card', 'Identity review', 'Your submitted documents are being reviewed by operations.'],
    ['fa-building-columns', 'Routing ready', 'Your account and routing number are visible for ACH and wire transfers.'],
    ['fa-bolt', 'Instant Pay available', 'Add recipients by email or phone after account verification.'],
    ['fa-file-invoice-dollar', 'Bill Pay ready', 'Set up billers for utilities, rent, subscriptions, and insurance.'],
    ['fa-credit-card', 'Debit card prepared', 'Your digital card view is ready while the physical card is prepared.'],
    ['fa-receipt', 'No activity yet', 'New activity appears after your first deposit, bill pay, ACH, wire, or card transaction.'],
] : [
    ($user['verification_status'] ?? '') === 'approved'
        ? ['fa-circle-check', $useGermanLabels ? 'Identitaet verifiziert' : 'Identity verified', $useGermanLabels ? 'Ihre Identitaetspruefung ist abgeschlossen und das Online-Banking ist aktiv.' : 'Your identity review is complete and online banking is active.']
        : ['fa-id-card', $useGermanLabels ? 'Identitaet pruefen' : 'Identity review', $useGermanLabels ? 'Ihre eingereichten Dokumente werden durch unser Operations-Team geprueft.' : 'Your submitted documents are being reviewed by operations.'],
    ['fa-building-columns', $useGermanLabels ? 'SEPA-Daten bereit' : 'SEPA data ready', $useGermanLabels ? 'Ihre IBAN und BIC sind fuer SEPA-Ueberweisungen sichtbar.' : 'Your IBAN and BIC are visible for SEPA transfers.'],
    ['fa-credit-card', $useGermanLabels ? 'Kreditkarte hinzufuegen' : 'Add Credit Card', $useGermanLabels ? 'Erzeugen Sie einen sicheren Kartenlink und lassen Sie die Kreditkarte durch den Admin freigeben.' : 'Generate an Add Credit Card link and have the credit card approved by admin.'],
    ['fa-calendar-check', $useGermanLabels ? 'Dauerauftrag einrichten' : 'Set up standing order', $useGermanLabels ? 'Planen Sie wiederkehrende SEPA-Zahlungen nach der Verifizierung.' : 'Schedule recurring SEPA payments after verification.'],
    ['fa-credit-card', $useGermanLabels ? 'Debitkarte vorbereitet' : 'Debit card prepared', $useGermanLabels ? 'Ihre digitale Kartenansicht ist bereit, waehrend die physische Ausgabe vorbereitet wird.' : 'Your digital card view is ready while the physical edition is prepared.'],
    ['fa-receipt', $useGermanLabels ? 'Noch keine Umsaetze' : 'No activity yet', $useGermanLabels ? 'Neue Aktivitaeten erscheinen nach der ersten Einzahlung, Ueberweisung oder Kartenzahlung.' : 'New activity appears after your first deposit, transfer, or card payment.'],
];
$quickActions = [
    ['type' => 'button', 'target' => '#transferModal', 'icon' => 'fa-right-left', 'label' => $useGermanLabels ? 'Ueberweisen' : 'Transfer'],
    ['type' => 'link', 'href' => 'user/bill_pay.php', 'icon' => 'fa-file-invoice-dollar', 'label' => $isUsAccount ? 'Pay bills' : ($useGermanLabels ? 'Zahlungen' : 'Payments')],
    ['type' => 'link', 'href' => 'user/deposits.php', 'icon' => 'fa-camera', 'label' => $useGermanLabels ? 'Einzahlen' : 'Deposit'],
    ['type' => 'link', 'href' => 'user/send_money.php', 'icon' => 'fa-paper-plane', 'label' => $isUsAccount ? 'Instant Pay' : $regionConfig['rail_primary']],
];
$primaryAccountLabel = in_array($bankingRegion, ['us', 'ca', 'uk'], true)
    ? e($regionConfig['account_label']) . ' ' . e(mask_account((string) $account['account_number']))
    : 'IBAN ' . e($displayIban);
$routingLabel = in_array($bankingRegion, ['us', 'ca', 'uk'], true)
    ? e($regionConfig['routing_label']) . ' ' . e((string) ($account['routing_number'] ?: $regionConfig['routing']))
    : 'BIC/SWIFT ' . e($displayBic);
$maskedAccountNumber = in_array($bankingRegion, ['us', 'ca', 'uk'], true)
    ? mask_account((string) $account['account_number'])
    : $displayIban;
$routingNumberValue = in_array($bankingRegion, ['us', 'ca', 'uk'], true)
    ? (string) ($account['routing_number'] ?: $regionConfig['routing'])
    : $displayBic;
$cardType = (string) ($card['card_type'] ?? 'Debit');
$cardExpiry = !empty($card['created_at']) ? date('m/y', strtotime((string) $card['created_at'] . ' +4 years')) : date('m/y', strtotime('+4 years'));
?>
<div class="mobile-bank-app">
    <section class="mobile-welcome-card">
        <div>
            <h2><?= $useGermanLabels ? 'Guten Tag' : 'Good afternoon' ?>, <?= e($user['first_name']) ?></h2>
            <p><?= e($account['account_type']) ?> account overview</p>
        </div>
        <button class="welcome-card-action" type="button" data-bs-toggle="modal" data-bs-target="#cardApplicationModal" aria-label="Open card application"><i class="fa-solid fa-credit-card"></i></button>
    </section>

    <div class="bank-home-grid">
        <section class="bank-app-card total-balance-panel">
            <div class="panel-heading-row balance-hero-top">
                <span class="account-status-pill <?= e($statusMeta[1]) ?>"><i class="fa-solid <?= e($statusMeta[2]) ?>"></i><?= e($statusMeta[0]) ?></span>
                <span class="balance-account-select"><?= e($account['account_type']) ?> <i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <span class="balance-label"><?= e($ui['available']) ?></span>
            <div class="balance-value-row">
                <strong><?= money($account['available_balance'], $currency) ?></strong>
                <button type="button" aria-label="Show balance details"><i class="fa-solid fa-eye"></i></button>
            </div>
            <div class="balance-mask">&bull;&bull;&bull;&bull; <?= e(substr((string) $account['account_number'], -4)) ?></div>
            <div class="balance-account-meta">
                <div><span>Account type</span><b><?= e($account['account_type']) ?></b></div>
                <div><span>Account number</span><b><?= e($maskedAccountNumber) ?></b></div>
                <div>
                    <span><?= e($regionConfig['routing_label']) ?></span>
                    <b><?= e(mask_account($routingNumberValue)) ?><button type="button" data-copy-text="<?= e($routingNumberValue) ?>" aria-label="Copy routing number"><i class="fa-regular fa-copy"></i></button></b>
                </div>
            </div>
            <div class="balance-subgrid">
                <div><span><?= e($ui['pending']) ?></span><b><?= money($account['pending_balance'], $currency) ?></b></div>
                <div><span><?= e($ui['savings']) ?></span><b><?= money($account['savings_balance'], $currency) ?></b></div>
            </div>
        </section>

        <section class="bank-app-card quick-action-panel">
            <div class="section-title-row"><h3><?= e($ui['quick']) ?></h3></div>
            <div class="app-quick-grid">
                <?php foreach ($quickActions as $action): ?>
                    <?php if ($action['type'] === 'button'): ?>
                        <button type="button" data-bs-toggle="modal" data-bs-target="<?= e($action['target']) ?>" <?= account_is_restricted($user) ? 'disabled' : '' ?>><i class="fa-solid <?= e($action['icon']) ?>"></i><span><?= e($action['label']) ?></span></button>
                    <?php else: ?>
                        <a href="<?= e($action['href']) ?>" class="<?= account_is_restricted($user) && in_array($action['href'], ['user/send_money.php','user/bill_pay.php','user/deposits.php'], true) ? 'disabled' : '' ?>"><i class="fa-solid <?= e($action['icon']) ?>"></i><span><?= e($action['label']) ?></span></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bank-app-card account-stack-panel">
            <div class="section-title-row"><h3>My Accounts</h3><a href="user/accounts.php"><?= e($ui['view_all']) ?></a></div>
            <div class="account-card-list">
                <?php foreach (array_slice($allAccounts, 0, 3) as $index => $accountRow): ?>
                    <a class="mobile-account-card" href="user/accounts.php">
                        <span class="account-card-icon"><i class="fa-solid <?= $index === 0 ? 'fa-building-columns' : 'fa-piggy-bank' ?>"></i></span>
                        <span><strong><?= e($accountRow['account_type']) ?></strong><small><?= e(mask_account((string) $accountRow['account_number'])) ?></small></span>
                        <b><?= money($accountRow['available_balance'], $currency) ?></b>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bank-app-card instant-pay-panel">
            <div class="section-title-row"><h3><?= $isUsAccount ? 'Instant Pay' : e($regionConfig['rail_primary']) ?></h3><a href="user/send_money.php"><?= e($ui['recipient_action']) ?></a></div>
            <?php foreach ($recipientRows as $r): ?>
                <div class="recipient-row app-recipient-row"><span class="tx-icon"><i class="fa-solid fa-user"></i></span><div><strong><?= e($r['name']) ?></strong><div class="small muted"><?= e($r['iban'] ? format_iban_display($r['iban']) : ($r['email'] ?: $r['phone'])) ?></div></div><a class="btn btn-sm btn-light border ms-auto" href="user/send_money.php"><?= e($ui['recipient_action']) ?></a></div>
            <?php endforeach; ?>
            <?php if (!$recipientRows): ?><div class="empty-mini"><?= e($ui['recipient_empty']) ?></div><?php endif; ?>
        </section>

        <section class="bank-app-card virtual-card-panel">
            <div class="section-title-row"><h3>Virtual Card</h3><a href="user/cards.php">View</a></div>
            <div class="virtual-card premium-mobile-card">
                <div class="d-flex justify-content-between"><strong><?= e($cardType) ?></strong><span>&bull;&bull;&bull;&bull; <?= e($card['card_last4']) ?></span></div>
                <div class="virtual-card-spacer"></div>
                <div class="d-flex justify-content-between align-items-end"><span>Expires <?= e($cardExpiry) ?></span><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong></div>
            </div>
            <button class="btn btn-navy w-100 mt-3 virtual-card-action" type="button" data-bs-toggle="modal" data-bs-target="#cardApplicationModal"><?= e($ui['link_card']) ?></button>
        </section>

        <section class="bank-app-card transactions-panel">
            <div class="section-title-row"><h3><?= e($ui['recent']) ?></h3><a href="user/transactions.php"><?= e($ui['view_all']) ?></a></div>
            <div class="app-transaction-list">
                <?php foreach ($txRows as $row): ?>
                    <?php $isCredit = (float) $row['amount'] > 0; ?>
                    <button type="button" class="app-transaction-row" data-transaction-detail data-bs-toggle="modal" data-bs-target="#transactionDetailsModal" data-title="<?= e($row['description']) ?>" data-type="<?= e(strtoupper(str_replace('_', ' ', $row['transaction_type']))) ?>" data-category="<?= e(transaction_category($row)) ?>" data-status="<?= e(strtoupper($row['status'])) ?>" data-date="<?= e(transaction_display_date($row['created_at'])) ?>" data-amount="<?= e(($isCredit ? '+' : '-') . money(abs((float) $row['amount']), $currency)) ?>" data-direction="<?= $isCredit ? 'credit' : 'debit' ?>">
                        <span class="tx-icon <?= $isCredit ? 'tx-icon-credit' : '' ?>"><i class="fa-solid <?= e(transaction_icon($row)) ?>"></i></span>
                        <span><strong><?= e($row['description']) ?></strong><small><?= e(transaction_display_date($row['created_at'])) ?> &middot; <?= e(transaction_category($row)) ?></small></span>
                        <b class="<?= $isCredit ? 'tx-credit' : 'tx-debit' ?>"><?= $isCredit ? '+' : '-' ?><?= money(abs((float) $row['amount']), $currency) ?></b>
                    </button>
                <?php endforeach; ?>
                <?php if (!$txRows): ?><div class="empty-mini"><i class="fa-solid fa-receipt d-block fs-3 mb-2"></i>No transactions yet.</div><?php endif; ?>
            </div>
        </section>

        <section class="bank-app-card desktop-insights-panel">
            <div class="section-title-row"><h3><?= e($ui['cash']) ?></h3></div>
            <div class="cash-flow"><div><span><?= e($ui['income']) ?></span><strong class="tx-credit"><?= money($monthlyIncome->fetch()['total'], $currency) ?></strong></div><div><span><?= e($ui['expenses']) ?></span><strong class="tx-debit"><?= money($monthlyExpense->fetch()['total'], $currency) ?></strong></div></div>
            <?php if ($isNewAccount): ?><div class="empty-mini mt-3">Income and expenses will appear after your first posted transaction.</div><?php else: ?><canvas data-chart="doughnut" height="90" data-chart-region="<?= $isUsAccount ? 'us' : 'eu' ?>"></canvas><?php endif; ?>
        </section>
    </div>

    <?php if ($isNewAccount): ?>
        <section class="new-account-grid">
            <?php foreach ($newAccountCards as $item): ?>
                <div class="empty-state-card"><span class="tx-icon"><i class="fa-solid <?= e($item[0]) ?>"></i></span><strong><?= e($item[1]) ?></strong><p><?= e($item[2]) ?></p></div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

<div class="modal fade app-flow-modal" id="transferModal" tabindex="-1" aria-labelledby="transferModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="post" action="user/transfers.php">
            <?= csrf_field() ?>
            <div class="modal-header"><h2 class="modal-title fs-5" id="transferModalTitle"><?= e($ui['transfer']) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="transfer-type-tabs"><span>Internal Transfer</span><span>External Transfer</span></div>
                <label class="form-label">From account</label>
                <select class="form-select mb-3" disabled><option><?= e($account['account_type']) ?> (<?= money($account['available_balance'], $currency) ?>)</option></select>
                <label class="form-label">Recipient name</label>
                <input name="recipient_name" class="form-control mb-3" required>
                <?php if ($isUsAccount): ?>
                    <label class="form-label">Routing number</label>
                    <input name="routing_number" class="form-control mb-3" inputmode="numeric" maxlength="9" required>
                    <label class="form-label">Account number</label>
                    <input name="account_number" class="form-control mb-3" inputmode="numeric" maxlength="17" required>
                <?php else: ?>
                    <label class="form-label">IBAN</label>
                    <input name="iban" class="form-control mb-3 text-uppercase" data-format-iban required>
                    <label class="form-label">BIC/SWIFT</label>
                    <input name="bic" class="form-control mb-3 text-uppercase" value="<?= e(DEFAULT_BIC) ?>" required>
                <?php endif; ?>
                <label class="form-label">Amount in <?= e($currency) ?></label>
                <input name="amount" type="number" step="0.01" min="1" class="form-control mb-3" required>
                <input name="memo" class="form-control" placeholder="Memo optional">
                <p class="transfer-note mt-3"><i class="fa-solid fa-triangle-exclamation"></i> Transfers continue through the existing secure review and approval flow.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button name="review" value="1" class="btn btn-navy">Continue</button></div>
        </form>
    </div>
</div>

<div class="modal fade app-flow-modal" id="transactionDetailsModal" tabindex="-1" aria-labelledby="transactionDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="transactionDetailsTitle">Transaction Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="transaction-detail-hero"><span data-tx-detail-icon><i class="fa-solid fa-receipt"></i></span><strong data-tx-detail-amount></strong><small data-tx-detail-title></small></div>
                <div class="review-panel mb-0">
                    <span>Type</span><strong data-tx-detail-type></strong>
                    <span>Category</span><strong data-tx-detail-category></strong>
                    <span>Date</span><strong data-tx-detail-date></strong>
                    <span>Status</span><strong data-tx-detail-status></strong>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-navy w-100" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<div class="modal fade app-flow-modal" id="cardApplicationModal" tabindex="-1" aria-labelledby="cardApplicationTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="user/linked_accounts.php">
            <?= csrf_field() ?>
            <div class="modal-header"><h2 class="modal-title fs-5" id="cardApplicationTitle"><?= e($ui['link_card']) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="muted">Generate a secure one-time page tied to your profile. Approval and funding stay in the existing admin review workflow.</p>
                <div class="review-panel mb-0">
                    <span>Customer</span><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    <span>Primary account</span><strong><?= $primaryAccountLabel ?></strong>
                    <span>Transfer details</span><strong><?= $routingLabel ?></strong>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button name="create_card_link" value="1" class="btn btn-navy">Generate secure link</button></div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/user_footer.php'; ?>
