<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/frontend_components.php';
$user = require_user();
$isOfflineDemo = !empty($_SESSION['offline_demo_user']);
if (!$isOfflineDemo) {
    ensure_banking_schema();
    cleanup_customer_notifications((int) $user['id']);
}
$accountForRegion = $isOfflineDemo ? [
    'iban' => (($user['country'] ?? '') === 'Germany') ? 'DE89370400440532013000' : '',
    'routing_number' => (($user['country'] ?? '') === 'United States') ? US_ROUTING_NUMBER : '',
] : user_account((int) $user['id']);
$bankingRegion = user_banking_region($user, $accountForRegion);
$regionConfig = banking_region_config($bankingRegion);
$isUsExperience = $bankingRegion === 'us';
$accountLanguage = $regionConfig['language'];
$GLOBALS['pageLanguage'] = $accountLanguage;
$GLOBALS['disableTranslate'] = true;
$isRestricted = account_is_restricted($user);
$unreadCount = 0;
$notificationPreview = [];
if (!$isOfflineDemo) {
    $unreadStmt = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0');
    $unreadStmt->execute([$user['id']]);
    $unreadCount = (int) $unreadStmt->fetch()['c'];
    $previewStmt = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 4');
    $previewStmt->execute([$user['id']]);
    $notificationPreview = $previewStmt->fetchAll();
}
?>
<!doctype html>
<html lang="<?= e($accountLanguage) ?>" class="notranslate" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="google" content="notranslate">
    <title><?= e($pageTitle ?? 'Member Dashboard') ?> | <?= e(UI_BRAND_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/styles.css') ?>" rel="stylesheet">
    <script>
        (function () {
            document.cookie = 'googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.cookie = 'googtrans=;path=/;domain=' + location.hostname + ';expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.documentElement.classList.add('notranslate');
            document.documentElement.setAttribute('translate', 'no');
        })();
    </script>
</head>
<body class="notranslate" translate="no">
<div class="app-shell">
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= url('dashboard.php') ?>"><?= lead_logo('light') ?></a>
    <nav class="nav flex-column">
        <?php $nav = [
            ['dashboard.php','fa-gauge-high','Overview'],
            ['user/accounts.php','fa-layer-group',$accountLanguage === 'de' ? 'Konten' : 'Accounts'],
            ['user/send_money.php','fa-bolt',$regionConfig['rail_primary']],
            ['user/bill_pay.php','fa-calendar-check',$regionConfig['rail_scheduled']],
            ['user/ach_transfers.php','fa-building-columns',$regionConfig['rail_bank']],
            ['user/linked_accounts.php','fa-credit-card','Manage Credit Cards'],
            ['user/loans.php','fa-hand-holding-dollar',$accountLanguage === 'de' ? 'Kredite' : 'Loans'],
            ['user/transactions.php','fa-receipt','Transactions'],
            ['user/transfers.php','fa-right-left',$regionConfig['rail_wire']],
            ['user/cards.php','fa-credit-card','Cards'],
            ['user/deposits.php','fa-camera','Deposits'],
            ['user/statements.php','fa-file-lines','Statements'],
            ['user/security_center.php','fa-shield-halved','Security'],
            ['user/notifications.php','fa-bell','Notifications'],
            ['user/profile.php','fa-user-shield','Profile'],
            ['user/support.php','fa-headset','Support'],
        ]; ?>
        <?php $restrictedPaths = ['user/send_money.php','user/bill_pay.php','user/ach_transfers.php','user/transfers.php','user/cards.php','user/deposits.php']; ?>
        <?php foreach ($nav as $item): ?>
            <?php $disabled = $isRestricted && in_array($item[0], $restrictedPaths, true); ?>
            <?= lead_nav_item($item[0], $item[1], $item[2], str_ends_with($_SERVER['SCRIPT_NAME'], $item[0]), $disabled) ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-session mt-auto">
        <i class="fa-solid fa-shield-halved"></i>
        <div><strong><?= $isUsExperience ? 'Secure Session' : 'Sichere Sitzung' ?></strong><span><?= $isUsExperience ? 'Last login' : 'Letzter Login' ?> <?= e(date('M j, g:i A')) ?></span></div>
    </div>
</aside>
<main class="app-main">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-navy mobile-toggle" data-toggle-sidebar><i class="fa-solid fa-bars"></i></button>
            <div><div class="topbar-kicker"><?= e($regionConfig['workspace']) ?></div><h1 class="h3 mb-0 fw-bold"><?= e($pageTitle ?? 'Dashboard') ?></h1><div class="muted">Welcome back, <?= e($user['first_name']) ?></div></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="language-static-pill"><i class="fa-solid fa-language"></i><?= $accountLanguage === 'de' ? 'Deutsch' : 'English' ?></span>
            <div class="dropdown notification-menu">
                <button class="btn btn-light border position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadCount > 0): ?><span class="unread-dot"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown p-0">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <strong>Alerts</strong><a class="small fw-bold" href="<?= url('user/notifications.php') ?>">View all</a>
                    </div>
                    <?php foreach ($notificationPreview as $notice): ?>
                        <a class="dropdown-item notification-preview" href="<?= url('user/notifications.php') ?>">
                            <?php $noticeCategory = $notice['category'] ?? 'account'; ?>
                            <span class="tx-icon tx-icon-credit"><i class="fa-solid <?= $noticeCategory==='security'?'fa-shield-halved':($noticeCategory==='transfer'?'fa-right-left':'fa-bell') ?>"></i></span>
                            <span><strong><?= e($notice['title']) ?></strong><small><?= e($notice['message']) ?></small></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$notificationPreview): ?><div class="p-3 muted small">No notifications yet.</div><?php endif; ?>
                </div>
            </div>
            <img class="avatar-sm" src="<?= e(avatar_url($user['avatar'] ?? null)) ?>" alt="Profile picture">
            <a class="btn btn-outline-danger" href="<?= url('logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Sign out</a>
        </div>
    </div>
    <?php if ($isRestricted): ?>
        <div class="restriction-banner">
            <div class="restriction-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
                <strong>Account access temporarily restricted</strong>
                <p><?= e(restricted_account_message()) ?></p>
                <div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-light border" href="<?= url('user/support.php') ?>"><i class="fa-solid fa-headset me-1"></i>Contact support</a><a class="btn btn-sm btn-light border" href="<?= url('user/security_center.php') ?>"><i class="fa-solid fa-lock me-1"></i>Review security</a></div>
            </div>
        </div>
    <?php endif; ?>
    <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
