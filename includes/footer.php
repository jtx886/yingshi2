<?php
/**
 * 公共底部模板
 */
if (!defined('ROOT_PATH')) {
    require_once dirname(__FILE__) . '/../config/config.php';
}
?>

<!-- 公告弹窗 (仅在首页显示) -->
<?php if (isset($showAnnouncement) && $showAnnouncement && !empty($activeAnnouncements)): ?>
<div id="announcement-modal" class="modal-overlay show">
    <div class="modal-content">
        <button class="modal-close" onclick="closeAnnouncement(event)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>
        <div class="modal-banner"></div>
        <div class="modal-body">
            <h3 class="modal-title" id="announcement-title"><?php echo e($activeAnnouncements[0]['title']); ?></h3>
            <div class="modal-date">📅 <?php echo format_time($activeAnnouncements[0]['created_at'], true); ?></div>
            <div class="modal-content-text" id="announcement-content">
                <?php echo nl2br(e($activeAnnouncements[0]['content'])); ?>
            </div>
        </div>
        <div class="modal-footer">
            <label class="modal-dont-show">
                <input type="checkbox" id="dont-show-again" style="display:none;" onchange="toggleCheckbox(this)">
                <span class="checkbox-custom"></span>
                不再提示此公告
            </label>
            <button class="btn btn-primary" onclick="closeAnnouncement(event)">我知道了</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <span class="logo-icon"></span>
                    <span class="logo-text">Jay影视</span>
                </div>
                <p class="footer-desc">
                    Jay影视是一个专业的在线影视播放平台，为您提供海量高清电影、电视剧、动漫、综艺等影视资源。支持多端访问，随时随地畅享精彩内容。
                </p>
                <div style="display:flex;gap:12px;">
                    <a href="#" style="width:36px;height:36px;background:var(--bg-card);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="color:#3b82f6;"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                </div>
            </div>
            <div>
                <h4 class="footer-col-title">分类浏览</h4>
                <div class="footer-links">
                    <a href="<?php echo BASE_URL; ?>/category.php?type=movie">电影</a>
                    <a href="<?php echo BASE_URL; ?>/category.php?type=tv">电视剧</a>
                    <a href="<?php echo BASE_URL; ?>/category.php?type=anime">动漫</a>
                    <a href="<?php echo BASE_URL; ?>/category.php?type=variety">综艺</a>
                </div>
            </div>
            <div>
                <h4 class="footer-col-title">帮助中心</h4>
                <div class="footer-links">
                    <a href="<?php echo BASE_URL; ?>/feedback.php">意见反馈</a>
                    <a href="#">常见问题</a>
                    <a href="#">用户协议</a>
                    <a href="#">隐私政策</a>
                </div>
            </div>
            <div>
                <h4 class="footer-col-title">联系我们</h4>
                <div class="footer-links">
                    <a href="mailto:jtxnb886@163.com">📧 jtxnb886@163.com</a>
                    <a href="#">🕐 每日 8:00 - 24:00</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> <?php echo e(get_setting('site_name', 'Jay影视')); ?> 版权所有 · 仅供学习交流使用 · 内容来源于互联网</p>
        </div>
    </div>
</footer>

<!-- Mobile Bottom Nav -->
<nav class="mobile-nav">
    <div class="mobile-nav-list">
        <a href="<?php echo BASE_URL; ?>/index.php" class="mobile-nav-item <?php echo (isset($currentPage) && $currentPage === 'home') ? 'active' : ''; ?>">
            <span class="mobile-nav-icon home-icon"></span>
            <span>首页</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/search.php" class="mobile-nav-item <?php echo (isset($currentPage) && $currentPage === 'search') ? 'active' : ''; ?>">
            <span class="search-icon" style="width:18px;height:18px;"></span>
            <span>搜索</span>
        </a>
        <?php if ($currentUser): ?>
        <a href="<?php echo BASE_URL; ?>/user/profile.php" class="mobile-nav-item <?php echo (isset($currentPage) && $currentPage === 'profile') ? 'active' : ''; ?>">
            <span class="mobile-nav-icon profile-icon"></span>
            <span>我的</span>
        </a>
        <?php else: ?>
        <a href="<?php echo BASE_URL; ?>/login.php" class="mobile-nav-item">
            <span class="mobile-nav-icon profile-icon"></span>
            <span>登录</span>
        </a>
        <?php endif; ?>
    </div>
</nav>

<script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=1.0"></script>

<script>
<?php if (isset($showAnnouncement) && $showAnnouncement && !empty($activeAnnouncements)): ?>
(function() {
    var annId = <?php echo intval($activeAnnouncements[0]['id']); ?>;
    var userId = <?php echo $currentUser ? intval($currentUser['id']) : 0; ?>;
    var ip = '<?php echo e(get_client_ip()); ?>';
    var storageKey = 'jay_ann_dismiss_' + annId + (userId ? '_u' + userId : '_ip' + ip);
    
    if (localStorage.getItem(storageKey) === '1') {
        var modal = document.getElementById('announcement-modal');
        if (modal) modal.classList.remove('show');
    }
    
    window.closeAnnouncement = function(e) {
        var dontShow = document.getElementById('dont-show-again');
        if (dontShow && dontShow.checked) {
            localStorage.setItem(storageKey, '1');
        }
        var modal = document.getElementById('announcement-modal');
        if (modal) modal.classList.remove('show');
    };
    
    window.toggleCheckbox = function(cb) {
        var next = cb.nextElementSibling;
        cb.checked = !cb.checked;
    };
})();
<?php endif; ?>
</script>

</body>
</html>
