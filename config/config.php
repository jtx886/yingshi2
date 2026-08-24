<?php
/**
 * Jay影视 - 主配置文件
 * 兼容所有PHP版本
 */

// 错误报告设置（部署完成后把 0/1 改成 0/0，即 display_errors=0 隐藏错误）
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1); // 部署后建议改 0，错误保存在服务器日志里
ini_set('log_errors', 1);

// ====== 超全局变量兼容初始化（CLI/低版本PHP/特殊服务器环境缺失时兜底） ======
// $_SERVER 初始化
if (!isset($_SERVER) || !is_array($_SERVER)) $_SERVER = array();
if (!isset($_SERVER['REQUEST_METHOD']))  $_SERVER['REQUEST_METHOD']  = 'GET';
if (!isset($_SERVER['REQUEST_URI']))     $_SERVER['REQUEST_URI']     = '/';
if (!isset($_SERVER['PHP_SELF']))        $_SERVER['PHP_SELF']        = '/index.php';
if (!isset($_SERVER['HTTP_HOST']))       $_SERVER['HTTP_HOST']       = 'localhost';
if (!isset($_SERVER['SERVER_NAME']))     $_SERVER['SERVER_NAME']     = 'localhost';
if (!isset($_SERVER['SERVER_PORT']))     $_SERVER['SERVER_PORT']     = 80;
if (!isset($_SERVER['REMOTE_ADDR']))     $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
if (!isset($_SERVER['HTTPS']))           $_SERVER['HTTPS']           = '';
if (!isset($_SERVER['SCRIPT_NAME']))     $_SERVER['SCRIPT_NAME']     = '/index.php';
if (!isset($_SERVER['HTTP_USER_AGENT'])) $_SERVER['HTTP_USER_AGENT'] = '';
if (!isset($_SERVER['HTTP_REFERER']))    $_SERVER['HTTP_REFERER']    = '';
if (!isset($_SERVER['QUERY_STRING']))    $_SERVER['QUERY_STRING']    = '';
// 其他超全局（$_SESSION 特殊：必须在 session_start() 后才会注册为全局，所以这里只初始化其他的，$_SESSION 留到 session_start() 之后再处理）
if (!isset($_GET)    || !is_array($_GET))    $_GET    = array();
if (!isset($_POST)   || !is_array($_POST))   $_POST   = array();
if (!isset($_COOKIE) || !is_array($_COOKIE)) $_COOKIE = array();
if (!isset($_REQUEST)|| !is_array($_REQUEST))$_REQUEST= array_merge($_GET, $_POST, $_COOKIE);
if (!isset($_FILES)  || !is_array($_FILES))  $_FILES  = array();
if (!isset($_ENV)    || !is_array($_ENV))    $_ENV    = array();

