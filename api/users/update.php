<?php
/**
 * REXTIAN SSO - POST /api/users/update.php
 * 更新用户
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
$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    jsonFail(40001, '用户 ID 无效');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, username, email, role, status FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    jsonFail(40401, '用户不存在');
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$isEditingSelf = ($id === $currentUserId);

$displayName = trim($input['display_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$role = $input['role'] ?? null;
$status = $input['status'] ?? null;
$password = $input['password'] ?? '';

// 禁止对自己：降级为普通用户、或禁用账户（防止锁死）
if ($isEditingSelf) {
    if ($role === 'user' && ($user['role'] ?? '') === 'admin') {
        jsonFail(40006, '不能将自己的角色降为普通用户');
    }
    if ($status === 'banned') {
        jsonFail(40006, '不能禁用当前登录账户');
    }
}

$updates = [];
$params = [];

$updates[] = 'display_name = ?';
$params[] = $displayName ?: null;
if ($email !== $user['email']) {
    if ($email !== '') {
        $chk = $pdo->prepare("SELECT 1 FROM users WHERE email = ? AND id != ? LIMIT 1");
        $chk->execute([$email, $id]);
        if ($chk->fetch()) {
            jsonFail(40004, '邮箱已被使用');
        }
    }
    $updates[] = 'email = ?';
    $params[] = $email ?: null;
}
$updates[] = 'phone = ?';
$params[] = $phone ?: null;

if ($role !== null && in_array($role, ['admin', 'user'])) {
    $updates[] = 'role = ?';
    $params[] = $role;
}
if ($status !== null && in_array($status, ['active', 'banned'])) {
    $updates[] = 'status = ?';
    $params[] = $status;
}
if ($password !== '') {
    $minLen = max(6, min(32, (int) (getSetting('security_password_min_length') ?: 6)));
    if (strlen($password) < $minLen) {
        jsonFail(40002, '密码至少 ' . $minLen . ' 位');
    }
    $updates[] = 'password_hash = ?';
    $params[] = password_hash($password, PASSWORD_DEFAULT);
}

if (empty($updates)) {
    echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['id' => $id]]);
    exit;
}

$params[] = $id;
$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
$pdo->prepare($sql)->execute($params);

$adminId = $_SESSION['user_id'] ?? 0;
auditLog('user.update', $adminId, null, ['target_user_id' => $id, 'username' => $user['username']], 'success');

echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['id' => $id]]);
