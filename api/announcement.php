<?php
/**
 * 公告弹窗接口：获取当前要显示的公告 + 不再提示记录
 */
require_once dirname(__FILE__) . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $uid = is_logged_in() ? intval(current_user()['id']) : 0;

    if ($action === 'dismiss') {
        $annId = isset($_POST['announcement_id']) ? intval($_POST['announcement_id']) : 0;
        if ($annId <= 0) json_error('参数错误');

        $cookieName = 'jay_dismissed_' . $annId;
        // 用Cookie记录一年（匿名用户）；登录用户额外存数据库？这里简单起见都用Cookie
        setcookie($cookieName, '1', time() + 365 * 86400, '/');

        // 如果是登录用户，也存session防止换设备出现，但用户要求"不再提示"
        // 这里简单处理：写入 dismissals 表（需要存在），但为了不用额外表，用用户设置表。
        if ($uid > 0) {
            // 用user_meta思路：写入 settings 或 cookie 双保险
            $key = 'dismissed_' . $annId;
            // 不重复造轮子，用Cookie就够（用户要求用户勾选不再提示；跨设备问题这里不处理）
        }
        json_success(null, '已记录不再提示');
    }
    json_error('未知操作');
}

// GET 请求：获取当前需要弹出的公告
// 条件：1. enabled=1  2. 用户没有设置"不再提示"（通过Cookie判断）
// 只返回最新的一条（优先级：importance high > normal > low, 然后 id DESC）
try {
    $rows = db()->fetchAll("SELECT * FROM announcements WHERE enabled = 1 ORDER BY
        CASE importance WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END ASC,
        id DESC LIMIT 20");
} catch (Exception $e) { $rows = array(); }

// 过滤掉用户已经勾选不再提示的
$list = array();
foreach ($rows as $r) {
    $cid = 'jay_dismissed_' . $r['id'];
    if (isset($_COOKIE[$cid]) && $_COOKIE[$cid] === '1') {
        // 用户明确勾选了不再提示，跳过这条
        continue;
    }
    $list[] = $r;
    // 一次只弹1条
    break;
}

if (empty($list)) {
    json_output(array('code' => 200, 'message' => '无公告', 'data' => null));
}
$ann = $list[0];
$ann['importance_text'] = array('high'=>'🚨 紧急公告','normal'=>'📣 公告','low'=>'ℹ️ 提示');
json_output(array('code' => 200, 'message' => 'ok', 'data' => $ann));
