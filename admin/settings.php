<?php
/**
 * 网站设置
 */
$activeMenu = 'settings';
$pageSubTitle = '网站设置';
require_once dirname(__FILE__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'save') {
        $fields = array('site_name','site_desc','player_parse_url','default_tmdb_lang','tmdb_api_key','register_enabled','email_verify_required');
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $v = is_array($_POST[$f]) ? '' : trim($_POST[$f]);
                update_setting($f, $v);
            }
        }
        // SMTP 字段
        $smtpFields = array('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name');
        foreach ($smtpFields as $f) {
            if (isset($_POST[$f])) update_setting($f, trim($_POST[$f]));
        }
        json_success(null, '设置已保存');
    }
    if ($action === 'test_smtp') {
        $to = isset($_POST['test_email']) ? trim($_POST['test_email']) : '';
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) json_error('请输入有效的邮箱地址');
        $ok = @send_notification_email($to, 'Jay影视 - SMTP测试', '恭喜！您的SMTP配置正确，邮件发送成功！当前时间：'.date('Y-m-d H:i:s'));
        if ($ok) json_success(null, '测试邮件已发送，请检查收件箱/垃圾箱');
        json_error('发送失败，请检查SMTP配置');
    }
    json_error('未知操作');
}
?>

<div style="max-width:880px;margin:0 auto;">
    <form onsubmit="saveSettings(event)">
        <div class="theme-card" style="margin-bottom:20px;">
            <div class="theme-card-header" style="border:none;">
                <div class="theme-card-title">🏠 基本信息</div>
            </div>
            <div style="padding:0 28px 28px;">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">网站名称</label>
                        <input type="text" class="form-input" id="s_site_name" value="<?php echo e(get_setting('site_name','Jay影视'));?>" placeholder="例如：Jay影视">
                    </div>
                    <div class="form-group">
                        <label class="form-label">TMDB 默认语言</label>
                        <select class="form-input" id="s_default_tmdb_lang">
                            <option value="zh-CN" <?php echo get_setting('default_tmdb_lang','zh-CN')==='zh-CN'?'selected':'';?>>简体中文 (zh-CN)</option>
                            <option value="zh-TW" <?php echo get_setting('default_tmdb_lang')==='zh-TW'?'selected':'';?>>繁体中文 (zh-TW)</option>
                            <option value="en-US" <?php echo get_setting('default_tmdb_lang')==='en-US'?'selected':'';?>>英文 (en-US)</option>
                            <option value="ja-JP" <?php echo get_setting('default_tmdb_lang')==='ja-JP'?'selected':'';?>>日语 (ja-JP)</option>
                            <option value="ko-KR" <?php echo get_setting('default_tmdb_lang')==='ko-KR'?'selected':'';?>>韩语 (ko-KR)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">网站描述</label>
                    <textarea class="form-textarea" id="s_site_desc" style="min-height:80px;" placeholder="用于SEO和首页Meta标签"><?php echo e(get_setting('site_desc','Jay影视 - 免费高清电影、电视剧、动漫、综艺在线观看'));?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">TMDB API Key (可选)</label>
                    <input type="text" class="form-input" id="s_tmdb_api_key" value="<?php echo e(get_setting('tmdb_api_key',''));?>" placeholder="输入TMDB v3 API Key（留空则使用内置默认）">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        申请地址：<a href="https://www.themoviedb.org/settings/api" target="_blank" style="color:var(--primary-color);">https://www.themoviedb.org/settings/api</a>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">默认解析播放器 URL</label>
                    <input type="url" class="form-input" id="s_player_parse_url" value="<?php echo e(get_setting('player_parse_url','https://svip.ffzyplay.com/?url='));?>" placeholder="例如：https://svip.ffzyplay.com/?url=">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        这是播放页嵌入的解析播放器，实际播放地址 = 解析器URL + 真实播放链接
                    </div>
                </div>
                <div style="display:flex;gap:20px;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="s_register_enabled" <?php echo intval(get_setting('register_enabled','1'))===1?'checked':'';?> style="width:16px;height:16px;">
                        <span>开放用户注册</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="s_email_verify_required" <?php echo intval(get_setting('email_verify_required','1'))===1?'checked':'';?> style="width:16px;height:16px;">
                        <span>注册需邮箱验证（推荐）</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="theme-card" style="margin-bottom:20px;">
            <div class="theme-card-header" style="border:none;">
                <div class="theme-card-title">📧 SMTP 邮件服务器</div>
            </div>
            <div style="padding:0 28px 28px;">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">SMTP 主机</label>
                        <input type="text" class="form-input" id="s_smtp_host" value="<?php echo e(get_setting('smtp_host',SMTP_HOST));?>" placeholder="例如：smtp.163.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP 端口</label>
                        <input type="number" class="form-input" id="s_smtp_port" value="<?php echo e(get_setting('smtp_port',SMTP_PORT));?>" placeholder="465 / 25 / 587">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP 用户</label>
                        <input type="email" class="form-input" id="s_smtp_user" value="<?php echo e(get_setting('smtp_user',SMTP_USER));?>" placeholder="邮箱账号">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP 密码 / 授权码</label>
                        <input type="password" class="form-input" id="s_smtp_pass" value="<?php echo e(get_setting('smtp_pass',SMTP_PASS));?>" placeholder="授权码（不是登录密码）">
                    </div>
                    <div class="form-group">
                        <label class="form-label">发件人邮箱</label>
                        <input type="email" class="form-input" id="s_smtp_from" value="<?php echo e(get_setting('smtp_from',SMTP_FROM));?>" placeholder="一般和SMTP用户相同">
                    </div>
                    <div class="form-group">
                        <label class="form-label">发件人名称</label>
                        <input type="text" class="form-input" id="s_smtp_from_name" value="<?php echo e(get_setting('smtp_from_name',SMTP_FROM_NAME));?>" placeholder="例如：Jay影视">
                    </div>
                </div>
                <div style="display:flex;gap:10px;align-items:center;margin-top:4px;flex-wrap:wrap;">
                    <input type="email" id="test_email" class="form-input" style="max-width:280px;" placeholder="输入测试邮箱">
                    <button type="button" class="btn btn-outline btn-sm" onclick="testSmtp()">📨 发送测试邮件</button>
                    <span class="hint-tag" style="background:var(--primary-bg);color:var(--primary-color);">💡 发送前请先点"保存设置"</span>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button type="button" class="btn btn-outline" onclick="location.reload()">↺ 还原</button>
            <button type="submit" class="btn btn-primary">💾 保存设置</button>
        </div>
    </form>
