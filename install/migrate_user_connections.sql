-- 用户第三方账号绑定表（第13天）
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
