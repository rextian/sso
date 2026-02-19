<?php
/**
 * REXTIAN SSO - 获取当前用户的RADIUS会话记录
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

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM radius_sessions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT rs.*, rc.name as nas_name 
        FROM radius_sessions rs 
        LEFT JOIN radius_clients rc ON rs.nas_ip_address = rc.ip_address 
        WHERE rs.user_id = ? 
        ORDER BY rs.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'code' => 0,
        'message' => 'success',
        'data' => [
            'sessions' => $sessions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Get radius sessions failed: ' . $e->getMessage());
    echo json_encode(['code' => 500, 'message' => '获取失败', 'data' => null]);
}
