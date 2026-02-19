<?php
/**
 * REXTIAN SSO - POST /api/auth/forgot-password.php
 * 发送密码重置邮件
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/email_helper.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim(strtolower($input['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonFail(40001, '请输入有效的邮箱地址');
}

$clientIp = getClientIp();
if (!checkRateLimit('forgot_password_' . $clientIp, $clientIp, 3, 600)) {
    jsonFail(42901, '请求过于频繁，请 10 分钟后再试');
}

$pdo = getDb();
if (!$pdo) jsonFail(50000, '服务暂时不可用');

// 确保 password_reset_tokens 表存在
$pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token VARCHAR(64) PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 查找用户（仅支持通过邮箱注册的账号）
$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ? AND password_hash IS NOT NULL AND status = 'active' LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 无论是否找到用户，都返回成功（防止邮箱枚举）
if (!$user) {
    auditLog('auth.password_reset.request', null, $email, ['found' => false], 'success');
    echo json_encode(['code' => 0, 'message' => '若该邮箱已注册，您将收到重置链接', 'data' => null]);
    exit;
}

// 同一用户 60 秒内只能发一次
$stmt = $pdo->prepare("SELECT 1 FROM password_reset_tokens WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
$stmt->execute([$user['id']]);
if ($stmt->fetch()) {
    jsonFail(40002, '发送过于频繁，请 60 秒后再试');
}

// 删除该用户之前的未过期 token（可选，保持唯一活跃 token）
$pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$user['id']]);

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 小时有效
$pdo->prepare("INSERT INTO password_reset_tokens (token, user_id, expires_at) VALUES (?, ?, ?)")->execute([$token, $user['id'], $expiresAt]);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$resetUrl = rtrim($baseUrl, '/') . '/reset_password.php?token=' . $token;
$siteName = getSetting('site_name') ?: 'REXTIAN ID';

$subject = '重置密码 - ' . $siteName;
$bodyHtml = "<p>您好，{$user['username']}：</p><p>您正在申请重置密码，请点击下方链接完成操作（1 小时内有效）：</p><p><a href=\"{$resetUrl}\" style=\"color:#000;text-decoration:underline;\">{$resetUrl}</a></p><p>如非本人操作，请忽略此邮件。</p>";
$bodyText = "您好，{$user['username']}：\n\n您正在申请重置密码，请访问以下链接完成操作（1 小时内有效）：\n{$resetUrl}\n\n如非本人操作，请忽略此邮件。";

$result = sendEmail($email, $subject, $bodyHtml, $bodyText);

if (!$result['success']) {
    $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?")->execute([$token]);
    auditLog('auth.password_reset.request', $user['id'], $email, ['error' => $result['message'] ?? 'send_failed'], 'failed');
    jsonFail(50001, $result['message'] ?? '邮件发送失败');
}

auditLog('auth.password_reset.request', $user['id'], $email, ['mock' => $result['mock'] ?? false], 'success');

echo json_encode(['code' => 0, 'message' => '若该邮箱已注册，您将收到重置链接', 'data' => null]);
