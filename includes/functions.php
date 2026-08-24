<?php
/**
 * 通用函数库
 */

// 防止直接访问
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

require_once dirname(__FILE__) . '/database.php';

/**
 * 获取数据库实例
 */
function db() {
    return Database::getInstance();
}

/**
 * 安全输出 - 防止XSS
 */
function e($str) {
    if ($str === null) return '';
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 重定向
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * 获取当前用户
 */
function current_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $userId = intval($_SESSION['user_id']);
    if ($userId <= 0) return null;
    try {
        $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));
        return $user;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 检查是否登录
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 检查是否是管理员
 */
function is_admin() {
    try {
        $user = current_user();
        return $user && intval($user['is_admin']) === 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 检查用户是否被封禁
 */
function is_banned($user = null) {
    try {
        if ($user === null) {
            $user = current_user();
        }
        if (!$user) return false;
        if (intval($user['status']) === 0) {
            if (!empty($user['ban_until'])) {
                $banUntil = strtotime($user['ban_until']);
                if ($banUntil > time()) {
                    return true;
                }
                // 封禁时间已过，自动解封
                @db()->update('users', array('status' => 1, 'ban_time' => null, 'ban_until' => null, 'ban_reason' => null), 'id = ?', array($user['id']));
                return false;
            }
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 生成CSRF Token
 */
function csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * 验证CSRF Token
 */
function verify_csrf($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * 生成随机字符串
 */
function random_str($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $string;
}

/**
 * 生成数字验证码
 */
function generate_email_code() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * 发送邮件 - 使用SMTP（兼容PHP5+，无需额外扩展，支持SSL）
 */
function send_email($to, $subject, $body, $altBody = '') {
    require_once ROOT_PATH . '/includes/smtp.php';
    $mail = new SMTPMailer();
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($to);
    $mail->setSubject($subject);
    $mail->setBody($body);
    if ($altBody) {
        $mail->setAltBody($altBody);
    }
    return $mail->send();
}

/**
 * 发送邮箱验证码
 */
function send_email_code($email, $code, $type = 'register') {
    $typeText = $type === 'register' ? '注册' : '重置密码';
    $subject = SMTP_FROM_NAME . " - 您的{$typeText}验证码";

    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px;">
        <div style="background: white; padding: 30px; border-radius: 8px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h1 style="color: #7c3aed; margin: 0; font-size: 28px;">🎬 ' . SMTP_FROM_NAME . '</h1>
                <p style="color: #666; margin-top: 10px;">邮箱验证码</p>
            </div>
            <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 30px; border-radius: 8px; text-align: center; margin: 20px 0;">
                <p style="color: #333; font-size: 16px; margin: 0 0 15px 0;">您的' . $typeText . '验证码是：</p>
                <div style="background: white; padding: 20px 40px; border-radius: 8px; display: inline-block; border: 2px dashed #7c3aed;">
                    <span style="font-size: 36px; font-weight: bold; color: #7c3aed; letter-spacing: 10px;">' . $code . '</span>
                </div>
                <p style="color: #666; font-size: 14px; margin-top: 15px;">验证码 10 分钟内有效</p>
            </div>
            <p style="color: #999; font-size: 12px; text-align: center;">如果您没有进行此操作，请忽略此邮件。</p>
            <div style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px; text-align: center;">
                <p style="color: #999; font-size: 12px; margin: 0;">© ' . date('Y') . ' ' . SMTP_FROM_NAME . ' 版权所有</p>
            </div>
        </div>
    </div>';

    $altBody = "您的{$typeText}验证码是：{$code}\n验证码 10 分钟内有效。\n如果您没有进行此操作，请忽略此邮件。";

    return send_email($email, $subject, $html, $altBody);
}

/**
 * 发送封禁通知邮件
 */
function send_ban_email($user, $banTime, $banUntil, $reason = '') {
    $subject = SMTP_FROM_NAME . " - 账号封禁通知";

    $banTimeStr = date('Y-m-d H:i:s', strtotime($banTime));
    $banUntilStr = $banUntil ? date('Y-m-d H:i:s', strtotime($banUntil)) : '永久';

    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 10px;">
        <div style="background: white; padding: 30px; border-radius: 8px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 60px;">🚫</div>
                <h1 style="color: #dc2626; margin: 10px 0; font-size: 24px;">账号封禁通知</h1>
                <p style="color: #666;">尊敬的 <strong>' . e($user['username']) . '</strong>：</p>
            </div>
            <div style="background: #fef2f2; padding: 20px; border-radius: 8px; border-left: 4px solid #ef4444;">
                <p style="color: #333; margin: 0 0 15px 0;"><strong>很抱歉地通知您，您的账号已被封禁。</strong></p>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #fee2e2;">
                        <td style="padding: 8px 0; color: #666; width: 100px;">封禁时间：</td>
                        <td style="padding: 8px 0; color: #333;">' . $banTimeStr . '</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #fee2e2;">
                        <td style="padding: 8px 0; color: #666;">解封时间：</td>
                        <td style="padding: 8px 0; color: #dc2626; font-weight: bold;">' . $banUntilStr . '</td>
                    </tr>
                    ' . ($reason ? '<tr><td style="padding: 8px 0; color: #666; vertical-align: top;">封禁原因：</td><td style="padding: 8px 0; color: #333;">' . e($reason) . '</td></tr>' : '') . '
                </table>
            </div>
            <p style="color: #666; font-size: 14px; margin-top: 20px; text-align: center;">
                如有疑问，请通过网站反馈功能联系管理员。
            </p>
            <div style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px; text-align: center;">
                <p style="color: #999; font-size: 12px; margin: 0;">© ' . date('Y') . ' ' . SMTP_FROM_NAME . ' 版权所有</p>
            </div>
        </div>
    </div>';

    $altBody = "账号封禁通知\n\n尊敬的 {$user['username']}：\n很抱歉地通知您，您的账号已被封禁。\n\n封禁时间：{$banTimeStr}\n解封时间：{$banUntilStr}\n" . ($reason ? "封禁原因：{$reason}\n" : "") . "\n如有疑问，请通过网站反馈功能联系管理员。";

    return send_email($user['email'], $subject, $html, $altBody);
}

/**
 * 发送通知邮件
 */
function send_notification_email($to, $title, $content) {
    $subject = SMTP_FROM_NAME . " - " . $title;

    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); border-radius: 10px;">
        <div style="background: white; padding: 30px; border-radius: 8px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 50px;">📢</div>
                <h1 style="color: #7c3aed; margin: 10px 0; font-size: 24px;">' . e($title) . '</h1>
            </div>
            <div style="background: #faf5ff; padding: 20px; border-radius: 8px; border-left: 4px solid #7c3aed;">
                <div style="color: #333; line-height: 1.8;">' . nl2br(e($content)) . '</div>
            </div>
            <div style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px; text-align: center;">
                <p style="color: #999; font-size: 12px; margin: 0;">© ' . date('Y') . ' ' . SMTP_FROM_NAME . ' 版权所有</p>
            </div>
        </div>
    </div>';

    $altBody = "{$title}\n\n{$content}\n\n© " . date('Y') . ' ' . SMTP_FROM_NAME;

    return send_email($to, $subject, $html, $altBody);
}

/**
 * 获取网站设置
 */
function get_setting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $settings = array();
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // 表可能还不存在
        }
    }
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * 获取主题颜色
 */
function get_theme_color() {
    return get_setting('theme_color', '#7c3aed');
}

/**
 * 获取客户端IP
 */
function get_client_ip() {
    if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ip[0]);
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = '127.0.0.1';
    }
    return $ip;
}

/**
 * 格式化时间
 */
function format_time($datetime, $full = false) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    if ($full) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return '刚刚';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 86400 * 7) {
        return floor($diff / 86400) . '天前';
    } elseif ($diff < 86400 * 30) {
        return floor($diff / (86400 * 7)) . '周前';
    } else {
        return date('Y-m-d', $timestamp);
    }
}

