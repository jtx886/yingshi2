<?php
/**
 * 观看秒数更新 API
 */
require_once dirname(__FILE__) . '/../config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_error('未登录', 401);
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$seconds = isset($_POST['seconds']) ? intval($_POST['seconds']) : 0;

if (!$id || $seconds <= 0) {
    json_error('参数错误');
}

$user = current_user();
$userId = intval($user['id']);

try {
    $history = db()->fetchOne("SELECT id, watch_seconds FROM watch_history WHERE id = ? AND user_id = ?", array($id, $userId));
    if ($history) {
        $newSeconds = max(intval($history['watch_seconds']), $seconds);
        db()->update('watch_history',
            array('watch_seconds' => $newSeconds, 'last_watch_at' => date('Y-m-d H:i:s')),
            'id = ?', array($id)
        );
        json_success(array('seconds' => $newSeconds));
    }
    json_error('记录不存在');
} catch (Exception $e) {
    json_error('更新失败');
}
