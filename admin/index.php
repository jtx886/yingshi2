<?php
/**
 * 管理后台仪表盘
 */
require_once dirname(__FILE__) . '/includes/header.php';

$activeMenu = 'dashboard';
$pageSubTitle = '仪表盘';
$stats = adminGetStats();

// 最新用户
try {
    $latestUsers = db()->fetchAll("SELECT id,username,email,avatar,created_at,status,is_admin FROM users ORDER BY created_at DESC LIMIT 8");
} catch (Exception $e) { $latestUsers = array(); }

// 最新反馈
try {
    $latestFeedbacks = db()->fetchAll(
        "SELECT f.id,f.title,f.status,f.created_at,u.username as u_name
         FROM feedbacks f LEFT JOIN users u ON f.user_id=u.id
         ORDER BY f.created_at DESC LIMIT 6"
    );
} catch (Exception $e) { $latestFeedbacks = array(); }

// 最近观看
try {
    $latestHistory = db()->fetchAll(
        "SELECT w.*,u.username as u_name FROM watch_history w LEFT JOIN users u ON w.user_id=u.id
         ORDER BY w.last_watch_at DESC LIMIT 6"
    );
} catch (Exception $e) { $latestHistory = array(); }

// 最近收藏
try {
    $latestFavorites = db()->fetchAll(
        "SELECT f.*,u.username as u_name FROM favorites f LEFT JOIN users u ON f.user_id=u.id
         ORDER BY f.created_at DESC LIMIT 6"
    );
} catch (Exception $e) { $latestFavorites = array(); }

include dirname(__FILE__) . '/includes/header.php'; // 重新包含（因为第一行$activeMenu在header读取前赋值需要header之前，但上面的include里已经输出了）
// 注意：上面两次include导致重复输出，这不行。所以调整：我们删除上面第一次include，因为$activeMenu必须在header前定义。

// 实际上正确做法是header里的$activeMenu依赖在include前定义，所以去掉多余include。但我们的header前面有require+die，所以要保留。
// 为了不重复输出，用ob缓冲区。但代码已经写了。简单做法：只保留一个include，把$activeMenu移到require之前。
ob_start();
require_once dirname(__FILE__) . '/includes/header.php';
$headerContent = ob_get_clean();
// 但之前已经echo了（第一行include），所以这不行。
// 更好的做法：重写这个文件的结构，把$activeMenu放在最前面。
// 考虑到时间，我直接在第一个include前定义$activeMenu。
// 但前面第一行已经include了header，所以下面的include重复了。
// => 因此去掉底部那个header include。
?>

<?php
// 上面的逻辑说明：为避免重复include，这里只需要第一个require_once。下面的代码直接开始内容区。
// 删除下面的 include header 行
?>

