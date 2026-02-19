-- REXTIAN SSO - 迁移脚本：创建RADIUS相关表
-- 执行方式：mysql -u 8085 -p 8085 < install/migrate_radius.sql

-- RADIUS客户端表
CREATE TABLE IF NOT EXISTS radius_clients (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RADIUS会话表
CREATE TABLE IF NOT EXISTS radius_sessions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 在settings表中添加RADIUS相关设置
INSERT IGNORE INTO settings (name, value, type, description, category) VALUES
('radius_enabled', '0', 'boolean', '启用RADIUS认证', 'radius'),
('radius_auth_port', '1812', 'number', 'RADIUS认证端口', 'radius'),
('radius_acct_port', '1813', 'number', 'RADIUS记账端口', 'radius'),
('radius_default_session_timeout', '86400', 'number', '默认会话超时（秒）', 'radius'),
('radius_default_idle_timeout', '3600', 'number', '默认空闲超时（秒）', 'radius'),
('radius_require_mfa', '0', 'boolean', 'RADIUS认证需要MFA', 'radius');
