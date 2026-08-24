<?php
/**
 * TMDB API封装
 */

// 防止直接访问
if (!defined('ROOT_PATH')) {
    require_once dirname(dirname(__FILE__)) . '/config/config.php';
}

class TMDBApi {
    private $apiKey;
    private $baseUrl;
    private $imageUrl;
    private $cacheDir;
    private $cacheTime = 3600; // 缓存1小时

    public function __construct() {
        $this->apiKey = get_setting('tmdb_api_key', TMDB_API_KEY);
        $this->baseUrl = TMDB_API_URL;
        $this->imageUrl = TMDB_IMAGE_URL;
        $this->cacheDir = ROOT_PATH . '/cache';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * 获取图片完整URL
     */
    public function getImageUrl($path, $size = 'w500') {
        if (empty($path)) return '';
        if (strpos($path, 'http') === 0) return $path;
        return $this->imageUrl . '/' . $size . $path;
    }

    /**
     * 缓存目录获取
     */
    private function getCachePath($key) {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    /**
     * 读取缓存
     */
    private function getCache($key) {
        $file = $this->getCachePath($key);
        if (file_exists($file) && (time() - filemtime($file)) < $this->cacheTime) {
            $data = @file_get_contents($file);
            if ($data) {
                return json_decode($data, true);
            }
        }
        return null;
    }

    /**
     * 写入缓存
     */
    private function setCache($key, $data) {
        $file = $this->getCachePath($key);
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 请求API
     */
    private function request($endpoint, $params = array()) {
        $cacheKey = $endpoint . '?' . http_build_query($params);
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        if (empty($this->apiKey)) {
            // 没有API Key时返回示例数据
            return $this->getMockData($endpoint, $params);
        }

        $params['api_key'] = $this->apiKey;
        $params['language'] = isset($params['language']) ? $params['language'] : 'zh-CN';
        if (!isset($params['region'])) {
            $params['region'] = 'CN';
        }

        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JayMovie/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            if ($data) {
                $this->setCache($cacheKey, $data);
                return $data;
            }
        }

        // API失败时使用示例数据
        return $this->getMockData($endpoint, $params);
    }

    /**
     * 示例数据（无API Key时使用）
     */
    private function getMockData($endpoint, $params) {
        $movies = $this->getMockMovies();
        $tvs = $this->getMockTVs();
        $animes = $this->getMockAnimes();
        $varieties = $this->getMockVarieties();

        // 热门推荐
        if (strpos($endpoint, '/trending') !== false || strpos($endpoint, '/popular') !== false) {
            $all = array_merge($movies, $tvs, $animes, $varieties);
            shuffle($all);
            return array(
                'results' => array_slice($all, 0, 20),
                'total_results' => 100,
                'total_pages' => 5,
                'page' => 1
            );
        }

        // 电影列表
        if (strpos($endpoint, '/movie/top_rated') !== false || strpos($endpoint, '/movie/now_playing') !== false || (strpos($endpoint, '/discover/movie') !== false)) {
            return array(
                'results' => $movies,
                'total_results' => count($movies) * 10,
                'total_pages' => 10,
                'page' => 1
            );
        }

        // 电视剧列表
        if (strpos($endpoint, '/tv/top_rated') !== false || strpos($endpoint, '/discover/tv') !== false) {
            return array(
                'results' => $tvs,
                'total_results' => count($tvs) * 10,
                'total_pages' => 10,
                'page' => 1
            );
        }

        // 搜索
        if (strpos($endpoint, '/search') !== false) {
            $query = isset($params['query']) ? $params['query'] : '';
            $all = array_merge($movies, $tvs, $animes, $varieties);
            $filtered = array();
            if ($query) {
                foreach ($all as $item) {
                    $name = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '');
                    if (stripos($name, $query) !== false) {
                        $filtered[] = $item;
                    }
                }
            } else {
                $filtered = $all;
            }
            return array(
                'results' => $filtered,
                'total_results' => count($filtered),
                'total_pages' => 1,
                'page' => 1
            );
        }

        // 详情页
        if (preg_match('#/(movie|tv)/(\d+)$#', $endpoint, $matches)) {
            $type = $matches[1];
            $id = intval($matches[2]);
            $all = $type === 'movie' ? $movies : array_merge($tvs, $animes, $varieties);
            foreach ($all as $item) {
                if ($item['id'] == $id) {
                    return $this->getMockDetail($item, $type);
                }
            }
            return $this->getMockDetail($all[0], $type);
        }

        // 季数据
        if (preg_match('#/tv/(\d+)/season/(\d+)#', $endpoint, $matches)) {
            return $this->getMockSeason(intval($matches[1]), intval($matches[2]));
        }

        // 集详情
        if (preg_match('#/tv/(\d+)/season/(\d+)/episode/(\d+)#', $endpoint, $matches)) {
            return $this->getMockEpisode(intval($matches[1]), intval($matches[2]), intval($matches[3]));
        }

        // 默认返回空结果
        return array(
            'results' => array(),
            'total_results' => 0,
            'total_pages' => 1,
            'page' => 1
        );
    }

    private function getMockMovies() {
        return array(
            array('id' => 101, 'title' => '流浪地球2', 'original_title' => 'The Wandering Earth II', 'poster_path' => '/movie1.jpg', 'backdrop_path' => '/backdrop1.jpg', 'overview' => '太阳即将毁灭，人类在地球表面建造出巨大的推进器，寻找新的家园。然而宇宙之路危机四伏，为了拯救地球，流浪地球时代的年轻人再次挺身而出，展开争分夺秒的生死之战。', 'release_date' => '2023-01-22', 'vote_average' => 8.3, 'genres' => array(array('name' => '科幻'), array('name' => '冒险')), 'runtime' => 173, 'media_type' => 'movie'),
            array('id' => 102, 'title' => '奥本海默', 'original_title' => 'Oppenheimer', 'poster_path' => '/movie2.jpg', 'backdrop_path' => '/backdrop2.jpg', 'overview' => '讲述美国"原子弹之父"罗伯特·奥本海默主导制造出世界上第一颗原子弹的故事。', 'release_date' => '2023-07-21', 'vote_average' => 8.8, 'genres' => array(array('name' => '剧情'), array('name' => '传记')), 'runtime' => 180, 'media_type' => 'movie'),
            array('id' => 103, 'title' => '封神第一部', 'original_title' => 'Creation of the Gods I', 'poster_path' => '/movie3.jpg', 'backdrop_path' => '/backdrop3.jpg', 'overview' => '商王殷寿勾结狐妖妲己，暴虐无道，引发天谴。', 'release_date' => '2023-07-20', 'vote_average' => 7.8, 'genres' => array(array('name' => '古装'), array('name' => '奇幻')), 'runtime' => 148, 'media_type' => 'movie'),
            array('id' => 104, 'title' => '哥斯拉-1.0', 'original_title' => 'Godzilla Minus One', 'poster_path' => '/movie4.jpg', 'backdrop_path' => '/backdrop4.jpg', 'overview' => '二战后的日本，受到毁灭性打击，雪上加霜的是受到了巨型怪兽哥斯拉的袭击。', 'release_date' => '2023-11-03', 'vote_average' => 7.5, 'genres' => array(array('name' => '科幻'), array('name' => '灾难')), 'runtime' => 125, 'media_type' => 'movie'),
            array('id' => 105, 'title' => '热辣滚烫', 'original_title' => 'YOLO', 'poster_path' => '/movie5.jpg', 'backdrop_path' => '/backdrop5.jpg', 'overview' => '乐莹宅家多年，在遭遇生活变故后，选择拳击重获新生的故事。', 'release_date' => '2024-02-10', 'vote_average' => 7.4, 'genres' => array(array('name' => '剧情'), array('name' => '喜剧')), 'runtime' => 129, 'media_type' => 'movie'),
            array('id' => 106, 'title' => '飞驰人生2', 'original_title' => 'Pegasus 2', 'poster_path' => '/movie6.jpg', 'backdrop_path' => '/backdrop6.jpg', 'overview' => '曾经的顶级赛车手张弛经历人生反转之后再次挑战顶级赛场的故事。', 'release_date' => '2024-02-10', 'vote_average' => 6.9, 'genres' => array(array('name' => '喜剧'), array('name' => '运动')), 'runtime' => 110, 'media_type' => 'movie')
        );
    }

    private function getMockTVs() {
        return array(
            array('id' => 201, 'name' => '庆余年 第二季', 'original_name' => 'Joy of Life Season 2', 'poster_path' => '/tv1.jpg', 'backdrop_path' => '/backdroptv1.jpg', 'overview' => '范闲因五竹突破昏迷被救回后苏醒，面对重重阴谋，继续在庆国朝堂上的故事。', 'first_air_date' => '2024-05-16', 'vote_average' => 8.9, 'genres' => array(array('name' => '古装'), array('name' => '权谋')), 'number_of_seasons' => 2, 'media_type' => 'tv'),
            array('id' => 202, 'name' => '漫长的季节', 'original_name' => 'The Long Season', 'poster_path' => '/tv2.jpg', 'backdrop_path' => '/backdroptv2.jpg', 'overview' => '小城桦林，出租车司机王响在查案过程中，与妹夫龚彪、退休刑警马德胜一同揭开了横跨多年的故事。', 'first_air_date' => '2023-04-22', 'vote_average' => 9.4, 'genres' => array(array('name' => '悬疑'), array('name' => '生活')), 'number_of_seasons' => 1, 'media_type' => 'tv'),
            array('id' => 203, 'name' => '难哄', 'original_name' => 'Love and Deep Space', 'poster_path' => '/tv3.jpg', 'backdrop_path' => '/backdroptv3.jpg', 'overview' => '温以凡与桑延之间的甜蜜爱情故事。', 'first_air_date' => '2025-02-18', 'vote_average' => 7.9, 'genres' => array(array('name' => '爱情'), array('name' => '都市')), 'number_of_seasons' => 1, 'media_type' => 'tv'),
            array('id' => 204, 'name' => '抓娃娃', 'original_name' => 'Stand by Me', 'poster_path' => '/tv4.jpg', 'backdrop_path' => '/backdroptv4.jpg', 'overview' => '家庭、教育与成长的温暖故事。', 'first_air_date' => '2024-07-12', 'vote_average' => 7.1, 'genres' => array(array('name' => '家庭'), array('name' => '喜剧')), 'number_of_seasons' => 1, 'media_type' => 'tv'),
            array('id' => 205, 'name' => '第二十条', 'original_name' => 'Article 20', 'poster_path' => '/tv5.jpg', 'backdrop_path' => '/backdroptv5.jpg', 'overview' => '关于法律与正义的故事。', 'first_air_date' => '2024-02-10', 'vote_average' => 7.8, 'genres' => array(array('name' => '剧情'), array('name' => '法律')), 'number_of_seasons' => 1, 'media_type' => 'tv')
        );
    }

    private function getMockAnimes() {
        return array(
            array('id' => 301, 'name' => '斗破苍穹 年番', 'original_name' => 'Battle Through the Heavens', 'poster_path' => '/anime1.jpg', 'backdrop_path' => '/backdropanime1.jpg', 'overview' => '天才少年萧炎在创造了家族空前绝后的修炼纪录后突然成了废人。', 'first_air_date' => '2022-07-31', 'vote_average' => 8.6, 'genres' => array(array('name' => '动画'), array('name' => '奇幻')), 'number_of_seasons' => 5, 'media_type' => 'tv'),
            array('id' => 302, 'name' => '海贼王', 'original_name' => 'One Piece', 'poster_path' => '/anime2.jpg', 'backdrop_path' => '/backdropanime2.jpg', 'overview' => '拥有财富、名声、权力，这世界上的一切的男人"海贼王"哥尔·D·罗杰，在被行刑受死之前说了一句话，让全世界的人都涌向了大海。', 'first_air_date' => '1999-10-20', 'vote_average' => 9.2, 'genres' => array(array('name' => '动画'), array('name' => '冒险')), 'number_of_seasons' => 21, 'media_type' => 'tv'),
            array('id' => 303, 'name' => '西游记之再世妖王', 'original_name' => 'Monkey King Reborn', 'poster_path' => '/anime3.jpg', 'backdrop_path' => '/backdropanime3.jpg', 'overview' => '混沌初开，世间万物生灵涂炭，妖鬼肆虐。孙悟空为寻求正义，踏上了一条充满挑战与守护的道路。', 'first_air_date' => '2021-04-02', 'vote_average' => 8.7, 'genres' => array(array('name' => '动画'), array('name' => '奇幻'), array('name' => '冒险')), 'number_of_seasons' => 1, 'media_type' => 'movie'),
            array('id' => 304, 'name' => '鬼灭之刃', 'original_name' => 'Demon Slayer', 'poster_path' => '/anime4.jpg', 'backdrop_path' => '/backdropanime4.jpg', 'overview' => '大正时期，灶门炭治郎一家被鬼所杀，妹妹祢豆子变成了鬼，为了让妹妹变回人类，炭治郎开始了斩鬼之旅。', 'first_air_date' => '2019-04-06', 'vote_average' => 8.8, 'genres' => array(array('name' => '动画'), array('name' => '战斗')), 'number_of_seasons' => 4, 'media_type' => 'tv')
        );
    }

    private function getMockVarieties() {
        return array(
            array('id' => 401, 'name' => '奔跑吧 最新一季', 'original_name' => 'Keep Running', 'poster_path' => '/variety1.jpg', 'backdrop_path' => '/backdropvar1.jpg', 'overview' => '知名户外竞技真人秀节目。', 'first_air_date' => '2024-04-26', 'vote_average' => 7.2, 'genres' => array(array('name' => '综艺'), array('name' => '真人秀')), 'number_of_seasons' => 12, 'media_type' => 'tv'),
            array('id' => 402, 'name' => '歌手2024', 'original_name' => 'Singer 2024', 'poster_path' => '/variety2.jpg', 'backdrop_path' => '/backdropvar2.jpg', 'overview' => '顶级音乐竞技节目。', 'first_air_date' => '2024-05-10', 'vote_average' => 8.1, 'genres' => array(array('name' => '综艺'), array('name' => '音乐')), 'number_of_seasons' => 1, 'media_type' => 'tv'),
            array('id' => 403, 'name' => '向往的生活', 'original_name' => 'Back to Field', 'poster_path' => '/variety3.jpg', 'backdrop_path' => '/backdropvar3.jpg', 'overview' => '生活服务纪实节目。', 'first_air_date' => '2024-07-05', 'vote_average' => 7.5, 'genres' => array(array('name' => '综艺'), array('name' => '生活')), 'number_of_seasons' => 8, 'media_type' => 'tv')
        );
    }

    private function getMockDetail($item, $type) {
        $isMovie = $type === 'movie';
        $detail = array(
            'id' => $item['id'],
            'overview' => $item['overview'],
            'vote_average' => $item['vote_average'],
            'poster_path' => $item['poster_path'],
            'backdrop_path' => $item['backdrop_path'],
            'genres' => isset($item['genres']) ? $item['genres'] : array(array('name' => '剧情')),
            'popularity' => 1000,
            'production_countries' => array(array('name' => $this->isChineseTitle($item) ? '中国大陆' : '美国')),
            'spoken_languages' => array(array('english_name' => $this->isChineseTitle($item) ? 'Mandarin' : 'English', 'name' => $this->isChineseTitle($item) ? '普通话' : '英语')),
            'status' => 'Released',
            'tagline' => ''
        );

        if ($isMovie) {
            $detail['title'] = $item['title'];
            $detail['original_title'] = isset($item['original_title']) ? $item['original_title'] : $item['title'];
            $detail['release_date'] = isset($item['release_date']) ? $item['release_date'] : '2024-01-01';
            $detail['runtime'] = isset($item['runtime']) ? $item['runtime'] : 120;
            $detail['credits'] = $this->getMockCredits();
            $detail['videos'] = array('results' => array());
            $detail['images'] = $this->getMockImages();
        } else {
            $detail['name'] = $item['name'];
            $detail['original_name'] = isset($item['original_name']) ? $item['original_name'] : $item['name'];
            $detail['first_air_date'] = isset($item['first_air_date']) ? $item['first_air_date'] : '2024-01-01';
            $detail['number_of_seasons'] = isset($item['number_of_seasons']) ? $item['number_of_seasons'] : 1;
            $detail['number_of_episodes'] = 24;
            $detail['seasons'] = $this->getMockSeasons($detail['number_of_seasons']);
            $detail['credits'] = $this->getMockCredits();
            $detail['videos'] = array('results' => array());
            $detail['images'] = $this->getMockImages();
        }

        return $detail;
    }

    private function isChineseTitle($item) {
        $name = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '');
        return preg_match('/[\x{4e00}-\x{9fff}]/u', $name);
    }

