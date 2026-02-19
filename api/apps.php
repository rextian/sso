<?php
/**
 * REXTIAN SSO - GET /api/apps.php
 * OAuth 应用列表
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

// 单应用详情
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, app_type, client_id, client_secret, name, description, icon, redirect_uris, ubnt_api_url, ubnt_api_key, ikuai_api_url, ikuai_token, status, created_at, updated_at FROM oauth_apps WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        echo json_encode(['code' => 40401, 'message' => '应用不存在', 'data' => null]);
        exit;
    }
    $redirectUris = $r['redirect_uris'];
    if (is_string($redirectUris)) {
        $redirectUris = json_decode($redirectUris, true) ?: [];
    }
    $isInteg = in_array($r['app_type'] ?? 'oauth', ['ubnt', 'ikuai']);
    $secret = $r['client_secret'];
    $maskedSecret = !$isInteg && strlen($secret) > 8 ? substr($secret, 0, 4) . '****' . substr($secret, -4) : ($isInteg ? '' : '****');
    echo json_encode([
        'code' => 0,
        'message' => 'success',
        'data' => [
            'id' => (int) $r['id'],
            'app_type' => $r['app_type'] ?? 'oauth',
            'client_id' => $r['client_id'],
            'client_secret' => $isInteg ? '' : $maskedSecret,
            'client_secret_full' => null,
            'ubnt_api_url' => $r['ubnt_api_url'] ?? '',
            'ubnt_api_key' => $r['ubnt_api_key'] ?? '',
            'ikuai_api_url' => $r['ikuai_api_url'] ?? '',
            'ikuai_token' => $r['ikuai_token'] ?? '',
            'name' => $r['name'],
            'description' => $r['description'],
            'icon' => $r['icon'],
            'redirect_uris' => $redirectUris ?: [],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at'],
        ],
    ]);
    exit;
}

// 应用列表
$stmt = $pdo->query("
    SELECT a.id, a.app_type, a.client_id, a.name, a.description, a.icon, a.status, a.created_at,
           COUNT(g.user_id) AS grant_count
    FROM oauth_apps a
    LEFT JOIN user_app_grants g ON a.id = g.app_id
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$apps = [];
foreach ($rows as $r) {
    $apps[] = [
        'id' => (int) $r['id'],
        'app_type' => $r['app_type'] ?? 'oauth',
        'client_id' => $r['client_id'],
        'name' => $r['name'],
        'description' => $r['description'],
        'icon' => $r['icon'],
        'status' => $r['status'],
        'grant_count' => (int) $r['grant_count'],
        'created_at' => $r['created_at'],
    ];
}

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['apps' => $apps],
]);
