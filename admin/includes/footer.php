<?php
/**
 * 管理后台公共底部
 */
if (!defined('ROOT_PATH')) require_once dirname(__FILE__) . '/../../config/config.php';
?>
        </div><!-- .admin-content -->
    </div><!-- .admin-main -->
</div><!-- .admin-wrapper -->

<script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=1.0"></script>

<script>
// Toast
function adminToast(msg, type) {
    var colors = {success:'#10b981', error:'#ef4444', info:'#3b82f6', warning:'#f59e0b'};
    type = type || 'info';
    var container = document.querySelector('.toast-container');
    if (!container) { container = document.createElement('div'); container.className = 'toast-container'; document.body.appendChild(container); }
    var t = document.createElement('div');
    t.className = 'toast';
    t.style.borderLeft = '4px solid ' + colors[type];
    t.textContent = msg;
    container.appendChild(t);
    setTimeout(() => { t.style.transition='all 0.3s'; t.style.opacity='0'; t.style.transform='translateX(120%)'; setTimeout(()=>t.remove(), 300); }, 3000);
}

// 通用 AJAX POST
function adminPost(url, data, cb) {
    fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(cb).catch(e => adminToast('网络错误: ' + e.message, 'error'));
}

// 通用confirm
function adminConfirm(msg, cb) {
    if (confirm(msg)) cb();
}
</script>
</body>
</html>
