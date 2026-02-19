-- OAuth 授权码临时表（第9天）
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
