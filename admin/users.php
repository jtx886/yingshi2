<?php
/**
 * 用户管理
 */
$activeMenu = 'users';
$pageSubTitle = '用户管理';
require_once dirname(__FILE__) . '/includes/header.php';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($action === 'ban') {
        $duration = isset($_POST['duration']) ? trim($_POST['duration']) : ''; // '1day','7days','30days','forever','custom'
        $customDate = isset($_POST['custom_date']) ? trim($_POST['custom_date']) : '';
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        $banTime = date('Y-m-d H:i:s');
        if ($duration === 'forever') {
            $banUntil = null;
        } elseif ($duration === 'custom' && $customDate) {
            $banUntil = date('Y-m-d H:i:s', strtotime($customDate));
        } else {
            $days = intval($duration);
            if ($days <= 0) $days = 7;
            $banUntil = date('Y-m-d H:i:s', time() + $days * 86400);
        }

        db()->update('users', array(
            'status' => 0,
            'ban_time' => $banTime,
            'ban_until' => $banUntil,
            'ban_reason' => $reason
        ), 'id = ?', array($userId));

        // 发送邮件通知
        $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));
        if ($user) {
            @send_ban_email($user, $banTime, $banUntil ? $banUntil : '永久封禁', $reason);
        }
        json_success(null, '用户已封禁，通知邮件已发送');
    }

    if ($action === 'unban') {
        db()->update('users', array(
            'status' => 1,
            'ban_time' => null,
            'ban_until' => null,
            'ban_reason' => null
        ), 'id = ?', array($userId));
        json_success(null, '已解封该用户');
    }

    if ($action === 'delete') {
        if ($userId === intval($currentUser['id'])) json_error('不能删除自己');
        db()->delete('users', 'id = ?', array($userId));
        json_success(null, '用户已删除');
    }

    if ($action === 'notify') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        if (!$title || !$content) json_error('标题和内容必填');
        $user = db()->fetchOne("SELECT email FROM users WHERE id = ?", array($userId));
        if ($user && $user['email']) {
            $sent = @send_notification_email($user['email'], $title, $content);
            if ($sent) json_success(null, '通知邮件已发送');
            json_error('邮件发送失败');
        }
        json_error('用户邮箱不存在');
    }

    json_error('未知操作');
}

// 列表
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
list($page, $perPage, $offset) = get_pagination_params();

