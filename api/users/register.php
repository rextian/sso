<?php
/**
 * REXTIAN SSO - POST /api/users/register
 * 用户自助注册接口
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

$clientIp = getClientIp();
if (!checkRateLimit('register', $clientIp, 5, 60)) {
    jsonFail(42901, '注册过于频繁，请1分钟后再试');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$verificationToken = trim($input['verification_token'] ?? '');

if (empty($username) || mb_strlen($username) < 2) {
    jsonFail(40001, '用户名至少需要2个字符');
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonFail(40002, '请输入有效的电子邮箱');
}

if (empty($password) || strlen($password) < 6) {
    jsonFail(40003, '密码至少需要6个字符');
}

if (empty($verificationToken)) {
    jsonFail(40006, '请先完成邮箱验证');
}

$pdo = getDb();
if (!$pdo) {
    jsonFail(50000, '服务暂时不可用');
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT id, email FROM email_codes WHERE code = ? AND type = 'register' AND used = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$verificationToken]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verification) {
        jsonFail(40007, '邮箱验证已过期或无效，请重新验证');
    }
    
    if (strcasecmp($verification['email'], $email) !== 0) {
        jsonFail(40008, '邮箱与验证信息不匹配');
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonFail(40004, '用户名已被使用');
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonFail(40005, '邮箱已被使用');
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $displayName = $username;
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, 'user', 'pending')");
    $stmt->execute([$username, $email, $displayName, $passwordHash]);
    
    $userId = (int) $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("UPDATE email_codes SET used = 1, used_at = NOW() WHERE id = ?");
    $stmt->execute([$verification['id']]);
    
    $pdo->commit();
    
    auditLog('user.register', $userId, $email, ['username' => $username], 'success');
    
    echo json_encode([
        'code' => 0,
        'message' => 'success',
        'data' => [
            'user_id' => $userId,
            'username' => $username
        ]
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Register failed: ' . $e->getMessage());
    jsonFail(50001, '注册失败，请稍后重试');
}
