<?php
/**
 * REXTIAN SSO - 取消用户授权
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

requireAdmin();

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    echo json_encode(['code' => 40300, 'message' => '请求无效，请刷新页面重试', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$authId = (int) ($input['id'] ?? 0);

if (empty($authId)) {
    echo json_encode(['code' => 40000, 'message' => '参数错误', 'data' => null]);
    exit;
}

$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 50000, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT uag.*, u.username, oa.name as app_name 
                          FROM user_app_grants uag 
                          INNER JOIN users u ON uag.user_id = u.id 
                          INNER JOIN oauth_apps oa ON uag.app_id = oa.id 
                          WHERE uag.id = ? LIMIT 1");
    $stmt->execute([$authId]);
    $auth = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auth) {
        $pdo->rollBack();
        echo json_encode(['code' => 40400, 'message' => '授权记录不存在', 'data' => null]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM user_app_grants WHERE id = ?");
    $stmt->execute([$authId]);

    $stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE user_id = ? AND app_id = ?");
    $stmt->execute([$auth['user_id'], $auth['app_id']]);

    $pdo->commit();

    auditLog('oauth.authorization.revoke', $_SESSION['user_id'] ?? null, $auth['user_id'], [
        'auth_id' => $authId,
        'username' => $auth['username'],
        'app_name' => $auth['app_name']
    ], 'success');

    echo json_encode(['code' => 0, 'message' => '取消授权成功', 'data' => null]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Revoke authorization failed: ' . $e->getMessage());
    echo json_encode(['code' => 50000, 'message' => '操作失败，请稍后重试', 'data' => null]);
}
