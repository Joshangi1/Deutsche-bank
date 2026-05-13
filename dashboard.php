<?php
$pageTitle = 'Account Overview';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/frontend_components.php';

if (!empty($_SESSION['offline_demo_user'])) {
    $demo = $_SESSION['offline_demo_user'];
    $isUsAccount = ($demo['region'] ?? 'us') === 'us';
    $currency = $isUsAccount ? 'USD' : 'EUR';
    $balance = $isUsAccount ? 89772.00 : 82640.00;
    $pending = $isUsAccount ? 250.00 : 250.00;
    $accountLabel = $isUsAccount ? 'Premium Checking' : 'Premium-Girokonto';
    $accountNumber = $isUsAccount ? '4078' : '0705';
    $sidebar = $isUsAccount
        ? [['fa-gauge-high','Overview'],['fa-bolt','Zelle'],['fa-calendar-check','Bill Pay'],['fa-building-columns','ACH Transfers'],['fa-credit-card','Manage Credit Cards'],['fa-right-left','Wire Transfers'],['fa-file-lines','Statements'],['fa-user-shield','Profile']]
        : [['fa-gauge-high','Uebersicht'],['fa-bolt','SEPA Instant'],['fa-calendar-check','Dauerauftraege'],['fa-building-columns','SEPA-Ueberweisungen'],['fa-credit-card','Kreditkarten'],['fa-right-left','Transfers'],['fa-file-lines','Kontoauszuege'],['fa-user-shield','Profil']];
    ?>
    <!doctype html>
    <html lang="<?= $isUsAccount ? 'en' : 'de' ?>" class="notranslate" translate="no">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="google" content="notranslate">
        <title>Demo Dashboard | Deutsche</title>
        <link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
        <link href="<?= url('assets/css/styles.css') ?>?v=<?= filemtime(__DIR__ . '/assets/css/styles.css') ?>" rel="stylesheet">
    </head>
    <body class="notranslate" translate="no">
    <div class="app-shell">
        <aside class="sidebar">
            <a class="sidebar-brand" href="<?= url('dashboard.php') ?>"><?= lead_logo('light') ?></a>
            <nav class="nav flex-column">
                <?php foreach ($sidebar as $index => $item): ?>
                    <span class="nav-link <?= $index === 0 ? 'active' : '' ?>"><i class="fa-solid <?= e($item[0]) ?>"></i><span><?= e($item[1]) ?></span></span>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-session mt-auto"><i class="fa-solid fa-shield-halved"></i><div><strong>Demo Session</strong><span>No database required</span></div></div>
        </aside>
        <main class="app-main">
            <div class="topbar">
                <div><div class="topbar-kicker"><?= $isUsAccount ? 'Deutsche US banking' : 'Deutsche European banking' ?></div><h1 class="h3 mb-0 fw-bold"><?= $isUsAccount ? 'Account Overview' : 'Kontouebersicht' ?></h1><div class="muted">Welcome back, <?= e($demo['first_name']) ?></div></div>
                <div class="d-flex align-items-center gap-2"><span class="language-static-pill"><i class="fa-solid fa-language"></i><?= $isUsAccount ? 'English' : 'Deutsch' ?></span><a class="btn btn-outline-danger" href="<?= url('logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Sign out</a></div>
            </div>
            <div class="banking-hero mb-4"><div><div class="eyebrow">Demo mode</div><h2><?= $isUsAccount ? 'Your U.S. banking workspace is ready' : 'Ihr europaeischer Banking-Arbeitsbereich ist bereit' ?></h2><p><?= $isUsAccount ? 'ACH, Zelle, Bill Pay, wire transfers, and credit-card tools are visible in this demo.' : 'SEPA, IBAN, BIC/SWIFT und Kreditkartenfunktionen sind in dieser Demo sichtbar.' ?></p></div><i class="fa-solid fa-circle-check"></i></div>
            <div class="row g-4">
                <div class="col-xl-8"><div class="balance-card"><div class="row g-4 position-relative"><div class="col-md-7"><div class="text-white-50 mb-2"><?= $isUsAccount ? 'Available balance' : 'Verfuegbarer Saldo' ?> <span class="account-status-pill status-success ms-2"><i class="fa-solid fa-circle"></i>Demo</span></div><div class="display-5 fw-bold"><?= money($balance, $currency) ?></div><div class="mt-4 d-flex gap-4"><div><div class="text-white-50 small">Pending</div><strong><?= money($pending, $currency) ?></strong></div><div><div class="text-white-50 small"><?= $isUsAccount ? 'Savings' : 'Tagesgeld' ?></div><strong><?= money(4200, $currency) ?></strong></div></div></div><div class="col-md-5"><div class="text-white-50 small"><?= $isUsAccount ? 'Account' : 'Konto' ?></div><h5><?= e($accountLabel) ?></h5><?php if ($isUsAccount): ?><p class="mb-1">Account &bull;&bull;&bull;&bull; <?= e($accountNumber) ?></p><p>Routing 071923846</p><?php else: ?><p class="mb-1">IBAN DE89 3704 0044 0532 0130 00</p><p>BIC/SWIFT DEUTDEFFXXX</p><?php endif; ?><span class="btn btn-gold"><?= $isUsAccount ? 'Wire transfer' : 'SEPA-Ueberweisung' ?></span></div></div></div></div>
                <div class="col-xl-4"><div class="virtual-card"><div class="d-flex justify-content-between"><strong>Deutsche</strong><i class="fa-brands fa-cc-visa fa-2x"></i></div><div class="fs-4 fw-bold">4582 **** **** <?= $isUsAccount ? '7771' : '4144' ?></div><div class="d-flex justify-content-between"><span><?= e(strtoupper($demo['first_name'] . ' ' . $demo['last_name'])) ?></span><span>ACTIVE</span></div></div></div>
                <div class="col-xl-8"><div class="table-card"><div class="p-4 d-flex justify-content-between"><h5 class="fw-bold mb-0">Recent transactions</h5><span>Demo</span></div><table class="table transaction-table align-middle mb-0"><tbody><tr><td><div class="tx-merchant"><span class="tx-icon tx-icon-credit"><i class="fa-solid fa-arrow-down"></i></span><div><strong>Referral Signup Bonus</strong><div class="small muted">Pending admin approval</div></div></div></td><td><span class="status-pill status-warning">PENDING</span></td><td class="text-end fw-bold tx-credit">+<?= money(250, $currency) ?></td></tr><tr><td><div class="tx-merchant"><span class="tx-icon tx-icon-credit"><i class="fa-solid fa-credit-card"></i></span><div><strong>Funds added through approved credit card</strong><div class="small muted">Completed demo transaction</div></div></div></td><td><span class="status-pill status-success">COMPLETED</span></td><td class="text-end fw-bold tx-credit">+<?= money(1250, $currency) ?></td></tr></tbody></table></div></div>
                <div class="col-xl-4"><div class="table-card p-4 h-100"><h5 class="fw-bold">Balance trend</h5><canvas data-chart="line" height="220"></canvas></div></div>
                <div class="col-12"><div class="premium-card p-4"><h5 class="fw-bold">Demo credentials</h5><p class="muted mb-0"><?= e($demo['email']) ?> / Deutsche123!. MySQL is offline, so this demo dashboard is running without database reads.</p></div></div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
    </body>
    </html>
    <?php
    exit;
}
include __DIR__ . '/includes/user_header.php';

