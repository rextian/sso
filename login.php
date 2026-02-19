<?php
/**
 * REXTIAN SSO - 登录页
 * 后续可在此添加 session 校验、数据库验证等
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings_helper.php';

// 检查各第三方登录是否已配置（仅显示已配置的入口）
function isSocialProviderConfigured($provider) {
    switch ($provider) {
        case 'wechat':  return (bool) getSetting('wechat_app_id');
        case 'feishu':  return (bool) getSetting('feishu_app_id');
        case 'github':  return (bool) getSetting('github_client_id');
        case 'dingtalk': return (bool) getSetting('dingtalk_appkey');
        case 'wecom':   return getSetting('wecom_corp_id') && getSetting('wecom_agent_id');
        default: return false;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REXTIAN | 统一身份认证</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        /* 全局字体设置 */
        body {
            font-family: 'Noto Sans SC', sans-serif;
            background-color: #ffffff;
        }
        
        .font-serif {
            font-family: 'Noto Serif SC', serif;
        }

        /* 极简输入框：底部线条风格 */
        .minimal-input {
            border: none;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 0;
            background: transparent;
            transition: all 0.4s ease;
            font-size: 0.95rem;
            color: #111;
            width: 100%;
        }
        
        .minimal-input:focus {
            outline: none;
            border-bottom-color: #000;
        }

        .minimal-input::placeholder {
            color: #9ca3af;
            font-weight: 300;
        }

        /* 黑色实心按钮 */
        .btn-black {
            background-color: #000;
            color: #fff;
            transition: all 0.3s ease;
            letter-spacing: 0.1em;
        }
        
        .btn-black:hover {
            background-color: #333;
            transform: translateY(-2px);
        }

        /* 选项卡切换动画 */
        .tab-btn {
            position: relative;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.3s ease;
        }
        .tab-btn.active {
            color: #000;
            font-weight: 500;
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #000;
        }

        /* 验证码按钮 */
        .code-btn {
            font-size: 0.8rem;
            color: #4b5563;
            cursor: pointer;
            transition: color 0.2s;
            white-space: nowrap;
        }
        .code-btn:hover { color: #000; }

        /* 隐藏/显示控制 */
        .hidden-panel { display: none; }
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="h-screen w-full flex overflow-hidden">

    <div class="w-full lg:w-[45%] h-full bg-white flex flex-col justify-center px-8 sm:px-16 xl:px-24 relative z-10">
        
        <div class="absolute top-10 left-8 sm:left-16 xl:left-24">
            <a href="login.php" class="font-serif text-2xl font-bold tracking-widest text-black">REXTIAN</a>
        </div>

        <div class="max-w-sm w-full mx-auto mt-10">
            <div class="mb-10">
                <p class="text-gray-400 text-xs tracking-[0.2em] mb-3 uppercase">Welcome Back</p>
                <h1 class="font-serif text-4xl text-black leading-tight mb-2">
                    登录您的账户
                </h1>
                <p class="text-xs text-gray-400 font-light">
                    开启 REXTIAN 数字生活
                </p>
                <?php if (isset($_GET['registered'])): ?>
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-sm">
                    <p class="text-xs text-green-700">
                        <i class="ri-check-line mr-1"></i>注册成功！请等待管理员审核通过后登录
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex space-x-8 mb-8 border-b border-gray-100 pb-2">
                <div id="tab-account" class="tab-btn active text-sm" onclick="switchTab('account')">账号密码</div>
                <div id="tab-phone" class="tab-btn text-sm" onclick="switchTab('phone')">手机验证码</div>
            </div>

            <form id="login-form" class="space-y-6 min-h-[220px]">
                <div id="login-error" class="text-xs text-red-500 hidden"></div>
                
                <div id="panel-mfa" class="hidden-panel fade-in space-y-6">
                    <p class="text-sm text-gray-600">请输入您的 Authenticator 应用中的 6 位验证码</p>
                    <div class="group">
                        <input type="text" name="mfa_code" id="mfa-code" class="minimal-input font-mono text-center text-xl tracking-[0.5em]" placeholder="000 000" maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code">
                    </div>
                    <button type="button" id="btn-mfa-back" class="text-xs text-gray-400 hover:text-black">← 返回重新登录</button>
                </div>
                
                <div id="panel-account" class="fade-in space-y-6">
                    <div class="group">
                        <input type="text" name="username" id="username" class="minimal-input" placeholder="邮箱 / 用户名 / 手机号">
                    </div>
                    <div class="group">
                        <input type="password" name="password" id="password" class="minimal-input" placeholder="密码">
                    </div>
                </div>

                <div id="panel-phone" class="hidden-panel fade-in space-y-6">
                    <div class="group flex items-end border-b border-gray-200">
                        <span class="text-sm text-gray-500 pb-4 pr-3">+86</span>
                        <input type="tel" name="phone" id="phone" class="minimal-input border-b-0 p-0 pb-4" placeholder="请输入手机号码">
                    </div>
                    <div class="group flex justify-between items-center border-b border-gray-200">
                        <input type="text" name="code" id="sms-code" class="minimal-input border-b-0 p-0 pb-4 w-2/3 font-mono" placeholder="6位验证码" maxlength="6" pattern="[0-9]*" inputmode="numeric">
                        <button type="button" id="btn-send-code" class="code-btn pb-4">获取验证码</button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="remember" class="w-3 h-3 rounded border-gray-300 accent-black">
                        <span class="ml-2 text-xs text-gray-500">保持登录状态</span>
                    </label>
                    <a href="forgot_password.php" class="text-xs text-gray-400 hover:text-black transition-colors">忘记密码?</a>
                </div>
                
                <div class="mt-8 text-center pt-6 border-t border-gray-100">
                    <p class="text-xs text-gray-400">
                        还没有账户？<a href="register.php" class="text-black border-b border-black pb-0.5 hover:opacity-70">立即注册 →</a>
                    </p>
                </div>

                <button type="submit" id="btn-submit" class="btn-black w-full py-3.5 text-sm font-medium tracking-widest mt-4">
                    立即登录
                </button>
            </form>

            <?php
            $redirectParam = !empty($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : '';
            $allProviders = [
                ['provider' => 'wechat',  'icon' => 'ri-wechat-fill',      'label' => '微信',   'color' => '#07C160'],
                ['provider' => 'feishu',  'icon' => 'ri-file-list-3-fill', 'label' => '飞书',   'color' => '#3370FF'],
                ['provider' => 'github',  'icon' => 'ri-github-fill',      'label' => 'GitHub', 'color' => '#181717'],
                ['provider' => 'dingtalk','icon' => 'ri-chat-smile-3-fill','label' => '钉钉',   'color' => '#0089FF'],
                ['provider' => 'wecom',   'icon' => 'ri-wechat-2-fill',      'label' => '企业微信','color' => '#2DC84B'],
            ];
            $socialProviders = array_filter($allProviders, function($p) { return isSocialProviderConfigured($p['provider']); });
            ?>
            <div class="mt-12">
                <?php if (!empty($socialProviders)): ?>
                <p class="text-center text-[11px] text-gray-400 tracking-widest uppercase mb-5">其他方式登录</p>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 sm:gap-3">
                    <?php foreach ($socialProviders as $p): ?>
                    <a href="auth/login.php?provider=<?php echo $p['provider']; ?><?php echo $redirectParam; ?>" 
                       class="flex flex-col items-center justify-center py-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:border-gray-200 hover:bg-white hover:shadow-sm transition-all duration-300 group" 
                       title="<?php echo htmlspecialchars($p['label']); ?>登录">
                        <i class="<?php echo $p['icon']; ?> text-2xl group-hover:scale-110 transition-transform duration-300" style="color: <?php echo $p['color']; ?>;"></i>
                        <span class="mt-2 text-[10px] text-gray-400 group-hover:text-gray-700 transition-colors"><?php echo htmlspecialchars($p['label']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-[11px] text-gray-400">第三方登录暂未开放，请联系管理员在【系统设置 → 第三方登录】中配置</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="absolute bottom-6 left-0 w-full text-center lg:text-left px-8 sm:px-16 xl:px-24">
            <p class="text-[10px] text-gray-300 tracking-widest">© 2026 REXTIAN ID. 浙ICP备2026001号</p>
        </div>
    </div>

    <div class="hidden lg:block lg:w-[55%] h-full relative bg-gray-50">
        <img src="https://images.unsplash.com/photo-1506784365847-bbad939e9335?q=80&w=2068&auto=format&fit=crop" 
             alt="Minimalist Aesthetics" 
             class="absolute inset-0 w-full h-full object-cover filter brightness-[0.9] contrast-[0.9] hover:scale-105 transition-transform duration-[10s]">
        
        <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
        
        <div class="absolute bottom-16 left-16 text-white max-w-md">
            <h3 class="font-serif text-3xl mb-4 tracking-wide">至简，<br>方能致远。</h3>
            <p class="text-xs tracking-widest opacity-80 uppercase">Simplicity is the ultimate sophistication.</p>
        </div>
    </div>

    <script>
        async function doMfaVerify() {
            const code = document.getElementById('mfa-code').value.trim();
            if (code.length !== 6) return;
            const btn = document.getElementById('btn-submit');
            const errEl = document.getElementById('login-error');
            errEl.classList.add('hidden');
            btn.disabled = true;
            btn.textContent = '验证中...';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch('api/auth/mfa-verify.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ code: code }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    const params = new URLSearchParams(location.search);
                    const redirect = params.get('redirect') || 'index.php';
                    location.href = redirect;
                } else {
                    errEl.textContent = data.message || '验证码错误';
                    errEl.classList.remove('hidden');
                }
            } catch (err) {
                errEl.textContent = '网络错误，请重试';
                errEl.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = '验证';
            }
        }

        document.getElementById('login-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (this.dataset.mfaMode === '1') {
                doMfaVerify();
                return;
            }
            const panel = document.getElementById('panel-account').classList.contains('hidden-panel');
            const btn = document.getElementById('btn-submit');
            const errEl = document.getElementById('login-error');
            errEl.classList.add('hidden');
            errEl.textContent = '';

            let url = panel ? 'api/auth/login-by-sms.php' : 'api/auth/login.php';
            let body;
            if (panel) {
                const phone = document.getElementById('phone').value.trim().replace(/\D/g, '');
                const code = document.getElementById('sms-code').value.trim();
                if (!/^1[3-9]\d{9}$/.test(phone)) {
                    errEl.textContent = '请输入正确的手机号';
                    errEl.classList.remove('hidden');
                    return;
                }
                if (code.length !== 6) {
                    errEl.textContent = '请输入 6 位验证码';
                    errEl.classList.remove('hidden');
                    return;
                }
                body = JSON.stringify({
                    phone: phone,
                    code: code,
                    remember: document.querySelector('input[name="remember"]').checked
                });
            } else {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                if (!username) {
                    errEl.textContent = '请输入邮箱 / 用户名 / 手机号';
                    errEl.classList.remove('hidden');
                    return;
                }
                if (!password) {
                    errEl.textContent = '请输入密码';
                    errEl.classList.remove('hidden');
                    return;
                }
                body = JSON.stringify({
                    username: username,
                    password: password,
                    remember: document.querySelector('input[name="remember"]').checked
                });
            }

            btn.disabled = true;
            btn.textContent = '登录中...';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: body,
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    if (data.mfa_required) {
                        document.getElementById('panel-account').classList.add('hidden-panel');
                        document.getElementById('panel-phone').classList.add('hidden-panel');
                        document.getElementById('tab-account').classList.add('hidden-panel');
                        document.getElementById('tab-phone').classList.add('hidden-panel');
                        document.getElementById('panel-mfa').classList.remove('hidden-panel');
                        document.getElementById('mfa-code').focus();
                        document.getElementById('login-form').dataset.mfaMode = '1';
                        document.getElementById('btn-submit').textContent = '验证';
                    } else {
                        const params = new URLSearchParams(location.search);
                        const redirect = params.get('redirect') || 'index.php';
                        location.href = redirect;
                    }
                } else {
                    errEl.textContent = data.message || '登录失败';
                    errEl.classList.remove('hidden');
                }
            } catch (err) {
                errEl.textContent = '网络错误，请重试';
                errEl.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = panel ? '验证码登录' : '立即登录';
            }
        });

        document.getElementById('mfa-code').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); doMfaVerify(); }
        });

        document.getElementById('btn-mfa-back').addEventListener('click', function() {
            document.getElementById('panel-mfa').classList.add('hidden-panel');
            document.getElementById('panel-account').classList.remove('hidden-panel');
            document.getElementById('tab-account').classList.remove('hidden-panel');
            document.getElementById('tab-phone').classList.remove('hidden-panel');
            document.getElementById('login-form').dataset.mfaMode = '0';
            document.getElementById('mfa-code').value = '';
            document.getElementById('btn-submit').textContent = '立即登录';
        });

        let codeCountdown = 0;
        document.getElementById('btn-send-code').addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (codeCountdown > 0) return;
            const phone = document.getElementById('phone').value.trim().replace(/\D/g, '');
            const errEl = document.getElementById('login-error');
            errEl.classList.add('hidden');
            errEl.textContent = '';
            if (!/^1[3-9]\d{9}$/.test(phone)) {
                errEl.textContent = '请输入正确的 11 位手机号';
                errEl.classList.remove('hidden');
                return;
            }
            const btn = this;
            const oldText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '发送中...';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch('api/auth/send-sms.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ phone: phone }),
                    credentials: 'same-origin'
                });
                let data;
                try {
                    data = await res.json();
                } catch (parseErr) {
                    errEl.textContent = '服务器返回异常，请刷新页面重试';
                    errEl.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = oldText;
                    return;
                }
                if (data.code === 0) {
                    codeCountdown = 60;
                    const t = setInterval(() => {
                        codeCountdown--;
                        btn.textContent = codeCountdown > 0 ? codeCountdown + 's 后重发' : '获取验证码';
                        if (codeCountdown <= 0) { btn.disabled = false; clearInterval(t); }
                    }, 1000);
                    errEl.classList.add('hidden');
                } else {
                    errEl.textContent = data.message || '发送失败';
                    errEl.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = oldText;
                }
            } catch (err) {
                errEl.textContent = '网络错误：' + (err.message || '请检查网络');
                errEl.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = oldText;
            }
        });

        function switchTab(type) {
            const tabAccount = document.getElementById('tab-account');
            const tabPhone = document.getElementById('tab-phone');
            const panelAccount = document.getElementById('panel-account');
            const panelPhone = document.getElementById('panel-phone');

            if (type === 'account') {
                tabAccount.classList.add('active');
                tabPhone.classList.remove('active');
                panelAccount.classList.remove('hidden-panel');
                panelPhone.classList.add('hidden-panel');
                document.getElementById('btn-submit').textContent = '立即登录';
            } else {
                tabPhone.classList.add('active');
                tabAccount.classList.remove('active');
                panelPhone.classList.remove('hidden-panel');
                panelAccount.classList.add('hidden-panel');
                document.getElementById('btn-submit').textContent = '验证码登录';
            }
        }

        const urlParams = new URLSearchParams(location.search);
        const errMap = {
            account_not_bound: '该第三方账号未绑定，请先使用账号密码登录后在个人中心绑定',
            session_expired: '登录已过期，请重试',
            config_missing: '该登录方式未配置，请管理员在【系统设置 → 第三方登录】中填写 App ID 和 Secret 后保存',
            token_failed: '授权失败，请重试',
            user_failed: '获取用户信息失败',
            server_error: '服务暂时不可用',
            account_disabled: '账户已被禁用',
            invalid_callback: '回调参数无效',
        };
        if (urlParams.get('error')) {
            const msg = errMap[urlParams.get('error')] || urlParams.get('error');
            document.getElementById('login-error').textContent = msg;
            document.getElementById('login-error').classList.remove('hidden');
            history.replaceState({}, '', location.pathname + (urlParams.get('redirect') ? '?redirect=' + urlParams.get('redirect') : ''));
        }
    </script>
</body>
</html>