    private function getMockCredits() {
        return array(
            'cast' => array(
                array('id' => 1001, 'name' => '演员A', 'original_name' => 'Actor A', 'character' => '主角', 'profile_path' => '/cast1.jpg', 'order' => 0),
                array('id' => 1002, 'name' => '演员B', 'original_name' => 'Actor B', 'character' => '女主角', 'profile_path' => '/cast2.jpg', 'order' => 1),
                array('id' => 1003, 'name' => '演员C', 'original_name' => 'Actor C', 'character' => '配角1', 'profile_path' => '/cast3.jpg', 'order' => 2),
                array('id' => 1004, 'name' => '演员D', 'original_name' => 'Actor D', 'character' => '配角2', 'profile_path' => '/cast4.jpg', 'order' => 3),
                array('id' => 1005, 'name' => '演员E', 'original_name' => 'Actor E', 'character' => '导演', 'profile_path' => '/cast5.jpg', 'order' => 4)
            ),
            'crew' => array(
                array('id' => 2001, 'name' => '导演F', 'job' => 'Director', 'department' => 'Directing', 'profile_path' => '/cast6.jpg')
            )
        );
    }

    private function getMockImages() {
        $posters = array();
        $backdrops = array();
        for ($i = 1; $i <= 5; $i++) {
            $posters[] = array('file_path' => '/poster' . $i . '.jpg', 'width' => 500, 'height' => 750, 'iso_639_1' => 'zh');
            $backdrops[] = array('file_path' => '/backdrop' . $i . '.jpg', 'width' => 1920, 'height' => 1080);
        }
        return array('posters' => $posters, 'backdrops' => $backdrops);
    }

