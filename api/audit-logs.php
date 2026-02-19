<?php
/**
 * REXTIAN SSO - GET /api/audit-logs.php
 * 审计日志列表：page, limit, event, user
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
$event = trim($_GET['event'] ?? '');
$user = trim($_GET['user'] ?? '');
$status = trim($_GET['status'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$where = ['1=1'];
$params = [];

if ($event !== '') {
    $where[] = 'a.event LIKE ?';
    $params[] = '%' . $event . '%';
}

if ($user !== '') {
    $where[] = '(a.user_email LIKE ? OR a.user_id IN (SELECT id FROM users WHERE username LIKE ? OR email LIKE ?))';
    $term = '%' . $user . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($status !== '' && in_array($status, ['success', 'failed'])) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}

if ($startDate !== '') {
    $where[] = 'a.created_at >= ?';
    $params[] = $startDate . ' 00:00:00';
}

if ($endDate !== '') {
    $where[] = 'a.created_at <= ?';
    $params[] = $endDate . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);
$offset = ($page - 1) * $limit;

$countSql = "SELECT COUNT(*) FROM audit_logs a WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$sql = "SELECT a.id, a.trace_id, a.event, a.user_id, a.user_email, a.ip, a.payload, a.status, a.created_at,
        u.username
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $whereSql
        ORDER BY a.created_at DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
foreach ($rows as $r) {
    $logs[] = [
        'id' => (int) $r['id'],
        'trace_id' => $r['trace_id'],
        'event' => $r['event'],
        'user_id' => $r['user_id'] ? (int) $r['user_id'] : null,
        'user_email' => $r['user_email'],
        'user_display' => $r['username'] ?: $r['user_email'] ?: '-',
        'ip' => $r['ip'],
        'payload' => $r['payload'] ? json_decode($r['payload'], true) : null,
        'status' => $r['status'],
        'created_at' => $r['created_at'],
    ];
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'logs' => $logs,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
    ],
]);
