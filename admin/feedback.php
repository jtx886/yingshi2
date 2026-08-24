<?php
/**
 * 反馈管理
 */
$activeMenu = 'feedback';
$pageSubTitle = '反馈管理';
require_once dirname(__FILE__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($action === 'reply') {
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        if (!$content) json_error('回复内容不能为空');
        db()->insert('feedback_replies', array(
            'feedback_id' => $id,
            'user_id' => intval($currentUser['id']),
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s')
        ));
        db()->update('feedbacks', array('status'=>'processing','updated_at'=>date('Y-m-d H:i:s')), 'id=?', array($id));
        json_success(null, '回复已发送');
    }

    if ($action === 'status') {
        $st = isset($_POST['status']) ? trim($_POST['status']) : 'pending';
        db()->update('feedbacks', array('status'=>$st,'updated_at'=>date('Y-m-d H:i:s')), 'id=?', array($id));
        json_success(null, '状态已更新');
    }

    if ($action === 'delete') {
        db()->delete('feedback_replies', 'feedback_id = ?', array($id));
        db()->delete('feedbacks', 'id = ?', array($id));
        json_success(null, '反馈已删除');
    }
    json_error('未知操作');
}

// 列表 or 详情
$detailId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($detailId > 0) {
    try {
        $fb = db()->fetchOne(
            "SELECT f.*, u.username as u_name, u.email as u_email, u.avatar as u_avatar FROM feedbacks f LEFT JOIN users u ON f.user_id=u.id WHERE f.id = ?",
            array($detailId)
        );
        $replies = db()->fetchAll(
            "SELECT r.*, u.username, u.avatar, u.is_admin FROM feedback_replies r LEFT JOIN users u ON r.user_id=u.id WHERE r.feedback_id = ? ORDER BY r.id ASC",
            array($detailId)
        );
    } catch (Exception $e) { $fb = null; $replies = array(); }

    if (!$fb) {
        echo '<div style="padding:60px;text-align:center;color:var(--text-muted);">反馈不存在。<a href="'.BASE_URL.'/admin/feedback.php" style="color:var(--primary-color);">返回列表</a></div>';
        require_once dirname(__FILE__) . '/includes/footer.php';
        exit;
    }

    $stArr = array('pending'=>array('待处理','status-pending'),'processing'=>array('处理中','status-active'),'resolved'=>array('已解决','status-resolved'),'closed'=>array('已关闭','status-closed'));
    $st = isset($stArr[$fb['status']]) ? $stArr[$fb['status']] : array($fb['status'],'status-active');
    $typeArr = array('bug'=>'🐛 程序错误','feature'=>'💡 功能建议','content'=>'🎬 内容问题','account'=>'👤 账号问题','other'=>'💬 其他');
    $type = isset($typeArr[$fb['type']]) ? $typeArr[$fb['type']] : $fb['type'];
    ?>

<div style="max-width:960px;margin:0 auto;">
    <a href="<?php echo BASE_URL;?>/admin/feedback.php" class="btn btn-outline btn-sm" style="margin-bottom:16px;">← 返回列表</a>

    <div class="dashboard-panel" style="margin-bottom:20px;">
        <div class="dashboard-panel-header" style="align-items:flex-start;">
            <div style="flex:1;">
                <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">
                    <span class="badge"><?php echo $type;?></span>
                    <span class="status-badge <?php echo $st[1];?>"><?php echo $st[0];?></span>
                </div>
                <h2 style="font-size:22px;margin:0 0 12px;"><?php echo e($fb['title']);?></h2>
                <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="avatar-sm">
                            <?php if(!empty($fb['u_avatar'])):?><img src="<?php echo e($fb['u_avatar']);?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: echo e(mb_substr($fb['u_name'],0,1,'UTF-8')); endif;?>
                        </div>
                        <div style="font-weight:500;"><?php echo e($fb['u_name']);?></div>
                        <div style="color:var(--text-muted);font-size:12px;"><?php echo e($fb['u_email']);?></div>
                    </div>
                    <div style="color:var(--text-muted);font-size:13px;">📅 <?php echo format_time($fb['created_at'],true);?></div>
                    <div style="color:var(--text-muted);font-size:13px;">👍 <?php echo intval($fb['likes']);?> 赞</div>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                <select class="form-input" style="width:120px;height:34px;padding:0 10px;" onchange="changeStatus(<?php echo intval($fb['id']);?>,this.value)">
                    <?php foreach($stArr as $k=>$v):?>
                    <option value="<?php echo $k;?>" <?php echo $fb['status']===$k?'selected':'';?>><?php echo $v[0];?></option>
                    <?php endforeach;?>
                </select>
                <button class="action-btn delete" onclick="deleteFb(<?php echo intval($fb['id']);?>)">🗑 删除</button>
            </div>
        </div>
        <div class="dashboard-panel-body">
            <div style="padding:20px;background:var(--bg-dark);border-radius:10px;border:1px solid var(--border-color);line-height:1.8;">
                <?php echo nl2br(e($fb['content']));?>
            </div>
        </div>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div class="dashboard-panel-title">💬 全部回复 (<?php echo count($replies);?>)</div>
        </div>
        <div class="dashboard-panel-body">
            <?php
            // 排序：先反馈者自己，然后管理员（is_admin=1），然后普通用户
            $sorted = array();
            $owner = intval($fb['user_id']);
            $op_replies = array();
            $admin_replies = array();
            $user_replies = array();
            foreach ($replies as $r) {
                if (intval($r['user_id']) === $owner) $op_replies[] = $r;
                elseif (intval($r['is_admin']) === 1) $admin_replies[] = $r;
                else $user_replies[] = $r;
            }
            $sorted = array_merge($op_replies, $admin_replies, $user_replies);
            foreach ($sorted as $r):
            ?>
            <div class="list-item" style="align-items:flex-start;">
                <div class="list-item-avatar">
                    <?php if(!empty($r['avatar'])):?>
                    <img src="<?php echo e($r['avatar']);?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: echo e(mb_substr($r['username'],0,1,'UTF-8')); endif;?>
                </div>
                <div class="list-item-content" style="margin-left:12px;">
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
                        <span style="font-weight:600;"><?php echo e($r['username']);?></span>
                        <?php if(intval($r['user_id'])===$owner):?><span class="status-badge status-active" style="font-size:10px;padding:2px 6px;">楼主</span><?php endif;?>
                        <?php if(intval($r['is_admin'])===1):?><span class="admin-logo-badge" style="margin-left:4px;">开发者</span><?php endif;?>
                        <span style="color:var(--text-muted);font-size:11px;"><?php echo format_time($r['created_at'],true);?></span>
                    </div>
                    <div style="line-height:1.7;color:var(--text-secondary);"><?php echo nl2br(e($r['content']));?></div>
                </div>
            </div>
            <?php endforeach;?>

            <?php if(empty($sorted)):?>
            <div class="empty-state"><div class="empty-state-icon">💭</div><div>暂无回复，写下第一条回复吧</div></div>
            <?php endif;?>

            <!-- 回复框 -->
            <div style="margin-top:16px;padding:16px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:12px;">
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
                    <div class="avatar-sm">
                        <?php if(!empty($currentUser['avatar'])):?>
                        <img src="<?php echo e($currentUser['avatar']);?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: echo e(mb_substr($currentUser['username'],0,1,'UTF-8')); endif;?>
                    </div>
                    <div style="font-weight:600;">
                        <?php echo e($currentUser['username']);?>
                        <span class="admin-logo-badge" style="margin-left:4px;">开发者</span>
                    </div>
                    <div style="color:var(--text-muted);font-size:12px;">管理员将优先展示</div>
                </div>
                <form onsubmit="submitReply(event, <?php echo intval($fb['id']);?>)">
                    <textarea id="reply-content" class="form-textarea" style="min-height:100px;" placeholder="写下你的回复...（支持换行）" required></textarea>
                    <div style="display:flex;justify-content:flex-end;margin-top:10px;">
                        <button type="submit" class="btn btn-primary btn-sm">📨 发送回复</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function submitReply(e, id) {
    e.preventDefault();
    var content = document.getElementById('reply-content').value.trim();
    if (!content) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'reply', id:id, content:content}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 500);
    });
}
function changeStatus(id, st) {
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'status', id:id, status:st}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
    });
}
function deleteFb(id) {
    if (!confirm('确定删除该反馈？所有回复也将一并删除！')) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'delete', id:id}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.href='<?php echo BASE_URL;?>/admin/feedback.php', 500);
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; exit; }

