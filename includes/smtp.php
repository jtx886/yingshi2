<?php
/**
 * 轻量级SMTP邮件发送类 - 无需PHPMailer，兼容所有PHP版本
 * 支持SSL/TLS
 */
class SMTPMailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from;
    private $fromName;
    private $to = array();
    private $subject;
    private $body;
    private $altBody;
    private $timeout = 30;
    private $debug = false;

    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->user = SMTP_USER;
        $this->pass = SMTP_PASS;
        $this->from = SMTP_FROM;
        $this->fromName = SMTP_FROM_NAME;
    }

    public function setFrom($email, $name = '') {
        $this->from = $email;
        $this->fromName = $name;
    }

    public function addAddress($email, $name = '') {
        $this->to[] = array('email' => $email, 'name' => $name);
    }

    public function setSubject($subject) {
        $this->subject = $subject;
    }

    public function setBody($body) {
        $this->body = $body;
    }

    public function setAltBody($altBody) {
        $this->altBody = $altBody;
    }

    public function setDebug($debug) {
        $this->debug = $debug;
    }

    private function encodeHeader($str) {
        if (preg_match('/[\x80-\xFF]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }

    private function getBoundary() {
        return md5(uniqid(time()));
    }

    public function send() {
        if (empty($this->to)) {
            return false;
        }

        $boundary = $this->getBoundary();

        // 构建邮件头
        $headers  = "Date: " . date('r') . "\r\n";
        $headers .= "Return-Path: <" . $this->from . ">\r\n";
        $headers .= "From: " . $this->encodeHeader($this->fromName) . " <" . $this->from . ">\r\n";

        $toList = array();
        foreach ($this->to as $recipient) {
            $toList[] = $this->encodeHeader($recipient['name']) . " <" . $recipient['email'] . ">";
        }
        $headers .= "To: " . implode(', ', $toList) . "\r\n";
        $headers .= "Subject: " . $this->encodeHeader($this->subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
        $headers .= "X-Mailer: PHP/JayMailer\r\n";

        // 构建邮件正文
        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->altBody ? $this->altBody : strip_tags($this->body)));

        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->body));

        $body .= "--" . $boundary . "--\r\n";

        // 连接SMTP服务器
        $host = ($this->port == 465) ? "ssl://" . $this->host : $this->host;
        $fp = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

        if (!$fp) {
            error_log("SMTP Connection Failed: {$errno} - {$errstr}");
            return false;
        }

        stream_set_timeout($fp, $this->timeout);

        $response = $this->readResponse($fp);
        if (strpos($response, '220') === false) {
            fclose($fp);
            return false;
        }

        // EHLO
        $this->sendCommand($fp, "EHLO " . $this->getHostname());
        $response = $this->readResponse($fp);
        if (strpos($response, '250') === false) {
            // 尝试HELO
            $this->sendCommand($fp, "HELO " . $this->getHostname());
            $response = $this->readResponse($fp);
            if (strpos($response, '250') === false) {
                fclose($fp);
                return false;
            }
        }

        // 如果是587端口，需要STARTTLS
        if ($this->port == 587) {
            $this->sendCommand($fp, "STARTTLS");
            $response = $this->readResponse($fp);
            if (strpos($response, '220') === false) {
                fclose($fp);
                return false;
            }
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand($fp, "EHLO " . $this->getHostname());
            $response = $this->readResponse($fp);
        }

        // 认证登录
        $this->sendCommand($fp, "AUTH LOGIN");
        $response = $this->readResponse($fp);
        if (strpos($response, '334') === false) {
            fclose($fp);
            return false;
        }

        $this->sendCommand($fp, base64_encode($this->user));
        $response = $this->readResponse($fp);
        if (strpos($response, '334') === false) {
            fclose($fp);
            return false;
        }

        $this->sendCommand($fp, base64_encode($this->pass));
        $response = $this->readResponse($fp);
        if (strpos($response, '235') === false) {
            error_log("SMTP Auth Failed: " . $response);
            fclose($fp);
            return false;
        }

        // MAIL FROM
        $this->sendCommand($fp, "MAIL FROM:<" . $this->from . ">");
        $response = $this->readResponse($fp);
        if (strpos($response, '250') === false) {
            fclose($fp);
            return false;
        }

        // RCPT TO
        foreach ($this->to as $recipient) {
            $this->sendCommand($fp, "RCPT TO:<" . $recipient['email'] . ">");
            $response = $this->readResponse($fp);
            if (strpos($response, '250') === false && strpos($response, '251') === false) {
                fclose($fp);
                return false;
            }
        }

        // DATA
        $this->sendCommand($fp, "DATA");
        $response = $this->readResponse($fp);
        if (strpos($response, '354') === false) {
            fclose($fp);
            return false;
        }

        // 发送邮件内容
        fwrite($fp, $headers . $body . "\r\n.\r\n");
        $response = $this->readResponse($fp);
        if (strpos($response, '250') === false) {
            error_log("SMTP Data Failed: " . $response);
            fclose($fp);
            return false;
        }

        // QUIT
        $this->sendCommand($fp, "QUIT");
        fclose($fp);

        return true;
    }

    private function sendCommand($fp, $command) {
        if ($this->debug) {
            echo "SEND: {$command}\n";
        }
        fwrite($fp, $command . "\r\n");
    }

    private function readResponse($fp) {
        $response = '';
        while ($line = fgets($fp, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] == ' ') {
                break;
            }
        }
        if ($this->debug) {
            echo "RECV: {$response}\n";
        }
        return $response;
    }

    private function getHostname() {
        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }
        return 'localhost';
    }
}
