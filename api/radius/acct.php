<?php
/**
 * REXTIAN SSO - RADIUS 记账 API
 * 
 * 使用方法：
 * POST /api/radius/acct.php
 * Content-Type: application/json
 * {
 *   "acct_session_id": "session-id",
 *   "acct_status_type": "Start|Interim-Update|Stop",
 *   "acct_session_time": 3600,
 *   "acct_input_octets": 1024000,
 *   "acct_output_octets": 2048000,
 *   "acct_input_packets": 1000,
 *   "acct_output_packets": 2000,
 *   "acct_terminate_cause": "User Request",
 *   "nas_ip": "192.168.1.1",
 *   "framed_ip_address": "192.168.1.100"
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/settings_helper.php';

function jsonResponse($code, $message, $data = null) {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, 'Method Not Allowed');
}

$enabled = getSetting('radius_enabled');
if (!$enabled) {
    jsonResponse(403, 'RADIUS记账未启用');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$acctSessionId = trim($input['acct_session_id'] ?? '');
$acctStatusType = trim($input['acct_status_type'] ?? '');
$acctSessionTime = (int) ($input['acct_session_time'] ?? 0);
$acctInputOctets = (int) ($input['acct_input_octets'] ?? 0);
$acctOutputOctets = (int) ($input['acct_output_octets'] ?? 0);
$acctInputPackets = (int) ($input['acct_input_packets'] ?? 0);
$acctOutputPackets = (int) ($input['acct_output_packets'] ?? 0);
$acctTerminateCause = trim($input['acct_terminate_cause'] ?? '');
$nasIp = trim($input['nas_ip'] ?? '');
$framedIpAddress = trim($input['framed_ip_address'] ?? '');

if (empty($acctSessionId)) {
    jsonResponse(400, '会话ID不能为空');
}

if (!in_array($acctStatusType, ['Start', 'Interim-Update', 'Stop'])) {
    jsonResponse(400, '无效的记账状态类型');
}

if (empty($nasIp)) {
    jsonResponse(400, 'NAS IP不能为空');
}

$pdo = getDb();
if (!$pdo) {
    jsonResponse(500, '服务暂时不可用');
}

$stmt = $pdo->prepare("SELECT id, secret, status FROM radius_clients WHERE ip_address = ? LIMIT 1");
$stmt->execute([$nasIp]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    jsonResponse(403, '未授权的NAS客户端');
}

if ($client['status'] !== 'active') {
    jsonResponse(403, 'NAS客户端已禁用');
}

$stmt = $pdo->prepare("SELECT id, user_id, username, nas_ip_address, acct_status_type FROM radius_sessions WHERE acct_session_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$acctSessionId]);
$existingSession = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingSession && $acctStatusType !== 'Start') {
    jsonResponse(404, '会话不存在');
}

try {
    $pdo->beginTransaction();
    
    if ($acctStatusType === 'Start' && !$existingSession) {
        jsonResponse(400, 'Start记账应通过auth接口处理');
    }
    
    $updateData = [
        'acct_status_type' => $acctStatusType,
        'acct_session_time' => $acctSessionTime,
        'acct_input_octets' => $acctInputOctets,
        'acct_output_octets' => $acctOutputOctets,
        'acct_input_packets' => $acctInputPackets,
        'acct_output_packets' => $acctOutputPackets,
        'session_update' => date('Y-m-d H:i:s')
    ];
    
    if ($framedIpAddress) {
        $updateData['framed_ip_address'] = $framedIpAddress;
    }
    
    if ($acctTerminateCause) {
        $updateData['acct_terminate_cause'] = $acctTerminateCause;
    }
    
    if ($acctStatusType === 'Stop') {
        $updateData['session_stop'] = date('Y-m-d H:i:s');
    }
    
    $setClauses = [];
    $params = [];
    foreach ($updateData as $key => $value) {
        $setClauses[] = "`{$key}` = ?";
        $params[] = $value;
    }
    $params[] = $existingSession['id'];
    
    $sql = "UPDATE radius_sessions SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $pdo->commit();
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('RADIUS accounting update failed: ' . $e->getMessage());
    jsonResponse(500, '记账更新失败');
}

jsonResponse(0, 'Accounting-Response', [
    'session' => [
        'session_id' => $acctSessionId,
        'status' => $acctStatusType
    ]
]);
