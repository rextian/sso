-- REXTIAN SSO - 添加爱快 (iKuai) 应用类型
-- 执行: mysql -u 8085 -p 8085 < install/migrate_ikuai_apps.sql

ALTER TABLE oauth_apps
  MODIFY COLUMN app_type ENUM('oauth','ubnt','ikuai') DEFAULT 'oauth',
  ADD COLUMN ikuai_api_url VARCHAR(512) NULL AFTER ubnt_api_key,
  ADD COLUMN ikuai_token VARCHAR(255) NULL AFTER ikuai_api_url;
