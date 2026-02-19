<?php
/**
 * REXTIAN SSO - POST /api/me/mfa/setup
 * 生成 TOTP 密钥，返回 secret 和 qr_url
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/totp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, username, email, mfa_enabled, mfa_secret FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    jsonFail(40401, '用户不存在');
}

if ($user['mfa_enabled']) {
    jsonFail(40003, 'MFA 已启用，请先关闭后再重新设置');
}

$secret = TotpHelper::generateSecret(16);
$account = $user['email'] ?: $user['username'];
$issuer = defined('SITE_NAME') ? SITE_NAME : 'REXTIAN ID';
$otpauthUrl = TotpHelper::getOtpAuthUrl($secret, $account, $issuer);

// 临时存入数据库（未验证前不启用），供 verify 时使用
$pdo->prepare("UPDATE users SET mfa_secret = ? WHERE id = ?")->execute([$secret, $userId]);

// 格式化 secret 为 4 字符一组便于手动输入
$secretFormatted = trim(chunk_split($secret, 4, ' '));

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'secret' => $secret,
        'secret_formatted' => $secretFormatted,
        'qr_url' => $otpauthUrl,
        'account' => $account,
    ],
]);
