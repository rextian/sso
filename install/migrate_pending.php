<?php
/**
 * REXTIAN SSO - 执行数据库迁移：添加 pending 状态
 */
require_once __DIR__ . '/../config.php';

echo "正在执行数据库迁移...\n";

$pdo = getDb();
if (!$pdo) {
    die("数据库连接失败\n");
}

try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN status ENUM('active','banned','pending') DEFAULT 'active'");
    echo "迁移成功！users 表现在支持 pending 状态。\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'enum') !== false) {
        echo "状态字段可能已更新，跳过。\n";
    } else {
        die("迁移失败: " . $e->getMessage() . "\n");
    }
}
?>
