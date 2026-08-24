<?php
/**
 * 管理后台公共头部
 */
if (!defined('ROOT_PATH')) {
    require_once dirname(__FILE__) . '/../../config/config.php';
}
require_once ROOT_PATH . '/includes/functions.php';

// 必须管理员登录
if (!is_logged_in()) {
    redirect(BASE_URL . '/login.php?redirect=' . base64_encode($_SERVER['REQUEST_URI']));
}
$currentUser = current_user();
if (!is_admin()) {
    die('<div style="text-align:center;padding:100px;font-family:sans-serif;"><div style="font-size:60px;">🚫</div><h2>无权访问</h2><p>您没有管理后台权限</p><a href="'.BASE_URL.'/index.php">返回首页</a></div>');
}
if (is_banned($currentUser)) {
    die('账号被封禁');
}

// 获取统计数据用
function adminGetStats() {
    try {
        return array(
            'users' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM users")['c']),
            'today_users' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM users WHERE DATE(created_at) = CURDATE()")['c']),
            'feedback' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM feedbacks")['c']),
            'pending_feedback' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM feedbacks WHERE status='pending'")['c']),
            'views' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM watch_history")['c']),
            'today_views' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM watch_history WHERE DATE(last_watch_at) = CURDATE()")['c']),
            'favorites' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM favorites")['c']),
            'today_favorites' => intval(db()->fetchOne("SELECT COUNT(*) as c FROM favorites WHERE DATE(created_at) = CURDATE()")['c'])
        );
    } catch (Exception $e) {
        return array_fill_keys(array('users','today_users','feedback','pending_feedback','views','today_views','favorites','today_favorites'), 0);
    }
}

$activeMenu = isset($activeMenu) ? $activeMenu : 'dashboard';
$themeColor = get_theme_color();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1">
<title>管理后台 - <?php echo e(get_setting('site_name','Jay影视')); ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=1.0">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css?v=1.0">
<style>
:root {
    --primary-color: <?php echo e($themeColor); ?>;
    --primary-bg: <?php echo e($themeColor); ?>1A;
    --primary-gradient: linear-gradient(135deg, <?php echo e($themeColor); ?> 0%, <?php echo e(adjustColor($themeColor,-15)); ?> 100%);
    --primary-light: <?php echo e(adjustColor($themeColor,20)); ?>;
    --primary-dark: <?php echo e(adjustColor($themeColor,-20)); ?>;
    --shadow-primary: 0 4px 20px <?php echo e($themeColor); ?>4D;
}
</style>
</head>
<body style="padding:0;">

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-header">
            <div class="admin-sidebar-logo">J<span style="font-size:10px;position:absolute;bottom:-2px;right:-3px;background:white;color:#dc2626;padding:1px 4px;border-radius:3px;font-weight:700;">Dev</span></div>
            <div>
                <div class="admin-sidebar-title">Jay影视后台</div>
                <div class="admin-sidebar-subtitle">控制台 v1.0</div>
            </div>
        </div>

        <nav class="admin-menu">
            <div class="admin-menu-section">
                <div class="admin-menu-section-title">主菜单</div>
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="admin-menu-item <?php echo $activeMenu==='dashboard'?'active':''; ?>">
                    <span class="admin-menu-icon">📊</span>
                    仪表盘
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="admin-menu-item <?php echo $activeMenu==='users'?'active':''; ?>">
                    <span class="admin-menu-icon">👥</span>
                    用户管理
                    <?php $s=adminGetStats(); if($s['today_users']>0):?><span class="menu-badge"><?php echo $s['today_users'];?></span><?php endif;?>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/history.php" class="admin-menu-item <?php echo $activeMenu==='history'?'active':''; ?>">
                    <span class="admin-menu-icon">📺</span>
                    观看历史
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/favorites.php" class="admin-menu-item <?php echo $activeMenu==='favorites'?'active':''; ?>">
                    <span class="admin-menu-icon">❤️</span>
                    用户收藏
                </a>
            </div>

            <div class="admin-menu-section">
                <div class="admin-menu-section-title">内容管理</div>
                <a href="<?php echo BASE_URL; ?>/admin/play_sources.php" class="admin-menu-item <?php echo $activeMenu==='sources'?'active':''; ?>">
                    <span class="admin-menu-icon">📡</span>
                    播放源管理
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/feedback.php" class="admin-menu-item <?php echo $activeMenu==='feedback'?'active':''; ?>">
                    <span class="admin-menu-icon">💬</span>
                    反馈管理
                    <?php if($s['pending_feedback']>0):?><span class="menu-badge"><?php echo $s['pending_feedback'];?></span><?php endif;?>
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/announcements.php" class="admin-menu-item <?php echo $activeMenu==='announcements'?'active':''; ?>">
                    <span class="admin-menu-icon">📢</span>
                    公告管理
                </a>
            </div>

            <div class="admin-menu-section">
                <div class="admin-menu-section-title">系统设置</div>
                <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="admin-menu-item <?php echo $activeMenu==='settings'?'active':''; ?>">
                    <span class="admin-menu-icon">⚙️</span>
                    网站设置
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/theme.php" class="admin-menu-item <?php echo $activeMenu==='theme'?'active':''; ?>">
                    <span class="admin-menu-icon">🎨</span>
                    主题颜色
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/notify.php" class="admin-menu-item <?php echo $activeMenu==='notify'?'active':''; ?>">
                    <span class="admin-menu-icon">📧</span>
                    邮件通知
                </a>
            </div>

            <div class="admin-menu-section">
                <div class="admin-menu-section-title">快捷操作</div>
                <a href="<?php echo BASE_URL; ?>/index.php" class="admin-menu-item" target="_blank">
                    <span class="admin-menu-icon">🏠</span>
                    前台首页
                </a>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="admin-menu-item" style="color:#f87171;">
                    <span class="admin-menu-icon">🚪</span>
                    退出登录
                </a>
            </div>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-user-card">
                <div class="admin-user-avatar">
                    <?php if(!empty($currentUser['avatar'])):?>
                    <img src="<?php echo e($currentUser['avatar']);?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: echo e(mb_substr($currentUser['username'],0,1,'UTF-8')); endif; ?>
                </div>
                <div class="admin-user-info">
                    <div class="admin-user-name">
                        <?php echo e($currentUser['username']);?>
                        <span class="admin-logo-badge" style="margin-left:4px;">开发者</span>
                    </div>
                    <div class="admin-user-role">超级管理员</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <div class="admin-main">
        <header class="admin-header">
            <div style="display:flex;align-items:center;gap:16px;">
                <button class="icon-btn" style="width:40px;height:40px;" onclick="document.getElementById('adminSidebar').classList.toggle('open')">☰</button>
                <div class="admin-header-title"><?php echo isset($pageSubTitle)?e($pageSubTitle):'管理后台';?></div>
            </div>
            <div class="admin-header-actions">
                <span style="font-size:13px;color:var(--text-muted);">
                    📅 <?php echo date('Y年m月d日 l');?>
                </span>
                <a href="<?php echo BASE_URL;?>/index.php" class="btn btn-outline btn-sm" target="_blank">前台预览</a>
            </div>
        </header>

        <div class="admin-content">
