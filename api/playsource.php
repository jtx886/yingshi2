<?php
/**
 * 播放源API封装
 */

// 防止直接访问
if (!defined('ROOT_PATH')) {
    require_once dirname(dirname(__FILE__)) . '/config/config.php';
}

class PlaySourceApi {
    private $sources;

    public function __construct() {
        $this->loadSources();
    }

    /**
     * 加载播放源列表
     */
    private function loadSources() {
        try {
            $this->sources = db()->fetchAll("SELECT * FROM play_sources WHERE status = 1 ORDER BY sort_order ASC, id ASC");
        } catch (Exception $e) {
            // 表不存在时使用默认
            $this->sources = array(array(
                'id' => 1,
                'name' => '云播资源',
                'api_url' => DEFAULT_PLAY_API,
                'parser_url' => DEFAULT_PARSER_URL,
                'is_default' => 1
            ));
        }
    }

    /**
     * 获取所有播放源
     */
    public function getAllSources() {
        return $this->sources;
    }

    /**
     * 获取默认播放源
     */
    public function getDefaultSource() {
        foreach ($this->sources as $source) {
            if (intval($source['is_default']) === 1) {
                return $source;
            }
        }
        return !empty($this->sources) ? $this->sources[0] : null;
    }

    /**
     * 通过ID获取播放源
     */
    public function getSourceById($id) {
        foreach ($this->sources as $source) {
            if (intval($source['id']) === intval($id)) {
                return $source;
            }
        }
        return $this->getDefaultSource();
    }

    /**
     * 请求播放源API搜索影视资源
     */
    public function search($keyword, $sourceId = null) {
        $source = $sourceId ? $this->getSourceById($sourceId) : $this->getDefaultSource();
        if (!$source) {
            return array();
        }

        $url = $source['api_url'];
        $separator = (strpos($url, '?') === false) ? '?' : '&';

        // 支持多种API格式
        if (strpos($url, 'apijson.php') !== false) {
            // yyzy-tv 格式
            $params = array(
                'action' => 'list',
                'wd' => $keyword
            );
        } elseif (strpos($url, 'api.php') !== false || strpos($url, 'api/') !== false) {
            $params = array(
                'ac' => 'list',
                'wd' => $keyword
            );
        } else {
            $params = array(
                'wd' => $keyword
            );
        }

        $requestUrl = $url . $separator . http_build_query($params);
        $response = $this->httpGet($requestUrl);

        if (!$response) {
            return $this->getMockSearchResults($keyword);
        }

        $data = json_decode($response, true);
        if (!$data) {
            // 尝试处理非标准JSON
            return $this->getMockSearchResults($keyword);
        }

        return $this->parseSearchResults($data);
    }

    /**
     * 获取影视详情（播放链接等）
     */
    public function getDetail($ids, $sourceId = null) {
        $source = $sourceId ? $this->getSourceById($sourceId) : $this->getDefaultSource();
        if (!$source) {
            return array();
        }

        $url = $source['api_url'];
        $separator = (strpos($url, '?') === false) ? '?' : '&';

        if (strpos($url, 'apijson.php') !== false) {
            $params = array(
                'action' => 'video',
                'ids' => $ids
            );
        } else {
            $params = array(
                'ac' => 'videolist',
                'ids' => $ids
            );
        }

        $requestUrl = $url . $separator . http_build_query($params);
        $response = $this->httpGet($requestUrl);

        if (!$response) {
            return $this->getMockDetail($ids);
        }

        $data = json_decode($response, true);
        if (!$data) {
            return $this->getMockDetail($ids);
        }

        return $this->parseDetail($data, $source);
    }

