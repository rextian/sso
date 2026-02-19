<?php
/**
 * REXTIAN SSO - POST /api/apps/delete.php
 * 删除 OAuth 应用
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

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) {
    jsonFail(40001, '应用 ID 无效');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, name FROM oauth_apps WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$app) {
    jsonFail(40401, '应用不存在');
}

$pdo->prepare("DELETE FROM oauth_apps WHERE id = ?")->execute([$id]);

$adminId = $_SESSION['user_id'] ?? 0;
auditLog('app.delete', $adminId, null, ['app_id' => $id, 'name' => $app['name']], 'success');

echo json_encode(['code' => 0, 'message' => 'success', 'data' => null]);
