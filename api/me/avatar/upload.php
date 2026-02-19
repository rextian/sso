<?php
/**
 * REXTIAN SSO - POST /api/me/avatar/upload.php
 * 上传头像图片
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/includes/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 40500, 'message' => 'Method Not Allowed', 'data' => null]);
    exit;
}

requireLogin();

function jsonFail($code, $msg) {
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => null]);
    exit;
}

if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonFail(40301, '请求无效，请刷新页面重试');
}

$userId = (int) $_SESSION['user_id'];

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['avatar']['error'] ?? -1;
    $msg = $err === UPLOAD_ERR_NO_FILE ? '请选择要上传的图片' : '上传失败，请重试';
    jsonFail(40001, $msg);
}

$file = $_FILES['avatar'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($ext, $allowed)) {
    jsonFail(40002, '仅支持 JPG、PNG、GIF、WEBP 格式');
}

if ($file['size'] > 2 * 1024 * 1024) {
    jsonFail(40003, '图片大小不能超过 2MB');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowedMime)) {
    jsonFail(40004, '文件类型无效');
}

$baseDir = dirname(__DIR__, 3) . '/uploads/avatars';
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        jsonFail(50001, '无法创建上传目录');
    }
}

$hash = bin2hex(random_bytes(8));
$filename = $userId . '_' . $hash . '.' . $ext;
$filepath = $baseDir . '/' . $filename;
$relativePath = 'uploads/avatars/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    jsonFail(50002, '保存文件失败');
}

$pdo = getDb();
if (!$pdo) {
    @unlink($filepath);
    jsonFail(50000, '服务暂时不可用');
}

// 删除旧头像文件（仅本地上传的）
$stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$old = $stmt->fetchColumn();
if ($old && strpos($old, 'uploads/avatars/') === 0) {
    $oldPath = dirname(__DIR__, 3) . '/' . $old;
    if (file_exists($oldPath)) @unlink($oldPath);
}

$pdo->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?")->execute([$relativePath, $userId]);
auditLog('user.avatar.upload', $userId, null, [], 'success');

echo json_encode([
    'code' => 0,
    'message' => 'success',
    'data' => ['avatar' => $relativePath],
]);
