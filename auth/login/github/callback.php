<?php
/**
 * REXTIAN SSO - GitHub 第三方登录回调
 */
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$loginUrl = rtrim($baseUrl, '/') . '/login.php';
$redirect = $_SESSION['social_login_redirect'] ?? 'index.php';

$error = $_GET['error'] ?? null;
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if ($error || !$code || !$state) {
    $err = $error ?: 'invalid_callback';
    $rd = $_SESSION['social_login_redirect'] ?? '';
    unset($_SESSION['social_login_provider'], $_SESSION['social_login_state'], $_SESSION['social_login_at'], $_SESSION['social_login_redirect']);
    header('Location: ' . $loginUrl . '?error=' . urlencode($err) . ($rd ? '&redirect=' . urlencode($rd) : ''));
    exit;
}

if (empty($_SESSION['social_login_provider']) || $_SESSION['social_login_provider'] !== 'github') {
    header('Location: ' . $loginUrl . '?error=session_expired');
    exit;
}

if (!hash_equals($_SESSION['social_login_state'] ?? '', $state) || (time() - ($_SESSION['social_login_at'] ?? 0)) > 600) {
    unset($_SESSION['social_login_provider'], $_SESSION['social_login_state'], $_SESSION['social_login_at'], $_SESSION['social_login_redirect']);
    header('Location: ' . $loginUrl . '?error=session_expired');
    exit;
}

$clientId = getSetting('github_client_id');
$clientSecret = getSetting('github_client_secret');
if (!$clientId || !$clientSecret) {
    header('Location: ' . $loginUrl . '?error=config_missing');
    exit;
}

$callbackUrl = rtrim($baseUrl, '/') . '/auth/login/github/callback.php';
$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['client_id' => $clientId, 'client_secret' => $clientSecret, 'code' => $code, 'redirect_uri' => $callbackUrl]),
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$tokenData = json_decode(curl_exec($ch), true);
curl_close($ch);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    header('Location: ' . $loginUrl . '?error=token_failed');
    exit;
}

$ch2 = curl_init('https://api.github.com/user');
curl_setopt_array($ch2, [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken], CURLOPT_RETURNTRANSFER => true]);
$ghUser = json_decode(curl_exec($ch2), true);
curl_close($ch2);
$providerUserId = (string) ($ghUser['id'] ?? '');

if (!$providerUserId) {
    header('Location: ' . $loginUrl . '?error=user_failed');
    exit;
}

unset($_SESSION['social_login_provider'], $_SESSION['social_login_state'], $_SESSION['social_login_at'], $_SESSION['social_login_redirect']);

$pdo = getDb();
if (!$pdo) {
    header('Location: ' . $loginUrl . '?error=server_error');
    exit;
}

$stmt = $pdo->prepare("SELECT user_id FROM user_connections WHERE provider = 'github' AND provider_user_id = ? LIMIT 1");
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
auditLog('auth.login.success', $userId, null, ['provider' => 'github'], 'success');

header('Location: ' . (strpos($redirect, 'http') === 0 ? $redirect : rtrim($baseUrl, '/') . '/' . ltrim($redirect, '/')));
