<?php
/**
 * REXTIAN SSO - 忘记密码
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/csrf.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘记密码 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background: #fff; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        .minimal-input { border: none; border-bottom: 1px solid #e5e7eb; padding: 1rem 0; background: transparent; width: 100%; font-size: 0.95rem; color: #111; transition: border-color 0.3s; }
        .minimal-input:focus { outline: none; border-bottom-color: #000; }
        .minimal-input::placeholder { color: #9ca3af; }
        .btn-black { background: #000; color: #fff; padding: 0.875rem 1.5rem; font-size: 0.875rem; font-weight: 500; letter-spacing: 0.1em; transition: all 0.3s; }
        .btn-black:hover { background: #333; transform: translateY(-2px); }
        .btn-black:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <div class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <a href="login.php" class="font-serif text-2xl font-bold tracking-widest text-black hover:opacity-70">REXTIAN</a>
            <div class="mt-12">
                <p class="text-gray-400 text-xs tracking-[0.2em] mb-3 uppercase">Reset Password</p>
                <h1 class="font-serif text-3xl text-black leading-tight mb-2">找回密码</h1>
                <p class="text-gray-400 text-sm font-light mb-8">输入注册邮箱，我们将发送重置链接</p>

                <form id="forgot-form" class="space-y-6">
                    <div id="form-error" class="text-xs text-red-500 hidden"></div>
                    <div id="form-success" class="text-xs text-green-600 hidden"></div>
                    <div>
                        <input type="email" name="email" id="email" class="minimal-input" placeholder="注册邮箱" required autocomplete="email">
                    </div>
                    <button type="submit" id="btn-submit" class="btn-black w-full">
                        发送重置链接
                    </button>
                </form>

                <p class="mt-8 text-center">
                    <a href="login.php" class="text-xs text-gray-400 hover:text-black transition-colors">← 返回登录</a>
                </p>
            </div>
        </div>
    </div>
    <footer class="py-6 text-center">
        <p class="text-[10px] text-gray-300 tracking-widest">© 2026 REXTIAN ID</p>
    </footer>

    <script>
        const form = document.getElementById('forgot-form');
        const btn = document.getElementById('btn-submit');
        const errEl = document.getElementById('form-error');
        const successEl = document.getElementById('form-success');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errEl.classList.add('hidden');
            successEl.classList.add('hidden');
            const email = document.getElementById('email').value.trim();
            if (!email) {
                errEl.textContent = '请输入邮箱地址';
                errEl.classList.remove('hidden');
                return;
            }
            btn.disabled = true;
            btn.textContent = '发送中...';
            try {
                const res = await fetch('api/auth/forgot-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const json = await res.json();
                if (json.code === 0) {
                    successEl.textContent = json.message || '若该邮箱已注册，您将收到重置链接，请查收邮件';
                    successEl.classList.remove('hidden');
                } else {
                    errEl.textContent = json.message || '发送失败';
                    errEl.classList.remove('hidden');
                }
            } catch (e) {
                errEl.textContent = '网络错误，请重试';
                errEl.classList.remove('hidden');
            }
            btn.disabled = false;
            btn.textContent = '发送重置链接';
        });
    </script>
</body>
</html>
