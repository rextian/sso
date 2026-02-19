<?php
/**
 * REXTIAN SSO - GET /api/audit-logs/export.php?format=csv
 * 导出审计日志 CSV
 */
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

requireAdmin();

$pdo = getDb();
if (!$pdo) {
    header('Content-Type: application/json');
    echo json_encode(['code' => 50000, 'message' => '服务暂时不可用']);
    exit;
}

$event = trim($_GET['event'] ?? '');
$user = trim($_GET['user'] ?? '');
$status = trim($_GET['status'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$limit = min(max((int) ($_GET['limit'] ?? 1000), 1), 10000);

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
$params[] = $limit;

$sql = "SELECT a.trace_id, a.event, a.user_email, a.ip, a.status, a.created_at, u.username
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $whereSql
        ORDER BY a.created_at DESC
        LIMIT ?";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'audit-logs-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$eventLabels = [
    'auth.login.success' => '登录成功',
    'auth.login.failed' => '登录失败',
    'auth.logout' => '退出登录',
    'auth.sms.sent' => '短信验证码已发送',
    'auth.sms.failed' => '短信发送失败',
    'auth.login.mfa_failed' => 'MFA 验证失败',
    'user.create' => '创建用户',
    'user.update' => '更新用户',
    'user.delete' => '删除用户',
    'user.profile.update' => '更新个人资料',
    'user.mfa.enabled' => '启用 MFA',
    'user.mfa.verify.failed' => 'MFA 验证失败',
    'user.mfa.disabled' => '关闭 MFA',
    'user.mfa.disable.failed' => '关闭 MFA 失败',
    'user.session.revoke' => '撤销会话',
    'user.connection.bind' => '绑定第三方账号',
    'user.connection.unbind' => '解除第三方绑定',
    'app.create' => '创建应用',
    'app.update' => '更新应用',
    'app.delete' => '删除应用',
    'app.reset_secret' => '重置应用密钥',
    'system.config.update' => '更新系统配置',
    'audit.cleanup' => '清理过期日志',
];

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM
fputcsv($out, ['追踪ID', '操作', '用户', 'IP', '状态', '时间']);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['trace_id'],
        $eventLabels[$r['event']] ?? $r['event'],
        $r['username'] ?: $r['user_email'] ?: '-',
        $r['ip'] ?: '-',
        $r['status'] === 'success' ? '成功' : ($r['status'] === 'failed' ? '失败' : $r['status']),
        $r['created_at'],
    ]);
}

fclose($out);
