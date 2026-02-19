<?php
/**
 * REXTIAN SSO - GET /api/me/connections
 * 当前用户第三方账号绑定列表
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

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

$stmt = $pdo->prepare("SELECT provider, provider_username, provider_email, connected_at FROM user_connections WHERE user_id = ? ORDER BY connected_at DESC");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$connections = [];
foreach ($rows as $r) {
    $connections[] = [
        'provider' => $r['provider'],
        'username' => $r['provider_username'],
        'email' => $r['provider_email'],
        'connected_at' => $r['connected_at'],
    ];
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['connections' => $connections],
]);
