<?php
declare(strict_types=1);

$localConfigCandidates = array_filter([
    getenv('APP_DATABASE_CONFIG') ?: null,
    __DIR__ . '/database.local.php',
    dirname(__DIR__) . '/database.local.php',
    dirname(__DIR__, 2) . '/database.local.php',
    !empty($_SERVER['HOME']) ? rtrim((string) $_SERVER['HOME'], '/\\') . '/database.local.php' : null,
]);

foreach ($localConfigCandidates as $localConfig) {
    if (is_file($localConfig)) {
        require_once $localConfig;
        break;
    }
}

defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: '12.0.0.1');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'u626255957_deutsche');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'u626255957_oracle');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: 'Joshangi1@');
defined('DB_CHARSET') || define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (!empty($GLOBALS['DB_SILENT_FAILURE'])) {
            throw $e;
        }
        http_response_code(500);
        exit('Database connection failed. Check config/database.php and import database/schema.sql.');
    }

    return $pdo;
}
