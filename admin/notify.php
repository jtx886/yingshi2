<?php
/**
 * 邮件通知：批量给用户发通知
 */
$activeMenu = 'notify';
$pageSubTitle = '邮件通知中心';
require_once dirname(__FILE__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'send') {
        $target = isset($_POST['target']) ? trim($_POST['target']) : 'all'; // all, specified, banned, active
        $specifyEmails = isset($_POST['emails']) ? trim($_POST['emails']) : '';
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $asHtml = isset($_POST['as_html']) ? intval($_POST['as_html']) : 0;

        if (!$title || !$content) json_error('标题和内容必填');

        $emails = array();
        if ($target === 'all') {
            $rows = db()->fetchAll("SELECT DISTINCT email FROM users WHERE email <> ''");
            foreach($rows as $r) $emails[] = $r['email'];
        } elseif ($target === 'active') {
            $rows = db()->fetchAll("SELECT DISTINCT email FROM users WHERE status=1 AND email <> ''");
            foreach($rows as $r) $emails[] = $r['email'];
        } elseif ($target === 'banned') {
            $rows = db()->fetchAll("SELECT DISTINCT email FROM users WHERE status=0 AND email <> ''");
            foreach($rows as $r) $emails[] = $r['email'];
        } elseif ($target === 'specified') {
            $t = preg_split('/[\s,;，；]+/', $specifyEmails);
            foreach ($t as $e) {
                $e = trim($e);
                if (filter_var($e, FILTER_VALIDATE_EMAIL)) $emails[] = $e;
            }
        }
        $emails = array_unique(array_filter($emails));
        if (empty($emails)) json_error('没有有效的收件邮箱');
        if (count($emails) > 500) {
            json_error('单次收件人数过多（' . count($emails) . '），请分批发送，单次不超过500人');
        }

        $success = 0; $failed = 0;
        foreach ($emails as $e) {
            $ok = $asHtml ? @send_html_email($e, $title, $content) : @send_notification_email($e, $title, $content);
            if ($ok) $success++; else $failed++;
            // 防止SMTP限流
            usleep(100000); // 0.1秒
        }
        json_success(array('total'=>count($emails),'success'=>$success,'failed'=>$failed),
            "发送完成：共".count($emails)."封，成功{$success}，失败{$failed}");
    }
    json_error('未知操作');
}

try {
    $stats = array(
        'all' => intval(db()->fetchOne("SELECT COUNT(*) c FROM users WHERE email <> ''")['c']),
        'active' => intval(db()->fetchOne("SELECT COUNT(*) c FROM users WHERE status=1 AND email <> ''")['c']),
        'banned' => intval(db()->fetchOne("SELECT COUNT(*) c FROM users WHERE status=0 AND email <> ''")['c'])
    );
} catch(Exception $e){ $stats = array('all'=>0,'active'=>0,'banned'=>0); }
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card users">
        <div class="stat-icon">📬</div>
        <div class="stat-label">全部有效邮箱</div>
        <div class="stat-value"><?php echo $stats['all'];?></div>
    </div>
    <div class="stat-card feedback">
        <div class="stat-icon">✅</div>
        <div class="stat-label">正常用户</div>
        <div class="stat-value"><?php echo $stats['active'];?></div>
    </div>
    <div class="stat-card views">
        <div class="stat-icon">🚫</div>
        <div class="stat-label">封禁用户</div>
        <div class="stat-value"><?php echo $stats['banned'];?></div>
    </div>
    <div class="stat-card favorites">
        <div class="stat-icon">📨</div>
        <div class="stat-label">单次上限</div>
        <div class="stat-value">500</div>
    </div>
</div>

