<?php
/**
 * REXTIAN SSO - GET /api/audit-logs/detail.php?trace_id=xxx
 * 审计日志详情
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireAdmin();

$traceId = trim($_GET['trace_id'] ?? '');
if ($traceId === '') {
    echo json_encode(['code' => 40001, 'message' => 'trace_id 必填', 'data' => null]);
    exit;
}

$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 50000, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

$stmt = $pdo->prepare("SELECT a.id, a.trace_id, a.event, a.user_id, a.user_email, a.ip, a.payload, a.status, a.created_at, u.username
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.trace_id = ? LIMIT 1");
$stmt->execute([$traceId]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    echo json_encode(['code' => 40401, 'message' => '日志不存在', 'data' => null]);
    exit;
}

$payload = $r['payload'] ? json_decode($r['payload'], true) : null;

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'id' => (int) $r['id'],
        'trace_id' => $r['trace_id'],
        'event' => $r['event'],
        'user_id' => $r['user_id'] ? (int) $r['user_id'] : null,
        'user_email' => $r['user_email'],
        'username' => $r['username'],
        'ip' => $r['ip'],
        'payload' => $payload,
        'status' => $r['status'],
        'created_at' => $r['created_at'],
    ],
]);