/**
 * JSON响应
 */
function json_response($data, $code = 200, $message = 'success') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'code' => $code,
        'message' => $message,
        'data' => $data
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 成功响应
 */
function json_success($data = null, $message = '操作成功') {
    json_response($data, 200, $message);
}

/**
 * 失败响应
 */
function json_error($message = '操作失败', $code = 400) {
    json_response(null, $code, $message);
}

/**
 * 获取分页参数（支持自定义每页数量）
 */
function get_pagination_params($customPerPage = null) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $defaultPer = $customPerPage ? $customPerPage : PER_PAGE;
    $perPage = isset($_GET['per_page']) ? min(500, max(1, intval($_GET['per_page']))) : $defaultPer;
    $offset = ($page - 1) * $perPage;
    return array($page, $perPage, $offset);
}

/**
 * 更新网站设置
 */
function update_setting($key, $value) {
    try {
        $exists = db()->fetchOne("SELECT setting_key FROM settings WHERE setting_key = ?", array($key));
        if ($exists) {
            db()->update('settings', array(
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ), 'setting_key = ?', array($key));
        } else {
            db()->insert('settings', array(
                'setting_key' => $key,
                'setting_value' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ));
        }
        // 清除静态缓存
        static $settingsSvc = null;
        if (class_exists('ReflectionProperty')) {
            // 无法直接清除 get_setting 的 static，简单处理：存到运行时内存
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 根据用户ID获取用户名
 */
function get_user_name($userId) {
    static $cache = array();
    $userId = intval($userId);
    if ($userId <= 0) return '未知';
    if (isset($cache[$userId])) return $cache[$userId];
    try {
        $u = db()->fetchOne("SELECT username FROM users WHERE id = ?", array($userId));
        $cache[$userId] = $u ? $u['username'] : '用户'.$userId;
    } catch (Exception $e) {
        $cache[$userId] = '用户'.$userId;
    }
    return $cache[$userId];
}

/**
 * 直接输出任意JSON数组
 */
function json_output($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 发送HTML格式邮件
 */
function send_html_email($to, $title, $htmlContent) {
    // 使用通用send_email，第三个参数是HTML，第四个是纯文本备用
    $altBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlContent)));
    return send_email($to, $title, $htmlContent, $altBody);
}

/**
 * 调整颜色亮度（统一函数，全局可用）
 */
function adjustColor($color, $percent) {
    $color = str_replace('#', '', (string)$color);
    if (strlen($color) !== 6) {
        return strlen($color) === 7 ? '#'.str_replace('#','',$color) : '#7c3aed';
    }
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    $r = max(0, min(255, $r + round(255 * $percent / 100)));
    $g = max(0, min(255, $g + round(255 * $percent / 100)));
    $b = max(0, min(255, $b + round(255 * $percent / 100)));
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}
