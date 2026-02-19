<?php
/**
 * REXTIAN SSO - 刷新 Token API
 * 功能：使用 refresh_token 刷新新的 access_token
 * 
 * 使用方法：
 * POST /api/sso/refresh.php
 * Content-Type: application/json
 * {
 *   "refresh_token": "your_refresh_token_here",
 *   "client_id": "your_client_id",
 *   "client_secret": "your_client_secret"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$refreshToken = trim($input['refresh_token'] ?? '');
$clientId = trim($input['client_id'] ?? '');
$clientSecret = trim($input['client_secret'] ?? '');

if (empty($refreshToken)) {
    jsonResponse(40001, '缺少 refresh_token');
}

if (empty($clientId) || empty($clientSecret)) {
    jsonResponse(40002, '缺少 client_id 或 client_secret');
}

$pdo = getDb();
if (!$pdo) {
    jsonResponse(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("
    SELECT t.refresh_token, t.user_id, t.app_id, t.scope, a.client_id, a.client_secret
    FROM oauth_tokens t
    JOIN oauth_apps a ON t.app_id = a.id
    WHERE t.refresh_token = ? LIMIT 1
");
$stmt->execute([$refreshToken]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    jsonResponse(40101, 'refresh_token 无效');
}

if ($token['client_id'] !== $clientId || !hash_equals($token['client_secret'], $clientSecret)) {
    jsonResponse(40102, 'client_id 或 client_secret 错误');
}

$pdo->prepare("DELETE FROM oauth_tokens WHERE refresh_token = ?")->execute([$refreshToken]);

$newAccessToken = bin2hex(random_bytes(32));
$newRefreshToken = bin2hex(random_bytes(32));
$expiresIn = 3600;
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

try {
    $pdo->prepare("
        INSERT INTO oauth_tokens (access_token, refresh_token, user_id, app_id, scope, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $newAccessToken,
        $newRefreshToken,
        $token['user_id'],
        $token['app_id'],
        $token['scope'],
        $expiresAt
    ]);
} catch (PDOException $e) {
    jsonResponse(50001, '生成新 token 失败');
}

jsonResponse(0, 'success', [
    'access_token' => $newAccessToken,
    'refresh_token' => $newRefreshToken,
    'token_type' => 'Bearer',
    'expires_in' => $expiresIn
]);
