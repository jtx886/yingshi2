<?php
/**
 * 播放页 - 需登录才能观看
 */
require_once dirname(__FILE__) . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/api/tmdb.php';
require_once ROOT_PATH . '/api/playsource.php';

// 必须登录
if (!is_logged_in()) {
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/index.php';
    $redirect = base64_encode($requestUri);
    redirect(BASE_URL . '/login.php?need_login=1&redirect=' . $redirect);
}

$currentUser = current_user();
if (is_banned($currentUser)) {
    die('您的账号已被封禁。');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? trim($_GET['type']) : 'movie';
$season = isset($_GET['season']) ? intval($_GET['season']) : 1;
$ep = isset($_GET['ep']) ? intval($_GET['ep']) : 1;
$dub = isset($_GET['dub']) ? trim($_GET['dub']) : '';
$sourceId = isset($_GET['source']) ? intval($_GET['source']) : 0;
if (!$id) redirect(BASE_URL . '/index.php');

$currentPage = $type;
$tmdb = new TMDBApi();
$playSource = new PlaySourceApi();

// 获取基本信息
if ($type === 'movie') {
    $basic = $tmdb->getMovieDetail($id);
} else {
    $basic = $tmdb->getTVDetail($id);
}
$title = isset($basic['title']) ? $basic['title'] : (isset($basic['name']) ? $basic['name'] : '播放');
$pageTitle = $title;
$poster = $tmdb->getImageUrl(isset($basic['poster_path']) ? $basic['poster_path'] : '', 'w500');

// 获取播放源详情
$playDetail = $playSource->getDetail($id, $sourceId);
$playLists = isset($playDetail['play_lists']) ? $playDetail['play_lists'] : array();

// 如果没有播放线路，生成默认mock
if (empty($playLists)) {
    $mock = $playSource->search($title);
    if (!empty($mock)) {
        $playDetail = $playSource->getDetail($mock[0]['id'], $sourceId);
        $playLists = isset($playDetail['play_lists']) ? $playDetail['play_lists'] : array();
    }
}

// 默认兜底播放源
if (empty($playLists)) {
    $defaultEpisodes = array();
    $epCount = $type === 'movie' ? 1 : 24;
    for ($i = 1; $i <= $epCount; $i++) {
        $defaultEpisodes[] = array(
            'episode' => $i,
            'name' => $type === 'movie' ? '正片' : ('第' . $i . '集'),
            'url' => 'https://demo.example.com/video/' . $id . '/' . $i . '.m3u8',
            'play_url' => DEFAULT_PARSER_URL . 'https://demo.example.com/video/' . $id . '/' . $i . '.m3u8',
            'dub' => 'original'
        );
        $defaultEpisodes[] = array(
            'episode' => $i,
            'name' => $type === 'movie' ? '正片 普通话' : ('第' . $i . '集 普通话'),
            'url' => 'https://demo.example.com/video/' . $id . '/' . $i . '_cn.m3u8',
            'play_url' => DEFAULT_PARSER_URL . 'https://demo.example.com/video/' . $id . '/' . $i . '_cn.m3u8',
            'dub' => 'mandarin'
        );
    }
    $playLists[] = array(
        'source' => 'default', 'source_name' => '云播线路',
        'episodes' => $defaultEpisodes,
        'has_mandarin' => true, 'has_original' => true
    );
}

// 找当前播放的源和集
$currentSource = !empty($playLists) ? $playLists[0] : null;
$currentEpisode = null;
$matchedEpisode = null;

// 根据集号和配音筛选
foreach ($playLists as $pl) {
    foreach ($pl['episodes'] as $epItem) {
        $epNum = intval($epItem['episode']);
        $epDub = isset($epItem['dub']) ? $epItem['dub'] : 'original';
        if ($epNum === ($type === 'movie' ? 1 : $ep)) {
            if (!$dub || $epDub === $dub || !$matchedEpisode) {
                $currentSource = $pl;
                $matchedEpisode = $epItem;
                if ($epDub === $dub || !$dub) {
                    $currentEpisode = $epItem;
                    if ($epDub === $dub) break 2;
                }
            }
        }
    }
}
if (!$currentEpisode && $matchedEpisode) $currentEpisode = $matchedEpisode;
if (!$currentEpisode && $currentSource && !empty($currentSource['episodes'])) {
    $currentEpisode = $currentSource['episodes'][0];
}

// 解析播放器地址
$parserUrl = get_setting('player_parser', DEFAULT_PARSER_URL);
$playerUrl = $currentEpisode ? $parserUrl . (isset($currentEpisode['url']) ? $currentEpisode['url'] : '') : $parserUrl;

// 判断国产（是否显示配音选项）
$isDomestic = $playSource->isDomestic(isset($playDetail['info']) ? $playDetail['info'] : array());
if (!$isDomestic) {
    $countries = isset($basic['production_countries']) ? $basic['production_countries'] : array();
    foreach ($countries as $c) {
        $cn = isset($c['name']) ? $c['name'] : '';
        if (strpos($cn, '中国') !== false) { $isDomestic = true; break; }
    }
}
$showDubbing = !$isDomestic;

// 获取季信息（电视剧）
$seasons = array();
if ($type !== 'movie') {
    $seasons = isset($basic['seasons']) ? $basic['seasons'] : array();
    if (empty($seasons)) {
        $seasons[] = array('season_number' => 1, 'name' => '第1季', 'episode_count' => 24);
    }
}

// 记录观看历史
$userId = intval($currentUser['id']);
$epName = $currentEpisode ? (isset($currentEpisode['name']) ? $currentEpisode['name'] : '') : '';
$epNum = $type === 'movie' ? 1 : $ep;
try {
    $existing = db()->fetchOne("SELECT id FROM watch_history WHERE user_id = ? AND movie_id = ? AND season = ? AND episode = ?",
        array($userId, strval($id), $season, $epNum));
    if ($existing) {
        db()->update('watch_history', array(
            'movie_name' => $title,
            'poster' => $poster,
            'type' => $type,
            'episode_name' => $epName,
            'last_watch_at' => date('Y-m-d H:i:s')
        ), 'id = ?', array($existing['id']));
        $historyId = $existing['id'];
    } else {
        $historyId = db()->insert('watch_history', array(
            'user_id' => $userId,
            'movie_id' => strval($id),
            'movie_name' => $title,
            'poster' => $poster,
            'type' => $type,
            'season' => $season,
            'episode' => $epNum,
            'episode_name' => $epName
        ));
    }
} catch (Exception $e) {
    $historyId = 0;
}

// 更新秒数的AJAX接口URL
$updateTimeUrl = BASE_URL . '/api/watch_time.php';
$detailUrl = BASE_URL . '/detail.php?id=' . $id . '&type=' . $type . '&season=' . $season;

include ROOT_PATH . '/includes/header.php';

// 生成剧集列表用于切换
$allEpisodes = $currentSource ? $currentSource['episodes'] : array();
// 过滤配音
$originalEpisodes = array();
$mandarinEpisodes = array();
foreach ($allEpisodes as $e) {
    $d = isset($e['dub']) ? $e['dub'] : 'original';
    if ($d === 'mandarin') $mandarinEpisodes[] = $e;
    else $originalEpisodes[] = $e;
}
if (empty($mandarinEpisodes)) $mandarinEpisodes = $originalEpisodes;

$displayEpisodes = ($dub === 'mandarin' && !empty($mandarinEpisodes)) ? $mandarinEpisodes : $originalEpisodes;
if (empty($displayEpisodes)) $displayEpisodes = $allEpisodes;
?>

<main style="background:var(--bg-darkest);">
    <div class="container" style="padding:20px;">
        <!-- 面包屑 -->
        <div style="margin-bottom:16px;color:var(--text-muted);font-size:14px;">
            <a href="<?php echo BASE_URL; ?>/index.php" style="color:var(--text-secondary);">首页</a>
            <span style="margin:0 8px;">/</span>
            <a href="<?php echo BASE_URL; ?>/category.php?type=<?php echo e($type); ?>" style="color:var(--text-secondary);">
                <?php echo array('movie'=>'电影','tv'=>'电视剧','anime'=>'动漫','variety'=>'综艺')[$type]; ?>
            </a>
            <span style="margin:0 8px;">/</span>
            <a href="<?php echo e($detailUrl); ?>" style="color:var(--text-secondary);"><?php echo e(mb_substr($title, 0, 20, 'UTF-8')); ?><?php echo mb_strlen($title,'UTF-8')>20?'...':''; ?></a>
            <span style="margin:0 8px;">/</span>
            <span style="color:var(--text-primary);">
                <?php echo $type === 'movie' ? '播放' : ('第' . $season . '季 - ' . ($currentEpisode ? $currentEpisode['name'] : '第'.$ep.'集')); ?>
            </span>
        </div>

        <!-- 播放器区域 -->
        <div style="background:var(--bg-card);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-lg);border:1px solid var(--border-color);margin-bottom:24px;">
            <div style="position:relative;padding-top:56.25%;background:#000;">
                <iframe id="player-iframe"
                    src="<?php echo e($playerUrl); ?>"
                    style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                    allowfullscreen="true"
                    allow="autoplay; encrypted-media; fullscreen; picture-in-picture; screen-wake-lock;"
                    scrolling="no"
                    referrerpolicy="no-referrer"
                    sandbox="allow-scripts allow-same-origin allow-presentation allow-popups allow-modals allow-forms allow-top-navigation"></iframe>
            </div>
            <div style="padding:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <h1 style="font-size:20px;font-weight:700;margin-bottom:8px;"><?php echo e($title); ?></h1>
                    <div style="color:var(--text-muted);font-size:13px;">
                        <?php if ($type !== 'movie'): ?>
                        <span>第 <?php echo $season; ?> 季</span> ·
                        <span><?php echo $currentEpisode ? e($currentEpisode['name']) : '第'.$ep.'集'; ?></span>
                        <?php else: ?>
                        <span>正片</span>
                        <?php endif; ?>
                        · <span>播放源：<?php echo $currentSource ? e($currentSource['source_name']) : '默认线路'; ?></span>
                    </div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="<?php echo e($detailUrl); ?>" class="btn btn-outline btn-sm">📄 详情</a>
                    <button class="btn btn-outline btn-sm" onclick="toggleFullscreen()">⛶ 全屏</button>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:280px 1fr;gap:24px;">
            <!-- 左侧：播放源 & 配音 -->
            <div>
                <?php if (count($playLists) > 1): ?>
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:16px;margin-bottom:20px;">
                    <div style="font-size:15px;font-weight:600;margin-bottom:12px;">📡 播放源</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ($playLists as $idx => $pl):
                            $srcId = isset($pl['source']) ? $pl['source'] : $idx;
                            $active = $currentSource && (isset($currentSource['source']) ? $currentSource['source'] : '') === $srcId;
                        ?>
                        <a href="?id=<?php echo $id; ?>&type=<?php echo e($type); ?>&season=<?php echo $season; ?>&ep=<?php echo $ep; ?>&dub=<?php echo e($dub); ?>&src=<?php echo e($srcId); ?>"
                           class="btn <?php echo $active ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" style="justify-content:flex-start;">
                            <?php echo isset($pl['source_name']) ? e($pl['source_name']) : ('线路 '.($idx+1)); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($showDubbing): ?>
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:16px;margin-bottom:20px;">
                    <div style="font-size:15px;font-weight:600;margin-bottom:12px;">🎙 配音版本</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php
                        $currentDub = $dub ? $dub : (isset($currentEpisode['dub']) && $currentEpisode['dub'] === 'mandarin' ? 'mandarin' : 'original');
                        ?>
                        <a href="?id=<?php echo $id; ?>&type=<?php echo e($type); ?>&season=<?php echo $season; ?>&ep=<?php echo $ep; ?>&dub=original"
                           class="btn <?php echo $currentDub === 'original' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" style="justify-content:flex-start;">
                            🔊 原画版本
                        </a>
                        <a href="?id=<?php echo $id; ?>&type=<?php echo e($type); ?>&season=<?php echo $season; ?>&ep=<?php echo $ep; ?>&dub=mandarin"
                           class="btn <?php echo $currentDub === 'mandarin' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" style="justify-content:flex-start;">
                            🗣 普通话配音
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($type !== 'movie' && count($seasons) > 1): ?>
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:16px;margin-bottom:20px;">
                    <div style="font-size:15px;font-weight:600;margin-bottom:12px;">📚 选择季</div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <?php foreach ($seasons as $s):
                            $sNum = intval($s['season_number']);
                            $active = $sNum === $season;
                        ?>
                        <a href="?id=<?php echo $id; ?>&type=<?php echo e($type); ?>&season=<?php echo $sNum; ?>&ep=1&dub=<?php echo e($currentDub); ?>"
                           class="genre-tab <?php echo $active ? 'active' : ''; ?>" style="text-align:center;">
                            <?php echo isset($s['name']) ? e($s['name']) : ('第'.$sNum.'季'); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- 右侧：剧集列表 -->
            <div>
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                        <div style="font-size:16px;font-weight:700;">
                            <?php echo $type === 'movie' ? '播放列表' : '全部剧集'; ?>
                            <span style="color:var(--text-muted);font-size:13px;font-weight:400;margin-left:8px;">(<?php echo count($displayEpisodes); ?>集)</span>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(100px, 1fr));gap:10px;">
                        <?php foreach ($displayEpisodes as $epItem):
                            $epNum = intval($epItem['episode']);
                            $epName = isset($epItem['name']) ? $epItem['name'] : '第'.$epNum.'集';
                            $active = $type === 'movie' ? true : ($epNum === $ep);
                            $link = '?id='.$id.'&type='.$type.'&season='.$season.'&ep='.$epNum.'&dub='.($dub ? $dub : 'original');
                            if ($sourceId) $link .= '&source='.$sourceId;
                            $epClass = $active ? 'ep-link active' : 'ep-link';
                        ?>
                        <a href="<?php echo e($link); ?>" class="<?php echo $epClass; ?>"
                           title="<?php echo e($epName); ?>">
                            <?php echo $type === 'movie' ? '▶ 正片' : $epNum; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
