<?php
/**
 * REXTIAN SSO - OAuth 2.0 授权端点
 * GET: 展示授权页（未登录则跳转登录）
 * POST: 处理允许/取消授权
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$clientId = trim($_GET['client_id'] ?? $_POST['client_id'] ?? '');
$redirectUri = trim($_GET['redirect_uri'] ?? $_POST['redirect_uri'] ?? '');
$responseType = trim($_GET['response_type'] ?? $_POST['response_type'] ?? '');
$scope = trim($_GET['scope'] ?? $_POST['scope'] ?? 'openid profile email');
$state = trim($_GET['state'] ?? $_POST['state'] ?? '');

function oauthError($redirectUri, $error, $state, $desc = '') {
    $params = ['error' => $error];
    if ($state) $params['state'] = $state;
    if ($desc) $params['error_description'] = $desc;
    $sep = strpos($redirectUri, '?') !== false ? '&' : '?';
    header('Location: ' . $redirectUri . $sep . http_build_query($params));
    exit;
}

function oauthSuccess($redirectUri, $code, $state) {
    $params = ['code' => $code];
    if ($state) $params['state'] = $state;
    $sep = strpos($redirectUri, '?') !== false ? '&' : '?';
    header('Location: ' . $redirectUri . $sep . http_build_query($params));
    exit;
}

// 校验必填参数
if ($clientId === '' || $redirectUri === '' || $responseType === '') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo 'invalid_request: client_id, redirect_uri, response_type 必填';
    exit;
}

if ($responseType !== 'code') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo 'unsupported_response_type: 仅支持 response_type=code';
    exit;
}

$pdo = getDb();
if (!$pdo) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'server_error';
    exit;
}

// 查询应用
$stmt = $pdo->prepare("SELECT id, name, redirect_uris FROM oauth_apps WHERE client_id = ? AND status IN ('live','dev') LIMIT 1");
$stmt->execute([$clientId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    oauthError($redirectUri, 'invalid_client', $state, '应用不存在或已禁用');
}

$redirectUris = $app['redirect_uris'];
if (is_string($redirectUris)) {
    $redirectUris = json_decode($redirectUris, true) ?: [];
}

// 校验 redirect_uri 在白名单中（OAuth 2.0 要求完全匹配）
$uriMatch = in_array($redirectUri, $redirectUris, true);
if (!$uriMatch) {
    // 兼容末尾斜杠差异
    foreach ($redirectUris as $uri) {
        if ($redirectUri === $uri || $redirectUri === rtrim($uri, '/') || rtrim($redirectUri, '/') === rtrim($uri, '/')) {
            $uriMatch = true;
            break;
        }
    }
}
if (!$uriMatch) {
    oauthError($redirectUri, 'invalid_request', $state, 'redirect_uri 不在应用白名单中');
}

// 未登录则跳转登录
if (empty($_SESSION['user_id'])) {
    $returnUrl = 'oauth/authorize.php?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => $responseType,
        'scope' => $scope,
        'state' => $state,
    ]);
    header('Location: login.php?redirect=' . urlencode($returnUrl));
    exit;
}

// POST: 处理授权结果
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'deny' || isset($_POST['cancel'])) {
        oauthError($redirectUri, 'access_denied', $state);
    }

    // 允许授权
    $userId = (int) $_SESSION['user_id'];
    $appId = (int) $app['id'];
    $code = bin2hex(random_bytes(24));
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 分钟

    try {
        $pdo->prepare("INSERT INTO oauth_authorization_codes (code, user_id, app_id, redirect_uri, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$code, $userId, $appId, $redirectUri, $scope ?: null, $expiresAt]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'oauth_authorization_codes') !== false) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
                code VARCHAR(64) PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                app_id INT UNSIGNED NOT NULL,
                redirect_uri VARCHAR(512) NOT NULL,
                scope VARCHAR(255),
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_expires (expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (app_id) REFERENCES oauth_apps(id) ON DELETE CASCADE
            )");
            $pdo->prepare("INSERT INTO oauth_authorization_codes (code, user_id, app_id, redirect_uri, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$code, $userId, $appId, $redirectUri, $scope ?: null, $expiresAt]);
        } else {
            throw $e;
        }
    }

    oauthSuccess($redirectUri, $code, $state);
}

// GET: 展示授权页
$appName = htmlspecialchars($app['name']);
$userDisplay = htmlspecialchars($_SESSION['username'] ?? $_SESSION['email'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权访问 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; background: #fcfcfc; color: #111; }
        .font-serif { font-family: "Songti SC", "SimSun", serif; }
        .btn-black { background: #000; color: #fff; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.75rem; transition: all 0.3s; }
        .btn-black:hover { background: #333; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="h-screen w-full flex items-center justify-center bg-gray-50">

    <div class="max-w-md w-full bg-white p-10 shadow-2xl relative">
        <div class="flex justify-center items-center gap-6 mb-10">
            <div class="flex flex-col items-center gap-2">
                <div class="w-16 h-16 rounded-full bg-gray-200 overflow-hidden border-2 border-white shadow-lg flex items-center justify-center font-serif text-2xl text-gray-500">
                    <?php echo strtoupper(substr($userDisplay, 0, 1)); ?>
                </div>
                <span class="text-xs text-gray-400 font-mono">You</span>
            </div>
            <div class="flex-1 h-px bg-gray-200 relative w-24">
                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-white border border-gray-200 rounded-full p-1 text-gray-300">
                    <i class="ri-arrow-right-line"></i>
                </div>
            </div>
            <div class="flex flex-col items-center gap-2">
                <div class="w-16 h-16 rounded-xl bg-black text-white flex items-center justify-center font-serif text-3xl shadow-lg">
                    <?php echo strtoupper(substr($appName, 0, 1)); ?>
                </div>
                <span class="text-xs text-gray-400 font-mono"><?php echo $appName; ?></span>
            </div>
        </div>

        <div class="text-center mb-8">
            <h1 class="font-serif text-2xl mb-2"><?php echo $appName; ?> 申请访问</h1>
            <p class="text-xs text-gray-500 leading-relaxed">该应用将获取您的以下权限：</p>
        </div>

        <div class="space-y-4 mb-10 border-t border-b border-gray-100 py-6">
            <div class="flex items-start gap-3">
                <i class="ri-user-smile-line text-lg mt-0.5 text-black"></i>
                <div>
                    <h4 class="text-sm font-bold">读取您的个人公开资料</h4>
                    <p class="text-[10px] text-gray-400">包括昵称、头像、User ID</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <i class="ri-mail-line text-lg mt-0.5 text-black"></i>
                <div>
                    <h4 class="text-sm font-bold">读取您的电子邮箱地址</h4>
                    <p class="text-[10px] text-gray-400">用于账号绑定与通知</p>
                </div>
            </div>
        </div>

        <form method="post" action="" class="flex gap-4">
            <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($clientId); ?>">
            <input type="hidden" name="redirect_uri" value="<?php echo htmlspecialchars($redirectUri); ?>">
            <input type="hidden" name="response_type" value="<?php echo htmlspecialchars($responseType); ?>">
            <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
            <input type="hidden" name="state" value="<?php echo htmlspecialchars($state); ?>">
            <button type="submit" name="cancel" value="1" class="flex-1 py-3 text-xs font-bold border border-gray-200 hover:bg-gray-50 transition uppercase tracking-widest text-gray-500">取消</button>
            <button type="submit" name="authorize" value="1" class="flex-1 py-3 btn-black shadow-lg">允许授权</button>
        </form>

        <div class="mt-8 text-center">
            <?php $switchRedirect = 'oauth/authorize.php?' . http_build_query(['client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => $responseType, 'scope' => $scope, 'state' => $state]); ?>
            <p class="text-[10px] text-gray-300">
                Logged in as <span class="text-gray-400 underline"><?php echo $userDisplay; ?></span> · <a href="../login.php?redirect=<?php echo urlencode($switchRedirect); ?>" class="hover:text-black">Switch Account</a>
            </p>
        </div>
    </div>
    
    <div class="absolute bottom-6 text-[10px] text-gray-300 tracking-widest uppercase">
        Secure OAuth 2.0 Authorization
    </div>

</body>
</html>
