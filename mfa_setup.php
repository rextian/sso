<?php
/**
 * REXTIAN SSO - MFA/2FA 双因素认证设置
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA 设置 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, sans-serif; background: #fff; color: #111; }
        .font-serif { font-family: "Songti SC", serif; }
        .mono-code { font-family: monospace; letter-spacing: 0.1em; }
    </style>
</head>
<body class="h-screen w-full flex">

    <div class="w-full lg:w-1/3 bg-gray-50 p-12 flex flex-col justify-center border-r border-gray-100">
        <div class="mb-12">
            <a href="index.php" class="font-serif text-xl font-bold tracking-widest text-black">REXTIAN</a>
        </div>
        <h1 class="font-serif text-3xl mb-4">双因素认证 (2FA)</h1>
        <p class="text-sm text-gray-500 leading-relaxed mb-8">
            为了增强您的账户安全性，我们建议开启两步验证。您需要使用 Authenticator 应用（如 Google Authenticator, Microsoft Authenticator）来生成动态验证码。
        </p>
        <div class="space-y-4">
            <div class="flex gap-4 items-center text-sm text-gray-600">
                <div class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center text-xs font-bold">1</div>
                <span>下载认证器 App</span>
            </div>
            <div class="flex gap-4 items-center text-sm text-gray-600">
                <div class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center text-xs font-bold">2</div>
                <span>扫描右侧二维码</span>
            </div>
            <div class="flex gap-4 items-center text-sm text-gray-600">
                <div class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center text-xs font-bold">3</div>
                <span>输入 6 位动态码</span>
            </div>
        </div>
    </div>

    <div class="flex-1 flex flex-col justify-center items-center p-12 relative">
        <a href="user_profile.php" class="absolute top-12 right-12 text-xs text-gray-400 hover:text-black uppercase tracking-widest">返回个人中心</a>

        <div class="max-w-md w-full text-center" id="mfa-container">
            <p class="text-gray-500">加载中...</p>
        </div>

    </div>

    <script>
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const container = document.getElementById('mfa-container');

        async function loadSetup() {
            const res = await fetch('api/me.php', { credentials: 'same-origin' });
            const json = await res.json();
            if (json.code !== 0) {
                container.innerHTML = '<p class="text-red-500">' + (json.message || '加载失败') + '</p>';
                return;
            }
            const me = json.data;
            if (me.mfa_enabled) {
                container.innerHTML = '<div class="text-center"><p class="text-green-600 font-medium mb-4">MFA 已启用</p><a href="user_profile.php" class="text-xs text-gray-500 hover:text-black">返回个人中心</a></div>';
                return;
            }
            const setupRes = await fetch('api/me/mfa/setup.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() }, credentials: 'same-origin' });
            const setupJson = await setupRes.json();
            if (setupJson.code !== 0) {
                container.innerHTML = '<p class="text-red-500">' + (setupJson.message || '获取密钥失败') + '</p>';
                return;
            }
            const d = setupJson.data;
            const qrData = encodeURIComponent(d.qr_url);
            container.innerHTML = '<div class="bg-white p-4 border border-gray-200 inline-block mb-8 shadow-xl">' +
                '<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + qrData + '" class="w-48 h-48" alt="QR">' +
                '</div>' +
                '<p class="text-xs text-gray-400 mb-2 uppercase tracking-wider">Secret Key</p>' +
                '<p class="font-mono text-sm bg-gray-100 py-2 px-4 inline-block rounded mb-8 select-all">' + (d.secret_formatted || d.secret) + '</p>' +
                '<div class="text-left"><label class="text-xs text-gray-500 uppercase tracking-wider block mb-2">输入验证码</label>' +
                '<div class="flex gap-3 mb-6"><input type="text" id="mfa-code" class="w-full border-b-2 border-gray-200 py-3 text-center text-2xl font-mono focus:border-black outline-none transition" placeholder="000 000" maxlength="6" pattern="[0-9]*" inputmode="numeric"></div>' +
                '<button type="button" id="btn-verify" class="w-full bg-black text-white py-4 text-xs font-bold uppercase tracking-widest hover:bg-gray-800 transition shadow-lg">验证并启用</button></div>';
            document.getElementById('btn-verify').addEventListener('click', doVerify);
            document.getElementById('mfa-code').addEventListener('keydown', e => { if (e.key === 'Enter') doVerify(); });
        }

        async function doVerify() {
            const code = document.getElementById('mfa-code').value.trim();
            if (code.length !== 6) {
                alert('请输入 6 位验证码');
                return;
            }
            const btn = document.getElementById('btn-verify');
            btn.disabled = true;
            btn.textContent = '验证中...';
            const res = await fetch('api/me/mfa/verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ code: code }),
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (json.code === 0) {
                container.innerHTML = '<div class="text-center"><p class="text-green-600 font-medium mb-4">MFA 已成功启用</p><a href="user_profile.php" class="text-sm text-black underline">返回个人中心</a></div>';
            } else {
                alert(json.message || '验证码错误');
                btn.disabled = false;
                btn.textContent = '验证并启用';
            }
        }

        loadSetup();
    </script>
</body>
</html>
