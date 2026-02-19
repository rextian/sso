<?php
/**
 * REXTIAN SSO - GET /api/dashboard/stats
 * 仪表盘统计数据
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

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

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeSessions = (int) $pdo->query("SELECT COUNT(*) FROM sessions WHERE expires_at > NOW()")->fetchColumn();
$ssoApps = (int) $pdo->query("SELECT COUNT(*) FROM oauth_apps")->fetchColumn();

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => [
        'total_users' => $totalUsers,
        'active_sessions' => $activeSessions,
        'sso_apps' => $ssoApps,
    ],
]);
