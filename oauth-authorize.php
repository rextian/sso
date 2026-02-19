<?php
/**
 * REXTIAN SSO - OAuth 授权确认页（入口）
 * 带 OAuth 参数时重定向到 oauth/authorize.php
 */
require_once __DIR__ . '/config.php';

$clientId = trim($_GET['client_id'] ?? '');
$redirectUri = trim($_GET['redirect_uri'] ?? '');
$responseType = trim($_GET['response_type'] ?? '');
$scope = trim($_GET['scope'] ?? '');
$state = trim($_GET['state'] ?? '');

if ($clientId && $redirectUri && $responseType) {
    $params = ['client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => $responseType];
    if ($scope) $params['scope'] = $scope;
    if ($state) $params['state'] = $state;
    header('Location: oauth/authorize.php?' . http_build_query($params));
    exit;
}
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
                <div class="w-16 h-16 rounded-full bg-gray-200 overflow-hidden border-2 border-white shadow-lg">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop" class="w-full h-full object-cover grayscale">
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
                    P
                </div>
                <span class="text-xs text-gray-400 font-mono">Alpha</span>
            </div>
        </div>

        <div class="text-center mb-8">
            <h1 class="font-serif text-2xl mb-2">Project Alpha 申请访问</h1>
            <p class="text-xs text-gray-500 leading-relaxed">该应用由 <span class="text-black font-medium">Rextian Inc.</span> 开发<br>将获取您的以下权限：</p>
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

        <form method="post" action="#" class="flex gap-4">
            <a href="login.php" class="flex-1 py-3 text-xs font-bold border border-gray-200 hover:bg-gray-50 transition uppercase tracking-widest text-gray-500 text-center">取消</a>
            <button type="submit" name="authorize" class="flex-1 py-3 btn-black shadow-lg">允许授权</button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-[10px] text-gray-300">
                Logged in as <span class="text-gray-400 underline">elena@rextian.com</span> · <a href="login.php" class="hover:text-black">Switch Account</a>
            </p>
        </div>
    </div>
    
    <div class="absolute bottom-6 text-[10px] text-gray-300 tracking-widest uppercase">
        Secure OAuth 2.0 Authorization
    </div>

</body>
</html>
