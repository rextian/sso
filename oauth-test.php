<?php
/**
 * REXTIAN SSO - OAuth 2.0 完整流程测试页
 * 用作 redirect_uri 回调，接收 code 后换 token、拉取 userinfo
 * 使用前：在 OAuth 应用管理中把此页 URL 加入 redirect_uris
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$callbackUrl = rtrim($baseUrl, '/') . '/' . ltrim($_SERVER['SCRIPT_NAME'], '/');

// 发起授权前保存凭证并重定向（必须在任何输出前）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_and_redirect']) && !empty($_POST['client_id']) && !empty($_POST['client_secret']) && !empty($_POST['redirect_uri'])) {
    $_SESSION['oauth_test_client_id'] = trim($_POST['client_id']);
    $_SESSION['oauth_test_client_secret'] = trim($_POST['client_secret']);
    $redirectUri = trim($_POST['redirect_uri']);
    $authUrl = rtrim($baseUrl, '/') . '/oauth/authorize.php?' . http_build_query([
        'client_id' => $_SESSION['oauth_test_client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => bin2hex(random_bytes(8)),
    ]);
    header('Location: ' . $authUrl);
    exit;
}

$error = $_GET['error'] ?? null;
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

// 测试用 client_id / client_secret（发起授权时存 session，回调时优先用 URL 参数）
$testClientId = trim($_GET['client_id'] ?? $_POST['client_id'] ?? $_SESSION['oauth_test_client_id'] ?? '');
$testClientSecret = trim($_GET['client_secret'] ?? $_POST['client_secret'] ?? $_SESSION['oauth_test_client_secret'] ?? '');

$result = null;
$userinfo = null;
$tokenData = null;

if ($error) {
    $result = ['error' => $error, 'error_description' => $_GET['error_description'] ?? '用户取消授权'];
} elseif ($code && $testClientId && $testClientSecret) {
    // 用 code 换 token（redirect_uri 必须与授权时完全一致）
    $tokenUrl = rtrim($baseUrl, '/') . '/oauth/token.php';
    $postData = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $callbackUrl,
        'client_id' => $testClientId,
        'client_secret' => $testClientSecret,
    ];
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $tokenResp = curl_exec($ch);
    $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tokenData = json_decode($tokenResp, true);

    if ($tokenData && isset($tokenData['access_token'])) {
        // 拉取 userinfo
        $userinfoUrl = rtrim($baseUrl, '/') . '/oauth/userinfo.php';
        $ch2 = curl_init($userinfoUrl);
        curl_setopt_array($ch2, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenData['access_token']],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $userinfoResp = curl_exec($ch2);
        curl_close($ch2);
        $userinfo = json_decode($userinfoResp, true);
    }
    $result = $tokenData ?: ['raw' => $tokenResp, 'http_code' => $tokenHttpCode];
}

// 若仅有 code 但无 client 凭证，提示输入
$needInput = $code && (!$testClientId || !$testClientSecret) && !$error;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth 2.0 流程测试 | REXTIAN SSO</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-8 font-sans">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">OAuth 2.0 授权码模式 - 流程测试</h1>

        <?php if (!$code && !$error): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">1. 发起授权</h2>
            <p class="text-sm text-gray-600 mb-4">在下方填入应用的 client_id 和 client_secret，点击按钮跳转到授权页。凭证会存入 session，回调时自动使用。</p>
            <form method="post" action="oauth-test.php" class="space-y-4">
                <input type="hidden" name="save_and_redirect" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">client_id</label>
                    <input type="text" name="client_id" value="<?php echo htmlspecialchars($testClientId); ?>" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="client_xxx">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">client_secret</label>
                    <input type="password" name="client_secret" value="<?php echo htmlspecialchars($testClientSecret); ?>" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="应用密钥">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">redirect_uri（回调地址）</label>
                    <input type="text" name="redirect_uri" value="<?php echo htmlspecialchars($callbackUrl); ?>" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="本页完整 URL">
                </div>
                <button type="submit" class="px-4 py-2 bg-black text-white rounded text-sm font-medium">保存并前往授权</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">或直接发起授权（需已填入凭证）</h2>
            <form method="get" action="oauth/authorize.php" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">client_id</label>
                    <input type="text" name="client_id" value="<?php echo htmlspecialchars($testClientId); ?>" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="client_xxx">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">redirect_uri（回调地址）</label>
                    <input type="text" name="redirect_uri" value="<?php echo htmlspecialchars($callbackUrl); ?>" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="本页完整 URL">
                </div>
                <input type="hidden" name="response_type" value="code">
                <input type="hidden" name="scope" value="openid profile email">
                <input type="hidden" name="state" value="<?php echo bin2hex(random_bytes(8)); ?>">
                <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded text-sm font-medium">直接前往授权</button>
            </form>
            <p class="text-xs text-gray-400 mt-4">注意：redirect_uri 必须已加入该应用的白名单。</p>
        </div>
        <?php endif; ?>

        <?php if ($needInput): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-amber-800 mb-2">需要 client 凭证</h2>
            <p class="text-sm text-amber-700 mb-4">已收到 code，请提供 client_id 和 client_secret 以换取 token。</p>
            <form method="get" class="space-y-4">
                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                <input type="hidden" name="state" value="<?php echo htmlspecialchars($state); ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">client_id</label>
                    <input type="text" name="client_id" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">client_secret</label>
                    <input type="password" name="client_secret" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded text-sm font-medium">换取 Token</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($result): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">2. Token 响应</h2>
            <?php if (isset($result['error'])): ?>
                <pre class="bg-red-50 p-4 rounded text-sm text-red-800 overflow-x-auto"><?php echo htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
            <?php else: ?>
                <pre class="bg-gray-50 p-4 rounded text-sm overflow-x-auto"><?php echo htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($userinfo): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">3. UserInfo 响应</h2>
            <pre class="bg-gray-50 p-4 rounded text-sm overflow-x-auto"><?php echo htmlspecialchars(json_encode($userinfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
        </div>
        <?php endif; ?>

        <div class="text-sm text-gray-500">
            <p>回调 URL：<code class="bg-gray-100 px-1 rounded"><?php echo htmlspecialchars($callbackUrl); ?></code></p>
            <p class="mt-2">请将此 URL 加入 OAuth 应用的 redirect_uris 白名单后再测试。</p>
        </div>
    </div>
</body>
</html>
