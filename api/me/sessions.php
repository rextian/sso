<?php
/**
 * REXTIAN SSO - GET /api/me/sessions
 * 当前用户会话列表
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
$currentSessionId = session_id();

$pdo = getDb();
if (!$pdo) {
    echo json_encode(['code' => 50000, 'message' => '服务暂时不可用', 'data' => null]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, user_id, ip, user_agent, expires_at, created_at
    FROM sessions
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sessions = [];
foreach ($rows as $r) {
    $isCurrent = ($r['id'] === $currentSessionId);
    $isExpired = strtotime($r['expires_at']) < time();
    $sessions[] = [
        'id' => $r['id'],
        'ip' => $r['ip'],
        'user_agent' => $r['user_agent'],
        'device' => parseUserAgent($r['user_agent']),
        'location' => null, // 可后续接入 IP 解析
        'expires_at' => $r['expires_at'],
        'created_at' => $r['created_at'],
        'is_current' => $isCurrent,
        'is_expired' => $isExpired,
    ];
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['sessions' => $sessions],
]);

function parseUserAgent($ua) {
    if (empty($ua)) return 'Unknown';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iPhone / iPad';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'Mac') !== false || stripos($ua, 'Macintosh') !== false) return 'Mac';
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Linux') !== false) return 'Linux';
    return 'Other';
}
