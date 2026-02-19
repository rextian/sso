-- REXTIAN SSO - 迁移脚本：创建邮箱验证码表
-- 执行方式：mysql -u 8085 -p 8085 < install/migrate_email_codes.sql

CREATE TABLE IF NOT EXISTS email_codes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
