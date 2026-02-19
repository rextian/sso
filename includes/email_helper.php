<?php
/**
 * REXTIAN SSO - 邮件发送辅助
 * 支持 SMTP 或 Mock
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings_helper.php';

/**
 * 发送邮件
 * @param string $to 收件人邮箱
 * @param string $subject 主题
 * @param string $bodyHtml HTML 正文
 * @param string $bodyText 纯文本正文（可选）
 * @return array ['success' => bool, 'mock' => bool, 'message' => string]
 */
function sendEmail(string $to, string $subject, string $bodyHtml, string $bodyText = ''): array {
    $host = getSetting('smtp_host');
    $port = (int) (getSetting('smtp_port') ?: 587);
    $encryption = getSetting('smtp_encryption') ?: '';
    $username = getSetting('smtp_username');
    $password = getSetting('smtp_password');
    $fromEmail = getSetting('smtp_from_email') ?: ($username ?: 'noreply@localhost');
    $fromName = getSetting('smtp_from_name') ?: 'REXTIAN ID';

    if (!$host || !$username || !$password) {
        error_log("[Email Mock] to={$to} subject={$subject}");
        return ['success' => true, 'mock' => true];
    }

    $smtp = new SmtpClient($host, $port, $encryption, $username, $password);
    $result = $smtp->send($fromEmail, $fromName, $to, $subject, $bodyHtml, $bodyText);
    return $result ? ['success' => true, 'mock' => false] : ['success' => false, 'message' => $smtp->getLastError()];
}

/**
 * 简易 SMTP 客户端
 */
class SmtpClient {
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $socket;
    private $lastError = '';

    public function __construct(string $host, int $port, string $encryption, string $username, string $password) {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = strtolower($encryption);
        $this->username = $username;
        $this->password = $password;
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function send(string $fromEmail, string $fromName, string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool {
        $prefix = $this->encryption === 'ssl' ? 'ssl://' : '';
        $addr = $prefix . $this->host . ':' . $this->port;
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $this->socket = @stream_socket_client($addr, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            $this->lastError = "连接失败: {$errstr}";
            return false;
        }
        stream_set_timeout($this->socket, 15);

        if (!$this->readResponse()) return false;
        $this->smtpCmd("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        // STARTTLS：端口 25/587 等均支持，不限于 587
        if ($this->encryption === 'tls') {
            if (!$this->smtpCmd('STARTTLS')) return false;
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = 'STARTTLS 加密升级失败';
                return false;
            }
            $this->smtpCmd("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }
        $this->smtpCmd('AUTH LOGIN');
        $this->smtpCmd(base64_encode($this->username));
        $this->smtpCmd(base64_encode($this->password));
        $this->smtpCmd("MAIL FROM:<{$fromEmail}>");
        $this->smtpCmd("RCPT TO:<{$to}>");
        $this->smtpCmd('DATA');

        $fromEnc = $this->encodeHeader($fromName);
        $boundary = '----=_Part_' . bin2hex(random_bytes(8));
        $headers = "From: {$fromEnc} <{$fromEmail}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . ($bodyText ?: strip_tags($bodyHtml)) . "\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$bodyHtml}\r\n--{$boundary}--\r\n.\r\n";

        $ok = $this->smtpCmd($headers . $body);
        $this->smtpCmd('QUIT');
        fclose($this->socket);
        return $ok;
    }

    private function smtpCmd(string $line): bool {
        fwrite($this->socket, $line . "\r\n");
        return $this->readResponse();
    }

    private function readResponse(): bool {
        $reply = '';
        while ($line = fgets($this->socket, 515)) {
            $reply .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $code = (int) substr($reply, 0, 3);
        if ($code >= 400) {
            $this->lastError = trim($reply);
            return false;
        }
        return true;
    }

    private function encodeHeader(string $str): string {
        if (preg_match('/^[\x20-\x7E]*$/', $str)) return $str;
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }
}
