-- REXTIAN SSO - 数据库初始化脚本
-- 执行前请确保数据库 8085 已创建
-- 使用方式: mysql -u 8085 -p 8085 < install/init.sql

-- 用户表
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  email VARCHAR(128) UNIQUE,
  phone VARCHAR(20),
  password_hash VARCHAR(255),
  display_name VARCHAR(64),
  avatar VARCHAR(255),
  role ENUM('admin','user') DEFAULT 'user',
  status ENUM('active','banned') DEFAULT 'active',
  mfa_secret VARCHAR(64),
  mfa_enabled TINYINT(1) DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth 应用表
CREATE TABLE IF NOT EXISTS oauth_apps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  app_type ENUM('oauth','ubnt','ikuai') DEFAULT 'oauth',
  client_id VARCHAR(64) UNIQUE NOT NULL,
  client_secret VARCHAR(128) NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT,
  icon VARCHAR(255),
  redirect_uris JSON,
  ubnt_api_url VARCHAR(512) NULL,
  ubnt_api_key VARCHAR(255) NULL,
  ikuai_api_url VARCHAR(512) NULL,
  ikuai_token VARCHAR(255) NULL,
  status ENUM('live','dev') DEFAULT 'dev',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户会话表
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(64) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ip VARCHAR(45),
  user_agent VARCHAR(255),
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 审计日志表
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(32) UNIQUE,
  event VARCHAR(64) NOT NULL,
  user_id INT UNSIGNED,
  user_email VARCHAR(128),
  ip VARCHAR(45),
  payload JSON,
  status ENUM('success','failed') DEFAULT 'success',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event (event),
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 短信验证码表
CREATE TABLE IF NOT EXISTS sms_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  code VARCHAR(8) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_phone_expires (phone, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 系统配置表
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) PRIMARY KEY,
  `value` TEXT,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth 授权码临时表
CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
  code VARCHAR(64) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  app_id INT UNSIGNED NOT NULL,
  redirect_uri VARCHAR(512) NOT NULL,
  scope VARCHAR(255),
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expires (expires_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (app_id) REFERENCES oauth_apps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth 访问令牌表
CREATE TABLE IF NOT EXISTS oauth_tokens (
  access_token VARCHAR(64) PRIMARY KEY,
  refresh_token VARCHAR(64) UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  app_id INT UNSIGNED NOT NULL,
  scope VARCHAR(255),
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_refresh (refresh_token),
  INDEX idx_user_app (user_id, app_id),
  INDEX idx_expires (expires_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (app_id) REFERENCES oauth_apps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户第三方账号绑定表
CREATE TABLE IF NOT EXISTS user_connections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  provider VARCHAR(32) NOT NULL,
  provider_user_id VARCHAR(128) NOT NULL,
  provider_username VARCHAR(128),
  provider_email VARCHAR(128),
  access_token VARCHAR(512),
  refresh_token VARCHAR(512),
  connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_provider (user_id, provider),
  UNIQUE KEY uk_provider_uid (provider, provider_user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth 授权记录（用户授权给某应用）
CREATE TABLE IF NOT EXISTS user_app_grants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  app_id INT UNSIGNED NOT NULL,
  scopes JSON,
  granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_app (user_id, app_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (app_id) REFERENCES oauth_apps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
