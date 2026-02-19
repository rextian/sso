<?php
/**
 * REXTIAN SSO - 用户授权管理
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireAdmin(basename($_SERVER['PHP_SELF']));
$current_page = 'authorizations';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权管理 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background-color: #fcfcfc; color: #111; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        
        /* 极简 Tab */
        .filter-tab {
            padding-bottom: 8px;
            color: #9ca3af;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
        }
        .filter-tab.active {
            color: #000;
            border-bottom-color: #000;
        }

        /* 表格样式 */
        .auth-row {
            transition: all 0.2s ease;
        }
        .auth-row:hover {
            background-color: #f9fafb;
            transform: scale-[1.002];
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        /* 状态指示点 */
        .status-dot {
            height: 6px; width: 6px; border-radius: 50%; display: inline-block; margin-right: 6px;
        }
        .status-active { background-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.2); }
        .status-expired { background-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,0.2); }

        /* 极简输入框 */
        .minimal-input {
            width: 100%; border: none; border-bottom: 1px solid #e5e7eb; 
            padding: 10px 0; background: transparent; outline: none; transition: border-color 0.3s;
        }
        .minimal-input:focus { border-bottom-color: #000; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="w-20 lg:w-64 border-r border-gray-100 flex flex-col justify-between py-8 items-center lg:items-start lg:pl-10 hidden md:flex">
        <div>
            <a href="admin_dashboard.php" class="font-serif text-xl font-bold tracking-widest text-black">REXTIAN</a>
            <nav class="mt-12 space-y-6">
                <a href="admin_dashboard.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-dashboard-line mr-2"></i><span class="hidden lg:inline">仪表盘</span></a>
                <a href="user.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-user-settings-line mr-2"></i><span class="hidden lg:inline">用户管理</span></a>
                <a href="oauth.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-apps-line mr-2"></i><span class="hidden lg:inline">应用接入</span></a>
                <div class="text-black font-medium flex items-center"><i class="ri-shield-check-line mr-2"></i><span class="hidden lg:inline">授权管理</span></div>
                <a href="auditlog.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-file-list-3-line mr-2"></i><span class="hidden lg:inline">审计日志</span></a>
                <a href="settings.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-settings-4-line mr-2"></i><span class="hidden lg:inline">系统设置</span></a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto relative">
        <header class="sticky top-0 bg-white/90 backdrop-blur-sm z-20 px-8 py-6 border-b border-gray-50 flex justify-between items-end">
            <div>
                <h1 class="font-serif text-3xl mb-1">授权管理</h1>
                <p id="header-total" class="text-xs text-gray-400 tracking-wider uppercase">Authorizations • 加载中...</p>
            </div>
            <div class="flex gap-4">
                <div class="relative group">
                    <i class="ri-search-line absolute left-0 top-2.5 text-gray-400"></i>
                    <input type="text" id="search-input" placeholder="搜索用户或应用..." class="pl-6 border-b border-gray-200 py-2 text-sm focus:border-black outline-none w-48 transition-all bg-transparent">
                </div>
            </div>
        </header>

        <div class="p-8 max-w-7xl mx-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[10px] text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        <th class="font-normal py-4 pl-4">用户</th>
                        <th class="font-normal py-4">应用</th>
                        <th class="font-normal py-4">授权时间</th>
                        <th class="font-normal py-4">过期时间</th>
                        <th class="font-normal py-4 text-right pr-4">操作</th>
                    </tr>
                </thead>
                <tbody id="auth-tbody" class="text-sm">
                    <tr><td colspan="5" class="py-12 text-center text-gray-400">加载中...</td></tr>
                </tbody>
            </table>
            
            <div class="mt-8 flex justify-end gap-4 items-center">
                <button id="btn-prev" class="text-xs text-gray-400 hover:text-black transition disabled:opacity-50" disabled>PREV</button>
                <span id="pagination-info" class="text-xs font-serif">Page 1 / 1</span>
                <button id="btn-next" class="text-xs text-black hover:text-gray-600 transition disabled:opacity-50">NEXT</button>
            </div>
        </div>
    </main>

    <div id="toast" class="fixed top-6 left-1/2 -translate-x-1/2 bg-black text-white text-xs px-6 py-3 rounded shadow-lg z-[60] opacity-0 transition-opacity pointer-events-none">已保存</div>

    <script>
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const basePath = <?php echo json_encode($basePath); ?>;
        const apiUrl = (path) => (basePath ? basePath + '/' : '') + path.replace(/^\//, '');
        let state = { page: 1, limit: 20, search: '', total: 0, totalPages: 1 };
        let searchTimer = null;

        function loadAuthorizations() {
            const params = new URLSearchParams({
                page: state.page,
                limit: state.limit,
            });
            if (state.search) params.set('search', state.search);
            fetch(apiUrl('api/authorizations.php') + '?' + params, { credentials: 'same-origin' })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    if (!res || res.code !== 0) {
                        document.getElementById('header-total').textContent = 'Authorizations • 加载失败';
                        document.getElementById('auth-tbody').innerHTML = '<tr><td colspan="5" class="py-12 text-center text-red-500">加载失败，请刷新页面</td></tr>';
                        return;
                    }
                    const d = res.data;
                    state.total = d.total;
                    state.totalPages = d.total_pages || 1;
                    document.getElementById('header-total').textContent = 'Authorizations • ' + d.total + ' Total';
                    renderAuthorizations(d.authorizations);
                    renderPagination();
                });
        }

        function renderAuthorizations(auths) {
            const tbody = document.getElementById('auth-tbody');
            if (!auths || !auths.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="py-12 text-center text-gray-400">暂无授权记录</td></tr>';
                return;
            }
            tbody.innerHTML = auths.map(auth => {
                const userInitial = (auth.display_name || auth.username)[0].toUpperCase();
                const authTime = auth.granted_at ? new Date(auth.granted_at).toLocaleString('zh-CN') : '-';
                let redirectUri = '';
                try {
                    const uris = JSON.parse(auth.redirect_uris || '[]');
                    redirectUri = Array.isArray(uris) && uris.length > 0 ? uris[0] : '';
                } catch (e) {
                    redirectUri = auth.redirect_uris || '';
                }
                
                return '<tr class="auth-row">' +
                    '<td class="py-4 pl-4"><div class="flex items-center gap-4">' +
                    '<div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-serif text-lg">' + escapeHtml(userInitial) + '</div>' +
                    '<div><p class="font-medium text-black font-serif text-base">' + escapeHtml(auth.display_name || auth.username) + '</p>' +
                    '<p class="text-xs text-gray-400 font-mono">' + escapeHtml(auth.email || auth.username) + '</p></div></div></td>' +
                    '<td class="py-4"><div><p class="font-medium text-black">' + escapeHtml(auth.app_name) + '</p>' +
                    '<p class="text-xs text-gray-400">' + escapeHtml(redirectUri || '') + '</p></div></td>' +
                    '<td class="py-4 text-gray-600">' + authTime + '</td>' +
                    '<td class="py-4"><span class="status-dot status-active"></span>有效</td>' +
                    '<td class="py-4 text-right pr-4">' +
                    '<button type="button" onclick="revokeAuth(' + auth.id + ')" class="text-xs text-red-600 hover:text-red-800 transition">取消授权</button>' +
                    '</td></tr>';
            }).join('');
        }

        function escapeHtml(s) { return (s || '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

        function renderPagination() {
            document.getElementById('pagination-info').textContent = 'Page ' + state.page + ' / ' + (state.totalPages || 1);
            document.getElementById('btn-prev').disabled = state.page <= 1;
            document.getElementById('btn-next').disabled = state.page >= state.totalPages;
        }

        async function revokeAuth(authId) {
            if (!confirm('确定要取消该授权吗？用户将需要重新授权才能访问该应用。')) return;
            try {
                const res = await fetch(apiUrl('api/authorizations/revoke.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ id: authId }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    showToast('已取消授权');
                    loadAuthorizations();
                } else {
                    showToast(data.message || '操作失败');
                }
            } catch (err) {
                showToast('网络错误');
            }
        }

        function showToast(msg) {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.classList.remove('opacity-0');
            setTimeout(() => el.classList.add('opacity-0'), 2500);
        }

        // 搜索（防抖 300ms）
        document.getElementById('search-input').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.search = this.value.trim();
                state.page = 1;
                loadAuthorizations();
            }, 300);
        });

        // 分页
        document.getElementById('btn-prev').addEventListener('click', () => { if (state.page > 1) { state.page--; loadAuthorizations(); } });
        document.getElementById('btn-next').addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadAuthorizations(); } });

        // 初始加载
        loadAuthorizations();
    </script>
</body>
</html>
