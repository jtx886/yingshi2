<?php
/**
 * 首页
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/api/tmdb.php';

$pageTitle = '首页';
$currentPage = 'home';
$showAnnouncement = true;

// 提前获取当前用户（后面第122、143行会用到，避免 header.php 包含前就使用导致未定义）
$currentUser = current_user();

$tmdb = new TMDBApi();

// 获取轮播图（Trending/Hero Banner）
$heroData = array();
try {
    $trending = $tmdb->getTrending('week', 1);
    $heroList = isset($trending['results']) ? array_slice($trending['results'], 0, 3) : array();
    foreach ($heroList as $item) {
        $id = isset($item['id']) ? $item['id'] : 0;
        $mediaType = isset($item['media_type']) ? $item['media_type'] : (isset($item['number_of_seasons']) ? 'tv' : 'movie');
        $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '');
        $originalTitle = isset($item['original_title']) ? $item['original_title'] : (isset($item['original_name']) ? $item['original_name'] : '');
        $year = '';
        if (isset($item['release_date'])) {
            $year = substr($item['release_date'], 0, 4);
        } elseif (isset($item['first_air_date'])) {
            $year = substr($item['first_air_date'], 0, 4);
        }
        $rating = isset($item['vote_average']) ? number_format($item['vote_average'], 1) : '0.0';
        $genres = array();
        if (isset($item['genre_ids']) && is_array($item['genre_ids'])) {
            $genreMap = array(28=>'动作',12=>'冒险',16=>'动画',35=>'喜剧',80=>'犯罪',99=>'纪录',18=>'剧情',10751=>'家庭',14=>'奇幻',36=>'历史',27=>'恐怖',10402=>'音乐',9648=>'悬疑',10749=>'爱情',878=>'科幻',10770=>'电视',53=>'惊悚',10752=>'战争',37=>'西部',10759=>'动作冒险',10762=>'儿童',10763=>'新闻',10764=>'真人秀',10765=>'科幻奇幻',10766=>'肥皂剧',10767=>'脱口秀',10768=>'战争政治',10753=>'西部');
            foreach (array_slice($item['genre_ids'], 0, 3) as $gid) {
                if (isset($genreMap[$gid])) $genres[] = $genreMap[$gid];
            }
        }
        if (empty($genres)) {
            $typeLabels = array('movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺');
            $genres[] = isset($typeLabels[$mediaType]) ? $typeLabels[$mediaType] : '精彩';
        }
        $heroData[] = array(
            'id' => $id,
            'type' => $mediaType,
            'title' => $title,
            'year' => $year,
            'rating' => $rating,
            'genres' => $genres,
            'overview' => isset($item['overview']) ? $item['overview'] : '',
            'poster' => $tmdb->getImageUrl(isset($item['poster_path']) ? $item['poster_path'] : '', 'original'),
            'backdrop' => $tmdb->getImageUrl(isset($item['backdrop_path']) ? $item['backdrop_path'] : '', 'original'),
            'detail_url' => BASE_URL . '/detail.php?id=' . $id . '&type=' . $mediaType,
            'play_url' => BASE_URL . '/play.php?id=' . $id . '&type=' . $mediaType
        );
    }
} catch (Exception $e) {
    $heroData = array();
}

// 如果轮播为空，填充默认
if (empty($heroData)) {
    $heroData[] = array(
        'id' => 303, 'type' => 'tv', 'title' => '西游记之再世妖王', 'year' => '2021',
        'rating' => '8.7', 'genres' => array('动画','奇幻','冒险'),
        'overview' => '混沌初开，世间万物生灵涂炭，妖鬼肆虐。孙悟空为寻求正义，踏上了一条充满挑战与守护的道路。',
        'poster' => '', 'backdrop' => '',
        'detail_url' => BASE_URL . '/detail.php?id=303&type=tv',
        'play_url' => BASE_URL . '/play.php?id=303&type=tv'
    );
}

// 热门推荐（混合）
try {
    $popularAll = $tmdb->getTrending('week', 1);
    $popularList = isset($popularAll['results']) ? array_slice($popularAll['results'], 0, 12) : array();
} catch (Exception $e) { $popularList = array(); }

// 电影
try {
    $moviesData = $tmdb->getPopularMovies(1);
    $movieList = isset($moviesData['results']) ? array_slice($moviesData['results'], 0, 12) : array();
} catch (Exception $e) { $movieList = array(); }

// 电视剧
try {
    $tvData = $tmdb->getPopularTV(1);
    $tvList = isset($tvData['results']) ? array_slice($tvData['results'], 0, 12) : array();
} catch (Exception $e) { $tvList = array(); }

// 动漫 - 用搜索"动画"或热门TV的动画类
try {
    $animeData = $tmdb->discover('tv', array('with_genres' => 16, 'sort_by' => 'popularity.desc', 'page' => 1));
    $animeList = isset($animeData['results']) ? array_slice($animeData['results'], 0, 12) : array();
} catch (Exception $e) { $animeList = array(); }
if (empty($animeList)) {
    try {
        $animeSearch = $tmdb->search('动漫', 1, 'tv');
        $animeList = isset($animeSearch['results']) ? array_slice($animeSearch['results'], 0, 12) : array();
    } catch (Exception $e) { $animeList = array(); }
}

// 综艺
try {
    $varietyData = $tmdb->discover('tv', array('with_genres' => '10764,10767', 'sort_by' => 'popularity.desc', 'page' => 1));
    $varietyList = isset($varietyData['results']) ? array_slice($varietyData['results'], 0, 12) : array();
} catch (Exception $e) { $varietyList = array(); }
if (empty($varietyList)) {
    try {
        $varietySearch = $tmdb->search('综艺', 1, 'tv');
        $varietyList = isset($varietySearch['results']) ? array_slice($varietySearch['results'], 0, 12) : array();
    } catch (Exception $e) { $varietyList = array(); }
}

// 获取激活的公告
$activeAnnouncements = array();
try {
    $activeAnnouncements = db()->fetchAll("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
} catch (Exception $e) { $activeAnnouncements = array(); }

// 检查是否已读公告（勾选不再提示的）
$currentUserId = $currentUser ? intval($currentUser['id']) : 0;
$clientIp = get_client_ip();
if (!empty($activeAnnouncements)) {
    foreach ($activeAnnouncements as $idx => $ann) {
        $annId = intval($ann['id']);
        try {
            if ($currentUserId > 0) {
                $read = db()->fetchOne("SELECT dont_show_again FROM announcement_reads WHERE user_id = ? AND announcement_id = ? ORDER BY id DESC LIMIT 1", array($currentUserId, $annId));
            } else {
                $read = db()->fetchOne("SELECT dont_show_again FROM announcement_reads WHERE ip_address = ? AND announcement_id = ? ORDER BY id DESC LIMIT 1", array($clientIp, $annId));
            }
            if ($read && intval($read['dont_show_again']) === 1) {
                unset($activeAnnouncements[$idx]);
            }
        } catch (Exception $e) {}
    }
    $activeAnnouncements = array_values($activeAnnouncements);
}

// 处理用户收藏状态
$userFavorites = array();
if ($currentUser) {
    try {
        $favRows = db()->fetchAll("SELECT movie_id FROM favorites WHERE user_id = ?", array($currentUser['id']));
        foreach ($favRows as $f) { $userFavorites[$f['movie_id']] = true; }
    } catch (Exception $e) {}
}

/**
 * 渲染电影卡片
 */
