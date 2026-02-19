<?php
/**
 * REXTIAN SSO - Portal 认证对接 API
 * 功能：验证 access_token 并返回用户信息（简化版，适合内部门户使用）
 * 
 * 使用方法：
 * POST /api/sso/verify.php
 * Content-Type: application/json
 * {
 *   "access_token": "your_access_token_here"
 * }
 * 
 * 或者：
 * GET /api/sso/verify.php?access_token=your_access_token_here
 * 
 * 或者 Header: Authorization: Bearer <access_token>
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';

function jsonResponse($code, $message, $data = null) {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getAccessToken() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim($m[1]);
    }
    if (!empty($_POST['access_token'])) {
        return trim($_POST['access_token']);
    }
    if (!empty($_GET['access_token'])) {
        return trim($_GET['access_token']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['access_token'])) {
        return trim($input['access_token']);
    }
    return null;
}

$accessToken = getAccessToken();

if (empty($accessToken)) {
    jsonResponse(40101, '缺少 access_token');
}

$pdo = getDb();
if (!$pdo) {
    jsonResponse(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("
    SELECT t.access_token, t.user_id, t.app_id, t.scope, t.expires_at,
           a.name as app_name, a.client_id
    FROM oauth_tokens t
    LEFT JOIN oauth_apps a ON t.app_id = a.id
    WHERE t.access_token = ? LIMIT 1
");
$stmt->execute([$accessToken]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    jsonResponse(40102, 'access_token 无效');
}

if (strtotime($token['expires_at']) < time()) {
    jsonResponse(40103, 'access_token 已过期');
}

$userId = (int) $token['user_id'];

$stmt = $pdo->prepare("
    SELECT id, username, email, phone, display_name, avatar, role, status, 
           mfa_enabled, last_login_at, created_at
    FROM users WHERE id = ? LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    jsonResponse(40401, '用户不存在');
}

if ($user['status'] !== 'active') {
    jsonResponse(40301, '用户已被禁用');
}

jsonResponse(0, 'success', [
    'user' => [
        'id' => (int) $user['id'],
        'uid' => (string) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'display_name' => $user['display_name'] ?: $user['username'],
        'avatar' => $user['avatar'],
        'role' => $user['role'],
        'is_admin' => $user['role'] === 'admin',
        'mfa_enabled' => (bool) $user['mfa_enabled'],
        'last_login_at' => $user['last_login_at'],
        'created_at' => $user['created_at']
    ],
    'app' => [
        'id' => (int) $token['app_id'],
        'name' => $token['app_name'],
        'client_id' => $token['client_id']
    ],
    'token' => [
        'scope' => $token['scope'],
        'expires_at' => $token['expires_at'],
        'expires_in' => strtotime($token['expires_at']) - time()
    ]
]);
