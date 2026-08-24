<?php
/**
 * 公告管理
 */
$activeMenu = 'announcements';
$pageSubTitle = '公告管理';
require_once dirname(__FILE__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($action === 'add' || $action === 'edit') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $importance = isset($_POST['importance']) ? trim($_POST['importance']) : 'normal';
        $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 1;
        if (!$title || !$content) json_error('标题和内容必填');

        $data = array(
            'title' => $title, 'content' => $content,
            'importance' => $importance, 'enabled' => $enabled,
            'updated_at' => date('Y-m-d H:i:s')
        );
        if ($action === 'add') {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = intval($currentUser['id']);
            db()->insert('announcements', $data);
            json_success(null, '公告已发布');
        } else {
            db()->update('announcements', $data, 'id = ?', array($id));
            json_success(null, '公告已更新');
        }
    }

    if ($action === 'delete') {
        db()->delete('announcements', 'id = ?', array($id));
        json_success(null, '公告已删除');
    }

    if ($action === 'toggle') {
        $a = db()->fetchOne("SELECT enabled FROM announcements WHERE id = ?", array($id));
        $new = intval($a['enabled'])===1?0:1;
        db()->update('announcements', array('enabled'=>$new), 'id = ?', array($id));
        json_success(array('enabled'=>$new), '状态已更新');
    }

    json_error('未知操作');
}

list($page,$perPage,$offset) = get_pagination_params(20);
try {
    $total = db()->fetchOne("SELECT COUNT(*) c FROM announcements");
    $totalCnt = intval($total['c']); $totalPages = ceil($totalCnt/$perPage);
    $list = db()->fetchAll("SELECT a.*, u.username as creator FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.id DESC LIMIT {$offset}, {$perPage}");
} catch(Exception $e){ $list=array(); $totalCnt=0; $totalPages=1; }
?>

<div class="admin-table-wrapper">
    <div class="admin-table-header">
        <div class="admin-table-title">📢 公告管理 (共<?php echo $totalCnt;?>条)</div>
        <div style="display:flex;gap:10px;">
            <span class="hint-tag" style="background:var(--primary-bg);color:var(--primary-color);">💡 弹窗显示在首页，用户勾选"不再提示"后不再出现该公告，新公告仍然显示</span>
            <button class="btn btn-primary btn-sm" onclick="openAnnModal()">➕ 发布公告</button>
        </div>
    </div>

    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>标题</th>
            <th>重要等级</th>
            <th>内容预览</th>
            <th>状态</th>
            <th>创建人</th>
            <th>更新时间</th>
            <th style="width:230px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($list as $a):
            $impArr = array(
                'high'=>array('🚨 紧急','status-banned'),
                'normal'=>array('📣 普通','status-active'),
                'low'=>array('ℹ️ 轻微','status-resolved')
            );
            $imp = isset($impArr[$a['importance']]) ? $impArr[$a['importance']] : array($a['importance'],'status-active');
        ?>
        <tr>
            <td>#<?php echo intval($a['id']);?></td>
            <td style="font-weight:600;"><?php echo e($a['title']);?></td>
            <td><span class="status-badge <?php echo $imp[1];?>"><?php echo $imp[0];?></span></td>
            <td style="color:var(--text-secondary);font-size:12px;max-width:280px;">
                <?php echo e(mb_substr(strip_tags($a['content']),0,60,'UTF-8'));?><?php echo mb_strlen(strip_tags($a['content']),'UTF-8')>60?'...':'';?>
            </td>
            <td>
                <label class="switch">
                    <input type="checkbox" <?php echo intval($a['enabled'])===1?'checked':'';?> onchange="toggleAnn(<?php echo intval($a['id']);?>,this)">
                    <span class="switch-slider"></span>
                </label>
            </td>
            <td style="color:var(--text-muted);font-size:12px;">
                <?php echo $a['creator']?e($a['creator']):'系统';?>
            </td>
            <td style="color:var(--text-muted);font-size:12px;"><?php echo format_time($a['updated_at'],true);?></td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn edit" onclick="openAnnModal(<?php echo intval($a['id']);?>,<?php echo htmlspecialchars(json_encode($a),ENT_QUOTES,'UTF-8');?>)">✏️ 编辑</button>
                    <button class="action-btn delete" onclick="deleteAnn(<?php echo intval($a['id']);?>)">🗑 删除</button>
                </div>
            </td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($list)):?>
        <tr><td colspan="8" style="text-align:center;padding:60px;color:var(--text-muted);">📭 暂无公告，点击右上角发布</td></tr>
        <?php endif;?>
        </tbody>
    </table>
    <?php if($totalPages>1):?>
    <div class="pagination">
        <?php $base='?page=';$start=max(1,$page-2);$end=min($totalPages,$start+4);if($end-$start<4)$start=max(1,$end-4);?>
        <button <?php echo $page<=1?'disabled':'';?> onclick="location.href='<?php echo $base.($page-1);?>'">‹ 上一页</button>
        <?php for($i=$start;$i<=$end;$i++):?>
        <button class="<?php echo $i===$page?'active':'';?>" onclick="location.href='<?php echo $base.$i;?>'"><?php echo $i;?></button>
        <?php endfor;?>
        <button <?php echo $page>=$totalPages?'disabled':'';?> onclick="location.href='<?php echo $base.($page+1);?>'">下一页 ›</button>
    </div>
    <?php endif;?>
