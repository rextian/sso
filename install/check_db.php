<?php
/**
 * REXTIAN SSO - 数据库连接验证脚本
 * 执行方式: php install/check_db.php
 */
require_once dirname(__DIR__) . '/config.php';

echo "正在验证数据库连接...\n";

$pdo = getDb();
if (!$pdo) {
    echo "失败: 无法连接数据库。请检查 config.php 中的 DB_HOST、DB_NAME、DB_USER、DB_PASS。\n";
    exit(1);
}

echo "成功: 数据库连接正常。\n";

// 检查表是否存在
$tables = ['users', 'oauth_apps', 'sessions', 'audit_logs', 'settings', 'user_app_grants', 'oauth_authorization_codes', 'oauth_tokens', 'user_connections', 'sms_codes'];
foreach ($tables as $t) {
    $r = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
    echo $r ? "  [√] 表 $t 存在\n" : "  [×] 表 $t 不存在\n";
}

echo "验证完成。\n";