function renderMovieCard($item, $tmdb, $userFavorites, $defaultType = 'movie') {
    $id = isset($item['id']) ? $item['id'] : 0;
    $mediaType = isset($item['media_type']) ? $item['media_type'] : $defaultType;
    $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '未知');
    $poster = $tmdb->getImageUrl(isset($item['poster_path']) ? $item['poster_path'] : '', 'w500');
    $rating = isset($item['vote_average']) ? number_format($item['vote_average'], 1) : '0.0';
    $year = '';
    if (isset($item['release_date'])) $year = substr($item['release_date'], 0, 4);
    elseif (isset($item['first_air_date'])) $year = substr($item['first_air_date'], 0, 4);
    
    $typeText = array('movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺');
    $typeLabel = isset($typeText[$mediaType]) ? $typeText[$mediaType] : '影视';
    
    // 集数信息
    $extra = '';
    if ($mediaType !== 'movie' && isset($item['status'])) {
        $numEp = isset($item['number_of_episodes']) ? $item['number_of_episodes'] : '';
        if ($numEp) $extra = $numEp . '集全';
        else $extra = '更新中';
    }
    if (!$extra && $year) $extra = $year;
    
    $favorited = isset($userFavorites[$id]);
    $favData = htmlspecialchars(json_encode(array(
        'id' => strval($id),
        'name' => $title,
        'poster' => $poster,
        'type' => $mediaType,
        'year' => $year ? intval($year) : null,
        'rating' => floatval($rating)
    )), ENT_QUOTES, 'UTF-8');
    
    $detailUrl = BASE_URL . '/detail.php?id=' . $id . '&type=' . $mediaType;
    ?>
    <div class="movie-card" data-movie-link="<?php echo e($detailUrl); ?>">
        <div class="movie-card-poster">
            <?php if ($poster): ?>
                <img src="<?php echo e($poster); ?>" alt="<?php echo e($title); ?>" loading="lazy" onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="poster-placeholder" style="display:none;">🎬</div>
            <?php else: ?>
                <div class="poster-placeholder">🎬 <?php echo e(mb_substr($title, 0, 4, 'UTF-8')); ?></div>
            <?php endif; ?>
            <?php if (floatval($rating) > 0): ?>
            <div class="card-rating">
                <span class="star-icon"></span>
                <span><?php echo e($rating); ?></span>
            </div>
            <?php endif; ?>
            <button class="card-favorite <?php echo $favorited ? 'active' : ''; ?>" data-favorite="<?php echo $favData; ?>" title="<?php echo $favorited ? '取消收藏' : '收藏'; ?>">
                <span class="heart-icon"></span>
            </button>
        </div>
        <div class="movie-card-info">
            <div class="movie-card-title" title="<?php echo e($title); ?>"><?php echo e($title); ?></div>
            <div class="movie-card-meta">
                <span class="movie-card-type"><?php echo e($typeLabel); ?></span>
                <span><?php echo e($extra); ?></span>
            </div>
        </div>
    </div>
    <?php
}

