<?php
/**
 * REXTIAN SSO - 认证辅助函数
 * require_once 此文件前需已加载 config（含 session_start）
 */

/**
 * 要求已登录，未登录则重定向到登录页
 * @param string $redirect 登录后回跳地址
 */
function requireLogin($redirect = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        $url = 'login.php';
        if ($redirect) {
            $url .= '?redirect=' . urlencode($redirect);
        }
        header('Location: ' . $url);
        exit;
    }
}

/**
 * 要求已登录且为管理员，否则重定向
 * @param string $redirect 登录后回跳地址
 */
function requireAdmin($redirect = '') {
    requireLogin($redirect);
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: user_profile.php');
        exit;
    }
}
