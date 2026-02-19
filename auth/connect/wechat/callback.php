<?php
/**
 * REXTIAN SSO - 微信开放平台 网站应用 OAuth 绑定回调
 */
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$profileUrl = rtrim($baseUrl, '/') . '/user_profile.php';

$error = $_GET['error'] ?? $_GET['errcode'] ?? null;
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if ($error) {
    header('Location: ' . $profileUrl . '?connect_error=' . urlencode($error));
    exit;
}

if (!$code || !$state) {
    header('Location: ' . $profileUrl . '?connect_error=invalid_callback');
    exit;
}

if (empty($_SESSION['connect_pending_provider']) || $_SESSION['connect_pending_provider'] !== 'wechat') {
    header('Location: ' . $profileUrl . '?connect_error=session_expired');
    exit;
}

if (!hash_equals($_SESSION['connect_pending_state'] ?? '', $state)) {
    header('Location: ' . $profileUrl . '?connect_error=invalid_state');
    exit;
}

if ((time() - ($_SESSION['connect_pending_at'] ?? 0)) > 600) {
    unset($_SESSION['connect_pending_provider'], $_SESSION['connect_pending_state'], $_SESSION['connect_pending_at']);
    header('Location: ' . $profileUrl . '?connect_error=session_expired');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!$userId) {
    header('Location: ' . dirname($profileUrl) . '/login.php?redirect=' . urlencode($profileUrl));
    exit;
}

$appId = getSetting('wechat_app_id');
$appSecret = getSetting('wechat_app_secret');
if (!$appId || !$appSecret) {
    header('Location: ' . $profileUrl . '?connect_error=config_missing');
    exit;
}

$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
    'appid' => $appId,
    'secret' => $appSecret,
    'code' => $code,
    'grant_type' => 'authorization_code',
]);
$tokenResp = @file_get_contents($url);
$tokenData = json_decode($tokenResp, true);
$accessToken = $tokenData['access_token'] ?? null;
$openid = $tokenData['openid'] ?? null;
$unionid = $tokenData['unionid'] ?? null;

if (!$accessToken || !$openid) {
    $errMsg = $tokenData['errmsg'] ?? 'token_failed';
    header('Location: ' . $profileUrl . '?connect_error=' . urlencode($errMsg));
    exit;
}

$userInfoUrl = 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
    'access_token' => $accessToken,
    'openid' => $openid,
    'lang' => 'zh_CN',
]);
$userResp = file_get_contents($userInfoUrl);
$wxUser = json_decode($userResp, true);
$providerUserId = (string) ($unionid ?: $openid);
$providerUsername = $wxUser['nickname'] ?? null;
$providerEmail = null;

unset($_SESSION['connect_pending_provider'], $_SESSION['connect_pending_state'], $_SESSION['connect_pending_at']);

$pdo = getDb();
if (!$pdo) {
    header('Location: ' . $profileUrl . '?connect_error=server_error');
    exit;
}

$chk = $pdo->prepare("SELECT user_id FROM user_connections WHERE provider = 'wechat' AND provider_user_id = ? LIMIT 1");
$chk->execute([$providerUserId]);
$existing = $chk->fetch(PDO::FETCH_ASSOC);
if ($existing && (int) $existing['user_id'] !== $userId) {
    header('Location: ' . $profileUrl . '?connect_error=already_bound');
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_connections (user_id, provider, provider_user_id, provider_username, provider_email, access_token, connected_at)
        VALUES (?, 'wechat', ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE provider_username = VALUES(provider_username), provider_email = VALUES(provider_email), access_token = VALUES(access_token)
    ");
    $stmt->execute([$userId, $providerUserId, $providerUsername, $providerEmail, $accessToken]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'user_connections') !== false) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_connections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL,
            provider_user_id VARCHAR(128) NOT NULL,
            provider_username VARCHAR(128),
            provider_email VARCHAR(128),
            access_token VARCHAR(512),
            refresh_token VARCHAR(512),
            connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_provider (user_id, provider),
            UNIQUE KEY uk_provider_uid (provider, provider_user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt->execute([$userId, $providerUserId, $providerUsername, $providerEmail, $accessToken]);
    } else {
        throw $e;
    }
}

auditLog('user.connection.bind', $userId, $_SESSION['username'] ?? null, ['provider' => 'wechat', 'username' => $providerUsername], 'success');

header('Location: ' . $profileUrl . '?connect_success=wechat');
