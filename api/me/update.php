<?php
/**
 * REXTIAN SSO - PUT /api/me
 * 更新当前用户个人资料（display_name 等）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = (int) $_SESSION['user_id'];

$displayName = trim($input['display_name'] ?? '');
$displayName = $displayName !== '' ? $displayName : null;

$avatar = null;
if (array_key_exists('avatar', $input)) {
    $val = trim($input['avatar'] ?? '');
    if ($val === '') {
        $avatar = null;
    } elseif (strpos($val, 'uploads/avatars/') === 0 || preg_match('#^https?://\S+$#i', $val)) {
        $avatar = $val;
    } else {
        jsonFail(40001, '头像地址格式无效');
    }
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$updates = [];
$params = [];
if ($displayName !== null || array_key_exists('display_name', $input)) {
    $updates[] = 'display_name = ?';
    $params[] = $displayName;
}
if ($avatar !== null || array_key_exists('avatar', $input)) {
    $updates[] = 'avatar = ?';
    $params[] = $avatar;
}
if (!empty($updates)) {
    $params[] = $userId;
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?");
    $stmt->execute($params);
}

$logPayload = [];
if (array_key_exists('display_name', $input)) $logPayload['display_name'] = $displayName;
if (array_key_exists('avatar', $input)) $logPayload['avatar'] = $avatar === null ? '(cleared)' : '(updated)';
if (!empty($logPayload)) auditLog('user.profile.update', $userId, $_SESSION['username'] ?? null, $logPayload, 'success');

// 返回更新后的用户信息
$stmt = $pdo->prepare("SELECT id, username, email, phone, display_name, avatar, role, status, mfa_enabled, last_login_at, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'id' => (int) $r['id'],
        'username' => $r['username'],
        'email' => $r['email'],
        'phone' => $r['phone'],
        'display_name' => $r['display_name'] ?: $r['username'],
        'avatar' => $r['avatar'],
        'role' => $r['role'],
        'status' => $r['status'],
        'mfa_enabled' => (bool) $r['mfa_enabled'],
        'last_login_at' => $r['last_login_at'],
        'created_at' => $r['created_at'],
    ],
]);
