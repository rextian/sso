<?php
/**
 * REXTIAN SSO - POST /api/users/create.php
 * 新建用户
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireAdmin();

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    echo json_encode(['code' => 40301, 'message' => '请求无效，请刷新页面重试', 'data' => null]);
    exit;
}

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$displayName = trim($input['display_name'] ?? '');
$role = $input['role'] ?? 'user';
$status = $input['status'] ?? 'active';

if ($username === '') {
    jsonFail(40001, '用户名不能为空');
}
$minLen = max(6, min(32, (int) (getSetting('security_password_min_length') ?: 6)));
if (strlen($password) < $minLen) {
    jsonFail(40002, '密码至少 ' . $minLen . ' 位');
}
$role = in_array($role, ['admin', 'user']) ? $role : 'user';
$status = in_array($status, ['active', 'banned']) ? $status : 'active';

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

// 校验 username 唯一
$stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    jsonFail(40003, '用户名已存在');
}

// 校验 email 唯一（非空时）
if ($email !== '') {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonFail(40004, '邮箱已被使用');
    }
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, email, phone, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$username, $email ?: null, $phone ?: null, $displayName ?: null, $passwordHash, $role, $status]);
$userId = (int) $pdo->lastInsertId();

$adminId = $_SESSION['user_id'] ?? 0;
auditLog('user.create', $adminId, null, ['target_user_id' => $userId, 'username' => $username], 'success');

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['id' => $userId, 'username' => $username],
]);
