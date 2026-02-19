<?php
/**
 * REXTIAN SSO - 微信开放平台 网站应用 OAuth 登录回调
 */
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$loginUrl = rtrim($baseUrl, '/') . '/login.php';
$redirect = $_SESSION['social_login_redirect'] ?? 'index.php';

$error = $_GET['error'] ?? $_GET['errcode'] ?? null;
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if ($error || !$code || !$state) {
    $err = $error ?: 'invalid_callback';
    $rd = $_SESSION['social_login_redirect'] ?? '';
    unset($_SESSION['social_login_provider'], $_SESSION['social_login_state'], $_SESSION['social_login_at'], $_SESSION['social_login_redirect']);
    header('Location: ' . $loginUrl . '?error=' . urlencode($err) . ($rd ? '&redirect=' . urlencode($rd) : ''));
    exit;
}

if (empty($_SESSION['social_login_provider']) || $_SESSION['social_login_provider'] !== 'wechat') {
    header('Location: ' . $loginUrl . '?error=session_expired');
    exit;
}

if (!hash_equals($_SESSION['social_login_state'] ?? '', $state) || (time() - ($_SESSION['social_login_at'] ?? 0)) > 600) {
    unset($_SESSION['social_login_provider'], $_SESSION['social_login_state'], $_SESSION['social_login_at'], $_SESSION['social_login_redirect']);
    header('Location: ' . $loginUrl . '?error=session_expired');
    exit;
}

$appId = getSetting('wechat_app_id');
$appSecret = getSetting('wechat_app_secret');
if (!$appId || !$appSecret) {
    header('Location: ' . $loginUrl . '?error=config_missing');
    exit;
}

$tokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
    'appid' => $appId,
    'secret' => $appSecret,
    'code' => $code,
    'grant_type' => 'authorization_code',
]);
$tokenResp = @file_get_contents($tokenUrl);
$tokenData = json_decode($tokenResp, true);
$accessToken = $tokenData['access_token'] ?? null;
$openid = $tokenData['openid'] ?? null;
$unionid = $tokenData['unionid'] ?? null;

if (!$accessToken || !$openid) {
    $errMsg = $tokenData['errmsg'] ?? 'token_failed';
    header('Location: ' . $loginUrl . '?error=' . urlencode($errMsg));
    exit;
}

$providerUserId = (string) ($unionid ?: $openid);

unset($_SESSION['social_login_provider'], $_SESSION['social_login_state'], $_SESSION['social_login_at'], $_SESSION['social_login_redirect']);

$pdo = getDb();
if (!$pdo) {
    header('Location: ' . $loginUrl . '?error=server_error');
    exit;
}

$stmt = $pdo->prepare("SELECT user_id FROM user_connections WHERE provider = 'wechat' AND provider_user_id = ? LIMIT 1");
$stmt->execute([$providerUserId]);
$row = $stmt->fetch();
if (!$row) {
    header('Location: ' . $loginUrl . '?error=account_not_bound');
    exit;
}

$userId = (int) $row['user_id'];
$stmt = $pdo->prepare("SELECT id, username, role, status, mfa_enabled FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user || $user['status'] === 'banned') {
    header('Location: ' . $loginUrl . '?error=account_disabled');
    exit;
}

if (!empty($user['mfa_enabled'])) {
    $_SESSION['mfa_pending_user_id'] = $userId;
    $_SESSION['mfa_pending_remember'] = false;
    $_SESSION['mfa_pending_at'] = time();
    header('Location: ' . $loginUrl . '?mfa=1');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['role'] = $user['role'];
$_SESSION['username'] = $user['username'];

$sid = session_id();
$expiresAt = date('Y-m-d H:i:s', time() + 24 * 3600);
$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
try {
    $pdo->prepare("INSERT INTO sessions (id, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)")->execute([$sid, $userId, $ip, $ua, $expiresAt]);
} catch (PDOException $e) {}
$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$userId]);
auditLog('auth.login.success', $userId, null, ['provider' => 'wechat'], 'success');

header('Location: ' . (strpos($redirect, 'http') === 0 ? $redirect : rtrim($baseUrl, '/') . '/' . ltrim($redirect, '/')));
