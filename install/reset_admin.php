<?php
/**
 * REXTIAN SSO - 重置管理员密码
 * 用法: php install/reset_admin.php [新密码]
 * 或通过浏览器访问: /install/reset_admin.php?password=新密码
 * 生产环境使用后请删除或限制访问
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$newPassword = $argv[1] ?? $_GET['password'] ?? 'Admin@123';

if (strlen($newPassword) < 6) {
    die("错误: 密码至少 6 位。用法: php reset_admin.php 新密码\n");
}

try {
    $pdo = getDb();
    if (!$pdo) {
        die("错误: 无法连接数据库。\n");
    }

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        // 创建 admin 用户
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, role, status) VALUES ('admin', 'admin@rextian.com', 'Administrator', ?, 'admin', 'active')")
            ->execute([$hash]);
        echo "已创建管理员: username=admin, password={$newPassword}\n";
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE username = 'admin'")->execute([$hash]);
        echo "已重置管理员密码: username=admin, password={$newPassword}\n";
    }

    echo "\n请使用 admin / {$newPassword} 登录，登录后请及时修改密码。\n";
} catch (Exception $e) {
    die("错误: " . $e->getMessage() . "\n");
}