/* ==================== 列表页面 ==================== */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
list($page,$perPage,$offset) = get_pagination_params(20);
$where = '1=1'; $params = array();
if ($search) { $where .= ' AND (f.title LIKE ? OR f.content LIKE ? OR u.username LIKE ?)'; $params[]="%{$search}%"; $params[]="%{$search}%"; $params[]="%{$search}%"; }
if ($status) { $where .= ' AND f.status = ' . db()->quote($status); }
if ($type) { $where .= ' AND f.type = ' . db()->quote($type); }

try {
    $total = db()->fetchOne("SELECT COUNT(*) c FROM feedbacks f LEFT JOIN users u ON f.user_id=u.id WHERE {$where}", $params);
    $totalCnt = intval($total['c']); $totalPages = ceil($totalCnt/$perPage);
    $list = db()->fetchAll(
        "SELECT f.*, u.username as u_name FROM feedbacks f LEFT JOIN users u ON f.user_id=u.id WHERE {$where} ORDER BY f.created_at DESC LIMIT {$offset}, {$perPage}",
        $params
    );
} catch(Exception $e){ $list=array(); $totalCnt=0; $totalPages=1; }
$stArr = array('pending'=>array('待处理','status-pending'),'processing'=>array('处理中','status-active'),'resolved'=>array('已解决','status-resolved'),'closed'=>array('已关闭','status-closed'));
$typeArr = array('bug'=>'🐛 错误','feature'=>'💡 建议','content'=>'🎬 内容','account'=>'👤 账号','other'=>'💬 其他');
?>

