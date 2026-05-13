<?php
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'sterling_harbor_bank');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

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
