<?php
/**
 * REXTIAN SSO - 审计日志
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireAdmin(basename($_SERVER['PHP_SELF']));
$current_page = 'auditlog';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>审计日志 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background-color: #fcfcfc; color: #111; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        ::-webkit-scrollbar { width: 0; background: transparent; }
        .drawer-overlay { opacity: 0; pointer-events: none; transition: opacity 0.3s; z-index: 50; }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        .drawer-panel { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .drawer-overlay.open .drawer-panel { transform: translateX(0); }
        .log-row:hover { background-color: #f9fafb; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="w-20 lg:w-64 border-r border-gray-100 flex flex-col py-8 items-center lg:items-start lg:pl-10 hidden md:flex">
        <a href="admin_dashboard.php" class="font-serif text-xl font-bold tracking-widest text-black mb-12">REXTIAN</a>
        <nav class="space-y-6 w-full">
            <a href="admin_dashboard.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-dashboard-line mr-3"></i><span class="hidden lg:inline">仪表盘</span></a>
            <a href="user.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-user-settings-line mr-3"></i><span class="hidden lg:inline">用户管理</span></a>
            <a href="oauth.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-apps-line mr-3"></i><span class="hidden lg:inline">应用接入</span></a>
            <a href="authorizations.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-shield-check-line mr-3"></i><span class="hidden lg:inline">授权管理</span></a>
            <div class="text-black font-medium flex items-center lg:w-full border-l-2 border-black pl-3 -ml-3 lg:ml-0 lg:border-l-0 lg:pl-0"><i class="ri-file-list-3-line mr-3"></i><span class="hidden lg:inline">审计日志</span></div>
            <a href="settings.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-settings-4-line mr-3"></i><span class="hidden lg:inline">系统设置</span></a>
        </nav>
        <div class="mt-auto flex items-center gap-3 border-t border-gray-100 pt-6">
            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-serif">A</div>
            <a href="logout.php" class="text-[10px] text-gray-400 hover:text-black">退出登录</a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto relative bg-white">
        <header class="sticky top-0 bg-white/90 backdrop-blur-sm z-20 px-8 py-6 border-b border-gray-50 flex justify-between items-end">
            <div>
                <h1 class="font-serif text-3xl mb-1 text-black">操作审计</h1>
                <p id="header-summary" class="text-xs text-gray-400 tracking-wider uppercase">操作审计 • 加载中...</p>
            </div>
            <div class="flex flex-wrap gap-4 items-end">
                <div><label class="text-[10px] text-gray-400 block mb-1">操作</label><input type="text" id="filter-event" class="text-xs py-2 border-b border-gray-200 focus:border-black outline-none w-32 bg-transparent" placeholder="登录/用户/应用"></div>
                <div><label class="text-[10px] text-gray-400 block mb-1">用户</label><input type="text" id="filter-user" class="text-xs py-2 border-b border-gray-200 focus:border-black outline-none w-36 bg-transparent" placeholder="用户名/邮箱"></div>
                <div><label class="text-[10px] text-gray-400 block mb-1">状态</label><select id="filter-status" class="text-xs py-2 border-b border-gray-200 focus:border-black outline-none bg-transparent w-24"><option value="">全部</option><option value="success">成功</option><option value="failed">失败</option></select></div>
                <div><label class="text-[10px] text-gray-400 block mb-1">开始日期</label><input type="date" id="filter-start-date" class="text-xs py-2 border-b border-gray-200 focus:border-black outline-none bg-transparent"></div>
                <div><label class="text-[10px] text-gray-400 block mb-1">结束日期</label><input type="date" id="filter-end-date" class="text-xs py-2 border-b border-gray-200 focus:border-black outline-none bg-transparent"></div>
                <button id="btn-search" class="text-xs border border-gray-200 px-4 py-2 hover:border-black transition">筛选</button>
                <a id="btn-export" href="api/audit-logs/export.php?format=csv" target="_blank" class="text-xs border border-black px-4 py-2 hover:bg-black hover:text-white transition">导出 CSV</a>
                <button id="btn-cleanup" class="text-xs border border-red-500 text-red-500 px-4 py-2 hover:bg-red-500 hover:text-white transition">清除日志</button>
            </div>
        </header>

        <div class="p-8 max-w-7xl mx-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[10px] text-gray-400 uppercase tracking-widest border-b border-gray-200">
                        <th class="font-normal py-3 w-16">状态</th>
                        <th class="font-normal py-3">操作</th>
                        <th class="font-normal py-3">用户</th>
                        <th class="font-normal py-3">时间</th>
                        <th class="font-normal py-3">追踪 ID</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody" class="text-sm">
                    <tr><td colspan="5" class="py-12 text-center text-gray-400">加载中...</td></tr>
                </tbody>
            </table>
            <div class="mt-6 flex justify-between items-center">
                <span id="pagination-info" class="text-xs text-gray-400">-</span>
                <div class="flex gap-2">
                    <button id="btn-prev" class="text-xs px-3 py-1 border border-gray-200 hover:border-black transition disabled:opacity-50" disabled>上一页</button>
                    <button id="btn-next" class="text-xs px-3 py-1 border border-gray-200 hover:border-black transition disabled:opacity-50">下一页</button>
                </div>
            </div>
        </div>
    </main>

    <div id="drawer-detail" class="drawer-overlay fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/20 backdrop-blur-[2px]" onclick="closeDrawer()"></div>
        <div class="drawer-panel w-full max-w-lg h-full bg-white shadow-2xl relative z-10 flex flex-col">
            <div class="px-8 py-6 border-b border-gray-50 bg-gray-50">
                <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1">追踪 ID</p>
                <p id="detail-trace_id" class="text-black font-bold font-mono">-</p>
            </div>
            <div class="p-8 space-y-6 flex-1 overflow-y-auto">
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div><span class="text-gray-400 block">操作</span><span id="detail-event" class="font-mono">-</span></div>
                    <div><span class="text-gray-400 block">状态</span><span id="detail-status" class="font-mono">-</span></div>
                    <div><span class="text-gray-400 block">时间</span><span id="detail-time" class="font-mono">-</span></div>
                    <div><span class="text-gray-400 block">IP</span><span id="detail-ip" class="font-mono">-</span></div>
                    <div class="col-span-2"><span class="text-gray-400 block">用户</span><span id="detail-user" class="font-mono">-</span></div>
                </div>
                <div><span class="text-gray-400 block text-xs mb-2">附加数据</span><pre id="detail-payload" class="bg-[#1e1e1e] text-[#d4d4d4] p-4 rounded text-xs overflow-x-auto font-mono">-</pre></div>
            </div>
        </div>
    </div>

    <div id="modal-cleanup" class="fixed inset-0 z-[60] hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50" onclick="closeCleanupModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-8">
            <h3 class="font-serif text-xl mb-4">清除审计日志</h3>
            <p class="text-sm text-gray-600 mb-6">请选择要保留的天数，此操作将删除指定天数之前的所有日志。</p>
            <div class="mb-6">
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-2">保留天数</label>
                <select id="cleanup-days" class="w-full border border-gray-200 py-3 px-4 rounded focus:border-black outline-none">
                    <option value="7">保留最近 7 天</option>
                    <option value="30">保留最近 30 天</option>
                    <option value="90" selected>保留最近 90 天</option>
                    <option value="180">保留最近 180 天</option>
                    <option value="365">保留最近 1 年</option>
                    <option value="0">清除所有日志</option>
                </select>
            </div>
            <div class="flex gap-4">
                <button onclick="closeCleanupModal()" class="flex-1 border border-gray-200 py-3 text-sm hover:border-black transition">取消</button>
                <button onclick="doCleanup()" class="flex-1 bg-red-600 text-white py-3 text-sm hover:bg-red-700 transition">确认清除</button>
            </div>
        </div>
    </div>

    <script>
        let state = { page: 1, limit: 20, event: '', user: '', status: '', startDate: '', endDate: '', total: 0, totalPages: 1 };

        const EVENT_LABELS = {
            'auth.login.success': '登录成功',
            'auth.login.failed': '登录失败',
            'auth.logout': '退出登录',
            'auth.sms.sent': '短信验证码已发送',
            'auth.sms.failed': '短信发送失败',
            'auth.login.mfa_failed': 'MFA 验证失败',
            'user.register': '用户注册',
            'user.create': '创建用户',
            'user.update': '更新用户',
            'user.delete': '删除用户',
            'user.profile.update': '更新个人资料',
            'user.mfa.enabled': '启用 MFA',
            'user.mfa.verify.failed': 'MFA 验证失败',
            'user.mfa.disabled': '关闭 MFA',
            'user.mfa.disable.failed': '关闭 MFA 失败',
            'user.session.revoke': '撤销会话',
            'user.connection.bind': '绑定第三方账号',
            'user.connection.unbind': '解除第三方绑定',
            'app.create': '创建应用',
            'app.update': '更新应用',
            'app.delete': '删除应用',
            'app.reset_secret': '重置应用密钥',
            'system.config.update': '更新系统配置',
            'audit.cleanup': '清理过期日志'
        };
        function eventLabel(code) {
            return EVENT_LABELS[code] || code;
        }
        function statusLabel(s) {
            return s === 'success' ? '成功' : (s === 'failed' ? '失败' : s);
        }

        function loadLogs() {
            const params = new URLSearchParams({ page: state.page, limit: state.limit });
            if (state.event) params.set('event', state.event);
            if (state.user) params.set('user', state.user);
            if (state.status) params.set('status', state.status);
            if (state.startDate) params.set('start_date', state.startDate);
            if (state.endDate) params.set('end_date', state.endDate);
            fetch('api/audit-logs.php?' + params, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res.code !== 0) return;
                    const d = res.data;
                    state.total = d.total;
                    state.totalPages = d.total_pages || 1;
                    document.getElementById('header-summary').textContent = '操作审计 • 共 ' + d.total + ' 条';
                    renderLogs(d.logs);
                    document.getElementById('pagination-info').textContent = '第 ' + state.page + ' / ' + state.totalPages + ' 页';
                    document.getElementById('btn-prev').disabled = state.page <= 1;
                    document.getElementById('btn-next').disabled = state.page >= state.totalPages;
                    document.getElementById('btn-export').href = 'api/audit-logs/export.php?format=csv&' + params.toString();
                });
        }

        function renderLogs(logs) {
            const tbody = document.getElementById('logs-tbody');
            if (!logs || !logs.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="py-12 text-center text-gray-400">暂无日志</td></tr>';
                return;
            }
            tbody.innerHTML = logs.map(l => {
                const dot = l.status === 'success' ? 'bg-green-500' : 'bg-red-500';
                const time = l.created_at ? formatTime(l.created_at) : '-';
                return '<tr class="log-row cursor-pointer border-b border-gray-50" data-trace-id="' + escapeHtml(l.trace_id || '') + '" onclick="openDetail(this.dataset.traceId)">' +
                    '<td class="py-3"><span class="w-1.5 h-1.5 rounded-full ' + dot + ' block"></span></td>' +
                    '<td class="py-3 font-medium">' + escapeHtml(eventLabel(l.event)) + '</td>' +
                    '<td class="py-3 text-gray-500">' + escapeHtml(l.user_display || '-') + '</td>' +
                    '<td class="py-3 font-mono text-xs text-gray-400">' + time + '</td>' +
                    '<td class="py-3 font-mono text-xs text-gray-400">' + escapeHtml(l.trace_id || '-') + '</td></tr>';
            }).join('');
        }

        function escapeHtml(s) { return (s || '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function formatTime(iso) {
            const d = new Date(iso);
            const now = new Date();
            const diff = (now - d) / 1000;
            if (diff < 60) return '刚刚';
            if (diff < 3600) return Math.floor(diff/60) + ' 分钟前';
            if (diff < 86400) return Math.floor(diff/3600) + ' 小时前';
            if (diff < 86400*2) return '昨天';
            return d.toLocaleString('zh-CN');
        }

        function openDetail(traceId) {
            if (!traceId) return;
            fetch('api/audit-logs/detail.php?trace_id=' + encodeURIComponent(traceId), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res.code !== 0) return;
                    const d = res.data;
                    document.getElementById('detail-trace_id').textContent = d.trace_id || '-';
                    document.getElementById('detail-event').textContent = eventLabel(d.event) || '-';
                    document.getElementById('detail-status').textContent = statusLabel(d.status) || '-';
                    document.getElementById('detail-time').textContent = d.created_at || '-';
                    document.getElementById('detail-ip').textContent = d.ip || '-';
                    document.getElementById('detail-user').textContent = (d.username || d.user_email) || '-';
                    document.getElementById('detail-payload').textContent = d.payload ? JSON.stringify(d.payload, null, 2) : '{}';
                    document.getElementById('drawer-detail').classList.add('open');
                });
        }

        function closeDrawer() { document.getElementById('drawer-detail').classList.remove('open'); }

        document.getElementById('btn-search').addEventListener('click', () => {
            state.event = document.getElementById('filter-event').value.trim();
            state.user = document.getElementById('filter-user').value.trim();
            state.status = document.getElementById('filter-status').value;
            state.startDate = document.getElementById('filter-start-date').value;
            state.endDate = document.getElementById('filter-end-date').value;
            state.page = 1;
            loadLogs();
        });

        document.getElementById('btn-prev').addEventListener('click', () => { if (state.page > 1) { state.page--; loadLogs(); } });
        document.getElementById('btn-next').addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadLogs(); } });

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

        document.getElementById('btn-cleanup').addEventListener('click', openCleanupModal);

        function openCleanupModal() {
            document.getElementById('modal-cleanup').classList.remove('hidden');
            document.getElementById('modal-cleanup').classList.add('flex');
        }

        function closeCleanupModal() {
            document.getElementById('modal-cleanup').classList.add('hidden');
            document.getElementById('modal-cleanup').classList.remove('flex');
        }

        async function doCleanup() {
            const days = parseInt(document.getElementById('cleanup-days').value);
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = '清除中...';
            
            try {
                const res = await fetch('api/audit-logs/cleanup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ retention_days: days }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    alert('已删除 ' + data.data.deleted + ' 条日志');
                    closeCleanupModal();
                    loadLogs();
                } else {
                    alert(data.message || '清除失败');
                }
            } catch (err) {
                alert('网络错误');
            }
            btn.disabled = false;
            btn.textContent = '确认清除';
        }

        loadLogs();
    </script>
</body>
</html>
