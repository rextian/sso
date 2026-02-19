<?php
/**
 * REXTIAN SSO - 用户管理
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireAdmin(basename($_SERVER['PHP_SELF']));
$current_page = 'user';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background-color: #fcfcfc; color: #111; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        
        /* 隐形滚动条 */
        ::-webkit-scrollbar { width: 0px; background: transparent; }

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
        .user-row {
            transition: all 0.2s ease;
        }
        .user-row:hover {
            background-color: #f9fafb;
            transform: scale-[1.002];
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        /* 状态指示点 */
        .status-dot {
            height: 6px; width: 6px; border-radius: 50%; display: inline-block; margin-right: 6px;
        }
        .status-active { background-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.2); }
        .status-banned { background-color: #ef4444; box-shadow: 0 0 0 2px rgba(239,68,68,0.2); }
        .status-pending { background-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,0.2); }

        /* 侧滑抽屉动画 */
        .drawer-overlay {
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        
        .drawer-panel {
            transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .drawer-overlay.open .drawer-panel { transform: translateX(0); }

        /* 极简输入框 (延续之前的风格) */
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
                <div class="text-black font-medium flex items-center"><i class="ri-user-settings-line mr-2"></i><span class="hidden lg:inline">用户管理</span></div>
                <a href="oauth.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-apps-line mr-2"></i><span class="hidden lg:inline">应用接入</span></a>
                <a href="authorizations.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-shield-check-line mr-2"></i><span class="hidden lg:inline">授权管理</span></a>
                <a href="auditlog.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-file-list-3-line mr-2"></i><span class="hidden lg:inline">审计日志</span></a>
                <a href="settings.php" class="text-gray-400 hover:text-black flex items-center"><i class="ri-settings-4-line mr-2"></i><span class="hidden lg:inline">系统设置</span></a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto relative">
        <header class="sticky top-0 bg-white/90 backdrop-blur-sm z-20 px-8 py-6 border-b border-gray-50 flex justify-between items-end">
            <div>
                <h1 class="font-serif text-3xl mb-1">用户名录</h1>
                <p id="header-total" class="text-xs text-gray-400 tracking-wider uppercase">User Directory • 加载中...</p>
            </div>
            <div class="flex gap-4">
                <div class="relative group">
                    <i class="ri-search-line absolute left-0 top-2.5 text-gray-400"></i>
                    <input type="text" id="search-input" placeholder="搜索用户..." class="pl-6 border-b border-gray-200 py-2 text-sm focus:border-black outline-none w-48 transition-all bg-transparent">
                </div>
                <button onclick="openDrawer('new')" class="bg-black text-white text-xs px-6 py-2.5 hover:bg-gray-800 transition tracking-widest uppercase">
                    + 新建用户
                </button>
            </div>
        </header>

        <div class="p-8 max-w-7xl mx-auto">
            <div class="flex space-x-8 border-b border-gray-100 mb-6">
                <div class="filter-tab active text-sm" data-filter="all">全部用户</div>
                <div class="filter-tab text-sm" data-filter="pending">待审核</div>
                <div class="filter-tab text-sm" data-filter="admin">管理员</div>
                <div class="filter-tab text-sm" data-filter="banned">已禁用</div>
            </div>

            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[10px] text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        <th class="font-normal py-4 pl-4">User Profile</th>
                        <th class="font-normal py-4">Contact</th>
                        <th class="font-normal py-4">Role</th>
                        <th class="font-normal py-4">Status</th>
                        <th class="font-normal py-4 text-right pr-4">Action</th>
                    </tr>
                </thead>
                <tbody id="users-tbody" class="text-sm">
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

    <div id="drawerOverlay" class="drawer-overlay fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/20 backdrop-blur-[2px]" onclick="closeDrawer()"></div>
        
        <div class="drawer-panel w-full max-w-md h-full bg-white shadow-2xl relative z-10 flex flex-col">
            
            <div class="px-8 py-8 border-b border-gray-50 flex justify-between items-start">
                <div>
                    <p id="drawer-subtitle" class="text-xs text-gray-400 uppercase tracking-widest mb-2">新建用户</p>
                    <h2 id="drawer-title" class="font-serif text-3xl text-black">新建用户</h2>
                </div>
                <button onclick="closeDrawer()" class="text-gray-400 hover:text-black transition hover:rotate-90 duration-300">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-8 py-6 space-y-8">
                <div id="user-avatar" class="flex items-center gap-6 hidden">
                    <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center font-serif text-3xl text-gray-400">?</div>
                    <div><p class="text-[10px] text-gray-400">头像上传（待实现）</p></div>
                </div>

                <form id="user-form" class="space-y-6">
                    <div id="field-username" class="">
                        <label class="text-xs text-gray-500 uppercase tracking-wider">Username</label>
                        <input type="text" id="input-username" name="username" class="minimal-input" placeholder="登录用户名" required>
                    </div>
                    <div id="field-password" class="">
                        <label class="text-xs text-gray-500 uppercase tracking-wider">Password</label>
                        <input type="password" id="input-password" name="password" class="minimal-input" placeholder="至少 6 位">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wider">Display Name</label>
                        <input type="text" id="input-display_name" name="display_name" class="minimal-input" placeholder="显示名称">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wider">Email</label>
                        <input type="email" id="input-email" name="email" class="minimal-input" placeholder="邮箱">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wider">Phone</label>
                        <input type="tel" id="input-phone" name="phone" class="minimal-input" placeholder="手机号">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wider">Role</label>
                        <select id="input-role" name="role" class="minimal-input bg-transparent">
                            <option value="user">Standard User</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between py-4 border-t border-gray-100 mt-4">
                        <div>
                            <p class="text-sm font-medium">账户状态</p>
                            <p class="text-xs text-gray-400" id="status-hint">禁用后用户将无法登录 SSO</p>
                        </div>
                        <div id="status-container">
                            <label class="relative inline-flex items-center cursor-pointer hidden" id="status-toggle">
                                <input type="checkbox" id="input-status" name="status" checked class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-black"></div>
                            </label>
                            <div id="status-actions" class="hidden">
                                <button type="button" onclick="approveUser(drawerUserId); closeDrawer();" class="text-xs bg-green-600 text-white px-3 py-1 mr-2 rounded hover:bg-green-700 transition">通过审核</button>
                                <button type="button" onclick="rejectUser(drawerUserId); closeDrawer();" class="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition">拒绝</button>
                            </div>
                        </div>
                    </div>
                    <div class="p-8 border-t border-gray-50 flex justify-between items-center bg-gray-50/50 -mx-8 -mb-8">
                        <button type="button" id="btn-delete" class="text-red-600 text-xs hover:text-red-800 transition hidden">删除用户</button>
                        <div class="flex gap-4 ml-auto">
                            <button type="button" onclick="closeDrawer()" class="text-xs text-gray-500 hover:text-black transition px-4 py-2">取消</button>
                            <button type="submit" id="btn-submit" class="bg-black text-white text-xs px-6 py-3 hover:bg-gray-800 transition tracking-widest uppercase">保存</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="toast" class="fixed top-6 left-1/2 -translate-x-1/2 bg-black text-white text-xs px-6 py-3 rounded shadow-lg z-[60] opacity-0 transition-opacity pointer-events-none">已保存</div>

    <script>
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const currentUserId = <?php echo $currentUserId; ?>;
        const basePath = <?php echo json_encode($basePath); ?>;
        const apiUrl = (path) => (basePath ? basePath + '/' : '') + path.replace(/^\//, '');
        // 用户列表状态
        let state = { page: 1, limit: 20, filter: 'all', search: '', total: 0, totalPages: 1 };
        let searchTimer = null;

        function loadUsers() {
            const params = new URLSearchParams({
                page: state.page,
                limit: state.limit,
                filter: state.filter,
            });
            if (state.search) params.set('search', state.search);
            fetch(apiUrl('api/users.php') + '?' + params, { credentials: 'same-origin' })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    if (!res || res.code !== 0) {
                        document.getElementById('header-total').textContent = 'User Directory • 加载失败';
                        document.getElementById('users-tbody').innerHTML = '<tr><td colspan="5" class="py-12 text-center text-red-500">加载失败，请刷新页面</td></tr>';
                        return;
                    }
                    const d = res.data;
                    state.total = d.total;
                    state.totalPages = d.total_pages || 1;
                    document.getElementById('header-total').textContent = 'User Directory • ' + d.total + ' Total';
                    renderUsers(d.users);
                    renderPagination();
                });
        }

        function renderUsers(users) {
            const tbody = document.getElementById('users-tbody');
            if (!users || !users.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-12 text-center text-gray-400">暂无用户</td></tr>';
                return;
            }
            tbody.innerHTML = users.map(u => {
                const initial = (u.display_name || u.username)[0].toUpperCase();
                let statusCls, statusText;
                if (u.status === 'active') {
                    statusCls = 'status-active';
                    statusText = 'Active';
                } else if (u.status === 'pending') {
                    statusCls = 'status-pending';
                    statusText = 'Pending';
                } else {
                    statusCls = 'status-banned';
                    statusText = 'Locked';
                }
                const roleCls = u.role === 'admin' ? 'bg-gray-100 text-gray-600' : 'border border-gray-200 text-gray-500';
                const roleText = u.role === 'admin' ? 'Admin' : 'User';
                const phone = u.phone ? '+86 ' + u.phone.replace(/(\d{3})\d{4}(\d{4})/, '$1 **** $2') : '';
                let actionButtons = '<i class="ri-pencil-line mr-3 hover:scale-110 inline-block cursor-pointer" data-action="edit" data-id="' + u.id + '"></i><i class="ri-more-2-fill hover:scale-110 inline-block"></i>';
                if (u.status === 'pending') {
                    actionButtons = '<button type="button" data-action="approve" data-id="' + u.id + '" class="text-xs bg-green-600 text-white px-3 py-1 mr-2 rounded hover:bg-green-700 transition">通过</button>' +
                        '<button type="button" data-action="reject" data-id="' + u.id + '" class="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition">拒绝</button>';
                }
                return '<tr class="user-row group cursor-pointer" data-user-id="' + u.id + '">' +
                    '<td class="py-4 pl-4"><div class="flex items-center gap-4">' +
                    '<div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-serif text-lg">' + escapeHtml(initial) + '</div>' +
                    '<div><p class="font-medium text-black font-serif text-base">' + escapeHtml(u.display_name || u.username) + '</p>' +
                    '<p class="text-xs text-gray-400 font-mono">ID: ' + u.id + '</p></div></div></td>' +
                    '<td class="py-4 text-gray-600">' + escapeHtml(u.email || '-') + (phone ? '<br><span class="text-xs text-gray-400">' + escapeHtml(phone) + '</span>' : '') + '</td>' +
                    '<td class="py-4"><span class="' + roleCls + ' text-xs px-2 py-1">' + roleText + '</span></td>' +
                    '<td class="py-4"><span class="status-dot ' + statusCls + '"></span>' + statusText + '</td>' +
                    '<td class="py-4 text-right pr-4 text-gray-400 group-hover:text-black transition">' + actionButtons + '</td></tr>';
            }).join('');
        }

        function escapeHtml(s) { return (s || '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

        function renderPagination() {
            document.getElementById('pagination-info').textContent = 'Page ' + state.page + ' / ' + (state.totalPages || 1);
            document.getElementById('btn-prev').disabled = state.page <= 1;
            document.getElementById('btn-next').disabled = state.page >= state.totalPages;
        }

        // 筛选 Tab
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                state.filter = this.dataset.filter;
                state.page = 1;
                loadUsers();
            });
        });

        // 搜索（防抖 300ms）
        document.getElementById('search-input').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.search = this.value.trim();
                state.page = 1;
                loadUsers();
            }, 300);
        });

        // 分页
        document.getElementById('btn-prev').addEventListener('click', () => { if (state.page > 1) { state.page--; loadUsers(); } });
        document.getElementById('btn-next').addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadUsers(); } });

        // 初始加载
        loadUsers();
        const urlId = new URLSearchParams(location.search).get('id');
        if (urlId) openDrawer('edit', parseInt(urlId));

        // 抽屉控制
        const drawer = document.getElementById('drawerOverlay');
        let drawerMode = 'new';
        let drawerUserId = 0;

        function openDrawer(type, id) {
            drawer.classList.add('open');
            drawerMode = type;
            drawerUserId = id || 0;
            if (type === 'new') {
                document.getElementById('drawer-subtitle').textContent = '新建用户';
                document.getElementById('drawer-title').textContent = '新建用户';
                document.getElementById('field-username').classList.remove('hidden');
                document.getElementById('input-username').required = true;
                document.getElementById('field-password').classList.remove('hidden');
                document.getElementById('input-password').placeholder = '至少 6 位（必填）';
                document.getElementById('input-password').required = true;
                document.getElementById('btn-delete').classList.add('hidden');
                document.getElementById('user-form').reset();
                document.getElementById('input-status').checked = true;
                document.getElementById('input-role').disabled = false;
                document.getElementById('input-status').disabled = false;
                document.getElementById('input-status').closest('div').style.opacity = '1';
            } else if (id) {
                document.getElementById('drawer-subtitle').textContent = '编辑用户';
                document.getElementById('drawer-title').textContent = '加载中...';
                document.getElementById('field-username').classList.add('hidden');
                document.getElementById('field-password').classList.remove('hidden');
                document.getElementById('input-password').placeholder = '留空则不修改';
                document.getElementById('input-password').required = false;
                document.getElementById('btn-delete').classList.remove('hidden');
                fetch(apiUrl('api/users.php?id=' + id), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(res => {
                        if (res.code !== 0) return;
                        const u = res.data;
                        const isSelf = (u.id === currentUserId);
                        document.getElementById('drawer-title').textContent = u.display_name || u.username;
                        document.getElementById('input-username').value = u.username;
                        document.getElementById('input-display_name').value = u.display_name || '';
                        document.getElementById('input-email').value = u.email || '';
                        document.getElementById('input-phone').value = u.phone || '';
                        document.getElementById('input-role').value = u.role || 'user';
                        document.getElementById('input-password').value = '';
                        
                        const statusToggle = document.getElementById('status-toggle');
                        const statusActions = document.getElementById('status-actions');
                        const statusHint = document.getElementById('status-hint');
                        
                        if (u.status === 'pending') {
                            statusToggle.classList.add('hidden');
                            statusActions.classList.remove('hidden');
                            statusHint.textContent = '该用户正在等待审核';
                        } else {
                            statusToggle.classList.remove('hidden');
                            statusActions.classList.add('hidden');
                            statusHint.textContent = '禁用后用户将无法登录 SSO';
                            document.getElementById('input-status').checked = u.status === 'active';
                        }
                        
                        // 编辑自己时：禁止降级、禁止禁用
                        const roleSelect = document.getElementById('input-role');
                        const statusContainer = document.getElementById('status-container');
                        if (isSelf && u.role === 'admin') {
                            roleSelect.disabled = true;
                            roleSelect.title = '不能修改自己的角色';
                            statusContainer.style.opacity = '0.6';
                            statusContainer.style.pointerEvents = 'none';
                        } else {
                            roleSelect.disabled = false;
                            roleSelect.title = '';
                            statusContainer.style.opacity = '1';
                            statusContainer.style.pointerEvents = 'auto';
                        }
                    });
            }
        }

        function closeDrawer() { drawer.classList.remove('open'); }

        function showToast(msg) {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.classList.remove('opacity-0');
            setTimeout(() => el.classList.add('opacity-0'), 2500);
        }

        async function approveUser(userId) {
            if (!confirm('确定要通过该用户的注册申请吗？')) return;
            try {
                const res = await fetch(apiUrl('api/users/update.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ id: userId, status: 'active' }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    showToast('已通过审核');
                    loadUsers();
                } else {
                    showToast(data.message || '操作失败');
                }
            } catch (err) {
                showToast('网络错误');
            }
        }

        async function rejectUser(userId) {
            if (!confirm('确定要拒绝该用户的注册申请吗？拒绝后用户将无法登录。')) return;
            try {
                const res = await fetch(apiUrl('api/users/update.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ id: userId, status: 'banned' }),
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.code === 0) {
                    showToast('已拒绝申请');
                    loadUsers();
                } else {
                    showToast(data.message || '操作失败');
                }
            } catch (err) {
                showToast('网络错误');
            }
        }

        document.getElementById('user-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit');
            btn.disabled = true;
            const payload = {
                display_name: document.getElementById('input-display_name').value.trim(),
                email: document.getElementById('input-email').value.trim(),
                phone: document.getElementById('input-phone').value.trim(),
                role: document.getElementById('input-role').value,
            };
            
            const statusToggle = document.getElementById('status-toggle');
            if (!statusToggle.classList.contains('hidden')) {
                payload.status = document.getElementById('input-status').checked ? 'active' : 'banned';
            }
            let url, method;
            if (drawerMode === 'new') {
                payload.username = document.getElementById('input-username').value.trim();
                payload.password = document.getElementById('input-password').value;
                if (!payload.username) { showToast('请输入用户名'); btn.disabled = false; return; }
                if (payload.password.length < 6) { showToast('密码至少 6 位'); btn.disabled = false; return; }
                url = apiUrl('api/users/create.php');
            } else {
                payload.id = drawerUserId;
                payload.password = document.getElementById('input-password').value;
                url = apiUrl('api/users/update.php');
            }
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin'
                });
                let data;
                try {
                    data = await res.json();
                } catch (parseErr) {
                    showToast('请求失败：' + (res.status === 302 ? '登录已过期，请刷新页面' : '服务器返回异常'));
                    btn.disabled = false;
                    return;
                }
                if (data.code === 0) {
                    closeDrawer();
                    showToast('保存成功');
                    loadUsers();
                } else {
                    showToast(data.message || '保存失败');
                }
            } catch (err) {
                showToast('网络错误：' + (err.message || '请检查网络'));
            }
            btn.disabled = false;
        });

        document.getElementById('btn-delete').addEventListener('click', async function() {
            if (!confirm('确定要删除该用户吗？此操作不可恢复。')) return;
            try {
                const res = await fetch(apiUrl('api/users/delete.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ id: drawerUserId }),
                    credentials: 'same-origin'
                });
                let data;
                try { data = await res.json(); } catch (e) { showToast('请求失败'); return; }
                if (data.code === 0) {
                    closeDrawer();
                    showToast('已删除');
                    loadUsers();
                } else {
                    showToast(data.message || '删除失败');
                }
            } catch (err) {
                showToast('网络错误');
            }
        });

        document.getElementById('users-tbody').addEventListener('click', function(e) {
            const target = e.target;
            
            const action = target.dataset.action;
            const id = target.dataset.id;
            
            if (action && id) {
                e.stopPropagation();
                if (action === 'edit') {
                    openDrawer('edit', parseInt(id));
                } else if (action === 'approve') {
                    approveUser(parseInt(id));
                } else if (action === 'reject') {
                    rejectUser(parseInt(id));
                }
                return;
            }
            
            const row = target.closest('.user-row');
            if (row) {
                const userId = row.dataset.userId;
                if (userId) {
                    openDrawer('edit', parseInt(userId));
                }
            }
        });

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
    </script>
</body>
</html>
