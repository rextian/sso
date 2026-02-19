<?php
/**
 * REXTIAN SSO - POST /api/auth/mfa-verify
 * 登录流程中的 MFA 验证（完成登录）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/totp.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$code = trim($input['code'] ?? $input['mfa_code'] ?? '');

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

if ($code === '') {
    jsonFail(40001, '请输入 6 位验证码');
}

$userId = (int) ($_SESSION['mfa_pending_user_id'] ?? 0);
$remember = !empty($_SESSION['mfa_pending_remember'] ?? false);
$pendingAt = (int) ($_SESSION['mfa_pending_at'] ?? 0);

// MFA 待验证状态 5 分钟有效
if (!$userId || (time() - $pendingAt) > 300) {
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_remember'], $_SESSION['mfa_pending_at']);
    jsonFail(40002, '会话已过期，请重新登录');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, username, email, role, mfa_secret FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['mfa_secret'])) {
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_remember'], $_SESSION['mfa_pending_at']);
    jsonFail(40003, '用户状态异常，请重新登录');
}

if (!TotpHelper::verify($user['mfa_secret'], $code)) {
    auditLog('auth.login.mfa_failed', $userId, $user['email'], [], 'failed');
    jsonFail(40004, '验证码错误');
}

// 清除 MFA 待验证状态
unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_remember'], $_SESSION['mfa_pending_at']);

// 防固定会话攻击
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
        ->execute([$sessionId, $userId, $ip, $ua, $expiresAt]);
} catch (PDOException $e) {}

$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$userId]);
auditLog('auth.login.success', $userId, $user['email'], ['username' => $user['username'], 'mfa' => true], 'success');

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
        'role' => $user['role'],
    ],
]);
