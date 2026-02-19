<?php
/**
 * REXTIAN SSO - GET /api/me
 * 当前登录用户信息（需登录）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireLogin();

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 50000, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.phone, u.display_name, u.avatar, u.role, u.status, u.mfa_enabled, u.last_login_at, u.created_at,
           COUNT(g.app_id) AS app_count
    FROM users u
    LEFT JOIN user_app_grants g ON u.id = g.user_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$userId]);
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
