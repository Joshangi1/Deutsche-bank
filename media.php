<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
ensure_banking_schema();

$subdir = trim(str_replace('\\', '/', (string) ($_GET['subdir'] ?? '')), '/');
$file = basename((string) ($_GET['file'] ?? ''));
$allowedSubdirs = ['avatars', 'admin_profiles'];

if (!in_array($subdir, $allowedSubdirs, true) || $file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(404);
    exit;
}

$stmt = db()->prepare('SELECT mime_type, size_bytes, content, updated_at FROM uploaded_media WHERE subdir=? AND file_name=? LIMIT 1');
$stmt->execute([$subdir, $file]);
$media = $stmt->fetch();
if (!$media) {
    http_response_code(404);
    exit;
}

$mime = (string) ($media['mime_type'] ?? 'application/octet-stream');
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}

$content = $media['content'];
if (is_resource($content)) {
    $content = stream_get_contents($content);
}
$content = (string) $content;
if ($content === '') {
    http_response_code(404);
    exit;
}

$etag = '"' . sha1($subdir . '/' . $file . '/' . (string) ($media['updated_at'] ?? '') . '/' . strlen($content)) . '"';
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($content));
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
echo $content;
