<?php
/**
 * REXTIAN SSO - 第三方登录入口
 * 使用: auth/login.php?provider=wechat|feishu|github|dingtalk|wecom
 * 重定向到各平台 OAuth 授权页，回调至 auth/login/{provider}/callback.php
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/settings_helper.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$loginUrl = rtrim($baseUrl, '/') . '/login.php';

$provider = strtolower(trim($_GET['provider'] ?? ''));
$allowed = ['wechat', 'feishu', 'github', 'dingtalk', 'wecom'];
if (!in_array($provider, $allowed)) {
    header('Location: ' . $loginUrl . '?error=unsupported');
    exit;
}

$callbackUrl = rtrim($baseUrl, '/') . '/auth/login/' . $provider . '/callback.php';
$state = bin2hex(random_bytes(16));
$_SESSION['social_login_provider'] = $provider;
$_SESSION['social_login_state'] = $state;
$_SESSION['social_login_at'] = time();
$_SESSION['social_login_redirect'] = $_GET['redirect'] ?? 'index.php';

if ($provider === 'wechat') {
    $appId = getSetting('wechat_app_id');
    if (!$appId) {
        header('Location: ' . $loginUrl . '?error=config_missing');
        exit;
    }
    $authUrl = 'https://open.weixin.qq.com/connect/qrconnect?' . http_build_query([
        'appid' => $appId,
        'redirect_uri' => $callbackUrl,
        'response_type' => 'code',
        'scope' => 'snsapi_login',
        'state' => $state,
    ]) . '#wechat_redirect';
    header('Location: ' . $authUrl);
    exit;
}

if ($provider === 'feishu') {
    $appId = getSetting('feishu_app_id');
    if (!$appId) {
        header('Location: ' . $loginUrl . '?error=config_missing');
        exit;
    }
    $authUrl = 'https://open.feishu.cn/open-apis/authen/v1/index?' . http_build_query([
        'app_id' => $appId,
        'redirect_uri' => $callbackUrl,
        'state' => $state,
    ]);
    header('Location: ' . $authUrl);
    exit;
}

if ($provider === 'github') {
    $clientId = getSetting('github_client_id');
    if (!$clientId) {
        header('Location: ' . $loginUrl . '?error=config_missing');
        exit;
    }
    $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $callbackUrl,
        'scope' => 'user:email',
        'state' => $state,
    ]);
    header('Location: ' . $authUrl);
    exit;
}

if ($provider === 'dingtalk') {
    $appKey = getSetting('dingtalk_appkey');
    if (!$appKey) {
        header('Location: ' . $loginUrl . '?error=config_missing');
        exit;
    }
    $authUrl = 'https://oapi.dingtalk.com/connect/qrconnect?' . http_build_query([
        'appid' => $appKey,
        'response_type' => 'code',
        'scope' => 'snsapi_login',
        'state' => $state,
        'redirect_uri' => $callbackUrl,
    ]);
    header('Location: ' . $authUrl);
    exit;
}

if ($provider === 'wecom') {
    $corpId = getSetting('wecom_corp_id');
    $agentId = getSetting('wecom_agent_id');
    if (!$corpId || !$agentId) {
        header('Location: ' . $loginUrl . '?error=config_missing');
        exit;
    }
    $authUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query([
        'appid' => $corpId,
        'redirect_uri' => $callbackUrl,
        'response_type' => 'code',
        'scope' => 'snsapi_base',
        'state' => $state,
        'agentid' => $agentId,
    ]) . '#wechat_redirect';
    header('Location: ' . $authUrl);
    exit;
}

header('Location: ' . $loginUrl . '?error=unsupported');
