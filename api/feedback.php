<?php
/**
 * 反馈 API
 */
require_once dirname(__FILE__) . '/../config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$action = isset($input['action']) ? $input['action'] : '';

switch ($action) {
    case 'like':
        if (!is_logged_in()) json_error('请先登录', 401);
        $user = current_user();
        if (is_banned($user)) json_error('账号被封禁', 403);
        $feedbackId = isset($input['feedback_id']) ? intval($input['feedback_id']) : 0;
        if (!$feedbackId) json_error('参数错误');
        $userId = intval($user['id']);

        $existing = db()->fetchOne("SELECT id FROM feedback_likes WHERE feedback_id = ? AND user_id = ?", array($feedbackId, $userId));
        if ($existing) {
            db()->delete('feedback_likes', 'id = ?', array($existing['id']));
            $liked = false;
        } else {
            db()->insert('feedback_likes', array('feedback_id' => $feedbackId, 'user_id' => $userId));
            $liked = true;
        }
        $total = db()->fetchOne("SELECT COUNT(*) as cnt FROM feedback_likes WHERE feedback_id = ?", array($feedbackId));
        json_success(array('liked' => $liked, 'likes' => intval($total['cnt'])));
        break;

    case 'create':
        if (!is_logged_in()) json_error('请先登录', 401);
        $user = current_user();
        if (is_banned($user)) json_error('账号被封禁', 403);
        $title = isset($input['title']) ? trim($input['title']) : '';
        $content = isset($input['content']) ? trim($input['content']) : '';
        if (empty($title) || empty($content)) json_error('请填写标题和内容');
        if (mb_strlen($title) > 100) json_error('标题过长');
        $id = db()->insert('feedbacks', array(
            'user_id' => intval($user['id']),
            'title' => $title,
            'content' => $content,
            'status' => 'pending'
        ));
        json_success(array('id' => $id), '反馈已提交');
        break;

    case 'reply':
        if (!is_logged_in()) json_error('请先登录', 401);
        $user = current_user();
        if (is_banned($user)) json_error('账号被封禁', 403);
        $feedbackId = isset($input['feedback_id']) ? intval($input['feedback_id']) : 0;
        $content = isset($input['content']) ? trim($input['content']) : '';
        if (!$feedbackId || empty($content)) json_error('参数错误');

        $feedback = db()->fetchOne("SELECT id FROM feedbacks WHERE id = ?", array($feedbackId));
        if (!$feedback) json_error('反馈不存在');

        $replyId = db()->insert('feedback_replies', array(
            'feedback_id' => $feedbackId,
            'user_id' => intval($user['id']),
            'content' => $content,
            'is_admin' => is_admin() ? 1 : 0
        ));
        json_success(array('id' => $replyId, 'is_admin' => is_admin()), '回复成功');
        break;

    default:
        json_error('未知操作');
}
