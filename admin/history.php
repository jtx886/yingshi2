<?php
/**
 * 独立模块：观看历史
 */
$activeMenu = 'history';
$pageSubTitle = '观看历史模块';
require_once dirname(__FILE__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($action === 'delete') {
        db()->delete('watch_history', 'id = ?', array($id));
        json_success(null, '记录已删除');
    }
    if ($action === 'clear_user') {
        $uid = intval($_POST['user_id']);
        db()->delete('watch_history', 'user_id = ?', array($uid));
        json_success(null, '已清空该用户的观看历史');
    }
    if ($action === 'clear_all') {
        db()->raw("TRUNCATE TABLE watch_history");
        json_success(null, '已清空全部观看历史');
    }
    json_error('未知操作');
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
list($page,$perPage,$offset) = get_pagination_params(30);

// 用户列表
try {
    $userOptions = db()->fetchAll("SELECT id, username FROM users ORDER BY id ASC LIMIT 500");
} catch(Exception $e){ $userOptions = array(); }

$where = '1=1'; $params = array();
if ($userId > 0) { $where .= ' AND w.user_id = ' . $userId; }
if ($search) { $where .= ' AND (w.movie_name LIKE ? OR u.username LIKE ?)'; $params[]="%{$search}%"; $params[]="%{$search}%"; }

try {
    $total = db()->fetchOne("SELECT COUNT(*) c FROM watch_history w LEFT JOIN users u ON w.user_id = u.id WHERE {$where}", $params);
    $totalCnt = intval($total['c']); $totalPages = ceil($totalCnt/$perPage);
    $totalSeconds = db()->fetchOne("SELECT COALESCE(SUM(w.watch_seconds),0) s FROM watch_history w LEFT JOIN users u ON w.user_id=u.id WHERE {$where}", $params);
    $totalSec = intval($totalSeconds['s']);
    $list = db()->fetchAll(
        "SELECT w.*, u.username, u.email FROM watch_history w LEFT JOIN users u ON w.user_id=u.id WHERE {$where} ORDER BY w.last_watch_at DESC LIMIT {$offset}, {$perPage}",
        $params
    );
} catch(Exception $e){ $list=array(); $totalCnt=0; $totalPages=1; $totalSec=0; }

// 总时长格式化
function formatDuration($s){
    if ($s<60) return $s.'秒';
    if ($s<3600) return floor($s/60).'分'.($s%60).'秒';
    $h = floor($s/3600); $m = floor(($s%3600)/60);
    if ($h<24) return $h.'时'.$m.'分';
    $d = floor($h/24); return $d.'天'.($h%24).'时';
}
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card users">
        <div class="stat-icon">📺</div>
        <div class="stat-label">总观看记录</div>
        <div class="stat-value"><?php echo $totalCnt;?></div>
    </div>
    <div class="stat-card feedback">
        <div class="stat-icon">⏱️</div>
        <div class="stat-label">累计观看时长</div>
        <div class="stat-value"><?php echo formatDuration($totalSec);?></div>
    </div>
    <div class="stat-card views">
        <div class="stat-icon">👤</div>
        <div class="stat-label">筛选用户</div>
        <div class="stat-value"><?php echo $userId>0?e(get_user_name($userId)):'全部';?></div>
    </div>
    <div class="stat-card favorites">
        <div class="stat-icon">📄</div>
        <div class="stat-label">当前页</div>
        <div class="stat-value"><?php echo $page;?>/<?php echo $totalPages;?></div>
    </div>
</div>

<div class="admin-table-wrapper">
    <div class="admin-table-header">
        <div class="admin-table-title">📺 观看历史</div>
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
            <select name="user_id" class="form-input" style="width:160px;height:36px;padding:0 10px;" onchange="this.form.submit()">
                <option value="">全部用户</option>
                <?php foreach($userOptions as $uo):?>
                <option value="<?php echo intval($uo['id']);?>" <?php echo $userId===intval($uo['id'])?'selected':'';?>><?php echo e($uo['username']);?></option>
                <?php endforeach;?>
            </select>
            <div class="admin-table-search">
                <input type="text" name="search" placeholder="搜索影视名/用户名" value="<?php echo e($search);?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">搜索</button>
            <a href="<?php echo BASE_URL;?>/admin/history.php" class="btn btn-outline btn-sm">重置</a>
            <?php if($userId>0):?>
            <button type="button" class="btn btn-outline btn-sm" style="border-color:#f59e0b;color:#f59e0b;" onclick="clearUser(<?php echo $userId;?>)">🗑 清空该用户</button>
            <?php endif;?>
            <button type="button" class="btn btn-danger btn-sm" onclick="clearAll()">⚠️ 清空全部</button>
        </form>
    </div>

    <table class="admin-table">
        <thead>
        <tr>
            <th style="width:70px;">封面</th>
            <th>影视</th>
            <th>用户</th>
            <th>季/集</th>
            <th>观看时长</th>
            <th>最后观看</th>
            <th style="width:110px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($list as $h):?>
        <tr>
            <td>
                <div style="width:52px;height:72px;border-radius:6px;overflow:hidden;background:var(--bg-dark);position:relative;">
                    <?php if(!empty($h['poster'])):?>
                    <img src="<?php echo e($h['poster']);?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;font-size:22px;color:var(--text-muted);\\'>🎬</div>'">
                    <?php else:?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:22px;color:var(--text-muted);">🎬</div>
                    <?php endif;?>
                </div>
            </td>
            <td>
                <div style="font-weight:500;"><?php echo e($h['movie_name']);?></div>
                <div style="font-size:11px;color:var(--text-muted);">类型：<?php echo e($h['movie_type']);?> · TMDB_ID: <?php echo intval($h['tmdb_id']);?></div>
            </td>
            <td>
                <div><?php echo $h['username']?e($h['username']):'<span style="color:var(--text-muted);">—</span>';?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?php echo $h['email']?e($h['email']):'';?></div>
            </td>
            <td>
                <span class="badge" style="background:var(--primary-bg);color:var(--primary-color);">S<?php echo intval($h['season']);?> EP<?php echo intval($h['episode']);?></span>
            </td>
            <td>
                <div style="color:var(--text-secondary);font-weight:500;"><?php echo formatDuration(intval($h['watch_seconds']));?></div>
                <div style="font-size:11px;color:var(--text-muted);">共<?php echo intval($h['watch_seconds']);?>秒</div>
            </td>
            <td style="color:var(--text-muted);font-size:12px;"><?php echo format_time($h['last_watch_at'],true);?></td>
            <td>
                <div class="action-buttons">
                    <?php if(!empty($h['tmdb_id'])):?>
                    <a class="action-btn edit" target="_blank" href="<?php echo BASE_URL;?>/detail.php?id=<?php echo intval($h['tmdb_id']);?>&type=<?php echo e($h['movie_type']);?>&s=<?php echo intval($h['season']);?>&ep=<?php echo intval($h['episode']);?>">👁</a>
                    <?php endif;?>
                    <button class="action-btn delete" onclick="delRecord(<?php echo intval($h['id']);?>)">🗑</button>
                </div>
            </td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($list)):?>
        <tr><td colspan="7" style="text-align:center;padding:60px;color:var(--text-muted);">📭 暂无观看历史</td></tr>
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
function delRecord(id){
    if(!confirm('删除这条观看记录？'))return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>',{action:'delete',id:id},function(r){
        adminToast(r.message,r.code===200?'success':'error');
        if(r.code===200)setTimeout(()=>location.reload(),400);
    });
}
function clearUser(uid){
    if(!confirm('确定清空该用户的全部观看历史？此操作不可恢复！'))return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>',{action:'clear_user',user_id:uid},function(r){
        adminToast(r.message,r.code===200?'success':'error');
        if(r.code===200)setTimeout(()=>location.reload(),500);
    });
}
function clearAll(){
    if(!confirm('⚠️ 确定清空全站所有用户的观看历史？此操作无法撤销！'))return;
    if(!confirm('⚠️ 再次确认：真的要清空全部观看历史吗？'))return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>',{action:'clear_all'},function(r){
        adminToast(r.message,r.code===200?'success':'error');
        if(r.code===200)setTimeout(()=>location.reload(),500);
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
