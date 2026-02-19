<?php
/**
 * REXTIAN SSO - DELETE /api/me/connections/{provider}
 * 解绑第三方账号
 * 调用方式: POST api/me/connections/unbind.php?provider=github
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireLogin();

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    echo json_encode(['code' => 40301, 'message' => '请求无效，请刷新页面重试', 'data' => null]);
    exit;
}

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

$provider = strtolower(trim($_GET['provider'] ?? ''));
$allowed = ['github', 'wechat', 'feishu', 'dingtalk', 'wecom'];
if (!in_array($provider, $allowed)) {
    jsonFail(40001, '不支持的平台');
}

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("DELETE FROM user_connections WHERE user_id = ? AND provider = ?");
$stmt->execute([$userId, $provider]);

if ($stmt->rowCount() > 0) {
    auditLog('user.connection.unbind', $userId, $_SESSION['username'] ?? null, ['provider' => $provider], 'success');
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => null,
]);
