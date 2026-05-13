<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/frontend_components.php';
ensure_banking_schema();
$admin = require_admin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Admin') ?> | <?= e(UI_BRAND_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/styles.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= url('admin/index.php') ?>"><?= lead_logo('light') ?><span class="admin-brand-label">Admin</span></a>
    <nav class="nav flex-column">
        <?php $nav = [
            ['admin/index.php','fa-chart-line','Analytics'],
            ['admin/users.php','fa-users','Users'],
            ['admin/transfers.php','fa-money-bill-transfer','Transfers'],
            ['admin/referral_bonuses.php','fa-gift','Signup Bonuses'],
            ['admin/card_approvals.php','fa-credit-card','Manage Credit Cards'],
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
    <div class="mt-auto small text-white-50"><?= e($admin['role']) ?> permissions<br><?= e($admin['email']) ?></div>
</aside>
<main class="app-main">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-navy mobile-toggle" data-toggle-sidebar><i class="fa-solid fa-bars"></i></button>
            <div><h1 class="h3 mb-0 fw-bold"><?= e($pageTitle ?? 'Admin') ?></h1><div class="muted">Operational command center</div></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= google_translate_widget() ?>
            <a class="btn btn-outline-danger" href="<?= url('admin/logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Sign out</a>
        </div>
    </div>
    <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
