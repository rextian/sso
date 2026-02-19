<?php
/**
 * REXTIAN SSO - POST /api/me/mfa/verify
 * 校验 6 位 TOTP 码，通过则设置 mfa_enabled=1
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
$code = trim($input['code'] ?? $input['mfa_code'] ?? $_POST['mfa_code'] ?? '');

if ($code === '') {
    jsonFail(40001, '请输入 6 位验证码');
}

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, mfa_secret, mfa_enabled FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['mfa_secret'])) {
    jsonFail(40002, '请先调用 setup 生成密钥');
}

if ($user['mfa_enabled']) {
    jsonFail(40003, 'MFA 已启用');
}

if (!TotpHelper::verify($user['mfa_secret'], $code)) {
    auditLog('user.mfa.verify.failed', $userId, $_SESSION['username'] ?? null, [], 'failed');
    jsonFail(40004, '验证码错误');
}

$pdo->prepare("UPDATE users SET mfa_enabled = 1 WHERE id = ?")->execute([$userId]);
auditLog('user.mfa.enabled', $userId, $_SESSION['username'] ?? null, [], 'success');

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['mfa_enabled' => true],
]);
