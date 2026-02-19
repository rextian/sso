<?php
/**
 * REXTIAN SSO - 执行数据库迁移：创建RADIUS相关表
 */
require_once __DIR__ . '/../config.php';

echo "正在创建RADIUS相关表...\n";

$pdo = getDb();
if (!$pdo) {
    die("数据库连接失败\n");
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS radius_clients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(128) NOT NULL COMMENT '客户端名称',
        ip_address VARCHAR(45) NOT NULL COMMENT '客户端IP地址',
        secret VARCHAR(128) NOT NULL COMMENT '共享密钥',
        description TEXT,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_ip (ip_address),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  ✓ radius_clients 表已创建\n";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS radius_sessions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        username VARCHAR(64) NOT NULL,
        nas_ip_address VARCHAR(45) NOT NULL COMMENT 'NAS IP地址',
        nas_port_id VARCHAR(128) COMMENT 'NAS端口ID',
        calling_station_id VARCHAR(64) COMMENT '主叫站ID（MAC地址）',
        called_station_id VARCHAR(64) COMMENT '被叫站ID',
        acct_session_id VARCHAR(64) NOT NULL COMMENT '计费会话ID',
        acct_status_type ENUM('Start','Interim-Update','Stop') NOT NULL,
        acct_session_time INT UNSIGNED DEFAULT 0 COMMENT '会话时长（秒）',
        acct_input_octets BIGINT UNSIGNED DEFAULT 0 COMMENT '入站字节数',
        acct_output_octets BIGINT UNSIGNED DEFAULT 0 COMMENT '出站字节数',
        acct_input_packets BIGINT UNSIGNED DEFAULT 0 COMMENT '入站数据包数',
        acct_output_packets BIGINT UNSIGNED DEFAULT 0 COMMENT '出站数据包数',
        acct_terminate_cause VARCHAR(64) COMMENT '终止原因',
        framed_ip_address VARCHAR(45) COMMENT '分配的IP地址',
        framed_protocol VARCHAR(32) COMMENT '帧协议',
        session_start DATETIME,
        session_update DATETIME,
        session_stop DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_session (acct_session_id),
        INDEX idx_nas (nas_ip_address),
        INDEX idx_status (acct_status_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  ✓ radius_sessions 表已创建\n";
    
    $settings = [
        'radius_enabled' => '0',
        'radius_auth_port' => '1812',
        'radius_acct_port' => '1813',
        'radius_default_session_timeout' => '86400',
        'radius_default_idle_timeout' => '3600',
        'radius_require_mfa' => '0'
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("SELECT `key` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
    }
    echo "  ✓ RADIUS设置已添加\n";
    
    echo "\n迁移成功！RADIUS相关表已创建。\n";
} catch (PDOException $e) {
    die("迁移失败: " . $e->getMessage() . "\n");
}
?>