include ROOT_PATH . '/includes/header.php';
?>

<!-- Main Content -->
<main class="container" style="padding-top:24px;">

    <!-- Hero Banner -->
    <div class="hero-banner">
        <?php foreach ($heroData as $idx => $hero): ?>
        <div data-hero-index="<?php echo $idx; ?>" style="display:<?php echo $idx === 0 ? 'block' : 'none'; ?>;">
            <?php if (!empty($hero['backdrop'])): ?>
            <div class="hero-bg" style="background-image:url('<?php echo e($hero['backdrop']); ?>');"></div>
            <?php else: ?>
            <div class="hero-bg" style="background:linear-gradient(135deg,#1a1a25 0%,#2d1b69 50%,#1a1a25 100%);"></div>
            <?php endif; ?>
            <div class="hero-content">
                <div class="hero-tag">🔥 正在热映</div>
                <h1 class="hero-title"><?php echo e($hero['title']); ?></h1>
                <div class="hero-meta">
                    <span class="rating-badge">
                        <span class="star-icon"></span>
                        <?php echo e($hero['rating']); ?>
                    </span>
                    <?php if ($hero['year']): ?>
                    <span style="color:var(--text-secondary);">📅 <?php echo e($hero['year']); ?></span>
                    <?php endif; ?>
                    <div class="hero-genres">
                        <?php foreach ($hero['genres'] as $g): ?>
                        <span class="genre-tag"><?php echo e($g); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($hero['overview']): ?>
                <p class="hero-overview"><?php echo e($hero['overview']); ?></p>
                <?php endif; ?>
                <div class="hero-actions">
                    <a href="<?php echo e($hero['play_url']); ?>" class="btn btn-primary btn-lg">
                        <span class="play-icon play-icon-lg"></span>
                        立即播放
                    </a>
                    <a href="<?php echo e($hero['detail_url']); ?>" class="btn btn-outline btn-lg">
                        <span class="plus-icon"></span>
                        查看详情
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="hero-dots">
            <?php foreach ($heroData as $idx => $_): ?>
            <span class="hero-dot <?php echo $idx === 0 ? 'active' : ''; ?>"></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 热门推荐 -->
    <section class="section" style="padding-top:0;">
        <div class="section-header">
            <h2 class="section-title">🔥 热门推荐</h2>
            <a href="<?php echo BASE_URL; ?>/search.php?q=%E7%83%AD%E9%97%A8" class="view-more">
                查看更多
                <span class="arrow-right"></span>
            </a>
        </div>
        <div class="movies-grid">
            <?php
            $count = 0;
            foreach ($popularList as $item) {
                if ($count >= 12) break;
                $mt = isset($item['media_type']) ? $item['media_type'] : (isset($item['number_of_seasons']) ? 'tv' : 'movie');
                renderMovieCard($item, $tmdb, $userFavorites, $mt);
                $count++;
            }
            // 补充
            while ($count < 6) {
                renderMovieCard(
                    array('id'=>100+$count, 'title'=>'示例影片'.($count+1), 'vote_average'=>8.0+($count*0.1), 'release_date'=>'2024-01-01'),
                    $tmdb, $userFavorites, 'movie'
                );
                $count++;
            }
            ?>
        </div>
    </section>

    <!-- 电影 -->
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:var(--primary-bg);border-radius:6px;color:var(--primary-light);font-size:14px;">🎬</span>
                电影
            </h2>
            <a href="<?php echo BASE_URL; ?>/category.php?type=movie" class="view-more">查看更多 <span class="arrow-right"></span></a>
        </div>
        <div class="genre-tabs">
            <button class="genre-tab active">全部</button>
            <button class="genre-tab" onclick="window.location.href='<?php echo BASE_URL; ?>/category.php?type=movie&genre=28'">动作</button>
            <button class="genre-tab" onclick="window.location.href='<?php echo BASE_URL; ?>/category.php?type=movie&genre=35'">喜剧</button>
            <button class="genre-tab" onclick="window.location.href='<?php echo BASE_URL; ?>/category.php?type=movie&genre=10749'">爱情</button>
            <button class="genre-tab" onclick="window.location.href='<?php echo BASE_URL; ?>/category.php?type=movie&genre=878'">科幻</button>
            <button class="genre-tab" onclick="window.location.href='<?php echo BASE_URL; ?>/category.php?type=movie&genre=9648'">悬疑</button>
            <button class="genre-tab" onclick="window.location.href='<?php echo BASE_URL; ?>/category.php?type=movie&genre=18'">剧情</button>
        </div>
        <div class="movies-grid">
            <?php
            $count = 0;
            foreach ($movieList as $item) {
                if ($count >= 6) break;
                renderMovieCard($item, $tmdb, $userFavorites, 'movie');
                $count++;
            }
            while ($count < 6) {
                renderMovieCard(
                    array('id'=>200+$count, 'title'=>'电影推荐'.($count+1), 'vote_average'=>7.0+($count*0.2), 'release_date'=>'2024-0'.($count+1).'-15'),
                    $tmdb, $userFavorites, 'movie'
                );
                $count++;
            }
            ?>
        </div>
    </section>

    <!-- 电视剧 -->
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:rgba(59,130,246,0.15);border-radius:6px;color:#60a5fa;font-size:14px;">📺</span>
                电视剧
            </h2>
            <a href="<?php echo BASE_URL; ?>/category.php?type=tv" class="view-more">查看更多 <span class="arrow-right"></span></a>
        </div>
        <div class="movies-grid">
            <?php
            $count = 0;
            foreach ($tvList as $item) {
                if ($count >= 6) break;
                renderMovieCard($item, $tmdb, $userFavorites, 'tv');
                $count++;
            }
            while ($count < 6) {
                renderMovieCard(
                    array('id'=>300+$count, 'name'=>'电视剧推荐'.($count+1), 'vote_average'=>8.0+($count*0.1), 'first_air_date'=>'2024-0'.($count+1).'-01'),
                    $tmdb, $userFavorites, 'tv'
                );
                $count++;
            }
            ?>
        </div>
    </section>

    <!-- 动漫 -->
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:rgba(236,72,153,0.15);border-radius:6px;color:#f472b6;font-size:14px;">🎌</span>
                动漫
            </h2>
            <a href="<?php echo BASE_URL; ?>/category.php?type=anime" class="view-more">查看更多 <span class="arrow-right"></span></a>
        </div>
        <div class="movies-grid">
            <?php
            $count = 0;
            foreach ($animeList as $item) {
                if ($count >= 6) break;
                renderMovieCard($item, $tmdb, $userFavorites, 'anime');
                $count++;
            }
            while ($count < 6) {
                renderMovieCard(
                    array('id'=>400+$count, 'name'=>'动漫推荐'.($count+1), 'vote_average'=>9.0-($count*0.1), 'first_air_date'=>'2024-0'.($count+1).'-20'),
                    $tmdb, $userFavorites, 'anime'
                );
                $count++;
            }
            ?>
        </div>
    </section>

    <!-- 综艺 -->
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:rgba(245,158,11,0.15);border-radius:6px;color:#fbbf24;font-size:14px;">🎤</span>
                综艺
            </h2>
            <a href="<?php echo BASE_URL; ?>/category.php?type=variety" class="view-more">查看更多 <span class="arrow-right"></span></a>
        </div>
        <div class="movies-grid">
            <?php
            $count = 0;
            foreach ($varietyList as $item) {
                if ($count >= 6) break;
                renderMovieCard($item, $tmdb, $userFavorites, 'variety');
                $count++;
            }
            while ($count < 6) {
                renderMovieCard(
                    array('id'=>500+$count, 'name'=>'综艺推荐'.($count+1), 'vote_average'=>7.5+($count*0.1), 'first_air_date'=>'2024-0'.($count+1).'-10'),
                    $tmdb, $userFavorites, 'variety'
                );
                $count++;
            }
            ?>
        </div>
    </section>

