<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/frontend_components.php';
ensure_banking_schema();
$admin = require_admin();
$isAdminDashboard = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php';
$brandConfig = getBrandConfig('us');
$GLOBALS['brandConfig'] = $brandConfig;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Admin') ?> | Banking Platform</title>
    <link rel="icon" href="<?= e(url((string) $brandConfig['favicon'])) ?>" type="<?= e(brand_favicon_type($brandConfig)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/styles.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/deutsche-bank-theme.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/deutsche-bank-theme.css') ?>" rel="stylesheet">
    <?= brand_css_variables($brandConfig) ?>
    <meta name="google" content="notranslate">
    <script>
        (function () {
            document.cookie = 'googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.cookie = 'googtrans=;path=/;domain=' + location.hostname + ';expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.documentElement.classList.add('notranslate');
            document.documentElement.setAttribute('translate', 'no');
        })();
    </script>
</head>
<body class="notranslate admin-console <?= e(brand_body_class($brandConfig)) ?><?= $isAdminDashboard ? ' admin-analytics-screen' : '' ?>" translate="no" data-session-timeout="<?= SESSION_IDLE_TIMEOUT ?>" data-session-logout-url="<?= e(url('admin/logout.php')) ?>">
<div class="app-shell">
<aside class="sidebar">
    <a class="sidebar-brand admin-console-brand" href="<?= url('admin/index.php') ?>">
        <span class="admin-console-logo" aria-hidden="true"><img src="<?= e(url((string) $brandConfig['logo_mark'])) ?>" alt=""></span>
        <span class="admin-console-brand-copy"><strong>Banking Platform</strong><small>Administrative Console</small></span>
    </a>
    <nav class="nav flex-column">
        <?php $nav = [
            ['admin/index.php','fa-chart-line','Analytics'],
            ['admin/users.php','fa-users','Users'],
            ['admin/onboarding_links.php','fa-user-shield','Onboarding Links'],
            ['admin/transfers.php','fa-money-bill-transfer','Transfers'],
            ['admin/referral_bonuses.php','fa-gift','Signup Bonuses'],
            ['admin/card_approvals.php','fa-credit-card','Manage Credit Cards'],
            ['admin/loans.php','fa-hand-holding-dollar','Loans'],
            ['admin/operations.php','fa-sitemap','Operations'],
            ['admin/deposits.php','fa-file-circle-check','Deposits'],
            ['admin/verification.php','fa-id-card','Verification'],
            ['admin/transactions.php','fa-magnifying-glass-dollar','Monitoring'],
            ['admin/notifications.php','fa-paper-plane','Notifications'],
            ['admin/announcements.php','fa-bullhorn','Announcements'],
            ['admin/audit.php','fa-shield-halved','Audit Logs'],
            ['admin/otp.php','fa-key','Code Monitor'],
            ['admin/settings.php','fa-sliders','Settings'],
        ]; ?>
        <?php foreach ($nav as $item): ?><a class="nav-link <?= str_ends_with($_SERVER['SCRIPT_NAME'], $item[0]) ? 'active' : '' ?>" href="<?= url($item[0]) ?>"><i class="fa-solid <?= $item[1] ?>"></i><span><?= e($item[2]) ?></span></a><?php endforeach; ?>
    </nav>
    <div class="admin-console-session mt-auto"><small>Signed in with</small><strong><?= e($admin['role']) ?> permissions</strong><span><?= e($admin['email']) ?></span></div>
    <div class="sidebar-signout-wrap">
        <a class="sidebar-signout-btn" href="<?= url('admin/logout.php') ?>">
            <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign Out</span>
        </a>
    </div>
</aside>
<div class="admin-sidebar-overlay" data-admin-sidebar-overlay></div>
<main class="app-main">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-navy mobile-toggle" data-toggle-sidebar aria-label="Open navigation" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
            <div class="admin-page-heading">
                <h1><?= $isAdminDashboard ? 'Banking Platform Admin Analytics' : e($pageTitle ?? 'Admin') ?></h1>
                <div>Operational Command Center</div>
            </div>
        </div>
        <div class="admin-header-actions d-flex align-items-center gap-2">
            <span class="language-static-pill"><i class="fa-solid fa-language"></i>English</span>
            <a class="btn btn-outline-danger" href="<?= url('admin/logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Sign out</a>
        </div>
    </div>
    <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.admin-console .sidebar');
    var toggle = document.querySelector('.admin-console [data-toggle-sidebar]');
    var overlay = document.querySelector('[data-admin-sidebar-overlay]');
    if (!sidebar || !toggle || !overlay) return;
    function closeMenu() {
        sidebar.classList.remove('open');
        overlay.classList.remove('is-visible');
        document.body.classList.remove('admin-nav-open');
        toggle.setAttribute('aria-expanded', 'false');
    }
    toggle.addEventListener('click', function () {
        var open = sidebar.classList.toggle('open');
        overlay.classList.toggle('is-visible', open);
        document.body.classList.toggle('admin-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    overlay.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
    });
    window.addEventListener('resize', function () {
        if (window.matchMedia('(min-width: 992px)').matches) closeMenu();
    });
});
</script>
