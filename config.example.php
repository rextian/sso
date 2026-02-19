<?php
/**
 * REXTIAN SSO - 配置文件示例
 * 复制为 config.php 并填写实际配置
 * 请勿将 config.php 提交到版本库
 */
defined('REXTIAN_SSO') or define('REXTIAN_SSO', true);

define('SITE_URL', 'https://sso.rextian.com');
define('SITE_NAME', 'REXTIAN ID');

define('DB_HOST', 'localhost');
define('DB_NAME', '8085');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}
