<?php
/**
 * Jay影视 - 主配置文件
 * 兼容所有PHP版本
 */

// 错误报告设置（生产环境可关闭）
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 网站根路径
define('ROOT_PATH', dirname(dirname(__FILE__)));

// 网站URL（自动检测）
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
define('BASE_URL', rtrim($protocol . $domainName, '/'));

// 数据库配置（用户根据实际情况修改）
define('DB_HOST', 'localhost');
define('DB_NAME', 'jay_movie');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Cookie/Session 配置
define('SESSION_LIFETIME', 86400 * 7); // 7天

// 上传配置
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// SMTP邮件配置
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

// TMDB API配置
define('TMDB_API_KEY', ''); // 用户需要自己填写
define('TMDB_API_URL', 'https://api.themoviedb.org/3');
define('TMDB_IMAGE_URL', 'https://image.tmdb.org/t/p');

// 播放源默认配置
define('DEFAULT_PARSER_URL', 'https://svip.ffzyplay.com/?url=');
define('DEFAULT_PLAY_API', 'https://api.yyzy-tv.vip/inc/apijson.php');

// 验证码有效期（秒）
define('EMAIL_CODE_EXPIRE', 600); // 10分钟

// 管理员账号（数据库中也有，这里是备用）
define('ADMIN_USERNAME', '杰同学');
define('ADMIN_PASSWORD', '101113');

// 页面每页显示数量
define('PER_PAGE', 20);

// 安全配置
define('CSRF_TOKEN_NAME', 'jay_csrf_token');

// 启动Session
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_start();
}

// 自动加载函数
spl_autoload_register(function ($class) {
    $file = ROOT_PATH . '/includes/' . strtolower($class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
