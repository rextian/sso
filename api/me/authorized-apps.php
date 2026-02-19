<?php
/**
 * REXTIAN SSO - 获取当前用户授权的应用
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['code' => 401, 'message' => '未登录', 'data' => null]);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 500, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.*, at.created_at as authorized_at
        FROM oauth_clients c
        INNER JOIN oauth_access_tokens at ON c.id = at.client_id
        WHERE at.user_id = ? AND at.revoked = 0
        ORDER BY at.created_at DESC
    ");
    $stmt->execute([$userId]);
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'code' => 0,
        'message' => 'success',
        'data' => [
            'apps' => $apps
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Get authorized apps failed: ' . $e->getMessage());
    echo json_encode(['code' => 500, 'message' => '获取失败', 'data' => null]);
}
