<?php
/**
 * REXTIAN SSO - 统一身份认证系统
 * 全局配置文件 - 后续可在此添加 MySQL 连接、常量等
 */

// 防止直接访问
defined('REXTIAN_SSO') or define('REXTIAN_SSO', true);

// 站点基础 URL（后续用于 OAuth 回调等）
define('SITE_URL', 'https://sso.rextian.com');
define('SITE_NAME', 'REXTIAN ID');

// Session Cookie Secure：true=仅 HTTPS 发送（生产推荐）；false=HTTP 也可用（内网/开发）
define('SESSION_COOKIE_SECURE', false);

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', '8085');
define('DB_USER', '8085');
define('DB_PASS', 'T516fN9YebrA7RbB');
define('DB_CHARSET', 'utf8mb4');

// 会话配置 - Cookie 安全属性（HttpOnly、Secure、SameSite）
if (session_status() === PHP_SESSION_NONE) {
    // Secure: 优先读取系统设置中的 security_session_cookie_secure，否则用 config 常量
    $secureOverrideFile = __DIR__ . '/data/security_session_secure.php';
    $forceSecure = file_exists($secureOverrideFile) ? (bool) include $secureOverrideFile : null;
    $useSecure = ($forceSecure !== null) ? $forceSecure : SESSION_COOKIE_SECURE;
    $isSecure = $useSecure && (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * 获取 PDO 数据库连接（按需调用）
 * @return PDO|null
 */
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
