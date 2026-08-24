<?php
/**
 * 详情页
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/api/tmdb.php';
require_once ROOT_PATH . '/api/playsource.php';

$currentUser = current_user();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? trim($_GET['type']) : 'movie';
if (!$id) redirect(BASE_URL . '/index.php');

$currentPage = $type;
$pageTitle = '详情';

$tmdb = new TMDBApi();
$playSource = new PlaySourceApi();

// 获取详情
if ($type === 'movie') {
    $detail = $tmdb->getMovieDetail($id);
} else {
    $detail = $tmdb->getTVDetail($id);
}
if (!$detail || !isset($detail['id'])) {
    // 使用mock数据兜底
    if ($type === 'movie') {
        $detail = array(
            'id' => $id, 'media_type' => 'movie',
            'title' => '影片 ' . $id, 'original_title' => 'Movie ' . $id,
            'overview' => '精彩的影片内容简介。', 'vote_average' => 8.0,
            'poster_path' => '', 'backdrop_path' => '',
            'release_date' => '2024-01-01', 'runtime' => 120,
            'genres' => array(array('name' => '剧情')),
            'production_countries' => array(array('name' => '中国大陆')),
            'spoken_languages' => array(array('name' => '普通话', 'english_name' => 'Mandarin')),
            'credits' => array('cast' => array(), 'crew' => array()),
            'images' => array('posters' => array(), 'backdrops' => array())
        );
    } else {
        $detail = array(
            'id' => $id, 'media_type' => 'tv',
            'name' => '剧集 ' . $id, 'original_name' => 'TV ' . $id,
            'overview' => '精彩的剧集内容简介。', 'vote_average' => 8.0,
            'poster_path' => '', 'backdrop_path' => '',
            'first_air_date' => '2024-01-01',
            'number_of_seasons' => 1, 'number_of_episodes' => 24,
            'seasons' => array(array('season_number'=>1,'name'=>'第1季','episode_count'=>24,'poster_path'=>'','air_date'=>'2024-01-01','overview'=>'第1季')),
            'genres' => array(array('name' => '剧情')),
            'production_countries' => array(array('name' => '中国大陆')),
            'spoken_languages' => array(array('name' => '普通话', 'english_name' => 'Mandarin')),
            'credits' => array('cast' => array(), 'crew' => array()),
            'images' => array('posters' => array(), 'backdrops' => array())
        );
    }
}

$title = isset($detail['title']) ? $detail['title'] : (isset($detail['name']) ? $detail['name'] : '详情');
$pageTitle = $title;

$year = '';
if (isset($detail['release_date'])) $year = substr($detail['release_date'], 0, 4);
elseif (isset($detail['first_air_date'])) $year = substr($detail['first_air_date'], 0, 4);

$rating = isset($detail['vote_average']) ? number_format($detail['vote_average'], 1) : '0.0';
$backdrop = $tmdb->getImageUrl(isset($detail['backdrop_path']) ? $detail['backdrop_path'] : '', 'original');
$poster = $tmdb->getImageUrl(isset($detail['poster_path']) ? $detail['poster_path'] : '', 'w500');

// 判断是否国产（是否显示配音选择）
$countries = isset($detail['production_countries']) ? $detail['production_countries'] : array();
$isDomestic = false;
foreach ($countries as $c) {
    $cn = isset($c['name']) ? $c['name'] : '';
    if (strpos($cn, '中国') !== false || $cn === 'Mainland China' || $cn === 'Hong Kong' || $cn === 'Taiwan') {
        $isDomestic = true; break;
    }
}
// 如果名称是中文但没明确国家，也算可能国产（保守起见非国产显示配音选项）
if (!$isDomestic && empty($countries) && preg_match('/[\x{4e00}-\x{9fff}]/u', $title)) {
    // 不设为国产，给用户选择权
}

// 季选择
$seasonNum = isset($_GET['season']) ? intval($_GET['season']) : 1;
$seasons = isset($detail['seasons']) ? $detail['seasons'] : array();
$seasonDetail = null;
$episodes = array();

if ($type !== 'movie') {
    // 找当前季
    foreach ($seasons as $s) {
        if (intval($s['season_number']) === $seasonNum) {
            $seasonDetail = $s; break;
        }
    }
    if (!$seasonDetail && !empty($seasons)) {
        $seasonDetail = $seasons[0];
        $seasonNum = intval($seasonDetail['season_number']);
    }
    // 获取季详情（含剧集）
    try {
        $seasonFull = $tmdb->getSeasonDetail($id, $seasonNum);
        if ($seasonFull && isset($seasonFull['episodes'])) {
            $episodes = $seasonFull['episodes'];
        }
    } catch (Exception $e) { $episodes = array(); }
    // 兜底剧集
    if (empty($episodes)) {
        $epCount = $seasonDetail ? (isset($seasonDetail['episode_count']) ? intval($seasonDetail['episode_count']) : 24) : 24;
        for ($i = 1; $i <= $epCount; $i++) {
            $episodes[] = array(
                'episode_number' => $i,
                'name' => '第' . $i . '集',
                'overview' => '第' . $i . '集的精彩剧情内容。',
                'still_path' => '',
                'air_date' => '2024-01-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'runtime' => 45,
                'vote_average' => 8.0
            );
        }
    }
}

// 演员列表
$cast = array();
if (isset($detail['credits']['cast'])) {
    $cast = array_slice($detail['credits']['cast'], 0, 16);
}

// 获取播放源信息
$playDetail = $playSource->getDetail($id);
$playLists = isset($playDetail['play_lists']) ? $playDetail['play_lists'] : array();

// 检测是否有普通话/原画版本
$hasDubbing = false;
foreach ($playLists as $pl) {
    if (!empty($pl['has_mandarin'])) { $hasDubbing = true; break; }
}
// 非国产影视默认显示配音选项（即使播放源没有，也让用户选择）
$showDubbing = !$isDomestic && ($hasDubbing || $type !== 'movie');

// 当前用户收藏状态
$favorited = false;
$currentUser = current_user();
if ($currentUser) {
    try {
        $fav = db()->fetchOne("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?", array(intval($currentUser['id']), strval($id)));
        $favorited = $fav ? true : false;
    } catch (Exception $e) {}
}

include ROOT_PATH . '/includes/header.php';

$playUrl = BASE_URL . '/play.php?id=' . $id . '&type=' . $type . '&season=' . $seasonNum . '&ep=1';
$genresList = isset($detail['genres']) ? $detail['genres'] : array();
$genreNames = array();
foreach ($genresList as $g) { $genreNames[] = isset($g['name']) ? $g['name'] : ''; }

$favJson = htmlspecialchars(json_encode(array(
    'id' => strval($id),
    'name' => $title,
    'poster' => $poster,
    'type' => $type,
    'year' => $year ? intval($year) : null,
    'rating' => floatval($rating)
)), ENT_QUOTES, 'UTF-8');
?>

<main style="position:relative;">

    <!-- Detail Hero -->
    <div class="detail-hero">
        <?php if ($backdrop): ?>
        <div class="detail-bg" style="background-image:url('<?php echo e($backdrop); ?>');"></div>
        <?php else: ?>
        <div class="detail-bg" style="background:linear-gradient(135deg,#1a1a25,#2d1b69,#1a1a25);"></div>
        <?php endif; ?>
        <div class="container">
            <div class="detail-content">
                <div class="detail-poster">
                    <?php if ($poster): ?>
                    <img src="<?php echo e($poster); ?>" alt="<?php echo e($title); ?>" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;background:var(--bg-card-hover);font-size:60px;\\'>🎬</div>';">
                    <?php else: ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;background:var(--bg-card-hover);font-size:60px;">🎬</div>
                    <?php endif; ?>
                </div>
                <div class="detail-info">
                    <h1><?php echo e($title); ?></h1>
                    <?php if (isset($detail['tagline']) && $detail['tagline']): ?>
                    <p class="detail-tagline">"<?php echo e($detail['tagline']); ?>"</p>
                    <?php endif; ?>
                    <div class="detail-meta-row">
                        <div class="detail-rating">
                            <span class="star-icon" style="width:22px;height:22px;"></span>
                            <span class="detail-rating-score"><?php echo e($rating); ?></span>
                            <span class="detail-rating-max">/ 10</span>
                        </div>
                        <?php if ($year): ?>
                        <span style="color:var(--text-secondary);">📅 <?php echo e($year); ?></span>
                        <?php endif; ?>
                        <?php if ($type !== 'movie' && isset($detail['number_of_episodes'])): ?>
                        <span style="color:var(--text-secondary);">📺 <?php echo intval($detail['number_of_episodes']); ?> 集</span>
                        <?php endif; ?>
                        <?php if (isset($detail['runtime'])): ?>
                        <span style="color:var(--text-secondary);">⏱ <?php echo intval($detail['runtime']); ?> 分钟</span>
                        <?php endif; ?>
                        <div class="detail-genres">
                            <?php foreach ($genreNames as $gn): if ($gn): ?>
                            <span class="genre-tag"><?php echo e($gn); ?></span>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>

                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:24px;">
                        <a href="<?php echo e($playUrl); ?>" class="btn btn-primary btn-lg">
                            <span class="play-icon play-icon-lg"></span>
                            立即播放
                        </a>
                        <button class="btn btn-outline btn-lg card-favorite <?php echo $favorited ? 'active' : ''; ?>" style="display:inline-flex;" data-favorite="<?php echo $favJson; ?>">
                            <span class="heart-icon"></span>
                            <?php echo $favorited ? '已收藏' : '收藏'; ?>
                        </button>
                    </div>

                    <h3 class="detail-overview-title">剧情简介</h3>
                    <p class="detail-overview">
                        <?php echo isset($detail['overview']) && $detail['overview'] ? e($detail['overview']) : '暂无简介。'; ?>
                    </p>

                    <div class="detail-info-grid">
                        <?php
                        $infoItems = array();
                        if (isset($detail['original_title']) && $detail['original_title'] !== $title) $infoItems['原名'] = $detail['original_title'];
                        elseif (isset($detail['original_name']) && $detail['original_name'] !== $title) $infoItems['原名'] = $detail['original_name'];
                        if (!empty($countries)) {
                            $cn = array(); foreach ($countries as $c) $cn[] = isset($c['name']) ? $c['name'] : '';
                            $infoItems['地区'] = implode(' / ', $cn);
                        }
                        $langs = isset($detail['spoken_languages']) ? $detail['spoken_languages'] : array();
                        if (!empty($langs)) {
                            $ln = array(); foreach ($langs as $l) $ln[] = isset($l['name']) ? $l['name'] : (isset($l['english_name']) ? $l['english_name'] : '');
                            $infoItems['语言'] = implode(' / ', $ln);
                        }
                        if (isset($detail['status'])) $infoItems['状态'] = $detail['status'] === 'Released' || $detail['status'] === 'Returning Series' ? '已上映' : $detail['status'];
                        foreach ($infoItems as $k => $v):
                        ?>
                        <div class="detail-info-item">
                            <div class="detail-info-label"><?php echo e($k); ?></div>
                            <div class="detail-info-value"><?php echo e($v); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="padding-bottom:40px;">

        <?php if ($type !== 'movie' && count($seasons) > 1): ?>
        <!-- 季选择 -->
        <div class="season-selector">
            <span class="season-selector-label">📚 选择季：</span>
            <div class="season-tabs">
                <?php foreach ($seasons as $s):
                    $sNum = intval($s['season_number']);
                    $sName = isset($s['name']) ? $s['name'] : '第' . $sNum . '季';
                    $active = $sNum === $seasonNum;
                    $epCount = isset($s['episode_count']) ? intval($s['episode_count']) : 0;
                ?>
                <a class="season-tab <?php echo $active ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/detail.php?id=<?php echo $id; ?>&type=<?php echo e($type); ?>&season=<?php echo $sNum; ?>">
                    <?php echo e($sName); ?> <?php echo $epCount ? "($epCount)" : ''; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($showDubbing): ?>
        <!-- 配音选择 -->
        <div class="dubbing-selector">
            <span class="dubbing-label">🎙 配音选择：</span>
            <div class="dubbing-options" id="dubbing-options">
                <button class="dubbing-option active" data-dub="original">🔊 原画</button>
                <button class="dubbing-option" data-dub="mandarin">🗣 普通话</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($type !== 'movie'): ?>
        <!-- 剧集列表 -->
        <div class="episodes-section">
            <div class="episodes-header">
                <h2 class="episodes-title">
                    📺 剧集列表
                    <span class="episodes-count">共 <?php echo count($episodes); ?> 集</span>
                </h2>
            </div>
            <div class="episodes-grid">
                <?php foreach ($episodes as $ep):
                    $epNum = isset($ep['episode_number']) ? intval($ep['episode_number']) : 0;
                    $epName = isset($ep['name']) ? $ep['name'] : '第' . $epNum . '集';
                    $epStill = $tmdb->getImageUrl(isset($ep['still_path']) ? $ep['still_path'] : '', 'w300');
                    $epRating = isset($ep['vote_average']) ? number_format($ep['vote_average'], 1) : '';
                    $epRuntime = isset($ep['runtime']) ? intval($ep['runtime']) : 0;
                    $epOverview = isset($ep['overview']) ? $ep['overview'] : '';
                    $singlePlayUrl = BASE_URL . '/play.php?id=' . $id . '&type=' . $type . '&season=' . $seasonNum . '&ep=' . $epNum;
                ?>
                <div class="episode-card" onclick="window.location.href='<?php echo e($singlePlayUrl); ?>'">
                    <div class="episode-thumb">
                        <?php if ($epStill): ?>
                        <img src="<?php echo e($epStill); ?>" alt="<?php echo e($epName); ?>" loading="lazy" onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\\'poster-placeholder\\' style=\\'display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;color:var(--text-muted);\\'>🎬<div class=\\'episode-num-badge\\'><?php echo $epNum; ?></div></div>';">
                        <div class="episode-num-badge">第<?php echo $epNum; ?>集</div>
                        <?php else: ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;background:var(--bg-dark);font-size:22px;color:var(--text-muted);">🎬</div>
                        <div class="episode-num-badge">第<?php echo $epNum; ?>集</div>
                        <?php endif; ?>
                        <div class="episode-thumb-overlay">
                            <span class="play-icon" style="border-left-color:white;border-top-width:8px;border-bottom-width:8px;border-left-width:14px;"></span>
                        </div>
                    </div>
                    <div class="episode-info">
                        <div class="episode-name" title="<?php echo e($epName); ?>"><?php echo e($epName); ?></div>
                        <div class="episode-meta">
                            <?php if ($epRating): ?>
                            <span class="episode-rating">⭐ <?php echo e($epRating); ?></span>
                            <?php endif; ?>
                            <?php if ($epRuntime): ?>
                            <span>⏱ <?php echo $epRuntime; ?>分钟</span>
                            <?php endif; ?>
                            <?php if (isset($ep['air_date'])): ?>
                            <span>📅 <?php echo e(substr($ep['air_date'], 0, 10)); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($epOverview): ?>
                        <div class="episode-overview"><?php echo e($epOverview); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 演员 -->
        <?php if (!empty($cast)): ?>
        <section class="cast-section">
            <div class="section-header">
                <h2 class="section-title">👥 主要演员</h2>
            </div>
            <div class="cast-grid">
                <?php foreach ($cast as $c):
                    $cName = isset($c['name']) ? $c['name'] : '演员';
                    $cChar = isset($c['character']) ? $c['character'] : '';
                    $cProfile = $tmdb->getImageUrl(isset($c['profile_path']) ? $c['profile_path'] : '', 'w185');
                    $cInitial = mb_substr($cName, 0, 1, 'UTF-8');
                ?>
                <div class="cast-card">
                    <div class="cast-avatar">
                        <?php if ($cProfile): ?>
                        <img src="<?php echo e($cProfile); ?>" alt="<?php echo e($cName); ?>" loading="lazy" onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\\'cast-avatar-placeholder\\'><?php echo e($cInitial); ?></div>';">
                        <?php else: ?>
                        <div class="cast-avatar-placeholder"><?php echo e($cInitial); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="cast-name" title="<?php echo e($cName); ?>"><?php echo e($cName); ?></div>
                    <?php if ($cChar): ?>
                    <div class="cast-character" title="<?php echo e($cChar); ?>"><?php echo e($cChar); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>
</main>

<script>
// 配音选择 - 存储在localStorage，播放页读取
document.querySelectorAll('#dubbing-options .dubbing-option').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#dubbing-options .dubbing-option').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        localStorage.setItem('jay_dub_' + <?php echo $id; ?>, btn.getAttribute('data-dub'));
        // 给所有播放链接加参数
        document.querySelectorAll('a[href*="play.php?id=<?php echo $id; ?>"]').forEach(function(a) {
            var url = new URL(a.href, window.location.origin);
            url.searchParams.set('dub', btn.getAttribute('data-dub'));
            a.href = url.pathname + url.search + url.hash;
        });
    });
});
// 恢复上次选择
var savedDub = localStorage.getItem('jay_dub_' + <?php echo $id; ?>);
if (savedDub) {
    var b = document.querySelector('#dubbing-options [data-dub="' + savedDub + '"]');
    if (b) b.click();
}
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
