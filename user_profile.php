<?php
/**
 * REXTIAN SSO - 用户个人中心
 * 登录用户查看/修改自己的资料、会话管理
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
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f9fafb; color: #111; }
        .font-serif { font-family: "Songti SC", "SimSun", serif; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .minimal-input { width: 100%; border: none; border-bottom: 1px solid #e5e7eb; padding: 10px 0; background: transparent; outline: none; transition: border-color 0.3s; }
        .minimal-input:focus { border-bottom-color: #000; }
        .card { background: white; border: 1px solid #f3f4f6; padding: 2rem; margin-bottom: 2rem; transition: box-shadow 0.3s; }
        .card:hover { box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05); }
        .icon-box { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .btn-outline { border: 1px solid #e5e7eb; padding: 4px 12px; font-size: 0.75rem; transition: all 0.2s; }
        .btn-outline:hover { border-color: #000; background: #000; color: #fff; }
        .toast { position: fixed; bottom: 2rem; right: 2rem; padding: 12px 20px; background: #000; color: #fff; font-size: 0.8rem; border-radius: 4px; opacity: 0; transform: translateY(10px); transition: all 0.3s; z-index: 9999; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: all 0.3s; }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-box { background: white; padding: 2rem; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.15); transform: translateY(-10px); transition: transform 0.3s; }
        .modal-overlay.show .modal-box { transform: translateY(0); }
        .modal-box h4 { font-family: "Songti SC", serif; margin-bottom: 1rem; font-size: 1.1rem; }
        .modal-box .minimal-input { width: 100%; border: none; border-bottom: 1px solid #e5e7eb; padding: 10px 0; background: transparent; outline: none; font-size: 14px; }
        .modal-box .minimal-input:focus { border-bottom-color: #000; }
        .modal-box label { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 4px; }
        .modal-box .flex-row { display: flex; gap: 8px; align-items: flex-end; }
        .modal-box .flex-row input { flex: 1; }
        .modal-box .btn-send { white-space: nowrap; padding: 10px 16px; border: 1px solid #e5e7eb; font-size: 12px; background: white; cursor: pointer; transition: all 0.2s; }
        .modal-box .btn-send:hover:not(:disabled) { border-color: #000; background: #000; color: #fff; }
        .modal-box .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }
        .modal-box .btn-submit { width: 100%; padding: 12px; background: #000; color: #fff; border: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; margin-top: 1rem; transition: opacity 0.2s; }
        .modal-box .btn-submit:hover:not(:disabled) { opacity: 0.85; }
        .modal-box .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body class="min-h-screen pb-12">

    <nav class="bg-white border-b border-gray-100 px-8 py-4 flex justify-between items-center sticky top-0 z-50 shadow-sm shadow-gray-100/50">
        <a href="index.php" class="font-serif text-xl font-bold tracking-widest cursor-pointer hover:opacity-70">REXTIAN</a>
        <div class="flex items-center gap-4">
            <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php" class="text-xs text-gray-500 hover:text-black uppercase tracking-wider">管理后台</a>
            <?php endif; ?>
            <a href="logout.php" class="text-[10px] text-gray-400 hover:text-black">退出登录</a>
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-black" id="nav-display-name">加载中...</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider" id="nav-username">-</p>
            </div>
            <div class="w-9 h-9 rounded-full bg-gray-200 overflow-hidden border border-gray-100 flex items-center justify-center text-gray-500 font-mono text-sm relative" id="nav-avatar-wrap">
                <img id="nav-avatar-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover" style="display:none">
                <span id="nav-avatar-letter">-</span>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto py-12 px-6">
        
        <div class="mb-10">
            <h1 class="font-serif text-3xl mb-2">账户设置</h1>
            <p class="text-xs text-gray-400 uppercase tracking-widest">Manage your profile and security preferences</p>
        </div>

        <div class="card rounded-sm">
            <div class="flex justify-between items-start mb-8">
                <h3 class="font-serif text-xl">个人资料 (Profile)</h3>
                <button type="button" id="btn-save-profile" class="btn-outline uppercase tracking-wider">保存更改</button>
            </div>
            <div class="flex flex-col sm:flex-row items-center gap-8">
                <div class="relative group">
                    <div id="profile-avatar-wrap" class="w-24 h-24 rounded-full bg-gray-200 overflow-hidden ring-4 ring-gray-50 flex items-center justify-center text-2xl font-mono text-gray-500 relative">
                        <img id="profile-avatar-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover" style="display:none">
                        <span id="profile-avatar-letter">-</span>
                    </div>
                    <button type="button" id="btn-change-avatar" class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-black text-white flex items-center justify-center text-sm hover:bg-gray-800 transition" title="更换头像"><i class="ri-camera-line"></i></button>
                </div>
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-8 w-full">
                    <div>
                        <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">Display Name</label>
                        <input type="text" id="input-display-name" class="minimal-input font-medium" placeholder="显示名称">
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">User ID</label>
                        <input type="text" id="input-user-id" class="minimal-input font-mono text-gray-400 text-sm" disabled>
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-sm">
            <h3 class="text-[10px] text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2 mb-4">基础认证</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between group">
                    <div class="flex items-center gap-4">
                        <div class="icon-box bg-gray-50 text-gray-600">
                            <i class="ri-smartphone-line text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold">手机号码</p>
                            <p class="text-xs text-gray-500 font-mono mt-0.5" id="profile-phone">-</p>
                        </div>
                    </div>
                    <button type="button" id="btn-change-phone" class="text-xs text-gray-500 hover:text-black border-b border-transparent hover:border-black transition">更换号码</button>
                </div>
                <div class="flex items-center justify-between group">
                    <div class="flex items-center gap-4">
                        <div class="icon-box bg-gray-50 text-gray-600">
                            <i class="ri-mail-line text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold">电子邮箱</p>
                            <p class="text-xs text-gray-500 font-mono mt-0.5" id="profile-email">-</p>
                        </div>
                    </div>
                    <button type="button" id="btn-change-email" class="text-xs text-gray-500 hover:text-black border-b border-transparent hover:border-black transition">更换邮箱</button>
                </div>
            </div>
        </div>

        <div class="card rounded-sm">
            <h3 class="text-[10px] text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2 mb-4">第三方账号</h3>
            <div id="connections-list" class="space-y-4">
                <p class="text-sm text-gray-500">加载中...</p>
            </div>
        </div>

        <div class="card rounded-sm border-l-4 border-l-black">
            <h3 class="font-serif text-xl mb-6">安全设备 (Security)</h3>
            
            <div class="space-y-1" id="sessions-list">
                <p class="text-sm text-gray-500">加载中...</p>
            </div>
            
            <div class="mt-8 pt-6 border-t border-gray-100">
                <div id="mfa-section" class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <i class="ri-shield-check-line text-lg text-gray-500"></i>
                        <span class="text-sm" id="mfa-status-text">-</span>
                    </div>
                    <span id="mfa-action"></span>
                </div>
                <button type="button" class="text-xs text-red-500 hover:text-red-700 font-bold tracking-widest uppercase flex items-center gap-2 mt-4" disabled title="待实现">
                    <i class="ri-delete-bin-line"></i> Delete Account
                </button>
            </div>
        </div>

    </main>

    <div id="toast" class="toast"></div>

    <!-- 更换邮箱弹窗 -->
    <div id="modal-email" class="modal-overlay">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h4>更换邮箱</h4>
            <p class="text-xs text-gray-500 mb-4">我们将向新邮箱发送验证码，验证通过后即可更新。</p>
            <div class="mb-4">
                <label>新邮箱地址</label>
                <div class="flex-row">
                    <input type="email" id="modal-email-input" class="minimal-input" placeholder="your@email.com">
                    <button type="button" id="modal-email-send" class="btn-send">发送验证码</button>
                </div>
            </div>
            <div class="mb-4">
                <label>验证码</label>
                <input type="text" id="modal-email-code" class="minimal-input" placeholder="6 位数字" maxlength="6" pattern="\d*">
            </div>
            <button type="button" id="modal-email-submit" class="btn-submit">确认更换</button>
        </div>
    </div>

    <!-- 更换头像弹窗 -->
    <div id="modal-avatar" class="modal-overlay">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h4>更换头像</h4>
            <p class="text-xs text-gray-500 mb-4">上传图片（JPG/PNG/GIF/WEBP，不超过 2MB）或填写图片地址。</p>
            <div class="mb-4">
                <label>图片地址（选填）</label>
                <input type="url" id="modal-avatar-url" class="minimal-input" placeholder="https://example.com/avatar.jpg">
            </div>
            <div class="mb-4">
                <label>或上传图片</label>
                <input type="file" id="modal-avatar-file" class="minimal-input text-xs" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <button type="button" id="modal-avatar-submit" class="btn-submit">确认更换</button>
            <button type="button" id="modal-avatar-clear" class="mt-2 w-full py-2 text-xs text-gray-500 hover:text-red-600 border border-gray-200 hover:border-red-200 transition">清除头像</button>
        </div>
    </div>

    <!-- 更换手机弹窗 -->
    <div id="modal-phone" class="modal-overlay">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h4>更换手机号</h4>
            <p class="text-xs text-gray-500 mb-4">我们将向新手机号发送验证码，验证通过后即可更新。</p>
            <div class="mb-4">
                <label>新手机号</label>
                <div class="flex-row">
                    <input type="tel" id="modal-phone-input" class="minimal-input" placeholder="13800138000" maxlength="11">
                    <button type="button" id="modal-phone-send" class="btn-send">发送验证码</button>
                </div>
            </div>
            <div class="mb-4">
                <label>验证码</label>
                <input type="text" id="modal-phone-code" class="minimal-input" placeholder="6 位数字" maxlength="6" pattern="\d*">
            </div>
            <button type="button" id="modal-phone-submit" class="btn-submit">确认更换</button>
        </div>
    </div>

    <script>
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        let meData = null;

        function toast(msg, type) {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.className = 'toast show';
            setTimeout(() => el.classList.remove('show'), 2500);
        }

        function avatarUrl(av) {
            if (!av) return '';
            if (/^https?:\/\//i.test(av)) return av;
            return av.startsWith('/') ? av : '/' + av;
        }

        function updateAvatarDisplay(avatar, letter) {
            const letterStr = (letter || 'U').charAt(0).toUpperCase();
            const imgEl = document.getElementById('profile-avatar-img');
            const letterEl = document.getElementById('profile-avatar-letter');
            const navImg = document.getElementById('nav-avatar-img');
            const navLetter = document.getElementById('nav-avatar-letter');
            if (avatar) {
                const url = avatarUrl(avatar);
                imgEl.src = url;
                imgEl.style.display = 'block';
                imgEl.onerror = () => { imgEl.style.display = 'none'; letterEl.style.display = 'flex'; letterEl.textContent = letterStr; };
                letterEl.style.display = 'none';
                navImg.src = url;
                navImg.style.display = 'block';
                navImg.onerror = () => { navImg.style.display = 'none'; navLetter.style.display = 'flex'; navLetter.textContent = letterStr; };
                navLetter.style.display = 'none';
            } else {
                imgEl.style.display = 'none';
                letterEl.style.display = 'flex';
                letterEl.textContent = letterStr;
                navImg.style.display = 'none';
                navLetter.style.display = 'flex';
                navLetter.textContent = letterStr;
            }
        }

        async function loadMe() {
            const res = await fetch('api/me.php', { credentials: 'same-origin' });
            const json = await res.json();
            if (json.code !== 0) {
                toast(json.message || '加载失败', 'error');
                return;
            }
            meData = json.data;
            document.getElementById('input-display-name').value = meData.display_name || '';
        document.getElementById('input-user-id').value = meData.id;
            document.getElementById('profile-email').textContent = meData.email || '未设置';
            document.getElementById('profile-phone').textContent = meData.phone ? meData.phone.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2') : '未设置';
            document.getElementById('nav-display-name').textContent = meData.display_name || meData.username;
            document.getElementById('nav-username').textContent = meData.username || '-';
            updateAvatarDisplay(meData.avatar, meData.display_name || meData.username);
            updateMfaSection(meData.mfa_enabled);
        }

        function updateMfaSection(enabled) {
            const statusEl = document.getElementById('mfa-status-text');
            const actionEl = document.getElementById('mfa-action');
            if (enabled) {
                statusEl.textContent = 'MFA 双因素认证已启用';
                actionEl.innerHTML = '<button type="button" id="btn-disable-mfa" class="text-xs text-red-500 hover:text-red-700 border-b border-transparent hover:border-red-500">关闭 MFA</button>';
                document.getElementById('btn-disable-mfa')?.addEventListener('click', showDisableMfaModal);
            } else {
                statusEl.textContent = 'MFA 未启用';
                actionEl.innerHTML = '<a href="mfa_setup.php" class="text-xs text-gray-500 hover:text-black">开启 MFA →</a>';
            }
        }

        function showDisableMfaModal() {
            const pw = prompt('请输入密码以关闭 MFA：');
            if (pw === null) return;
            doDisableMfa(pw, null);
        }

        async function doDisableMfa(password, code) {
            const body = password ? { password } : { code };
            const res = await fetch('api/me/mfa/disable.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const json = await res.json();
            if (json.code === 0) {
                toast('MFA 已关闭');
                meData.mfa_enabled = false;
                updateMfaSection(false);
            } else {
                toast(json.message || '操作失败', 'error');
            }
        }

        document.getElementById('btn-save-profile').addEventListener('click', async () => {
            const displayName = document.getElementById('input-display-name').value.trim();
            const btn = document.getElementById('btn-save-profile');
            btn.disabled = true;
            const res = await fetch('api/me/update.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify({ display_name: displayName || null }),
            });
            const json = await res.json();
            btn.disabled = false;
            if (json.code === 0) {
                toast('保存成功');
                meData = json.data;
                document.getElementById('nav-display-name').textContent = meData.display_name || meData.username;
                updateAvatarDisplay(meData.avatar, meData.display_name || meData.username);
            } else {
                toast(json.message || '保存失败', 'error');
            }
        });

        async function loadSessions() {
            const res = await fetch('api/me/sessions.php', { credentials: 'same-origin' });
            const json = await res.json();
            const container = document.getElementById('sessions-list');
            if (json.code !== 0) {
                container.innerHTML = '<p class="text-sm text-red-500">' + (json.message || '加载失败') + '</p>';
                return;
            }
            const sessions = json.data.sessions || [];
            if (sessions.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-500">暂无会话</p>';
                return;
            }
            container.innerHTML = sessions.map(s => {
                const deviceIcon = s.device.includes('iPhone') || s.device.includes('Android') ? 'ri-smartphone-line' : 'ri-macbook-line';
                const statusBadge = s.is_current ? '<span class="text-xs text-green-600 uppercase tracking-wider">Current</span>' : 
                    (s.is_expired ? '<span class="text-xs text-gray-400">已过期</span>' : 
                    '<button type="button" class="revoke-btn text-xs text-red-500 border border-red-200 px-3 py-1 bg-white hover:bg-red-500 hover:text-white transition uppercase tracking-widest" data-id="' + s.id + '">Revoke</button>');
                return '<div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-100 session-item" data-id="' + s.id + '">' +
                    '<div class="flex items-center gap-4">' +
                    '<i class="' + deviceIcon + ' text-2xl"></i>' +
                    '<div><p class="text-sm font-bold flex items-center gap-2">' + s.device + (s.is_current ? ' <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>' : '') + '</p>' +
                    '<p class="text-xs text-gray-400 font-mono">' + (s.user_agent || '-') + ' · ' + (s.ip || '-') + '</p></div></div>' + statusBadge + '</div>';
            }).join('');

            container.querySelectorAll('.revoke-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('确定要撤销该设备会话吗？')) return;
                    const id = btn.dataset.id;
                    const res = await fetch('api/me/sessions/revoke.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: id }),
                    });
                    const json = await res.json();
                    if (json.code === 0) {
                        toast('已撤销');
                        const item = container.querySelector('.session-item[data-id="' + id + '"]');
                        if (item) item.remove();
                    } else {
                        toast(json.message || '操作失败', 'error');
                    }
                });
            });
        }

        const PROVIDERS = {
            github: { name: 'GitHub', icon: 'ri-github-fill', color: 'text-black', bg: 'bg-gray-100' },
            wechat: { name: '微信', icon: 'ri-wechat-fill', color: 'text-[#07C160]', bg: 'bg-[#07C160]/10' },
            feishu: { name: '飞书', icon: 'ri-file-list-3-fill', color: 'text-[#3370FF]', bg: 'bg-[#3370FF]/10' },
            dingtalk: { name: '钉钉', icon: 'ri-chat-smile-3-fill', color: 'text-[#0089FF]', bg: 'bg-[#0089FF]/10' },
            wecom: { name: '企业微信', icon: 'ri-wechat-2-fill', color: 'text-[#2DC84B]', bg: 'bg-[#2DC84B]/10' },
        };

        async function loadConnections() {
            const res = await fetch('api/me/connections.php', { credentials: 'same-origin' });
            const json = await res.json();
            const container = document.getElementById('connections-list');
            if (json.code !== 0) {
                container.innerHTML = '<p class="text-sm text-red-500">' + (json.message || '加载失败') + '</p>';
                return;
            }
            const conns = json.data.connections || [];
            const bound = {};
            conns.forEach(c => { bound[c.provider] = c; });
            const html = Object.keys(PROVIDERS).map(provider => {
                const p = PROVIDERS[provider];
                const c = bound[provider];
                if (c) {
                    return '<div class="flex items-center justify-between group"><div class="flex items-center gap-4"><div class="icon-box ' + p.bg + ' ' + p.color + '"><i class="' + p.icon + ' text-xl"></i></div><div><p class="text-sm font-bold">' + p.name + '</p><p class="text-xs text-gray-500 font-mono mt-0.5">' + (c.username || c.email || '已连接') + '</p></div></div><button type="button" class="unbind-btn text-xs text-red-500 border border-red-200 px-3 py-1 hover:bg-red-500 hover:text-white transition" data-provider="' + provider + '">解除绑定</button></div>';
                }
                return '<div class="flex items-center justify-between group"><div class="flex items-center gap-4"><div class="icon-box bg-gray-50 text-gray-600"><i class="' + p.icon + ' text-xl"></i></div><div><p class="text-sm font-bold">' + p.name + '</p><p class="text-xs text-gray-400 mt-0.5">未绑定</p></div></div><button type="button" class="bind-btn btn-outline" data-provider="' + provider + '">立即绑定</button></div>';
            }).join('');
            container.innerHTML = html || '<p class="text-sm text-gray-500">暂无支持的平台</p>';
            container.querySelectorAll('.bind-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const provider = btn.dataset.provider;
                    const res = await fetch('api/me/connections/bind.php?provider=' + provider, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() }, credentials: 'same-origin' });
                    const json = await res.json();
                    if (json.code === 0 && json.data && json.data.auth_url) {
                        location.href = json.data.auth_url;
                    } else {
                        toast(json.message || '绑定失败', 'error');
                    }
                });
            });
            container.querySelectorAll('.unbind-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('确定要解除绑定吗？')) return;
                    const provider = btn.dataset.provider;
                    const res = await fetch('api/me/connections/unbind.php?provider=' + provider, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() }, credentials: 'same-origin' });
                    const json = await res.json();
                    if (json.code === 0) {
                        toast('已解除绑定');
                        loadConnections();
                    } else {
                        toast(json.message || '操作失败', 'error');
                    }
                });
            });
        }

        function openModal(id) {
            document.getElementById(id).classList.add('show');
            document.addEventListener('keydown', escClose);
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
            document.removeEventListener('keydown', escClose);
        }
        function escClose(e) {
            if (e.key === 'Escape') {
                closeModal('modal-email');
                closeModal('modal-phone');
                closeModal('modal-avatar');
            }
        }
        document.getElementById('modal-email').addEventListener('click', () => closeModal('modal-email'));
        document.getElementById('modal-phone').addEventListener('click', () => closeModal('modal-phone'));
        document.getElementById('modal-avatar').addEventListener('click', () => closeModal('modal-avatar'));

        document.getElementById('btn-change-email').addEventListener('click', () => {
            document.getElementById('modal-email-input').value = '';
            document.getElementById('modal-email-code').value = '';
            document.getElementById('modal-email-send').disabled = false;
            document.getElementById('modal-email-send').textContent = '发送验证码';
            openModal('modal-email');
        });
        document.getElementById('btn-change-phone').addEventListener('click', () => {
            document.getElementById('modal-phone-input').value = '';
            document.getElementById('modal-phone-code').value = '';
            document.getElementById('modal-phone-send').disabled = false;
            document.getElementById('modal-phone-send').textContent = '发送验证码';
            openModal('modal-phone');
        });
        document.getElementById('btn-change-avatar').addEventListener('click', () => {
            document.getElementById('modal-avatar-url').value = '';
            document.getElementById('modal-avatar-file').value = '';
            openModal('modal-avatar');
        });

        document.getElementById('modal-avatar-submit').addEventListener('click', async function() {
            const url = document.getElementById('modal-avatar-url').value.trim();
            const fileInput = document.getElementById('modal-avatar-file');
            const file = fileInput.files?.[0];
            if (!url && !file) {
                toast('请上传图片或填写图片地址', 'error');
                return;
            }
            const btn = this;
            btn.disabled = true;
            try {
                if (file) {
                    const fd = new FormData();
                    fd.append('avatar', file);
                    const res = await fetch('api/me/avatar/upload.php', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': csrfToken() },
                        credentials: 'same-origin',
                        body: fd,
                    });
                    const json = await res.json();
                    if (json.code === 0) {
                        meData.avatar = json.data.avatar;
                        updateAvatarDisplay(meData.avatar, meData.display_name || meData.username);
                        toast('头像已更新');
                        closeModal('modal-avatar');
                    } else {
                        toast(json.message || '上传失败', 'error');
                    }
                } else {
                    if (!/^https?:\/\/.+/i.test(url)) {
                        toast('请输入有效的图片地址', 'error');
                        btn.disabled = false;
                        return;
                    }
                    const res = await fetch('api/me/update.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                        credentials: 'same-origin',
                        body: JSON.stringify({ avatar: url }),
                    });
                    const json = await res.json();
                    if (json.code === 0) {
                        meData.avatar = json.data.avatar;
                        updateAvatarDisplay(meData.avatar, meData.display_name || meData.username);
                        toast('头像已更新');
                        closeModal('modal-avatar');
                    } else {
                        toast(json.message || '更新失败', 'error');
                    }
                }
            } finally {
                btn.disabled = false;
            }
        });
        document.getElementById('modal-avatar-clear').addEventListener('click', async function() {
            const res = await fetch('api/me/update.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify({ avatar: '' }),
            });
            const json = await res.json();
            if (json.code === 0) {
                meData.avatar = null;
                updateAvatarDisplay(null, meData.display_name || meData.username);
                toast('头像已清除');
                closeModal('modal-avatar');
            } else {
                toast(json.message || '操作失败', 'error');
            }
        });

        document.getElementById('modal-email-send').addEventListener('click', async function() {
            const email = document.getElementById('modal-email-input').value.trim().toLowerCase();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                toast('请输入有效的邮箱地址', 'error');
                return;
            }
            const btn = this;
            btn.disabled = true;
            const res = await fetch('api/me/send-email-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify({ email }),
            });
            const json = await res.json();
            if (json.code === 0) {
                toast('验证码已发送');
                let t = 60;
                const iv = setInterval(() => {
                    t--;
                    btn.textContent = t + ' 秒后重发';
                    if (t <= 0) { clearInterval(iv); btn.disabled = false; btn.textContent = '发送验证码'; }
                }, 1000);
            } else {
                toast(json.message || '发送失败', 'error');
                btn.disabled = false;
            }
        });

        document.getElementById('modal-phone-send').addEventListener('click', async function() {
            const raw = document.getElementById('modal-phone-input').value.trim();
            const phone = raw.replace(/\D/g, '');
            if (phone.length !== 11 || !/^1[3-9]\d{9}$/.test(phone)) {
                toast('请输入有效的 11 位手机号', 'error');
                return;
            }
            const btn = this;
            btn.disabled = true;
            const res = await fetch('api/me/send-phone-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify({ phone }),
            });
            const json = await res.json();
            if (json.code === 0) {
                toast('验证码已发送');
                let t = 60;
                const iv = setInterval(() => {
                    t--;
                    btn.textContent = t + ' 秒后重发';
                    if (t <= 0) { clearInterval(iv); btn.disabled = false; btn.textContent = '发送验证码'; }
                }, 1000);
            } else {
                toast(json.message || '发送失败', 'error');
                btn.disabled = false;
            }
        });

        document.getElementById('modal-email-submit').addEventListener('click', async function() {
            const email = document.getElementById('modal-email-input').value.trim().toLowerCase();
            const code = document.getElementById('modal-email-code').value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                toast('请输入有效的邮箱地址', 'error');
                return;
            }
            if (code.length !== 6 || !/^\d+$/.test(code)) {
                toast('请输入 6 位数字验证码', 'error');
                return;
            }
            const btn = this;
            btn.disabled = true;
            const res = await fetch('api/me/verify-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify({ email, code }),
            });
            const json = await res.json();
            btn.disabled = false;
            if (json.code === 0) {
                toast('邮箱已更新');
                closeModal('modal-email');
                meData.email = json.data.email;
                document.getElementById('profile-email').textContent = json.data.email;
            } else {
                toast(json.message || '更新失败', 'error');
            }
        });

        document.getElementById('modal-phone-submit').addEventListener('click', async function() {
            const raw = document.getElementById('modal-phone-input').value.trim();
            const phone = raw.replace(/\D/g, '');
            const code = document.getElementById('modal-phone-code').value.trim();
            if (phone.length !== 11 || !/^1[3-9]\d{9}$/.test(phone)) {
                toast('请输入有效的 11 位手机号', 'error');
                return;
            }
            if (code.length !== 6 || !/^\d+$/.test(code)) {
                toast('请输入 6 位数字验证码', 'error');
                return;
            }
            const btn = this;
            btn.disabled = true;
            const res = await fetch('api/me/verify-phone.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify({ phone, code }),
            });
            const json = await res.json();
            btn.disabled = false;
            if (json.code === 0) {
                toast('手机号已更新');
                closeModal('modal-phone');
                meData.phone = json.data.phone;
                document.getElementById('profile-phone').textContent = json.data.phone.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2');
            } else {
                toast(json.message || '更新失败', 'error');
            }
        });

        loadMe();
        loadSessions();
        loadConnections();

        const urlParams = new URLSearchParams(location.search);
        if (urlParams.get('connect_success')) {
            toast('绑定成功');
            loadConnections();
            history.replaceState({}, '', location.pathname);
        }
        if (urlParams.get('connect_error')) {
            const err = urlParams.get('connect_error');
            const msg = { already_bound: '该账号已被其他用户绑定', session_expired: '会话已过期，请重新登录', config_missing: '配置缺失', token_failed: '授权失败', user_failed: '获取用户信息失败', server_error: '服务错误', invalid_callback: '回调参数无效', invalid_state: '请求已过期，请重试' }[err] || err;
            toast(msg, 'error');
            history.replaceState({}, '', location.pathname);
        }
    </script>
</body>
</html>