$where = '1=1'; $params = array();
if ($search) { $where .= ' AND (username LIKE ? OR email LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if ($status) { $where .= ' AND status = ' . ($status==='banned' ? '0' : '1'); }

try {
    $total = db()->fetchOne("SELECT COUNT(*) c FROM users WHERE {$where}", $params);
    $totalCnt = intval($total['c']);
    $totalPages = ceil($totalCnt / $perPage);
    $users = db()->fetchAll("SELECT * FROM users WHERE {$where} ORDER BY created_at DESC LIMIT {$offset}, {$perPage}", $params);
} catch (Exception $e) { $users = array(); $totalCnt = 0; $totalPages = 1; }
?>

<div class="admin-table-wrapper">
    <div class="admin-table-header">
        <div class="admin-table-title">👥 用户管理 (共<?php echo $totalCnt;?>位)</div>
        <div class="admin-table-filters">
            <form method="GET" style="display:flex;gap:10px;">
                <div class="admin-table-search">
                    <input type="text" name="search" placeholder="搜索用户名/邮箱" value="<?php echo e($search);?>">
                </div>
                <select name="status" class="form-input" style="width:140px;height:36px;padding:0 12px;">
                    <option value="">全部状态</option>
                    <option value="active" <?php echo $status==='active'?'selected':'';?>>正常</option>
                    <option value="banned" <?php echo $status==='banned'?'selected':'';?>>已封禁</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">搜索</button>
                <a href="<?php echo BASE_URL;?>/admin/users.php" class="btn btn-outline btn-sm">重置</a>
            </form>
        </div>
    </div>

    <table class="admin-table">
        <thead>
        <tr>
            <th>用户</th>
            <th>邮箱</th>
            <th>角色</th>
            <th>状态</th>
            <th>封禁信息</th>
            <th>注册时间</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <div class="user-cell">
                    <div class="avatar-sm">
                        <?php if(!empty($u['avatar'])):?>
                        <img src="<?php echo e($u['avatar']);?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: echo e(mb_substr($u['username'],0,1,'UTF-8')); endif;?>
                    </div>
                    <div>
                        <div style="font-weight:600;">
                            <?php echo e($u['username']);?>
                            <?php if(intval($u['is_admin'])===1):?><span class="admin-logo-badge" style="margin-left:4px;">开发者</span><?php endif;?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);">ID: <?php echo intval($u['id']);?></div>
                    </div>
                </div>
            </td>
            <td style="color:var(--text-secondary);font-size:13px;"><?php echo e($u['email']);?></td>
            <td>
                <?php echo intval($u['is_admin'])===1 ? '<span class="status-badge status-resolved">管理员</span>' : '<span style="color:var(--text-muted);font-size:12px;">普通用户</span>';?>
            </td>
            <td>
                <?php if(intval($u['status'])===1):?>
                <span class="status-badge status-active">正常</span>
                <?php else:?>
                <span class="status-badge status-banned">已封禁</span>
                <?php endif;?>
            </td>
            <td style="font-size:12px;">
                <?php if(intval($u['status'])===0 && $u['ban_until']):?>
                至: <?php echo date('m-d H:i',strtotime($u['ban_until']));?>
                <?php elseif(intval($u['status'])===0):?>
                <span style="color:#ef4444;">永久</span>
                <?php else:?>
                <span style="color:var(--text-muted);">-</span>
                <?php endif;?>
                <?php if(!empty($u['ban_reason'])):?>
                <div style="color:var(--text-muted);" title="<?php echo e($u['ban_reason']);?>">原因: <?php echo e(mb_substr($u['ban_reason'],0,12,'UTF-8'));?><?php echo mb_strlen($u['ban_reason'],'UTF-8')>12?'...':'';?></div>
                <?php endif;?>
            </td>
            <td style="color:var(--text-muted);font-size:12px;"><?php echo format_time($u['created_at'],true);?></td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn reply" onclick="openNotify(<?php echo intval($u['id']);?>,'<?php echo e($u['username']);?>')">📧 发通知</button>
                    <?php if(intval($u['status'])===1):?>
                    <button class="action-btn ban" onclick="openBan(<?php echo intval($u['id']);?>,'<?php echo e($u['username']);?>')">🚫 封禁</button>
                    <?php else:?>
                    <button class="action-btn edit" onclick="unbanUser(<?php echo intval($u['id']);?>)">✅ 解封</button>
                    <?php endif;?>
                    <?php if(intval($u['is_admin'])!==1 && intval($u['id'])!==intval($currentUser['id'])):?>
                    <button class="action-btn delete" onclick="deleteUser(<?php echo intval($u['id']);?>)">删除</button>
                    <?php endif;?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)):?>
        <tr><td colspan="7" style="text-align:center;padding:60px;color:var(--text-muted);">📭 没有找到用户</td></tr>
        <?php endif;?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $qs = $_GET; unset($qs['page']);
        $qs = http_build_query($qs);
        $base = '?'.($qs?$qs.'&':'').'page=';
        $start = max(1, $page - 2); $end = min($totalPages, $start + 4);
        if ($end - $start < 4) $start = max(1, $end - 4);
        ?>
        <button <?php echo $page<=1?'disabled':'';?> onclick="location.href='<?php echo $base.($page-1);?>'">‹ 上一页</button>
        <?php for($i=$start;$i<=$end;$i++):?>
        <button class="<?php echo $i===$page?'active':'';?>" onclick="location.href='<?php echo $base.$i;?>'"><?php echo $i;?></button>
        <?php endfor;?>
        <button <?php echo $page>=$totalPages?'disabled':'';?> onclick="location.href='<?php echo $base.($page+1);?>'">下一页 ›</button>
    </div>
    <?php endif; ?>
</div>

