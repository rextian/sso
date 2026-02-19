-- REXTIAN SSO - 迁移脚本：为 users 表添加 pending 状态
-- 执行方式：mysql -u 8085 -p 8085 < install/migrate_add_pending_status.sql

ALTER TABLE users MODIFY COLUMN status ENUM('active','banned','pending') DEFAULT 'active';