<div style="max-width:880px;margin:0 auto;">
    <div class="theme-card">
        <div class="theme-card-header" style="border:none;">
            <div>
                <div class="theme-card-title">📧 邮件通知</div>
                <div class="theme-card-subtitle">向用户批量发送邮件通知。使用SMTP发送，请先在网站设置中确认配置正确。</div>
            </div>
        </div>
        <div style="padding:0 28px 28px;">
            <form onsubmit="sendNotify(event)">
                <div class="form-group">
                    <label class="form-label">收件对象</label>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:14px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:10px;cursor:pointer;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)'">
                            <input type="radio" name="target" value="all" checked style="margin-top:2px;width:16px;height:16px;" onchange="toggleEmailInput()">
                            <div style="flex:1;">
                                <div style="font-weight:600;">📬 全部用户</div>
                                <div style="font-size:12px;color:var(--text-muted);">共 <?php echo $stats['all'];?> 位用户有邮箱</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:14px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:10px;cursor:pointer;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)'">
                            <input type="radio" name="target" value="active" style="margin-top:2px;width:16px;height:16px;" onchange="toggleEmailInput()">
                            <div style="flex:1;">
                                <div style="font-weight:600;">✅ 正常用户</div>
                                <div style="font-size:12px;color:var(--text-muted);">共 <?php echo $stats['active'];?> 位</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:14px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:10px;cursor:pointer;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)'">
                            <input type="radio" name="target" value="banned" style="margin-top:2px;width:16px;height:16px;" onchange="toggleEmailInput()">
                            <div style="flex:1;">
                                <div style="font-weight:600;">🚫 封禁用户</div>
                                <div style="font-size:12px;color:var(--text-muted);">共 <?php echo $stats['banned'];?> 位</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:14px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:10px;cursor:pointer;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)'">
                            <input type="radio" name="target" value="specified" style="margin-top:2px;width:16px;height:16px;" onchange="toggleEmailInput()">
                            <div style="flex:1;">
                                <div style="font-weight:600;">✏️ 自定义邮箱列表</div>
                                <div style="font-size:12px;color:var(--text-muted);">手动输入邮箱地址</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="emails-input-row" style="display:none;">
                    <label class="form-label">邮箱列表（用逗号或换行分隔）</label>
                    <textarea class="form-textarea" id="custom-emails" style="min-height:100px;font-family:monospace;font-size:12px;" placeholder="例：user1@example.com, user2@example.com&#10;user3@example.com"></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">邮件标题 <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="nt-title" class="form-input" placeholder="例如：Jay影视重要通知" required>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <label style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--bg-dark);border:1px solid var(--border-color);border-radius:8px;cursor:pointer;user-select:none;">
                            <input type="checkbox" id="nt-as-html" style="width:16px;height:16px;">
                            <div>
                                <div style="font-weight:600;font-size:13px;">HTML 格式</div>
                                <div style="font-size:11px;color:var(--text-muted);">支持富文本HTML标签</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">邮件内容 <span style="color:#ef4444;">*</span></label>
                    <textarea id="nt-content" class="form-textarea" style="min-height:260px;line-height:1.8;" placeholder="邮件正文，支持HTML格式（勾选后）。可使用变量：{username}、{email}、{date}" required></textarea>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertTemplate(1)">📋 通用通知模板</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertTemplate(2)">🎬 新片上线模板</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertTemplate(3)">🎉 活动公告模板</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="sendTestNow()">📤 先发给自己测试</button>
                    </div>
                </div>

                <div id="send-progress" style="display:none;padding:16px;background:var(--primary-bg);border:1px solid var(--primary-color);border-radius:10px;margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span>正在发送...</span>
                        <span id="progress-text">0%</span>
                    </div>
                    <div style="height:6px;background:rgba(255,255,255,0.1);border-radius:3px;overflow:hidden;">
                        <div id="progress-bar" style="height:100%;width:0%;background:var(--primary-color);transition:width 0.3s;border-radius:3px;"></div>
                    </div>
                </div>

                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="location.reload()">清空</button>
                    <button type="submit" class="btn btn-primary" id="send-btn">📨 开始发送</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleEmailInput() {
    var t = document.querySelector('input[name="target"]:checked').value;
    document.getElementById('emails-input-row').style.display = t === 'specified' ? 'block' : 'none';
}
function insertTemplate(id) {
    var t = '';
    if (id === 1) t = '亲爱的 {username}：<br><br>您好！感谢您使用 <?php echo e(get_setting("site_name","Jay影视"));?>。<br><br>这里有一则重要通知告知您：<br><br>📢 <b>通知内容在这里</b><br><br>如有疑问请回复本邮件或在站内反馈区留言。<br><br>祝您观影愉快！<br><br>—— <?php echo e(get_setting("site_name","Jay影视"));?> 团队<br>{date}';
    if (id === 2) t = '🎬 <b><?php echo e(get_setting("site_name","Jay影视"));?> - 新片上线啦！</b><br><br>亲爱的 {username}：<br><br>我们为您带来最新精彩内容：<br><br>🔥 <b>《影视名称》</b> 正式上线<br>📅 更新日期：{date}<br>⭐ 推荐指数：★★★★★<br><br>立即前往网站观看 👉 <a href="<?php echo BASE_URL;?>/index.php"><?php echo BASE_URL;?>/index.php</a><br><br>—— <?php echo e(get_setting("site_name","Jay影视"));?> 团队';
    if (id === 3) t = '🎉 <b><?php echo e(get_setting("site_name","Jay影视"));?> 活动公告</b><br><br>亲爱的 {username}：<br><br>为了回馈广大用户，我们举办特别活动！<br><br>📅 活动时间：即日起至{date}<br>🎁 活动奖励：丰厚奖品等你拿<br>📌 参与方式：活动期间登录网站即可参与<br><br>更多详情请访问：<a href="<?php echo BASE_URL;?>/index.php"><?php echo BASE_URL;?>/index.php</a><br><br>—— <?php echo e(get_setting("site_name","Jay影视"));?> 团队';
    document.getElementById('nt-as-html').checked = true;
    document.getElementById('nt-content').value = t.replace(/<br>/g,'\n').replace(/<[^>]+>/g, '');
    setTimeout(()=>{document.getElementById('nt-as-html').checked = true;document.getElementById('nt-content').value = t;},50);
}
function sendNotify(e) {
    e.preventDefault();
    var target = document.querySelector('input[name="target"]:checked').value;
    var emails = document.getElementById('custom-emails').value.trim();
    var title = document.getElementById('nt-title').value.trim();
    var content = document.getElementById('nt-content').value;
    var asHtml = document.getElementById('nt-as-html').checked?1:0;
    if (target === 'specified' && !emails) { adminToast('请输入自定义邮箱列表', 'warning'); return; }
    if (!title || !content) return;
    if (!confirm('确认开始发送邮件？根据数量可能需要一点时间。')) return;

    document.getElementById('send-btn').disabled = true;
    document.getElementById('send-progress').style.display = 'block';
    document.getElementById('progress-bar').style.width = '15%';
    document.getElementById('progress-text').textContent = '准备中...';

    adminPost('<?php echo $_SERVER['PHP_SELF'];?>', {action:'send', target:target, emails:emails, title:title, content:content, as_html:asHtml}, function(res){
        document.getElementById('progress-bar').style.width = '100%';
        document.getElementById('progress-text').textContent = '完成 100%';
        adminToast(res.message, res.code===200?'success':'error');
        setTimeout(function(){
            document.getElementById('send-progress').style.display = 'none';
            document.getElementById('send-btn').disabled = false;
        }, 2000);
    });
}
function sendTestNow() {
    var title = document.getElementById('nt-title').value.trim() || '测试邮件';
    var content = document.getElementById('nt-content').value || '这是一封测试邮件';
    adminPost('<?php echo BASE_URL;?>/admin/settings.php', {action:'test_smtp', test_email:'<?php echo e($currentUser["email"]);?>'}, function(res){
        adminToast(res.message, res.code===200?'success':'error');
    });
}
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
