<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = require_admin();
$id = (int) ($_GET['id'] ?? 0);
$side = (string) ($_GET['side'] ?? '');

if (!in_array($side, ['front', 'back'], true)) {
    http_response_code(404);
    exit('Media not found.');
}

$column = $side === 'front' ? 'front_image' : 'back_image';
$stmt = db()->prepare('SELECT ' . $column . ' file_name FROM linked_cards WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
$fileName = $row['file_name'] ?? null;

if (!$fileName) {
    http_response_code(404);
    exit('Media not found.');
}

$baseDir = __DIR__ . '/../uploads/private/cards';
$path = realpath($baseDir . DIRECTORY_SEPARATOR . basename((string) $fileName));
$base = realpath($baseDir);
if (!$path || !$base || !str_starts_with($path, $base)) {
    http_response_code(404);
    exit('Media not found.');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
