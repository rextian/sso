<?php
/**
 * REXTIAN SSO - 用户自助服务门户
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
    <title>用户中心 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f9fafb; color: #111; }
        .font-serif { font-family: "Songti SC", "SimSun", serif; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .minimal-input { width: 100%; border: none; border-bottom: 1px solid #e5e7eb; padding: 10px 0; background: transparent; outline: none; transition: border-color 0.3s; }
        .minimal-input:focus { border-bottom-color: #000; }
        .card { background: white; border: 1px solid #f3f4f6; padding: 1.5rem; margin-bottom: 1.5rem; transition: box-shadow 0.3s; }
        .card:hover { box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05); }
        .icon-box { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .btn-outline { border: 1px solid #e5e7eb; padding: 6px 16px; font-size: 0.75rem; transition: all 0.2s; }
        .btn-outline:hover { border-color: #000; background: #000; color: #fff; }
        .btn-primary { background: #000; color: #fff; padding: 8px 20px; font-size: 0.8rem; transition: all 0.2s; }
        .btn-primary:hover { opacity: 0.85; }
        .toast { position: fixed; bottom: 2rem; right: 2rem; padding: 12px 20px; background: #000; color: #fff; font-size: 0.8rem; border-radius: 4px; opacity: 0; transform: translateY(10px); transition: all 0.3s; z-index: 9999; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .tab-btn { padding: 8px 16px; font-size: 0.85rem; border-bottom: 2px solid transparent; transition: all 0.2s; color: #9ca3af; }
        .tab-btn:hover { color: #000; }
        .tab-btn.active { color: #000; border-bottom-color: #000; font-weight: 500; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .table-row:hover { background-color: #f9fafb; }
        .status-badge { padding: 2px 8px; font-size: 0.7rem; border-radius: 9999px; }
    </style>
</head>
<body class="min-h-screen pb-12">

    <nav class="bg-white border-b border-gray-100 px-8 py-4 flex justify-between items-center sticky top-0 z-50 shadow-sm shadow-gray-100/50">
        <a href="index.php" class="font-serif text-xl font-bold tracking-widest cursor-pointer hover:opacity-70">REXTIAN</a>
        <div class="flex items-center gap-4">
            <a href="user_profile.php" class="text-xs text-gray-500 hover:text-black">账户设置</a>
            <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php" class="text-xs text-gray-500 hover:text-black uppercase tracking-wider">管理后台</a>
            <?php endif; ?>
            <a href="logout.php" class="text-[10px] text-gray-400 hover:text-black">退出登录</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto py-12 px-6">
        
        <div class="mb-10">
            <h1 class="font-serif text-3xl mb-2">用户中心</h1>
            <p class="text-xs text-gray-400 uppercase tracking-widest">User Dashboard</p>
        </div>

        <div class="flex items-center gap-2 mb-8 border-b border-gray-100">
            <button class="tab-btn active" data-tab="overview">概览</button>
            <button class="tab-btn" data-tab="logins">登录历史</button>
            <button class="tab-btn" data-tab="radius">RADIUS 会话</button>
            <button class="tab-btn" data-tab="apps">授权应用</button>
            <button class="tab-btn" data-tab="security">安全设置</button>
        </div>

        <div id="panel-overview" class="tab-panel active">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card rounded-sm">
                    <div class="flex items-center gap-4">
                        <div class="icon-box bg-gray-50 text-gray-600"><i class="ri-shield-user-line text-xl"></i></div>
                        <div>
                            <p class="text-2xl font-bold" id="stat-logins">0</p>
                            <p class="text-xs text-gray-500">最近登录</p>
                        </div>
                    </div>
                </div>
                <div class="card rounded-sm">
                    <div class="flex items-center gap-4">
                        <div class="icon-box bg-blue-50 text-blue-600"><i class="ri-wifi-2-line text-xl"></i></div>
                        <div>
                            <p class="text-2xl font-bold" id="stat-radius">0</p>
                            <p class="text-xs text-gray-500">RADIUS 会话</p>
                        </div>
                    </div>
                </div>
                <div class="card rounded-sm">
                    <div class="flex items-center gap-4">
                        <div class="icon-box bg-green-50 text-green-600"><i class="ri-apps-line text-xl"></i></div>
                        <div>
                            <p class="text-2xl font-bold" id="stat-apps">0</p>
                            <p class="text-xs text-gray-500">授权应用</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card rounded-sm">
                <h3 class="font-serif text-lg mb-4">快速操作</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="user_profile.php" class="flex flex-col items-center p-4 border border-gray-100 rounded hover:bg-gray-50 transition">
                        <i class="ri-user-settings-line text-2xl mb-2 text-gray-600"></i>
                        <span class="text-xs text-gray-700">账户设置</span>
                    </a>
                    <button class="flex flex-col items-center p-4 border border-gray-100 rounded hover:bg-gray-50 transition" onclick="switchTab('logins')">
                        <i class="ri-history-line text-2xl mb-2 text-gray-600"></i>
                        <span class="text-xs text-gray-700">登录历史</span>
                    </button>
                    <button class="flex flex-col items-center p-4 border border-gray-100 rounded hover:bg-gray-50 transition" onclick="switchTab('radius')">
                        <i class="ri-wifi-line text-2xl mb-2 text-gray-600"></i>
                        <span class="text-xs text-gray-700">RADIUS 会话</span>
                    </button>
                    <button class="flex flex-col items-center p-4 border border-gray-100 rounded hover:bg-gray-50 transition" onclick="switchTab('apps')">
                        <i class="ri-apps-2-line text-2xl mb-2 text-gray-600"></i>
                        <span class="text-xs text-gray-700">授权应用</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="panel-logins" class="tab-panel">
            <div class="card rounded-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-serif text-lg">登录历史</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                <th class="font-normal py-3">时间</th>
                                <th class="font-normal py-3">IP 地址</th>
                                <th class="font-normal py-3">设备</th>
                                <th class="font-normal py-3">状态</th>
                            </tr>
                        </thead>
                        <tbody id="logins-tbody">
                            <tr><td colspan="4" class="py-8 text-center text-gray-400">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panel-radius" class="tab-panel">
            <div class="card rounded-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-serif text-lg">RADIUS 会话记录</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                <th class="font-normal py-3">NAS</th>
                                <th class="font-normal py-3">MAC 地址</th>
                                <th class="font-normal py-3">开始时间</th>
                                <th class="font-normal py-3">时长</th>
                                <th class="font-normal py-3">流量</th>
                                <th class="font-normal py-3">状态</th>
                            </tr>
                        </thead>
                        <tbody id="radius-tbody">
                            <tr><td colspan="6" class="py-8 text-center text-gray-400">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panel-apps" class="tab-panel">
            <div class="card rounded-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-serif text-lg">授权应用</h3>
                </div>
                <div id="apps-list">
                    <p class="text-sm text-gray-400">加载中...</p>
                </div>
            </div>
        </div>

        <div id="panel-security" class="tab-panel">
            <div class="card rounded-sm">
                <h3 class="font-serif text-lg mb-6">安全设置</h3>
                
                <div class="space-y-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="icon-box bg-gray-100 text-gray-600">
                                <i class="ri-lock-password-line text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold">修改密码</p>
                                <p class="text-xs text-gray-500">定期更换密码以保护账户安全</p>
                            </div>
                        </div>
                        <button type="button" id="btn-change-password" class="btn-outline">修改</button>
                    </div>
                    
                    <div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="icon-box bg-gray-100 text-gray-600">
                                <i class="ri-shield-check-line text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold">双因素认证 (MFA)</p>
                                <p class="text-xs text-gray-500" id="mfa-status-text">启用后更安全</p>
                            </div>
                        </div>
                        <span id="mfa-action"></span>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <div id="toast" class="toast"></div>

    <div id="modal-password" class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 max-w-md w-full mx-4 shadow-2xl" onclick="event.stopPropagation()">
            <h4 class="font-serif text-lg mb-6">修改密码</h4>
            <div class="space-y-4">
                <div>
                    <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">当前密码</label>
                    <input type="password" id="current-password" class="minimal-input" placeholder="请输入当前密码">
                </div>
                <div>
                    <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">新密码</label>
                    <input type="password" id="new-password" class="minimal-input" placeholder="至少 6 位">
                </div>
                <div>
                    <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">确认新密码</label>
                    <input type="password" id="confirm-password" class="minimal-input" placeholder="再次输入新密码">
                </div>
            </div>
            <div class="flex items-center gap-3 mt-6 pt-4 border-t border-gray-100">
                <button type="button" onclick="closePasswordModal()" class="flex-1 py-2 text-sm border border-gray-200 hover:bg-gray-50">取消</button>
                <button type="button" id="btn-submit-password" class="flex-1 py-2 text-sm bg-black text-white hover:bg-gray-800">确认修改</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        let meData = null;

        function toast(msg) {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.className = 'toast show';
            setTimeout(() => el.classList.remove('show'), 2500);
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabId);
            });
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.toggle('active', panel.id === 'panel-' + tabId);
            });
            
            if (tabId === 'logins') loadLogins();
            if (tabId === 'radius') loadRadiusSessions();
            if (tabId === 'apps') loadAuthorizedApps();
        }

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        async function loadMe() {
            const res = await fetch('api/me.php', { credentials: 'same-origin' });
            const json = await res.json();
            if (json.code === 0) {
                meData = json.data;
                updateMfaSection(meData.mfa_enabled);
            }
        }

        function updateMfaSection(enabled) {
            const statusEl = document.getElementById('mfa-status-text');
            const actionEl = document.getElementById('mfa-action');
            if (enabled) {
                statusEl.textContent = '已启用，账户更安全';
                actionEl.innerHTML = '<button type="button" id="btn-disable-mfa" class="text-xs text-red-500 hover:text-red-700 border-b border-transparent hover:border-red-500">关闭 MFA</button>';
                document.getElementById('btn-disable-mfa')?.addEventListener('click', () => {
                    const pw = prompt('请输入密码以关闭 MFA：');
                    if (pw) doDisableMfa(pw);
                });
            } else {
                statusEl.textContent = '未启用';
                actionEl.innerHTML = '<a href="mfa_setup.php" class="text-xs text-gray-500 hover:text-black border-b border-transparent hover:border-black">立即启用 →</a>';
            }
        }

        async function doDisableMfa(password) {
            const res = await fetch('api/me/mfa/disable.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                credentials: 'same-origin',
                body: JSON.stringify({ password }),
            });
            const json = await res.json();
            if (json.code === 0) {
                toast('MFA 已关闭');
                meData.mfa_enabled = false;
                updateMfaSection(false);
            } else {
                toast(json.message || '操作失败');
            }
        }

        async function loadStats() {
            const [loginsRes, radiusRes, appsRes] = await Promise.all([
                fetch('api/audit-logs.php?event=auth.login&limit=10', { credentials: 'same-origin' }),
                fetch('api/me/radius-sessions.php', { credentials: 'same-origin' }),
                fetch('api/me/authorized-apps.php', { credentials: 'same-origin' })
            ]);
            
            const loginsJson = await loginsRes.json();
            if (loginsJson.code === 0) {
                document.getElementById('stat-logins').textContent = loginsJson.data.total || 0;
            }
            
            const radiusJson = await radiusRes.json();
            if (radiusJson.code === 0) {
                document.getElementById('stat-radius').textContent = radiusJson.data.total || 0;
            }
            
            const appsJson = await appsRes.json();
            if (appsJson.code === 0) {
                document.getElementById('stat-apps').textContent = appsJson.data.apps?.length || 0;
            }
        }

        async function loadLogins() {
            const res = await fetch('api/audit-logs.php?event=auth.login&limit=20', { credentials: 'same-origin' });
            const json = await res.json();
            const tbody = document.getElementById('logins-tbody');
            
            if (json.code !== 0 || !json.data.logs?.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-gray-400">暂无登录记录</td></tr>';
                return;
            }
            
            tbody.innerHTML = json.data.logs.map(log => {
                const time = log.created_at ? new Date(log.created_at).toLocaleString('zh-CN') : '-';
                const statusBadge = log.status === 'success' 
                    ? '<span class="status-badge bg-green-50 text-green-600">成功</span>'
                    : '<span class="status-badge bg-red-50 text-red-600">失败</span>';
                return `<tr class="table-row border-b border-gray-50">
                    <td class="py-3 text-xs text-gray-500">${time}</td>
                    <td class="py-3 font-mono text-xs">${log.ip || '-'}</td>
                    <td class="py-3 text-xs text-gray-600">${log.user_agent ? log.user_agent.substring(0, 50) : '-'}</td>
                    <td class="py-3">${statusBadge}</td>
                </tr>`;
            }).join('');
        }

        async function loadRadiusSessions() {
            const tbody = document.getElementById('radius-tbody');
            try {
                const res = await fetch('api/me/radius-sessions.php?limit=20', { credentials: 'same-origin' });
                const json = await res.json();
                
                if (json.code !== 0 || !json.data.sessions?.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-gray-400">暂无 RADIUS 会话记录</td></tr>';
                    return;
                }
                
                tbody.innerHTML = json.data.sessions.map(session => {
                    const startTime = session.start_time ? new Date(session.start_time).toLocaleString('zh-CN') : '-';
                    let duration = session.session_time ? formatDuration(session.session_time);
                    let traffic = formatTraffic(session.input_octets, session.output_octets);
                    const statusBadge = session.acct_status_type === 'Start' 
                        ? '<span class="status-badge bg-green-50 text-green-600">进行中</span>'
                        : '<span class="status-badge bg-gray-50 text-gray-600">已结束</span>';
                    return `<tr class="table-row border-b border-gray-50">
                        <td class="py-3 text-xs text-gray-500">${session.nas_name || session.nas_ip_address || '-'}</td>
                        <td class="py-3 font-mono text-xs">${session.calling_station_id || '-'}</td>
                        <td class="py-3 text-xs text-gray-500">${startTime}</td>
                        <td class="py-3 text-xs text-gray-600">${duration}</td>
                        <td class="py-3 text-xs text-gray-600">${traffic}</td>
                        <td class="py-3">${statusBadge}</td>
                    </tr>`;
                }).join('');
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-gray-400">加载失败</td></tr>';
            }
        }

        function formatDuration(seconds) {
            if (!seconds) return '-';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            if (h > 0) return `${h}小时${m}分${s}秒`;
            if (m > 0) return `${m}分${s}秒`;
            return `${s}秒`;
        }

        function formatTraffic(inBytes, outBytes) {
            const total = (inBytes || 0) + (outBytes || 0);
            if (total === 0) return '-';
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let i = 0;
            let size = total;
            while (size >= 1024 && i < units.length - 1) {
                size /= 1024;
                i++;
            }
            return size.toFixed(2) + ' ' + units[i];
        }

        async function loadAuthorizedApps() {
            const list = document.getElementById('apps-list');
            try {
                const res = await fetch('api/me/authorized-apps.php', { credentials: 'same-origin' });
                const json = await res.json();
                
                if (json.code !== 0 || !json.data.apps?.length) {
                    list.innerHTML = '<p class="text-sm text-gray-400">暂无授权应用</p>';
                    return;
                }
                
                list.innerHTML = json.data.apps.map(app => {
                    const authTime = app.authorized_at ? new Date(app.authorized_at).toLocaleDateString('zh-CN') : '-';
                    return `<div class="flex items-center justify-between p-4 border border-gray-100 mb-3">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                                <i class="ri-apps-2-line text-gray-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold">${app.name || '未知应用'}</p>
                                <p class="text-xs text-gray-500">授权时间：${authTime}</p>
                            </div>
                        </div>
                        <a href="${app.redirect_uri || '#'}" target="_blank" class="text-xs text-gray-500 hover:text-black">访问应用</a>
                    </div>`;
                }).join('');
            } catch (e) {
                list.innerHTML = '<p class="text-sm text-gray-400">加载失败</p>';
            }
        }

        document.getElementById('btn-change-password').addEventListener('click', () => {
            document.getElementById('current-password').value = '';
            document.getElementById('new-password').value = '';
            document.getElementById('confirm-password').value = '';
            document.getElementById('modal-password').classList.remove('hidden');
        });

        function closePasswordModal() {
            document.getElementById('modal-password').classList.add('hidden');
        }

        document.getElementById('modal-password').addEventListener('click', (e) => {
            if (e.target.id === 'modal-password') closePasswordModal();
        });

        document.getElementById('btn-submit-password').addEventListener('click', async () => {
            const current = document.getElementById('current-password').value;
            const newPwd = document.getElementById('new-password').value;
            const confirm = document.getElementById('confirm-password').value;
            
            if (!current) { toast('请输入当前密码'); return; }
            if (!newPwd || newPwd.length < 6) { toast('新密码至少需要 6 位'); return; }
            if (newPwd !== confirm) { toast('两次输入的新密码不一致'); return; }
            
            const btn = document.getElementById('btn-submit-password');
            btn.disabled = true;
            
            try {
                const res = await fetch('api/me/change-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    credentials: 'same-origin',
                    body: JSON.stringify({ current_password: current, new_password: newPwd })
                });
                const json = await res.json();
                if (json.code === 0) {
                    toast('密码修改成功');
                    closePasswordModal();
                } else {
                    toast(json.message || '修改失败');
                }
            } catch (e) {
                toast('网络错误');
            } finally {
                btn.disabled = false;
            }
        });

        loadMe();
        loadStats();
    </script>
</body>
</html>
