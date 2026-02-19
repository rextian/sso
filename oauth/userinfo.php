<?php
/**
 * REXTIAN SSO - OAuth 2.0 UserInfo 端点
 * GET: 根据 access_token 返回用户信息
 * 需 Header: Authorization: Bearer <access_token>
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Method Not Allowed']);
    exit;
}

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$accessToken = null;
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
    $accessToken = trim($m[1]);
}

function userinfoError($error, $desc = '', $httpCode = 401) {
    http_response_code($httpCode);
    header('WWW-Authenticate: Bearer error="' . $error . '"');
    $out = ['error' => $error];
    if ($desc) $out['error_description'] = $desc;
    echo json_encode($out);
    exit;
}

if (empty($accessToken)) {
    userinfoError('invalid_token', '缺少 Authorization: Bearer 或 token 为空');
}

$pdo = getDb();
if (!$pdo) {
    userinfoError('server_error', '服务暂时不可用', 500);
}

$stmt = $pdo->prepare("
    SELECT t.access_token, t.user_id, t.app_id, t.scope, t.expires_at
    FROM oauth_tokens t
    WHERE t.access_token = ? LIMIT 1
");
$stmt->execute([$accessToken]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    userinfoError('invalid_token', 'access_token 无效');
}

if (strtotime($token['expires_at']) < time()) {
    userinfoError('invalid_token', 'access_token 已过期');
}

$userId = (int) $token['user_id'];
$appId = (int) $token['app_id'];
$scope = $token['scope'] ?: 'openid profile email';

// 记录 user_app_grants（用户授权给某应用）
$scopesArr = array_filter(array_map('trim', explode(' ', $scope)));
$scopesJson = json_encode($scopesArr);
try {
    $pdo->prepare("
        INSERT INTO user_app_grants (user_id, app_id, scopes) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE scopes = VALUES(scopes), granted_at = NOW()
    ")->execute([$userId, $appId, $scopesJson]);
} catch (PDOException $e) {
    // 表可能无 ON DUPLICATE，尝试 INSERT IGNORE
    try {
        $pdo->prepare("INSERT IGNORE INTO user_app_grants (user_id, app_id, scopes) VALUES (?, ?, ?)")
            ->execute([$userId, $appId, $scopesJson]);
    } catch (PDOException $e2) {
        // 忽略
    }
}

// 查询用户信息
$stmt = $pdo->prepare("SELECT id, username, email, display_name, avatar, role, status FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    userinfoError('invalid_token', '用户不存在或已禁用');
}

$scopes = array_map('trim', explode(' ', $scope));
$hasOpenId = in_array('openid', $scopes);
$hasProfile = in_array('profile', $scopes);
$hasEmail = in_array('email', $scopes);

$sub = (string) $user['id'];
$result = [];

if ($hasOpenId) {
    $result['sub'] = $sub;
}
if ($hasProfile) {
    $result['name'] = $user['display_name'] ?: $user['username'];
    $result['preferred_username'] = $user['username'];
    $result['picture'] = $user['avatar'] ?: null;
}
if ($hasEmail) {
    $result['email'] = $user['email'] ?: null;
    $result['email_verified'] = !empty($user['email']);
}

// 始终返回 id（兼容）及基础字段
$result['id'] = (int) $user['id'];
$result['username'] = $user['username'];
$result['display_name'] = $user['display_name'] ?: $user['username'];
if (!isset($result['email'])) $result['email'] = $user['email'] ?: null;

echo json_encode($result);