<style>
.ep-link{display:flex;align-items:center;justify-content:center;height:44px;padding:0 12px;border-radius:10px;font-size:13px;font-weight:500;transition:all 0.2s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:var(--bg-dark);border:1px solid var(--border-color);color:var(--text-secondary);text-decoration:none;}
.ep-link:hover{background:var(--bg-card-hover);color:var(--text-primary);border-color:rgba(255,255,255,0.15);}
.ep-link.active{background:var(--primary-gradient);color:white;box-shadow:var(--shadow-primary);border:none;}
</style>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
var historyId = <?php echo intval($historyId); ?>;
var startTime = Math.floor(Date.now() / 1000);

// 定时上报观看秒数
setInterval(function() {
    var seconds = Math.floor(Date.now() / 1000) - startTime;
    if (seconds <= 0 || !historyId) return;
    var fd = new FormData();
    fd.append('id', historyId);
    fd.append('seconds', seconds);
    fetch('<?php echo $updateTimeUrl; ?>', {method:'POST', body:fd, credentials:'same-origin'}).catch(function(){});
}, 30000); // 每30秒上报

// 离开前上报一次
window.addEventListener('beforeunload', function() {
    if (!historyId) return;
    var seconds = Math.floor(Date.now() / 1000) - startTime;
    if (seconds <= 0) return;
    var fd = new FormData();
    fd.append('id', historyId);
    fd.append('seconds', seconds);
    navigator.sendBeacon && navigator.sendBeacon('<?php echo $updateTimeUrl; ?>', fd);
});

// 全屏
function toggleFullscreen() {
    var iframe = document.getElementById('player-iframe');
    if (!iframe) return;
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else if (iframe.requestFullscreen) {
        iframe.requestFullscreen();
    } else if (iframe.webkitRequestFullscreen) {
        iframe.webkitRequestFullscreen();
    }
}
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
