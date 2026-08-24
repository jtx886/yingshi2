<?php
/**
 * 搜索页
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/api/tmdb.php';

$currentUser = current_user();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all';

$pageTitle = $q ? '搜索: ' . $q : '搜索';
$currentPage = 'search';

$tmdb = new TMDBApi();

$results = array();
$totalPages = 1;
$totalResults = 0;

if ($q) {
    try {
        if ($type === 'movie') {
            $data = $tmdb->search($q, $page, 'movie');
        } elseif ($type === 'tv') {
            $data = $tmdb->search($q, $page, 'tv');
        } else {
            $data = $tmdb->search($q, $page, 'multi');
        }
        $results = isset($data['results']) ? $data['results'] : array();
        $totalPages = isset($data['total_pages']) ? min(500, intval($data['total_pages'])) : 1;
        $totalResults = isset($data['total_results']) ? intval($data['total_results']) : count($results);
    } catch (Exception $e) {
        $results = array();
    }
}

// 当前用户收藏
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
                <?php if ($q): ?>
                🔍 搜索 "<?php echo e($q); ?>"
                <?php else: ?>
                🔍 搜索影视
                <?php endif; ?>
                <?php if ($totalResults): ?>
                <span style="font-size:14px;font-weight:400;color:var(--text-muted);margin-left:12px;">
                    找到 <?php echo number_format($totalResults); ?> 个结果
                </span>
                <?php endif; ?>
            </h2>
        </div>

        <!-- 搜索框 -->
        <div style="max-width:600px;margin:0 auto 32px;">
            <form action="" method="GET" style="display:flex;gap:10px;">
                <input type="text" name="q" class="form-input" placeholder="输入电影、电视剧、动漫名称..." value="<?php echo e($q); ?>" style="height:48px;font-size:16px;">
                <button class="btn btn-primary" style="height:48px;padding:0 24px;">搜索</button>
            </form>
            <!-- 类型筛选 -->
            <div style="display:flex;gap:10px;margin-top:16px;justify-content:center;flex-wrap:wrap;">
                <?php
                $types = array('all'=>'全部', 'movie'=>'电影', 'tv'=>'电视剧', 'anime'=>'动漫', 'variety'=>'综艺');
                foreach ($types as $tk => $tv):
                    $active = ($type === $tk || ($tk === 'anime' && $type === 'anime'));
                    if ($tk === 'all') $active = $type === 'all';
                ?>
                <a href="?q=<?php echo urlencode($q); ?>&type=<?php echo $tk; ?>"
                   class="genre-tab <?php echo ($type === $tk || ($tk === 'all' && !in_array($type, array_keys($types)))) ? 'active' : ''; ?>">
                    <?php echo $tv; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($q): ?>
        <?php if (!empty($results)): ?>
        <div class="movies-grid">
            <?php
            foreach ($results as $item) {
                $id = isset($item['id']) ? $item['id'] : 0;
                $mt = isset($item['media_type']) ? $item['media_type'] : (isset($item['number_of_seasons']) ? 'tv' : 'movie');
                if ($mt === 'person') continue; // 跳过人物
                $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '未知');
                $poster = $tmdb->getImageUrl(isset($item['poster_path']) ? $item['poster_path'] : '', 'w500');
                $rating = isset($item['vote_average']) ? number_format($item['vote_average'], 1) : '0.0';
                $year = '';
                if (isset($item['release_date'])) $year = substr($item['release_date'], 0, 4);
                elseif (isset($item['first_air_date'])) $year = substr($item['first_air_date'], 0, 4);
                $typeText = array('movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺');
                $typeLabel = isset($typeText[$mt]) ? $typeText[$mt] : '影视';
                $extra = '';
                if ($mt !== 'movie' && $year) $extra = $year;
                elseif ($year) $extra = $year;

                $favData = htmlspecialchars(json_encode(array(
                    'id' => strval($id), 'name' => $title, 'poster' => $poster,
                    'type' => $mt, 'year' => $year ? intval($year) : null, 'rating' => floatval($rating)
                )), ENT_QUOTES, 'UTF-8');
                $favorited = isset($userFavorites[$id]);
                $detailUrl = BASE_URL . '/detail.php?id=' . $id . '&type=' . $mt;
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
            <?php
            }
            ?>
        </div>

        <!-- 分页 -->
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
            <div style="font-size:80px;margin-bottom:24px;">🔍</div>
            <h3 style="font-size:22px;margin-bottom:12px;">没有找到相关结果</h3>
            <p style="color:var(--text-muted);">试试换个关键词搜索吧</p>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div style="text-align:center;padding:60px 20px;">
            <div style="font-size:72px;margin-bottom:20px;">🎬</div>
            <h3 style="font-size:20px;margin-bottom:12px;">输入关键词开始搜索</h3>
            <p style="color:var(--text-muted);margin-bottom:24px;">支持搜索电影、电视剧、动漫名称</p>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <?php $hotSearches = array('流浪地球','庆余年','海贼王','斗破苍穹','封神第一部','奥本海默','漫长的季节','鬼灭之刃'); ?>
                <?php foreach ($hotSearches as $hs): ?>
                <a href="?q=<?php echo urlencode($hs); ?>" class="genre-tab">🔥 <?php echo e($hs); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

</main>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
