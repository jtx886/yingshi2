<?php
/**
 * 个人中心 - 我的页面
 */
require_once dirname(__FILE__) . '/../config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!is_logged_in()) {
    redirect(BASE_URL . '/login.php');
}

$pageTitle = '个人中心';
$currentPage = 'profile';
$user = current_user();
if (is_banned($user)) die('账号被封禁');

$userId = intval($user['id']);
$error = '';
$success = '';

// 处理头像上传/设置和密码修改
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 自定义头像（URL方式或上传）
    if ($action === 'update_avatar') {
        $avatar = isset($_POST['avatar_url']) ? trim($_POST['avatar_url']) : '';
        // 上传文件
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allow = array('jpg','jpeg','png','gif','webp');
            if (!in_array($ext, $allow)) {
                $error = '头像格式只支持 JPG/PNG/GIF/WEBP';
            } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
                $error = '头像大小不能超过5MB';
            } else {
                $fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $targetPath = UPLOAD_PATH . '/' . $fileName;
                if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $avatar = UPLOAD_URL . '/' . $fileName;
                } else {
                    $error = '头像上传失败，请检查目录权限';
                }
            }
        }
        if (!$error) {
            db()->update('users', array('avatar' => $avatar), 'id = ?', array($userId));
            $success = '头像更新成功！';
            $user = current_user(); // refresh
        }
    }

    // 修改密码
    if ($action === 'change_password') {
        $oldPwd = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        $newPwd = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPwd = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        if (empty($oldPwd) || empty($newPwd) || empty($confirmPwd)) {
            $error = '请填写所有密码字段';
        } elseif (!password_verify($oldPwd, $user['password']) && !($user['username'] === ADMIN_USERNAME && $oldPwd === ADMIN_PASSWORD)) {
            $error = '原密码错误';
        } elseif (strlen($newPwd) < 6) {
            $error = '新密码至少6位';
        } elseif ($newPwd !== $confirmPwd) {
            $error = '两次输入的新密码不一致';
        } else {
            db()->update('users', array('password' => password_hash($newPwd, PASSWORD_DEFAULT)), 'id = ?', array($userId));
            $success = '密码修改成功！';
        }
    }

    // 修改用户名
    if ($action === 'update_username') {
        $newName = isset($_POST['username']) ? trim($_POST['username']) : '';
        if (strlen($newName) < 2 || strlen($newName) > 20) {
            $error = '用户名长度2-20字符';
        } else {
            $exist = db()->fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", array($newName, $userId));
            if ($exist) {
                $error = '用户名已被使用';
            } else {
                db()->update('users', array('username' => $newName), 'id = ?', array($userId));
                $success = '用户名修改成功！';
                $user = current_user();
            }
        }
    }
}

// 统计数据
try {
    $favoriteCount = db()->fetchOne("SELECT COUNT(*) as cnt FROM favorites WHERE user_id = ?", array($userId));
    $historyCount = db()->fetchOne("SELECT COUNT(*) as cnt FROM watch_history WHERE user_id = ?", array($userId));
    $totalSeconds = db()->fetchOne("SELECT SUM(watch_seconds) as sec FROM watch_history WHERE user_id = ?", array($userId));
    $feedbackCount = db()->fetchOne("SELECT COUNT(*) as cnt FROM feedbacks WHERE user_id = ?", array($userId));
} catch (Exception $e) {
    $favoriteCount = array('cnt'=>0); $historyCount = array('cnt'=>0); $totalSeconds = array('sec'=>0); $feedbackCount = array('cnt'=>0);
}
$favCnt = intval($favoriteCount['cnt']);
$histCnt = intval($historyCount['cnt']);
$totalSec = intval($totalSeconds['sec']);
$feedCnt = intval($feedbackCount['cnt']);
$watchHours = floor($totalSec / 3600);
$watchMins = floor(($totalSec % 3600) / 60);

include ROOT_PATH . '/includes/header.php';

