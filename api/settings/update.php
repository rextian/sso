<?php
/**
 * REXTIAN SSO - POST /api/settings/update.php
 * 按 key 更新配置，支持模块
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireAdmin();

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!is_array($input)) {
    jsonFail(40001, '无效的请求体');
}

$allowedKeys = [
    'site_name', 'site_url',
    'sms_provider', 'sms_key', 'sms_secret', 'sms_test_phone', 'sms_sign_name', 'sms_template_code', 'sms_mock_return_code',
    'sms_tencent_secret_id', 'sms_tencent_secret_key', 'sms_tencent_sdk_app_id', 'sms_tencent_sign_name', 'sms_tencent_template_id',
    'sms_jdcloud_access_key', 'sms_jdcloud_secret_key', 'sms_jdcloud_sign_id', 'sms_jdcloud_template_id',
    'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name', 'smtp_test_email',
    'audit_retention_days',
    'security_login_rate_limit', 'security_sms_rate_limit', 'security_password_min_length',
    'security_session_hours', 'security_remember_hours', 'security_session_cookie_secure',
    'wechat_app_id', 'wechat_app_secret', 'wechat_enabled',
    'github_client_id', 'github_client_secret', 'github_enabled',
    'dingtalk_appkey', 'dingtalk_app_secret', 'dingtalk_enabled',
    'feishu_app_id', 'feishu_app_secret', 'feishu_enabled',
    'wecom_corp_id', 'wecom_agent_id', 'wecom_secret', 'wecom_enabled',
];

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

$updated = [];
foreach ($input as $key => $value) {
    if (!in_array($key, $allowedKeys)) continue;
    $key = preg_replace('/[^a-z0-9_]/', '', $key);
    if ($key === '') continue;
    $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    $stmt->execute([$key, $value]);
    $updated[] = $key;
}

if (!empty($updated)) {
    $adminId = $_SESSION['user_id'] ?? 0;
    auditLog('system.config.update', $adminId, null, ['keys' => $updated], 'success');
}

// 当 security_session_cookie_secure 变更时，写入覆盖文件供 config 读取（config 加载早于 DB）
if (isset($input['security_session_cookie_secure'])) {
    $dataDir = dirname(__DIR__, 2) . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    $file = $dataDir . '/security_session_secure.php';
    $val = (is_bool($input['security_session_cookie_secure']) && $input['security_session_cookie_secure'])
        || $input['security_session_cookie_secure'] === '1' || $input['security_session_cookie_secure'] === true;
    @file_put_contents($file, '<?php return ' . ($val ? 'true' : 'false') . ';');
}

echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['updated' => $updated]]);
