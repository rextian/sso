<?php
/**
 * REXTIAN SSO - 入口页
 * 已登录跳转仪表盘，未登录跳转登录页
 */
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'user';
    header('Location: ' . ($role === 'admin' ? 'admin_dashboard.php' : 'portal.php'));
    exit;
}
header('Location: login.php');
exit;
