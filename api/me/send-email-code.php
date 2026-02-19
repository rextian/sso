<?php
/**
 * REXTIAN SSO - POST /api/me/send-email-code.php
 * 发送邮箱验证码（用于更换邮箱）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/email_helper.php';
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
$email = trim(strtolower($input['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonFail(40001, '请输入有效的邮箱地址');
}

$userId = (int) $_SESSION['user_id'];
$clientIp = getClientIp();
if (!checkRateLimit('send_email_code_' . $userId, $clientIp, 5, 60)) {
    jsonFail(42901, '发送过于频繁，请 1 分钟后再试');
}

$pdo = getDb();
if (!$pdo) jsonFail(50000, '服务暂时不可用');

// 检查新邮箱是否已被占用
$stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ? AND id != ? LIMIT 1");
$stmt->execute([$email, $userId]);
if ($stmt->fetch()) {
    jsonFail(40002, '该邮箱已被其他账号使用');
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

// 同一邮箱 60 秒内只能发一次
$stmt = $pdo->prepare("SELECT 1 FROM verification_codes WHERE user_id = ? AND target = ? AND purpose = 'email_change' AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
$stmt->execute([$userId, $email]);
if ($stmt->fetch()) {
    jsonFail(40002, '发送过于频繁，请 60 秒后再试');
}

$code = (string) random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 分钟
$pdo->prepare("INSERT INTO verification_codes (user_id, target, code, purpose, expires_at) VALUES (?, ?, ?, 'email_change', ?)")->execute([$userId, $email, $code, $expiresAt]);

$subject = '验证您的邮箱 - REXTIAN ID';
$body = "您的验证码是：<strong>{$code}</strong>，10 分钟内有效。如非本人操作请忽略。";
$result = sendEmail($email, $subject, $body);

if (!$result['success']) {
    auditLog('user.email_code.failed', $userId, $email, [], 'failed');
    jsonFail(50001, $result['message'] ?? '邮件发送失败');
}

auditLog('user.email_code.sent', $userId, $email, ['mock' => $result['mock'] ?? false], 'success');

echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['expires_in' => 600]]);
