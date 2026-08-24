<?php
/**
 * 登出处理
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

// 清除session
$_SESSION = array();
session_destroy();

// 清除记住登录cookie
if (isset($_COOKIE['jay_remember'])) {
    try {
        $token = $_COOKIE['jay_remember'];
        db()->delete('remember_tokens', 'token = ?', array($token));
    } catch (Exception $e) {}
    setcookie('jay_remember', '', time() - 3600, '/');
}

redirect(BASE_URL . '/index.php');