</main>

<!-- 公告弹窗（仅首页显示） -->
<div id="announcement-modal" class="announcement-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:20px;">
    <div class="announcement-card" id="announcement-card" style="position:relative;max-width:520px;width:100%;background:linear-gradient(180deg,#1f1f2e 0%,#171723 100%);border:1px solid rgba(255,255,255,0.08);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.5);overflow:hidden;animation:annBounceIn 0.4s cubic-bezier(0.34,1.56,0.64,1);">
        <div class="ann-banner" id="ann-banner" style="height:100px;background:var(--primary-gradient);position:relative;overflow:hidden;">
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:300px;height:300px;background:rgba(255,255,255,0.08);border-radius:50%;"></div>
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:180px;height:180px;background:rgba(255,255,255,0.1);border-radius:50%;"></div>
            <div id="ann-icon" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:48px;filter:drop-shadow(0 4px 10px rgba(0,0,0,0.3));">📢</div>
        </div>
        <button class="ann-close" id="ann-close" style="position:absolute;top:12px;right:12px;width:36px;height:36px;border-radius:50%;background:rgba(0,0,0,0.4);color:white;border:none;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);transition:0.2s;z-index:10;" onmouseover="this.style.background='rgba(239,68,68,0.9)'" onmouseout="this.style.background='rgba(0,0,0,0.4)'">✕</button>
        <div style="padding:24px 28px 20px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span id="ann-tag" class="ann-tag" style="padding:4px 12px;background:var(--primary-bg);color:var(--primary-color);border-radius:20px;font-size:12px;font-weight:600;">📣 公告</span>
                <span id="ann-date" style="font-size:12px;color:var(--text-muted);"></span>
            </div>
            <h2 id="ann-title" style="font-size:22px;font-weight:700;margin:0 0 14px;color:#fff;line-height:1.4;"></h2>
            <div id="ann-content" style="color:rgba(255,255,255,0.82);line-height:1.8;font-size:14px;max-height:320px;overflow-y:auto;padding:2px;"></div>
            <div style="margin-top:20px;padding-top:18px;border-top:1px solid rgba(255,255,255,0.08);">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;padding:6px 0;">
                    <input type="checkbox" id="ann-dismiss" style="width:18px;height:18px;accent-color:var(--primary-color);border-radius:4px;">
                    <span style="font-size:13px;color:rgba(255,255,255,0.7);">不再提示此公告（有新公告仍会显示）</span>
                </label>
            </div>
            <div style="margin-top:18px;display:flex;gap:12px;">
                <button class="btn btn-primary" style="flex:1;height:44px;font-size:14px;" id="ann-ok-btn">知道了 ✅</button>
            </div>
        </div>
    </div>