// ====== 全局错误/异常兜底：避免白屏和 500 错误 ======
function jay_error_handler($errno, $errstr, $errfile, $errline) {
    // 忽略被@抑制的错误
    if (error_reporting() === 0) return false;
    // 不处理不在error_reporting范围内的错误（如E_NOTICE）
    if (!(error_reporting() & $errno)) return false;
    $errTypeMap = array(
        E_ERROR             => 'Fatal Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Strict',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
    );
    $type = isset($errTypeMap[$errno]) ? $errTypeMap[$errno] : 'Error';
    $logMsg = "[{$type}] {$errstr} in {$errfile}:{$errline}";
    @error_log($logMsg);
    // Fatal 级别显示友好页
    if (ini_get('display_errors')) {
        echo '<div style="padding:16px;background:#fff3cd;border:1px solid #ffc107;color:#856404;margin:8px;border-radius:6px;font-family:sans-serif;">';
        echo '<b>' . htmlspecialchars($type) . ':</b> ' . htmlspecialchars($errstr) . '<br>';
        echo '<small>位置: ' . htmlspecialchars(basename($errfile)) . ' 第 ' . (int)$errline . ' 行</small>';
        echo '</div>';
    }
    return true;
}
function jay_exception_handler($exception) {
    $msg = $exception->getMessage();
    $file = basename($exception->getFile());
    $line = $exception->getLine();
    @error_log("[Uncaught Exception] {$msg} in {$file}:{$line}");
    // 尝试输出友好页面
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统繁忙 - Jay影视</title>';
    echo '<style>body{font-family:sans-serif;background:#0f0f1a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}';
    echo '.card{max-width:520px;width:100%;background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.3);border-radius:16px;padding:28px;}';
    echo 'h1{margin:0 0 12px;font-size:20px;}code{background:rgba(0,0,0,0.35);padding:10px 12px;border-radius:6px;font-size:12px;display:block;margin:14px 0;white-space:pre-wrap;word-break:break-all;}';
    echo '.tag{display:inline-block;padding:4px 12px;background:#f59e0b;color:#000;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:14px;}';
    echo '</style></head><body><div class="card">';
    echo '<span class="tag">⚠️ 运行时提示</span>';
    echo '<h1>系统繁忙，请稍后再试</h1>';
    echo '<p style="color:rgba(255,255,255,0.7);line-height:1.8;">如果您是网站管理员，请检查服务器错误日志，或在 config/config.php 中将 display_errors 设为 1 查看详细报错。</p>';
    if (ini_get('display_errors')) {
        echo '<code>' . htmlspecialchars(get_class($exception) . ': ' . $msg . "\n文件: {$file} 行 {$line}") . '</code>';
    }
    echo '<p style="margin-top:20px;font-size:12px;color:rgba(255,255,255,0.45);">Jay影视 · 兼容安全模式</p>';
    echo '</div></body></html>';
    exit(1);
}
function jay_shutdown_handler() {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
        // Fatal error 已经输出了部分内容，尽量兜底显示信息
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $msg = $err['message'];
        $file = basename($err['file']);
        $line = $err['line'];
        @error_log("[Fatal] {$msg} in {$file}:{$line}");
        echo '<div style="padding:20px;background:#1a0a0a;border:2px solid #ef4444;color:#fff;margin:10px;border-radius:10px;font-family:sans-serif;">';
        echo '<b style="color:#ef4444;">❌ 致命错误</b><br>';
        if (ini_get('display_errors')) {
            echo htmlspecialchars($msg) . '<br><small>文件: ' . htmlspecialchars($file) . ' 第 ' . (int)$line . ' 行</small>';
        } else {
            echo '网站发生了意外错误，请稍后重试。管理员可查看服务器错误日志。';
        }
        echo '</div>';
    }
}
set_error_handler('jay_error_handler');
set_exception_handler('jay_exception_handler');
register_shutdown_function('jay_shutdown_handler');

// PHP 版本兼容常量
if (!defined('JSON_UNESCAPED_UNICODE')) define('JSON_UNESCAPED_UNICODE', 256);
if (!defined('JSON_UNESCAPED_SLASHES')) define('JSON_UNESCAPED_SLASHES', 64);
if (!defined('ENT_SUBSTITUTE')) define('ENT_SUBSTITUTE', 8);
if (!defined('PASSWORD_BCRYPT')) define('PASSWORD_BCRYPT', 1);

// 兼容 PHP 5.x：如果没有 password_hash 则自定义
if (!function_exists('password_hash')) {
    function password_hash($password, $algo = null, $options = null) {
        // 降级：使用 salted crypt 哈希（Blowfish）
        $salt = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ./'), 0, 22);
        return crypt($password, '$2a$10$' . $salt);
    }
    function password_verify($password, $hash) {
        if (strpos($hash, '$2a$') === 0 || strpos($hash, '$2y$') === 0 || strpos($hash, '$2b$') === 0) {
            return crypt($password, $hash) === $hash;
        }
        // 兼容MD5/SHA1（旧）
        return md5($password) === $hash || sha1($password) === $hash || $hash === md5($password);
    }
    function password_needs_rehash($hash, $algo = null, $options = null) { return false; }
}

