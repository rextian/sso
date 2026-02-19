<?php
/**
 * REXTIAN SSO - POST /api/auth/login
 * 账号密码登录（含 MFA 校验流程）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? $_POST['username'] ?? '');
$password = $input['password'] ?? $_POST['password'] ?? '';
$remember = !empty($input['remember'] ?? $_POST['remember'] ?? '');

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

$clientIp = getClientIp();
$loginLimit = max(3, min(50, (int) (getSetting('security_login_rate_limit') ?: 10)));
if (!checkRateLimit('login', $clientIp, $loginLimit, 60)) {
    jsonFail(42901, '登录尝试过于频繁，请 1 分钟后再试');
}

if ($username === '' || $password === '') {
    jsonFail(40001, '请输入用户名和密码');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

// 支持 username / email / phone 登录，需查询 mfa_enabled
$stmt = $pdo->prepare("SELECT id, username, email, display_name, password_hash, role, status, mfa_enabled FROM users WHERE (username = ? OR email = ? OR phone = ?) LIMIT 1");
$stmt->execute([$username, $username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
    auditLog('auth.login.failed', null, $username, ['reason' => 'invalid_credentials'], 'failed');
    jsonFail(40001, '用户名或密码错误');
}

if ($user['status'] === 'banned') {
    auditLog('auth.login.failed', (int) $user['id'], $user['email'], ['reason' => 'banned'], 'failed');
    jsonFail(40003, '账户已被禁用');
}

if ($user['status'] === 'pending') {
    auditLog('auth.login.failed', (int) $user['id'], $user['email'], ['reason' => 'pending'], 'failed');
    jsonFail(40004, '账户正在审核中，请等待管理员审核');
}

// 若已开启 MFA，不直接登录，要求输入验证码
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

// 防固定会话攻击
session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['username'] = $user['username'];

// 写入 sessions 表
$sessionId = session_id();
$sessionHours = max(1, min(168, (int) (getSetting('security_session_hours') ?: 24)));
$rememberHours = max(24, min(720, (int) (getSetting('security_remember_hours') ?: 168)));
$expiresHours = $remember ? $rememberHours : $sessionHours;
$expiresAt = date('Y-m-d H:i:s', time() + $expiresHours * 3600);
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = explode(',', $ip)[0];
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {
    $ins = $pdo->prepare("INSERT INTO sessions (id, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$sessionId, $user['id'], $ip, $ua, $expiresAt]);
} catch (PDOException $e) {
    // 忽略重复等
}

// 更新 last_login_at
$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

// 审计：登录成功
auditLog('auth.login.success', (int) $user['id'], $user['email'], ['username' => $user['username']], 'success');

// 设置 cookie 过期（remember）
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
