<?php

declare(strict_types=1);

namespace App\Mail;

use Core\Log;

class Mailer
{
    private MailerConfig $config;
    private string       $lastError = '';
    private Log          $logger;

    public function __construct(MailerConfig $config)
    {
        $this->config = $config;
        $this->logger = new Log();
    }

    public static function make(): self
    {
        $rows     = (new \Core\Model('settings'))->get() ?: [];
        $settings = array_column($rows, 'value', 'key');
        return new self(MailerConfig::fromSettings($settings));
    }

    public function send(MailMessage $message): bool
    {
        if (!$message->getTo() || !$message->getSubject()) {
            $this->lastError = 'Recipient and subject are required.';
            return false;
        }

        $fromEmail = $message->getFromEmail() ?: $this->config->fromAddress;
        $fromName  = $message->getFromName()  ?: $this->config->fromName;

        if (!$fromEmail) {
            $this->lastError = 'Sender email is not configured.';
            return false;
        }

        $filled = (clone $message)->from($fromEmail, $fromName);

        try {
            if ($this->config->driver === 'smtp') {
                return $this->sendViaSmtp($filled);
            }
            return $this->sendViaMail($filled);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->logger->write('mailer/mailer', 'error', 'Mailer exception: ' . $e->getMessage());
            return false;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    // ── PHP mail() driver ─────────────────────────────────────────────────────

    private function sendViaMail(MailMessage $m): bool
    {
        $boundary  = '----=_Part_' . md5(uniqid('', true));
        $fromLabel = $m->getFromName()
            ? '=?UTF-8?B?' . base64_encode($m->getFromName()) . '?= <' . $m->getFromEmail() . '>'
            : $m->getFromEmail();

        $toLabel = $m->getToName()
            ? '=?UTF-8?B?' . base64_encode($m->getToName()) . '?= <' . $m->getTo() . '>'
            : $m->getTo();

        $subject = '=?UTF-8?B?' . base64_encode($m->getSubject()) . '?=';

        $headers  = "From: {$fromLabel}\r\n";
        $headers .= "Reply-To: " . ($m->getReplyTo() ?: $m->getFromEmail()) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: VertextCMS/Mailer\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($m->getTextBody() ?: strip_tags($m->getHtmlBody()))) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($m->getHtmlBody())) . "\r\n";
        $body .= "--{$boundary}--";

        $ok = @mail($toLabel, $subject, $body, $headers);

        if (!$ok) {
            $this->lastError = 'mail() returned false — check server mail configuration.';
            $this->logger->write('mailer/mailer', 'warning', $this->lastError);
        }

        return $ok;
    }

    // ── Native SMTP driver ────────────────────────────────────────────────────

    private function sendViaSmtp(MailMessage $m): bool
    {
        $host       = $this->config->host;
        $port       = $this->config->port;
        $encryption = $this->config->encryption;
        $timeout    = $this->config->timeout;

        $socketHost = ($encryption === 'ssl') ? "ssl://{$host}" : $host;

        $sock = @fsockopen($socketHost, $port, $errno, $errstr, $timeout);
        if (!$sock) {
            $this->lastError = "SMTP connect failed ({$errno}): {$errstr}";
            return false;
        }

        stream_set_timeout($sock, $timeout);

        $read = $this->smtpRead($sock);
        if (!str_starts_with($read, '220')) {
            $this->lastError = "SMTP greeting error: {$read}";
            fclose($sock);
            return false;
        }

        $localHost = gethostname() ?: 'localhost';

        $this->smtpWrite($sock, "EHLO {$localHost}");
        $this->smtpRead($sock); // EHLO response

        if ($encryption === 'tls') {
            $this->smtpWrite($sock, 'STARTTLS');
            $tls = $this->smtpRead($sock);
            if (!str_starts_with($tls, '220')) {
                $this->lastError = "STARTTLS failed: {$tls}";
                fclose($sock);
                return false;
            }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = 'TLS negotiation failed.';
                fclose($sock);
                return false;
            }
            $this->smtpWrite($sock, "EHLO {$localHost}");
            $this->smtpRead($sock);
        }

