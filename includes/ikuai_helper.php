<?php
/**
 * REXTIAN SSO - 爱快 (iKuai) 配置读取
 * 从 OAuth 应用表中获取爱快类型应用的配置
 */
require_once __DIR__ . '/db.php';

function getIkuaiConfig(): ?array {
    $pdo = getDb();
    if (!$pdo) return null;
    $stmt = $pdo->query("SELECT ikuai_api_url, ikuai_token FROM oauth_apps WHERE app_type = 'ikuai' AND ikuai_api_url IS NOT NULL AND ikuai_token IS NOT NULL AND status = 'live' LIMIT 1");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    return ['url' => $r['ikuai_api_url'], 'token' => $r['ikuai_token']];
}