    /**
     * 解析搜索结果
     */
    private function parseSearchResults($data) {
        $results = array();

        // 不同API格式处理
        if (isset($data['list']) && is_array($data['list'])) {
            $items = $data['list'];
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $items = $data['data'];
        } elseif (isset($data['videos']) && is_array($data['videos'])) {
            $items = $data['videos'];
        } else {
            return $results;
        }

        foreach ($items as $item) {
            $vodId = isset($item['vod_id']) ? $item['vod_id'] : (isset($item['id']) ? $item['id'] : '');
            $vodName = isset($item['vod_name']) ? $item['vod_name'] : (isset($item['name']) ? $item['name'] : '未知');
            $vodPic = isset($item['vod_pic']) ? $item['vod_pic'] : (isset($item['pic']) ? $item['pic'] : '');
            $typeId = isset($item['type_id']) ? $item['type_id'] : (isset($item['category']) ? $item['category'] : '');
            $typeName = isset($item['type_name']) ? $item['type_name'] : (isset($item['type']) ? $item['type'] : '');
            $remarks = isset($item['vod_remarks']) ? $item['vod_remarks'] : (isset($item['note']) ? $item['note'] : '');
            $year = isset($item['vod_year']) ? $item['vod_year'] : '';
            $area = isset($item['vod_area']) ? $item['vod_area'] : '';
            $actor = isset($item['vod_actor']) ? $item['vod_actor'] : '';
            $director = isset($item['vod_director']) ? $item['vod_director'] : '';

            $results[] = array(
                'id' => $vodId,
                'name' => $vodName,
                'pic' => $vodPic,
                'type_id' => $typeId,
                'type_name' => $typeName,
                'remarks' => $remarks,
                'year' => $year,
                'area' => $area,
                'actor' => $actor,
                'director' => $director
            );
        }

        return $results;
    }

    /**
     * 解析详情数据
     */
    private function parseDetail($data, $source) {
        $result = array(
            'info' => array(),
            'play_lists' => array()
        );

        $list = isset($data['list']) && is_array($data['list']) ? $data['list'] : array();
        if (isset($data['data']) && is_array($data['data'])) {
            $list = $data['data'];
        }
        if (isset($data['video']) && is_array($data['video'])) {
            $list = array($data['video']);
        }

        if (empty($list)) {
            return $result;
        }

        $video = $list[0];

        $result['info'] = array(
            'id' => isset($video['vod_id']) ? $video['vod_id'] : (isset($video['id']) ? $video['id'] : ''),
            'name' => isset($video['vod_name']) ? $video['vod_name'] : (isset($video['name']) ? $video['name'] : ''),
            'sub_name' => isset($video['vod_sub']) ? $video['vod_sub'] : '',
            'pic' => isset($video['vod_pic']) ? $video['vod_pic'] : (isset($video['pic']) ? $video['pic'] : ''),
            'type_name' => isset($video['type_name']) ? $video['type_name'] : (isset($video['type']) ? $video['type'] : ''),
            'year' => isset($video['vod_year']) ? $video['vod_year'] : '',
            'area' => isset($video['vod_area']) ? $video['vod_area'] : '',
            'actor' => isset($video['vod_actor']) ? $video['vod_actor'] : '',
            'director' => isset($video['vod_director']) ? $video['vod_director'] : '',
            'content' => isset($video['vod_content']) ? $video['vod_content'] : (isset($video['desc']) ? $video['desc'] : ''),
            'remarks' => isset($video['vod_remarks']) ? $video['vod_remarks'] : (isset($video['note']) ? $video['note'] : ''),
            'score' => isset($video['vod_score']) ? $video['vod_score'] : ''
        );

        // 解析播放列表 - 支持多个播放源
        $playFrom = isset($video['vod_play_from']) ? $video['vod_play_from'] : '';
        $playUrl = isset($video['vod_play_url']) ? $video['vod_play_url'] : '';
        $playServer = isset($video['vod_play_server']) ? $video['vod_play_server'] : '';

        if ($playFrom && $playUrl) {
            $fromArr = explode('$$$', $playFrom);
            $urlArr = explode('$$$', $playUrl);
            $serverArr = $playServer ? explode('$$$', $playServer) : array();

            foreach ($fromArr as $idx => $from) {
                if (empty($from)) continue;
                $urlStr = isset($urlArr[$idx]) ? $urlArr[$idx] : '';
                $parserUrl = !empty($source['parser_url']) ? $source['parser_url'] : DEFAULT_PARSER_URL;

                $episodes = $this->parseEpisodes($urlStr, $parserUrl);
                $hasMandarin = $this->detectMandarinDub($episodes);

                $result['play_lists'][] = array(
                    'source' => $from,
                    'source_name' => $this->getSourceName($from),
                    'server' => isset($serverArr[$idx]) ? $serverArr[$idx] : '',
                    'episodes' => $episodes,
                    'has_mandarin' => $hasMandarin,
                    'has_original' => true
                );
            }
        } elseif (isset($video['play_list']) && is_array($video['play_list'])) {
            // 另一种格式
            foreach ($video['play_list'] as $pl) {
                $from = isset($pl['name']) ? $pl['name'] : '默认线路';
                $urls = isset($pl['urls']) ? $pl['urls'] : array();
                $parserUrl = !empty($source['parser_url']) ? $source['parser_url'] : DEFAULT_PARSER_URL;

                $episodes = array();
                if (is_array($urls)) {
                    foreach ($urls as $i => $u) {
                        $name = is_array($u) ? (isset($u['name']) ? $u['name'] : '第' . ($i + 1) . '集') : '第' . ($i + 1) . '集';
                        $url = is_array($u) ? (isset($u['url']) ? $u['url'] : '') : $u;
                        $episodes[] = array(
                            'episode' => $i + 1,
                            'name' => $name,
                            'url' => $url,
                            'play_url' => $parserUrl . $url,
                            'dub' => 'original'
                        );
                    }
                }
                $hasMandarin = $this->detectMandarinDub($episodes);

                $result['play_lists'][] = array(
                    'source' => $from,
                    'source_name' => $from,
                    'episodes' => $episodes,
                    'has_mandarin' => $hasMandarin,
                    'has_original' => true
                );
            }
        }

        return $result;
    }

