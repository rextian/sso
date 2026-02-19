<?php
/**
 * REXTIAN SSO - POST /api/auth/reset-password.php
 * 使用 token 重置密码
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
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
$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    jsonFail(40001, '无效的重置链接');
}

$minLen = max(6, min(32, (int) (getSetting('security_password_min_length') ?: 6)));
if (strlen($password) < $minLen) {
    jsonFail(40002, "密码至少需要 {$minLen} 位");
}

$pdo = getDb();
if (!$pdo) jsonFail(50000, '服务暂时不可用');

$stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    jsonFail(40003, '重置链接已过期或无效，请重新申请');
}

$userId = (int) $row['user_id'];
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$passwordHash, $userId]);
    $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?")->execute([$token]);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    jsonFail(50000, '操作失败，请重试');
}

auditLog('auth.password_reset.success', $userId, null, [], 'success');

echo json_encode(['code' => 0, 'message' => '密码已重置，请使用新密码登录', 'data' => null]);
