<?php
/**
 * REXTIAN SSO - POST /api/apps/update.php
 * 更新 OAuth 应用
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
$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    jsonFail(40001, '应用 ID 无效');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, name, app_type FROM oauth_apps WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$app) {
    jsonFail(40401, '应用不存在');
}

$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$redirectUris = $input['redirect_uris'] ?? [];
$status = $input['status'] ?? null;
$appType = $input['app_type'] ?? ($app['app_type'] ?? 'oauth');
$ubntApiUrl = trim($input['ubnt_api_url'] ?? '');
$ubntApiKey = trim($input['ubnt_api_key'] ?? '');
$ikuaiApiUrl = trim($input['ikuai_api_url'] ?? '');
$ikuaiToken = trim($input['ikuai_token'] ?? '');

if ($name === '') {
    jsonFail(40002, '应用名称不能为空');
}

if (!is_array($redirectUris)) {
    $redirectUris = [];
}
$redirectUris = array_values(array_filter(array_map('trim', $redirectUris)));
$appType = in_array($appType, ['oauth', 'ubnt', 'ikuai']) ? $appType : 'oauth';

$updates = ['name = ?', 'description = ?', 'redirect_uris = ?', 'app_type = ?'];
$params = [$name, $description ?: null, json_encode($redirectUris), $appType];

if ($status !== null && in_array($status, ['live', 'dev'])) {
    $updates[] = 'status = ?';
    $params[] = $status;
}

if ($appType === 'ubnt') {
    if ($ubntApiUrl === '' || $ubntApiKey === '') {
        jsonFail(40002, 'UBNT 集成需填写 API 连接地址和 API Key');
    }
    $updates[] = 'ubnt_api_url = ?';
    $updates[] = 'ubnt_api_key = ?';
    $params[] = $ubntApiUrl;
    $params[] = $ubntApiKey;
} else {
    $updates[] = 'ubnt_api_url = NULL';
    $updates[] = 'ubnt_api_key = NULL';
}
if ($appType === 'ikuai') {
    if ($ikuaiApiUrl === '' || $ikuaiToken === '') {
        jsonFail(40002, '爱快集成需填写 API 连接地址和 Token');
    }
    $updates[] = 'ikuai_api_url = ?';
    $updates[] = 'ikuai_token = ?';
    $params[] = $ikuaiApiUrl;
    $params[] = $ikuaiToken;
} else {
    $updates[] = 'ikuai_api_url = NULL';
    $updates[] = 'ikuai_token = NULL';
}

$params[] = $id;
$pdo->prepare("UPDATE oauth_apps SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

$adminId = $_SESSION['user_id'] ?? 0;
auditLog('app.update', $adminId, null, ['app_id' => $id, 'name' => $name], 'success');

echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['id' => $id]]);