    private function getMockSeasons($count) {
        $seasons = array();
        for ($i = 1; $i <= $count; $i++) {
            $seasons[] = array(
                'season_number' => $i,
                'name' => '第' . $i . '季',
                'overview' => '第' . $i . '季的精彩故事。',
                'poster_path' => '/season' . $i . '.jpg',
                'air_date' => '2024-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01',
                'episode_count' => 12
            );
        }
        return $seasons;
    }

    private function getMockSeason($tvId, $seasonNumber) {
        $episodes = array();
        for ($i = 1; $i <= 12; $i++) {
            $episodes[] = array(
                'episode_number' => $i,
                'name' => '第' . $i . '集',
                'overview' => '第' . $i . '集的精彩剧情内容，故事发展进入高潮，主角面临新的挑战。',
                'still_path' => '/episode_' . $seasonNumber . '_' . $i . '.jpg',
                'air_date' => '2024-' . str_pad($seasonNumber, 2, '0', STR_PAD_LEFT) . '-' . str_pad($i * 2, 2, '0', STR_PAD_LEFT),
                'runtime' => 45,
                'vote_average' => 8.0 + rand(0, 9) / 10
            );
        }
        return array(
            'id' => $tvId * 100 + $seasonNumber,
            'season_number' => $seasonNumber,
            'name' => '第' . $seasonNumber . '季',
            'overview' => '第' . $seasonNumber . '季的完整故事线。',
            'poster_path' => '/season' . $seasonNumber . '.jpg',
            'air_date' => '2024-' . str_pad($seasonNumber, 2, '0', STR_PAD_LEFT) . '-01',
            'episodes' => $episodes
        );
    }