<!-- 封禁弹窗 -->
<div id="ban-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('ban-modal').classList.remove('show')">✕</button>
        <div class="modal-banner" style="background:linear-gradient(135deg,#ef4444,#dc2626);"></div>
        <div class="modal-body">
            <h3 class="modal-title">🚫 封禁用户</h3>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
                封禁对象：<strong id="ban-uname" style="color:var(--text-primary);"></strong>
                · 封禁后将自动向用户发送通知邮件
            </p>
            <form onsubmit="submitBan(event)">
                <input type="hidden" id="ban-uid">
                <div class="form-group">
                    <label class="form-label">封禁时长</label>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
                        <label style="display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:8px;cursor:pointer;">
                            <input type="radio" name="duration" value="1" checked onchange="document.getElementById('custom-date-row').style.display='none'">
                            <span>1 天</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:8px;cursor:pointer;">
                            <input type="radio" name="duration" value="7" onchange="document.getElementById('custom-date-row').style.display='none'">
                            <span>7 天</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:8px;cursor:pointer;">
                            <input type="radio" name="duration" value="30" onchange="document.getElementById('custom-date-row').style.display='none'">
                            <span>30 天</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:8px;cursor:pointer;">
                            <input type="radio" name="duration" value="forever" onchange="document.getElementById('custom-date-row').style.display='none'">
                            <span>永久封禁</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:8px;cursor:pointer;grid-column:span 2;">
                            <input type="radio" name="duration" value="custom" onchange="document.getElementById('custom-date-row').style.display='block'">
                            <span>自定义时间</span>
                        </label>
                    </div>
                </div>
                <div class="form-group" id="custom-date-row" style="display:none;">
                    <label class="form-label">解封日期时间</label>
                    <input type="datetime-local" id="custom-date" class="form-input" min="<?php echo date('Y-m-d\TH:i', time()+3600);?>">
                </div>
                <div class="form-group">
                    <label class="form-label">封禁原因（可选）</label>
                    <input type="text" id="ban-reason" class="form-input" placeholder="例如：违反社区规则、恶意灌水等">
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('ban-modal').classList.remove('show')">取消</button>
                    <button type="submit" class="btn btn-danger">确认封禁</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 通知弹窗 -->
<div id="notify-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:520px;">
        <button class="modal-close" onclick="document.getElementById('notify-modal').classList.remove('show')">✕</button>
        <div class="modal-banner"></div>
        <div class="modal-body">
            <h3 class="modal-title">📧 发送通知邮件</h3>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
                收件人：<strong id="notify-uname" style="color:var(--text-primary);"></strong>
            </p>
            <form onsubmit="submitNotify(event)">
                <input type="hidden" id="notify-uid">
                <div class="form-group">
                    <label class="form-label">邮件标题</label>
                    <input type="text" id="notify-title" class="form-input" placeholder="例如：来自Jay影视的通知" required>
                </div>
                <div class="form-group">
                    <label class="form-label">邮件内容</label>
                    <textarea id="notify-content" class="form-textarea" style="min-height:160px;" placeholder="自定义邮件内容..." required></textarea>
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('notify-modal').classList.remove('show')">取消</button>
                    <button type="submit" class="btn btn-primary">📨 发送邮件</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openBan(uid, name) {
    document.getElementById('ban-uid').value = uid;
    document.getElementById('ban-uname').textContent = name;
    document.getElementById('ban-reason').value = '';
    document.getElementById('custom-date-row').style.display = 'none';
    document.getElementById('ban-modal').classList.add('show');
}
function submitBan(e) {
    e.preventDefault();
    var uid = parseInt(document.getElementById('ban-uid').value);
    var duration = document.querySelector('input[name="duration"]:checked').value;
    var custom = duration === 'custom' ? document.getElementById('custom-date').value : '';
    var reason = document.getElementById('ban-reason').value;
    if (duration === 'custom' && !custom) { alert('请选择解封时间'); return; }
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'ban', user_id:uid, duration:duration, custom_date:custom, reason:reason}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 700);
        else document.getElementById('ban-modal').classList.remove('show');
    });
}
function unbanUser(uid) {
    if (!confirm('确定要解封该用户吗？')) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'unban', user_id:uid}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 600);
    });
}
function deleteUser(uid) {
    if (!confirm('确定要删除该用户吗？此操作不可恢复！')) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'delete', user_id:uid}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 600);
    });
}
function openNotify(uid, name) {
    document.getElementById('notify-uid').value = uid;
    document.getElementById('notify-uname').textContent = name;
    document.getElementById('notify-title').value = '来自Jay影视的通知';
    document.getElementById('notify-content').value = '';
    document.getElementById('notify-modal').classList.add('show');
}
function submitNotify(e) {
    e.preventDefault();
    var uid = parseInt(document.getElementById('notify-uid').value);
    var title = document.getElementById('notify-title').value.trim();
    var content = document.getElementById('notify-content').value.trim();
    if (!title || !content) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'notify', user_id:uid, title:title, content:content}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) document.getElementById('notify-modal').classList.remove('show');
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
