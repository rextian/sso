<?php
/**
 * REXTIAN SSO - OAuth 应用接入管理
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings_helper.php';
require_once __DIR__ . '/includes/csrf.php';
requireAdmin(basename($_SERVER['PHP_SELF']));
$current_page = 'oauth';
$baseUrl = rtrim(getSetting('site_url') ?: SITE_URL, '/');
$oauthAuthUrl = $baseUrl . '/oauth/authorize.php';
$oauthTokenUrl = $baseUrl . '/oauth/token.php';
$oauthUserinfoUrl = $baseUrl . '/oauth/userinfo.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>应用接入 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background-color: #fcfcfc; color: #111; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        
        /* 隐形滚动条 */
        ::-webkit-scrollbar { width: 0px; background: transparent; }

        /* 应用卡片 */
        .app-card {
            background: #fff; border: 1px solid #f3f4f6; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative; overflow: hidden;
        }
        .app-card:hover {
            transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.04); border-color: #000;
        }

        /* 密钥模糊效果 */
        .secret-blur { filter: blur(4px); cursor: pointer; transition: filter 0.3s; user-select: none;}
        .secret-blur:hover { filter: blur(2px); }
        .secret-reveal { filter: blur(0); }

        /* 标签样式 */
        .tag {
            background: #f9fafb; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; 
            color: #4b5563; border: 1px solid #e5e7eb; display: inline-flex; items-center; gap: 4px;
        }
        
        /* 侧滑抽屉动画 */
        .drawer-overlay {
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        .drawer-panel {
            transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .drawer-overlay.open .drawer-panel { transform: translateX(0); }

        /* 极简输入框 */
        .minimal-input {
            width: 100%; border: none; border-bottom: 1px solid #e5e7eb; 
            padding: 10px 0; background: transparent; outline: none; transition: border-color 0.3s;
        }
        .minimal-input:focus { border-bottom-color: #000; }
        
        /* 等宽字体类 (用于 ID/Key) */
        .font-mono-code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="w-20 lg:w-64 border-r border-gray-100 flex flex-col py-8 items-center lg:items-start lg:pl-10 hidden md:flex">
        <a href="admin_dashboard.php" class="font-serif text-xl font-bold tracking-widest text-black mb-12">REXTIAN</a>
        <nav class="space-y-6 w-full">
            <a href="admin_dashboard.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-dashboard-line mr-3"></i><span class="hidden lg:inline">仪表盘</span></a>
            <a href="user.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-user-settings-line mr-3"></i><span class="hidden lg:inline">用户管理</span></a>
            <div class="text-black font-medium flex items-center lg:w-full border-l-2 border-black pl-3 -ml-3 lg:ml-0 lg:border-l-0 lg:pl-0"><i class="ri-apps-line mr-3"></i><span class="hidden lg:inline">应用接入</span></div>
            <a href="authorizations.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-shield-check-line mr-3"></i><span class="hidden lg:inline">授权管理</span></a>
            <a href="auditlog.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-file-list-3-line mr-3"></i><span class="hidden lg:inline">审计日志</span></a>
            <a href="settings.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-settings-4-line mr-3"></i><span class="hidden lg:inline">系统设置</span></a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto relative bg-gray-50/30">
        <header class="sticky top-0 bg-white/90 backdrop-blur-sm z-20 px-8 py-6 border-b border-gray-50">
            <div class="flex justify-between items-end mb-6">
                <div>
                    <h1 class="font-serif text-3xl mb-1 text-black">OAuth 接入应用</h1>
                    <p id="header-summary" class="text-xs text-gray-400 tracking-wider uppercase">Connected Applications • 加载中...</p>
                </div>
                <button onclick="openDrawer('new')" class="bg-black text-white text-xs px-6 py-2.5 hover:bg-gray-800 transition tracking-widest uppercase shadow-lg shadow-gray-200">
                    + 创建应用
                </button>
            </div>
            
            <div class="bg-gray-50 p-6 rounded-sm border border-gray-100">
                <h4 class="text-sm font-bold uppercase tracking-wider mb-4">OAuth API 接口地址</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">授权地址</label>
                        <div class="flex gap-2 items-center">
                            <p class="font-mono-code text-xs break-all flex-1 select-text"><?php echo htmlspecialchars($oauthAuthUrl); ?></p>
                            <button type="button" class="text-xs border border-gray-300 px-2 py-1 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyText('<?php echo addslashes($oauthAuthUrl); ?>')">复制</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">Token 地址</label>
                        <div class="flex gap-2 items-center">
                            <p class="font-mono-code text-xs break-all flex-1 select-text"><?php echo htmlspecialchars($oauthTokenUrl); ?></p>
                            <button type="button" class="text-xs border border-gray-300 px-2 py-1 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyText('<?php echo addslashes($oauthTokenUrl); ?>')">复制</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">用户信息地址</label>
                        <div class="flex gap-2 items-center">
                            <p class="font-mono-code text-xs break-all flex-1 select-text"><?php echo htmlspecialchars($oauthUserinfoUrl); ?></p>
                            <button type="button" class="text-xs border border-gray-300 px-2 py-1 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyText('<?php echo addslashes($oauthUserinfoUrl); ?>')">复制</button>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">SSO 简化接口</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex gap-2 items-center">
                            <span class="text-[10px] bg-gray-200 px-2 py-1 rounded">Verify</span>
                            <p class="font-mono-code text-xs break-all flex-1 select-text"><?php echo htmlspecialchars($baseUrl . '/api/sso/verify.php'); ?></p>
                            <button type="button" class="text-xs border border-gray-300 px-2 py-1 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyText('<?php echo addslashes($baseUrl . '/api/sso/verify.php'); ?>')">复制</button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <span class="text-[10px] bg-gray-200 px-2 py-1 rounded">Login URL</span>
                            <p class="font-mono-code text-xs break-all flex-1 select-text"><?php echo htmlspecialchars($baseUrl . '/api/sso/login-url.php'); ?></p>
                            <button type="button" class="text-xs border border-gray-300 px-2 py-1 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyText('<?php echo addslashes($baseUrl . '/api/sso/login-url.php'); ?>')">复制</button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <span class="text-[10px] bg-gray-200 px-2 py-1 rounded">Refresh</span>
                            <p class="font-mono-code text-xs break-all flex-1 select-text"><?php echo htmlspecialchars($baseUrl . '/api/sso/refresh.php'); ?></p>
                            <button type="button" class="text-xs border border-gray-300 px-2 py-1 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyText('<?php echo addslashes($baseUrl . '/api/sso/refresh.php'); ?>')">复制</button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div id="apps-grid" class="p-8 max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="col-span-full text-center py-12 text-gray-400">加载中...</div>
        </div>
    </main>

    <div id="drawerOverlay" class="drawer-overlay fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/20 backdrop-blur-[2px]" onclick="closeDrawer()"></div>
        
        <div class="drawer-panel w-full max-w-xl h-full bg-white shadow-2xl relative z-10 flex flex-col">
            <div class="px-8 py-8 border-b border-gray-50 flex justify-between items-start">
                <div>
                    <p id="drawer-subtitle" class="text-xs text-gray-400 uppercase tracking-widest mb-2">新建应用</p>
                    <h2 id="drawer-title" class="font-serif text-3xl text-black">新建应用</h2>
                </div>
                <button onclick="closeDrawer()" class="text-gray-400 hover:text-black hover:rotate-90 transition duration-300"><i class="ri-close-line text-2xl"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto px-8 py-8 space-y-10">
                <section class="space-y-6">
                    <h4 class="text-sm font-bold uppercase tracking-wider border-l-2 border-black pl-3">应用类型</h4>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="input-app-type" value="oauth" class="w-4 h-4" checked>
                            <span class="text-sm">OAuth 应用</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="input-app-type" value="ubnt" class="w-4 h-4">
                            <span class="text-sm">UBNT / UniFi 集成</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="input-app-type" value="ikuai" class="w-4 h-4">
                            <span class="text-sm">爱快 (iKuai) 集成</span>
                        </label>
                    </div>
                </section>

                <section id="section-oauth-addrs" class="space-y-6">
                    <h4 class="text-sm font-bold uppercase tracking-wider border-l-2 border-black pl-3">OAuth 接口地址</h4>
                    <div class="bg-gray-50 p-6 rounded-sm space-y-4 border border-gray-100">
                        <div>
                            <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">授权地址 (Authorization)</label>
                            <div class="flex gap-2 items-center">
                                <p id="url-auth" class="font-mono-code text-sm break-all flex-1 select-text"><?php echo htmlspecialchars($oauthAuthUrl); ?></p>
                                <button type="button" class="text-xs border border-gray-300 px-3 py-1.5 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyUrl('url-auth')">复制</button>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 pt-4">
                            <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">Token 地址</label>
                            <div class="flex gap-2 items-center">
                                <p id="url-token" class="font-mono-code text-sm break-all flex-1 select-text"><?php echo htmlspecialchars($oauthTokenUrl); ?></p>
                                <button type="button" class="text-xs border border-gray-300 px-3 py-1.5 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyUrl('url-token')">复制</button>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 pt-4">
                            <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-1">用户信息 (Userinfo)</label>
                            <div class="flex gap-2 items-center">
                                <p id="url-userinfo" class="font-mono-code text-sm break-all flex-1 select-text"><?php echo htmlspecialchars($oauthUserinfoUrl); ?></p>
                                <button type="button" class="text-xs border border-gray-300 px-3 py-1.5 hover:bg-black hover:text-white hover:border-black transition shrink-0" onclick="copyUrl('url-userinfo')">复制</button>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-2 italic">需 Header: Authorization: Bearer &lt;access_token&gt;</p>
                        </div>
                    </div>
                </section>

                <section id="section-ubnt" class="space-y-6 hidden">
                    <h4 class="text-sm font-bold uppercase tracking-wider border-l-2 border-black pl-3">UBNT API 配置</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">API 连接地址</label>
                            <input type="url" id="input-ubnt-api-url" class="minimal-input font-mono text-sm" placeholder="https://10.10.11.1">
                            <p class="text-[10px] text-gray-400 mt-1">控制器地址，路径 /proxy/network/integration/v1 自动拼接</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">API Key</label>
                            <input type="password" id="input-ubnt-api-key" class="minimal-input font-mono text-sm" placeholder="在 UniFi 控制台 Integrations 中创建">
                            <p class="text-[10px] text-gray-400 mt-1">用于 X-API-KEY 请求头</p>
                        </div>
                    </div>
                </section>

                <section id="section-ikuai" class="space-y-6 hidden">
                    <h4 class="text-sm font-bold uppercase tracking-wider border-l-2 border-black pl-3">爱快 (iKuai) 配置</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">API 连接地址</label>
                            <input type="url" id="input-ikuai-api-url" class="minimal-input font-mono text-sm" placeholder="https://open.ikuai8.com 或 路由器管理地址">
                            <p class="text-[10px] text-gray-400 mt-1">爱快开放平台或本地路由管理地址</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Token / 密钥</label>
                            <input type="password" id="input-ikuai-token" class="minimal-input font-mono text-sm" placeholder="Portal 回调 token 或开放平台密钥">
                            <p class="text-[10px] text-gray-400 mt-1">用于 webauth-up 回调或 Open API 认证</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-6">
                    <h4 class="text-sm font-bold uppercase tracking-wider border-l-2 border-black pl-3">基本信息</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">应用名称</label>
                            <input type="text" id="input-name" class="minimal-input text-base font-serif" placeholder="应用名称" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">应用描述</label>
                            <textarea id="input-description" class="minimal-input text-sm h-20 resize-none font-sans" placeholder="请输入应用描述..."></textarea>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">状态</label>
                            <select id="input-status" class="minimal-input bg-transparent">
                                <option value="dev">开发模式</option>
                                <option value="live">运行中</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section id="section-credentials" class="space-y-6 hidden">
                    <h4 class="text-sm font-bold uppercase tracking-wider border-l-2 border-black pl-3">应用凭证</h4>
                    <div class="bg-gray-50 p-6 rounded-sm space-y-5 border border-gray-100">
                        <div>
                            <label class="text-[10px] text-gray-400 uppercase tracking-wider flex justify-between">
                                Client ID <i class="ri-file-copy-line cursor-pointer hover:text-black" title="复制" onclick="copyText(document.getElementById('display-client_id').textContent)"></i>
                            </label>
                            <p id="display-client_id" class="font-mono-code text-sm mt-1 select-all"></p>
                        </div>
                        <div class="border-t border-gray-200 pt-4">
                            <label class="text-[10px] text-gray-400 uppercase tracking-wider flex justify-between">
                                Client Secret
                                <span id="secret-reveal-btn" class="text-gray-500 cursor-pointer hover:underline hidden">显示</span>
                            </label>
                            <p id="display-client_secret" class="font-mono-code text-sm mt-1 secret-blur select-all text-gray-600"></p>
                            <p class="text-[10px] text-gray-400 mt-2 italic">⚠️ 请妥善保管此密钥，切勿泄露给第三方。</p>
                        </div>
                    </div>
                    <button type="button" id="btn-reset-secret" class="text-xs text-black border-b border-black pb-0.5 hover:opacity-70">重置密钥</button>
                </section>

                <section id="section-redirect" class="space-y-6">
                    <h4 class="text-sm font-bold uppercase tracking-wider border-l-2 border-black pl-3">回调地址 (Redirect URIs)</h4>
                    <div class="space-y-3">
                        <div id="redirect-uris-tags" class="flex flex-wrap gap-2"></div>
                        <div class="flex gap-2">
                            <input type="text" id="input-redirect-uri" class="minimal-input text-xs font-mono-code flex-1" placeholder="https://myapp.com/callback">
                            <button type="button" id="btn-add-uri" class="text-xs bg-black text-white px-4 hover:bg-gray-800 whitespace-nowrap">添加</button>
                        </div>
                    </div>
                </section>

                <section id="section-danger" class="pt-8 mt-8 border-t border-gray-100 hidden">
                    <h4 class="text-sm font-bold text-red-600 uppercase tracking-wider mb-4">危险区域</h4>
                    <button type="button" id="btn-delete" class="w-full border border-red-200 text-red-600 py-3 text-xs uppercase tracking-widest hover:bg-red-50 transition">删除此应用</button>
                </section>
            </div>

            <div class="p-6 border-t border-gray-50 bg-white sticky bottom-0 z-20 flex justify-end gap-4">
                <button onclick="closeDrawer()" class="px-6 py-3 text-xs text-gray-500 hover:text-black transition">取消</button>
                <button type="button" id="btn-save" class="bg-black text-white px-8 py-3 text-xs font-bold tracking-widest hover:bg-gray-800 transition shadow-lg">保存</button>
            </div>
        </div>
    </div>
    <div id="toast" class="fixed top-6 left-1/2 -translate-x-1/2 bg-black text-white text-xs px-6 py-3 rounded shadow-lg z-[60] opacity-0 transition-opacity pointer-events-none">已保存</div>

    <script>
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const drawer = document.getElementById('drawerOverlay');
        let drawerMode = 'new';
        let drawerAppId = 0;
        let redirectUris = [];

        function toggleSectionsByType() {
            const type = document.querySelector('input[name="input-app-type"]:checked')?.value || 'oauth';
            const isOauth = type === 'oauth';
            document.getElementById('section-oauth-addrs').classList.toggle('hidden', !isOauth);
            document.getElementById('section-redirect').classList.toggle('hidden', !isOauth);
            document.getElementById('section-ubnt').classList.toggle('hidden', type !== 'ubnt');
            document.getElementById('section-ikuai').classList.toggle('hidden', type !== 'ikuai');
        }

        function copyText(t) {
            if (!t) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(t).then(() => showToast('已复制')).catch(() => fallbackCopy(t));
            } else {
                fallbackCopy(t);
            }
        }
        function copyUrl(id) { copyText(document.getElementById(id)?.textContent?.trim() || ''); }
        function fallbackCopy(t) {
            const ta = document.createElement('textarea');
            ta.value = t;
            ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); showToast('已复制'); } catch (e) { showToast('复制失败'); }
            document.body.removeChild(ta);
        }
        function showToast(msg) {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.classList.remove('opacity-0');
            setTimeout(() => el.classList.add('opacity-0'), 2500);
        }

        function loadApps() {
            fetch('api/apps.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    const grid = document.getElementById('apps-grid');
                    if (res.code !== 0) {
                        grid.innerHTML = '<div class="col-span-full text-center py-12 text-gray-400">加载失败</div>';
                        return;
                    }
                    const apps = res.data.apps || [];
                    document.getElementById('header-summary').textContent = 'Connected Applications • ' + apps.length + ' 个应用';
                    const cards = apps.map(a => {
                        const initial = (a.name || '?')[0].toUpperCase();
                        const statusCls = a.status === 'live' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500';
                        const statusText = a.status === 'live' ? '运行中' : '开发模式';
                        const typeBadge = a.app_type === 'ubnt' ? '<span class="bg-blue-50 text-blue-700 text-[10px] px-2 py-0.5 rounded mr-1">UBNT</span>' : (a.app_type === 'ikuai' ? '<span class="bg-amber-50 text-amber-700 text-[10px] px-2 py-0.5 rounded mr-1">爱快</span>' : '');
                        const time = a.created_at ? formatTime(a.created_at) : '-';
                        const redirectUris = a.redirect_uris || [];
                        const firstRedirectUri = redirectUris.length > 0 ? redirectUris[0] : null;
                        let redirectHtml = '';
                        if (a.app_type === 'oauth' && firstRedirectUri) {
                            redirectHtml = '<div class="mt-3 pt-3 border-t border-gray-100"><p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1">回调地址</p><p class="font-mono-code text-xs text-gray-500 break-all">' + escapeHtml(firstRedirectUri) + '</p>' + (redirectUris.length > 1 ? '<p class="text-[10px] text-gray-400 mt-1">+' + (redirectUris.length - 1) + ' 个更多...</p>' : '') + '</div>';
                        }
                        return '<div class="app-card p-6 rounded-sm cursor-pointer group" onclick="openDrawer(\'edit\',' + a.id + ')">' +
                            '<div class="flex justify-between items-start mb-6">' +
                            '<div class="w-12 h-12 ' + (a.status === 'live' ? 'bg-black text-white' : 'border border-gray-200 text-gray-400') + ' flex items-center justify-center font-serif text-xl">' + initial + '</div>' +
                            '<div class="flex items-center gap-1">' + typeBadge + '<span class="' + statusCls + ' text-[10px] px-2 py-1 rounded-full uppercase tracking-wider font-medium">' + statusText + '</span></div></div>' +
                            '<h3 class="font-serif text-xl mb-1 group-hover:underline decoration-1 underline-offset-4">' + escapeHtml(a.name) + '</h3>' +
                            '<p class="text-xs text-gray-400 mb-2 font-mono-code">ID: ' + escapeHtml(a.client_id) + '</p>' +
                            redirectHtml +
                            '<div class="border-t border-gray-100 pt-4 space-y-3 mt-4">' +
                            '<div class="flex justify-between text-xs"><span class="text-gray-400">授权用户数</span><span class="font-mono-code text-gray-600">' + a.grant_count + '</span></div>' +
                            '<div class="flex justify-between text-xs"><span class="text-gray-400">创建时间</span><span class="font-mono-code text-gray-600">' + time + '</span></div></div></div>';
                    });
                    grid.innerHTML = cards.join('') + '<div onclick="openDrawer(\'new\')" class="border border-dashed border-gray-300 rounded-sm p-6 flex flex-col items-center justify-center text-gray-400 hover:border-black hover:text-black transition cursor-pointer min-h-[240px]">' +
                        '<i class="ri-add-line text-3xl mb-2"></i><span class="text-xs uppercase tracking-widest">新建应用接入</span></div>';
                });
        }
        function escapeHtml(s) { return (s || '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function formatTime(iso) {
            const d = new Date(iso);
            const now = new Date();
            const diff = (now - d) / 1000;
            if (diff < 3600) return Math.floor(diff/60) + ' 分钟前';
            if (diff < 86400) return Math.floor(diff/3600) + ' 小时前';
            if (diff < 86400*2) return '昨天';
            return d.toLocaleDateString('zh-CN');
        }

        function renderRedirectTags() {
            const el = document.getElementById('redirect-uris-tags');
            el.innerHTML = redirectUris.map((uri, i) => '<div class="tag">' + escapeHtml(uri) + '<i class="ri-close-line cursor-pointer hover:text-red-500 ml-1" onclick="removeRedirectUri(' + i + ')"></i></div>').join('');
        }

        function addRedirectUri() {
            const input = document.getElementById('input-redirect-uri');
            const v = input.value.trim();
            if (v && !redirectUris.includes(v)) {
                redirectUris.push(v);
                renderRedirectTags();
                input.value = '';
            }
        }

        function removeRedirectUri(i) {
            redirectUris.splice(i, 1);
            renderRedirectTags();
        }

        function openDrawer(type, id) {
            drawer.classList.add('open');
            drawerMode = type;
            drawerAppId = id || 0;
            document.getElementById('section-credentials').classList.add('hidden');
            document.getElementById('section-danger').classList.add('hidden');
            document.getElementById('btn-reset-secret').classList.add('hidden');
            if (type === 'new') {
                document.getElementById('drawer-subtitle').textContent = '新建应用';
                document.getElementById('drawer-title').textContent = '新建应用';
                document.querySelector('input[name="input-app-type"][value="oauth"]').checked = true;
                document.getElementById('input-name').value = '';
                document.getElementById('input-description').value = '';
                document.getElementById('input-status').value = 'dev';
                document.getElementById('input-ubnt-api-url').value = '';
                document.getElementById('input-ubnt-api-key').value = '';
                document.getElementById('input-ikuai-api-url').value = '';
                document.getElementById('input-ikuai-token').value = '';
                redirectUris = [];
                renderRedirectTags();
                toggleSectionsByType();
            } else if (id) {
                document.getElementById('drawer-subtitle').textContent = '编辑应用';
                document.getElementById('drawer-title').textContent = '加载中...';
                document.getElementById('section-danger').classList.remove('hidden');
                fetch('api/apps.php?id=' + id, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(res => {
                        if (res.code !== 0) return;
                        const a = res.data;
                        const isInteg = (a.app_type === 'ubnt' || a.app_type === 'ikuai');
                        document.getElementById('drawer-title').textContent = a.name;
                        document.querySelector('input[name="input-app-type"][value="' + (a.app_type || 'oauth') + '"]').checked = true;
                        document.getElementById('input-name').value = a.name;
                        document.getElementById('input-description').value = a.description || '';
                        document.getElementById('input-status').value = a.status || 'dev';
                        document.getElementById('input-ubnt-api-url').value = a.ubnt_api_url || '';
                        document.getElementById('input-ubnt-api-key').value = a.ubnt_api_key || '';
                        document.getElementById('input-ikuai-api-url').value = a.ikuai_api_url || '';
                        document.getElementById('input-ikuai-token').value = a.ikuai_token || '';
                        redirectUris = a.redirect_uris || [];
                        renderRedirectTags();
                        toggleSectionsByType();
                        document.getElementById('section-credentials').classList.toggle('hidden', isInteg);
                        document.getElementById('btn-reset-secret').classList.toggle('hidden', isInteg);
                        document.getElementById('display-client_id').textContent = a.client_id || '-';
                        document.getElementById('display-client_secret').textContent = a.client_secret || '';
                        document.getElementById('display-client_secret').classList.add('secret-blur');
                    });
            }
        }

        function closeDrawer() { drawer.classList.remove('open'); loadApps(); }

        document.querySelectorAll('input[name="input-app-type"]').forEach(r => r.addEventListener('change', toggleSectionsByType));
        document.getElementById('btn-add-uri').addEventListener('click', addRedirectUri);
        document.getElementById('input-redirect-uri').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addRedirectUri(); } });

        document.getElementById('btn-save').addEventListener('click', async function() {
            const name = document.getElementById('input-name').value.trim();
            if (!name) { showToast('请输入应用名称'); return; }
            const appType = document.querySelector('input[name="input-app-type"]:checked')?.value || 'oauth';
            const payload = { name, description: document.getElementById('input-description').value.trim(), redirect_uris: redirectUris, status: document.getElementById('input-status').value, app_type: appType };
            if (appType === 'ubnt') {
                payload.ubnt_api_url = document.getElementById('input-ubnt-api-url').value.trim();
                payload.ubnt_api_key = document.getElementById('input-ubnt-api-key').value.trim();
                if (!payload.ubnt_api_url || !payload.ubnt_api_key) {
                    showToast('请填写 UBNT API 连接地址和 API Key'); return;
                }
            }
            if (appType === 'ikuai') {
                payload.ikuai_api_url = document.getElementById('input-ikuai-api-url').value.trim();
                payload.ikuai_token = document.getElementById('input-ikuai-token').value.trim();
                if (!payload.ikuai_api_url || !payload.ikuai_token) {
                    showToast('请填写爱快 API 连接地址和 Token'); return;
                }
            }
            const btn = this;
            btn.disabled = true;
            try {
                const url = drawerMode === 'new' ? 'api/apps/create.php' : 'api/apps/update.php';
                if (drawerMode === 'edit') payload.id = drawerAppId;
                const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() }, body: JSON.stringify(payload), credentials: 'same-origin' });
                const data = await res.json();
                if (data.code === 0) {
                    const isInteg = (payload.app_type === 'ubnt' || payload.app_type === 'ikuai');
                    if (drawerMode === 'new' && data.data.client_secret && !isInteg) {
                        document.getElementById('display-client_id').textContent = data.data.client_id;
                        document.getElementById('display-client_secret').textContent = data.data.client_secret;
                        document.getElementById('display-client_secret').classList.remove('secret-blur');
                        document.getElementById('section-credentials').classList.remove('hidden');
                        document.getElementById('drawer-subtitle').textContent = '创建成功，请保存密钥';
                        showToast('创建成功！请妥善保存 Client Secret');
                    } else {
                        closeDrawer();
                        showToast('保存成功');
                    }
                } else {
                    showToast(data.message || '保存失败');
                }
            } catch (err) { showToast('网络错误'); }
            btn.disabled = false;
        });

        document.getElementById('btn-reset-secret').addEventListener('click', async function() {
            if (!confirm('重置后原密钥将失效，确定继续？')) return;
            try {
                const res = await fetch('api/apps/reset-secret.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() }, body: JSON.stringify({ id: drawerAppId }), credentials: 'same-origin' });
                const data = await res.json();
                if (data.code === 0) {
                    document.getElementById('display-client_secret').textContent = data.data.client_secret;
                    document.getElementById('display-client_secret').classList.remove('secret-blur');
                    showToast('已重置，请保存新密钥');
                } else showToast(data.message || '重置失败');
            } catch (err) { showToast('网络错误'); }
        });

        document.getElementById('btn-delete').addEventListener('click', async function() {
            if (!confirm('确定要删除此应用吗？此操作不可恢复。')) return;
            try {
                const res = await fetch('api/apps/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() }, body: JSON.stringify({ id: drawerAppId }), credentials: 'same-origin' });
                const data = await res.json();
                if (data.code === 0) { closeDrawer(); showToast('已删除'); }
                else showToast(data.message || '删除失败');
            } catch (err) { showToast('网络错误'); }
        });

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
        loadApps();
    </script>
</body>
</html>