        if ($this->config->username) {
            $this->smtpWrite($sock, 'AUTH LOGIN');
            $auth = $this->smtpRead($sock);
            if (!str_starts_with($auth, '334')) {
                $this->lastError = "AUTH LOGIN not accepted: {$auth}";
                fclose($sock);
                return false;
            }
            $this->smtpWrite($sock, base64_encode($this->config->username));
            $userPrompt = $this->smtpRead($sock);
            if (!str_starts_with($userPrompt, '334')) {
                $this->lastError = "Username not accepted: {$userPrompt}";
                fclose($sock);
                return false;
            }
            $this->smtpWrite($sock, base64_encode($this->config->password));
            $passResp = $this->smtpRead($sock);
            if (!str_starts_with($passResp, '235')) {
                $this->lastError = "Authentication failed: {$passResp}";
                fclose($sock);
                return false;
            }
        }

        $this->smtpWrite($sock, "MAIL FROM:<{$m->getFromEmail()}>");
        $from = $this->smtpRead($sock);
        if (!str_starts_with($from, '250')) {
            $this->lastError = "MAIL FROM rejected: {$from}";
            fclose($sock);
            return false;
        }

        $this->smtpWrite($sock, "RCPT TO:<{$m->getTo()}>");
        $rcpt = $this->smtpRead($sock);
        if (!str_starts_with($rcpt, '250')) {
            $this->lastError = "RCPT TO rejected: {$rcpt}";
            fclose($sock);
            return false;
        }

        $this->smtpWrite($sock, 'DATA');
        $data = $this->smtpRead($sock);
        if (!str_starts_with($data, '354')) {
            $this->lastError = "DATA not accepted: {$data}";
            fclose($sock);
            return false;
        }

        $this->smtpWrite($sock, $this->buildMimeMessage($m));
        $this->smtpWrite($sock, '.');
        $sent = $this->smtpRead($sock);

        $this->smtpWrite($sock, 'QUIT');
        fclose($sock);

        if (!str_starts_with($sent, '250')) {
            $this->lastError = "Message rejected: {$sent}";
            return false;
        }

        return true;
    }

    private function buildMimeMessage(MailMessage $m): string
    {
        $boundary = '----=_Part_' . md5(uniqid('', true));

        $fromLabel = $m->getFromName()
            ? '=?UTF-8?B?' . base64_encode($m->getFromName()) . '?= <' . $m->getFromEmail() . '>'
            : $m->getFromEmail();

        $toLabel = $m->getToName()
            ? '=?UTF-8?B?' . base64_encode($m->getToName()) . '?= <' . $m->getTo() . '>'
            : $m->getTo();

        $subject = '=?UTF-8?B?' . base64_encode($m->getSubject()) . '?=';

        $msg  = "From: {$fromLabel}\r\n";
        $msg .= "To: {$toLabel}\r\n";
        $msg .= "Subject: {$subject}\r\n";
        $msg .= "Reply-To: " . ($m->getReplyTo() ?: $m->getFromEmail()) . "\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $msg .= "X-Mailer: VertextCMS/Mailer\r\n";
        $msg .= "\r\n";
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($m->getTextBody() ?: strip_tags($m->getHtmlBody())));
        $msg .= "\r\n--{$boundary}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($m->getHtmlBody()));
        $msg .= "\r\n--{$boundary}--";

        // Dot-stuffing: lines beginning with '.' must be doubled
        $lines = explode("\r\n", $msg);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        return implode("\r\n", $lines);
    }

    private function smtpWrite($sock, string $data): void
    {
        fwrite($sock, $data . "\r\n");
    }

    private function smtpRead($sock): string
    {
        $response = '';
        while ($line = fgets($sock, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }
}