// 兼容 PHP < 7.0：random_bytes / random_int
if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        $length = (int)$length;
        if ($length <= 0) return '';
        // 方式1：mcrypt (PHP 5.3+)
        if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
            $r = @mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
            if ($r !== false && strlen($r) === $length) return $r;
        }
        // 方式2：openssl (PHP 5.3+)
        if (function_exists('openssl_random_pseudo_bytes')) {
            $r = @openssl_random_pseudo_bytes($length, $strong);
            if ($r !== false && strlen($r) === $length) return $r;
        }
        // 方式3：/dev/urandom (Linux)
        if (file_exists('/dev/urandom') && is_readable('/dev/urandom')) {
            $fp = @fopen('/dev/urandom', 'rb');
            if ($fp) {
                $r = @fread($fp, $length);
                @fclose($fp);
                if ($r !== false && strlen($r) === $length) return $r;
            }
        }
        // 方式4：降级 rand() 兜底（够用，安全级别稍低）
        $r = '';
        for ($i = 0; $i < $length; $i++) {
            $r .= chr(mt_rand(0, 255));
        }
        return $r;
    }
}
if (!function_exists('random_int')) {
    function random_int($min, $max) {
        $min = (int)$min;
        $max = (int)$max;
        if ($min > $max) {
            $tmp = $min; $min = $max; $max = $tmp;
        }
        if (function_exists('mt_rand')) {
            return mt_rand($min, $max);
        }
        return rand($min, $max);
    }
}

// 兼容 PHP < 5.6：hash_equals
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        $a = (string)$known_string;
        $b = (string)$user_string;
        $lenA = strlen($a);
        $lenB = strlen($b);
        if ($lenA !== $lenB) {
            // 长度不同仍然继续比较相同长度部分，避免长度时序攻击
            $len = min($lenA, $lenB);
            $result = 1;
        } else {
            $len = $lenA;
            $result = 0;
        }
        for ($i = 0; $i < $len; $i++) {
            $result |= (ord($a[$i]) ^ ord($b[$i]));
        }
        return $result === 0;
    }
}

// 兼容 PHP < 5.5：array_column
if (!function_exists('array_column')) {
    function array_column($input, $columnKey, $indexKey = null) {
        $result = array();
        if (!is_array($input)) return $result;
        foreach ($input as $row) {
            if (!is_array($row)) continue;
            $value = null;
            if ($columnKey === null) {
                $value = $row;
            } elseif (array_key_exists($columnKey, $row)) {
                $value = $row[$columnKey];
            }
            if ($indexKey !== null && array_key_exists($indexKey, $row)) {
                $result[$row[$indexKey]] = $value;
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }
}

// 兼容 PHP < 5.4：short array tag 已经是语法层面兼容，这里处理 Session 函数
if (!function_exists('session_status')) {
    function session_status() {
        if (function_exists('\session_status')) return \session_status();
        if (session_id() === '') return 1; // PHP_SESSION_NONE
        return 2; // PHP_SESSION_ACTIVE
    }
    define('PHP_SESSION_NONE', 1);
    define('PHP_SESSION_ACTIVE', 2);
    define('PHP_SESSION_DISABLED', 0);
}

// 时区设置
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Shanghai');
}

// 网站根路径
define('ROOT_PATH', dirname(dirname(__FILE__)));

// 网站URL（自动检测，兼容CLI和各种服务器环境）
$jay_protocol = 'http://';
if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $jay_protocol = 'https://';
} elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
    $jay_protocol = 'https://';
}
$jay_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
$jay_script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$jay_path = str_replace(basename($jay_script), '', $jay_script);
define('BASE_URL', rtrim($jay_protocol . $jay_host . $jay_path, '/'));

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
// $_SESSION 必须在 session_start() 之后才可用，这里补兜底
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = array();
}

// 自动加载函数（兼容 PHP 5.2：不使用匿名函数）
function jay_autoload($class) {
    $file = ROOT_PATH . '/includes/' . strtolower($class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}
spl_autoload_register('jay_autoload');
