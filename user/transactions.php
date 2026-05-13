<?php
$pageTitle = 'Transactions';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_user();
$account = user_account((int) $user['id']);
$isUsAccount = user_is_us_account($user, $account);
$currency = user_account_currency($user, $account);

$status = $_GET['status'] ?? '';
$query = trim((string) ($_GET['q'] ?? ''));
$downloadReady = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['download_statement'])) {
        if (verify_transaction_pin($user, (string) ($_POST['statement_pin'] ?? ''))) {
            $downloadReady = true;
        } else {
            flash('danger', 'Invalid PIN. Statement download was not authorized.');
            header('Location: transactions.php');
            exit;
        }
    }
}

$sql = 'SELECT * FROM transactions WHERE user_id=?';
$params = [$user['id']];
if ($status) {
    $sql .= ' AND status=?';
    $params[] = $status;
}
if ($query !== '') {
    $sql .= ' AND (description LIKE ? OR transaction_type LIKE ?)';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}
$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
$postedTotal = 0.0;
$pendingCount = 0;
foreach ($transactions as $summaryRow) {
    if ($summaryRow['status'] === 'pending') {
        $pendingCount++;
    }
    if ($summaryRow['status'] === 'completed') {
        $postedTotal += (float) $summaryRow['amount'];
    }
}

if ($downloadReady) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sterling-harbor-statement.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Type', 'Description', 'Status', 'Amount']);
    foreach ($transactions as $r) {
        fputcsv($out, [$r['created_at'], $r['transaction_type'], $r['description'], $r['status'], $r['amount']]);
    }
    fclose($out);
    exit;
}
?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<div class="table-card">
    <div class="transaction-toolbar p-4">
        <div>
            <h5 class="fw-bold mb-1">Transaction history</h5>
            <div class="muted small"><?= count($transactions) ?> records shown · <?= $pendingCount ?> pending · <?= money($postedTotal) ?> posted net</div>
        </div>
        <div class="transaction-actions">
            <form class="transaction-filter">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input name="q" value="<?= e($query) ?>" class="form-control" placeholder="<?= $isUsAccount ? 'Search merchants, ACH, Zelle, wires' : 'Search merchants, SEPA, transfers' ?>" data-tx-search>
                </div>
                <select name="status" class="form-select"><option value="">All statuses</option><?php foreach(['pending','completed','failed','rejected'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
                <button class="btn btn-navy"><i class="fa-solid fa-filter me-1"></i>Apply</button>
            </form>
            <form method="post" class="statement-form">
                <?= csrf_field() ?>
                <input name="statement_pin" type="password" inputmode="numeric" maxlength="6" class="form-control" placeholder="PIN required">
                <button name="download_statement" value="1" class="btn btn-light border"><i class="fa-solid fa-file-arrow-down me-1"></i>Export CSV</button>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table transaction-table align-middle mb-0">
            <thead><tr><th>Transaction</th><th>Category</th><th>Status</th><th>Posted</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach($transactions as $r): ?>
                <?php $isCredit = (float) $r['amount'] > 0; ?>
                <tr data-tx-row data-search="<?= e(strtolower($r['description'] . ' ' . $r['transaction_type'] . ' ' . transaction_category($r))) ?>">
                    <td>
                        <div class="tx-merchant">
                            <span class="tx-icon <?= $isCredit ? 'tx-icon-credit' : '' ?>"><i class="fa-solid <?= e(transaction_icon($r)) ?>"></i></span>
                            <div>
                                <strong><?= e($r['description']) ?></strong>
                                <div class="small muted"><?= e(strtoupper(str_replace('_', ' ', $r['transaction_type']))) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="category-badge"><?= e(transaction_category($r)) ?></span></td>
                    <td><span class="status-pill status-<?= $r['status']==='completed'?'success':(in_array($r['status'], ['failed','rejected'], true)?'danger':'warning') ?>"><?= e(strtoupper($r['status'])) ?></span></td>
                    <td><?= e(transaction_display_date($r['created_at'])) ?></td>
                    <td class="text-end fw-bold tx-amount <?= $isCredit ? 'tx-credit' : 'tx-debit' ?>"><?= $isCredit ? '+' : '-' ?><?= money(abs((float) $r['amount']), $currency) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr><td colspan="5" class="text-center muted py-5">No transactions match this view.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/user_footer.php'; ?>
