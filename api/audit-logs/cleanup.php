<?php
/**
 * REXTIAN SSO - POST /api/audit-logs/cleanup.php
 * 清理过期审计日志
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireAdmin();

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    echo json_encode(['code' => 40301, 'message' => '请求无效，请刷新页面重试', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$retentionDays = max(0, min(3650, (int) ($input['retention_days'] ?? 90)));

$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 50000, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

$deleted = 0;
if ($retentionDays === 0) {
    $stmt = $pdo->prepare("DELETE FROM audit_logs");
    $stmt->execute();
    $deleted = $stmt->rowCount();
} else {
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
    $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < ?");
    $stmt->execute([$cutoff]);
    $deleted = $stmt->rowCount();
}

auditLog('audit.cleanup', $_SESSION['user_id'] ?? 0, null, ['retention_days' => $retentionDays, 'deleted' => $deleted], 'success');

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['deleted' => $deleted, 'retention_days' => $retentionDays],
]);
