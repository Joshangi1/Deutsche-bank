<?php
require_once __DIR__ . '/includes/helpers.php';

$redirect = 'choose_banking.php?next=login';
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $region = user_banking_region($user, user_account((int) $user['id']));
            $redirect = banking_region_config($region)['login'];
        }
    } catch (Throwable $e) {
        $redirect = 'choose_banking.php?next=login';
    }
}

session_destroy();
header('Location: ' . $redirect);
exit;