    private function getMockEpisode($tvId, $seasonNumber, $episodeNumber) {
        return array(
            'id' => $tvId * 10000 + $seasonNumber * 100 + $episodeNumber,
            'episode_number' => $episodeNumber,
            'season_number' => $seasonNumber,
            'name' => '第' . $episodeNumber . '集',
            'overview' => '本集讲述了精彩的剧情发展，主角团队面临新的危机和挑战。',
            'still_path' => '/episode_' . $seasonNumber . '_' . $episodeNumber . '.jpg',
            'air_date' => '2024-' . str_pad($seasonNumber, 2, '0', STR_PAD_LEFT) . '-' . str_pad($episodeNumber * 2, 2, '0', STR_PAD_LEFT),
            'runtime' => 45,
            'vote_average' => 8.5,
            'crew' => array(),
            'guest_stars' => array()
        );
    }

    // ========== 公开方法 ==========

    /**
     * 获取首页横幅
     */
    public function getTrending($timeWindow = 'week', $page = 1) {
        return $this->request('/trending/all/' . $timeWindow, array('page' => $page));
    }

    /**
     * 获取热门电影
     */
    public function getPopularMovies($page = 1) {
        return $this->request('/movie/popular', array('page' => $page));
    }

    /**
     * 获取正在热映的电影
     */
    public function getNowPlayingMovies($page = 1) {
        return $this->request('/movie/now_playing', array('page' => $page));
    }

