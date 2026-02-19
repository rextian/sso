<?php
/**
 * REXTIAN SSO - 退出登录（服务端处理，重定向到登录页）
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionId = session_id();
if ($sessionId) {
    $pdo = getDb();
    if ($pdo) {
        try {
            $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$sessionId]);
        } catch (PDOException $e) {
            // ignore
        }
    }
}

$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['username'] ?? null;
if ($userId) {
    auditLog('auth.logout', (int) $userId, $userEmail, [], 'success');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: login.php');
exit;
