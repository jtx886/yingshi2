-- Jay影视网站数据库结构
-- 兼容所有MySQL版本

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=正常, 0=封禁',
  `ban_time` datetime DEFAULT NULL,
  `ban_until` datetime DEFAULT NULL,
  `ban_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 邮箱验证码表
CREATE TABLE IF NOT EXISTS `email_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `type` varchar(20) DEFAULT 'register' COMMENT 'register, reset_password',
  `expire_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 播放源表
CREATE TABLE IF NOT EXISTS `play_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `api_url` varchar(500) NOT NULL,
  `parser_url` varchar(500) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 观看历史表
CREATE TABLE IF NOT EXISTS `watch_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `movie_id` varchar(50) NOT NULL,
  `movie_name` varchar(255) NOT NULL,
  `poster` varchar(500) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL COMMENT 'movie, tv, anime, variety',
  `season` int(11) DEFAULT 1,
  `episode` int(11) DEFAULT 1,
  `episode_name` varchar(255) DEFAULT NULL,
  `watch_seconds` int(11) DEFAULT 0,
  `duration` int(11) DEFAULT 0,
  `last_watch_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `movie_id` (`movie_id`),
  UNIQUE KEY `unique_user_movie` (`user_id`,`movie_id`,`season`,`episode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 收藏表
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `movie_id` varchar(50) NOT NULL,
  `movie_name` varchar(255) NOT NULL,
  `poster` varchar(500) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  UNIQUE KEY `unique_user_favorite` (`user_id`,`movie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 公告表
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户公告已读表
CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `announcement_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `dont_show_again` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `announcement_id` (`announcement_id`),
  KEY `user_ip` (`user_id`,`ip_address`,`announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 反馈表
CREATE TABLE IF NOT EXISTS `feedbacks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `status` varchar(20) DEFAULT 'pending' COMMENT 'pending, processing, resolved, closed',
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 反馈回复表
CREATE TABLE IF NOT EXISTS `feedback_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `feedback_id` (`feedback_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 反馈点赞表
CREATE TABLE IF NOT EXISTS `feedback_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`feedback_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 网站设置表
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `type` varchar(20) DEFAULT 'string' COMMENT 'string, int, json, color',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认管理员账号: 杰同学 / 101113
INSERT INTO `users` (`username`, `email`, `password`, `is_admin`, `status`) VALUES
('杰同学', 'admin@jaymovie.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);

-- 注意: 上面的密码哈希需要在安装时重新生成，这里是示例
-- 默认播放源
INSERT INTO `play_sources` (`name`, `api_url`, `parser_url`, `is_default`, `status`, `sort_order`) VALUES
('云播资源', 'https://api.yyzy-tv.vip/inc/apijson.php', 'https://svip.ffzyplay.com/?url=', 1, 1, 1);

-- 默认网站设置
INSERT INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('site_name', 'Jay影视', 'string', '网站名称'),
('theme_color', '#7c3aed', 'color', '主题颜色'),
('tmdb_api_key', '', 'string', 'TMDB API Key'),
('player_parser', 'https://svip.ffzyplay.com/?url=', 'string', '默认解析播放器地址'),
('smtp_host', 'smtp.163.com', 'string', 'SMTP服务器'),
('smtp_port', '465', 'int', 'SMTP端口'),
('smtp_user', 'jtxnb886@163.com', 'string', 'SMTP用户名'),
('smtp_pass', 'FLLRDtadYAfGXp9Y', 'string', 'SMTP密码'),
('smtp_from', 'jtxnb886@163.com', 'string', '发件人邮箱'),
('smtp_from_name', 'Jay影视', 'string', '发件人名称');

COMMIT;
