<?php require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/helpers.php'; require_once __DIR__ . '/frontend_components.php'; ?>
<?php
$publicLoginUrl = $GLOBALS['pageLoginUrl'] ?? 'choose_banking.php?next=login';
$brandConfig = $GLOBALS['brandConfig'] ?? getBrandConfig($GLOBALS['authRegion'] ?? ($_GET['region'] ?? 'us'));
$GLOBALS['brandConfig'] = $brandConfig;
$pageLanguage = 'en';
$translateDisabled = true;
$publicStaticMode = !empty($GLOBALS['publicStaticMode']);
$navLabels = ['infra' => 'Financial Infrastructure', 'banking' => 'Banking', 'about' => 'About', 'contact' => 'Contact', 'signin' => 'Sign in'];
?>
<!doctype html>
<html lang="<?= e($pageLanguage) ?>"<?= $translateDisabled ? ' class="notranslate" translate="no"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e((string) $brandConfig['brand_name']) ?> digital banking and financial infrastructure.">
    <?php if ($translateDisabled): ?><meta name="google" content="notranslate"><?php endif; ?>
    <title><?= e($pageTitle ?? (string) $brandConfig['brand_short_name']) ?></title>
    <link rel="icon" href="<?= e(url((string) $brandConfig['favicon'])) ?>" type="<?= e(brand_favicon_type($brandConfig)) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/styles.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/styles.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/deutsche-bank-theme.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/deutsche-bank-theme.css') ?>" rel="stylesheet">
    <?= brand_css_variables($brandConfig) ?>
    <?php if ($translateDisabled): ?>
    <script>
        (function () {
            document.cookie = 'googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.cookie = 'googtrans=;path=/;domain=' + location.hostname + ';expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.documentElement.classList.add('notranslate');
            document.documentElement.setAttribute('translate', 'no');
        })();
    </script>
    <?php endif; ?>
</head>
<body class="<?= e(trim(($translateDisabled ? 'notranslate ' : '') . brand_body_class($brandConfig))) ?>"<?= $translateDisabled ? ' translate="no"' : '' ?>>
<div class="loader"><span></span></div>
<nav class="navbar navbar-expand-lg bank-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand p-0" href="<?= url('index.php') ?>"><?= brand_logo('dark') ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
        <div id="mainNav" class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>"><?= e($navLabels['infra']) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('personal.php') ?>"><?= e($navLabels['banking']) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('about.php') ?>"><?= e($navLabels['about']) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('contact.php') ?>"><?= e($navLabels['contact']) ?></a></li>
                <?php if (!$translateDisabled): ?><li class="nav-item"><?= google_translate_widget() ?></li><?php endif; ?>
                <li class="nav-item ms-lg-3"><a class="btn btn-primary-pill btn-sm px-4" href="<?= url($publicLoginUrl) ?>"><?= e($navLabels['signin']) ?></a></li>
            </ul>
        </div>
    </div>
</nav>
<main>
<?php foreach ($publicStaticMode ? [] : flashes() as $f): ?>
    <div class="container mt-3"><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div></div>
<?php endforeach; ?>
