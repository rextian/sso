<?php
/**
 * REXTIAN SSO - 500 服务器错误页
 */
require_once __DIR__ . '/config.php';
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Server Error | REXTIAN ID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500&family=Noto+Serif+SC:wght@100;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f9fafb; color: #111; }
        .font-serif { font-family: "Noto Serif SC", serif; }
        .text-huge { font-size: 12rem; line-height: 1; background: linear-gradient(180deg, #111 0%, #666 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: -0.05em; }
    </style>
</head>
<body class="h-screen w-full flex flex-col justify-center items-center p-8">
    <nav class="absolute top-8 left-8">
        <a href="index.php" class="font-serif text-xl font-bold tracking-widest text-black">REXTIAN</a>
    </nav>
    <main class="text-center">
        <h1 class="font-serif text-huge font-thin">500</h1>
        <h2 class="font-serif text-2xl mb-4">服务器内部错误</h2>
        <p class="text-sm text-gray-500 mb-8 max-w-md">服务暂时不可用，请稍后再试。如问题持续，请联系管理员。</p>
        <div class="flex gap-6 justify-center">
            <a href="index.php" class="bg-black text-white text-xs px-8 py-4 uppercase tracking-widest hover:bg-gray-800 transition">返回首页</a>
            <a href="login.php" class="text-gray-500 text-xs uppercase tracking-widest hover:text-black">登录</a>
        </div>
    </main>
</body>
</html>
