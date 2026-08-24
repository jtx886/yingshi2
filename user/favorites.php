<?php
/**
 * 我的收藏
 */
require_once dirname(__FILE__) . '/../config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!is_logged_in()) redirect(BASE_URL . '/login.php');
$user = current_user();
if (is_banned($user)) die('账号被封禁');
$userId = intval($user['id']);

$pageTitle = '我的收藏';
$currentPage = 'profile';

// 处理删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $mid = isset($_POST['movie_id']) ? strval($_POST['movie_id']) : '';
    if (!$mid) json_error('参数错误');
    db()->delete('favorites', 'user_id = ? AND movie_id = ?', array($userId, $mid));
    json_success(null, '已取消收藏');
}

// 分页
list($page, $perPage, $offset) = get_pagination_params();

try {
    $total = db()->fetchOne("SELECT COUNT(*) as cnt FROM favorites WHERE user_id = ?", array($userId));
    $totalCnt = intval($total['cnt']);
    $totalPages = ceil($totalCnt / $perPage);
    $favorites = db()->fetchAll("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC LIMIT {$offset}, {$perPage}", array($userId));
} catch (Exception $e) {
    $totalCnt = 0; $totalPages = 1; $favorites = array();
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
                <a href="<?php echo BASE_URL; ?>/user/favorites.php" class="user-dropdown-item active" style="background:var(--primary-bg);color:var(--primary-light);border-radius:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 5a5.5 5.5 0 0 1 9.5 7C19 16.5 12 21 12 21z"/></svg>
                    我的收藏 <span style="margin-left:auto;color:var(--primary-light);"><?php echo $totalCnt; ?></span>
                </a>
                <a href="<?php echo BASE_URL; ?>/user/history.php" class="user-dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    观看历史
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
                    <div class="admin-table-title">❤️ 我的收藏 (<?php echo $totalCnt; ?>)</div>
                    <?php if ($totalCnt > 0): ?>
                    <button onclick="clearAll()" class="btn btn-danger btn-sm">🗑 清空全部</button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($favorites)): ?>
                <div class="movies-grid" style="padding:24px;">
                    <?php foreach ($favorites as $f):
                        $mid = $f['movie_id'];
                        $type = isset($f['type']) ? $f['type'] : 'movie';
                        $detailUrl = BASE_URL . '/detail.php?id=' . $mid . '&type=' . $type;
                        $playUrl = BASE_URL . '/play.php?id=' . $mid . '&type=' . $type;
                    ?>
                    <div class="movie-card" data-movie-link="<?php echo e($detailUrl); ?>">
                        <div class="movie-card-poster">
                            <?php if (!empty($f['poster'])): ?>
                            <img src="<?php echo e($f['poster']); ?>" alt="<?php echo e($f['movie_name']); ?>" loading="lazy" onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="poster-placeholder" style="display:none;">🎬</div>
                            <?php else: ?>
                            <div class="poster-placeholder">🎬 <?php echo e(mb_substr($f['movie_name'],0,4,'UTF-8')); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($f['rating'])): ?>
                            <div class="card-rating"><span class="star-icon"></span><span><?php echo e(number_format($f['rating'],1)); ?></span></div>
                            <?php endif; ?>
                            <button class="card-favorite active" onclick="event.stopPropagation();removeFav(this,'<?php echo e($mid); ?>')" title="取消收藏">
                                <span class="heart-icon"></span>
                            </button>
                        </div>
                        <div class="movie-card-info">
                            <div class="movie-card-title" title="<?php echo e($f['movie_name']); ?>"><?php echo e($f['movie_name']); ?></div>
                            <div class="movie-card-meta">
                                <span class="movie-card-type"><?php echo isset($f['type']) ? array('movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺')[$f['type']] : '影视'; ?></span>
                                <span><?php echo !empty($f['year']) ? e($f['year']) : format_time($f['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

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
                    <div style="font-size:72px;margin-bottom:16px;">💔</div>
                    <h3 style="font-size:20px;margin-bottom:8px;">还没有收藏</h3>
                    <p style="color:var(--text-muted);margin-bottom:20px;">去首页逛逛，看到喜欢的就点击❤收藏吧</p>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">去首页看看</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function removeFav(btn, mid) {
    if (!confirm('确定要取消收藏吗？')) return;
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('movie_id', mid);
    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json()).then(res => {
            if (res.code === 200) {
                var card = btn.closest('.movie-card');
                card.style.transition = 'all 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8)';
                setTimeout(() => { card.remove(); location.reload(); }, 300);
            } else {
                alert(res.message || '操作失败');
            }
        });
}
function clearAll() {
    if (!confirm('确定要清空所有收藏吗？此操作不可恢复！')) return;
    fetch('<?php echo BASE_URL; ?>/api/favorite.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action: 'clear'})
    }).catch(()=>{}).finally(() => location.reload());
}
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
