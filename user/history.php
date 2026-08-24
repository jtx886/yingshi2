<?php
/**
 * 观看历史
 */
require_once dirname(__FILE__) . '/../config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!is_logged_in()) redirect(BASE_URL . '/login.php');
$user = current_user();
if (is_banned($user)) die('账号被封禁');
$userId = intval($user['id']);

$pageTitle = '观看历史';
$currentPage = 'profile';

// 处理删除
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'delete') {
        $hid = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$hid) json_error('参数错误');
        db()->delete('watch_history', 'id = ? AND user_id = ?', array($hid, $userId));
        json_success();
    } elseif ($action === 'clear') {
        db()->delete('watch_history', 'user_id = ?', array($userId));
        json_success();
    }
    json_error('未知操作');
}

list($page, $perPage, $offset) = get_pagination_params();

try {
    $total = db()->fetchOne("SELECT COUNT(*) as cnt FROM watch_history WHERE user_id = ?", array($userId));
    $totalCnt = intval($total['cnt']);
    $totalPages = ceil($totalCnt / $perPage);
    $history = db()->fetchAll("SELECT * FROM watch_history WHERE user_id = ? ORDER BY last_watch_at DESC LIMIT {$offset}, {$perPage}", array($userId));
} catch (Exception $e) {
    $totalCnt = 0; $totalPages = 1; $history = array();
}

include ROOT_PATH . '/includes/header.php';
?>

<main class="container" style="padding:24px 20px 60px;">

    <div style="display:grid;grid-template-columns:320px 1fr;gap:24px;">
        <!-- 侧边菜单 -->
        <div>
            <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:24px;text-align:center;margin-bottom:24px;">
                <div style="width:80px;height:80px;border-radius:50%;margin:0 auto 12px;background:var(--primary-gradient);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:white;overflow:hidden;">
                    <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo e($user['avatar']); ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: echo e(mb_substr($user['username'],0,1,'UTF-8')); endif; ?>
                </div>
                <h3 style="font-size:16px;font-weight:600;"><?php echo e($user['username']); ?></h3>
                <p style="font-size:12px;color:var(--text-muted);"><?php echo e($user['email']); ?></p>
            </div>
            <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:8px;">
                <a href="<?php echo BASE_URL; ?>/user/profile.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                    个人信息
                </a>
                <a href="<?php echo BASE_URL; ?>/user/favorites.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 5a5.5 5.5 0 0 1 9.5 7C19 16.5 12 21 12 21z"/></svg>
                    我的收藏
                </a>
                <a href="<?php echo BASE_URL; ?>/user/history.php" class="user-dropdown-item active" style="background:var(--primary-bg);color:var(--primary-light);border-radius:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    观看历史 <span style="margin-left:auto;color:var(--primary-light);"><?php echo $totalCnt; ?></span>
                </a>
                <a href="<?php echo BASE_URL; ?>/feedback.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    反馈中心
                </a>
            </div>
        </div>

        <!-- 右侧内容 -->
        <div>
            <div class="admin-table-wrapper">
                <div class="admin-table-header">
                    <div class="admin-table-title">⏱️ 观看历史 (<?php echo $totalCnt; ?>)</div>
                    <?php if ($totalCnt > 0): ?>
                    <button onclick="clearHistory()" class="btn btn-danger btn-sm">🗑 清空历史</button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($history)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">封面</th>
                            <th>影片名称</th>
                            <th>类型</th>
                            <th>进度</th>
                            <th>观看时间</th>
                            <th style="width:150px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h):
                            $mid = $h['movie_id'];
                            $type = isset($h['type']) ? $h['type'] : 'movie';
                            $playUrl = BASE_URL . '/play.php?id=' . $mid . '&type=' . $type;
                            if ($type !== 'movie') $playUrl .= '&season=' . intval($h['season']) . '&ep=' . intval($h['episode']);
                            $detailUrl = BASE_URL . '/detail.php?id=' . $mid . '&type=' . $type;
                            $secs = intval($h['watch_seconds']);
                            $watchH = floor($secs / 3600);
                            $watchM = floor(($secs % 3600) / 60);
                            $watchS = $secs % 60;
                            $watchTimeStr = '';
                            if ($watchH) $watchTimeStr .= $watchH . '时';
                            if ($watchM) $watchTimeStr .= $watchM . '分';
                            if (!$watchH && !$watchM) $watchTimeStr .= $watchS . '秒';
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo e($playUrl); ?>">
                                    <div style="width:56px;height:80px;border-radius:6px;overflow:hidden;background:var(--bg-dark);">
                                        <?php if (!empty($h['poster'])): ?>
                                        <img src="<?php echo e($h['poster']); ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;font-size:20px;\\'>🎬</div>'">
                                        <?php else: ?>
                                        <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:20px;color:var(--text-muted);">🎬</div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo e($detailUrl); ?>" style="font-weight:600;display:block;margin-bottom:4px;"><?php echo e($h['movie_name']); ?></a>
                                <?php if ($type !== 'movie'): ?>
                                <div style="font-size:12px;color:var(--text-muted);">
                                    <a href="<?php echo e($playUrl); ?>" style="color:var(--primary-light);">
                                        第<?php echo intval($h['season']); ?>季 - <?php echo $h['episode_name'] ? e($h['episode_name']) : ('第'.intval($h['episode']).'集'); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="movie-card-type"><?php echo isset($h['type']) ? array('movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺')[$h['type']] : '-'; ?></span>
                            </td>
                            <td>
                                <div style="color:var(--text-secondary);font-size:13px;">
                                    <span title="观看了 <?php echo $secs; ?> 秒">⏱ <?php echo $watchTimeStr; ?></span>
                                </div>
                            </td>
                            <td style="color:var(--text-muted);font-size:13px;"><?php echo format_time($h['last_watch_at'], true); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo e($playUrl); ?>" class="action-btn edit">继续观看</a>
                                    <button class="action-btn delete" onclick="deleteHistory(<?php echo intval($h['id']); ?>, this)">删除</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $prev = $page - 1; $next = $page + 1;
                    $start = max(1, $page - 2); $end = min($totalPages, $start + 4);
                    if ($end - $start < 4) $start = max(1, $end - 4);
                    ?>
                    <button <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="location.href='?page=<?php echo $prev; ?>'">‹ 上一页</button>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <button class="<?php echo $i === $page ? 'active' : ''; ?>" onclick="location.href='?page=<?php echo $i; ?>'"><?php echo $i; ?></button>
                    <?php endfor; ?>
                    <button <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="location.href='?page=<?php echo $next; ?>'">下一页 ›</button>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div style="padding:80px;text-align:center;">
                    <div style="font-size:72px;margin-bottom:16px;">📺</div>
                    <h3 style="font-size:20px;margin-bottom:8px;">还没有观看记录</h3>
                    <p style="color:var(--text-muted);margin-bottom:20px;">看部电影开启你的观影之旅吧</p>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">去首页看看</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function deleteHistory(id, btn) {
    if (!confirm('确定要删除这条观看记录吗？')) return;
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json()).then(res => {
            if (res.code === 200) {
                btn.closest('tr').remove();
                if (!document.querySelector('.admin-table tbody tr')) location.reload();
            } else alert(res.message || '删除失败');
        });
}
function clearHistory() {
    if (!confirm('确定要清空所有观看历史吗？此操作不可恢复！')) return;
    var fd = new FormData();
    fd.append('action', 'clear');
    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json()).then(res => location.reload());
}
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
