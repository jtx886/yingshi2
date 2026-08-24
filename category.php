<?php
/**
 * 分类页
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/api/tmdb.php';

$type = isset($_GET['type']) ? trim($_GET['type']) : 'movie';
$genre = isset($_GET['genre']) ? intval($_GET['genre']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'popularity';

$typeLabels = array('movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺');
$pageTitle = isset($typeLabels[$type]) ? $typeLabels[$type] : '分类';
$currentPage = $type;

$tmdb = new TMDBApi();

// 分类映射
$movieGenres = array(0=>'全部',28=>'动作',12=>'冒险',16=>'动画',35=>'喜剧',80=>'犯罪',99=>'纪录',18=>'剧情',10751=>'家庭',14=>'奇幻',36=>'历史',27=>'恐怖',10402=>'音乐',9648=>'悬疑',10749=>'爱情',878=>'科幻',53=>'惊悚',10752=>'战争',37=>'西部');
$tvGenres = array(0=>'全部',10759=>'动作冒险',16=>'动画',35=>'喜剧',80=>'犯罪',99=>'纪录',18=>'剧情',10751=>'家庭',10762=>'儿童',9648=>'悬疑',10763=>'新闻',10764=>'真人秀',10765=>'科幻奇幻',10766=>'肥皂剧',10767=>'脱口秀',10768=>'战争政治',37=>'西部');
$animeGenres = array(0=>'全部',16=>'动画',10759=>'热血',18=>'剧情',10765=>'奇幻科幻',35=>'搞笑',10749=>'恋爱',9648=>'悬疑',28=>'动作');
$varietyGenres = array(0=>'全部',10764=>'真人秀',10767=>'脱口秀',10402=>'音乐',35=>'喜剧',99=>'纪实');

$currentGenres = $type === 'movie' ? $movieGenres : ($type === 'tv' ? $tvGenres : ($type === 'anime' ? $animeGenres : $varietyGenres));

$results = array();
$totalPages = 1;

// 获取数据
try {
    $params = array('page' => $page, 'sort_by' => 'popularity.desc');
    if ($genre > 0) $params['with_genres'] = $genre;

    if ($type === 'movie') {
        $data = $tmdb->discover('movie', $params);
    } elseif ($type === 'anime') {
        $params['with_genres'] = $genre > 0 ? $genre : 16;
        $data = $tmdb->discover('tv', $params);
    } elseif ($type === 'variety') {
        $params['with_genres'] = $genre > 0 ? $genre : '10764,10767,10402';
        $data = $tmdb->discover('tv', $params);
    } else {
        $data = $tmdb->discover('tv', $params);
    }
    $results = isset($data['results']) ? $data['results'] : array();
    $totalPages = isset($data['total_pages']) ? min(500, intval($data['total_pages'])) : 1;
} catch (Exception $e) {
    $results = array();
}

// 如果没数据，兜底
if (empty($results)) {
    for ($i = 1; $i <= 18; $i++) {
        $mock = $type === 'movie'
            ? array('id' => 900 + $i, 'title' => $typeLabels[$type] . '推荐' . $i, 'vote_average' => 6.5 + $i * 0.1, 'release_date' => '2024-' . str_pad(($i % 12) + 1, 2, '0', STR_PAD_LEFT) . '-10')
            : array('id' => 900 + $i, 'name' => $typeLabels[$type] . '推荐' . $i, 'vote_average' => 7.0 + $i * 0.1, 'first_air_date' => '2024-' . str_pad(($i % 12) + 1, 2, '0', STR_PAD_LEFT) . '-10');
        $results[] = $mock;
    }
    $totalPages = 5;
}

$currentUser = current_user();
$userFavorites = array();
if ($currentUser) {
    try {
        $favs = db()->fetchAll("SELECT movie_id FROM favorites WHERE user_id = ?", array(intval($currentUser['id'])));
        foreach ($favs as $f) $userFavorites[$f['movie_id']] = true;
    } catch (Exception $e) {}
}

include ROOT_PATH . '/includes/header.php';
?>

<main class="container" style="padding-top:24px;padding-bottom:40px;">

    <section class="section" style="padding-top:0;">
        <div class="section-header">
            <h2 class="section-title">
                <span style="font-size:22px;">
                    <?php
                    $icons = array('movie'=>'🎬','tv'=>'📺','anime'=>'🎌','variety'=>'🎤');
                    echo isset($icons[$type]) ? $icons[$type] : '🎬';
                    ?>
                </span>
                <?php echo $pageTitle; ?>
            </h2>
        </div>

        <!-- 分类标签 -->
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:24px;">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <span style="font-weight:600;color:var(--text-secondary);white-space:nowrap;">🎭 分类：</span>
                <div class="genre-tabs" style="margin:0;padding:0;flex:1;">
                    <?php foreach ($currentGenres as $gid => $gname): ?>
                    <a class="genre-tab <?php echo $gid === $genre ? 'active' : ''; ?>"
                       href="?type=<?php echo e($type); ?>&genre=<?php echo $gid; ?>">
                        <?php echo e($gname); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($results)): ?>
        <div class="movies-grid">
            <?php
            foreach ($results as $item) {
                $id = isset($item['id']) ? $item['id'] : 0;
                $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '未知');
                $poster = $tmdb->getImageUrl(isset($item['poster_path']) ? $item['poster_path'] : '', 'w500');
                $rating = isset($item['vote_average']) ? number_format($item['vote_average'], 1) : '0.0';
                $year = '';
                if (isset($item['release_date'])) $year = substr($item['release_date'], 0, 4);
                elseif (isset($item['first_air_date'])) $year = substr($item['first_air_date'], 0, 4);
                $typeText = $typeLabels;
                $typeLabel = isset($typeText[$type]) ? $typeText[$type] : '影视';
                $extra = $year;
                $favData = htmlspecialchars(json_encode(array(
                    'id' => strval($id), 'name' => $title, 'poster' => $poster,
                    'type' => $type, 'year' => $year ? intval($year) : null, 'rating' => floatval($rating)
                )), ENT_QUOTES, 'UTF-8');
                $favorited = isset($userFavorites[$id]);
                $detailUrl = BASE_URL . '/detail.php?id=' . $id . '&type=' . $type;
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
                    <div class="card-rating"><span class="star-icon"></span><span><?php echo e($rating); ?></span></div>
                    <?php endif; ?>
                    <button class="card-favorite <?php echo $favorited ? 'active' : ''; ?>" data-favorite="<?php echo $favData; ?>">
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
            <?php } ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top:32px;">
            <?php
            $prevDisabled = $page <= 1;
            $nextDisabled = $page >= $totalPages;
            $start = max(1, $page - 2);
            $end = min($totalPages, $start + 4);
            if ($end - $start < 4) $start = max(1, $end - 4);
            ?>
            <button <?php echo $prevDisabled ? 'disabled' : ''; ?> onclick="goPage(<?php echo $page - 1; ?>)">‹ 上一页</button>
            <?php for ($i = $start; $i <= $end; $i++): ?>
            <button class="<?php echo $i === $page ? 'active' : ''; ?>" onclick="goPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
            <?php endfor; ?>
            <button <?php echo $nextDisabled ? 'disabled' : ''; ?> onclick="goPage(<?php echo $page + 1; ?>)">下一页 ›</button>
        </div>
        <script>
            function goPage(p) {
                var params = new URLSearchParams(location.search);
                params.set('page', p);
                location.search = params.toString();
            }
        </script>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align:center;padding:80px 20px;">
            <div style="font-size:80px;margin-bottom:24px;">📭</div>
            <h3 style="font-size:22px;margin-bottom:12px;">暂无内容</h3>
            <p style="color:var(--text-muted);">试试选择其他分类吧</p>
        </div>
        <?php endif; ?>
    </section>

</main>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
