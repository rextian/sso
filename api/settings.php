<?php
/**
 * REXTIAN SSO - GET /api/settings.php
 * 获取全部配置
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

$stmt = $pdo->query("SELECT `key`, `value` FROM settings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$data = [];
foreach ($rows as $r) {
    $data[$r['key']] = $r['value'];
}
// security_session_cookie_secure 可能仅存在于 data 文件，同步到返回数据
if (!isset($data['security_session_cookie_secure'])) {
    $overrideFile = dirname(__DIR__) . '/data/security_session_secure.php';
    $data['security_session_cookie_secure'] = (file_exists($overrideFile) && include $overrideFile) ? '1' : '0';
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => $data,
]);
