<?php
/**
 * REXTIAN SSO - 获取登录跳转 URL API
 * 功能：生成 OAuth 授权登录 URL，供其他门户直接跳转到登录页
 * 
 * 使用方法：
 * GET /api/sso/login-url.php?client_id=xxx&redirect_uri=xxx&state=xxx
 * 
 * 返回：
 * {
 *   "code": 0,
 *   "message": "success",
 *   "data": {
 *     "login_url": "https://sso.rextian.com/oauth/authorize.php?..."
 *   }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';

function jsonResponse($code, $message, $data = null) {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$clientId = trim($_GET['client_id'] ?? '');
$redirectUri = trim($_GET['redirect_uri'] ?? '');
$scope = trim($_GET['scope'] ?? 'openid profile email');
$state = trim($_GET['state'] ?? bin2hex(random_bytes(16)));

if (empty($clientId)) {
    jsonResponse(40001, '缺少 client_id 参数');
}

if (empty($redirectUri)) {
    jsonResponse(40002, '缺少 redirect_uri 参数');
}

$pdo = getDb();
if (!$pdo) {
    jsonResponse(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, name, redirect_uris, status FROM oauth_apps WHERE client_id = ? LIMIT 1");
$stmt->execute([$clientId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    jsonResponse(40401, '应用不存在');
}

if ($app['status'] !== 'live' && $app['status'] !== 'dev') {
    jsonResponse(40301, '应用已禁用');
}

$redirectUris = $app['redirect_uris'];
if (is_string($redirectUris)) {
    $redirectUris = json_decode($redirectUris, true) ?: [];
}

$uriMatch = false;
foreach ($redirectUris as $uri) {
    if ($redirectUri === $uri || rtrim($redirectUri, '/') === rtrim($uri, '/')) {
        $uriMatch = true;
        break;
    }
}

if (!$uriMatch && !empty($redirectUris)) {
    jsonResponse(40003, 'redirect_uri 不在白名单中');
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$loginUrl = $baseUrl . '/oauth/authorize.php?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scope,
    'state' => $state
]);

jsonResponse(0, 'success', [
    'login_url' => $loginUrl,
    'state' => $state,
    'app_name' => $app['name']
]);
