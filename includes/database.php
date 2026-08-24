<?php
/**
 * 数据库连接类 - 使用PDO，兼容所有PHP版本
 */
class Database {
    private static $instance = null;
    private $pdo;
    private $error;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true, // 兼容更多服务器
            );
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
            }
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->pdo->exec("SET NAMES " . DB_CHARSET);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            // 友好提示：未导入install.sql时不白屏
            $msg = $e->getMessage();
            if (strpos($msg, 'Unknown database') !== false || strpos($msg, 'Access denied') !== false || strpos($msg, 'can\'t connect') !== false || strpos($msg, 'SQLSTATE') !== false) {
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>数据库连接错误 - Jay影视</title>';
                echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:linear-gradient(135deg,#1a1a25,#2d1b69);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}';
                echo '.card{max-width:560px;width:100%;background:rgba(255,255,255,0.06);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:32px;}';
                echo '.tag{display:inline-block;padding:4px 14px;background:#ef4444;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:14px;}';
                echo 'h1{margin:0 0 14px;font-size:22px;}code{background:rgba(0,0,0,0.3);padding:8px 12px;border-radius:6px;font-size:12px;display:block;margin:14px 0;white-space:pre-wrap;word-break:break-all;}';
                echo 'ol{padding-left:20px;line-height:1.9;}li{margin-bottom:6px;}.tip{background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);padding:12px 16px;border-radius:10px;margin-top:16px;font-size:13px;line-height:1.7;}</style></head><body>';
                echo '<div class="card"><span class="tag">❌ 数据库连接失败</span>';
                echo '<h1>网站暂时无法访问</h1><p style="color:rgba(255,255,255,0.75);line-height:1.8;">请先在 <b>config/config.php</b> 文件中填写正确的数据库连接信息，并在 phpMyAdmin 导入 <b>install.sql</b> 文件。</p>';
                echo '<code>' . htmlspecialchars($msg) . '</code>';
                echo '<div class="tip"><b>🛠️ InfinityFree 部署步骤：</b><ol>';
                echo '<li>登录 cPanel 创建 MySQL 数据库和用户，并把用户加入数据库（授予全部权限）</li>';
                echo '<li>修改 config/config.php 中 DB_HOST / DB_NAME / DB_USER / DB_PASS（DB_HOST一般是 sqlxxx.epizy.com 可在cPanel看到）</li>';
                echo '<li>打开 phpMyAdmin，选中该数据库，导入根目录下的 install.sql 文件</li>';
                echo '<li>刷新首页即可访问</li>';
                echo '</ol></div>';
                echo '</div></body></html>';
                die;
            }
            die("数据库连接失败: " . htmlspecialchars($msg));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * 转义字符串（PDO quote）
     */
    public function quote($value) {
        if (is_int($value) || is_float($value)) return "'" . $value . "'";
        if ($this->pdo) return $this->pdo->quote($value);
        return "'" . addslashes($value) . "'";
    }

    /**
     * 执行原生SQL（返回statement）
     */
    public function raw($sql, $params = array()) {
        return $this->query($sql, $params);
    }

    /**
     * 获取单列首行值
     */
    public function fetchColumn($sql, $params = array(), $col = 0) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($col);
    }

    public function query($sql, $params = array()) {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            return $stmt;
        } catch (PDOException $e) {
            error_log("SQL Error: " . $e->getMessage() . " SQL: " . $sql);
            throw $e;
        }
    }

    public function fetchAll($sql, $params = array()) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = array()) {
        $stmt = $this->query($sql, $params);
        $r = $stmt->fetch();
        return $r ? $r : null;
    }

    public function insert($table, $data) {
        if (empty($data)) return 0;
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO " . $table . " (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = array()) {
        if (empty($data)) return 0;
        $fields = array();
        $params = array();
        foreach ($data as $key => $value) {
            $fields[] = $key . " = ?";
            $params[] = $value;
        }
        $sql = "UPDATE " . $table . " SET " . implode(',', $fields) . " WHERE " . $where;
        $params = array_merge($params, is_array($whereParams) ? $whereParams : array($whereParams));
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, $whereParams = array()) {
        $sql = "DELETE FROM " . $table . " WHERE " . $where;
        $stmt = $this->query($sql, is_array($whereParams) ? $whereParams : array($whereParams));
        return $stmt->rowCount();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
