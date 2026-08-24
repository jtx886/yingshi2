<?php
/**
 * 收藏 API
 */
require_once dirname(__FILE__) . '/../config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 未登录
if (!is_logged_in()) {
    json_error('请先登录', 401);
}

$user = current_user();
if (!$user || is_banned($user)) {
    json_error('账号不可用', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$action = isset($input['action']) ? $input['action'] : '';
$userId = intval($user['id']);

switch ($action) {
    case 'toggle':
        $movieId = isset($input['movie_id']) ? strval($input['movie_id']) : '';
        $movieName = isset($input['movie_name']) ? trim($input['movie_name']) : '';
        if (empty($movieId)) json_error('参数错误');
        
        $existing = db()->fetchOne("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?", array($userId, $movieId));
        if ($existing) {
            db()->delete('favorites', 'id = ?', array($existing['id']));
            json_success(array('favorited' => false), '已取消收藏');
        } else {
            $data = array(
                'user_id' => $userId,
                'movie_id' => $movieId,
                'movie_name' => $movieName,
                'poster' => isset($input['poster']) ? $input['poster'] : '',
                'type' => isset($input['type']) ? $input['type'] : 'movie',
            );
            if (isset($input['year']) && !empty($input['year'])) $data['year'] = intval($input['year']);
            if (isset($input['rating']) && !empty($input['rating'])) $data['rating'] = floatval($input['rating']);
            db()->insert('favorites', $data);
            json_success(array('favorited' => true), '已添加收藏');
        }
        break;

    case 'remove':
        $movieId = isset($input['movie_id']) ? strval($input['movie_id']) : '';
        if (empty($movieId)) json_error('参数错误');
        db()->delete('favorites', 'user_id = ? AND movie_id = ?', array($userId, $movieId));
        json_success(null, '已删除');
        break;

    case 'check':
        $movieId = isset($input['movie_id']) ? strval($input['movie_id']) : '';
        $existing = db()->fetchOne("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?", array($userId, $movieId));
        json_success(array('favorited' => $existing ? true : false));
        break;

    default:
        json_error('未知操作');
}