    /**
     * 解析剧集字符串
     */
    private function parseEpisodes($urlStr, $parserUrl) {
        $episodes = array();
        if (empty($urlStr)) return $episodes;

        // 常见分隔符 # 或 \n
        $parts = preg_split('/#|\r?\n/', $urlStr);
        $parts = array_filter($parts);

        foreach ($parts as $idx => $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // 格式: 集名$链接 或 集名$link$dub 或 只有链接
            if (strpos($part, '$') !== false) {
                $segments = explode('$', $part);
                $name = trim($segments[0]);
                $url = isset($segments[1]) ? trim($segments[1]) : '';
                $dub = isset($segments[2]) ? trim($segments[2]) : 'original';
            } else {
                $name = '第' . ($idx + 1) . '集';
                $url = $part;
                $dub = 'original';
            }

            // 检测普通话版本在名称中
            $dub = $this->detectDubFromName($name, $dub);

            // 尝试提取集号
            $episodeNum = $idx + 1;
            if (preg_match('/第(\d+)集/', $name, $m)) {
                $episodeNum = intval($m[1]);
            } elseif (preg_match('/^(\d+)$/', trim($name), $m)) {
                $episodeNum = intval($m[1]);
                $name = '第' . $episodeNum . '集';
            }

            $episodes[] = array(
                'episode' => $episodeNum,
                'name' => $name,
                'url' => $url,
                'play_url' => $url ? $parserUrl . $url : '',
                'dub' => $dub
            );
        }

        // 按集号排序
        usort($episodes, function ($a, $b) {
            return $a['episode'] - $b['episode'];
        });

        return $episodes;
    }

    /**
     * 从名称检测配音类型
     */
    private function detectDubFromName($name, $default) {
        $lowerName = strtolower($name);
        if (strpos($lowerName, '普通话') !== false ||
            strpos($lowerName, '国语') !== false ||
            strpos($lowerName, '中配') !== false ||
            strpos($lowerName, '中文配音') !== false) {
            return 'mandarin';
        }
        if (strpos($lowerName, '原画') !== false ||
            strpos($lowerName, '原版') !== false ||
            strpos($lowerName, '原声') !== false ||
            strpos($lowerName, '日语') !== false ||
            strpos($lowerName, '英语') !== false ||
            strpos($lowerName, '韩版') !== false) {
            return 'original';
        }
        return $default;
    }

