<?php
/**
 * REXTIAN SSO - RADIUS 认证 API
 * 用于WiFi Portal认证
 * 
 * 使用方法：
 * POST /api/radius/auth.php
 * Content-Type: application/json
 * {
 *   "username": "user123",
 *   "password": "password",
 *   "nas_ip": "192.168.1.1",
 *   "nas_port": "1",
 *   "calling_station_id": "AA:BB:CC:DD:EE:FF",
 *   "called_station_id": "FF:EE:DD:CC:BB:AA"
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';

function jsonResponse($code, $message, $data = null) {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, 'Method Not Allowed');
}

$enabled = getSetting('radius_enabled');
if (!$enabled) {
    jsonResponse(403, 'RADIUS认证未启用');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$nasIp = trim($input['nas_ip'] ?? '');
$nasPort = trim($input['nas_port'] ?? '');
$callingStationId = trim($input['calling_station_id'] ?? '');
$calledStationId = trim($input['called_station_id'] ?? '');

if (empty($username) || empty($password)) {
    jsonResponse(400, '用户名和密码不能为空');
}

if (empty($nasIp)) {
    jsonResponse(400, 'NAS IP不能为空');
}

$pdo = getDb();
if (!$pdo) {
    jsonResponse(500, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, secret, status FROM radius_clients WHERE ip_address = ? LIMIT 1");
$stmt->execute([$nasIp]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    jsonResponse(403, '未授权的NAS客户端');
}

if ($client['status'] !== 'active') {
    jsonResponse(403, 'NAS客户端已禁用');
}

$stmt = $pdo->prepare("SELECT id, username, password_hash, status, mfa_enabled FROM users WHERE username = ? OR email = ? LIMIT 1");
$stmt->execute([$username, $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    jsonResponse(401, '用户名或密码错误');
}

if ($user['status'] !== 'active') {
    jsonResponse(403, '用户已被禁用');
}

if (!password_verify($password, $user['password_hash'])) {
    jsonResponse(401, '用户名或密码错误');
}

$requireMfa = getSetting('radius_require_mfa');
if ($requireMfa && $user['mfa_enabled']) {
    jsonResponse(403, '需要MFA验证，请通过网页登录');
}

$sessionTimeout = (int) getSetting('radius_default_session_timeout') ?: 86400;
$idleTimeout = (int) getSetting('radius_default_idle_timeout') ?: 3600;

$acctSessionId = bin2hex(random_bytes(16));

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO radius_sessions (
        user_id, username, nas_ip_address, nas_port_id, 
        calling_station_id, called_station_id, acct_session_id, 
        acct_status_type, session_start
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Start', NOW())");
    $stmt->execute([
        $user['id'],
        $user['username'],
        $nasIp,
        $nasPort,
        $callingStationId,
        $calledStationId,
        $acctSessionId
    ]);
    
    $sessionId = $pdo->lastInsertId();
    
    $pdo->commit();
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('RADIUS session create failed: ' . $e->getMessage());
}

jsonResponse(0, 'Access-Accept', [
    'user' => [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'status' => $user['status']
    ],
    'session' => [
        'session_id' => $acctSessionId,
        'session_timeout' => $sessionTimeout,
        'idle_timeout' => $idleTimeout
    ]
]);
