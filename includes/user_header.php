<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/frontend_components.php';
$user = require_user();
ensure_banking_schema();
cleanup_customer_notifications((int) $user['id']);
$accountForRegion = user_account((int) $user['id']);
$bankingRegion = user_banking_region($user, $accountForRegion);
$regionConfig = banking_region_config($bankingRegion);
$isUsExperience = $bankingRegion === 'us';
$accountLanguage = 'en';
$useGermanLabels = false;
$GLOBALS['pageLanguage'] = $accountLanguage;
$GLOBALS['disableTranslate'] = true;
$isRestricted = account_is_restricted($user);
$unreadStmt = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0');
$unreadStmt->execute([$user['id']]);
$unreadCount = (int) $unreadStmt->fetch()['c'];
$previewStmt = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 4');
$previewStmt->execute([$user['id']]);
$notificationPreview = $previewStmt->fetchAll();
$isDashboardPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'dashboard.php';
?>
<!doctype html>
<html lang="<?= e($accountLanguage) ?>" class="notranslate" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="google" content="notranslate">
    <title><?= e($pageTitle ?? 'Member Dashboard') ?> | <?= e(UI_BRAND_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/styles.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/styles.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/premium-banking.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/premium-banking.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/mobile-premium-fix.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/mobile-premium-fix.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/deutsche-bank-theme.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/deutsche-bank-theme.css') ?>" rel="stylesheet">
    <script>
        (function () {
            document.cookie = 'googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.cookie = 'googtrans=;path=/;domain=' + location.hostname + ';expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.documentElement.classList.add('notranslate');
            document.documentElement.setAttribute('translate', 'no');
        })();
    </script>
</head>
<body class="notranslate<?= $isDashboardPage ? ' dashboard-screen' : '' ?>" translate="no" data-session-timeout="<?= SESSION_IDLE_TIMEOUT ?>" data-session-logout-url="<?= e(url('logout.php')) ?>">
<div class="app-shell">

<!-- ═══════════════════════════════════════════
     SIDEBAR
     ═══════════════════════════════════════════ -->
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= url('dashboard.php') ?>"><?= brand_logo('light') ?></a>
    <nav class="nav flex-column">
        <?php $nav = [
            ['dashboard.php','fa-gauge-high','Overview'],
            ['user/accounts.php','fa-layer-group','Accounts'],
            ['user/send_money.php','fa-bolt',$regionConfig['rail_primary']],
            ['user/bill_pay.php','fa-calendar-check',$regionConfig['rail_scheduled']],
            ['user/ach_transfers.php','fa-building-columns',$regionConfig['rail_bank']],
            ['user/linked_accounts.php','fa-credit-card','Manage Credit Cards'],
            ['user/loans.php','fa-hand-holding-dollar','Loans'],
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
            <?= brand_nav_item($item[0], $item[1], $item[2], str_ends_with($_SERVER['SCRIPT_NAME'], $item[0]), $disabled) ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-session mt-auto">
        <i class="fa-solid fa-shield-halved"></i>
        <div><strong>Secure Session</strong><span>Last login <?= e(date('M j, g:i A')) ?></span></div>
    </div>
    <div class="sidebar-signout-wrap">
        <a class="sidebar-signout-btn" href="<?= url('logout.php') ?>">
            <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign Out</span>
        </a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.querySelector('.sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var toggles = document.querySelectorAll('[data-toggle-sidebar]');
  if (!sidebar || !overlay) return;

  function setExpanded(value) {
    toggles.forEach(function (btn) {
      btn.setAttribute('aria-expanded', value ? 'true' : 'false');
    });
  }

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open', 'show');
    document.body.classList.add('sidebar-open');
    setExpanded(true);
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open', 'show');
    document.body.classList.remove('sidebar-open');
    setExpanded(false);
  }

  toggles.forEach(function (btn) {
    btn.setAttribute('aria-expanded', 'false');
    btn.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    }, true);
  });

  overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeSidebar();
  });
  window.addEventListener('resize', function () {
    if (window.matchMedia('(min-width: 992px)').matches) closeSidebar();
  });
});
</script>

<!-- ═══════════════════════════════════════════
     MAIN CONTENT
     ═══════════════════════════════════════════ -->
<main class="app-main">

    <!-- ── TOPBAR ── -->
    <div class="topbar">

        <!-- LEFT: hamburger + title -->
        <div class="d-flex align-items-center">
            <button class="btn btn-navy mobile-toggle me-2" data-toggle-sidebar aria-label="Open menu" aria-controls="sidebarOverlay">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <?php if ($isDashboardPage): ?>
                    <div class="dashboard-greeting">
                        <span><?= $useGermanLabels ? 'Guten Tag' : 'Good afternoon' ?>,</span>
                        <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    </div>
                <?php else: ?>
                    <span class="topbar-kicker"><?= e($regionConfig['workspace']) ?></span>
                    <h1 class="h3 mb-0 fw-bold"><?= e($pageTitle ?? 'Dashboard') ?></h1>
                    <span class="muted">Welcome back, <?= e($user['first_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: actions — never overlap, always in one row -->
        <div class="d-flex align-items-center">

            <!-- Language -->
            <span class="language-static-pill me-1" title="Language: English">
                <i class="fa-solid fa-language"></i>
                <span class="language-static-label">EN</span>
            </span>

            <!-- Notifications -->
            <div class="dropdown notification-menu me-1">
                <button class="btn btn-light border position-relative"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="unread-dot"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown p-0">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <strong>Alerts</strong>
                        <a class="small fw-bold" href="<?= url('user/notifications.php') ?>">View all</a>
                    </div>
                    <?php foreach ($notificationPreview as $notice): ?>
                        <?php $noticeCategory = $notice['category'] ?? 'account'; ?>
                        <a class="dropdown-item notification-preview" href="<?= url('user/notifications.php') ?>">
                            <span class="tx-icon tx-icon-credit">
                                <i class="fa-solid <?= $noticeCategory==='security'?'fa-shield-halved':($noticeCategory==='transfer'?'fa-right-left':'fa-bell') ?>"></i>
                            </span>
                            <span>
                                <strong><?= e($notice['title']) ?></strong>
                                <small><?= e($notice['message']) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$notificationPreview): ?>
                        <div class="p-3 muted small">No notifications yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Avatar -->
            <img class="avatar-sm me-1"
                 src="<?= e(avatar_url($user['avatar'] ?? null)) ?>"
                 alt="<?= e($user['first_name']) ?>'s profile picture">

            <!-- Sign out -->
            <a class="btn btn-outline-danger" href="<?= url('logout.php') ?>" title="Sign out" aria-label="Sign out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div><!-- /topbar -->

    <!-- Restriction banner -->
    <?php if ($isRestricted): ?>
        <div class="restriction-banner">
            <div class="restriction-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
                <strong>Account access temporarily restricted</strong>
                <p><?= e(restricted_account_message()) ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-light border" href="<?= url('user/support.php') ?>">
                        <i class="fa-solid fa-headset me-1"></i>Contact support
                    </a>
                    <a class="btn btn-sm btn-light border" href="<?= url('user/security_center.php') ?>">
                        <i class="fa-solid fa-lock me-1"></i>Review security
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Flash messages -->
    <?php foreach (flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
