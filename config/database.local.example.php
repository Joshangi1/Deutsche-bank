<?php
declare(strict_types=1);

// Copy this file to one of these private server-only locations:
// 1. config/database.local.php
// 2. public_html/database.local.php
// 3. one folder above public_html as database.local.php
//
// Hostinger Git/file deploys may overwrite public_html. The safest option is
// placing database.local.php one folder above public_html so pushes cannot erase it.
// Do not commit database.local.php. It contains private hosting credentials.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'u626255957_Lead_Bank');
define('DB_USER', 'your_hostinger_database_user');
define('DB_PASS', 'your_hostinger_database_password');
define('DB_CHARSET', 'utf8mb4');
