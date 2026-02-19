<?php
/**
 * REXTIAN SSO - POST /api/me/verify-email.php
 * 验证邮箱验证码并更新用户邮箱
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
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
$code = trim($input['code'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonFail(40001, '请输入有效的邮箱地址');
}
if (strlen($code) !== 6 || !ctype_digit($code)) {
    jsonFail(40002, '请输入 6 位数字验证码');
}

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) jsonFail(50000, '服务暂时不可用');

// 校验验证码
$stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE user_id = ? AND target = ? AND purpose = 'email_change' AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId, $email, $code]);
$row = $stmt->fetch();
if (!$row) {
    auditLog('user.email_change.failed', $userId, $email, ['reason' => 'invalid_code'], 'failed');
    jsonFail(40003, '验证码错误或已过期');
}

// 删除已使用的验证码
$pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$row['id']]);

// 再次检查邮箱是否被占用（防止并发）
$stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ? AND id != ? LIMIT 1");
$stmt->execute([$email, $userId]);
if ($stmt->fetch()) {
    jsonFail(40004, '该邮箱已被其他账号使用');
}

// 更新用户邮箱
$pdo->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?")->execute([$email, $userId]);

auditLog('user.email_change', $userId, $email, [], 'success');

// 返回更新后的用户信息
$stmt = $pdo->prepare("SELECT id, username, email, phone, display_name, avatar, role, status, mfa_enabled FROM users WHERE id = ?");
$stmt->execute([$userId]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'id' => (int) $r['id'],
        'email' => $r['email'],
    ],
]);
