<?php
/**
 * REXTIAN SSO - GET /api/users
 * 用户列表：page, limit, search, filter (all|admin|banned)
 * 单用户：GET /api/users.php?id=123
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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// 单用户详情
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.email, u.phone, u.display_name, u.avatar, u.role, u.status, u.mfa_enabled, u.last_login_at, u.created_at,
            COUNT(g.app_id) AS app_count
            FROM users u
            LEFT JOIN user_app_grants g ON u.id = g.user_id
            WHERE u.id = ?
            GROUP BY u.id");
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        echo json_encode(['code' => 40401, 'message' => '用户不存在', 'data' => null]);
        exit;
    }
    echo json_encode([
        'code' => 0,
        'message' => 'success',
        'data' => [
            'id' => (int) $r['id'],
            'username' => $r['username'],
            'email' => $r['email'],
            'phone' => $r['phone'],
            'display_name' => $r['display_name'] ?: $r['username'],
            'avatar' => $r['avatar'],
            'role' => $r['role'],
            'status' => $r['status'],
            'mfa_enabled' => (bool) $r['mfa_enabled'],
            'app_count' => (int) $r['app_count'],
            'last_login_at' => $r['last_login_at'],
            'created_at' => $r['created_at'],
        ],
    ]);
    exit;
}

// 用户列表
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(max((int) ($_GET['limit'] ?? 20), 1), 100);
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$sort = $_GET['sort'] ?? 'last_login';

$allowedFilter = ['all', 'pending', 'admin', 'banned'];
if (!in_array($filter, $allowedFilter)) {
    $filter = 'all';
}

$where = ['1=1'];
$params = [];

if ($filter === 'admin') {
    $where[] = "u.role = 'admin'";
} elseif ($filter === 'banned') {
    $where[] = "u.status = 'banned'";
} elseif ($filter === 'pending') {
    $where[] = "u.status = 'pending'";
}

if ($search !== '') {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$orderBy = 'u.last_login_at IS NULL, u.last_login_at DESC';
if ($sort === 'created') {
    $orderBy = 'u.created_at DESC';
} elseif ($sort === 'username') {
    $orderBy = 'u.username ASC';
}

$whereSql = implode(' AND ', $where);
$offset = ($page - 1) * $limit;

// 总数
$countSql = "SELECT COUNT(*) FROM users u WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// 列表
$sql = "SELECT u.id, u.username, u.email, u.phone, u.display_name, u.role, u.status, u.last_login_at,
        COUNT(g.app_id) AS app_count
        FROM users u
        LEFT JOIN user_app_grants g ON u.id = g.user_id
        WHERE $whereSql
        GROUP BY u.id
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = [];
foreach ($rows as $r) {
    $users[] = [
        'id' => (int) $r['id'],
        'username' => $r['username'],
        'email' => $r['email'],
        'phone' => $r['phone'],
        'display_name' => $r['display_name'] ?: $r['username'],
        'role' => $r['role'],
        'status' => $r['status'],
        'app_count' => (int) $r['app_count'],
        'last_login_at' => $r['last_login_at'],
    ];
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'users' => $users,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
    ],
]);
