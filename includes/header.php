<?php
/**
 * 公共头部模板
 */
if (!defined('ROOT_PATH')) {
    require_once dirname(__FILE__) . '/../config/config.php';
}
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/api/tmdb.php';

$currentPage = isset($currentPage) ? $currentPage : 'home';
$currentUser = current_user();
$themeColor = get_theme_color();
$tmdb = new TMDBApi();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?php echo e($themeColor); ?>">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e(get_setting('site_name', 'Jay影视')); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=1.0">
    <style>
        /* 动态主题色 */
        :root {
            --primary-color: <?php echo e($themeColor); ?>;
            --primary-bg: <?php echo e($themeColor); ?>1A;
            --primary-gradient: linear-gradient(135deg, <?php echo e($themeColor); ?> 0%, <?php echo e(adjustColor($themeColor, -15)); ?> 100%);
            --primary-light: <?php echo e(adjustColor($themeColor, 20)); ?>;
            --primary-dark: <?php echo e(adjustColor($themeColor, -20)); ?>;
            --shadow-primary: 0 4px 20px <?php echo e($themeColor); ?>4D;
        }
    </style>
</head>
<body>
<!-- Header -->
<header class="header">
    <div class="container">
        <a href="<?php echo BASE_URL; ?>/index.php" class="logo">
            <span class="logo-icon"></span>
            <span class="logo-text">Jay影视</span>
        </a>

        <!-- Desktop Navigation -->
        <nav class="desktop-nav">
            <a href="<?php echo BASE_URL; ?>/index.php" class="nav-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>">首页</a>
            <a href="<?php echo BASE_URL; ?>/category.php?type=movie" class="nav-link <?php echo $currentPage === 'movie' ? 'active' : ''; ?>">电影</a>
            <a href="<?php echo BASE_URL; ?>/category.php?type=tv" class="nav-link <?php echo $currentPage === 'tv' ? 'active' : ''; ?>">电视剧</a>
            <a href="<?php echo BASE_URL; ?>/category.php?type=anime" class="nav-link <?php echo $currentPage === 'anime' ? 'active' : ''; ?>">动漫</a>
            <a href="<?php echo BASE_URL; ?>/category.php?type=variety" class="nav-link <?php echo $currentPage === 'variety' ? 'active' : ''; ?>">综艺</a>
            <a href="<?php echo BASE_URL; ?>/feedback.php" class="nav-link <?php echo $currentPage === 'feedback' ? 'active' : ''; ?>">反馈</a>
        </nav>

        <!-- Header Right -->
        <div class="header-right">
            <form class="search-box" action="<?php echo BASE_URL; ?>/search.php" method="GET">
                <input type="text" name="q" class="search-input" placeholder="搜索电影、电视剧、动漫..." value="<?php echo isset($_GET['q']) ? e($_GET['q']) : ''; ?>">
                <button type="submit" class="search-btn" title="搜索">
                    <span class="search-icon"></span>
                </button>
            </form>

            <?php if ($currentUser): ?>
                <a href="<?php echo BASE_URL; ?>/user/history.php" class="icon-btn" title="观看历史">
                    <span class="history-icon"></span>
                </a>
            <?php endif; ?>

            <?php if ($currentUser): ?>
                <div class="user-menu">
                    <div class="user-avatar" onclick="this.parentElement.classList.toggle('active')">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <img src="<?php echo e($currentUser['avatar']); ?>" alt="<?php echo e($currentUser['username']); ?>">
                        <?php else: ?>
                            <?php echo mb_substr($currentUser['username'], 0, 1, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-dropdown">
                        <div class="user-dropdown-header">
                            <div class="user-dropdown-name">
                                <?php echo e($currentUser['username']); ?>
                                <?php if (is_admin()): ?>
                                    <span class="admin-logo-text">开发者</span>
                                <?php endif; ?>
                            </div>
                            <div class="user-dropdown-email"><?php echo e($currentUser['email']); ?></div>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/user/profile.php" class="user-dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                            个人中心
                        </a>
                        <a href="<?php echo BASE_URL; ?>/user/favorites.php" class="user-dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 5a5.5 5.5 0 0 1 9.5 7C19 16.5 12 21 12 21z"/></svg>
                            我的收藏
                        </a>
                        <a href="<?php echo BASE_URL; ?>/user/history.php" class="user-dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            观看历史
                        </a>
                        <?php if (is_admin()): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="user-dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            管理后台
                            <span class="admin-badge">管理</span>
                        </a>
                        <?php endif; ?>
                        <div style="border-top:1px solid var(--border-color); margin:8px 0;"></div>
                        <a href="<?php echo BASE_URL; ?>/logout.php" class="user-dropdown-item" style="color:#f87171;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                            退出登录
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline btn-sm">登录</a>
                <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary btn-sm">注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php
/**
 * 颜色调整函数（亮度）
 */
function adjustColor($color, $percent) {
    $color = str_replace('#', '', $color);
    if (strlen($color) !== 6) return $color;
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    $r = max(0, min(255, $r + round(255 * $percent / 100)));
    $g = max(0, min(255, $g + round(255 * $percent / 100)));
    $b = max(0, min(255, $b + round(255 * $percent / 100)));
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}
?>
