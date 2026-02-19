<?php
/**
 * REXTIAN SSO - 用户注册页
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings_helper.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册账户 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background-color: #ffffff; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        
        .minimal-input {
            border: none; border-bottom: 1px solid #e5e7eb; padding: 1rem 0;
            background: transparent; transition: all 0.4s ease; font-size: 0.95rem;
            color: #111; width: 100%;
        }
        .minimal-input:focus { outline: none; border-bottom-color: #000; }
        .minimal-input::placeholder { color: #9ca3af; font-weight: 300; }
        
        .btn-black {
            background-color: #000; color: #fff; transition: all 0.3s ease;
            letter-spacing: 0.1em;
        }
        .btn-black:hover { background-color: #333; transform: translateY(-2px); }
        .btn-black:disabled { background-color: #999; cursor: not-allowed; transform: none; }
        
        .btn-outline {
            background-color: transparent; color: #000; border: 1px solid #000; transition: all 0.3s ease;
            letter-spacing: 0.1em;
        }
        .btn-outline:hover { background-color: #000; color: #fff; }
        
        #toast {
            position: fixed; top: 24px; left: 50%; transform: translateX(-50%) translateY(-100px);
            background: #000; color: #fff; padding: 12px 32px; border-radius: 4px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 100; font-size: 0.8rem; letter-spacing: 0.05em;
        }
        #toast.show { transform: translateX(-50%) translateY(0); }
        
        .step-indicator {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }
        .step-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #e5e7eb;
            transition: background-color 0.3s ease;
        }
        .step-dot.active {
            background-color: #000;
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
                <p class="text-gray-400 text-xs tracking-[0.2em] mb-3 uppercase">Create Account</p>
                <h1 class="font-serif text-4xl text-black leading-tight mb-2">
                    注册新账户
                </h1>
                <p class="text-xs text-gray-400 font-light">
                    注册后需等待管理员审核通过
                </p>
                
                <div class="step-indicator mt-4">
                    <div class="step-dot active" id="step-1"></div>
                    <div class="step-dot" id="step-2"></div>
                    <div class="step-dot" id="step-3"></div>
                </div>
            </div>

            <div id="register-error" class="text-xs text-red-500 hidden mb-4"></div>

            <div id="step-email" class="space-y-6">
                <div class="group">
                    <input type="email" id="reg-email" class="minimal-input" placeholder="电子邮箱">
                </div>
                
                <div class="flex gap-3">
                    <div class="group flex-1">
                        <input type="text" id="email-code" class="minimal-input" placeholder="验证码" maxlength="6">
                    </div>
                    <button type="button" id="btn-send-code" class="btn-outline px-6 py-3.5 text-sm font-medium tracking-widest whitespace-nowrap">
                        发送验证码
                    </button>
                </div>

                <button type="button" id="btn-next-step" class="btn-black w-full py-3.5 text-sm font-medium tracking-widest mt-4">
                    下一步
                </button>
            </div>

            <div id="step-password" class="space-y-6 hidden">
                <div class="group">
                    <input type="text" id="reg-username" class="minimal-input" placeholder="用户名" maxlength="64">
                </div>
                <div class="group">
                    <input type="password" id="reg-password" class="minimal-input" placeholder="设置密码">
                </div>
                <div class="group">
                    <input type="password" id="reg-confirm-password" class="minimal-input" placeholder="确认密码">
                </div>

                <div class="flex gap-3">
                    <button type="button" id="btn-back-step" class="btn-outline flex-1 py-3.5 text-sm font-medium tracking-widest">
                        返回
                    </button>
                    <button type="button" id="btn-register" class="btn-black flex-1 py-3.5 text-sm font-medium tracking-widest">
                        立即注册
                    </button>
                </div>
            </div>

            <div id="step-success" class="space-y-6 hidden text-center">
                <div class="text-6xl mb-6">✓</div>
                <h2 class="font-serif text-2xl text-black mb-2">注册成功</h2>
                <p class="text-sm text-gray-500 mb-8">请等待管理员审核通过</p>
                <button type="button" id="btn-go-login" class="btn-black w-full py-3.5 text-sm font-medium tracking-widest">
                    前往登录
                </button>
            </div>

            <div class="mt-8 text-center">
                <p class="text-xs text-gray-400">
                    已有账户？<a href="login.php" class="text-black border-b border-black pb-0.5 hover:opacity-70">立即登录 →</a>
                </p>
            </div>
        </div>
        
        <div class="absolute bottom-6 left-0 w-full text-center lg:text-left px-8 sm:px-16 xl:px-24">
            <p class="text-[10px] text-gray-300 tracking-widest">© 2026 REXTIAN ID. 浙ICP备2026001号</p>
        </div>
    </div>

    <div class="hidden lg:block lg:w-[55%] h-full relative bg-gray-50">
        <img src="https://images.unsplash.com/photo-1497215728101-856f4ea42174?q=80&w=2068&auto=format&fit=crop" 
             alt="Minimalist Aesthetics" 
             class="absolute inset-0 w-full h-full object-cover filter brightness-[0.9] contrast-[0.9] hover:scale-105 transition-transform duration-[10s]">
        
        <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
        
        <div class="absolute bottom-16 left-16 text-white max-w-md">
            <h3 class="font-serif text-3xl mb-4 tracking-wide">新的开始，<br>从这里启程。</h3>
            <p class="text-xs tracking-widest opacity-80 uppercase">A new beginning starts here.</p>
        </div>
    </div>

    <div id="toast"></div>

    <script>
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        let verificationToken = '';
        let registeredEmail = '';
        
        function showToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.style.background = isError ? '#dc2626' : '#000';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        function showError(msg) {
            const errEl = document.getElementById('register-error');
            errEl.textContent = msg;
            errEl.classList.remove('hidden');
        }
        
        function hideError() {
            document.getElementById('register-error').classList.add('hidden');
        }
        
        function setStep(step) {
            document.querySelectorAll('.step-dot').forEach((dot, index) => {
                dot.classList.toggle('active', index < step);
            });
            
            document.getElementById('step-email').classList.toggle('hidden', step !== 1);
            document.getElementById('step-password').classList.toggle('hidden', step !== 2);
            document.getElementById('step-success').classList.toggle('hidden', step !== 3);
        }
        
        let countdownTimer = null;
        function startCountdown() {
            const btn = document.getElementById('btn-send-code');
            let seconds = 60;
            btn.disabled = true;
            btn.textContent = `${seconds}s`;
            
            countdownTimer = setInterval(() => {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(countdownTimer);
                    btn.disabled = false;
                    btn.textContent = '发送验证码';
                } else {
                    btn.textContent = `${seconds}s`;
                }
            }, 1000);
        }
        
        document.getElementById('btn-send-code').addEventListener('click', async function() {
            hideError();
            const email = document.getElementById('reg-email').value.trim();
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError('请输入有效的电子邮箱');
                return;
            }
            
            this.disabled = true;
            try {
                const res = await fetch('api/auth/send-email-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ email, type: 'register' }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    showToast('验证码已发送');
                    startCountdown();
                    registeredEmail = email;
                } else {
                    showError(data.message || '发送失败');
                }
            } catch (err) {
                showError('网络错误，请重试');
            } finally {
                if (!countdownTimer) {
                    this.disabled = false;
                }
            }
        });
        
        document.getElementById('btn-next-step').addEventListener('click', async function() {
            hideError();
            const email = document.getElementById('reg-email').value.trim();
            const code = document.getElementById('email-code').value.trim();
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError('请输入有效的电子邮箱');
                return;
            }
            if (!code || code.length !== 6) {
                showError('请输入6位验证码');
                return;
            }
            
            this.disabled = true;
            this.textContent = '验证中...';
            try {
                const res = await fetch('api/auth/verify-email-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ email, code, type: 'register' }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    verificationToken = data.data.token;
                    registeredEmail = email;
                    setStep(2);
                } else {
                    showError(data.message || '验证失败');
                }
            } catch (err) {
                showError('网络错误，请重试');
            } finally {
                this.disabled = false;
                this.textContent = '下一步';
            }
        });
        
        document.getElementById('btn-back-step').addEventListener('click', function() {
            hideError();
            setStep(1);
        });
        
        document.getElementById('btn-register').addEventListener('click', async function() {
            hideError();
            const username = document.getElementById('reg-username').value.trim();
            const password = document.getElementById('reg-password').value;
            const confirmPassword = document.getElementById('reg-confirm-password').value;
            
            if (!username || username.length < 2) {
                showError('请输入至少2位的用户名');
                return;
            }
            if (!password || password.length < 6) {
                showError('密码至少需要6位');
                return;
            }
            if (password !== confirmPassword) {
                showError('两次输入的密码不一致');
                return;
            }
            
            this.disabled = true;
            this.textContent = '注册中...';
            try {
                const res = await fetch('api/users/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ 
                        username, 
                        email: registeredEmail, 
                        password,
                        verification_token: verificationToken
                    }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    setStep(3);
                } else {
                    showError(data.message || '注册失败');
                }
            } catch (err) {
                showError('网络错误，请重试');
            } finally {
                this.disabled = false;
                this.textContent = '立即注册';
            }
        });
        
        document.getElementById('btn-go-login').addEventListener('click', function() {
            location.href = 'login.php?registered=1';
        });
    </script>
</body>
</html>
