<?php
/**
 * REXTIAN SSO - DELETE /api/me/sessions/{id}
 * 撤销指定会话（通过 ?id= 传递 session_id）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// 支持 DELETE 或 POST，session_id 通过 ?id= 或 body
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = trim($_GET['id'] ?? $input['id'] ?? '');

if ($sessionId === '') {
    jsonFail(40001, '请提供要撤销的会话 ID');
}

$userId = (int) $_SESSION['user_id'];
$currentSessionId = session_id();

// 不能撤销当前会话（会导致自己掉线）
if ($sessionId === $currentSessionId) {
    jsonFail(40002, '不能撤销当前会话，请先退出登录');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, user_id FROM sessions WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$sessionId, $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    jsonFail(40401, '会话不存在或无权操作');
}

$pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$sessionId]);

auditLog('user.session.revoke', $userId, $_SESSION['username'] ?? null, ['session_id' => $sessionId], 'success');

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => null,
]);
