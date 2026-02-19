<?php
/**
 * REXTIAN SSO - OAuth 2.0 Token 端点
 * POST: 用 authorization_code 换取 access_token
 * 支持 application/x-www-form-urlencoded 或 application/json
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Method Not Allowed']);
    exit;
}

// 解析请求体：OAuth 2.0 标准为 application/x-www-form-urlencoded
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

$grantType = trim($input['grant_type'] ?? '');
$code = trim($input['code'] ?? '');
$redirectUri = trim($input['redirect_uri'] ?? '');
$clientId = trim($input['client_id'] ?? '');
$clientSecret = trim($input['client_secret'] ?? '');

// 支持 Basic Auth 传递 client_id / client_secret
if (empty($clientId) || empty($clientSecret)) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Basic\s+(.+)$/i', $auth, $m)) {
        $decoded = base64_decode(trim($m[1]), true);
        if ($decoded && strpos($decoded, ':') !== false) {
            list($clientId, $clientSecret) = explode(':', $decoded, 2);
        }
    }
}

function tokenError($error, $desc = '', $httpCode = 400) {
    http_response_code($httpCode);
    $out = ['error' => $error];
    if ($desc) $out['error_description'] = $desc;
    echo json_encode($out);
    exit;
}

if ($grantType !== 'authorization_code') {
    tokenError('unsupported_grant_type', '仅支持 grant_type=authorization_code');
}

if ($code === '' || $redirectUri === '' || $clientId === '' || $clientSecret === '') {
    tokenError('invalid_request', 'code, redirect_uri, client_id, client_secret 必填');
}

$pdo = getDb();
if (!$pdo) {
    tokenError('server_error', '服务暂时不可用', 500);
}

// 查询授权码
$stmt = $pdo->prepare("
    SELECT ac.code, ac.user_id, ac.app_id, ac.redirect_uri, ac.scope, ac.expires_at, a.client_id, a.client_secret
    FROM oauth_authorization_codes ac
    JOIN oauth_apps a ON ac.app_id = a.id
    WHERE ac.code = ? LIMIT 1
");
$stmt->execute([$code]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    tokenError('invalid_grant', '授权码无效或已使用');
}

if ($row['client_id'] !== $clientId || !hash_equals($row['client_secret'], $clientSecret)) {
    tokenError('invalid_client', 'client_id 或 client_secret 错误');
}

if (strtotime($row['expires_at']) < time()) {
    tokenError('invalid_grant', '授权码已过期');
}

// redirect_uri 必须与授权时一致
$reqUri = $redirectUri;
$storedUri = $row['redirect_uri'];
if ($reqUri !== $storedUri && rtrim($reqUri, '/') !== rtrim($storedUri, '/')) {
    tokenError('invalid_grant', 'redirect_uri 不匹配');
}

// 删除已使用的授权码（一次性）
$pdo->prepare("DELETE FROM oauth_authorization_codes WHERE code = ?")->execute([$code]);

// 生成 access_token、refresh_token
$accessToken = bin2hex(random_bytes(32));
$refreshToken = bin2hex(random_bytes(32));
$expiresIn = 3600; // 1 小时
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
$userId = (int) $row['user_id'];
$appId = (int) $row['app_id'];
$scope = $row['scope'] ?: null;

// 确保 oauth_tokens 表存在并写入
try {
    $pdo->prepare("INSERT INTO oauth_tokens (access_token, refresh_token, user_id, app_id, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$accessToken, $refreshToken, $userId, $appId, $scope, $expiresAt]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'oauth_tokens') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS oauth_tokens (
            access_token VARCHAR(64) PRIMARY KEY,
            refresh_token VARCHAR(64) UNIQUE,
            user_id INT UNSIGNED NOT NULL,
            app_id INT UNSIGNED NOT NULL,
            scope VARCHAR(255),
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_refresh (refresh_token),
            INDEX idx_user_app (user_id, app_id),
            INDEX idx_expires (expires_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (app_id) REFERENCES oauth_apps(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->prepare("INSERT INTO oauth_tokens (access_token, refresh_token, user_id, app_id, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$accessToken, $refreshToken, $userId, $appId, $scope, $expiresAt]);
    } else {
        throw $e;
    }
}

echo json_encode([
    'access_token' => $accessToken,
    'token_type' => 'Bearer',
    'expires_in' => $expiresIn,
    'refresh_token' => $refreshToken,
]);
