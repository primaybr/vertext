<?php

declare(strict_types=1);

namespace App\Mail;

class MailerConfig
{
    public string $driver      = 'mail'; // 'mail' | 'smtp'
    public string $host        = '';
    public int    $port        = 587;
    public string $username    = '';
    public string $password    = '';
    public string $encryption  = 'tls';  // 'tls' | 'ssl' | ''
    public string $fromAddress = '';
    public string $fromName    = 'Vertext CMS';
    public int    $timeout     = 15;

    public static function fromSettings(array $settings): self
    {
        $c              = new self();
        $c->driver      = $settings['mail_driver']        ?? 'mail';
        $c->host        = $settings['mail_host']          ?? '';
        $c->port        = (int) ($settings['mail_port']   ?? 587);
        $c->username    = $settings['mail_username']      ?? '';
        $c->password    = $settings['mail_password']      ?? '';
        $c->encryption  = $settings['mail_encryption']    ?? 'tls';
        $c->fromAddress = $settings['mail_from_address']  ?? ($settings['admin_email'] ?? '');
        $c->fromName    = $settings['mail_from_name']     ?? ($settings['site_name']   ?? 'Vertext CMS');
        return $c;
    }
}
