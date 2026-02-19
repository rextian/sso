<?php
/**
 * REXTIAN SSO - 管理控制台仪表盘
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(basename($_SERVER['PHP_SELF']));
$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>控制台 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background-color: #f9fafb; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        
        /* 侧边栏菜单项 */
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            color: #9ca3af;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .nav-item:hover, .nav-item.active {
            color: #000;
        }
        .nav-item.active {
            font-weight: 500;
        }
        /* 激活状态的小黑点 */
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: -16px;
            width: 4px;
            height: 4px;
            background: #000;
            border-radius: 50%;
        }

        /* 优雅的表格行 */
        .table-row {
            transition: background-color 0.2s;
            border-bottom: 1px solid #f3f4f6;
        }
        .table-row:hover {
            background-color: #fafafa;
        }
        .table-row:last-child { border-bottom: none; }

        /* 状态标签 */
        .badge {
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: 9999px;
            letter-spacing: 0.05em;
        }
        .badge-active { background: #f0fdf4; color: #166534; } /* 极淡绿 */
        .badge-inactive { background: #fef2f2; color: #991b1b; } /* 极淡红 */
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between px-8 py-10 hidden md:flex">
        <div>
            <div class="mb-12">
                <a href="admin_dashboard.php" class="font-serif text-xl font-bold tracking-widest text-black">REXTIAN</a>
                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-wider">Admin Console</p>
            </div>

            <nav class="space-y-2 relative">
                <a href="admin_dashboard.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?> block">
                    <span class="ml-2">仪表盘概览</span>
                </a>
                <a href="user.php" class="nav-item <?php echo $current_page === 'user' ? 'active' : ''; ?> block">
                    <span class="ml-2">用户管理</span>
                </a>
                <a href="oauth.php" class="nav-item <?php echo $current_page === 'oauth' ? 'active' : ''; ?> block">
                    <span class="ml-2">应用接入 (OAuth)</span>
                </a>
                <a href="authorizations.php" class="nav-item <?php echo $current_page === 'authorizations' ? 'active' : ''; ?> block">
                    <span class="ml-2">授权管理</span>
                </a>
                <a href="auditlog.php" class="nav-item <?php echo $current_page === 'auditlog' ? 'active' : ''; ?> block">
                    <span class="ml-2">审计日志</span>
                </a>
                <a href="settings.php" class="nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?> block">
                    <span class="ml-2">系统设置</span>
                </a>
            </nav>
        </div>

        <div class="flex items-center gap-3 border-t border-gray-100 pt-6">
            <div class="w-8 h-8 rounded-full bg-gray-200 overflow-hidden">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="Admin">
            </div>
            <div>
                <p class="text-xs font-medium text-black">Admin</p>
                <a href="logout.php" class="text-[10px] text-gray-400 hover:text-black">退出登录</a>
            </div>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto bg-white">
        <header class="sticky top-0 bg-white/80 backdrop-blur-md z-20 border-b border-gray-50 px-8 py-4 flex justify-between items-center">
            <h2 class="font-serif text-lg text-black">仪表盘概览</h2>
            <div class="flex gap-4">
                <a href="oauth.php" class="text-xs text-gray-500 hover:text-black transition">API 密钥</a>
            </div>
        </header>

        <div class="p-8 max-w-7xl mx-auto space-y-12">
            
            <section class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-6 border border-gray-100 rounded-sm hover:shadow-lg hover:shadow-gray-100/50 transition duration-500">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-4">Total Users</p>
                    <div class="flex items-baseline gap-2">
                        <span id="stat-users" class="font-serif text-4xl text-black">-</span>
                        <span class="text-xs text-gray-400 font-medium">用户总数</span>
                    </div>
                </div>
                <div class="p-6 border border-gray-100 rounded-sm hover:shadow-lg hover:shadow-gray-100/50 transition duration-500">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-4">Active Sessions</p>
                    <div class="flex items-baseline gap-2">
                        <span id="stat-sessions" class="font-serif text-4xl text-black">-</span>
                        <span class="text-xs text-gray-400 font-medium">当前在线</span>
                    </div>
                </div>
                <div class="p-6 border border-gray-100 rounded-sm hover:shadow-lg hover:shadow-gray-100/50 transition duration-500">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-4">SSO Applications</p>
                    <div class="flex items-baseline gap-2">
                        <span id="stat-apps" class="font-serif text-4xl text-black">-</span>
                        <a href="oauth.php" class="text-xs text-black font-medium border-b border-black cursor-pointer">管理应用 →</a>
                    </div>
                </div>
            </section>

            <section>
                <div class="flex justify-between items-end mb-6">
                    <div>
                        <h3 class="font-serif text-xl text-black mb-1">最近活跃用户</h3>
                        <p class="text-xs text-gray-400 font-light">管理您的 REXTIAN ID 用户群</p>
                    </div>
                    <div class="flex gap-3">
                         <div class="relative group">
                            <input type="text" placeholder="Search users..." class="text-xs py-2 border-b border-gray-200 focus:border-black outline-none w-48 transition-all bg-transparent">
                        </div>
                        <a href="user.php" class="bg-black text-white text-xs px-4 py-2 hover:bg-gray-800 transition inline-block">添加用户</a>
                    </div>
                </div>

                <div class="w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs text-gray-400 border-b border-gray-100">
                                <th class="font-normal py-4 pl-2">用户信息</th>
                                <th class="font-normal py-4">应用授权数</th>
                                <th class="font-normal py-4">状态</th>
                                <th class="font-normal py-4">最近登录</th>
                                <th class="font-normal py-4 text-right pr-2">操作</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody" class="text-sm text-gray-600">
                            <tr><td colspan="5" class="py-8 text-center text-gray-400">加载中...</td></tr>
                        </tbody>
                    </table>
                    
                    <div class="mt-6 flex justify-between items-center">
                        <span id="users-summary" class="text-xs text-gray-400">-</span>
                        <a href="user.php" class="text-xs text-black font-medium border-b border-black cursor-pointer">查看全部 →</a>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        // 加载统计数据
        fetch('api/dashboard/stats.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (res.code === 0) {
                    document.getElementById('stat-users').textContent = res.data.total_users.toLocaleString();
                    document.getElementById('stat-sessions').textContent = res.data.active_sessions.toLocaleString();
                    document.getElementById('stat-apps').textContent = res.data.sso_apps.toLocaleString();
                }
            });
        // 加载最近活跃用户
        fetch('api/users.php?limit=10&sort=last_login', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('users-tbody');
                const summary = document.getElementById('users-summary');
                if (res.code !== 0 || !res.data.users.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-gray-400">暂无用户</td></tr>';
                    summary.textContent = '共 0 个用户';
                    return;
                }
                const total = res.data.total || res.data.users.length;
                summary.textContent = 'Showing ' + res.data.users.length + ' of ' + total + ' users';
                tbody.innerHTML = res.data.users.map(u => {
                    const initial = (u.display_name || u.username)[0].toUpperCase();
                    const badge = u.status === 'active' ? 'badge-active">Active' : 'badge-inactive">Locked';
                    const time = u.last_login_at ? formatTime(u.last_login_at) : '从未登录';
                    const appText = u.app_count === 0 ? '0 Apps' : u.app_count + ' App' + (u.app_count > 1 ? 's' : '');
                    return '<tr class="table-row group"><td class="py-4 pl-2"><div class="flex items-center gap-3">' +
                        '<div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-serif">' + initial + '</div>' +
                        '<div><p class="text-black font-medium">' + escapeHtml(u.display_name || u.username) + '</p>' +
                        '<p class="text-xs text-gray-400">' + escapeHtml(u.email || u.username) + '</p></div></div></td>' +
                        '<td class="py-4">' + appText + '</td>' +
                        '<td class="py-4"><span class="badge ' + badge + '</span></td>' +
                        '<td class="py-4 text-xs font-mono text-gray-400">' + time + '</td>' +
                        '<td class="py-4 text-right pr-2"><a href="user.php?id=' + u.id + '" class="text-xs text-gray-400 hover:text-black transition underline decoration-transparent hover:decoration-black">编辑</a></td></tr>';
                }).join('');
            });
        function escapeHtml(s) { return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        function formatTime(iso) {
            const d = new Date(iso);
            const now = new Date();
            const diff = (now - d) / 1000;
            if (diff < 60) return '刚刚';
            if (diff < 3600) return Math.floor(diff/60) + ' mins ago';
            if (diff < 86400) return Math.floor(diff/3600) + ' hours ago';
            if (diff < 86400*2) return '1 day ago';
            if (diff < 86400*7) return Math.floor(diff/86400) + ' days ago';
            return d.toLocaleDateString('zh-CN');
        }
    </script>
</body>
</html>
