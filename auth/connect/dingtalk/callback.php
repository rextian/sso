<?php
/**
 * REXTIAN SSO - 钉钉扫码登录 OAuth 绑定回调
 */
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$profileUrl = rtrim($baseUrl, '/') . '/user_profile.php';

$error = $_GET['error'] ?? $_GET['authCode'] ?? null;
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (isset($_GET['error']) && $_GET['error'] !== '') {
    header('Location: ' . $profileUrl . '?connect_error=' . urlencode($_GET['error']));
    exit;
}

if (!$code || !$state) {
    header('Location: ' . $profileUrl . '?connect_error=invalid_callback');
    exit;
}

if (empty($_SESSION['connect_pending_provider']) || $_SESSION['connect_pending_provider'] !== 'dingtalk') {
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

$appKey = getSetting('dingtalk_appkey');
$appSecret = getSetting('dingtalk_app_secret');
if (!$appKey || !$appSecret) {
    header('Location: ' . $profileUrl . '?connect_error=config_missing');
    exit;
}

$timestamp = (string) (time() * 1000);
$stringToSign = $timestamp . "\n" . $appSecret;
$signature = base64_encode(hash_hmac('sha256', $stringToSign, $appSecret, true));

$apiUrl = 'https://oapi.dingtalk.com/sns/getuserinfo_bycode?' . http_build_query([
    'accessKey' => $appKey,
    'timestamp' => $timestamp,
    'signature' => $signature,
]);
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['tmp_auth_code' => $code]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);
$providerUserId = (string) ($data['user_info']['unionid'] ?? $data['user_info']['openid'] ?? '');
$providerUsername = $data['user_info']['nick'] ?? null;

unset($_SESSION['connect_pending_provider'], $_SESSION['connect_pending_state'], $_SESSION['connect_pending_at']);

if (!$providerUserId || ($data['errcode'] ?? 0) !== 0) {
    $errMsg = $data['errmsg'] ?? 'user_failed';
    header('Location: ' . $profileUrl . '?connect_error=' . urlencode($errMsg));
    exit;
}

$pdo = getDb();
if (!$pdo) {
    header('Location: ' . $profileUrl . '?connect_error=server_error');
    exit;
}

$chk = $pdo->prepare("SELECT user_id FROM user_connections WHERE provider = 'dingtalk' AND provider_user_id = ? LIMIT 1");
$chk->execute([$providerUserId]);
$existing = $chk->fetch(PDO::FETCH_ASSOC);
if ($existing && (int) $existing['user_id'] !== $userId) {
    header('Location: ' . $profileUrl . '?connect_error=already_bound');
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_connections (user_id, provider, provider_user_id, provider_username, provider_email, connected_at)
        VALUES (?, 'dingtalk', ?, ?, NULL, NOW())
        ON DUPLICATE KEY UPDATE provider_username = VALUES(provider_username)
    ");
    $stmt->execute([$userId, $providerUserId, $providerUsername]);
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
        $stmt->execute([$userId, $providerUserId, $providerUsername]);
    } else {
        throw $e;
    }
}

auditLog('user.connection.bind', $userId, $_SESSION['username'] ?? null, ['provider' => 'dingtalk', 'username' => $providerUsername], 'success');

header('Location: ' . $profileUrl . '?connect_success=dingtalk');