</div>

<!-- 公告弹窗 -->
<div id="ann-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:640px;">
        <button class="modal-close" onclick="document.getElementById('ann-modal').classList.remove('show')">✕</button>
        <div class="modal-banner"></div>
        <div class="modal-body">
            <h3 class="modal-title" id="ann-modal-title">📢 发布新公告</h3>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">公告将以弹窗形式在首页展示，除非用户勾选"不再提示"。</p>
            <form onsubmit="submitAnn(event)">
                <input type="hidden" id="ann-id">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">公告标题 <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="ann-title" class="form-input" placeholder="例如：欢迎使用Jay影视" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">重要等级</label>
                        <select id="ann-importance" class="form-input">
                            <option value="low">ℹ️ 轻微</option>
                            <option value="normal" selected>📣 普通</option>
                            <option value="high">🚨 紧急</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">公告内容 <span style="color:#ef4444;">*</span> (支持HTML)</label>
                    <textarea id="ann-content" class="form-textarea" style="min-height:220px;" placeholder="支持HTML标签，例如：<b>加粗</b>、<br>换行、<a href='#'>链接</a>" required></textarea>
                </div>
                <div style="display:flex;gap:16px;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="ann-enabled" checked style="width:16px;height:16px;">
                        <span>立即发布</span>
                    </label>
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('ann-modal').classList.remove('show')">取消</button>
                    <button type="submit" class="btn btn-primary">💾 保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAnnModal(id,a){
    document.getElementById('ann-id').value = id||0;
    if (id&&a){
        document.getElementById('ann-modal-title').textContent='✏️ 编辑公告';
        document.getElementById('ann-title').value = a.title;
        document.getElementById('ann-importance').value = a.importance;
        document.getElementById('ann-content').value = a.content;
        document.getElementById('ann-enabled').checked = parseInt(a.enabled)===1;
    } else {
        document.getElementById('ann-modal-title').textContent='📢 发布新公告';
        document.getElementById('ann-title').value='';
        document.getElementById('ann-importance').value='normal';
        document.getElementById('ann-content').value='';
        document.getElementById('ann-enabled').checked=true;
    }
    document.getElementById('ann-modal').classList.add('show');
}
function submitAnn(e){
    e.preventDefault();
    var id = parseInt(document.getElementById('ann-id').value);
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>',{
        action:id>0?'edit':'add', id:id,
        title:document.getElementById('ann-title').value.trim(),
        importance:document.getElementById('ann-importance').value,
        content:document.getElementById('ann-content').value,
        enabled:document.getElementById('ann-enabled').checked?1:0
    }, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(),500);
    });
}
function toggleAnn(id,el){
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>',{action:'toggle',id:id},function(res){
        if (res.code!==200){el.checked=!el.checked;adminToast(res.message,'error');}
    });
}
function deleteAnn(id){
    if(!confirm('确定删除此公告？'))return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>',{action:'delete',id:id},function(res){
        adminToast(res.message,res.code===200?'success':'error');
        if(res.code===200)setTimeout(()=>location.reload(),500);
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
