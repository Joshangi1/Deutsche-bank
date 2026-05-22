<?php
$mobileNavItems = [
    ['dashboard.php', 'fa-house', 'Dashboard'],
    ['user/cards.php', 'fa-credit-card', 'Cards'],
    ['user/transactions.php', 'fa-right-left', 'Transactions'],
    ['user/profile.php', 'fa-user', 'Profile'],
];
$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
?>
</main>
</div>
<nav class="mobile-bottom-nav" aria-label="Mobile banking navigation">
    <?php foreach ($mobileNavItems as $item): ?>
        <?php $active = str_ends_with($currentScript, $item[0]); ?>
        <a class="<?= $active ? 'active' : '' ?>" href="<?= url($item[0]) ?>">
            <i class="fa-solid <?= e($item[1]) ?>"></i>
            <span><?= e($item[2]) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
