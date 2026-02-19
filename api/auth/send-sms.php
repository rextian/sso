<?php
/**
 * REXTIAN SSO - POST /api/auth/send-sms
 * 发送短信验证码（登录用）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/sms_helper.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';

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
$phone = preg_replace('/\D/', '', trim($input['phone'] ?? ''));

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

$clientIp = getClientIp();
$smsLimit = max(2, min(20, (int) (getSetting('security_sms_rate_limit') ?: 5)));
if (!checkRateLimit('send_sms', $clientIp, $smsLimit, 60)) {
    jsonFail(42901, '请求过于频繁，请 1 分钟后再试');
}

if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
    jsonFail(40001, '手机号格式不正确');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

// 同一手机 60 秒内只能发一次
$stmt = $pdo->prepare("SELECT 1 FROM sms_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
$stmt->execute([$phone]);
if ($stmt->fetch()) {
    jsonFail(40002, '发送过于频繁，请 60 秒后再试');
}

$code = (string) random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 分钟

try {
    $pdo->prepare("INSERT INTO sms_codes (phone, code, expires_at) VALUES (?, ?, ?)")->execute([$phone, $code, $expiresAt]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'sms_codes') !== false) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sms_codes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            code VARCHAR(8) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone_expires (phone, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->prepare("INSERT INTO sms_codes (phone, code, expires_at) VALUES (?, ?, ?)")->execute([$phone, $code, $expiresAt]);
    } else {
        throw $e;
    }
}

$result = sendSmsCode($phone, $code);
if (!$result['success']) {
    auditLog('auth.sms.failed', null, $phone, ['reason' => $result['message'] ?? 'send_failed'], 'failed');
    jsonFail(50001, $result['message'] ?? '短信发送失败');
}

auditLog('auth.sms.sent', null, $phone, ['mock' => $result['mock'] ?? false], 'success');

$data = ['expires_in' => 300];
if (!empty($result['mock']) && getSetting('sms_mock_return_code') === '1') {
    $data['dev_code'] = $code;
}
echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => $data,
]);
