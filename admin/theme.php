<?php
/**
 * 主题颜色自定义
 */
$activeMenu = 'theme';
$pageSubTitle = '主题颜色设置';
require_once dirname(__FILE__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'save') {
        $color = isset($_POST['color']) ? trim($_POST['color']) : '';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) json_error('颜色格式不正确');
        update_setting('theme_color', $color);
        json_success(array('color'=>$color), '主题颜色已保存！刷新全站生效');
    }
    if ($action === 'reset') {
        update_setting('theme_color', '#7c3aed');
        json_success(array('color'=>'#7c3aed'), '已重置默认主题');
    }
    json_error('未知操作');
}

$currentColor = get_theme_color();

// 预设色板
$presets = array(
    '#7c3aed','#ef4444','#f59e0b','#10b981','#3b82f6',
    '#8b5cf6','#ec4899','#06b6d4','#14b8a6','#f97316',
    '#84cc16','#6366f1','#a855f7','#22c55e','#0ea5e9',
    '#dc2626','#d946ef','#eab308','#0891b2','#4f46e5'
);
?>

<div class="theme-container">
    <div class="theme-card">
        <div class="theme-card-header">
            <div>
                <div class="theme-card-title">🎨 网站主题颜色</div>
                <div class="theme-card-subtitle">选择一个你喜欢的颜色，全站自动适配。颜色会应用到按钮、图标、链接、高亮等主要元素。</div>
            </div>
            <div class="theme-current-preview" style="background:<?php echo e($currentColor);?>;">
                <span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:24px;font-weight:700;">J</span>
            </div>
        </div>

        <div class="theme-preview-row">
            <div class="theme-preview-block">
                <div class="theme-preview-label">按钮效果</div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button class="btn btn-primary">主要按钮</button>
                    <button class="btn btn-primary btn-sm">小按钮</button>
                    <button class="btn btn-outline">轮廓按钮</button>
                    <span class="badge">徽章</span>
                </div>
            </div>
            <div class="theme-preview-block">
                <div class="theme-preview-label">标签/状态效果</div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <span class="status-badge status-active">激活</span>
                    <span class="status-badge status-banned">禁止</span>
                    <span class="status-badge status-resolved">已解决</span>
                    <span class="menu-badge">NEW</span>
                    <span class="admin-logo-badge">开发者</span>
                </div>
            </div>
        </div>

        <div class="theme-section">
            <div class="theme-section-title">💠 预设颜色</div>
            <div class="theme-presets">
                <?php foreach($presets as $c):?>
                <button class="theme-preset-btn" style="background:<?php echo $c;?>" onclick="selectColor('<?php echo $c;?>')" title="<?php echo $c;?>">
                    <?php if(strtolower($c)===strtolower($currentColor)):?>
                    <span style="color:white;font-weight:700;">✓</span>
                    <?php endif;?>
                </button>
                <?php endforeach;?>
            </div>
        </div>

        <div class="theme-section">
            <div class="theme-section-title">🎯 自定义颜色</div>
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <div class="color-picker-wrapper">
                    <input type="color" id="colorPicker" value="<?php echo e($currentColor);?>" onchange="selectColor(this.value)">
                </div>
                <div style="position:relative;">
                    <input type="text" id="colorText" class="form-input" style="width:160px;font-family:monospace;letter-spacing:1px;" value="<?php echo e($currentColor);?>" maxlength="7" oninput="validateColor(this)">
                </div>
                <span class="hint-tag" id="themeHint" style="background:var(--primary-bg);color:var(--primary-color);">当前颜色：<?php echo e($currentColor);?></span>
            </div>
        </div>

        <div class="theme-actions">
            <button class="btn btn-outline" onclick="resetTheme()">↺ 重置默认</button>
            <button class="btn btn-primary" onclick="saveTheme()">💾 保存主题</button>
        </div>
    </div>

    <div class="theme-card" style="margin-top:20px;">
        <div class="theme-card-header" style="border:none;">
            <div>
                <div class="theme-card-title">💡 使用说明</div>
                <div class="theme-card-subtitle">颜色会持久化保存，应用于前台和后台所有页面。</div>
            </div>
        </div>
        <div style="padding:0 28px 28px;">
            <ul style="color:var(--text-secondary);font-size:13px;line-height:2;list-style:none;padding:0;margin:0;">
                <li>• 点击任意预设色块即可预览，确认后点击保存按钮生效</li>
                <li>• 颜色选择器支持渐变调色，也可以直接输入 #RRGGBB 十六进制色值</li>
                <li>• 所有CSS变量（按钮、图标、链接、徽章、阴影等）会同步更新</li>
                <li>• 用户端刷新页面后立即看到新主题，无需清除缓存</li>
            </ul>
        </div>
    </div>
</div>

<script>
function selectColor(c) {
    c = c.toLowerCase();
    document.getElementById('colorPicker').value = c;
    document.getElementById('colorText').value = c;
    document.getElementById('themeHint').textContent = '预览颜色：' + c;
    document.getElementById('themeHint').style.color = c;
    document.getElementById('themeHint').style.background = c + '1A';
    // 实时预览
    document.documentElement.style.setProperty('--primary-color', c);
    document.documentElement.style.setProperty('--primary-bg', c + '1A');
    document.documentElement.style.setProperty('--shadow-primary', '0 4px 20px ' + c + '4D');
}
function validateColor(el) {
    var v = el.value.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(v)) {
        selectColor(v);
    }
}
function saveTheme() {
    var c = document.getElementById('colorText').value.trim();
    if (!/^#[0-9a-fA-F]{6}$/.test(c)) { adminToast('颜色格式不正确，应为 #RRGGBB', 'error'); return; }
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'save', color:c}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 800);
    });
}
function resetTheme() {
    if (!confirm('确定要恢复默认主题吗？')) return;
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'reset'}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 600);
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