    /**
     * 获取高分电影
     */
    public function getTopRatedMovies($page = 1) {
        return $this->request('/movie/top_rated', array('page' => $page));
    }

    /**
     * 获取热门电视剧
     */
    public function getPopularTV($page = 1) {
        return $this->request('/tv/popular', array('page' => $page));
    }

    /**
     * 获取高分电视剧
     */
    public function getTopRatedTV($page = 1) {
        return $this->request('/tv/top_rated', array('page' => $page));
    }

    /**
     * 搜索
     */
    public function search($query, $page = 1, $type = 'multi') {
        $params = array('query' => $query, 'page' => $page, 'include_adult' => false);
        if ($type === 'movie') {
            return $this->request('/search/movie', $params);
        } elseif ($type === 'tv') {
            return $this->request('/search/tv', $params);
        }
        return $this->request('/search/multi', $params);
    }

    /**
     * 获取电影详情
     */
    public function getMovieDetail($id) {
        $detail = $this->request('/movie/' . $id, array(
            'append_to_response' => 'credits,videos,images,similar,recommendations'
        ));
        $detail['media_type'] = 'movie';
        return $detail;
    }

    /**
     * 获取电视剧详情
     */
    public function getTVDetail($id) {
        $detail = $this->request('/tv/' . $id, array(
            'append_to_response' => 'credits,videos,images,similar,recommendations'
        ));
        $detail['media_type'] = 'tv';
        return $detail;
    }

    /**
     * 获取季详情
     */
    public function getSeasonDetail($tvId, $seasonNumber) {
        return $this->request('/tv/' . $tvId . '/season/' . $seasonNumber, array(
            'append_to_response' => 'credits,images,videos'
        ));
    }

    /**
     * 获取集详情
     */
    public function getEpisodeDetail($tvId, $seasonNumber, $episodeNumber) {
        return $this->request('/tv/' . $tvId . '/season/' . $seasonNumber . '/episode/' . $episodeNumber);
    }

    /**
     * 按分类发现
     */
    public function discover($type, $params = array()) {
        $endpoint = '/discover/' . ($type === 'tv' ? 'tv' : 'movie');
        return $this->request($endpoint, $params);
    }
}
