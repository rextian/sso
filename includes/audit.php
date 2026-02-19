<?php
/**
 * REXTIAN SSO - 审计日志辅助函数
 */

/**
 * 写入审计日志
 * @param string $event 事件类型，如 auth.login.success, auth.login.failed
 * @param int|null $userId 用户 ID
 * @param string|null $userEmail 用户邮箱（用于未登录场景）
 * @param array|null $payload 附加数据
 * @param string $status success|failed
 */
function auditLog($event, $userId = null, $userEmail = null, $payload = null, $status = 'success') {
    $pdo = getDb();
    if (!$pdo) return;

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    $traceId = bin2hex(random_bytes(8));

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (trace_id, event, user_id, user_email, ip, payload, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $traceId,
            $event,
            $userId,
            $userEmail ?: null,
            $ip ?: null,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            $status,
        ]);
    } catch (PDOException $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}
