<?php
/**
 * REXTIAN SSO - POST /api/settings/sms-test.php
 * 发送测试短信（已配置则调用阿里云，否则 Mock）
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/sms_helper.php';

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
$phone = preg_replace('/\D/', '', trim($input['phone'] ?? $input['test_phone'] ?? ''));

if ($phone === '' || !preg_match('/^1[3-9]\d{9}$/', $phone)) {
    jsonFail(40002, '请输入正确的 11 位手机号');
}

$code = (string) random_int(100000, 999999);
$result = sendSmsCode($phone, $code);

if (!$result['success']) {
    jsonFail(50001, $result['message'] ?? '短信发送失败');
}

$data = ['phone' => $phone, 'message' => $result['mock'] ? '测试短信已发送（Mock 模式）' : '测试短信已发送'];
if ($result['mock'] && getSetting('sms_mock_return_code') === '1') {
    $data['dev_code'] = $code;
    $data['message'] .= '，验证码：' . $code;
}
echo json_encode(['code' => 0, 'message' => 'success', 'data' => $data]);
