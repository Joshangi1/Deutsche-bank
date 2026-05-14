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
        'hero_copy' => 'Routing, bill pay, Zelle, ACH, and wire tools are available after verification.',
        'bill_title' => 'Upcoming bill payments', 'bill_day' => 'Due day', 'bill_active' => 'AUTOPAY', 'bill_manual' => 'MANUAL',
        'bill_empty' => 'No bill payments yet. Add a biller when you are ready.',
        'recipient_title' => 'Zelle recipients', 'recipient_action' => 'Send', 'recipient_empty' => 'No Zelle recipients yet.',
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

$newAccountCards = in_array($bankingRegion, ['us', 'ca', 'uk'], true) ? [
    ($user['verification_status'] ?? '') === 'approved'
        ? ['fa-circle-check', 'Identity verified', 'Your identity review is complete and online banking is active.']
        : ['fa-id-card', 'Identity review', 'Your submitted documents are being reviewed by operations.'],
    ['fa-building-columns', $regionConfig['routing_label'] . ' ready', 'Your local account details are visible for transfers and account review.'],
    ['fa-bolt', $regionConfig['rail_primary'] . ' available', 'Add recipients after account verification.'],
    ['fa-file-invoice-dollar', $regionConfig['rail_scheduled'] . ' ready', 'Set up scheduled payments after verification.'],
    ['fa-credit-card', 'Debit card prepared', 'Your digital card view is ready while the physical card is prepared.'],
    ['fa-receipt', 'No activity yet', 'New activity appears after your first deposit, transfer, scheduled payment, or card transaction.'],
] : [
    ($user['verification_status'] ?? '') === 'approved'
        ? ['fa-circle-check', $useGermanLabels ? 'Identitaet verifiziert' : 'Identity verified', $useGermanLabels ? 'Ihre Identitaetspruefung ist abgeschlossen und das Online-Banking ist aktiv.' : 'Your identity review is complete and online banking is active.']
        : ['fa-id-card', $useGermanLabels ? 'Identitaet pruefen' : 'Identity review', $useGermanLabels ? 'Ihre eingereichten Dokumente werden durch unser Operations-Team geprueft.' : 'Your submitted documents are being reviewed by operations.'],
    ['fa-building-columns', $useGermanLabels ? 'SEPA-Daten bereit' : 'IBAN data ready', $useGermanLabels ? 'Ihre IBAN und BIC sind fuer SEPA-Ueberweisungen sichtbar.' : 'Your IBAN and BIC/SWIFT are visible for transfers.'],
    ['fa-credit-card', $useGermanLabels ? 'Kreditkarte hinzufuegen' : 'Add Credit Card', $useGermanLabels ? 'Erzeugen Sie einen sicheren Kartenlink und lassen Sie die Kreditkarte durch den Admin freigeben.' : 'Generate an Add Credit Card link and have the credit card approved by admin.'],
    ['fa-calendar-check', $useGermanLabels ? 'Dauerauftrag einrichten' : 'Set up standing order', $useGermanLabels ? 'Planen Sie wiederkehrende SEPA-Zahlungen nach der Verifizierung.' : 'Schedule recurring SEPA payments after verification.'],
    ['fa-credit-card', $useGermanLabels ? 'Debitkarte vorbereitet' : 'Debit card prepared', $useGermanLabels ? 'Ihre digitale Kartenansicht ist bereit, waehrend die physische Ausgabe vorbereitet wird.' : 'Your digital card view is ready while the physical edition is prepared.'],
    ['fa-receipt', $useGermanLabels ? 'Noch keine Umsaetze' : 'No activity yet', $useGermanLabels ? 'Neue Aktivitaeten erscheinen nach der ersten Einzahlung, Ueberweisung oder Kartenzahlung.' : 'New activity appears after your first deposit, transfer, or card payment.'],
];
?>
<?php if ($isNewAccount): ?>
<div class="banking-hero mb-4"><div><div class="eyebrow"><?= e($ui['hero_eyebrow']) ?></div><h2><?= e($ui['hero_title']) ?></h2><p><?= e($ui['hero_copy']) ?></p></div><i class="fa-solid fa-circle-check"></i></div>
<?php endif; ?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="balance-card">
            <div class="row g-4 position-relative">
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <div class="text-white-50"><?= e($ui['available']) ?></div>
                        <span class="account-status-pill <?= e($statusMeta[1]) ?>"><i class="fa-solid <?= e($statusMeta[2]) ?>"></i><?= e($statusMeta[0]) ?></span>
                    </div>
                    <div class="display-5 fw-bold"><?= money($account['available_balance'], $currency) ?></div>
                    <div class="mt-4 d-flex gap-4 flex-wrap">
                        <div><div class="text-white-50 small"><?= e($ui['pending']) ?></div><strong><?= money($account['pending_balance'], $currency) ?></strong></div>
                        <div><div class="text-white-50 small"><?= e($ui['savings']) ?></div><strong><?= money($account['savings_balance'], $currency) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="text-white-50 small"><?= e($ui['account']) ?></div>
                    <h5><?= e($account['account_type']) ?></h5>
                    <?php if (in_array($bankingRegion, ['us', 'ca', 'uk'], true)): ?>
                        <p class="mb-1"><?= e($regionConfig['account_label']) ?> <?= e(mask_account($account['account_number'])) ?></p>
                        <p><?= e($regionConfig['routing_label']) ?> <?= e($account['routing_number'] ?: $regionConfig['routing']) ?></p>
                    <?php else: ?>
                        <p class="mb-1">IBAN <?= e($displayIban) ?></p>
                        <p>BIC/SWIFT <?= e($displayBic) ?></p>
                    <?php endif; ?>
                    <a class="btn btn-gold <?= account_is_restricted($user) ? 'disabled' : '' ?>" href="user/transfers.php"><?= e($ui['transfer']) ?></a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4"><div class="virtual-card"><div class="d-flex justify-content-between"><strong>Deutsche</strong><i class="fa-brands fa-cc-visa fa-2x"></i></div><div class="fs-4 fw-bold">4582 **** **** <?= e($card['card_last4']) ?></div><div class="d-flex justify-content-between"><span><?= e(strtoupper($user['first_name'] . ' ' . $user['last_name'])) ?></span><span><?= $isNewAccount ? 'PREPARING' : e(strtoupper($card['status'])) ?></span></div></div></div>
    <?php if ($isNewAccount): ?>
        <div class="col-12"><div class="row g-3">
            <?php foreach ($newAccountCards as $item): ?>
                <div class="col-md-4"><div class="empty-state-card"><span class="tx-icon"><i class="fa-solid <?= e($item[0]) ?>"></i></span><strong><?= e($item[1]) ?></strong><p><?= e($item[2]) ?></p></div></div>
            <?php endforeach; ?>
        </div></div>
    <?php endif; ?>
    <div class="col-xl-8"><div class="table-card"><div class="p-4 d-flex justify-content-between"><h5 class="fw-bold mb-0"><?= e($ui['recent']) ?></h5><a href="user/transactions.php"><?= e($ui['view_all']) ?></a></div><div class="table-responsive"><table class="table transaction-table align-middle mb-0"><tbody><?php foreach ($txRows as $row): ?><?php $isCredit = (float) $row['amount'] > 0; ?><tr><td><div class="tx-merchant"><span class="tx-icon <?= $isCredit ? 'tx-icon-credit' : '' ?>"><i class="fa-solid <?= e(transaction_icon($row)) ?>"></i></span><div><strong><?= e($row['description']) ?></strong><div class="small muted"><?= e(transaction_display_date($row['created_at'])) ?> &middot; <?= e(transaction_category($row)) ?></div></div></div></td><td><span class="status-pill status-<?= $row['status']==='completed'?'success':(in_array($row['status'], ['failed','rejected'], true)?'danger':'warning') ?>"><?= e(strtoupper($row['status'])) ?></span></td><td class="text-end fw-bold tx-amount <?= $isCredit ? 'tx-credit' : 'tx-debit' ?>"><?= $isCredit ? '+' : '-' ?><?= money(abs((float) $row['amount']), $currency) ?></td></tr><?php endforeach; ?><?php if (!$txRows): ?><tr><td colspan="3" class="text-center muted py-5"><i class="fa-solid fa-receipt d-block fs-3 mb-2"></i>No transactions yet.</td></tr><?php endif; ?></tbody></table></div></div></div>
    <div class="col-xl-4"><div class="table-card p-4 h-100"><h5 class="fw-bold"><?= e($ui['balance']) ?></h5><?php if ($isNewAccount): ?><div class="empty-mini">Charts will populate after account activity begins.</div><?php else: ?><canvas data-chart="line" height="220"></canvas><?php endif; ?></div></div>
    <div class="col-xl-4"><div class="premium-card p-4 h-100"><h5 class="fw-bold"><?= e($ui['quick']) ?></h5><div class="quick-grid">
        <a href="user/accounts.php"><i class="fa-solid fa-layer-group"></i><span><?= $isUsAccount ? 'Accounts' : ($useGermanLabels ? 'Konten' : 'Accounts') ?></span></a>
        <a href="user/linked_accounts.php"><i class="fa-solid fa-credit-card"></i><span><?= e($ui['link_card']) ?></span></a>
        <a href="user/send_money.php"><i class="fa-solid fa-bolt"></i><span><?= e($regionConfig['rail_primary']) ?></span></a>
        <a href="user/bill_pay.php"><i class="fa-solid fa-calendar-check"></i><span><?= e($regionConfig['rail_scheduled']) ?></span></a>
        <a href="user/ach_transfers.php"><i class="fa-solid fa-building-columns"></i><span><?= e($regionConfig['rail_bank']) ?></span></a>
        <a href="user/loans.php"><i class="fa-solid fa-hand-holding-dollar"></i><span><?= $useGermanLabels ? 'Kredite' : 'Loans' ?></span></a>
    </div></div></div>
    <div class="col-xl-4"><div class="table-card p-4 h-100"><h5 class="fw-bold"><?= e($ui['cash']) ?></h5><div class="cash-flow"><div><span><?= e($ui['income']) ?></span><strong class="tx-credit"><?= money($monthlyIncome->fetch()['total'], $currency) ?></strong></div><div><span><?= e($ui['expenses']) ?></span><strong class="tx-debit"><?= money($monthlyExpense->fetch()['total'], $currency) ?></strong></div></div><?php if ($isNewAccount): ?><div class="empty-mini">Income and expenses will appear after your first posted transaction.</div><?php else: ?><canvas data-chart="doughnut" height="160" data-chart-region="<?= $isUsAccount ? 'us' : 'eu' ?>"></canvas><?php endif; ?></div></div>
    <div class="col-xl-4"><div class="premium-card p-4 h-100"><h5 class="fw-bold"><?= e($ui['verification']) ?></h5><p class="muted"><?= e($ui['review']) ?></p><div class="goal-ring"><strong><?= e(strtoupper(str_replace('_',' ', $user['verification_status'] ?? 'NOT STARTED'))) ?></strong><span><?= e(strtoupper(str_replace('_',' ', $user['risk_status'] ?? 'CLEAR'))) ?></span></div></div></div>
    <div class="col-xl-6"><div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0"><?= e($ui['bill_title']) ?></h5></div><table class="table align-middle mb-0"><tbody><?php foreach ($billRows as $bill): ?><tr><td><i class="fa-solid fa-calendar-day text-warning me-2"></i><?= e($bill['name']) ?><div class="small muted"><?= e($bill['category']) ?> &middot; <?= e($ui['bill_day']) ?> <?= e((string)$bill['due_day']) ?></div></td><td><span class="status-pill status-<?= $bill['autopay']?'success':'warning' ?>"><?= $bill['autopay'] ? e($ui['bill_active']) : e($ui['bill_manual']) ?></span></td></tr><?php endforeach; ?><?php if (!$billRows): ?><tr><td class="text-center muted py-5"><?= e($ui['bill_empty']) ?></td></tr><?php endif; ?></tbody></table></div></div>
    <div class="col-xl-6"><div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0"><?= e($ui['recipient_title']) ?></h5></div><div class="p-3"><?php foreach ($recipientRows as $r): ?><div class="recipient-row"><span class="tx-icon"><i class="fa-solid fa-user"></i></span><div><strong><?= e($r['name']) ?></strong><div class="small muted"><?= e($r['iban'] ? format_iban_display($r['iban']) : ($r['email'] ?: $r['phone'])) ?></div></div><a class="btn btn-sm btn-light border ms-auto" href="user/send_money.php"><?= e($ui['recipient_action']) ?></a></div><?php endforeach; ?><?php if (!$recipientRows): ?><div class="text-center muted py-4"><?= e($ui['recipient_empty']) ?></div><?php endif; ?></div></div></div>
</div>
<?php include __DIR__ . '/includes/user_footer.php'; ?>