$account = user_account((int) $user['id']);
if (!$account) {
    flash('danger', 'Account record not found. Please contact support.');
    include __DIR__ . '/includes/user_footer.php';
    exit;
}

$isUsAccount = user_is_us_account($user, $account);
$currency = user_account_currency($user, $account);
$useGermanLabels = !$isUsAccount && current_language() !== 'en';
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

if ($isUsAccount) {
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
    ['fa-bolt', 'Zelle available', 'Add recipients by email or phone after account verification.'],
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
                    <?php if ($isUsAccount): ?>
                        <p class="mb-1">Account <?= e(mask_account($account['account_number'])) ?></p>
                        <p>Routing <?= e($account['routing_number'] ?: US_ROUTING_NUMBER) ?></p>
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
        <a href="user/linked_accounts.php"><i class="fa-solid fa-credit-card"></i><span><?= e($ui['link_card']) ?></span></a>
        <a href="user/send_money.php"><i class="fa-solid fa-bolt"></i><span><?= $isUsAccount ? 'Zelle' : 'SEPA Instant' ?></span></a>
        <a href="user/bill_pay.php"><i class="fa-solid fa-calendar-check"></i><span><?= $isUsAccount ? 'Bill Pay' : ($useGermanLabels ? 'Dauerauftraege' : 'Standing Orders') ?></span></a>
        <a href="user/ach_transfers.php"><i class="fa-solid fa-building-columns"></i><span><?= $isUsAccount ? 'ACH' : 'SEPA' ?></span></a>
        <a href="user/transfers.php"><i class="fa-solid fa-right-left"></i><span><?= $isUsAccount ? 'Wire' : 'Transfer' ?></span></a>
        <a href="user/statements.php"><i class="fa-solid fa-file-lines"></i><span><?= e($ui['statements']) ?></span></a>
    </div></div></div>
    <div class="col-xl-4"><div class="table-card p-4 h-100"><h5 class="fw-bold"><?= e($ui['cash']) ?></h5><div class="cash-flow"><div><span><?= e($ui['income']) ?></span><strong class="tx-credit"><?= money($monthlyIncome->fetch()['total'], $currency) ?></strong></div><div><span><?= e($ui['expenses']) ?></span><strong class="tx-debit"><?= money($monthlyExpense->fetch()['total'], $currency) ?></strong></div></div><?php if ($isNewAccount): ?><div class="empty-mini">Income and expenses will appear after your first posted transaction.</div><?php else: ?><canvas data-chart="doughnut" height="160" data-chart-region="<?= $isUsAccount ? 'us' : 'eu' ?>"></canvas><?php endif; ?></div></div>
    <div class="col-xl-4"><div class="premium-card p-4 h-100"><h5 class="fw-bold"><?= e($ui['verification']) ?></h5><p class="muted"><?= e($ui['review']) ?></p><div class="goal-ring"><strong><?= e(strtoupper(str_replace('_',' ', $user['verification_status'] ?? 'NOT STARTED'))) ?></strong><span><?= e(strtoupper(str_replace('_',' ', $user['risk_status'] ?? 'CLEAR'))) ?></span></div></div></div>
    <div class="col-xl-6"><div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0"><?= e($ui['bill_title']) ?></h5></div><table class="table align-middle mb-0"><tbody><?php foreach ($billRows as $bill): ?><tr><td><i class="fa-solid fa-calendar-day text-warning me-2"></i><?= e($bill['name']) ?><div class="small muted"><?= e($bill['category']) ?> &middot; <?= e($ui['bill_day']) ?> <?= e((string)$bill['due_day']) ?></div></td><td><span class="status-pill status-<?= $bill['autopay']?'success':'warning' ?>"><?= $bill['autopay'] ? e($ui['bill_active']) : e($ui['bill_manual']) ?></span></td></tr><?php endforeach; ?><?php if (!$billRows): ?><tr><td class="text-center muted py-5"><?= e($ui['bill_empty']) ?></td></tr><?php endif; ?></tbody></table></div></div>
    <div class="col-xl-6"><div class="table-card"><div class="p-4"><h5 class="fw-bold mb-0"><?= e($ui['recipient_title']) ?></h5></div><div class="p-3"><?php foreach ($recipientRows as $r): ?><div class="recipient-row"><span class="tx-icon"><i class="fa-solid fa-user"></i></span><div><strong><?= e($r['name']) ?></strong><div class="small muted"><?= e($r['iban'] ? format_iban_display($r['iban']) : ($r['email'] ?: $r['phone'])) ?></div></div><a class="btn btn-sm btn-light border ms-auto" href="user/send_money.php"><?= e($ui['recipient_action']) ?></a></div><?php endforeach; ?><?php if (!$recipientRows): ?><div class="text-center muted py-4"><?= e($ui['recipient_empty']) ?></div><?php endif; ?></div></div></div>
</div>
<?php include __DIR__ . '/includes/user_footer.php'; ?>
