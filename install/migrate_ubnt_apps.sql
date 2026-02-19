-- REXTIAN SSO - 迁移 UBNT 配置到 OAuth 应用表
-- 执行: mysql -u 8085 -p 8085 < install/migrate_ubnt_apps.sql

ALTER TABLE oauth_apps
  ADD COLUMN app_type ENUM('oauth','ubnt') DEFAULT 'oauth' AFTER id,
  ADD COLUMN ubnt_api_url VARCHAR(512) NULL AFTER redirect_uris,
  ADD COLUMN ubnt_api_key VARCHAR(255) NULL AFTER ubnt_api_url;