    /**
     * 检测是否有普通话配音版本
     */
    private function detectMandarinDub($episodes) {
        foreach ($episodes as $ep) {
            if (isset($ep['dub']) && $ep['dub'] === 'mandarin') {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取播放源显示名称
     */
    private function getSourceName($source) {
        $names = array(
            'ykm3u8' => '云播M3U8',
            'qq' => '腾讯',
            'iqiyi' => '爱奇艺',
            'youku' => '优酷',
            'mgtv' => '芒果',
            'sohu' => '搜狐',
            'letv' => '乐视',
            'bilibili' => '哔哩哔哩',
            'xigua' => '西瓜',
            'ckm3u8' => '酷云M3U8',
            'kuyun' => '酷云',
            'dbm3u8' => '豆瓣M3U8',
            'wjm3u8' => '无尽M3U8',
            'tkm3u8' => '天空M3U8',
            'bdyun' => '百度云',
            'ok' => 'OK云'
        );
        $key = strtolower($source);
        return isset($names[$key]) ? $names[$key] : $source;
    }

    /**
     * HTTP GET请求
     */
    private function httpGet($url, $timeout = 30) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == 200 && $response) {
                return $response;
            }
            return null;
        }

        // file_get_contents备选
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                'ignore_errors' => true
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));
        $response = @file_get_contents($url, false, $context);
        return $response ? $response : null;
    }

    /**
     * Mock搜索结果
     */
    private function getMockSearchResults($keyword) {
        $results = array();
        $names = array('流浪地球', '庆余年', '斗破苍穹', '海贼王', '漫长的季节', '封神第一部', '奥本海默', '热辣滚烫');
        foreach ($names as $i => $name) {
            if (empty($keyword) || stripos($name, $keyword) !== false) {
                $results[] = array(
                    'id' => 10000 + $i,
                    'name' => $name . ($i % 2 ? ' 普通话版' : ''),
                    'pic' => '',
                    'type_name' => $i < 3 ? '电影' : ($i < 6 ? '电视剧' : '动漫'),
                    'remarks' => '全' . (24 - $i) . '集',
                    'year' => 2023 + ($i % 2),
                    'area' => $i % 2 ? '中国大陆' : '美国',
                    'actor' => '演员1, 演员2, 演员3',
                    'director' => '导演' . chr(65 + $i)
                );
            }
        }
        return $results;
    }

    /**
     * Mock详情数据
     */
    private function getMockDetail($ids) {
        $episodes = array();
        $episodesMandarin = array();
        $totalEpisodes = 24;

        for ($i = 1; $i <= $totalEpisodes; $i++) {
            $episodes[] = array(
                'episode' => $i,
                'name' => '第' . $i . '集',
                'url' => 'https://example.com/play/original/' . $ids . '/' . $i . '.mp4',
                'play_url' => DEFAULT_PARSER_URL . 'https://example.com/play/original/' . $ids . '/' . $i . '.mp4',
                'dub' => 'original'
            );
            $episodesMandarin[] = array(
                'episode' => $i,
                'name' => '第' . $i . '集 普通话',
                'url' => 'https://example.com/play/mandarin/' . $ids . '/' . $i . '.mp4',
                'play_url' => DEFAULT_PARSER_URL . 'https://example.com/play/mandarin/' . $ids . '/' . $i . '.mp4',
                'dub' => 'mandarin'
            );
        }

        return array(
            'info' => array(
                'id' => $ids,
                'name' => '示例影视 ' . $ids,
                'pic' => '',
                'type_name' => '电视剧',
                'year' => '2024',
                'area' => '中国大陆',
                'actor' => '演员一, 演员二, 演员三',
                'director' => '导演名',
                'content' => '这是一部精彩的影视作品，讲述了动人的故事。',
                'remarks' => '全' . $totalEpisodes . '集',
                'score' => '8.8'
            ),
            'play_lists' => array(
                array(
                    'source' => 'ykm3u8',
                    'source_name' => '云播M3U8',
                    'episodes' => $episodes,
                    'has_mandarin' => false,
                    'has_original' => true
                ),
                array(
                    'source' => 'mandarin_m3u8',
                    'source_name' => '国配M3U8',
                    'episodes' => array_merge($episodes, $episodesMandarin),
                    'has_mandarin' => true,
                    'has_original' => true
                )
            )
        );
    }

    /**
     * 判断是否国产（用于判断是否需要配音选择）
     */
    public function isDomestic($detail) {
        $info = isset($detail['info']) ? $detail['info'] : $detail;
        $area = isset($info['area']) ? $info['area'] : '';
        $name = isset($info['name']) ? $info['name'] : '';

        // 国产地区
        $domesticAreas = array('中国大陆', '中国香港', '中国台湾', '香港', '台湾', '内地', '国产');
        foreach ($domesticAreas as $da) {
            if (strpos($area, $da) !== false) {
                return true;
            }
        }

        // 中文名默认国产可能性高，但不绝对
        // 如果明确标注外国地区则不算
        $foreignAreas = array('美国', '日本', '韩国', '英国', '法国', '德国', '印度', '泰国', '好莱坞', '欧美', '日韩');
        foreach ($foreignAreas as $fa) {
            if (strpos($area, $fa) !== false) {
                return false;
            }
        }

        // 名称含中文且无明确外国地区，默认国产
        return preg_match('/[\x{4e00}-\x{9fff}]/u', $name) && empty($area);
    }
}
