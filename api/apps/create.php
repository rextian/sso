<?php
/**
 * REXTIAN SSO - POST /api/apps/create.php
 * 创建 OAuth 应用
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
$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$redirectUris = $input['redirect_uris'] ?? [];
$status = $input['status'] ?? 'dev';
$appType = $input['app_type'] ?? 'oauth';
$ubntApiUrl = trim($input['ubnt_api_url'] ?? '');
$ubntApiKey = trim($input['ubnt_api_key'] ?? '');
$ikuaiApiUrl = trim($input['ikuai_api_url'] ?? '');
$ikuaiToken = trim($input['ikuai_token'] ?? '');

if ($name === '') {
    jsonFail(40001, '应用名称不能为空');
}

if (!is_array($redirectUris)) {
    $redirectUris = [];
}
$redirectUris = array_values(array_filter(array_map('trim', $redirectUris)));
$status = in_array($status, ['live', 'dev']) ? $status : 'dev';
$appType = in_array($appType, ['oauth', 'ubnt', 'ikuai']) ? $appType : 'oauth';

if ($appType === 'ubnt') {
    if ($ubntApiUrl === '' || $ubntApiKey === '') {
        jsonFail(40001, 'UBNT 集成需填写 API 连接地址和 API Key');
    }
}
if ($appType === 'ikuai') {
    if ($ikuaiApiUrl === '' || $ikuaiToken === '') {
        jsonFail(40001, '爱快集成需填写 API 连接地址和 Token');
    }
}

$prefix = $appType === 'ubnt' ? 'ubnt_' : ($appType === 'ikuai' ? 'ikuai_' : 'client_');
$clientId = $prefix . bin2hex(random_bytes(8));
$clientSecret = $appType === 'ubnt' ? bin2hex(random_bytes(16)) : bin2hex(random_bytes(24));

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

// 确保 client_id 唯一（极小概率重复）
for ($i = 0; $i < 3; $i++) {
    $chk = $pdo->prepare("SELECT 1 FROM oauth_apps WHERE client_id = ? LIMIT 1");
    $chk->execute([$clientId]);
    if (!$chk->fetch()) break;
    $clientId = 'client_' . bin2hex(random_bytes(8));
}

$stmt = $pdo->prepare("INSERT INTO oauth_apps (app_type, client_id, client_secret, name, description, redirect_uris, ubnt_api_url, ubnt_api_key, ikuai_api_url, ikuai_token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $appType, $clientId, $clientSecret, $name, $description ?: null, json_encode($redirectUris),
    $appType === 'ubnt' ? $ubntApiUrl : null, $appType === 'ubnt' ? $ubntApiKey : null,
    $appType === 'ikuai' ? $ikuaiApiUrl : null, $appType === 'ikuai' ? $ikuaiToken : null,
    $status
]);
$appId = (int) $pdo->lastInsertId();

$adminId = $_SESSION['user_id'] ?? 0;
auditLog('app.create', $adminId, null, ['app_id' => $appId, 'name' => $name], 'success');

$data = ['id' => $appId, 'client_id' => $clientId, 'name' => $name];
if ($appType === 'oauth') {
    $data['client_secret'] = $clientSecret;
}
echo json_encode(['code' => 0, 'message' => 'success', 'data' => $data]);
