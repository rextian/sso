-- OAuth 访问令牌表（第10天）
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
