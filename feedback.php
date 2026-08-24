<?php
/**
 * 反馈中心
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

$pageTitle = '反馈中心';
$currentPage = 'feedback';
$currentUser = current_user();

$mustLogin = false; // 列表可以浏览，但发布需要登录

// 处理提交反馈
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$currentUser) {
    redirect(BASE_URL . '/login.php?need_login=1');
}

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$viewId = isset($_GET['id']) ? intval($_GET['id']) : 0;

list($page, $perPage, $offset) = get_pagination_params();

// 查询条件
$whereSql = '1=1';
$params = array();
if ($status) {
    $whereSql .= ' AND f.status = ?';
    $params[] = $status;
}
$orderSql = 'f.is_pinned DESC, f.created_at DESC';

try {
    $total = db()->fetchOne("SELECT COUNT(*) as cnt FROM feedbacks f WHERE {$whereSql}", $params);
    $totalCnt = intval($total['cnt']);
    $totalPages = ceil($totalCnt / $perPage);
    $feedbacks = db()->fetchAll(
        "SELECT f.*, u.username as author_name, u.avatar as author_avatar, u.is_admin as author_is_admin,
         (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id = f.id) as reply_count,
         (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id = f.id) as like_count
         FROM feedbacks f LEFT JOIN users u ON f.user_id = u.id
         WHERE {$whereSql} ORDER BY {$orderSql} LIMIT {$offset}, {$perPage}",
        $params
    );
} catch (Exception $e) {
    $feedbacks = array(); $totalCnt = 0; $totalPages = 1;
}

// 处理查看详情
$viewFeedback = null;
$viewReplies = array();
$likedIds = array();
if ($viewId > 0) {
    try {
        $viewFeedback = db()->fetchOne(
            "SELECT f.*, u.username as author_name, u.avatar as author_avatar, u.is_admin as author_is_admin,
             (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id = f.id) as reply_count,
             (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id = f.id) as like_count
             FROM feedbacks f LEFT JOIN users u ON f.user_id = u.id WHERE f.id = ?",
            array($viewId)
        );
        if ($viewFeedback) {
            // 获取回复：按要求排序 - 反馈者的回复优先在最下面，管理员在其之上，普通用户在管理员之上？
            // 根据需求：管理员回复在普通用户回复上面，反馈者下面
            // 顺序：最上面是反馈者原文（已显示）→ 管理员回复 → 普通用户回复（按时间）
            // 简化实现：先显示反馈者回复，然后管理员，再按时间普通用户
            $rawReplies = db()->fetchAll(
                "SELECT r.*, u.username as reply_name, u.avatar as reply_avatar, u.is_admin as reply_is_admin
                 FROM feedback_replies r LEFT JOIN users u ON r.user_id = u.id
                 WHERE r.feedback_id = ? ORDER BY r.created_at ASC",
                array($viewId)
            );
            // 排序：反馈者自己的回复在最后（紧贴反馈者），管理员在反馈者上面，普通在管理员上面
            // 即：普通(最早) → 管理员 → 反馈者回复
            $authorId = intval($viewFeedback['user_id']);
            $sortedReplies = array();
            $adminReplies = array();
            $authorReplies = array();
            foreach ($rawReplies as $r) {
                $isAuthor = intval($r['user_id']) === $authorId;
                $isAdmin = intval($r['reply_is_admin']) === 1;
                if ($isAuthor) $authorReplies[] = $r;
                elseif ($isAdmin) $adminReplies[] = $r;
                else $sortedReplies[] = $r;
            }
            // 合并顺序：普通 → 管理员 → 作者回复
            $viewReplies = array_merge($sortedReplies, $adminReplies, $authorReplies);

            // 当前用户点赞过哪些反馈
            if ($currentUser) {
                $likes = db()->fetchAll("SELECT feedback_id FROM feedback_likes WHERE user_id = ?", array(intval($currentUser['id'])));
                foreach ($likes as $l) $likedIds[$l['feedback_id']] = true;
            }
        }
    } catch (Exception $e) {
        $viewFeedback = null;
    }
}

include ROOT_PATH . '/includes/header.php';
?>

<main class="container" style="padding:24px 20px 60px;">

    <div class="section-header">
        <h2 class="section-title">💬 反馈中心</h2>
        <?php if ($currentUser && !$viewId): ?>
        <button class="btn btn-primary" onclick="document.getElementById('new-feedback-modal').classList.add('show')">
            ✍️ 发布反馈
        </button>
        <?php endif; ?>
    </div>

    <!-- 状态筛选 -->
    <div style="margin-bottom:24px;display:flex;gap:10px;flex-wrap:wrap;">
        <a class="genre-tab <?php echo !$status ? 'active' : ''; ?>" href="?">全部</a>
        <a class="genre-tab <?php echo $status === 'pending' ? 'active' : ''; ?>" href="?status=pending">待处理</a>
        <a class="genre-tab <?php echo $status === 'processing' ? 'active' : ''; ?>" href="?status=processing">处理中</a>
        <a class="genre-tab <?php echo $status === 'resolved' ? 'active' : ''; ?>">已解决</a>
        <a class="genre-tab <?php echo $status === 'closed' ? 'active' : ''; ?>" href="?status=closed">已关闭</a>
    </div>

    <?php if ($viewFeedback): ?>
    <!-- 详情视图 -->
    <div style="margin-bottom:20px;">
        <a href="<?php echo BASE_URL; ?>/feedback.php" class="btn btn-outline btn-sm">← 返回列表</a>
    </div>

    <div class="feedback-detail-header">
        <div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:20px;">
            <div class="reply-avatar <?php echo intval($viewFeedback['author_is_admin']) === 1 ? 'admin' : 'author'; ?>" style="width:52px;height:52px;font-size:20px;flex-shrink:0;">
                <?php if (!empty($viewFeedback['author_avatar'])): ?>
                <img src="<?php echo e($viewFeedback['author_avatar']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: echo e(mb_substr($viewFeedback['author_name'],0,1,'UTF-8')); endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                    <h3 style="font-size:20px;font-weight:700;"><?php echo e($viewFeedback['title']); ?></h3>
                    <?php $st = $viewFeedback['status'];
                        $statusBadge = array('pending'=>array('待处理','status-pending'),'processing'=>array('处理中','status-active'),'resolved'=>array('已解决','status-resolved'),'closed'=>array('已关闭','status-closed'));
                        $sb = isset($statusBadge[$st]) ? $statusBadge[$st] : array($st,'status-banned');
                    ?>
                    <span class="status-badge <?php echo $sb[1]; ?>"><?php echo $sb[0]; ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;color:var(--text-muted);font-size:13px;flex-wrap:wrap;margin-bottom:12px;">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <strong style="color:var(--text-primary);"><?php echo e($viewFeedback['author_name']); ?></strong>
                        (反馈者)
                        <?php if (intval($viewFeedback['author_is_admin']) === 1): ?>
                        <span class="admin-logo-badge">开发者</span>
                        <?php endif; ?>
                    </span>
                    <span>📅 <?php echo format_time($viewFeedback['created_at'], true); ?></span>
                    <span>💬 <?php echo intval($viewFeedback['reply_count']); ?> 回复</span>
                    <button class="action-btn <?php echo isset($likedIds[$viewId]) ? 'liked' : ''; ?>"
                            data-like-feedback="<?php echo $viewId; ?>"
                            style="padding:4px 10px;<?php echo isset($likedIds[$viewId]) ? 'color:var(--primary-light);border-color:var(--primary-color);' : ''; ?>">
                        👍 <span class="like-count"><?php echo intval($viewFeedback['like_count']); ?></span>
                    </button>
                </div>
                <div style="background:var(--bg-dark);padding:16px;border-radius:var(--radius-md);line-height:1.8;color:var(--text-secondary);white-space:pre-wrap;word-break:break-word;">
                    <?php echo e($viewFeedback['content']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 回复区 -->
    <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;">💬 回复 (<?php echo count($viewReplies); ?>)</h3>
    <div class="feedback-replies-container" id="replies-container">
        <?php
        $collapseAfter = 3;
        $totalReplies = count($viewReplies);
        foreach ($viewReplies as $idx => $r):
            $isAdmin = intval($r['reply_is_admin']) === 1;
            $isAuthor = intval($r['user_id']) === $authorId;
            $collapsed = ($totalReplies > $collapseAfter) && ($idx >= $collapseAfter);
        ?>
        <div class="feedback-reply <?php echo $isAdmin ? 'is-admin' : ($isAuthor ? 'is-author' : ''); ?>"
             data-feedback="<?php echo $viewId; ?>" data-collapsed="<?php echo $collapsed ? 'true' : 'false'; ?>"
             style="<?php echo $collapsed ? 'display:none;' : ''; ?>">
            <div class="reply-header">
                <div class="reply-avatar <?php echo $isAdmin ? 'admin' : ($isAuthor ? 'author' : 'user'); ?>">
                    <?php if (!empty($r['reply_avatar'])): ?>
                    <img src="<?php echo e($r['reply_avatar']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: echo e(mb_substr($r['reply_name'],0,1,'UTF-8')); endif; ?>
                </div>
                <div class="reply-meta">
                    <div class="reply-name">
                        <?php echo e($r['reply_name']); ?>
                        <?php if ($isAdmin): ?><span class="admin-logo-badge">开发者</span><?php endif; ?>
                        <?php if ($isAuthor): ?><span style="padding:2px 8px;background:var(--primary-bg);color:var(--primary-light);font-size:10px;border-radius:4px;font-weight:600;margin-left:6px;">反馈者</span><?php endif; ?>
                    </div>
                    <div class="reply-time"><?php echo format_time($r['created_at'], true); ?></div>
                </div>
            </div>
            <div class="reply-content" style="white-space:pre-wrap;word-break:break-word;"><?php echo e($r['content']); ?></div>
        </div>
        <?php endforeach; ?>

        <?php if ($totalReplies > $collapseAfter): ?>
        <button class="expand-btn" data-expand-replies="<?php echo $viewId; ?>" data-expanded="false">
            展开剩余 <?php echo $totalReplies - $collapseAfter; ?> 条回复 ▾
        </button>
        <?php endif; ?>
    </div>

    <!-- 回复输入 -->
    <?php if ($currentUser && !is_banned($currentUser)): ?>
    <div style="margin-top:24px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:20px;">
        <h4 style="font-size:15px;font-weight:600;margin-bottom:12px;">✏️ 撰写回复</h4>
        <form onsubmit="submitReply(event, <?php echo $viewId; ?>)">
            <textarea class="form-textarea" id="reply-content" placeholder="写下你的回复...（Ctrl+Enter 快速发送）" required></textarea>
            <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                <button type="submit" class="btn btn-primary">发送回复</button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div style="margin-top:24px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:24px;text-align:center;">
        <p style="color:var(--text-secondary);margin-bottom:12px;">💡 登录后才能回复哦～</p>
        <a href="<?php echo BASE_URL; ?>/login.php?need_login=1" class="btn btn-primary btn-sm">立即登录</a>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- 列表视图 -->
    <?php if (!empty($feedbacks)): ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($feedbacks as $f):
            $authorId = intval($f['user_id']);
            $isAuthor = $currentUser && intval($currentUser['id']) === $authorId;
            $isAdminAuthor = intval($f['author_is_admin']) === 1;
            $st = $f['status'];
            $statusBadge = array('pending'=>array('待处理','status-pending'),'processing'=>array('处理中','status-active'),'resolved'=>array('已解决','status-resolved'),'closed'=>array('已关闭','status-closed'));
            $sb = isset($statusBadge[$st]) ? $statusBadge[$st] : array($st,'status-banned');
            $isLiked = $currentUser && isset($likedIds[intval($f['id'])]);
        ?>
        <a href="?id=<?php echo intval($f['id']); ?>" style="display:block;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:24px;transition:var(--transition);"
           onmouseover="this.style.borderColor='var(--primary-color)';this.style.transform='translateY(-2px)'"
           onmouseout="this.style.borderColor='var(--border-color)';this.style.transform='none'">
            <div style="display:flex;align-items:center;gap:14px;">
                <div class="reply-avatar <?php echo $isAdminAuthor ? 'admin' : 'user'; ?>" style="width:48px;height:48px;font-size:18px;flex-shrink:0;">
                    <?php if (!empty($f['author_avatar'])): ?>
                    <img src="<?php echo e($f['author_avatar']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: echo e(mb_substr($f['author_name'],0,1,'UTF-8')); endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                        <h4 style="font-size:16px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:500px;"><?php echo e($f['title']); ?></h4>
                        <?php if (intval($f['is_pinned']) === 1): ?>
                        <span class="status-badge status-warning">📌 置顶</span>
                        <?php endif; ?>
                        <span class="status-badge <?php echo $sb[1]; ?>"><?php echo $sb[0]; ?></span>
                    </div>
                    <p style="color:var(--text-secondary);font-size:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.7;margin-bottom:10px;">
                        <?php echo e($f['content']); ?>
                    </p>
                    <div style="display:flex;align-items:center;gap:16px;color:var(--text-muted);font-size:12px;flex-wrap:wrap;">
                        <span style="display:flex;align-items:center;gap:6px;">
                            <strong style="color:var(--text-secondary);"><?php echo e($f['author_name']); ?></strong>
                            <?php if ($isAdminAuthor): ?><span class="admin-logo-badge">开发者</span><?php endif; ?>
                            <?php if ($isAuthor): ?><span style="padding:1px 6px;background:var(--primary-bg);color:var(--primary-light);font-size:10px;border-radius:3px;">我</span><?php endif; ?>
                        </span>
                        <span>📅 <?php echo format_time($f['created_at']); ?></span>
                        <span>💬 <?php echo intval($f['reply_count']); ?></span>
                        <span>👍 <?php echo intval($f['like_count']); ?></span>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:32px;">
        <?php
        $qs = $_GET; unset($qs['page']);
        $baseQs = http_build_query($qs);
        $baseQs = $baseQs ? '?' . $baseQs . '&page=' : '?page=';
        $prev = $page - 1; $next = $page + 1;
        $start = max(1, $page - 2); $end = min($totalPages, $start + 4);
        if ($end - $start < 4) $start = max(1, $end - 4);
        ?>
        <button <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="location.href='<?php echo $baseQs . $prev; ?>'">‹ 上一页</button>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <button class="<?php echo $i === $page ? 'active' : ''; ?>" onclick="location.href='<?php echo $baseQs . $i; ?>'"><?php echo $i; ?></button>
        <?php endfor; ?>
        <button <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="location.href='<?php echo $baseQs . $next; ?>'">下一页 ›</button>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div style="padding:80px;text-align:center;">
        <div style="font-size:72px;margin-bottom:16px;">📭</div>
        <h3 style="font-size:20px;margin-bottom:8px;">暂无反馈</h3>
        <p style="color:var(--text-muted);margin-bottom:20px;">
            <?php if ($currentUser): ?>成为第一个发布反馈的人吧！<?php else: ?>登录后发布你的第一条反馈<?php endif; ?>
        </p>
        <?php if ($currentUser): ?>
        <button class="btn btn-primary" onclick="document.getElementById('new-feedback-modal').classList.add('show')">发布反馈</button>
        <?php else: ?>
        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary">立即登录</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>

<!-- 发布反馈弹窗 -->
<?php if ($currentUser && !is_banned($currentUser)): ?>
<div id="new-feedback-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:560px;">
        <button class="modal-close" onclick="document.getElementById('new-feedback-modal').classList.remove('show')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
        <div class="modal-banner"></div>
        <div class="modal-body">
            <h3 class="modal-title">📝 发布反馈</h3>
            <p class="modal-date">帮助我们做得更好，您的每条反馈都很重要！</p>
            <form onsubmit="submitFeedback(event)" style="margin-top:16px;">
                <div class="form-group">
                    <label class="form-label">标题</label>
                    <input type="text" id="fb-title" class="form-input" placeholder="简要描述您的问题或建议（≤100字）" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label class="form-label">详细内容</label>
                    <textarea id="fb-content" class="form-textarea" style="min-height:160px;" placeholder="请详细描述您遇到的问题或建议内容..." required></textarea>
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('new-feedback-modal').classList.remove('show')">取消</button>
                    <button type="submit" class="btn btn-primary">📨 提交反馈</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function submitFeedback(e) {
    e.preventDefault();
    if (!<?php echo $currentUser ? 'true' : 'false'; ?>) { location.href='<?php echo BASE_URL; ?>/login.php?need_login=1'; return; }
    var title = document.getElementById('fb-title').value.trim();
    var content = document.getElementById('fb-content').value.trim();
    if (!title || !content) return;
    fetch('<?php echo BASE_URL; ?>/api/feedback.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'create', title:title, content:content})
    }).then(r=>r.json()).then(res => {
        if (res.code === 200) {
            JayMovie.showToast('反馈提交成功，感谢您的支持！', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            JayMovie.showToast(res.message || '提交失败', 'error');
        }
    }).catch(() => JayMovie.showToast('网络错误', 'error'));
}
function submitReply(e, fid) {
    e.preventDefault();
    var ta = document.getElementById('reply-content');
    var content = ta.value.trim();
    if (!content) return;
    fetch('<?php echo BASE_URL; ?>/api/feedback.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'reply', feedback_id:fid, content:content})
    }).then(r=>r.json()).then(res => {
        if (res.code === 200) {
            JayMovie.showToast('回复成功', 'success');
            setTimeout(() => location.reload(), 600);
        } else if (res.code === 401) {
            location.href = '<?php echo BASE_URL; ?>/login.php?need_login=1';
        } else {
            JayMovie.showToast(res.message || '回复失败', 'error');
        }
    });
}
// Ctrl+Enter 快速回复
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        var ta = document.getElementById('reply-content');
        if (ta && document.activeElement === ta) {
            ta.closest('form').requestSubmit();
        }
    }
});
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