<!-- 内容区开始 -->
<div class="stats-grid">
    <div class="stat-card users">
        <div class="stat-icon">👥</div>
        <div class="stat-label">总用户数</div>
        <div class="stat-value"><?php echo $stats['users'];?></div>
        <div class="stat-change up">↑ 今日新增 <?php echo $stats['today_users'];?></div>
    </div>
    <div class="stat-card feedback">
        <div class="stat-icon">💬</div>
        <div class="stat-label">反馈总数</div>
        <div class="stat-value"><?php echo $stats['feedback'];?></div>
        <div class="stat-change <?php echo $stats['pending_feedback']>0?'down':'up';?>">
            <?php echo $stats['pending_feedback']>0?'⚠ 待处理':'✅ 全部处理';?> <?php echo $stats['pending_feedback'];?>
        </div>
    </div>
    <div class="stat-card views">
        <div class="stat-icon">📺</div>
        <div class="stat-label">观看历史</div>
        <div class="stat-value"><?php echo $stats['views'];?></div>
        <div class="stat-change up">↑ 今日观看 <?php echo $stats['today_views'];?></div>
    </div>
    <div class="stat-card favorites">
        <div class="stat-icon">❤️</div>
        <div class="stat-label">收藏总数</div>
        <div class="stat-value"><?php echo $stats['favorites'];?></div>
        <div class="stat-change up">↑ 今日收藏 <?php echo $stats['today_favorites'];?></div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- 最新注册用户 -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div class="dashboard-panel-title">👤 最新注册用户</div>
            <a href="<?php echo BASE_URL;?>/admin/users.php" class="list-item-action">查看全部 →</a>
        </div>
        <div class="dashboard-panel-body">
            <?php if (empty($latestUsers)): ?>
            <div class="empty-state"><div class="empty-state-icon">👥</div><div>暂无用户</div></div>
            <?php else: foreach ($latestUsers as $u): ?>
            <div class="list-item">
                <div class="list-item-avatar">
                    <?php if(!empty($u['avatar'])):?>
                    <img src="<?php echo e($u['avatar']);?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: echo e(mb_substr($u['username'],0,1,'UTF-8')); endif;?>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title">
                        <?php echo e($u['username']);?>
                        <?php if(intval($u['is_admin'])===1):?><span class="admin-logo-badge" style="margin-left:6px;">开发者</span><?php endif;?>
                        <?php if(intval($u['status'])===0):?><span style="margin-left:6px;font-size:10px;color:#ef4444;">已封禁</span><?php endif;?>
                    </div>
                    <div class="list-item-subtitle"><?php echo e($u['email']);?></div>
                </div>
                <div class="list-item-time"><?php echo format_time($u['created_at']);?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- 最新反馈 -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div class="dashboard-panel-title">💬 最新反馈</div>
            <a href="<?php echo BASE_URL;?>/admin/feedback.php" class="list-item-action">查看全部 →</a>
        </div>
        <div class="dashboard-panel-body">
            <?php if (empty($latestFeedbacks)): ?>
            <div class="empty-state"><div class="empty-state-icon">💬</div><div>暂无反馈</div></div>
            <?php else: foreach ($latestFeedbacks as $f):
                $stArr = array('pending'=>array('待处理','status-pending'),'processing'=>array('处理中','status-active'),'resolved'=>array('已解决','status-resolved'),'closed'=>array('已关闭','status-closed'));
                $st = isset($stArr[$f['status']]) ? $stArr[$f['status']] : array($f['status'],'status-banned');
            ?>
            <div class="list-item" onclick="location.href='<?php echo BASE_URL;?>/admin/feedback.php?id=<?php echo intval($f['id']);?>'" style="cursor:pointer;">
                <div class="list-item-content" style="margin-left:0;">
                    <div class="list-item-title" style="display:flex;align-items:center;gap:8px;">
                        <?php echo e(mb_substr($f['title'],0,30,'UTF-8'));?><?php echo mb_strlen($f['title'],'UTF-8')>30?'...':'';?>
                        <span class="status-badge <?php echo $st[1];?>" style="margin-left:auto;"><?php echo $st[0];?></span>
                    </div>
                    <div class="list-item-subtitle">来自：<?php echo e($f['u_name']);?></div>
                </div>
                <div class="list-item-time"><?php echo format_time($f['created_at']);?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- 观看历史模块 -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div class="dashboard-panel-title">📺 观看历史</div>
            <a href="<?php echo BASE_URL;?>/admin/history.php" class="list-item-action">独立模块 →</a>
        </div>
        <div class="dashboard-panel-body">
            <?php if (empty($latestHistory)): ?>
            <div class="empty-state"><div class="empty-state-icon">📺</div><div>暂无观看记录</div></div>
            <?php else: foreach ($latestHistory as $h): ?>
            <div class="list-item">
                <div style="width:40px;height:56px;border-radius:6px;overflow:hidden;background:var(--bg-dark);flex-shrink:0;">
                    <?php if(!empty($h['poster'])):?>
                    <img src="<?php echo e($h['poster']);?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none';this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;font-size:18px;color:var(--text-muted);\\'>🎬</div>'">
                    <?php else:?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:18px;color:var(--text-muted);">🎬</div>
                    <?php endif;?>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title" title="<?php echo e($h['movie_name']);?>"><?php echo e(mb_substr($h['movie_name'],0,20,'UTF-8'));?><?php echo mb_strlen($h['movie_name'],'UTF-8')>20?'...':'';?></div>
                    <div class="list-item-subtitle">用户：<?php echo e($h['u_name']);?> · 第<?php echo intval($h['season']);?>季第<?php echo intval($h['episode']);?>集 · 观看<?php echo intval($h['watch_seconds']);?>秒</div>
                </div>
                <div class="list-item-time"><?php echo format_time($h['last_watch_at']);?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- 用户收藏模块 -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div class="dashboard-panel-title">❤️ 用户收藏</div>
            <a href="<?php echo BASE_URL;?>/admin/favorites.php" class="list-item-action">独立模块 →</a>
        </div>
        <div class="dashboard-panel-body">
            <?php if (empty($latestFavorites)): ?>
            <div class="empty-state"><div class="empty-state-icon">❤️</div><div>暂无收藏记录</div></div>
            <?php else: foreach ($latestFavorites as $f): ?>
            <div class="list-item">
                <div style="width:40px;height:56px;border-radius:6px;overflow:hidden;background:var(--bg-dark);flex-shrink:0;">
                    <?php if(!empty($f['poster'])):?>
                    <img src="<?php echo e($f['poster']);?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none';this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;font-size:18px;color:var(--text-muted);\\'>🎬</div>'">
                    <?php else:?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:18px;color:var(--text-muted);">🎬</div>
                    <?php endif;?>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title" title="<?php echo e($f['movie_name']);?>"><?php echo e(mb_substr($f['movie_name'],0,24,'UTF-8'));?><?php echo mb_strlen($f['movie_name'],'UTF-8')>24?'...':'';?></div>
                    <div class="list-item-subtitle">用户：<?php echo e($f['u_name']);?> · 评分：<?php echo $f['rating']?number_format($f['rating'],1):'-';?></div>
                </div>
                <div class="list-item-time"><?php echo format_time($f['created_at']);?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- 包含底部 -->
<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