</div>
<style>
@keyframes annBounceIn { 0% { opacity:0; transform:scale(0.8) translateY(20px);} 60% { opacity:1; transform:scale(1.04) translateY(-4px);} 100% { opacity:1; transform:scale(1) translateY(0);} }
@keyframes annBounceOut { 0% { opacity:1; transform:scale(1);} 100% { opacity:0; transform:scale(0.9) translateY(10px);} }
@media (max-width:520px) {
    #announcement-card { max-width:100%; border-radius:16px; }
    #announcement-card h2 { font-size:18px; }
    #announcement-card > div:nth-child(4) { padding:20px; }
}
</style>
<script>
(function(){
    // 公告弹窗（只在首页加载一次）
    window.addEventListener('DOMContentLoaded', function(){
        setTimeout(function(){
            fetch('<?php echo BASE_URL;?>/api/announcement.php', {method:'GET', credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.code === 200 && res.data) showAnnouncement(res.data);
            }).catch(function(){});
        }, 1200);
    });
    function showAnnouncement(a) {
        var overlay = document.getElementById('announcement-modal');
        if (!overlay) return;
        var impColors = {high:'linear-gradient(135deg,#dc2626 0%,#f97316 100%)', normal:'var(--primary-gradient)', low:'linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%)'};
        var impIcons  = {high:'🚨', normal:'📣', low:'ℹ️'};
        var impTexts  = {high:'🚨 紧急公告', normal:'📣 公告', low:'ℹ️ 提示'};
        document.getElementById('ann-banner').style.background = impColors[a.importance] || impColors.normal;
        document.getElementById('ann-icon').textContent = impIcons[a.importance] || '📣';
        document.getElementById('ann-tag').textContent = (a.importance_text && a.importance_text[a.importance]) ? a.importance_text[a.importance] : (impTexts[a.importance] || '📣 公告');
        document.getElementById('ann-title').textContent = a.title;
        document.getElementById('ann-content').innerHTML = a.content;
        document.getElementById('ann-date').textContent = '📅 ' + a.updated_at.substring(0,10);
        overlay.style.display = 'flex';
        var card = document.getElementById('announcement-card');
        card.style.animation = 'annBounceIn 0.4s cubic-bezier(0.34,1.56,0.64,1)';
        function closeAnn() {
            card.style.animation = 'annBounceOut 0.28s ease forwards';
            setTimeout(function(){ overlay.style.display = 'none'; }, 260);
            var dismiss = document.getElementById('ann-dismiss').checked;
            if (dismiss) {
                fetch('<?php echo BASE_URL;?>/api/announcement.php', {
                    method:'POST', credentials:'same-origin',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({action:'dismiss', announcement_id: parseInt(a.id)})
                });
            }
        }
        document.getElementById('ann-close').onclick = closeAnn;
        document.getElementById('ann-ok-btn').onclick = closeAnn;
        overlay.onclick = function(e){ if (e.target === overlay) closeAnn(); };
    }
})();
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
