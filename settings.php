<?php
/**
 * REXTIAN SSO - 系统设置
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireAdmin(basename($_SERVER['PHP_SELF']));
$current_page = 'settings';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background-color: #fcfcfc; color: #111; }
        .font-serif { font-family: 'Noto Serif SC', serif; }
        
        ::-webkit-scrollbar { width: 0px; background: transparent; }

        .settings-nav-item {
            padding: 12px 0; border-right: 2px solid transparent; 
            color: #9ca3af; cursor: pointer; transition: all 0.3s;
            display: flex; justify-content: space-between; align-items: center;
        }
        .settings-nav-item:hover { color: #000; }
        .settings-nav-item.active {
            color: #000; border-right-color: #000; font-weight: 500;
        }

        .minimal-input {
            width: 100%; border: none; border-bottom: 1px solid #e5e7eb; 
            padding: 10px 0; background: transparent; outline: none; transition: border-color 0.3s;
        }
        .minimal-input:focus { border-bottom-color: #000; }

        .panel { display: none; opacity: 0; transition: opacity 0.4s ease; }
        .panel.active { display: block; animation: fadeIn 0.5s forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        #toast {
            position: fixed; top: 24px; left: 50%; transform: translateX(-50%) translateY(-100px);
            background: #000; color: #fff; padding: 12px 32px; border-radius: 4px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 100; font-size: 0.8rem; letter-spacing: 0.05em;
        }
        #toast.show { transform: translateX(-50%) translateY(0); }
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
            <a href="auditlog.php" class="text-gray-400 hover:text-black flex items-center lg:w-full"><i class="ri-file-list-3-line mr-3"></i><span class="hidden lg:inline">审计日志</span></a>
            <div class="text-black font-medium flex items-center lg:w-full border-l-2 border-black pl-3 -ml-3 lg:ml-0 lg:border-l-0 lg:pl-0"><i class="ri-settings-4-line mr-3"></i><span class="hidden lg:inline">系统设置</span></div>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col h-full bg-white relative">
        
        <div id="toast">配置已保存</div>

        <header class="px-8 py-8 border-b border-gray-50 flex justify-between items-end bg-white z-10">
            <div>
                <h1 class="font-serif text-3xl mb-1 text-black">全局配置</h1>
                <p class="text-xs text-gray-400 tracking-wider uppercase">System Configuration</p>
            </div>
            <button onclick="saveSettings()" class="bg-black text-white text-xs px-8 py-3 hover:bg-gray-800 transition tracking-widest uppercase shadow-lg">
                保存更改
            </button>
        </header>

        <div class="flex-1 flex overflow-hidden">
            
            <div class="w-64 bg-gray-50/50 border-r border-gray-100 py-8 pr-6 pl-8 space-y-2 hidden lg:block">
                <div class="settings-nav-item active" onclick="switchPanel('general', this)">
                    <span class="text-sm">基础信息 (General)</span>
                    <i class="ri-arrow-right-s-line"></i>
                </div>
                <div class="settings-nav-item" onclick="switchPanel('sms', this)">
                    <span class="text-sm">短信服务 (SMS)</span>
                    <i class="ri-arrow-right-s-line"></i>
                </div>
                <div class="settings-nav-item" onclick="switchPanel('security', this)">
                    <span class="text-sm">安全策略 (Security)</span>
                    <i class="ri-arrow-right-s-line"></i>
                </div>
                <div class="settings-nav-item" onclick="switchPanel('email', this)">
                    <span class="text-sm">邮件服务 (SMTP)</span>
                    <i class="ri-arrow-right-s-line"></i>
                </div>
                <div class="settings-nav-item" onclick="switchPanel('social', this)">
                    <span class="text-sm">第三方登录 (Social)</span>
                    <i class="ri-arrow-right-s-line"></i>
                </div>
                <div class="settings-nav-item" onclick="switchPanel('audit', this)">
                    <span class="text-sm">审计与日志 (Logs)</span>
                    <i class="ri-arrow-right-s-line"></i>
                </div>
                <div class="settings-nav-item" onclick="switchPanel('radius', this)">
                    <span class="text-sm">RADIUS 认证</span>
                    <i class="ri-arrow-right-s-line"></i>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-8 lg:p-12 relative">
                
                <div id="panel-general" class="panel active space-y-12 max-w-3xl">
                    <section>
                        <h3 class="font-serif text-xl mb-6 border-b border-gray-100 pb-2">站点标识</h3>
                        <div class="space-y-6">
                            <div class="grid grid-cols-2 gap-8">
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">系统名称</label><input type="text" id="site_name" class="minimal-input font-serif text-lg" placeholder="REXTIAN ID"></div>
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">主域名</label><input type="text" id="site_url" class="minimal-input font-mono text-sm" placeholder="https://sso.rextian.com"></div>
                            </div>
                        </div>
                    </section>
                </div>

                <div id="panel-sms" class="panel space-y-10 max-w-3xl">
                    <section class="border border-gray-100 p-6 rounded-sm">
                        <h4 class="font-medium text-black mb-4">短信服务商</h4>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="sms_provider" value="aliyun" class="w-4 h-4" checked>
                                <span class="text-sm">阿里云</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="sms_provider" value="tencent" class="w-4 h-4">
                                <span class="text-sm">腾讯云</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="sms_provider" value="jdcloud" class="w-4 h-4">
                                <span class="text-sm">京东云</span>
                            </label>
                        </div>
                    </section>
                    <!-- 阿里云 -->
                    <section id="sms-aliyun" class="sms-provider-section border border-gray-200 p-8 relative">
                        <div class="absolute top-0 right-0 bg-black text-white text-[10px] px-3 py-1 uppercase font-medium">Aliyun</div>
                        <div class="flex items-start gap-4 mb-6"><i class="ri-aliens-fill text-3xl text-[#FF6A00]"></i><div><h3 class="font-serif text-xl">阿里云短信服务</h3><p class="text-xs text-gray-400">AccessKey、签名、模板需在阿里云控制台申请</p></div></div>
                        <div class="space-y-6">
                            <div class="grid grid-cols-2 gap-8">
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">AccessKey ID</label><input type="text" id="sms_key" class="minimal-input font-mono text-xs" placeholder="LTAI5t********"></div>
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">AccessKey Secret</label><input type="password" id="sms_secret" class="minimal-input font-mono text-xs" placeholder="********"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-8">
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">短信签名</label><input type="text" id="sms_sign_name" class="minimal-input font-mono text-xs" placeholder="REXTIAN 或 REXTIAN ID"></div>
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">短信模板编码</label><input type="text" id="sms_template_code" class="minimal-input font-mono text-xs" placeholder="SMS_123456789"></div>
                            </div>
                            <p class="text-xs text-gray-500">未配置 AccessKey 时将使用 Mock 模式（仅记录日志，不真实发送）</p>
                        </div>
                    </section>
                    <!-- 腾讯云 -->
                    <section id="sms-tencent" class="sms-provider-section border border-gray-200 p-8 relative hidden">
                        <div class="absolute top-0 right-0 bg-[#006EFF] text-white text-[10px] px-3 py-1 uppercase font-medium">Tencent</div>
                        <div class="flex items-start gap-4 mb-6"><i class="ri-cloud-fill text-3xl text-[#006EFF]"></i><div><h3 class="font-serif text-xl">腾讯云短信服务</h3><p class="text-xs text-gray-400">SecretId、SecretKey、SdkAppId、签名、模板需在腾讯云控制台申请</p></div></div>
                        <div class="space-y-6">
                            <div class="grid grid-cols-2 gap-8">
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">SecretId</label><input type="text" id="sms_tencent_secret_id" class="minimal-input font-mono text-xs" placeholder="AKID********"></div>
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">SecretKey</label><input type="password" id="sms_tencent_secret_key" class="minimal-input font-mono text-xs" placeholder="********"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-8">
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">SdkAppId</label><input type="text" id="sms_tencent_sdk_app_id" class="minimal-input font-mono text-xs" placeholder="1400006666"></div>
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">短信签名</label><input type="text" id="sms_tencent_sign_name" class="minimal-input font-mono text-xs" placeholder="REXTIAN"></div>
                            </div>
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">短信模板 ID</label><input type="text" id="sms_tencent_template_id" class="minimal-input font-mono text-xs" placeholder="1110"></div>
                            <p class="text-xs text-gray-500">未配置 SecretId/SecretKey 时将使用 Mock 模式</p>
                        </div>
                    </section>
                    <!-- 京东云 -->
                    <section id="sms-jdcloud" class="sms-provider-section border border-gray-200 p-8 relative hidden">
                        <div class="absolute top-0 right-0 bg-[#E3393C] text-white text-[10px] px-3 py-1 uppercase font-medium">JD Cloud</div>
                        <div class="flex items-start gap-4 mb-6"><i class="ri-shopping-cart-fill text-3xl text-[#E3393C]"></i><div><h3 class="font-serif text-xl">京东云短信服务</h3><p class="text-xs text-gray-400">AccessKey、SecretKey、签名ID、模板ID需在京东云控制台申请</p></div></div>
                        <div class="space-y-6">
                            <div class="grid grid-cols-2 gap-8">
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">AccessKey</label><input type="text" id="sms_jdcloud_access_key" class="minimal-input font-mono text-xs" placeholder="********"></div>
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">SecretKey</label><input type="password" id="sms_jdcloud_secret_key" class="minimal-input font-mono text-xs" placeholder="********"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-8">
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">签名 ID</label><input type="text" id="sms_jdcloud_sign_id" class="minimal-input font-mono text-xs" placeholder=""></div>
                                <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">模板 ID</label><input type="text" id="sms_jdcloud_template_id" class="minimal-input font-mono text-xs" placeholder=""></div>
                            </div>
                            <p class="text-xs text-gray-500">未配置 AccessKey/SecretKey 时将使用 Mock 模式</p>
                        </div>
                    </section>
                    <section class="border border-gray-100 p-6 rounded-sm">
                        <h4 class="font-medium text-black mb-4">开发调试</h4>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm">Mock 模式下在接口返回验证码</p>
                                <p class="text-xs text-gray-400 mt-1">便于本地/测试环境调试，生产环境请关闭</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="sms_mock_return_code" class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                            </label>
                        </div>
                    </section>
                    <section class="bg-gray-50 border border-gray-100 p-6 rounded-sm flex items-end gap-4">
                        <div class="flex-1">
                            <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-2">测试接收手机号</label>
                            <input type="tel" id="sms_test_phone" class="bg-transparent border-b border-gray-200 py-2 outline-none w-full font-mono text-sm" placeholder="13800000000">
                        </div>
                        <button type="button" id="btn-sms-test" class="bg-black text-white text-xs px-6 py-2.5">发送测试</button>
                    </section>
                </div>

                <div id="panel-security" class="panel space-y-12 max-w-3xl">
                    <h3 class="font-serif text-xl border-b border-gray-100 pb-2">安全策略</h3>
                    
                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">登录限流</h4>
                        <p class="text-xs text-gray-400">同一 IP 在时间窗口内的最大请求次数，防止暴力破解</p>
                        <div class="grid grid-cols-2 gap-8">
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">登录尝试（次/分钟）</label><input type="number" id="security_login_rate_limit" class="minimal-input font-mono text-sm" placeholder="10" min="3" max="50"></div>
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">短信发送（次/分钟）</label><input type="number" id="security_sms_rate_limit" class="minimal-input font-mono text-sm" placeholder="5" min="2" max="20"></div>
                        </div>
                    </section>

                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">密码策略</h4>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">密码最小长度</label><input type="number" id="security_password_min_length" class="minimal-input font-mono text-sm w-24" placeholder="6" min="6" max="32"></div>
                    </section>

                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">会话超时</h4>
                        <p class="text-xs text-gray-400">登录后的 Session 有效期（小时）</p>
                        <div class="grid grid-cols-2 gap-8">
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">普通登录（小时）</label><input type="number" id="security_session_hours" class="minimal-input font-mono text-sm" placeholder="24" min="1" max="168"></div>
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">记住登录（小时）</label><input type="number" id="security_remember_hours" class="minimal-input font-mono text-sm" placeholder="168" min="24" max="720"></div>
                        </div>
                        <p class="text-xs text-gray-500">168 小时 = 7 天</p>
                    </section>

                    <section class="border border-gray-100 p-6 rounded-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-black">Session Cookie 仅 HTTPS</h4>
                                <p class="text-xs text-gray-400 mt-1">启用后 HTTP 访问将无法保持登录，生产 HTTPS 环境建议开启</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="security_session_cookie_secure" class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                            </label>
                        </div>
                    </section>
                </div>
                
                <div id="panel-email" class="panel space-y-12 max-w-3xl">
                    <h3 class="font-serif text-xl border-b border-gray-100 pb-2">SMTP 配置</h3>
                    <p class="text-xs text-gray-400 mt-2">用于发送验证码、通知等邮件，支持 TLS/SSL 加密</p>
                    
                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">服务器</h4>
                        <div class="grid grid-cols-2 gap-8">
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">SMTP 主机</label><input type="text" id="smtp_host" class="minimal-input font-mono text-sm" placeholder="smtp.example.com"></div>
                            <div>
                                <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">端口</label>
                                <div class="flex gap-2 items-center">
                                    <select id="smtp_port_preset" class="minimal-input bg-transparent flex-shrink-0 w-28">
                                        <option value="25">25</option>
                                        <option value="465">465</option>
                                        <option value="587" selected>587</option>
                                        <option value="">自定义</option>
                                    </select>
                                    <input type="number" id="smtp_port" class="minimal-input font-mono text-sm flex-1" placeholder="587" min="1" max="65535" title="自定义端口">
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">加密方式</label>
                            <select id="smtp_encryption" class="minimal-input bg-transparent">
                                <option value="">无</option>
                                <option value="tls">TLS (推荐)</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                    </section>

                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">认证</h4>
                        <div class="grid grid-cols-2 gap-8">
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">用户名</label><input type="text" id="smtp_username" class="minimal-input font-mono text-sm" placeholder="user@example.com"></div>
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">密码</label><input type="password" id="smtp_password" class="minimal-input font-mono text-sm" placeholder="********"></div>
                        </div>
                    </section>

                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">发件人</h4>
                        <div class="grid grid-cols-2 gap-8">
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">发件邮箱</label><input type="email" id="smtp_from_email" class="minimal-input font-mono text-sm" placeholder="noreply@example.com"></div>
                            <div><label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">发件人名称</label><input type="text" id="smtp_from_name" class="minimal-input font-mono text-sm" placeholder="REXTIAN ID"></div>
                        </div>
                    </section>

                    <section class="bg-gray-50 border border-gray-100 p-6 rounded-sm flex items-end gap-4">
                        <div class="flex-1">
                            <label class="text-[10px] text-gray-400 uppercase tracking-wider block mb-2">测试接收邮箱</label>
                            <input type="email" id="smtp_test_email" class="bg-transparent border-b border-gray-200 py-2 outline-none w-full font-mono text-sm" placeholder="test@example.com">
                        </div>
                        <button type="button" id="btn-email-test" class="bg-black text-white text-xs px-6 py-2.5">发送测试</button>
                    </section>
                </div>

                <div id="panel-social" class="panel space-y-12 max-w-3xl">
                    
                    <section class="bg-gray-50 border border-gray-100 p-6 rounded-sm">
                        <h3 class="font-serif text-lg mb-4">授权回调 URL</h3>
                        <p class="text-xs text-gray-400 mb-4">请在各第三方平台开发者后台配置以下回调地址</p>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 bg-white p-3 rounded border border-gray-100">
                                <i class="ri-wechat-fill text-lg text-[#07C160]"></i>
                                <span class="text-xs text-gray-400 flex-1 font-mono" id="callback-wechat"></span>
                                <button type="button" onclick="copyCallbackUrl('wechat')" class="text-xs text-black border border-black px-2 py-1 hover:bg-black hover:text-white transition">复制</button>
                            </div>
                            <div class="flex items-center gap-3 bg-white p-3 rounded border border-gray-100">
                                <i class="ri-github-fill text-lg text-black"></i>
                                <span class="text-xs text-gray-400 flex-1 font-mono" id="callback-github"></span>
                                <button type="button" onclick="copyCallbackUrl('github')" class="text-xs text-black border border-black px-2 py-1 hover:bg-black hover:text-white transition">复制</button>
                            </div>
                            <div class="flex items-center gap-3 bg-white p-3 rounded border border-gray-100">
                                <i class="ri-dingding-fill text-lg text-[#007FFF]"></i>
                                <span class="text-xs text-gray-400 flex-1 font-mono" id="callback-dingtalk"></span>
                                <button type="button" onclick="copyCallbackUrl('dingtalk')" class="text-xs text-black border border-black px-2 py-1 hover:bg-black hover:text-white transition">复制</button>
                            </div>
                            <div class="flex items-center gap-3 bg-white p-3 rounded border border-gray-100">
                                <i class="ri-send-plane-fill text-lg text-[#00D6B9]"></i>
                                <span class="text-xs text-gray-400 flex-1 font-mono" id="callback-feishu"></span>
                                <button type="button" onclick="copyCallbackUrl('feishu')" class="text-xs text-black border border-black px-2 py-1 hover:bg-black hover:text-white transition">复制</button>
                            </div>
                            <div class="flex items-center gap-3 bg-white p-3 rounded border border-gray-100">
                                <i class="ri-briefcase-4-fill text-lg text-[#2568F4]"></i>
                                <span class="text-xs text-gray-400 flex-1 font-mono" id="callback-wecom"></span>
                                <button type="button" onclick="copyCallbackUrl('wecom')" class="text-xs text-black border border-black px-2 py-1 hover:bg-black hover:text-white transition">复制</button>
                            </div>
                        </div>
                    </section>
                    
                    <section>
                        <h3 class="font-serif text-xl mb-6 border-b border-gray-100 pb-2">社交平台 (Social)</h3>
                        <div class="space-y-6">
                            <div class="border border-gray-100 p-6 rounded-sm flex justify-between items-start hover:shadow-lg hover:shadow-gray-100/50 transition duration-300">
                                <div class="flex gap-5 w-full">
                                    <i class="ri-wechat-fill text-3xl text-[#07C160]"></i>
                                    <div class="flex-1">
                                        <h4 class="font-medium text-black">WeChat (微信)</h4>
                                        <p class="text-xs text-gray-400 mt-1 mb-4">微信开放平台网页应用</p>
                                        <div class="grid grid-cols-2 gap-4 w-11/12">
                                            <input type="text" id="wechat_app_id" class="minimal-input text-xs font-mono" placeholder="App ID">
                                            <input type="password" id="wechat_app_secret" class="minimal-input text-xs font-mono" placeholder="App Secret">
                                        </div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" id="wechat_enabled" checked class="sr-only peer">
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                                </label>
                            </div>
                            
                            <div class="border border-gray-100 p-6 rounded-sm flex justify-between items-start hover:shadow-lg hover:shadow-gray-100/50 transition duration-300">
                                <div class="flex gap-5 w-full">
                                    <i class="ri-github-fill text-3xl text-black"></i>
                                    <div class="flex-1">
                                        <h4 class="font-medium text-black">GitHub</h4>
                                        <p class="text-xs text-gray-400 mt-1 mb-4">开发者账户登录</p>
                                        <div class="grid grid-cols-2 gap-4 w-11/12">
                                            <input type="text" id="github_client_id" class="minimal-input text-xs font-mono" placeholder="Client ID">
                                            <input type="password" id="github_client_secret" class="minimal-input text-xs font-mono" placeholder="Client Secret">
                                        </div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" id="github_enabled" class="sr-only peer">
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="font-serif text-xl mb-6 border-b border-gray-100 pb-2">企业办公平台 (Enterprise)</h3>
                        <div class="space-y-6">
                            
                            <div class="border border-gray-100 p-6 rounded-sm flex justify-between items-start hover:border-[#007FFF] transition duration-300 group">
                                <div class="flex gap-5 w-full">
                                    <i class="ri-dingding-fill text-3xl text-[#007FFF]"></i>
                                    <div class="flex-1">
                                        <h4 class="font-medium text-black group-hover:text-[#007FFF] transition">DingTalk (钉钉)</h4>
                                        <p class="text-xs text-gray-400 mt-1 mb-4">企业内部开发H5微应用</p>
                                        <div class="grid grid-cols-2 gap-4 w-11/12">
                                            <div><label class="text-[10px] text-gray-400 uppercase">AppKey</label><input type="text" id="dingtalk_appkey" class="minimal-input text-xs font-mono" placeholder="ding********"></div>
                                            <div><label class="text-[10px] text-gray-400 uppercase">AppSecret</label><input type="password" id="dingtalk_app_secret" class="minimal-input text-xs font-mono" placeholder="********"></div>
                                        </div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" id="dingtalk_enabled" class="sr-only peer">
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                                </label>
                            </div>

                            <div class="border border-gray-100 p-6 rounded-sm flex justify-between items-start hover:border-[#00D6B9] transition duration-300 group">
                                <div class="flex gap-5 w-full">
                                    <i class="ri-send-plane-fill text-3xl text-[#00D6B9]"></i>
                                    <div class="flex-1">
                                        <h4 class="font-medium text-black group-hover:text-[#00D6B9] transition">Feishu (飞书)</h4>
                                        <p class="text-xs text-gray-400 mt-1 mb-4">飞书自建应用 SSO</p>
                                        <div class="grid grid-cols-2 gap-4 w-11/12">
                                            <div><label class="text-[10px] text-gray-400 uppercase">App ID</label><input type="text" id="feishu_app_id" class="minimal-input text-xs font-mono" placeholder="cli_********"></div>
                                            <div><label class="text-[10px] text-gray-400 uppercase">App Secret</label><input type="password" id="feishu_app_secret" class="minimal-input text-xs font-mono" placeholder="********"></div>
                                        </div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" id="feishu_enabled" class="sr-only peer">
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                                </label>
                            </div>

                            <div class="border border-gray-100 p-6 rounded-sm flex justify-between items-start hover:border-[#2568F4] transition duration-300 group">
                                <div class="flex gap-5 w-full">
                                    <i class="ri-briefcase-4-fill text-3xl text-[#2568F4]"></i>
                                    <div class="flex-1">
                                        <h4 class="font-medium text-black group-hover:text-[#2568F4] transition">WeCom (企业微信)</h4>
                                        <p class="text-xs text-gray-400 mt-1 mb-4">企业微信自建代开发应用</p>
                                        <div class="space-y-4 w-11/12">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div><label class="text-[10px] text-gray-400 uppercase">Corp ID</label><input type="text" id="wecom_corp_id" class="minimal-input text-xs font-mono" placeholder="ww********"></div>
                                                <div><label class="text-[10px] text-gray-400 uppercase">Agent ID</label><input type="text" id="wecom_agent_id" class="minimal-input text-xs font-mono" placeholder="100001"></div>
                                            </div>
                                            <div><label class="text-[10px] text-gray-400 uppercase">Secret</label><input type="password" id="wecom_secret" class="minimal-input text-xs font-mono" placeholder="应用 Secret"></div>
                                        </div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" id="wecom_enabled" class="sr-only peer">
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                                </label>
                            </div>

                        </div>
                    </section>
                </div>

                <div id="panel-audit" class="panel space-y-8 max-w-3xl">
                    <h3 class="font-serif text-xl mb-6 border-b border-gray-100 pb-2">审计与日志</h3>
                    
                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">日志保留</h4>
                        <div>
                            <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">保留天数</label>
                            <input type="number" id="audit_retention_days" class="minimal-input font-mono text-sm w-32" placeholder="90" min="0" max="3650">
                            <p class="text-xs text-gray-500 mt-2">0 表示不自动清理，建议 90–365 天</p>
                        </div>
                    </section>

                    <section class="border border-gray-100 p-6 rounded-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-black">清理过期日志</h4>
                                <p class="text-xs text-gray-400 mt-1">根据保留天数删除过期记录，建议定期执行</p>
                            </div>
                            <button type="button" id="btn-audit-cleanup" class="bg-black text-white text-xs px-6 py-2.5 hover:bg-gray-800 transition">立即清理</button>
                        </div>
                        <p id="audit-cleanup-result" class="text-xs text-gray-500 mt-3 hidden"></p>
                    </section>

                    <section class="flex items-center gap-4">
                        <a href="auditlog.php" class="text-xs text-black border-b border-black pb-0.5 hover:opacity-70">前往审计日志 →</a>
                        <a href="api/audit-logs/export.php?format=csv" target="_blank" class="text-xs text-gray-500 hover:text-black">导出 CSV</a>
                    </section>
                </div>

                <div id="panel-radius" class="panel space-y-12 max-w-4xl">
                    <h3 class="font-serif text-xl mb-6 border-b border-gray-100 pb-2">RADIUS 认证</h3>
                    <p class="text-xs text-gray-400 mt-2">用于WiFi Portal认证，支持标准RADIUS协议</p>
                    
                    <section class="border border-gray-100 p-6 rounded-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-black">启用 RADIUS 认证</h4>
                                <p class="text-xs text-gray-400 mt-1">开启后可通过API进行RADIUS认证和记账</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="radius_enabled" class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                            </label>
                        </div>
                    </section>

                    <section class="border border-gray-100 p-6 rounded-sm space-y-6">
                        <h4 class="font-medium text-black">会话配置</h4>
                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">认证端口</label>
                                <input type="number" id="radius_auth_port" class="minimal-input font-mono text-sm" placeholder="1812" min="1" max="65535">
                            </div>
                            <div>
                                <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">记账端口</label>
                                <input type="number" id="radius_acct_port" class="minimal-input font-mono text-sm" placeholder="1813" min="1" max="65535">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">会话超时（秒）</label>
                                <input type="number" id="radius_default_session_timeout" class="minimal-input font-mono text-sm" placeholder="86400" min="60" max="31536000">
                            </div>
                            <div>
                                <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">空闲超时（秒）</label>
                                <input type="number" id="radius_default_idle_timeout" class="minimal-input font-mono text-sm" placeholder="3600" min="60" max="86400">
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <div>
                                <h4 class="font-medium text-black">需要 MFA 验证</h4>
                                <p class="text-xs text-gray-400 mt-1">启用后，已开启MFA的用户无法通过RADIUS登录</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="radius_require_mfa" class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                            </label>
                        </div>
                    </section>

                    <section>
                        <div class="flex items-center justify-between mb-6">
                            <h4 class="font-medium text-black">RADIUS 客户端（NAS）</h4>
                            <button type="button" id="btn-add-radius-client" class="text-xs border border-black px-4 py-2 hover:bg-black hover:text-white transition">添加客户端</button>
                        </div>
                        
                        <div id="radius-clients-list" class="space-y-4">
                            <p class="text-xs text-gray-400">暂无客户端</p>
                        </div>
                    </section>

                    <section class="bg-gray-50 border border-gray-100 p-6 rounded-sm">
                        <h4 class="font-medium text-black mb-4">API 接口</h4>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 bg-white p-3 rounded border border-gray-100">
                                <i class="ri-shield-keyhole-line text-lg text-black"></i>
                                <span class="text-xs text-gray-400 flex-1 font-mono" id="radius-api-auth">/api/radius/auth.php</span>
                                <span class="text-xs text-gray-500">认证</span>
                            </div>
                            <div class="flex items-center gap-3 bg-white p-3 rounded border border-gray-100">
                                <i class="ri-file-list-3-line text-lg text-black"></i>
                                <span class="text-xs text-gray-400 flex-1 font-mono" id="radius-api-acct">/api/radius/acct.php</span>
                                <span class="text-xs text-gray-500">记账</span>
                            </div>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </main>

    <script>
        const SETTING_IDS = ['site_name','site_url','sms_provider','sms_key','sms_secret','sms_sign_name','sms_template_code','sms_tencent_secret_id','sms_tencent_secret_key','sms_tencent_sdk_app_id','sms_tencent_sign_name','sms_tencent_template_id','sms_jdcloud_access_key','sms_jdcloud_secret_key','sms_jdcloud_sign_id','sms_jdcloud_template_id','sms_mock_return_code','sms_test_phone','smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password','smtp_from_email','smtp_from_name','smtp_test_email','audit_retention_days','security_login_rate_limit','security_sms_rate_limit','security_password_min_length','security_session_hours','security_remember_hours','security_session_cookie_secure','wechat_app_id','wechat_app_secret','wechat_enabled','github_client_id','github_client_secret','github_enabled','dingtalk_appkey','dingtalk_app_secret','dingtalk_enabled','feishu_app_id','feishu_app_secret','feishu_enabled','wecom_corp_id','wecom_agent_id','wecom_secret','wecom_enabled','radius_enabled','radius_auth_port','radius_acct_port','radius_default_session_timeout','radius_default_idle_timeout','radius_require_mfa'];
        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

        function switchPanel(panelId, navItem) {
            document.querySelectorAll('.settings-nav-item').forEach(el => el.classList.remove('active'));
            navItem.classList.add('active');
            document.querySelectorAll('.panel').forEach(el => el.classList.remove('active'));
            const targetPanel = document.getElementById('panel-' + panelId);
            if(targetPanel) targetPanel.classList.add('active');
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg || '配置已保存';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }

        function loadSettings() {
            fetch('api/settings.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res.code !== 0) return;
                    const d = res.data || {};
                    SETTING_IDS.forEach(id => {
                        if (id === 'sms_provider') {
                            const val = d[id] || 'aliyun';
                            document.querySelectorAll('input[name="sms_provider"]').forEach(r => { r.checked = (r.value === val); });
                            return;
                        }
                        const el = document.getElementById(id);
                        if (!el) return;
                        if (el.type === 'checkbox') {
                            el.checked = d[id] === '1' || d[id] === 'true' || d[id] === true;
                        } else if (d[id] != null) {
                            el.value = d[id];
                        }
                    });
                    syncSmtpPortPreset();
                    updateSmsProviderVisibility();
                });
        }

        function syncSmtpPortPreset() {
            const port = document.getElementById('smtp_port').value;
            const preset = document.getElementById('smtp_port_preset');
            if (['25','465','587'].includes(port)) preset.value = port;
            else preset.value = '';
        }
        document.getElementById('smtp_port_preset').addEventListener('change', function() {
            const v = this.value;
            document.getElementById('smtp_port').value = v || '';
        });
        document.getElementById('smtp_port').addEventListener('input', syncSmtpPortPreset);

        function updateSmsProviderVisibility() {
            const provider = document.querySelector('input[name="sms_provider"]:checked')?.value || 'aliyun';
            document.querySelectorAll('.sms-provider-section').forEach(s => s.classList.add('hidden'));
            const target = document.getElementById('sms-' + provider);
            if (target) target.classList.remove('hidden');
        }

        function saveSettings() {
            const payload = {};
            SETTING_IDS.forEach(id => {
                if (id === 'sms_provider') {
                    payload[id] = document.querySelector('input[name="sms_provider"]:checked')?.value || 'aliyun';
                    return;
                }
                const el = document.getElementById(id);
                if (!el) return;
                if (el.type === 'checkbox') {
                    payload[id] = el.checked;
                } else {
                    payload[id] = el.value.trim();
                }
            });
            fetch('api/settings/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) showToast('配置已保存');
                else showToast(data.message || '保存失败');
            })
            .catch(() => showToast('网络错误'));
        }

        document.getElementById('btn-audit-cleanup').addEventListener('click', function() {
            const btn = this;
            const resultEl = document.getElementById('audit-cleanup-result');
            const days = parseInt(document.getElementById('audit_retention_days').value, 10) || 90;
            if (!confirm('将删除 ' + days + ' 天前的日志，确定继续？')) return;
            btn.disabled = true;
            resultEl.classList.add('hidden');
            fetch('api/audit-logs/cleanup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ retention_days: days }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) {
                    resultEl.textContent = '已清理 ' + (data.data?.deleted || 0) + ' 条过期日志';
                    resultEl.classList.remove('hidden');
                    showToast('清理完成');
                } else {
                    showToast(data.message || '清理失败');
                }
            })
            .catch(() => showToast('网络错误'))
            .finally(() => { btn.disabled = false; });
        });

        document.getElementById('btn-email-test').addEventListener('click', function() {
            let email = document.getElementById('smtp_test_email').value.trim();
            if (!email) { showToast('请输入测试邮箱'); return; }
            const btn = this;
            btn.disabled = true;
            fetch('api/settings/email-test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ email }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) showToast(data.data?.message || '测试邮件已发送');
                else showToast(data.message || '发送失败');
            })
            .catch(() => showToast('网络错误'))
            .finally(() => { btn.disabled = false; });
        });

        document.querySelectorAll('input[name="sms_provider"]').forEach(r => {
            r.addEventListener('change', updateSmsProviderVisibility);
        });

        document.getElementById('btn-sms-test').addEventListener('click', function() {
            let phone = document.getElementById('sms_test_phone').value.trim().replace(/\D/g, '');
            if (phone.length > 11) phone = phone.slice(-11);
            if (!phone || phone.length !== 11) { showToast('请输入正确的11位手机号'); return; }
            fetch('api/settings/sms-test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ phone }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) showToast(data.data?.message || '测试短信已发送（mock）');
                else showToast(data.message || '发送失败');
            })
            .catch(() => showToast('网络错误'));
        });

        function getCallbackUrl(provider) {
            const protocol = location.protocol;
            const host = location.host;
            return `${protocol}//${host}/auth/login/${provider}/callback.php`;
        }

        function updateCallbackUrls() {
            const providers = ['wechat', 'github', 'dingtalk', 'feishu', 'wecom'];
            providers.forEach(provider => {
                const el = document.getElementById('callback-' + provider);
                if (el) {
                    el.textContent = getCallbackUrl(provider);
                }
            });
        }

        function copyCallbackUrl(provider) {
            const url = getCallbackUrl(provider);
            try {
                const textarea = document.createElement('textarea');
                textarea.value = url;
                textarea.style.position = 'fixed';
                textarea.style.top = '0';
                textarea.style.left = '0';
                textarea.style.width = '2em';
                textarea.style.height = '2em';
                textarea.style.padding = '0';
                textarea.style.border = 'none';
                textarea.style.outline = 'none';
                textarea.style.boxShadow = 'none';
                textarea.style.background = 'transparent';
                document.body.appendChild(textarea);
                textarea.select();
                textarea.setSelectionRange(0, 99999);
                const success = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (success) {
                    showToast('已复制到剪贴板');
                } else {
                    showToast('复制失败，请手动复制');
                }
            } catch (e) {
                showToast('复制失败，请手动复制');
            }
        }

        let radiusClients = [];
        
        function loadRadiusClients() {
            fetch('api/radius/clients.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res.code !== 0) return;
                    radiusClients = res.data || [];
                    renderRadiusClients();
                });
        }
        
        function renderRadiusClients() {
            const listEl = document.getElementById('radius-clients-list');
            if (!radiusClients || radiusClients.length === 0) {
                listEl.innerHTML = '<p class="text-xs text-gray-400">暂无客户端</p>';
                return;
            }
            listEl.innerHTML = radiusClients.map(client => `
                <div class="border border-gray-100 p-4 rounded-sm hover:shadow-lg transition">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <i class="ri-wifi-2-line text-lg"></i>
                            <span class="font-medium">${client.name}</span>
                        </div>
                        <span class="text-xs px-2 py-1 rounded ${client.status === 'active' ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500'}">
                            ${client.status === 'active' ? '活跃' : '停用'}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-xs text-gray-500">
                        <div>IP: <span class="font-mono text-gray-700">${client.ip_address}</span></div>
                        <div>密钥: <span class="font-mono text-gray-700">••••••••</span></div>
                    </div>
                    ${client.description ? `<p class="text-xs text-gray-400 mt-2">${client.description}</p>` : ''}
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                        <button type="button" onclick="editRadiusClient(${client.id})" class="text-xs text-gray-500 hover:text-black">编辑</button>
                        <button type="button" onclick="deleteRadiusClient(${client.id})" class="text-xs text-red-500 hover:text-red-700">删除</button>
                    </div>
                </div>
            `).join('');
        }
        
        function showRadiusClientModal(client = null) {
            const modal = document.createElement('div');
            modal.id = 'radius-client-modal';
            modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-sm p-6 w-full max-w-md mx-4">
                    <h3 class="font-serif text-xl mb-6">${client ? '编辑' : '添加'} RADIUS 客户端</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">名称</label>
                            <input type="text" id="modal-client-name" class="minimal-input" placeholder="WiFi AP" value="${client?.name || ''}">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">IP 地址</label>
                            <input type="text" id="modal-client-ip" class="minimal-input font-mono" placeholder="192.168.1.1" value="${client?.ip_address || ''}">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">共享密钥 ${client ? '(留空不修改)' : ''}</label>
                            <input type="password" id="modal-client-secret" class="minimal-input font-mono" placeholder="secret-key">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 uppercase tracking-wider block mb-1">描述</label>
                            <input type="text" id="modal-client-desc" class="minimal-input" placeholder="可选" value="${client?.description || ''}">
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm">状态</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="modal-client-status" class="sr-only peer" ${!client || client.status === 'active' ? 'checked' : ''}>
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="button" onclick="document.getElementById('radius-client-modal').remove()" class="flex-1 py-2 text-sm border border-gray-200 hover:bg-gray-50">取消</button>
                        <button type="button" id="modal-save-btn" class="flex-1 py-2 text-sm bg-black text-white hover:bg-gray-800">保存</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            modal.querySelector('#modal-save-btn').addEventListener('click', () => {
                const data = {
                    name: document.getElementById('modal-client-name').value.trim(),
                    ip_address: document.getElementById('modal-client-ip').value.trim(),
                    secret: document.getElementById('modal-client-secret').value,
                    description: document.getElementById('modal-client-desc').value.trim(),
                    status: document.getElementById('modal-client-status').checked ? 'active' : 'inactive'
                };
                if (client) {
                    data.id = client.id;
                    saveRadiusClient(data, 'PUT');
                } else {
                    saveRadiusClient(data, 'POST');
                }
            });
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.remove();
            });
        }
        
        function saveRadiusClient(data, method) {
            fetch('api/radius/clients.php', {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify(data),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(res => {
                if (res.code === 0) {
                    document.getElementById('radius-client-modal').remove();
                    loadRadiusClients();
                    showToast('保存成功');
                } else {
                    showToast(res.message || '保存失败');
                }
            })
            .catch(() => showToast('网络错误'));
        }
        
        function editRadiusClient(id) {
            const client = radiusClients.find(c => c.id === id);
            if (client) showRadiusClientModal(client);
        }
        
        function deleteRadiusClient(id) {
            if (!confirm('确定删除此客户端？')) return;
            fetch('api/radius/clients.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ id }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(res => {
                if (res.code === 0) {
                    loadRadiusClients();
                    showToast('删除成功');
                } else {
                    showToast(res.message || '删除失败');
                }
            })
            .catch(() => showToast('网络错误'));
        }
        
        document.getElementById('btn-add-radius-client').addEventListener('click', () => {
            showRadiusClientModal();
        });
        
        loadSettings();
        updateCallbackUrls();
        loadRadiusClients();
    </script>
</body>
</html>
