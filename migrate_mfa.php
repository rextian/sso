<?php
/**
 * MFA数据库迁移脚本
 * 为users表添加mfa_secret和mfa_enabled字段
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

echo '<h1>MFA数据库迁移</h1>';

$pdo = getDb();
if (!$pdo) {
    echo '<p style="color: red;">数据库连接失败</p>';
    exit;
}

try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    $changes = [];
    
    if (!in_array('mfa_secret', $columnNames)) {
        echo '<p>添加 mfa_secret 字段...</p>';
        $pdo->exec("ALTER TABLE users ADD COLUMN mfa_secret VARCHAR(64) AFTER status");
        $changes[] = 'mfa_secret';
    } else {
        echo '<p style="color: green;">mfa_secret 字段已存在</p>';
    }
    
    if (!in_array('mfa_enabled', $columnNames)) {
        echo '<p>添加 mfa_enabled 字段...</p>';
        $pdo->exec("ALTER TABLE users ADD COLUMN mfa_enabled TINYINT(1) DEFAULT 0 AFTER mfa_secret");
        $changes[] = 'mfa_enabled';
    } else {
        echo '<p style="color: green;">mfa_enabled 字段已存在</p>';
    }
    
    if (empty($changes)) {
        echo '<p style="color: green; font-size: 1.2em;">数据库已是最新版本，无需迁移</p>';
    } else {
        echo '<p style="color: green; font-size: 1.2em;">迁移完成！已添加字段: ' . implode(', ', $changes) . '</p>';
    }
    
} catch (PDOException $e) {
    echo '<p style="color: red;">错误: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
