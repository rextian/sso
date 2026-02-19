<?php
/**
 * REXTIAN SSO - GitHub OAuth 绑定回调
 */
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$profileUrl = rtrim($baseUrl, '/') . '/user_profile.php';

$error = $_GET['error'] ?? null;
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

if (empty($_SESSION['connect_pending_provider']) || $_SESSION['connect_pending_provider'] !== 'github') {
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

$clientId = getSetting('github_client_id');
$clientSecret = getSetting('github_client_secret');
if (!$clientId || !$clientSecret) {
    header('Location: ' . $profileUrl . '?connect_error=config_missing');
    exit;
}

$callbackUrl = rtrim($baseUrl, '/') . '/auth/connect/github/callback.php';

$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $callbackUrl,
    ]),
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$tokenResp = curl_exec($ch);
$tokenHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenData = json_decode($tokenResp, true);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    header('Location: ' . $profileUrl . '?connect_error=token_failed');
    exit;
}

$ch2 = curl_init('https://api.github.com/user');
curl_setopt_array($ch2, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Accept: application/vnd.github.v3+json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$userResp = curl_exec($ch2);
curl_close($ch2);

$ghUser = json_decode($userResp, true);
$providerUserId = (string) ($ghUser['id'] ?? '');
$providerUsername = $ghUser['login'] ?? null;
$providerEmail = $ghUser['email'] ?? null;

if (!$providerUserId) {
    header('Location: ' . $profileUrl . '?connect_error=user_failed');
    exit;
}

if (empty($providerEmail)) {
    $ch3 = curl_init('https://api.github.com/user/emails');
    curl_setopt_array($ch3, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Accept: application/vnd.github.v3+json'],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $emailsResp = curl_exec($ch3);
    curl_close($ch3);
    $emails = json_decode($emailsResp, true);
    if (is_array($emails)) {
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['email'])) {
                $providerEmail = $e['email'];
                break;
            }
        }
        if (!$providerEmail && !empty($emails[0]['email'])) {
            $providerEmail = $emails[0]['email'];
        }
    }
}

unset($_SESSION['connect_pending_provider'], $_SESSION['connect_pending_state'], $_SESSION['connect_pending_at']);

$pdo = getDb();
if (!$pdo) {
    header('Location: ' . $profileUrl . '?connect_error=server_error');
    exit;
}

// 检查该 GitHub 账号是否已被其他用户绑定
$chk = $pdo->prepare("SELECT user_id FROM user_connections WHERE provider = 'github' AND provider_user_id = ? LIMIT 1");
$chk->execute([$providerUserId]);
$existing = $chk->fetch(PDO::FETCH_ASSOC);
if ($existing && (int) $existing['user_id'] !== $userId) {
    header('Location: ' . $profileUrl . '?connect_error=already_bound');
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_connections (user_id, provider, provider_user_id, provider_username, provider_email, access_token, connected_at)
        VALUES (?, 'github', ?, ?, ?, ?, NOW())
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

auditLog('user.connection.bind', $userId, $_SESSION['username'] ?? null, ['provider' => 'github', 'username' => $providerUsername], 'success');

header('Location: ' . $profileUrl . '?connect_success=github');
