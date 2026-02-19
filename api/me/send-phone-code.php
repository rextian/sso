<?php
/**
 * REXTIAN SSO - POST /api/me/send-phone-code.php
 * 发送手机验证码（用于更换手机号）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/sms_helper.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireLogin();

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$phone = preg_replace('/\D/', '', trim($input['phone'] ?? ''));

if (strlen($phone) !== 11 || !preg_match('/^1[3-9]\d{9}$/', $phone)) {
    jsonFail(40001, '请输入有效的 11 位中国大陆手机号');
}

$userId = (int) $_SESSION['user_id'];
$clientIp = getClientIp();
if (!checkRateLimit('send_phone_code_' . $userId, $clientIp, 5, 60)) {
    jsonFail(42901, '发送过于频繁，请 1 分钟后再试');
}

$pdo = getDb();
if (!$pdo) jsonFail(50000, '服务暂时不可用');

// 检查新手机号是否已被占用
$stmt = $pdo->prepare("SELECT 1 FROM users WHERE phone = ? AND id != ? LIMIT 1");
$stmt->execute([$phone, $userId]);
if ($stmt->fetch()) {
    jsonFail(40002, '该手机号已被其他账号使用');
}

// 确保 verification_codes 表存在
$pdo->exec("CREATE TABLE IF NOT EXISTS verification_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    target VARCHAR(128) NOT NULL,
    code VARCHAR(8) NOT NULL,
    purpose VARCHAR(32) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_target (user_id, target, purpose),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 同一手机 60 秒内只能发一次
$stmt = $pdo->prepare("SELECT 1 FROM verification_codes WHERE user_id = ? AND target = ? AND purpose = 'phone_change' AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
$stmt->execute([$userId, $phone]);
if ($stmt->fetch()) {
    jsonFail(40002, '发送过于频繁，请 60 秒后再试');
}

$code = (string) random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 分钟
$pdo->prepare("INSERT INTO verification_codes (user_id, target, code, purpose, expires_at) VALUES (?, ?, ?, 'phone_change', ?)")->execute([$userId, $phone, $code, $expiresAt]);

$result = sendSmsCode($phone, $code);

if (!$result['success']) {
    auditLog('user.phone_code.failed', $userId, null, ['phone' => $phone], 'failed');
    jsonFail(50001, $result['message'] ?? '短信发送失败');
}

auditLog('user.phone_code.sent', $userId, null, ['phone' => substr($phone, 0, 3) . '****' . substr($phone, -4), 'mock' => $result['mock'] ?? false], 'success');

echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['expires_in' => 600]]);
