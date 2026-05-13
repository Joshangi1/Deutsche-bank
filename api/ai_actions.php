<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

try {
    $admin = require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }

    $raw = file_get_contents('php://input') ?: '';
    $input = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    $action = (string) ($input['action'] ?? '');
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    $assistantId = preg_replace('/[^a-zA-Z0-9_.:-]/', '', (string) ($input['assistant_id'] ?? 'admin-console'));

    $allowed = ['create_transfer', 'create_notification', 'update_balance'];
    if (!in_array($action, $allowed, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Unsupported action']);
        exit;
    }

    $actor = banking_actor('ai', $assistantId);
    $result = banking_ai_action($action, $payload, $actor);
    log_admin((int) $admin['id'], 'ai_action', 'Approved AI-safe action: ' . $action, isset($payload['user_id']) ? (int) $payload['user_id'] : null, null, ['assistant_id' => $assistantId, 'result' => $result]);
    echo json_encode(['ok' => true, 'result' => $result]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
