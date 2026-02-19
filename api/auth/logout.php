<?php
/**
 * REXTIAN SSO - POST /api/auth/logout
 * 退出登录
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['username'] ?? null;
if ($userId) {
    auditLog('auth.logout', (int) $userId, $userEmail, [], 'success');
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

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

echo json_encode(['code' => 0, 'message' => 'success', 'data' => null]);
