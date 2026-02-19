<?php
/**
 * REXTIAN SSO - RADIUS 客户端管理 API
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

if (!isAdmin()) {
    echo json_encode(['code' => 403, 'message' => '无权限']);
    exit;
}

function jsonResponse($code, $message, $data = null) {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

$pdo = getDb();
if (!$pdo) {
    jsonResponse(500, '服务暂时不可用');
}

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM radius_clients ORDER BY created_at DESC");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(0, 'success', $clients);
}

if ($method === 'POST') {
    if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonResponse(403, '请求无效');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim($input['name'] ?? '');
    $ipAddress = trim($input['ip_address'] ?? '');
    $secret = trim($input['secret'] ?? '');
    $description = trim($input['description'] ?? '');
    $status = trim($input['status'] ?? 'active');
    
    if (empty($name)) {
        jsonResponse(400, '名称不能为空');
    }
    if (empty($ipAddress)) {
        jsonResponse(400, 'IP地址不能为空');
    }
    if (empty($secret)) {
        jsonResponse(400, '共享密钥不能为空');
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO radius_clients (name, ip_address, secret, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $ipAddress, $secret, $description, $status]);
        $id = $pdo->lastInsertId();
        jsonResponse(0, '创建成功', ['id' => $id]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            jsonResponse(400, '该IP地址已存在');
        }
        jsonResponse(500, '创建失败');
    }
}

if ($method === 'PUT') {
    if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonResponse(403, '请求无效');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $ipAddress = trim($input['ip_address'] ?? '');
    $secret = trim($input['secret'] ?? '');
    $description = trim($input['description'] ?? '');
    $status = trim($input['status'] ?? 'active');
    
    if ($id <= 0) {
        jsonResponse(400, '无效的ID');
    }
    
    try {
        if (!empty($secret)) {
            $stmt = $pdo->prepare("UPDATE radius_clients SET name = ?, ip_address = ?, secret = ?, description = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $ipAddress, $secret, $description, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE radius_clients SET name = ?, ip_address = ?, description = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $ipAddress, $description, $status, $id]);
        }
        jsonResponse(0, '更新成功');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            jsonResponse(400, '该IP地址已存在');
        }
        jsonResponse(500, '更新失败');
    }
}

if ($method === 'DELETE') {
    if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonResponse(403, '请求无效');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($input['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(400, '无效的ID');
    }
    
    $stmt = $pdo->prepare("DELETE FROM radius_clients WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(0, '删除成功');
}

jsonResponse(405, 'Method Not Allowed');
