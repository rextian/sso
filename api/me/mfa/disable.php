<?php
/**
 * REXTIAN SSO - POST /api/me/mfa/disable
 * 关闭 MFA（需验证密码或 TOTP）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/totp.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$password = $input['password'] ?? '';
$code = trim($input['code'] ?? $input['mfa_code'] ?? '');

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, password_hash, mfa_secret, mfa_enabled FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    jsonFail(40401, '用户不存在');
}

if (!$user['mfa_enabled']) {
    jsonFail(40002, 'MFA 未启用');
}

$verified = false;
if ($password !== '' && password_verify($password, $user['password_hash'] ?? '')) {
    $verified = true;
}
if (!$verified && $code !== '' && TotpHelper::verify($user['mfa_secret'], $code)) {
    $verified = true;
}

if (!$verified) {
    auditLog('user.mfa.disable.failed', $userId, $_SESSION['username'] ?? null, [], 'failed');
    jsonFail(40003, '密码或验证码错误');
}

$pdo->prepare("UPDATE users SET mfa_enabled = 0, mfa_secret = NULL WHERE id = ?")->execute([$userId]);
auditLog('user.mfa.disabled', $userId, $_SESSION['username'] ?? null, [], 'success');

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['mfa_enabled' => false],
]);
