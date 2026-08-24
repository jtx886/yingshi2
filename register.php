<?php
/**
 * 注册页面
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

$pageTitle = '注册';

// 已登录跳首页
if (is_logged_in()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
$success = '';
$formData = array(
    'email' => '',
    'username' => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';

    $formData['email'] = $email;
    $formData['username'] = $username;

    // 发送验证码
    if ($action === 'send_code') {
        header('Content-Type: application/json');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('请输入有效的邮箱地址');
        }
        // 检查邮箱是否已注册
        $exist = db()->fetchOne("SELECT id FROM users WHERE email = ?", array($email));
        if ($exist) {
            json_error('该邮箱已被注册');
        }
        // 限制频繁发送
        $recent = db()->fetchOne("SELECT created_at FROM email_codes WHERE email = ? ORDER BY id DESC LIMIT 1", array($email));
        if ($recent && (time() - strtotime($recent['created_at'])) < 60) {
            json_error('验证码发送太频繁，请1分钟后再试');
        }
        // 生成并发送验证码
        $code = generate_email_code();
        $expireAt = date('Y-m-d H:i:s', time() + EMAIL_CODE_EXPIRE);
        db()->insert('email_codes', array(
            'email' => $email,
            'code' => $code,
            'type' => 'register',
            'expire_at' => $expireAt
        ));
        $sent = send_email_code($email, $code, 'register');
        if ($sent) {
            json_success(null, '验证码已发送到您的邮箱，请注意查收');
        } else {
            json_error('验证码发送失败，请稍后重试');
        }
    }

    // 注册提交
    if (empty($email) || empty($username) || empty($password) || empty($confirmPassword) || empty($code)) {
        $error = '请填写所有必填字段';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif (strlen($username) < 2 || strlen($username) > 20) {
        $error = '用户名长度需在2-20个字符之间';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } else {
        // 检查用户名和邮箱
        $existUser = db()->fetchOne("SELECT id FROM users WHERE username = ?", array($username));
        if ($existUser) {
            $error = '该用户名已被使用';
        } else {
            $existEmail = db()->fetchOne("SELECT id FROM users WHERE email = ?", array($email));
            if ($existEmail) {
                $error = '该邮箱已被注册';
            } else {
                // 验证验证码
                $codeRecord = db()->fetchOne("SELECT * FROM email_codes WHERE email = ? AND type = 'register' ORDER BY id DESC LIMIT 1", array($email));
                if (!$codeRecord || $codeRecord['code'] !== $code || strtotime($codeRecord['expire_at']) < time()) {
                    $error = '验证码无效或已过期';
                } else {
                    // 创建用户
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $userId = db()->insert('users', array(
                        'username' => $username,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'status' => 1
                    ));
                    if ($userId) {
                        // 标记验证码已使用
                        db()->update('email_codes', array('expire_at' => date('Y-m-d H:i:s', time() - 100)), 'id = ?', array($codeRecord['id']));
                        // 自动登录
                        $_SESSION['user_id'] = $userId;
                        // 提示注册成功
                        $success = '注册成功！正在跳转到首页...';
                        echo '<script>setTimeout(function(){window.location.href="' . BASE_URL . '/index.php";},1500);</script>';
                    } else {
                        $error = '注册失败，请稍后重试';
                    }
                }
            }
        }
    }
}
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
        function adjustColor($color, $percent) {
            $color = str_replace('#', '', $color);
            $r = hexdec(substr($color,0,2)); $g = hexdec(substr($color,2,2)); $b = hexdec(substr($color,4,2));
            $r = max(0,min(255,$r+round(255*$percent/100)));
            $g = max(0,min(255,$g+round(255*$percent/100)));
            $b = max(0,min(255,$b+round(255*$percent/100)));
            return '#'.sprintf('%02x%02x%02x',$r,$g,$b);
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
        <p class="auth-subtitle">创建账号，畅享高清影视内容</p>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo e($success); ?></div>
        <?php endif; ?>

        <form method="POST" id="register-form">
            <div class="form-group">
                <label class="form-label">邮箱</label>
                <input type="email" name="email" class="form-input" placeholder="请输入您的邮箱" value="<?php echo e($formData['email']); ?>" required>
                <span class="form-help">将用于接收验证码和找回密码</span>
            </div>

            <div class="form-group">
                <label class="form-label">用户名</label>
                <input type="text" name="username" class="form-input" placeholder="2-20个字符" value="<?php echo e($formData['username']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">密码</label>
                <input type="password" name="password" class="form-input" placeholder="至少6位字符" required>
            </div>

            <div class="form-group">
                <label class="form-label">确认密码</label>
                <input type="password" name="confirm_password" class="form-input" placeholder="再次输入密码" required>
            </div>

            <div class="form-group">
                <label class="form-label">邮箱验证码</label>
                <div class="form-input-group">
                    <input type="text" name="code" class="form-input" placeholder="请输入6位验证码" maxlength="6" required>
                    <button type="button" class="btn btn-outline" id="send-code-btn" onclick="sendCode()">获取验证码</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">立即注册</button>
        </form>

        <div class="auth-footer">
            已有账号？<a href="<?php echo BASE_URL; ?>/login.php">立即登录</a>
        </div>
    </div>
</div>

<script>
var countdown = 0;
var timer = null;

function sendCode() {
    var emailInput = document.querySelector('input[name="email"]');
    var email = emailInput.value.trim();
    if (!email) {
        alert('请先输入邮箱地址');
        emailInput.focus();
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('请输入有效的邮箱地址');
        emailInput.focus();
        return;
    }
    if (countdown > 0) return;

    var btn = document.getElementById('send-code-btn');
    var formData = new FormData();
    formData.append('action', 'send_code');
    formData.append('email', email);

    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if (res.code === 200) {
            showToast(res.message, 'success');
            countdown = 60;
            btn.disabled = true;
            timer = setInterval(function() {
                countdown--;
                btn.textContent = countdown + '秒后重试';
                if (countdown <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.textContent = '获取验证码';
                }
            }, 1000);
        } else {
            showToast(res.message, 'error');
        }
    }).catch(() => showToast('请求失败，请稍后重试', 'error'));
}

function showToast(msg, type) {
    var existing = document.querySelector('.toast-container');
    if (!existing) {
        existing = document.createElement('div');
        existing.className = 'toast-container';
        document.body.appendChild(existing);
    }
    var toast = document.createElement('div');
    toast.className = 'toast';
    var colors = {success: '#10b981', error: '#ef4444', info: '#3b82f6'};
    toast.style.borderLeft = '4px solid ' + (colors[type] || colors.info);
    toast.textContent = msg;
    existing.appendChild(toast);
    setTimeout(function() { toast.style.opacity = '0'; toast.style.transform = 'translateX(120%)'; setTimeout(function(){toast.remove();}, 300); }, 3000);
}
</script>
</body>
</html>