<div class="admin-table-wrapper">
    <div class="admin-table-header">
        <div class="admin-table-title">💬 反馈管理 (共<?php echo $totalCnt;?>条)</div>
        <form method="GET" style="display:flex;gap:10px;">
            <div class="admin-table-search">
                <input type="text" name="search" placeholder="搜索标题/内容/用户" value="<?php echo e($search);?>">
            </div>
            <select name="status" class="form-input" style="width:110px;height:36px;padding:0 10px;">
                <option value="">全部状态</option>
                <?php foreach($stArr as $k=>$v):?>
                <option value="<?php echo $k;?>" <?php echo $status===$k?'selected':'';?>><?php echo $v[0];?></option>
                <?php endforeach;?>
            </select>
            <select name="type" class="form-input" style="width:110px;height:36px;padding:0 10px;">
                <option value="">全部类型</option>
                <?php foreach($typeArr as $k=>$v):?>
                <option value="<?php echo $k;?>" <?php echo $type===$k?'selected':'';?>><?php echo $v;?></option>
                <?php endforeach;?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">搜索</button>
            <a href="<?php echo BASE_URL;?>/admin/feedback.php" class="btn btn-outline btn-sm">重置</a>
        </form>
    </div>

    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>标题</th>
            <th>类型</th>
            <th>提交用户</th>
            <th>点赞</th>
            <th>状态</th>
            <th>提交时间</th>
            <th style="width:160px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($list as $f):
            $s = isset($stArr[$f['status']]) ? $stArr[$f['status']] : array($f['status'],'status-active');
            $t = isset($typeArr[$f['type']]) ? $typeArr[$f['type']] : $f['type'];
        ?>
        <tr onclick="location.href='<?php echo BASE_URL;?>/admin/feedback.php?id=<?php echo intval($f['id']);?>'" style="cursor:pointer;">
            <td>#<?php echo intval($f['id']);?></td>
            <td style="font-weight:500;">
                <?php echo e(mb_substr($f['title'],0,36,'UTF-8'));?><?php echo mb_strlen($f['title'],'UTF-8')>36?'...':'';?>
            </td>
            <td><span class="badge" style="background:var(--primary-bg);color:var(--primary-color);"><?php echo $t;?></span></td>
            <td style="color:var(--text-secondary);font-size:13px;"><?php echo $f['u_name']?e($f['u_name']):'<span style="color:var(--text-muted);">匿名</span>';?></td>
            <td style="color:var(--text-muted);font-size:13px;">👍 <?php echo intval($f['likes']);?></td>
            <td><span class="status-badge <?php echo $s[1];?>"><?php echo $s[0];?></span></td>
            <td style="color:var(--text-muted);font-size:12px;"><?php echo format_time($f['created_at'],true);?></td>
            <td>
                <div class="action-buttons" onclick="event.stopPropagation()">
                    <button class="action-btn reply" onclick="event.stopPropagation();location.href='<?php echo BASE_URL;?>/admin/feedback.php?id=<?php echo intval($f['id']);?>'">👁 查看</button>
                    <button class="action-btn delete" onclick="event.stopPropagation();deleteFb(<?php echo intval($f['id']);?>)">🗑</button>
                </div>
            </td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($list)):?>
        <tr><td colspan="8" style="text-align:center;padding:60px;color:var(--text-muted);">📭 暂无反馈</td></tr>
        <?php endif;?>
        </tbody>
    </table>
    <?php if($totalPages>1):?>
    <div class="pagination">
        <?php
        $qs = $_GET; unset($qs['page']); $qs = http_build_query($qs);
        $base = '?'.($qs?$qs.'&':'').'page=';
        $start=max(1,$page-2);$end=min($totalPages,$start+4);if($end-$start<4)$start=max(1,$end-4);?>
        <button <?php echo $page<=1?'disabled':'';?> onclick="location.href='<?php echo $base.($page-1);?>'">‹ 上一页</button>
        <?php for($i=$start;$i<=$end;$i++):?>
        <button class="<?php echo $i===$page?'active':'';?>" onclick="location.href='<?php echo $base.$i;?>'"><?php echo $i;?></button>
        <?php endfor;?>
        <button <?php echo $page>=$totalPages?'disabled':'';?> onclick="location.href='<?php echo $base.($page+1);?>'">下一页 ›</button>
    </div>
    <?php endif;?>
</div>
<script>
function deleteFb(id) {
    if(!confirm('确定删除该反馈？所有回复一并删除！'))return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>',{action:'delete',id:id},function(res){
        adminToast(res.message,res.code===200?'success':'error');
        if(res.code===200)setTimeout(()=>location.reload(),500);
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
