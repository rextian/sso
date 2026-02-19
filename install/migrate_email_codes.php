<?php
/**
 * REXTIAN SSO - 执行数据库迁移：创建邮箱验证码表
 */
require_once __DIR__ . '/../config.php';

echo "正在创建邮箱验证码表...\n";

$pdo = getDb();
if (!$pdo) {
    die("数据库连接失败\n");
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_codes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(128) NOT NULL,
        code VARCHAR(64) NOT NULL,
        type ENUM('register','reset_password') NOT NULL DEFAULT 'register',
        used TINYINT(1) NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        used_at DATETIME NULL,
        INDEX idx_email_expires (email, type, expires_at),
        INDEX idx_used (used)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "迁移成功！email_codes 表已创建。\n";
} catch (PDOException $e) {
    die("迁移失败: " . $e->getMessage() . "\n");
}
?>
