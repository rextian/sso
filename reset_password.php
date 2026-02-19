<?php
/**
 * REXTIAN SSO - 重置密码（通过邮件链接进入）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/csrf.php';

$token = trim($_GET['token'] ?? '');
$validToken = (strlen($token) === 64 && ctype_xdigit($token));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码 | REXTIAN ID</title>
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
                <?php if (!$validToken): ?>
                <p class="text-gray-400 text-xs tracking-[0.2em] mb-3 uppercase">Invalid Link</p>
                <h1 class="font-serif text-3xl text-black leading-tight mb-2">链接无效</h1>
                <p class="text-gray-400 text-sm font-light mb-8">重置链接已过期或格式错误，请<a href="forgot_password.php" class="text-black underline">重新申请</a>。</p>
                <a href="forgot_password.php" class="btn-black inline-block text-center w-full">重新申请</a>
                <?php else: ?>
                <p class="text-gray-400 text-xs tracking-[0.2em] mb-3 uppercase">Set New Password</p>
                <h1 class="font-serif text-3xl text-black leading-tight mb-2">设置新密码</h1>
                <p class="text-gray-400 text-sm font-light mb-8">请输入您的新密码</p>

                <form id="reset-form" class="space-y-6">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div id="form-error" class="text-xs text-red-500 hidden"></div>
                    <div id="form-success" class="text-xs text-green-600 hidden"></div>
                    <div>
                        <input type="password" name="password" id="password" class="minimal-input" placeholder="新密码" required minlength="6" autocomplete="new-password">
                    </div>
                    <div>
                        <input type="password" name="password2" id="password2" class="minimal-input" placeholder="确认新密码" required minlength="6" autocomplete="new-password">
                    </div>
                    <button type="submit" id="btn-submit" class="btn-black w-full">
                        重置密码
                    </button>
                </form>
                <?php endif; ?>

                <p class="mt-8 text-center">
                    <a href="login.php" class="text-xs text-gray-400 hover:text-black transition-colors">← 返回登录</a>
                </p>
            </div>
        </div>
    </div>
    <footer class="py-6 text-center">
        <p class="text-[10px] text-gray-300 tracking-widest">© 2026 REXTIAN ID</p>
    </footer>

    <?php if ($validToken): ?>
    <script>
        const form = document.getElementById('reset-form');
        const btn = document.getElementById('btn-submit');
        const errEl = document.getElementById('form-error');
        const successEl = document.getElementById('form-success');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errEl.classList.add('hidden');
            successEl.classList.add('hidden');
            const password = document.getElementById('password').value;
            const password2 = document.getElementById('password2').value;
            const token = document.querySelector('input[name="token"]').value;

            if (password !== password2) {
                errEl.textContent = '两次输入的密码不一致';
                errEl.classList.remove('hidden');
                return;
            }
            if (password.length < 6) {
                errEl.textContent = '密码至少需要 6 位';
                errEl.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            btn.textContent = '提交中...';
            try {
                const res = await fetch('api/auth/reset-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, password })
                });
                const json = await res.json();
                if (json.code === 0) {
                    successEl.textContent = json.message || '密码已重置';
                    successEl.classList.remove('hidden');
                    setTimeout(() => { location.href = 'login.php'; }, 2000);
                } else {
                    errEl.textContent = json.message || '重置失败';
                    errEl.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = '重置密码';
                }
            } catch (e) {
                errEl.textContent = '网络错误，请重试';
                errEl.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = '重置密码';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
