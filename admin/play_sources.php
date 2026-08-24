<?php
/**
 * 播放源管理
 */
$activeMenu = 'sources';
$pageSubTitle = '播放源管理';
require_once dirname(__FILE__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($action === 'add' || $action === 'edit') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $api_url = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
        $parse_url = isset($_POST['parse_url']) ? trim($_POST['parse_url']) : get_setting('player_parse_url','https://svip.ffzyplay.com/?url=');
        $type = isset($_POST['type']) ? trim($_POST['type']) : 'vod';
        $is_default = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;
        $sort = isset($_POST['sort']) ? intval($_POST['sort']) : 0;
        $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 1;
        if (!$name || !$api_url) json_error('名称和API地址必填');

        $data = array(
            'name' => $name, 'api_url' => $api_url, 'parse_url' => $parse_url,
            'type' => $type, 'sort' => $sort, 'enabled' => $enabled,
            'updated_at' => date('Y-m-d H:i:s')
        );

        if ($is_default === 1) {
            db()->raw("UPDATE play_sources SET is_default = 0");
            $data['is_default'] = 1;
        }

        if ($action === 'add') {
            $data['created_at'] = date('Y-m-d H:i:s');
            db()->insert('play_sources', $data);
            json_success(null, '播放源添加成功');
        } else {
            db()->update('play_sources', $data, 'id = ?', array($id));
            json_success(null, '播放源更新成功');
        }
    }

    if ($action === 'toggle') {
        $src = db()->fetchOne("SELECT enabled FROM play_sources WHERE id = ?", array($id));
        $new = intval($src['enabled']) === 1 ? 0 : 1;
        db()->update('play_sources', array('enabled'=>$new), 'id = ?', array($id));
        json_success(array('enabled'=>$new), '状态已更新');
    }

    if ($action === 'default') {
        db()->raw("UPDATE play_sources SET is_default = 0");
        db()->update('play_sources', array('is_default'=>1), 'id = ?', array($id));
        json_success(null, '已设为默认播放源');
    }

    if ($action === 'delete') {
        db()->delete('play_sources', 'id = ?', array($id));
        json_success(null, '播放源已删除');
    }

    json_error('未知操作');
}

list($page, $perPage, $offset) = get_pagination_params(30);
try {
    $total = db()->fetchOne("SELECT COUNT(*) c FROM play_sources");
    $totalCnt = intval($total['c']); $totalPages = ceil($totalCnt / $perPage);
    $sources = db()->fetchAll("SELECT * FROM play_sources ORDER BY is_default DESC, sort ASC, id ASC LIMIT {$offset}, {$perPage}");
} catch (Exception $e) { $sources = array(); $totalCnt = 0; $totalPages = 1; }
?>

<div class="admin-table-wrapper">
    <div class="admin-table-header">
        <div class="admin-table-title">📡 播放源管理 (共<?php echo $totalCnt;?>个)</div>
        <div style="display:flex;gap:10px;">
            <a href="<?php echo BASE_URL;?>/admin/play_sources.php" class="btn btn-outline btn-sm">🔄 刷新</a>
            <button class="btn btn-primary btn-sm" onclick="openSourceModal()">➕ 添加播放源</button>
        </div>
    </div>

    <table class="admin-table">
        <thead>
        <tr>
            <th style="width:60px;">默认</th>
            <th>名称</th>
            <th>类型</th>
            <th>API 地址</th>
            <th>解析播放器</th>
            <th>排序</th>
            <th>状态</th>
            <th>更新时间</th>
            <th style="width:230px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($sources as $s):?>
        <tr>
            <td>
                <?php if(intval($s['is_default'])===1):?>
                <span title="默认播放源" style="display:inline-block;width:24px;height:24px;background:var(--primary-color);color:white;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;">★</span>
                <?php else:?>
                <button class="action-btn edit" style="font-size:13px;padding:4px 10px;" onclick="setDefault(<?php echo intval($s['id']);?>)">设为默认</button>
                <?php endif;?>
            </td>
            <td>
                <div style="font-weight:600;"><?php echo e($s['name']);?></div>
                <div style="font-size:11px;color:var(--text-muted);">ID: <?php echo intval($s['id']);?></div>
            </td>
            <td><span class="status-badge status-active"><?php echo e($s['type']);?></span></td>
            <td style="color:var(--text-secondary);font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo e($s['api_url']);?>"><?php echo e($s['api_url']);?></td>
            <td style="color:var(--text-secondary);font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo e($s['parse_url']);?>"><?php echo e($s['parse_url']);?></td>
            <td><?php echo intval($s['sort']);?></td>
            <td>
                <label class="switch">
                    <input type="checkbox" <?php echo intval($s['enabled'])===1?'checked':'';?> onchange="toggleSource(<?php echo intval($s['id']);?>, this)">
                    <span class="switch-slider"></span>
                </label>
            </td>
            <td style="color:var(--text-muted);font-size:12px;"><?php echo format_time($s['updated_at'],true);?></td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn edit" onclick="openSourceModal(<?php echo intval($s['id']);?>, <?php echo htmlspecialchars(json_encode($s),ENT_QUOTES,'UTF-8');?>)">✏️ 编辑</button>
                    <button class="action-btn delete" onclick="deleteSource(<?php echo intval($s['id']);?>)">🗑 删除</button>
                </div>
            </td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($sources)):?>
        <tr><td colspan="9" style="text-align:center;padding:60px;color:var(--text-muted);">📭 暂无播放源，点击右上角添加</td></tr>
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

