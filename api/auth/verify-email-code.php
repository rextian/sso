<?php
/**
 * REXTIAN SSO - POST /api/auth/verify-email-code
 * 验证邮箱验证码
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

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
$code = trim($input['code'] ?? '');
$type = trim($input['type'] ?? 'register');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonFail(40001, '请输入有效的电子邮箱');
}

if (empty($code)) {
    jsonFail(40002, '请输入验证码');
}

if (!in_array($type, ['register', 'reset_password'])) {
    jsonFail(40003, '无效的验证类型');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, code, expires_at FROM email_codes WHERE email = ? AND type = ? AND used = 0 ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email, $type]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    jsonFail(40004, '验证码无效或已过期');
}

if (strtotime($row['expires_at']) < time()) {
    jsonFail(40005, '验证码已过期，请重新获取');
}

if (!hash_equals($row['code'], $code)) {
    jsonFail(40006, '验证码错误');
}

$verifyToken = bin2hex(random_bytes(32));
$tokenExpiresAt = date('Y-m-d H:i:s', time() + 1800);

try {
    $pdo->prepare("UPDATE email_codes SET code = ?, expires_at = ? WHERE id = ?")->execute([$verifyToken, $tokenExpiresAt, $row['id']]);
} catch (PDOException $e) {
    jsonFail(50001, '验证失败: ' . $e->getMessage());
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'token' => $verifyToken
    ]
]);
