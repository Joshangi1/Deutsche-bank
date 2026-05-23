<?php
declare(strict_types=1);

require_once __DIR__ . '/frontend_components.php';

function deposit_protection_banner(array $user, ?array $account = null, string $className = ''): string
{
    return deposit_protection_badge($user, $account, $className);
}
