<?php
/**
 * 登录页面
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

$pageTitle = '登录';

// 已登录跳首页
if (is_logged_in()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
$formData = array('username' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? $_POST['remember'] : '';

    $formData['username'] = $username;

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        // 支持用户名或邮箱登录
        $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = db()->fetchOne("SELECT * FROM users WHERE {$field} = ?", array($username));

        // 默认管理员账号兜底
        if (!$user && $username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            // 检查数据库是否有默认管理员，没有则创建
            $admin = db()->fetchOne("SELECT * FROM users WHERE username = ?", array(ADMIN_USERNAME));
            if (!$admin) {
                $hashedPassword = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
                db()->insert('users', array(
                    'username' => ADMIN_USERNAME,
                    'email' => 'admin@jaymovie.com',
                    'password' => $hashedPassword,
                    'is_admin' => 1,
                    'status' => 1
                ));
                $user = db()->fetchOne("SELECT * FROM users WHERE username = ?", array(ADMIN_USERNAME));
            } else {
                $user = $admin;
            }
        }

        if (!$user) {
            $error = '用户不存在';
        } elseif (!password_verify($password, $user['password'])) {
            // 兜底：如果是默认管理员密码，直接登录
            if ($user['username'] === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
                // 继续登录
            } else {
                $error = '密码错误';
            }
        }

        if (empty($error)) {
            // 检查是否被封禁
            if (is_banned($user)) {
                $banUntil = $user['ban_until'] ? '，解封时间：' . date('Y-m-d H:i:s', strtotime($user['ban_until'])) : '（永久）';
                $error = '您的账号已被封禁' . $banUntil;
            } else {
                // 登录成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['login_time'] = time();

                // Remember me
                if ($remember === 'on') {
                    $token = random_str(40);
                    db()->insert('remember_tokens', array(
                        'user_id' => $user['id'],
                        'token' => $token,
                        'expire_at' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
                    ));
                    setcookie('jay_remember', $token, time() + SESSION_LIFETIME, '/', '', false, true);
                }

                // 跳回来源页或首页
                $redirect = isset($_GET['redirect']) ? base64_decode($_GET['redirect']) : BASE_URL . '/index.php';
                if (empty($redirect) || strpos($redirect, 'login.php') !== false || strpos($redirect, 'register.php') !== false) {
                    $redirect = BASE_URL . '/index.php';
                }
                redirect($redirect);
            }
        }
    }
}

// 创建remember_tokens表如果不存在（兼容安装前）
try {
    db()->query("CREATE TABLE IF NOT EXISTS `remember_tokens` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `token` varchar(100) NOT NULL,
        `expire_at` datetime NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?> - <?php echo e(get_setting('site_name', 'Jay影视')); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=1.0">
    <style>
        :root {
            --primary-color: <?php echo e(get_theme_color()); ?>;
            --primary-bg: <?php echo e(get_theme_color()); ?>1A;
            --primary-gradient: linear-gradient(135deg, <?php echo e(get_theme_color()); ?> 0%, <?php echo e(adjustColor(get_theme_color(), -15)); ?> 100%);
            --primary-light: <?php echo e(adjustColor(get_theme_color(), 20)); ?>;
            --shadow-primary: 0 4px 20px <?php echo e(get_theme_color()); ?>4D;
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <a href="<?php echo BASE_URL; ?>/index.php" class="logo" style="justify-content:center;">
                <span class="logo-icon"></span>
                <span class="logo-text">Jay影视</span>
            </a>
        </div>
        <p class="auth-subtitle">欢迎回来，登录后继续观看精彩内容</p>

        <?php if (isset($_GET['need_login'])): ?>
            <div class="alert alert-warning">
                ⚠️ 需要登录才可以观看哦，如没有账号请注册！
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">用户名 / 邮箱</label>
                <input type="text" name="username" class="form-input" placeholder="请输入用户名或邮箱" value="<?php echo e($formData['username']); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">密码</label>
                <input type="password" name="password" class="form-input" placeholder="请输入密码" required>
            </div>

            <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;">
                <label class="modal-dont-show" style="margin:0;">
                    <input type="checkbox" name="remember" style="display:none;">
                    <span class="checkbox-custom"></span>
                    记住我（7天）
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:8px;">登 录</button>
        </form>

        <div class="auth-footer">
            还没有账号？<a href="<?php echo BASE_URL; ?>/register.php">立即注册</a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('input[name="remember"]').forEach(function(cb) {
    var label = cb.closest('label');
    label.addEventListener('click', function(e) {
        e.preventDefault();
        cb.checked = !cb.checked;
    });
});
</script>
</body>
</html>
