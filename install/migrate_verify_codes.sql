-- REXTIAN SSO - 邮箱/手机更换验证码表
-- 执行: mysql -u 8085 -p 8085 < install/migrate_verify_codes.sql

CREATE TABLE IF NOT EXISTS verification_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  target VARCHAR(128) NOT NULL COMMENT 'email 或 phone',
  code VARCHAR(8) NOT NULL,
  purpose ENUM('email_change','phone_change') NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_target (user_id, target, purpose),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
