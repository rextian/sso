<?php
/**
 * REXTIAN SSO - POST /api/settings/email-test.php
 * 发送测试邮件
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/email_helper.php';

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
    echo json_encode(['code' => 40301, 'message' => '请求无效，请刷新页面重试', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim($input['email'] ?? $input['test_email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonFail(40002, '请输入正确的邮箱地址');
}

$subject = 'REXTIAN SSO 测试邮件';
$bodyHtml = '<p>这是一封测试邮件。</p><p>如果您收到此邮件，说明 SMTP 配置正确。</p><p style="color:#999;font-size:12px;">REXTIAN ID</p>' . date('Y-m-d H:i:s');

$result = sendEmail($email, $subject, $bodyHtml);

if (!$result['success']) {
    $msg = $result['message'] ?? '邮件发送失败';
    jsonFail(50001, $msg);
}

$data = ['email' => $email, 'message' => $result['mock'] ? '测试邮件已发送（Mock 模式）' : '测试邮件已发送'];
echo json_encode(['code' => 0, 'message' => 'success', 'data' => $data]);
