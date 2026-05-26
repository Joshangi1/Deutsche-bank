<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = require_admin();
$id = (int) ($_GET['id'] ?? 0);
$file = (string) ($_GET['file'] ?? '');
if (!in_array($file, ['card', 'proof'], true)) {
    http_response_code(404);
    exit('Media not found.');
}

$column = $file === 'card' ? 'front_image' : 'proof_file';
$stmt = db()->prepare('SELECT ' . $column . ' AS file_name FROM deposits WHERE id=? AND deposit_method="apple_gift_card" LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
$fileName = $row['file_name'] ?? null;
$baseDir = realpath(__DIR__ . '/../uploads/private/gift_cards');
$path = $baseDir && $fileName ? realpath($baseDir . DIRECTORY_SEPARATOR . basename((string) $fileName)) : false;
if (!$baseDir || !$path || !str_starts_with($path, $baseDir)) {
    http_response_code(404);
    exit('Media not found.');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
    http_response_code(404);
    exit('Media not found.');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="deposit-document.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