<!-- 播放源弹窗 -->
<div id="source-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:600px;">
        <button class="modal-close" onclick="document.getElementById('source-modal').classList.remove('show')">✕</button>
        <div class="modal-banner"></div>
        <div class="modal-body">
            <h3 class="modal-title" id="source-modal-title">➕ 添加播放源</h3>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">播放源API地址一般返回JSON格式，解析播放器是嵌入播放页的URL前缀（即播放链接附加到该URL后）。</p>
            <form onsubmit="submitSource(event)">
                <input type="hidden" id="src-id">
                <div class="form-group">
                    <label class="form-label">播放源名称 <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="src-name" class="form-input" placeholder="例如：云播资源" required>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">类型</label>
                        <select id="src-type" class="form-input">
                            <option value="vod">影视 VOD</option>
                            <option value="live">直播</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">排序（小在前）</label>
                        <input type="number" id="src-sort" class="form-input" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">API 地址 <span style="color:#ef4444;">*</span></label>
                    <input type="url" id="src-api" class="form-input" placeholder="例如：https://api.example.com/api.php" required>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        推荐默认：<code style="background:var(--bg-dark);padding:2px 6px;border-radius:4px;">https://api.yyzy-tv.vip/inc/apijson.php</code>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">解析播放器 URL</label>
                    <input type="url" id="src-parse" class="form-input" placeholder="例如：https://svip.ffzyplay.com/?url=">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        推荐默认：<code style="background:var(--bg-dark);padding:2px 6px;border-radius:4px;">https://svip.ffzyplay.com/?url=</code>
                        · 实际播放链接 = 解析播放器 URL + 真实播放链接
                    </div>
                </div>
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="src-enabled" checked style="width:16px;height:16px;">
                        <span>启用</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="src-default" style="width:16px;height:16px;">
                        <span>设为默认播放源</span>
                    </label>
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('source-modal').classList.remove('show')">取消</button>
                    <button type="submit" class="btn btn-primary" id="src-submit-btn">💾 保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSourceModal(id, s) {
    document.getElementById('src-id').value = id || 0;
    if (id && s) {
        document.getElementById('source-modal-title').textContent = '✏️ 编辑播放源';
        document.getElementById('src-name').value = s.name;
        document.getElementById('src-type').value = s.type;
        document.getElementById('src-sort').value = s.sort;
        document.getElementById('src-api').value = s.api_url;
        document.getElementById('src-parse').value = s.parse_url;
        document.getElementById('src-enabled').checked = parseInt(s.enabled)===1;
        document.getElementById('src-default').checked = parseInt(s.is_default)===1;
    } else {
        document.getElementById('source-modal-title').textContent = '➕ 添加播放源';
        document.getElementById('src-name').value = '';
        document.getElementById('src-type').value = 'vod';
        document.getElementById('src-sort').value = '0';
        document.getElementById('src-api').value = 'https://api.yyzy-tv.vip/inc/apijson.php';
        document.getElementById('src-parse').value = 'https://svip.ffzyplay.com/?url=';
        document.getElementById('src-enabled').checked = true;
        document.getElementById('src-default').checked = false;
    }
    document.getElementById('source-modal').classList.add('show');
}
function submitSource(e) {
    e.preventDefault();
    var id = parseInt(document.getElementById('src-id').value);
    var data = {
        action: id>0?'edit':'add',
        id: id,
        name: document.getElementById('src-name').value.trim(),
        type: document.getElementById('src-type').value,
        sort: parseInt(document.getElementById('src-sort').value)||0,
        api_url: document.getElementById('src-api').value.trim(),
        parse_url: document.getElementById('src-parse').value.trim(),
        enabled: document.getElementById('src-enabled').checked?1:0,
        is_default: document.getElementById('src-default').checked?1:0
    };
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', data, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 600);
    });
}
function toggleSource(id, el) {
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'toggle', id:id}, function(res){
        if (res.code!==200) { el.checked = !el.checked; adminToast(res.message,'error'); }
    });
}
function setDefault(id) {
    if (!confirm('确定设为默认播放源吗？原默认将被取消。')) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'default', id:id}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 500);
    });
}
function deleteSource(id) {
    if (!confirm('确定删除该播放源？')) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'delete', id:id}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 500);
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
