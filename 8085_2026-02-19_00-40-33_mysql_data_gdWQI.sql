-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: 8085
-- ------------------------------------------------------
-- Server version	5.7.44-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trace_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `user_email` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `status` enum('success','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trace_id` (`trace_id`),
  KEY `idx_event` (`event`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (50,'63e3b1d0117cd287','audit.cleanup',1,NULL,'10.10.10.26','{\"deleted\": 49, \"retention_days\": 0}','success','2026-02-19 00:39:52');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_codes`
--

DROP TABLE IF EXISTS `email_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('register','reset_password') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'register',
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_expires` (`email`,`type`,`expires_at`),
  KEY `idx_used` (`used`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_codes`
--

LOCK TABLES `email_codes` WRITE;
/*!40000 ALTER TABLE `email_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_apps`
--

DROP TABLE IF EXISTS `oauth_apps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_apps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_type` enum('oauth','ubnt','ikuai') COLLATE utf8mb4_unicode_ci DEFAULT 'oauth',
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_secret` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect_uris` json DEFAULT NULL,
  `ubnt_api_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ubnt_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ikuai_api_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ikuai_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('live','dev') COLLATE utf8mb4_unicode_ci DEFAULT 'dev',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_apps`
--

LOCK TABLES `oauth_apps` WRITE;
/*!40000 ALTER TABLE `oauth_apps` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_apps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_authorization_codes`
--

DROP TABLE IF EXISTS `oauth_authorization_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_authorization_codes` (
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `app_id` int(10) unsigned NOT NULL,
  `redirect_uri` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`),
  KEY `idx_expires` (`expires_at`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  CONSTRAINT `oauth_authorization_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oauth_authorization_codes_ibfk_2` FOREIGN KEY (`app_id`) REFERENCES `oauth_apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_authorization_codes`
--

LOCK TABLES `oauth_authorization_codes` WRITE;
/*!40000 ALTER TABLE `oauth_authorization_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_authorization_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_tokens`
--

DROP TABLE IF EXISTS `oauth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_tokens` (
  `access_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refresh_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `app_id` int(10) unsigned NOT NULL,
  `scope` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`access_token`),
  UNIQUE KEY `refresh_token` (`refresh_token`),
  KEY `idx_refresh` (`refresh_token`),
  KEY `idx_user_app` (`user_id`,`app_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `app_id` (`app_id`),
  CONSTRAINT `oauth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oauth_tokens_ibfk_2` FOREIGN KEY (`app_id`) REFERENCES `oauth_apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_tokens`
--

LOCK TABLES `oauth_tokens` WRITE;
/*!40000 ALTER TABLE `oauth_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `token` varchar(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radius_clients`
--

DROP TABLE IF EXISTS `radius_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radius_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '客户端名称',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '客户端IP地址',
  `secret` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '共享密钥',
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ip` (`ip_address`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radius_clients`
--

LOCK TABLES `radius_clients` WRITE;
/*!40000 ALTER TABLE `radius_clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `radius_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radius_sessions`
--

DROP TABLE IF EXISTS `radius_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radius_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nas_ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NAS IP地址',
  `nas_port_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NAS端口ID',
  `calling_station_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '主叫站ID（MAC地址）',
  `called_station_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '被叫站ID',
  `acct_session_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '计费会话ID',
  `acct_status_type` enum('Start','Interim-Update','Stop') COLLATE utf8mb4_unicode_ci NOT NULL,
  `acct_session_time` int(10) unsigned DEFAULT '0' COMMENT '会话时长（秒）',
  `acct_input_octets` bigint(20) unsigned DEFAULT '0' COMMENT '入站字节数',
  `acct_output_octets` bigint(20) unsigned DEFAULT '0' COMMENT '出站字节数',
  `acct_input_packets` bigint(20) unsigned DEFAULT '0' COMMENT '入站数据包数',
  `acct_output_packets` bigint(20) unsigned DEFAULT '0' COMMENT '出站数据包数',
  `acct_terminate_cause` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '终止原因',
  `framed_ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分配的IP地址',
  `framed_protocol` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '帧协议',
  `session_start` datetime DEFAULT NULL,
  `session_update` datetime DEFAULT NULL,
  `session_stop` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_session` (`acct_session_id`),
  KEY `idx_nas` (`nas_ip_address`),
  KEY `idx_status` (`acct_status_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radius_sessions`
--

LOCK TABLES `radius_sessions` WRITE;
/*!40000 ALTER TABLE `radius_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `radius_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limit_log`
--

DROP TABLE IF EXISTS `rate_limit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `action` varchar(32) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_action_time` (`ip`,`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limit_log`
--

LOCK TABLES `rate_limit_log` WRITE;
/*!40000 ALTER TABLE `rate_limit_log` DISABLE KEYS */;
INSERT INTO `rate_limit_log` VALUES (13,'10.10.10.26','email_send','2026-02-18 20:47:57'),(14,'10.10.10.26','email_send','2026-02-18 20:51:02'),(15,'10.10.10.26','email_send','2026-02-18 20:52:58'),(16,'10.10.10.26','email_send','2026-02-18 20:55:14'),(6,'10.10.10.26','forgot_password_10.10.10.26','2026-02-17 04:55:10'),(1,'10.10.10.26','login','2026-02-17 00:31:32'),(2,'10.10.10.26','login','2026-02-17 01:29:00'),(3,'10.10.10.26','login','2026-02-17 01:34:25'),(4,'10.10.10.26','login','2026-02-17 01:35:26'),(5,'10.10.10.26','login','2026-02-17 03:56:11'),(7,'10.10.10.26','login','2026-02-17 17:44:26'),(8,'10.10.10.26','login','2026-02-18 19:28:37'),(18,'10.10.10.26','login','2026-02-18 21:14:21'),(19,'10.10.10.26','login','2026-02-18 21:14:24'),(9,'10.10.10.26','register','2026-02-18 20:21:55'),(10,'10.10.10.26','register','2026-02-18 20:22:07'),(11,'10.10.10.26','register','2026-02-18 20:22:17'),(12,'10.10.10.26','register','2026-02-18 20:29:38'),(17,'10.10.10.26','register','2026-02-18 20:56:03');
/*!40000 ALTER TABLE `rate_limit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('lugct0noq71q1mio4gbe9q2iu4',1,'10.10.10.26','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-19 19:28:37','2026-02-18 19:28:37'),('pgc4n9mfb7ct6a495ob9qbntjv',1,'127.0.0.1','test','2026-02-17 23:36:56','2026-02-16 23:36:56');
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('audit_retention_days','90','2026-02-18 19:48:34'),('dingtalk_app_secret','',NULL),('dingtalk_appkey','',NULL),('dingtalk_enabled','0',NULL),('feishu_app_id','',NULL),('feishu_app_secret','',NULL),('feishu_enabled','0',NULL),('github_client_id','',NULL),('github_client_secret','',NULL),('github_enabled','0',NULL),('radius_acct_port','1813',NULL),('radius_auth_port','1812',NULL),('radius_default_idle_timeout','3600',NULL),('radius_default_session_timeout','86400',NULL),('radius_enabled','0',NULL),('radius_require_mfa','0',NULL),('security_login_rate_limit','5','2026-02-17 17:45:42'),('security_password_min_length','6','2026-02-18 23:35:19'),('security_remember_hours','168','2026-02-18 23:35:30'),('security_session_cookie_secure','0',NULL),('security_session_hours','24','2026-02-18 23:35:30'),('security_sms_rate_limit','1','2026-02-17 17:45:37'),('site_name','REXTIAN ID',NULL),('site_url','https://sso.rextian.com',NULL),('sms_jdcloud_access_key','',NULL),('sms_jdcloud_secret_key','',NULL),('sms_jdcloud_sign_id','',NULL),('sms_jdcloud_template_id','',NULL),('sms_key','','2026-02-19 00:34:18'),('sms_mock_return_code','0',NULL),('sms_provider','aliyun',NULL),('sms_secret','','2026-02-19 00:34:18'),('sms_sign_name','瑞克斯天',NULL),('sms_template_code','','2026-02-19 00:34:18'),('sms_tencent_sdk_app_id','',NULL),('sms_tencent_secret_id','',NULL),('sms_tencent_secret_key','',NULL),('sms_tencent_sign_name','',NULL),('sms_tencent_template_id','',NULL),('sms_test_phone','','2026-02-19 00:34:22'),('smtp_encryption','','2026-02-19 00:34:40'),('smtp_from_email','','2026-02-19 00:34:40'),('smtp_from_name','RexTian ID','2026-02-17 02:08:06'),('smtp_host','','2026-02-19 00:34:40'),('smtp_password','','2026-02-19 00:34:40'),('smtp_port','','2026-02-19 00:34:40'),('smtp_test_email','','2026-02-19 00:34:40'),('smtp_username','','2026-02-19 00:34:40'),('wechat_app_id','',NULL),('wechat_app_secret','',NULL),('wechat_enabled','0',NULL),('wecom_agent_id','',NULL),('wecom_corp_id','',NULL),('wecom_enabled','0',NULL),('wecom_secret','',NULL);
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_codes`
--

DROP TABLE IF EXISTS `sms_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_phone_expires` (`phone`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_codes`
--

LOCK TABLES `sms_codes` WRITE;
/*!40000 ALTER TABLE `sms_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_app_grants`
--

DROP TABLE IF EXISTS `user_app_grants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_app_grants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `app_id` int(10) unsigned NOT NULL,
  `scopes` json DEFAULT NULL,
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_app` (`user_id`,`app_id`),
  KEY `app_id` (`app_id`),
  CONSTRAINT `user_app_grants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_app_grants_ibfk_2` FOREIGN KEY (`app_id`) REFERENCES `oauth_apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_app_grants`
--

LOCK TABLES `user_app_grants` WRITE;
/*!40000 ALTER TABLE `user_app_grants` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_app_grants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_connections`
--

DROP TABLE IF EXISTS `user_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_connections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_user_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_username` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_email` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_token` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refresh_token` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `connected_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_provider` (`user_id`,`provider`),
  UNIQUE KEY `uk_provider_uid` (`provider`,`provider_user_id`),
  CONSTRAINT `user_connections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_connections`
--

LOCK TABLES `user_connections` WRITE;
/*!40000 ALTER TABLE `user_connections` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_connections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `status` enum('active','banned','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `mfa_secret` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mfa_enabled` tinyint(1) DEFAULT '0',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@rextian.com',NULL,'$2y$10$C1i0o.hF7/06evs/0sXsh.1p1ORhsxfqoZZsRXlrMr8TyH2ixWW2.','Administrator',NULL,'admin','active','SWA2PQPLC6B7PZ4F',1,'2026-02-18 19:28:37','2026-02-16 23:35:15','2026-02-19 00:34:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database '8085'
--

--
-- Dumping routines for database '8085'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-19  0:40:33
