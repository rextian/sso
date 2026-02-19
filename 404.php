<?php
/**
 * REXTIAN SSO - 404 错误页
 */
require_once __DIR__ . '/config.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@100;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            background-color: #f9fafb; 
            color: #111; 
            overflow: hidden;
        }
        .font-serif { font-family: "Noto Serif SC", serif; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, monospace; }
        .hairline { height: 1px; background: #e5e7eb; width: 100%; }
        .text-huge {
            font-size: 12rem;
            line-height: 1;
            background: linear-gradient(180deg, #111 0%, #666 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.05em;
        }
        .btn-link {
            position: relative;
            text-decoration: none;
            padding-bottom: 2px;
        }
        .btn-link::after {
            content: ''; position: absolute; width: 100%; height: 1px; bottom: 0; left: 0; 
            background-color: #000; transform: scaleX(0); transform-origin: bottom right; 
            transition: transform 0.3s ease-out;
        }
        .btn-link:hover::after { transform: scaleX(1); transform-origin: bottom left; }
    </style>
</head>
<body class="h-screen w-full flex flex-col justify-between p-8 sm:p-12 lg:p-20 relative">

    <div class="absolute top-0 left-0 w-full h-full pointer-events-none z-0">
        <div class="absolute top-0 left-1/4 w-px h-full bg-gray-100"></div>
        <div class="absolute top-1/3 left-0 w-full h-px bg-gray-100"></div>
    </div>

    <nav class="flex justify-between items-center relative z-10">
        <a href="login.php" class="font-serif text-xl font-bold tracking-widest text-black">REXTIAN</a>
        <span class="text-xs font-mono text-gray-400">ERROR_PAGE_RENDER</span>
    </nav>

    <main class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-12 mt-10 lg:mt-0">
        
        <div class="relative">
            <h1 class="font-serif text-huge font-thin">404</h1>
            <div class="absolute -bottom-4 left-2 flex gap-4 text-xs font-mono text-gray-400">
                <span>Code: NOT_FOUND</span>
                <span>/</span>
                <span>Ref: unknown_path</span>
            </div>
        </div>

        <div class="max-w-md space-y-8 lg:border-l lg:border-gray-200 lg:pl-12 py-4">
            <div>
                <h2 class="font-serif text-3xl mb-4 italic">Page vanished into the void.</h2>
                <p class="text-sm text-gray-500 leading-relaxed font-light">
                    您访问的页面似乎已不存在，或者从未存在过。它可能已被移动、编辑或被永久删除。
                </p>
                <p class="text-xs text-gray-400 mt-4 font-mono">
                    Trace ID: err_882910aa-bb29-cc38
                </p>
            </div>

            <div class="hairline"></div>

            <div class="flex gap-8 items-center">
                <a href="index.php" class="bg-black text-white text-xs px-8 py-4 uppercase tracking-widest hover:bg-gray-800 transition shadow-xl">
                    返回首页
                </a>
                <a href="login.php" class="btn-link text-xs uppercase tracking-widest text-gray-500 hover:text-black transition">
                    返回登录
                </a>
            </div>
        </div>
    </main>

    <footer class="flex justify-between items-end relative z-10 text-[10px] text-gray-400 uppercase tracking-widest">
        <div class="flex gap-6">
            <span>System Status: <span class="text-green-600">● Normal</span></span>
            <span>Latency: 24ms</span>
        </div>
        <div class="text-right">
            <p>© 2026 REXTIAN Identity System</p>
        </div>
    </footer>

</body>
</html>
