<?php
/**
 * REXTIAN SSO - POST /api/auth/send-email-code
 * 发送邮箱验证码（用于注册）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';
require_once dirname(__DIR__, 2) . '/includes/email_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim($input['email'] ?? '');
$type = trim($input['type'] ?? 'register');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonFail(40001, '请输入有效的电子邮箱');
}

if (!in_array($type, ['register', 'reset_password'])) {
    jsonFail(40002, '无效的验证类型');
}

$clientIp = getClientIp();
if (!checkRateLimit('email_send', $clientIp, 5, 60)) {
    jsonFail(42901, '发送过于频繁，请1分钟后再试');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

if ($type === 'register') {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonFail(40003, '该邮箱已被注册');
    }
}

$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + 600);

try {
    $pdo->prepare("DELETE FROM email_codes WHERE email = ? AND type = ?")->execute([$email, $type]);
    $pdo->prepare("INSERT INTO email_codes (email, code, type, used, expires_at) VALUES (?, ?, ?, 0, ?)")->execute([$email, $code, $type, $expiresAt]);
} catch (PDOException $e) {
    jsonFail(50001, '保存验证码失败: ' . $e->getMessage());
}

$subject = $type === 'register' ? '【REXTIAN ID】注册验证码' : '【REXTIAN ID】重置密码验证码';
$bodyHtml = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2 style="color: #111; font-weight: 600;">' . ($type === 'register' ? '欢迎注册 REXTIAN ID' : '重置您的密码') . '</h2>
    <p style="color: #6b7280; font-size: 14px; line-height: 1.6;">
        您的验证码是：
    </p>
    <div style="background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; margin: 16px 0;">
        <span style="font-size: 28px; font-weight: bold; letter-spacing: 8px; color: #111;">' . $code . '</span>
    </div>
    <p style="color: #6b7280; font-size: 12px;">
        验证码有效期为 10 分钟，请勿将验证码泄露给他人。
    </p>
</div>';

$result = sendEmail($email, $subject, $bodyHtml);

if (!$result['success']) {
    jsonFail(50002, '发送失败，请稍后重试');
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'mock' => !empty($result['mock']),
        'code' => !empty($result['mock']) ? $code : null
    ]
]);