$defaultAvatars = array(
    BASE_URL . '/assets/images/avatar1.png',
    BASE_URL . '/assets/images/avatar2.png',
    BASE_URL . '/assets/images/avatar3.png',
    BASE_URL . '/assets/images/avatar4.png',
    BASE_URL . '/assets/images/avatar5.png',
    BASE_URL . '/assets/images/avatar6.png',
);

$avatarDisplay = !empty($user['avatar']) ? $user['avatar'] : '';
?>

<main class="container" style="padding:24px 20px 60px;">

    <div style="display:grid;grid-template-columns:320px 1fr;gap:24px;">
        <!-- 左侧卡片 -->
        <div>
            <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-xl);padding:30px;text-align:center;margin-bottom:24px;overflow:hidden;position:relative;">
                <div style="height:100px;background:var(--primary-gradient);margin:-30px -30px 40px;border-radius:var(--radius-xl) var(--radius-xl) 0 0;opacity:0.7;"></div>
                <div style="margin-top:-80px;">
                    <div style="width:120px;height:120px;border-radius:50%;margin:0 auto 16px;background:var(--bg-dark);border:4px solid var(--bg-card);display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:var(--shadow-primary);">
                        <?php if ($avatarDisplay): ?>
                        <img src="<?php echo e($avatarDisplay); ?>" alt="头像" id="avatar-preview" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                        <div style="font-size:48px;font-weight:800;color:white;background:var(--primary-gradient);width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                            <?php echo e(mb_substr($user['username'], 0, 1, 'UTF-8')); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h3 style="font-size:20px;font-weight:700;margin-bottom:4px;">
                        <?php echo e($user['username']); ?>
                        <?php if (is_admin()): ?>
                        <span class="admin-logo-text">开发者</span>
                        <?php endif; ?>
                    </h3>
                    <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;"><?php echo e($user['email']); ?></p>
                    <p style="font-size:12px;color:var(--text-muted);">
                        注册于：<?php echo e(date('Y-m-d', strtotime($user['created_at']))); ?>
                    </p>
                </div>
            </div>

            <!-- 菜单 -->
            <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:8px;">
                <a href="<?php echo BASE_URL; ?>/user/profile.php" class="user-dropdown-item active" style="background:var(--primary-bg);color:var(--primary-light);border-radius:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                    个人信息
                </a>
                <a href="<?php echo BASE_URL; ?>/user/favorites.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 5a5.5 5.5 0 0 1 9.5 7C19 16.5 12 21 12 21z"/></svg>
                    我的收藏 <span style="margin-left:auto;color:var(--primary-light);"><?php echo $favCnt; ?></span>
                </a>
                <a href="<?php echo BASE_URL; ?>/user/history.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    观看历史 <span style="margin-left:auto;color:var(--primary-light);"><?php echo $histCnt; ?></span>
                </a>
                <a href="<?php echo BASE_URL; ?>/feedback.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    我的反馈 <span style="margin-left:auto;color:var(--primary-light);"><?php echo $feedCnt; ?></span>
                </a>
                <?php if (is_admin()): ?>
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    管理后台 <span class="admin-badge">管理</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 右侧内容 -->
        <div>
            <?php if ($error): ?><div class="alert alert-error">⚠️ <?php echo e($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?php echo e($success); ?></div><?php endif; ?>

            <!-- 统计卡片 -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
                <div class="stat-card users" style="padding:20px;margin:0;">
                    <div class="stat-icon">❤️</div>
                    <div class="stat-label">收藏影片</div>
                    <div class="stat-value" style="font-size:26px;"><?php echo $favCnt; ?></div>
                </div>
                <div class="stat-card views" style="padding:20px;margin:0;">
                    <div class="stat-icon">▶️</div>
                    <div class="stat-label">观看记录</div>
                    <div class="stat-value" style="font-size:26px;"><?php echo $histCnt; ?></div>
                </div>
                <div class="stat-card favorites" style="padding:20px;margin:0;">
                    <div class="stat-icon">⏱️</div>
                    <div class="stat-label">观看时长</div>
                    <div class="stat-value" style="font-size:26px;"><?php echo $watchHours; ?><span style="font-size:14px;color:var(--text-muted);font-weight:500;">小时<?php echo $watchMins; ?>分</span></div>
                </div>
                <div class="stat-card feedback" style="padding:20px;margin:0;">
                    <div class="stat-icon">💬</div>
                    <div class="stat-label">反馈数量</div>
                    <div class="stat-value" style="font-size:26px;"><?php echo $feedCnt; ?></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <!-- 头像设置 -->
                <div class="admin-form">
                    <h3 style="font-size:18px;font-weight:700;margin-bottom:20px;">🖼 自定义头像</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_avatar">

                        <div class="form-group">
                            <label class="form-label">上传头像图片</label>
                            <input type="file" name="avatar_file" accept="image/*" class="form-input" onchange="previewFile(this)">
                            <span class="form-help">支持 JPG/PNG/GIF/WEBP，大小 ≤ 5MB</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">或填写头像图片URL</label>
                            <input type="text" name="avatar_url" class="form-input" placeholder="https://example.com/avatar.png" value="<?php echo e($user['avatar'] ? $user['avatar'] : ''); ?>" onchange="previewUrl(this.value)">
                        </div>

                        <div class="form-group">
                            <label class="form-label">选择默认头像</label>
                            <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;">
                                <?php for ($i = 0; $i < 6; $i++):
                                    $color = array('#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899')[$i];
                                    $letter = mb_substr($user['username'], 0, 1, 'UTF-8');
                                ?>
                                <div onclick="selectDefaultAvatar(this, '<?php echo $color; ?>', '<?php echo e($letter); ?>')"
                                     style="width:100%;aspect-ratio:1/1;border-radius:50%;background:<?php echo $color; ?>;display:flex;align-items:center;justify-content:center;color:white;font-size:22px;font-weight:700;cursor:pointer;border:3px solid transparent;transition:var(--transition);"
                                     title="使用此头像">
                                    <?php echo $letter; ?>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-full">💾 保存头像</button>
                    </form>
                </div>

                <!-- 账号信息 -->
                <div style="display:flex;flex-direction:column;gap:24px;">
                    <div class="admin-form">
                        <h3 style="font-size:18px;font-weight:700;margin-bottom:20px;">👤 修改用户名</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_username">
                            <div class="form-group">
                                <label class="form-label">用户名</label>
                                <input type="text" name="username" class="form-input" value="<?php echo e($user['username']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-full">修改用户名</button>
                        </form>
                    </div>

                    <div class="admin-form">
                        <h3 style="font-size:18px;font-weight:700;margin-bottom:20px;">🔐 修改密码</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label class="form-label">原密码</label>
                                <input type="password" name="old_password" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">新密码（≥6位）</label>
                                <input type="password" name="new_password" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">确认新密码</label>
                                <input type="password" name="confirm_password" class="form-input" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-full">修改密码</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<script>
function previewFile(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('avatar-preview');
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function previewUrl(url) {
    if (!url) return;
    var img = document.getElementById('avatar-preview');
    if (img) img.src = url;
}
function selectDefaultAvatar(el, color, letter) {
    // 生成SVG data URL作为头像
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" rx="100" fill="' + color + '"/><text x="100" y="100" font-size="90" font-family="sans-serif" fill="white" font-weight="bold" text-anchor="middle" dominant-baseline="central">' + letter + '</text></svg>';
    var dataUrl = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
    var img = document.getElementById('avatar-preview');
    if (img) img.src = dataUrl;
    document.querySelector('input[name="avatar_url"]').value = dataUrl;
    // 选中高亮
    document.querySelectorAll('[onclick^="selectDefaultAvatar"]').forEach(function(n) { n.style.borderColor = 'transparent'; });
    el.style.borderColor = 'var(--primary-color)';
}
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
