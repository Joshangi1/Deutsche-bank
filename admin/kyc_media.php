<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = require_admin();
$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$field = (string) ($_GET['field'] ?? '');
$baseDir = null;
$fileName = null;

if ($type === 'document') {
    $stmt = db()->prepare('SELECT file_name FROM kyc_documents WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $baseDir = __DIR__ . '/../uploads/private/kyc';
    $fileName = $row['file_name'] ?? null;
} elseif ($type === 'biometric' && in_array($field, ['capture_forward', 'capture_left', 'capture_right', 'capture_blink'], true)) {
    $stmt = db()->prepare('SELECT ' . $field . ' file_name FROM biometric_verifications WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $baseDir = __DIR__ . '/../uploads/private/biometric';
    $fileName = $row['file_name'] ?? null;
}

if (!$baseDir || !$fileName) {
    http_response_code(404);
    exit('Media not found.');
}

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
readfile($path);