</div>

<script>
function saveSettings(e) {
    e.preventDefault();
    var data = {
        action:'save',
        site_name: document.getElementById('s_site_name').value.trim(),
        site_desc: document.getElementById('s_site_desc').value.trim(),
        default_tmdb_lang: document.getElementById('s_default_tmdb_lang').value,
        tmdb_api_key: document.getElementById('s_tmdb_api_key').value.trim(),
        player_parse_url: document.getElementById('s_player_parse_url').value.trim(),
        register_enabled: document.getElementById('s_register_enabled').checked?1:0,
        email_verify_required: document.getElementById('s_email_verify_required').checked?1:0,
        smtp_host: document.getElementById('s_smtp_host').value.trim(),
        smtp_port: document.getElementById('s_smtp_port').value.trim(),
        smtp_user: document.getElementById('s_smtp_user').value.trim(),
        smtp_pass: document.getElementById('s_smtp_pass').value,
        smtp_from: document.getElementById('s_smtp_from').value.trim(),
        smtp_from_name: document.getElementById('s_smtp_from_name').value.trim()
    };
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', data, function(res){
        adminToast(res.message, res.code===200?'success':'error');
        if (res.code===200) setTimeout(()=>location.reload(), 800);
    });
}
function testSmtp() {
    var to = document.getElementById('test_email').value.trim();
    if (!to) { adminToast('请先输入测试邮箱', 'warning'); return; }
    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'test_smtp', test_email:to}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
