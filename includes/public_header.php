<?php require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/helpers.php'; require_once __DIR__ . '/frontend_components.php'; ?>
<?php
$publicLoginUrl = $GLOBALS['pageLoginUrl'] ?? 'login.php';
$pageLanguage = $GLOBALS['pageLanguage'] ?? 'en';
$translateDisabled = !empty($GLOBALS['disableTranslate']);
$publicStaticMode = !empty($GLOBALS['publicStaticMode']);
$isGermanPage = $pageLanguage === 'de';
$navLabels = $isGermanPage
    ? ['infra' => 'Finanzinfrastruktur', 'banking' => 'Banking', 'about' => 'Ueber uns', 'contact' => 'Kontakt', 'signin' => 'Anmelden']
    : ['infra' => 'Financial Infrastructure', 'banking' => 'Banking', 'about' => 'About', 'contact' => 'Contact', 'signin' => 'Sign in'];
?>
<!doctype html>
<html lang="<?= e($pageLanguage) ?>"<?= $translateDisabled ? ' class="notranslate" translate="no"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Deutsche digital banking and financial infrastructure.">
    <?php if ($translateDisabled): ?><meta name="google" content="notranslate"><?php endif; ?>
    <title><?= e($pageTitle ?? UI_BRAND_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/styles.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/styles.css') ?>" rel="stylesheet">
    <style>
        :root {
            --navy: <?= e($publicStaticMode ? '#111827' : setting('theme_navy', '#111827')) ?>;
            --gold: <?= e($publicStaticMode ? '#0018a8' : setting('theme_gold', '#0018a8')) ?>;
        }
    </style>
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
<body<?= $translateDisabled ? ' class="notranslate" translate="no"' : '' ?>>
<div class="loader"><span></span></div>
<nav class="navbar navbar-expand-lg bank-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand p-0" href="<?= url('index.php') ?>"><?= lead_logo('dark') ?></a>
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
