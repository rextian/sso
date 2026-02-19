<?php
/**
 * REXTIAN SSO - 获取用户授权列表
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireAdmin();

$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 50000, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(max((int) ($_GET['limit'] ?? 20), 1), 100);
$search = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.display_name LIKE ? OR oa.name LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$whereSql = implode(' AND ', $where);
$offset = ($page - 1) * $limit;

try {
    $countSql = "SELECT COUNT(*) FROM user_app_grants uag 
                INNER JOIN users u ON uag.user_id = u.id 
                INNER JOIN oauth_apps oa ON uag.app_id = oa.id 
                WHERE $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT uag.id, uag.user_id, uag.app_id, uag.scopes, uag.granted_at,
                   u.username, u.email, u.display_name,
                   oa.name as app_name, oa.redirect_uris
            FROM user_app_grants uag
            INNER JOIN users u ON uag.user_id = u.id
            INNER JOIN oauth_apps oa ON uag.app_id = oa.id
            WHERE $whereSql
            ORDER BY uag.granted_at DESC
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $authorizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'code' => 0,
        'message' => 'success',
        'data' => [
            'authorizations' => $authorizations,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
        ],
    ]);
} catch (PDOException $e) {
    error_log('Get authorizations failed: ' . $e->getMessage());
    echo json_encode(['code' => 50000, 'message' => '获取失败', 'data' => null]);
}
