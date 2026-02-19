<?php
/**
 * REXTIAN SSO - 用户修改密码
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 405, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    echo json_encode(['code' => 401, 'message' => '未登录', 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    echo json_encode(['code' => 403, 'message' => '请求无效，请刷新页面重试', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';

if (empty($currentPassword)) {
    echo json_encode(['code' => 400, 'message' => '请输入当前密码', 'data' => null]);
    exit;
}

if (empty($newPassword) || strlen($newPassword) < 6) {
    echo json_encode(['code' => 400, 'message' => '新密码至少需要6个字符', 'data' => null]);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 500, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['code' => 404, 'message' => '用户不存在', 'data' => null]);
        exit;
    }
    
    if (!password_verify($currentPassword, $user['password_hash'])) {
        echo json_encode(['code' => 401, 'message' => '当前密码错误', 'data' => null]);
        exit;
    }
    
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$newPasswordHash, $userId]);
    
    auditLog('user.password.change', $userId, null, [], 'success');
    
    echo json_encode(['code' => 0, 'message' => '密码修改成功', 'data' => null]);
    
} catch (PDOException $e) {
    error_log('Change password failed: ' . $e->getMessage());
    echo json_encode(['code' => 500, 'message' => '修改失败，请稍后重试', 'data' => null]);
}
