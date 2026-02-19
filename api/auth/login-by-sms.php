<?php
/**
 * REXTIAN SSO - POST /api/auth/login-by-sms
 * 手机验证码登录
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$phone = preg_replace('/\D/', '', trim($input['phone'] ?? ''));
$code = trim($input['code'] ?? '');
$remember = !empty($input['remember'] ?? $_POST['remember'] ?? '');

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
    jsonFail(40001, '手机号格式不正确');
}

if (strlen($code) !== 6 || !ctype_digit($code)) {
    jsonFail(40002, '请输入 6 位验证码');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, code, expires_at FROM sms_codes WHERE phone = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$phone]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || $row['code'] !== $code) {
    auditLog('auth.login.failed', null, $phone, ['reason' => 'invalid_code'], 'failed');
    jsonFail(40003, '验证码错误');
}

if (strtotime($row['expires_at']) < time()) {
    auditLog('auth.login.failed', null, $phone, ['reason' => 'code_expired'], 'failed');
    jsonFail(40004, '验证码已过期，请重新获取');
}

$pdo->prepare("DELETE FROM sms_codes WHERE phone = ?")->execute([$phone]);

$stmt = $pdo->prepare("SELECT id, username, email, display_name, role, status, mfa_enabled FROM users WHERE phone = ? LIMIT 1");
$stmt->execute([$phone]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    auditLog('auth.login.failed', null, $phone, ['reason' => 'phone_not_registered'], 'failed');
    jsonFail(40005, '该手机号未注册，请先使用账号密码注册或联系管理员');
}

if ($user['status'] === 'banned') {
    auditLog('auth.login.failed', (int) $user['id'], $user['email'], ['reason' => 'banned'], 'failed');
    jsonFail(40006, '账户已被禁用');
}

if (!empty($user['mfa_enabled'])) {
    session_regenerate_id(true);
    $_SESSION['mfa_pending_user_id'] = (int) $user['id'];
    $_SESSION['mfa_pending_remember'] = $remember;
    $_SESSION['mfa_pending_at'] = time();
    echo json_encode([
        'code' => 0,
        'message' => 'success',
        'mfa_required' => true,
        'data' => ['username' => $user['username']],
    ]);
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['username'] = $user['username'];

$sessionId = session_id();
$sessionHours = max(1, min(168, (int) (getSetting('security_session_hours') ?: 24)));
$rememberHours = max(24, min(720, (int) (getSetting('security_remember_hours') ?: 168)));
$expiresHours = $remember ? $rememberHours : $sessionHours;
$expiresAt = date('Y-m-d H:i:s', time() + $expiresHours * 3600);
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = explode(',', $ip)[0];
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {
    $pdo->prepare("INSERT INTO sessions (id, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)")
        ->execute([$sessionId, $user['id'], $ip, $ua, $expiresAt]);
} catch (PDOException $e) {}

$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
auditLog('auth.login.success', (int) $user['id'], $user['email'], ['username' => $user['username'], 'method' => 'sms'], 'success');

if ($remember) {
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), time() + $expiresHours * 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
        'display_name' => $user['display_name'] ?: $user['username'],
        'role' => $user['role'],
    ],
]);
