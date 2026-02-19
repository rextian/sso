<?php
/**
 * REXTIAN SSO - POST /api/me/connections/{provider}/bind
 * 发起第三方绑定，返回授权 URL
 * 调用方式: POST api/me/connections/bind.php?provider=github
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireLogin();

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    echo json_encode(['code' => 40301, 'message' => '请求无效，请刷新页面重试', 'data' => null]);
    exit;
}

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

$provider = strtolower(trim($_GET['provider'] ?? ''));
$allowed = ['github', 'wechat', 'feishu', 'dingtalk', 'wecom'];
if (!in_array($provider, $allowed)) {
    jsonFail(40001, '不支持的平台');
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$callbackUrl = rtrim($baseUrl, '/') . '/auth/connect/' . $provider . '/callback.php';
$state = bin2hex(random_bytes(16));
$_SESSION['connect_pending_provider'] = $provider;
$_SESSION['connect_pending_state'] = $state;
$_SESSION['connect_pending_at'] = time();

if ($provider === 'github') {
    $clientId = getSetting('github_client_id');
    $clientSecret = getSetting('github_client_secret');
    if (!$clientId || !$clientSecret) {
        jsonFail(40002, 'GitHub 未配置，请联系管理员在系统设置中配置');
    }
    $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $callbackUrl,
        'scope' => 'user:email',
        'state' => $state,
    ]);
    echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['auth_url' => $authUrl]]);
    exit;
}

if ($provider === 'wechat') {
    $appId = getSetting('wechat_app_id');
    $appSecret = getSetting('wechat_app_secret');
    if (!$appId || !$appSecret) {
        jsonFail(40002, '微信未配置，请联系管理员在系统设置中配置 App ID 和 Secret');
    }
    $authUrl = 'https://open.weixin.qq.com/connect/qrconnect?' . http_build_query([
        'appid' => $appId,
        'redirect_uri' => $callbackUrl,
        'response_type' => 'code',
        'scope' => 'snsapi_login',
        'state' => $state,
    ]) . '#wechat_redirect';
    echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['auth_url' => $authUrl]]);
    exit;
}

if ($provider === 'feishu') {
    $appId = getSetting('feishu_app_id');
    $appSecret = getSetting('feishu_app_secret');
    if (!$appId || !$appSecret) {
        jsonFail(40002, '飞书未配置，请联系管理员在系统设置中配置 App ID 和 App Secret');
    }
    $authUrl = 'https://open.feishu.cn/open-apis/authen/v1/index?' . http_build_query([
        'app_id' => $appId,
        'redirect_uri' => $callbackUrl,
        'state' => $state,
    ]);
    echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['auth_url' => $authUrl]]);
    exit;
}

if ($provider === 'dingtalk') {
    $appKey = getSetting('dingtalk_appkey');
    $appSecret = getSetting('dingtalk_app_secret');
    if (!$appKey || !$appSecret) {
        jsonFail(40002, '钉钉未配置，请联系管理员在系统设置中配置 AppKey 和 AppSecret');
    }
    $authUrl = 'https://oapi.dingtalk.com/connect/qrconnect?' . http_build_query([
        'appid' => $appKey,
        'response_type' => 'code',
        'scope' => 'snsapi_login',
        'state' => $state,
        'redirect_uri' => $callbackUrl,
    ]);
    echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['auth_url' => $authUrl]]);
    exit;
}

if ($provider === 'wecom') {
    $corpId = getSetting('wecom_corp_id');
    $agentId = getSetting('wecom_agent_id');
    $secret = getSetting('wecom_secret');
    if (!$corpId || !$agentId || !$secret) {
        jsonFail(40002, '企业微信未配置，请联系管理员在系统设置中配置 Corp ID、Agent ID 和 Secret');
    }
    $authUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query([
        'appid' => $corpId,
        'redirect_uri' => $callbackUrl,
        'response_type' => 'code',
        'scope' => 'snsapi_base',
        'state' => $state,
        'agentid' => $agentId,
    ]) . '#wechat_redirect';
    echo json_encode(['code' => 0, 'message' => 'success', 'data' => ['auth_url' => $authUrl]]);
    exit;
}

jsonFail(40001, '不支持的平台');
